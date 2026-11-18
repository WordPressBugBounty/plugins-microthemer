<?php

namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AdminInitTrait {

	var $activation_function_ran;
	var $all_pages = array();
	var $user_memory_limit;
	
	function init(){

		// setup text domain
		$this->loadTextDomain();

		// get the directory paths
		include dirname(__FILE__) .'/../get-dir-paths.inc.php';

		// for media queries
		$this->unq_base = uniqid('mtmq');

		// Setup essential vars
		// Page slugs include ".php" (e.g. tvr-microthemer.php). Do not use
		// sanitize_key() — it strips dots and would skip initMicrothemerPage().
		$raw_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		$page = in_array($raw_page, $this->getAllPluginPages(), true) ? $raw_page : false;
		$this->preferences = get_option($this->preferencesName);

		// files from the pre-7.6 location move to uploads/microthemer (once per site)
		$this->maybeMoveStorage();

		// setup reference to content class
		if ($this->hasContentCapability()){
			$this->contentClass = new Content\AdminContent($this);
		}

		// components that ship in some builds only; they connect through extFilter()/extAction()
		foreach (glob(dirname(__FILE__) . '/Extensions/*.php') ?: array() as $extension_file){
			$extension_class = __NAMESPACE__ . '\Extensions\\' . basename($extension_file, '.php');
			$this->extensions[] = new $extension_class($this);
		}

		// AI assistant (AI-enabled builds only — see src/AIPanel.php for why the wp.org
		// package ships without it)
		if (file_exists(dirname(__FILE__) . '/AIPanel.php')){
			$this->aiPanel = new AIPanel($this);
		}

		// UI driven by the Amender JS module (mt-html.js). Tied to the build rather than the
		// content capability: the static program data carries its templates
		if ($this->hasContentDir()){
			$this->moduleUI = new Content\ModuleUI($this);
		}

		// Set the app name
		$this->setAppName();

		// bail if server doesn't meet the minimum requirements
		if (!$this->checkMinimumRequirements()){
			return;
		}

		// hook minimal functionality on all admin pages
		$this->hookAjax();
		$this->hookAdminMenu();
		$this->hookAdminBarShortcut();
		$this->hookActivation();
		$this->hookOrphanedProDataNotice();
		$this->hookPublishedCustomCodeCleanup();

		// Load additional functionality on MT admin pages
		if (in_array($page, $this->getAllPluginPages()) ) {
			$this->initMicrothemerPage($page);
		}

		// Non-MT page
		// - note MT admin area styling only works for admin level priviledges, without some rejigging
		else {

			// Pass in the context of loading assets or actually being able to edit the admin area with point and click
			$context = !empty($this->preferences['admin_asset_editing']) ? 'edit' : 'respond';

			new AssetAuth($context);
		}

	}

	function initMicrothemerPage($page){

		$this->new_version = (empty($this->preferences['version'])
		                      || $this->preferences['version'] != $this->version)
		                      || $this->contentRequiresSetup();

		// if it's a new version, run the activation/upgrade function (if not done at activation hook)
		// this will update the translations in the JS cached HTML
		// and ensures the pre-update settings are saved in the history table
		if ($this->new_version){
			//$this->microthemer_activated_or_updated();
			add_action('admin_init', array(&$this, 'microthemer_activated_or_updated'));
		}

		// get lang for non-english exceptions (e.g. showing English property labels too)
		$this->locale = get_locale();

		$this->dis_text = __('DISABLED', 'microthemer');
		$this->level_map = array(
			'section' => __('folder', 'microthemer'),
			'selector' => __('selector', 'microthemer'),
			'tab' => __('tab', 'microthemer'),
			'tab-input' => __('tab', 'microthemer'),
			'group' => __('group', 'microthemer'),
			'pgtab' => __('settings', 'microthemer'),
			'subgroup' => __('settings', 'microthemer'),
			'property' => __('property', 'microthemer'),
			'script' => __('Enqueued Script', 'microthemer')
		);

		// check if integratable plugins are active
		add_action( 'admin_init', array(&$this, 'check_integrations'));

		// setup vars that depend on WP being fully loaded
		add_action( 'admin_init', array(&$this, 'setup_wp_dependent_vars'));

		// we don't want the WP admin bar on any Microthemer pages
		add_filter('show_admin_bar', '__return_false');

		// loading URL for iframe
		//$this->placeholderURLs = $this->get_placeholder_urls();


		// links that save something (e.g. ?design_pack=) need their nonce, whatever else is in the URL
		add_action('admin_init', array(&$this, 'verifyPageLinkNonces') );

		// add scripts and styles
		// Not necessary if this is an ajax call. XDEBUG_PROFILE showed add_js was adding sig time.
		if ( empty($_GET['action']) or $_GET['action'] != 'mtui'){

			add_action('admin_init', array(&$this, 'actionCookieUpdates') );
			add_action('admin_init', array(&$this, 'add_no_cache_headers'), 1);
			add_action('admin_enqueue_scripts', array(&$this, 'add_css'), PHP_INT_MAX);
			add_action('admin_enqueue_scripts', array(&$this, 'add_js'), PHP_INT_MAX);
			//add_action( 'wp_body_open', array(&$this, 'add_svg_sprite') );

			// fix compatibility issues due to a plugin loading scripts or styles on MT interface pages
			add_action('admin_enqueue_scripts', array('Microthemer\Common', 'dequeue_rogue_assets'), 1000);
			add_action('wp_enqueue_media', array('Microthemer\Common', 'dequeue_rogue_assets'), 1000);

		} else {
			//echo 'it is an ajax request';
		}

	}

	// Stop the plugin if below requirements
	function checkMinimumRequirements(){
		return $this->required_version() && $this->check_user_memory_limit() && !defined('TVR_MICROBOTH');
	}

	// Hook into WordPress ajax action (for saving settings)
	function hookAjax(){
		add_action('wp_ajax_mtui', array(&$this, 'microthemer_ajax_actions'));

		//echo '<pre>' . print_r($GLOBALS, 1 ) . '</pre>';
	}

	// add menu links (all WP admin pages need this)
	function hookAdminMenu(){
		add_action("admin_menu", array(&$this, "microthemer_dedicated_menu"));
	}

	// add shortcut to Microthemer if preference
	function hookAdminBarShortcut(){

		if ( !empty($this->preferences['admin_bar_shortcut']) ) {
			add_action( 'admin_bar_menu', array(&$this, 'custom_toolbar_link'), 999999);
		}
	}

	// activation hook for setting initial preferences (so e.g. Microthemer link appears in top toolbar)
	function hookActivation(){

		// (the activation hook itself is registered in tvr-microthemer.php, which flags activation)

		// check if Microthemer has been activated and run any code that CAN tap into all WP functionality
		add_action( 'admin_init', array(&$this, 'check_microthemer_activation_flag') );
	}

	function actionCookieUpdates(){

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing; the only effect is clearing the plugin's own draft-folder cookie for the current user when they open the Microthemer screen
		if (isset($_GET['page']) && $_GET['page'] === $this->microthemeruipage){
			if (isset($_COOKIE['microthemer_draft_folder'])){
				$this->deleteCookie('microthemer_draft_folder');
			}
		}
	}


	// Links the plugin generates that save state must carry a valid nonce. Checked before admin-header.php
	// runs, as a wp_die() during page output would leave the page's scripts without their footer data.
	function verifyPageLinkNonces(){

		// admin-ajax requests (e.g. the editor saving its preview URL with page= and mt_preview_url)
		// are checked by the AJAX handlers with the mcth_simple_ajax nonce
		if (wp_doing_ajax()){
			return;
		}

		$page_links = array(
			$this->microthemespage => array('packs_page', 'mt_manage_packs'),
			$this->managesinglepage => array('design_pack', 'mt_manage_packs'),
			$this->microthemeruipage => array('mt_preview_url', 'mt-preview-nonce'),
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only page routing to decide which nonce the request needs
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared against the known page slugs above
		$page = isset($_GET['page']) ? $_GET['page'] : '';

		if (!is_string($page) || !isset($page_links[$page]) || !isset($_GET[$page_links[$page][0]])){
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $_REQUEST['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), $page_links[$page][1] ) ) {
			wp_die( 'Security check failed', '', array( 'response' => 403 ) );
		}
	}


	function supportAdminAssets(){
		$p = $this->preferences;
		return !empty($p['admin_asset_loading']) || !empty($p['admin_asset_editing']);
	}

	function getAllPluginPages(){

		$this->all_pages = array(
			$this->microthemeruipage,
			$this->microthemespage,
			$this->managesinglepage,
			$this->docspage,
			$this->fontspage,
			$this->preferencespage,
			$this->detachedpreviewpage
		);

		return $this->all_pages;
	}

	// @taken from ngg gallery: http://wordpress.org/extend/plugins/nextgen-gallery/
	function required_version() {

		global $wp_version;

		// if requirements not met
		if ( !version_compare($wp_version, $this->minimum_wordpress, '>=') ) {

			add_action(
				'admin_notices',
				function(){
					echo '<div id="message" class="error"><p><strong>' .
					     /* translators: 1: application name, 2: minimum WordPress version, 3: application name */
					     sprintf(esc_html__('Sorry, %1$s only runs on WordPress version %2$s or above. Deactivate %3$s to remove this message.', 'microthemer'), esc_html($this->appName), esc_html($this->minimum_wordpress), esc_html($this->appName)) .
					     '</strong></p></div>';
				}
			);

			return false;
		}

		return true;
	}

	// check the user has a minimal amount of memory
	function check_user_memory_limit() {

		// get memory limit including unit
		$subject = ini_get('memory_limit'); // e.g. 256M
		$pattern = '/([\-0-9]+)/';
		preg_match($pattern, $subject, $matches);
		$this->user_memory_limit = $matches[0];
		$unit = str_replace($matches[0], '', $subject);

		// cautious memory check that will only throw error if memory is given in MB.
		// Too many variables to safely accommodate all e.g. 0, -1, (int) 268435456, 3GB etc
		if (($unit == 'M' || $unit == 'MB') && $this->user_memory_limit < 16){
			// we don't have enough
			add_action(
				'admin_notices',
				function() {
					echo '<div id="message" class="error"><p><strong>' .
					     /* translators: 1: application name, 2: allocated memory in MB, 3: application name */
					     sprintf( esc_html__( 'Sorry, %1$s has a memory requirement of 16MB or higher to run. Your allocated memory is less than this (%2$sMB). Deactivate %3$s to remove this message. Or increase your memory limit.', 'microthemer' ), esc_html($this->appName), esc_html($this->user_memory_limit), esc_html($this->appName)) .
					     '</strong></p></div>';
				}
			);

			return false;
		}

		return true;
	}

	// Microthemer dedicated menu
	function microthemer_dedicated_menu() {

		// for draft mode and preventing two users overwriting each other's edits
		// get_current_user_id() needs to be here (hooked function)
		$this->current_user_id = get_current_user_id();

		add_menu_page(
			/* translators: %s: application name */
			sprintf(__('%s UI', 'microthemer'), $this->appName),
			$this->appNameFull,
			'administrator', $this->microthemeruipage, array(&$this,'microthemer_ui_page')
		);

		add_submenu_page('options.php',
			__('Manage Design Packs', 'microthemer'),
			__('Manage Design Packs', 'microthemer'),
			'administrator', $this->microthemespage, array(&$this,'manage_micro_themes_page'));
		add_submenu_page('options.php',
			__('Manage Single Pack', 'microthemer'),
			__('Manage Single Pack', 'microthemer'),
			'administrator', $this->managesinglepage, array(&$this,'manage_single_page'));
		add_submenu_page('options.php',
			__('Microthemer Docs', 'microthemer'),
			__('Documentation', 'microthemer'),
			'administrator', $this->docspage, array(&$this,'microthemer_docs_page'));
		add_submenu_page('options.php',
			__('Google Fonts', 'microthemer'),
			__('Google Fonts', 'microthemer'),
			'administrator', $this->fontspage, array(&$this,'microthemer_fonts_page'));
		add_submenu_page('options.php',
			__('Detached Preview', 'microthemer'),
			__('Detached Preview', 'microthemer'),
			'administrator', $this->detachedpreviewpage, array(&$this,'microthemer_detached_preview_page'));
		add_submenu_page($this->microthemeruipage,
			__('Preferences', 'microthemer'),
			__('Preferences', 'microthemer'),
			'administrator', $this->preferencespage, array(&$this,'microthemer_preferences_page'));
	}

	// add a link to the WP Toolbar (this was copied from frontend class - use better method later)
	function custom_toolbar_link($wp_admin_bar) {

		if (!current_user_can('manage_options')){
			return false;
		}

		if (!empty($this->preferences['top_level_shortcut'])){
			$parent = false;
		} else {
			$parent = 'site-name';
		}

		// root URL to MT UI
		$href = $this->wp_blog_admin_url . 'admin.php?page=' . $this->microthemeruipage;

		// if admin edit post or page - MT should load that page
		$front = $this->get_url_from_edit_screen();

		if ($front){

			$href.= '&mt_preview_url=' . rawurlencode($front['url'])
			        . '&mt_item_id=' . rawurlencode($front['postID'])
			        . '&mt_path_label=' . rawurlencode($front['title'])
			        . '&_wpnonce=' . wp_create_nonce( 'mt-preview-nonce' );

			// not sure how to make a post
			if ($front['post_status'] === 'auto-draft'){
				$href.= '&auto_save_draft='.$front['postID'];
			}

			//wp_die('<pre>'.print_r($front, true).'</pre>');
		}

		$args = array(
			'id' => 'wp-mcr-shortcut',
			'title' => $this->appNameFull,
			'parent' => $parent,
			'href' => $href,
			'meta' => array(
				'class' => 'wp-mcr-shortcut',
				/* translators: %s: application name */
				'title' => sprintf(__('Jump to the %s interface', 'microthemer'), $this->appName)
			)
		);

		$wp_admin_bar->add_node($args);
	}

	function get_url_from_edit_screen(){

		global $post;

		$url = false;

		if ($post && function_exists('get_current_screen')) {

			$current_screen = get_current_screen();
			$post_type = $current_screen->post_type;
			$isPostOrPage = ($post_type === 'post' || $post_type === 'page');
			$isEditScreen = $isPostOrPage && isset($_GET['action'])
			                && $_GET['action'] === 'edit'
			                && !empty($_GET['post']);
			$isAddScreen = $isPostOrPage && $current_screen->action === 'add';

			//wp_die('<pre>'.print_r($post, true).'</pre>');

			// if add new or saved draft use preview URL
			if ($isAddScreen || $post->post_status !== 'publish'){
				$url = get_preview_post_link($post->ID);
			}

			// get link for published post
			else if ($isEditScreen){
				$url = get_permalink( intval($_GET['post']) );
			}

			if ($url){
				return array(
					'url' => $url,
					'post_status' => $post->post_status,
					'title' => $post->post_title,
					'postID' => $post->ID
				);
			}

			//wp_die('<pre>'.print_r(get_current_screen()->id, true).'</pre>');
		}

		return false;
	}

	// When a WP plugin is activated, we can't hook into all WordPress functionality
	// so the workaround they advise is to flag that activation happened (tvr-microthemer.php), so when
	// WP redirects to another admin page, the activation functionality can run on the admin_init hook
	// https://developer.wordpress.org/reference/functions/register_activation_hook/
	function check_microthemer_activation_flag(){

		if ( get_option( 'microthemer_activation' ) == '1' ) {

			// delete the flag
			delete_option( 'microthemer_activation' );

			// run the activation code
			$this->microthemer_activated_or_updated();
		}
	}

	// ensure preferences are set upon activation
	function microthemer_activated_or_updated(){

		if (!$this->activation_function_ran){

			$pd_context = 'microthemer_activated_or_updated';

			// we need to check_integrations here, so that the default MQs are set for a builder, if active
			//$this->check_integrations(); // is causes an error - is_plugin_active is not defined - need to come back to this
			/*wp_die('check_integrations result on activation: <pre>'.print_r(array(
							'integrations' => $this->integrations,
							'mqs' => $this->mq_sets
						), true).'</pre>' );*/

			// setup program data arrays
			// calls getPreferences() which also sets if nothing to get yet
			// and creates a backup of the settings and preferences if a new version
			include dirname(__FILE__) .'/../includes/program-data.php';

			// if non-english, we need to write to program-data.js in current language
			// log success of overwrite
			// (this didn't work properly on some servers, maybe @fopen suppress would work, but this is safer)
			$locale = $this->locale !== '' ? $this->locale : get_locale();
			$pref_array = array(
				'inlineJsProgData' => ( strpos($locale, 'en_') === false ) //!$this->write_mt_version_specific_js('../js-min')
			);

			$this->savePreferences($pref_array);

			// Ensure that all new preferences have been set, so the frontend can use new preferences
			$this->getPreferences();


			// todo save all lang strings in DB at this point to save CPU later, start with property-options.inc.php

			// ensure storage folder is created animation-events.js and stock.zip extracted
			$this->setup_micro_themes_dir(true);

			$this->activation_function_ran = true;

		}

	}

	// Lite-only notice for sites with saved work this edition can't show or apply, which in
	// practice means the site used Microthemer Pro before it became a separate plugin: until
	// 7.5.x, wp.org served the edition that Pro unlocked, so the first Lite update from wp.org
	// replaces Pro on those sites. Not a license check - shared code has no license state - only
	// the same opaque settings comparison UnavailableSettings does to keep that data safe on save.
	// Stays on every admin screen until it's dismissed or Pro is installed, as a customer can
	// easily miss a message that only shows in one place. It's only for sites that have this data.
	function hookOrphanedProDataNotice(){

		// pro builds have every property - nothing is missing
		if ($this->hasProPropertyOptions()){
			return;
		}

		if (!current_user_can('manage_options')){
			return;
		}

		if (!empty($this->preferences['pro_data_notice_dismissed'])){
			return;
		}

		if (isset($_GET['mt_dismiss_pro_data_notice'])){
			check_admin_referer('mt_dismiss_pro_data_notice');
			$this->savePreferences(array('pro_data_notice_dismissed' => 1));
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only routes to the notice; the dismiss branch above is nonce-checked
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

		// Microthemer's own pages hide admin notices, so they get the message in their own log instead
		if (in_array($page, $this->getAllPluginPages(), true)){
			$orphaned = $this->cachedOrphanedProData();
			if ($orphaned){
				$notice = $this->orphanedProDataNotice($orphaned);
				$this->log(esc_html($notice['short']), $notice['long'], 'warning');
			}
			return;
		}

		// the settings scan runs only when notices are actually printed
		add_action('admin_notices', array($this, 'renderOrphanedProDataNotice'));
	}

	// orphanedProData() loads the property map and walks all the settings, which is too much for
	// every admin screen, and the answer only changes as often as Lite's settings are saved
	function cachedOrphanedProData(){

		$orphaned = get_transient('microthemer_orphaned_pro_data');

		if (!is_array($orphaned)){
			$orphaned = $this->orphanedProData();
			set_transient('microthemer_orphaned_pro_data', $orphaned, HOUR_IN_SECONDS);
		}

		return $orphaned;
	}

	// Builds without the code editors never output custom code, but a build that had them (Pro,
	// before the site updated to the wp.org build) may have published some
	function hookPublishedCustomCodeCleanup(){
		if ($this->isCSSEditor() && !$this->hasCodeEditors() && current_user_can('manage_options')){
			// late enough for the program data the asset generation relies on (setup_wp_dependent_vars)
			add_action('admin_init', array($this, 'clearPublishedCustomCode'), 20);
		}
	}

	// Stop loading published custom JS immediately. Rebuild published CSS from its own
	// settings snapshot, never from newer drafts, so switching to Lite cannot leave
	// Pro code live or accidentally publish work the user has not published.
	function clearPublishedCustomCode(){

		// On ordinary admin screens, init() reads the option before the editor's setup
		// has created it. WordPress returns false for a missing option. Defer cleanup
		// until preferences exist; do not initialize or change saved work here.
		if (!is_array($this->preferences) || !$this->preferences){
			return;
		}

		// This runs on every admin request until it succeeds (and some plugins, e.g. InfiniteWP's
		// client, fire admin_init on every request), so an error here must not lock people out.
		// Restore the draft state it may have swapped out, then pause published CSS as when the
		// published state can't be recovered.
		$draftOptions = $this->options;
		$draftPreferences = $this->preferences;
		try {
			$this->cleanUpPublishedCodeForLite();
		} catch (\Throwable $e) {
			$this->options = $draftOptions;
			$this->preferences = $draftPreferences;
			if (function_exists('error_log')){
				error_log('Microthemer: Lite published CSS cleanup failed: ' . $e->getMessage());
			}
			$this->pausePublishedCssForLite();
		}
	}

	function cleanUpPublishedCodeForLite(){

		if (!empty($this->preferences['load_js']) || !empty($this->preferences['load_js_published'])){
			$this->savePreferences(array('load_js' => 0, 'load_js_published' => 0));
		}

		if (empty($this->options)){
			$this->getOptions();
		}

		if (get_option('microthemer_lite_css_cleaned')){
			return;
		}

		// A draft may have removed code that remains in the published stylesheet.
		// The current options alone cannot prove that the published CSS is clean.
		if (!get_option('microthemer_lite_css_retry')
			&& array_key_exists('global_stylesheet_required_published', $this->preferences)
			&& empty($this->preferences['global_stylesheet_required_published'])
			&& empty($this->preferences['asset_loading_published']['conditional'])){
			update_option('microthemer_lite_css_cleaned', 1, false);
			return;
		}

		$publishedState = $this->publishedCssStateForLiteCleanup();
		if (!is_array($publishedState)){
			$this->pausePublishedCssForLite();
			return;
		}

		$draftOptions = $this->options;
		$draftPreferences = $this->preferences;
		$this->options = $publishedState['settings'];
		$this->preferences = array_replace($this->preferences, $publishedState['preferences']);
		$assetsWritten = $this->update_assets('customised', '', array('active' => 1));
		$publishedPreferences = $this->preferences;
		$this->options = $draftOptions;
		if (!$assetsWritten){
			$this->savePreferences($draftPreferences);
			$this->pausePublishedCssForLite(true);
			return;
		}

		// update_assets saves working preferences as it generates files. Restore the
		// draft side, copying only the loading flags for the new published assets.
		$draftPreferences['asset_loading_published'] = $publishedPreferences['asset_loading'];
		$draftPreferences['global_stylesheet_required_published'] = $publishedPreferences['global_stylesheet_required'];
		$draftPreferences['num_saves'] = $publishedPreferences['num_saves'];
		$draftPreferences['load_js_published'] = 0;
		$draftPreferences['custom_css_published'] = 0;
		$this->savePreferences($draftPreferences);
		delete_option('microthemer_lite_css_retry');
		update_option('microthemer_lite_css_cleaned', 1, false);
	}

	function publishedCssStateForLiteCleanup(){
		$published = get_option('microthemer_published_css_snapshot');
		if (is_array($published) && isset($published['settings'], $published['preferences']['m_queries'])
			&& is_array($published['settings']) && is_array($published['preferences']['m_queries'])){
			return $published;
		}
		if (empty($this->preferences['num_unpublished_saves'])){
			return array(
				'settings' => $this->options,
				'preferences' => array(
					'm_queries' => isset($this->preferences['m_queries']) ? $this->preferences['m_queries'] : array(),
					'allow_scss' => !empty($this->preferences['allow_scss']) ? 1 : 0,
				),
			);
		}

		// Older releases did not save a published snapshot. Revision counts cannot
		// reconstruct it reliably: some saves skip revisions and other actions add
		// them. Leave drafts untouched and require an explicit publish instead.
		return null;
	}

	function pausePublishedCssForLite($retry = false){
		// If the old published state cannot be recovered, do not serve a stylesheet
		// that may contain uneditable code, and leave all draft settings untouched.
		$this->savePreferences(array(
			'global_stylesheet_required_published' => 0,
			'asset_loading_published' => array(),
			'load_js_published' => 0,
		));
		if ($retry){
			update_option('microthemer_lite_css_retry', 1, false);
		} else {
			update_option('microthemer_lite_css_cleaned', 1, false);
		}
		$this->log(
			esc_html__('Published styles are paused', 'microthemer'),
			'<p>' . ($retry
				? esc_html__('Microthemer could not write the cleaned stylesheet. Fix the storage folder permissions to retry; your saved work is unchanged.', 'microthemer')
				: esc_html__('Microthemer could not recover the previous published settings. Publish again to restore styles; your saved work is unchanged.', 'microthemer')) . '</p>',
			'warning'
		);
		$this->cache_global_msg();
	}

	// what the saved settings use that this build can't show or apply (empty if nothing):
	// 'styles' - properties/groups the active map lacks (incl. per-selector code),
	// 'custom_code' - code from the full CSS/JS editors, which this build doesn't output
	function orphanedProData(){

		if (empty($this->options)){
			$this->getOptions();
		}
		if (empty($this->propertyoptions)){
			$this->getPropertyOptions();
		}

		$orphaned = array();

		if (UnavailableSettings::hasAny($this->options, $this->propertyoptions)){
			$orphaned['styles'] = true;
		}

		if (!$this->hasCodeEditors()){
			foreach (array('hand_coded_css', 'js') as $code_key){
				if (!empty($this->options['non_section'][$code_key]) && trim($this->options['non_section'][$code_key]) !== ''){
					$orphaned['custom_code'] = true;
				}
			}
		}

		return $orphaned;
	}

	function renderOrphanedProDataNotice(){

		$orphaned = $this->cachedOrphanedProData();
		if (!$orphaned){
			return;
		}

		$notice = $this->orphanedProDataNotice($orphaned);

		echo '<div class="notice notice-warning"><p><strong>' . esc_html($notice['short']) . '</strong></p>' .
			wp_kses($notice['long'], array('p' => array(), 'a' => array('href' => true, 'target' => true, 'rel' => true))) .
			'</div>';
	}

	// heading and HTML body (already escaped) of the orphaned pro data notice
	function orphanedProDataNotice($orphaned){

		$current_url = isset($_SERVER['REQUEST_URI'])
			? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))
			: admin_url('plugins.php');

		$dismiss_url = wp_nonce_url(
			add_query_arg('mt_dismiss_pro_data_notice', '1', $current_url),
			'mt_dismiss_pro_data_notice'
		);

		$link = '<a href="%s" target="_blank" rel="noopener noreferrer">';

		$install = sprintf(
			/* translators: 1: opening link tag to the themeover.com account page, 2: closing link tag */
			esc_html__('If you already have a license, %1$sdownload Microthemer Pro from your Themeover account%2$s and install it in place of this plugin. Pro downloads and updates are provided by Themeover, not the WordPress.org plugin page. If your download is missing, check that you are signed in with the email address associated with your license.', 'microthemer'),
			sprintf($link, esc_url('https://themeover.com/my-account/?contentView=microthemer')),
			'</a>'
		);

		$long = '';

		if (!empty($orphaned['custom_code'])){
			$long.= '<p>' . esc_html__('This edition of Microthemer does not include the code editors, so the custom CSS and JavaScript you wrote in them is no longer added to your site. It is still saved, and is applied again as soon as Pro is installed.', 'microthemer') . '</p>';
		}

		if (!empty($orphaned['styles'])){
			$long.= '<p>' . esc_html__('Some of your saved styles use Pro features (such as Grid, Flexbox, Animation or the selector code editor) that this edition of Microthemer does not include. Your settings are safe and will come back as soon as Pro is installed.', 'microthemer') . '</p>';
		}

		return array(
			'short' => __('Microthemer Pro is now a separate plugin', 'microthemer'),
			'long' =>
				$long .
				'<p>' . $install . '</p>' .
				'<p><a href="' . esc_url($dismiss_url) . '">' . esc_html__('Dismiss this notice', 'microthemer') . '</a></p>',
		);
	}

	// The preferences/media query/script forms' tvr_preferences array: unslashed (incl. the
	// interface's own backslash escaping) and sanitized against Schemas::preferencesForm().
	// Callers are nonce-checked in microthemer_ajax_actions().
	function posted_preferences_form(){

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- unslashed by deep_unescape(), sanitized by the schema below; nonce checked by the caller
		$raw = isset($_POST['tvr_preferences']) && is_array($_POST['tvr_preferences']) ? $_POST['tvr_preferences'] : array();

		$checked = Request\Schemas::preferencesForm()->sanitize(array(
			'tvr_preferences' => $this->deep_unescape($raw, 0, 1, 1),
		));
		$form = isset($checked['value']['tvr_preferences']) ? $checked['value']['tvr_preferences'] : array();

		// a script entry is only usable with a valid handle
		if (isset($form['enq_js'])){
			$form['enq_js'] = array_filter($form['enq_js'], function($script){
				return !empty($script['display_name']);
			});
		}

		return $this->preferencesThisUserCanSave($form);
	}

	function microthemer_ajax_actions(){

		if ( !current_user_can('manage_options') ){
			wp_die( 'Access denied' );
		}

		// simple ajax operations that can be executed from any page, pointing to ui page
		if (isset($_GET['mcth_simple_ajax'])) {

			check_ajax_referer( 'mcth_simple_ajax', '_wpnonce' );

			$revision_id = null;

			// workspace preferences
			if (isset($_POST['tvr_preferences_form'])) {
				$this->process_preferences_form();
				wp_die();
			}

			// if it's an options save request
			if (isset($_GET['mt_action']) and $_GET['mt_action'] === 'mt_save_interface') {
				$this->actionSaveInterface();
				wp_die();
			}

			// If we're copying the revision from the server to indexedDB
			if (isset($_GET['copy_revision_locally'])) {
				$revision_id = !empty($_GET["revision_id"]) ? intval($_GET["revision_id"]) : null;
				$recent_revisions = !empty($_GET["recent_revisions"]) ? intval($_GET["recent_revisions"]) : null;
				$this->getRevisionForIndexedDB($revision_id, true, true, $recent_revisions);
				wp_die();
			}


			// if it's a silent save request for updating ui options (e.g. last viewed selector)
			if (isset($_GET['mt_action']) and $_GET['mt_action'] == 'mt_silent_save_interface') {
				// large JSON settings payload (user-authored CSS/selectors); sanitize_text_field would
				// corrupt it. Structure is validated by apply_save_package; nonce + capability gated above.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- unslashed by deep_unescape(), sanitized by Schemas::sanitizeSettings()
				$savePackage = isset($_POST['savePackage']) ? Request\Schemas::sanitizeSettings($this->deep_unescape($_POST['savePackage'], 1, 1, 1)) : array();
				/*echo 'show_me from ajax save (before): <pre> ';
						print_r($savePackage);
						echo '</pre>';
						return false;*/

				$this->applySilentSavePackage($savePackage);
				wp_die();
			}

			// addon installation
			if (!empty($_GET['install_tvr_addon'])) {
				echo wp_json_encode($this->extFilter('install_addon', array('success' => 0), sanitize_key(wp_unslash($_GET["install_tvr_addon"]))));
				wp_die();
			}

			// content actions
			$this->contentMethod('ajaxActions');

			// $this->get_site_pages();
			if (isset($_GET['get_site_pages'])) {

				// MT posts search should only check title or slug so we get precise results (that appear in top 10 limit)
				// And because MT will filter out results with no title match on JS side anyway
				add_filter( 'posts_search', array(&$this, 'search_by_title_or_slug'), 10, 2 );

				$searchTerm = isset($_GET['search_term'])
					? sanitize_text_field(wp_unslash($_GET["search_term"]))
					: null;

				echo wp_json_encode($this->get_site_pages($searchTerm));

				wp_die();
			}

			// ajax - load selectors and/or selector options


			// ajax - toggle draft mode
			if (isset($_GET['draft_mode'])) {

				$pref_array['draft_mode'] = intval($_GET['draft_mode']);

				// ned to get current user id again as $this->current_user_id won't be set in ajax request
				$current_user_id = get_current_user_id();

				// save current user in array
				if ($pref_array['draft_mode']){
					$pref_array['draft_mode_uids'][$current_user_id] = $current_user_id;
				} else {
					// reset if draft mode is off
					$pref_array['draft_mode_uids'] = array();
				}
				$this->savePreferences($pref_array);
				wp_die();
			}

			if (isset($_GET['mt_publish_settings'])) {
				$this->jsonResponse($this->publishSettings());
			}

			// selname_code_synced
			if (isset($_GET['load_sass_import'])) {

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- unslashed and sanitized on this same line; rawurldecode() sits in between only so percent-encoded characters survive, which the sniff cannot follow
				$path = sanitize_text_field(rawurldecode(wp_unslash($_GET["load_sass_import"])));
				$imports = $this->get_sass_import_paths('@import "'.$path.'";', '');
				$content = false;

				if ($imports){
					$content = $this->recursively_scan_import_files(
						array(
							'import' => $imports[0]
						)
					);
				}

				$response = array(
					'error' => !$content,
					'content' => $content
				);

				echo wp_json_encode($response);
				wp_die();
			}

			// selname_code_synced
			if (isset($_GET['selname_code_synced'])) {
				$pref_array['selname_code_synced'] = intval($_GET['selname_code_synced']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// code_manual_resize
			if (isset($_GET['code_manual_resize'])) {
				$pref_array['code_manual_resize'] = intval($_GET['code_manual_resize']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// ace full page html
			if (isset($_GET['wizard_expanded'])) {
				$pref_array['wizard_expanded'] = intval($_GET['wizard_expanded']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// AI admin access
			if (isset($_GET['ai_admin_access'])) {
				$pref_array['ai_admin_access'] = intval($_GET['ai_admin_access']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// remember the state of the extra icons in the selectors menu
			if (isset($_GET['show_extra_actions'])) {
				$pref_array['show_extra_actions'] = intval($_GET['show_extra_actions']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// remember the grid highlight status
			if (isset($_GET['grid_highlight'])) {
				$pref_array['grid_highlight'] = intval($_GET['grid_highlight']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// remember show_sampled_values
			if (isset($_GET['show_sampled_values'])) {
				$pref_array['show_sampled_values'] = intval($_GET['show_sampled_values']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// remember show_sampled_variables
			if (isset($_GET['show_sampled_variables'])) {
				$pref_array['show_sampled_variables'] = intval($_GET['show_sampled_variables']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// mt_color_variables_css
			if (isset($_POST['mt_color_variables_css'])) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- user-authored CSS; sanitize_text_field would corrupt it. Tags stripped, nonce + capability gated.
				$pref_array['mt_color_variables_css'] = wp_strip_all_tags(wp_unslash($_POST['mt_color_variables_css']));
				$this->savePreferences($pref_array);
				wp_die();
			}

			// wizard footer/right dock
			if (isset($_GET['dock_ai_right'])) {
				$pref_array['dock_ai_right'] = intval($_GET['dock_ai_right']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// wizard footer/right dock
			if (isset($_GET['dock_settings_right'])) {
				$pref_array['dock_settings_right'] = intval($_GET['dock_settings_right']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// instant hover inspection
			if (isset($_GET['hover_inspect'])) {
				$pref_array['hover_inspect'] = intval($_GET['hover_inspect']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// auto folders
			if (isset($_GET['auto_folders'])) {
				$pref_array['auto_folders'] = intval($_GET['auto_folders']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// auto folders
			if (isset($_GET['auto_folders_page'])) {
				$pref_array['auto_folders_page'] = intval($_GET['auto_folders_page']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// ajax - update preview url after page navigation
			if (isset($_GET['mt_preview_url'])) {
				// this request context carries the mcth_simple_ajax nonce (verified above too)
				$this->maybe_set_preview_url('mcth_simple_ajax');
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// ajax - update preview url after page navigation
			if (isset($_GET['import_css_url'])) {
				// update view_import_stylesheets list with possible new stylesheet
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- unslashed and sanitized on this same line; rawurldecode() sits in between only so percent-encoded characters survive, which the sniff cannot follow
				$this->update_css_import_urls(sanitize_text_field(rawurldecode(wp_unslash($_GET["import_css_url"]))));
				wp_die();
			}

			// code editor focus
			if (isset($_GET['show_code_editor'])) {
				$pref_array = array();
				// stays off in builds without the code editors
				$pref_array['show_code_editor'] = $this->canEditCode() ? intval($_GET['show_code_editor']) : 0;
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// ruler show/hide
			if (isset($_GET['show_rulers'])) {
				$pref_array = array();
				$pref_array['show_rulers'] = intval($_GET['show_rulers']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// dark theme
			if (isset($_GET['mt_dark_mode'])) {
				$pref_array = array();
				$pref_array['mt_dark_mode'] = intval($_GET['mt_dark_mode']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// dark theme
			if (isset($_GET['sync_browser_tabs'])) {
				$pref_array = array();
				$pref_array['sync_browser_tabs'] = intval($_GET['sync_browser_tabs']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// auto-load Elementor, BB, Oxygen
			if (isset($_GET['autoload_elementor'])) {
				$this->savePreferences(array(
					'autoload_elementor' => intval($_GET['autoload_elementor'])
				));
				wp_die();
			} if (isset($_GET['autoload_FLBuilder'])) {
				$this->savePreferences(array(
					'autoload_FLBuilder' => intval($_GET['autoload_FLBuilder'])
				));
				wp_die();
			} if (isset($_GET['autoload_oxygen'])) {
				$this->savePreferences(array(
					'autoload_oxygen' => intval($_GET['autoload_oxygen'])
				));
				wp_die();
			}

			// Auto-save mode
			if (isset($_GET['auto_save_mode'])) {
				$pref_array = array();
				$pref_array['auto_save_mode'] = intval($_GET['auto_save_mode']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// Auto-publish mode
			if (isset($_GET['auto_publish_mode'])) {
				$pref_array = array();
				$pref_array['auto_publish_mode'] = intval($_GET['auto_publish_mode']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			if (isset($_GET['sticky_styles_toolbar'])) {
				$this->savePreferences(array(
					'sticky_styles_toolbar' => intval($_GET['sticky_styles_toolbar'])
				));
				wp_die();
			}

			if (isset($_GET['pseudo_base_styles'])){
				$this->savePreferences(array(
					'pseudo_base_styles' => intval($_GET['pseudo_base_styles'])
				));
				wp_die();
			}

			// specificity preference
			if (isset($_GET['specificity_preference'])) {
				$pref_array = array();
				$pref_array['specificity_preference'] = intval($_GET['specificity_preference']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// sidebar size
			/* if (isset($_GET['sidebar_size'])) {
						 $pref_array = array();
						 $pref_array['sidebar_size'] = intval($_GET['sidebar_size']);
						 $pref_array["sidebar_size_category"] = sanitize_text_field(wp_unslash($_GET["sidebar_size"]));
						 $this->savePreferences($pref_array);
						 // kill the program - this action is always requested via ajax. no message necessary
						 wp_die();
					 }*/

			// save new MT layout
			if (isset($_GET['update_mt_layout'])) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; unslashed, decoded and sanitized by Schemas::layout()
				$raw_json = isset($_POST['tvr_serialized_data']) && is_string($_POST['tvr_serialized_data']) ? $_POST['tvr_serialized_data'] : '';
				$checked = Request\Schemas::layout()->sanitizeJson(Request\Schemas::wrapJson($raw_json, 'config'));
				// malformed JSON has no 'config', and must not replace the stored layout with nothing
				if (isset($checked['value']['config'])) {
					$this->savePreferences(array('layout' => $checked['value']['config']));
				}
				wp_die();
			}

			// dock folders left
			if (isset($_GET['dock_folders_left'])) {
				$pref_array = array();
				$pref_array['dock_folders_left'] = intval($_GET['dock_folders_left']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// dock styles left
			if (isset($_GET['dock_styles_left'])) {
				$pref_array = array();
				$pref_array['dock_styles_left'] = intval($_GET['dock_styles_left']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// dock editor left
			if (isset($_GET['dock_editor_left'])) {
				$pref_array = array();
				// stays off in builds without the code editors
				$pref_array['dock_editor_left'] = $this->canEditCode() ? intval($_GET['dock_editor_left']) : 0;
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// full_height_left_sidebar
			if (isset($_GET['full_height_left_sidebar'])) {
				$pref_array = array();
				$pref_array['full_height_left_sidebar'] = intval($_GET['full_height_left_sidebar']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// expand_device_tabs
			if (isset($_GET['expand_device_tabs'])) {
				$pref_array = array();
				$pref_array['expand_device_tabs'] = intval($_GET['expand_device_tabs']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// update multiple preferences in one go
			if (isset($_GET['save_multiple_preferences']) && isset($_POST['pref_array'])) {

				// Preference keys are dynamic, so this is a wildcard map rather than a named schema.
				// Values are genuinely mixed: plain scalars (ai_priority), user-authored strings that
				// must not be mangled (mt_rich_text_code holds CSS/JSON — sanitize_text_field would
				// corrupt it), and opaque nested arrays (m_queries, whitelisted_domains, my_props).
				// 'mixed' keeps every value byte-identical, key order included, while still applying the
				// depth, node-count, item-count and null-byte limits the hand-rolled loop had none of.
				// Losslessness matters here beyond convenience: savePreferences() feeds my_props to
				// UnavailableSettings::preserve_my_props(), which compares stored keys against the active
				// property map — mangle a key and lite silently drops a pro-only preference.
				//
				// 'allInt' is the stricter mode the JS has always advertised
				// (MTsave.save_multiple_preferences(pref_array, 1)) and the server previously ignored.
				// In that mode every value must be a scalar number; arrays are rejected outright.
				$all_int = !empty($_POST['allInt']) && $_POST['allInt'] !== 'false';

				$schema = Request\Schemas::preferences($all_int);

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by the schema above
				$sanitized = $schema->sanitize(wp_unslash(array('pref_array' => $_POST['pref_array'])));

				$pref_array = isset($sanitized['value']['pref_array'])
					? $sanitized['value']['pref_array']
					: array();

				$this->savePreferences($this->preferencesThisUserCanSave($pref_array));
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// if it's an email error report request
			elseif (isset($_GET['send_error_report'])){

				$manual = !empty($_POST['manual']) && (bool) intval($_POST['manual']);

				// only the report fields, each typed (Schemas::errorReport)
				$report = Request\Schemas::errorReport()->sanitize(wp_unslash($_POST));
				$response = $this->maybeSendReport($report['value'], $manual);

				if ($manual || TVR_DEV_MODE){
					echo wp_json_encode(array(
						//'html'=> '<div id="microthemer-notice">'. $this->display_log() . '</div>',
						'response' => $response,
						'success' => !!$response
					));
				}

				wp_die();
			}


			// dock ALL options left
			/* if (isset($_GET['dock_styles_left'])) {
						 $pref_array = array();
						 $pref_array['dock_styles_left'] = intval($_GET['dock_styles_left']);
						 $this->savePreferences($pref_array);
						 // kill the program - this action is always requested via ajax. no message necessary
						 wp_die();
					 }*/

			// detach preview -
			if (isset($_GET['detach_preview'])) {
				$pref_array = array();
				$pref_array['detach_preview'] = intval($_GET['detach_preview']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// ruler show/hide
			if (isset($_GET['show_text_labels'])) {
				$pref_array = array();
				$pref_array['show_text_labels'] = intval($_GET['show_text_labels']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// show/hide whole interface
			if (isset($_GET['hide_interface'])) {
				$pref_array = array();
				$pref_array['hide_interface'] = intval($_GET['hide_interface']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// active MQ tab
			if (isset($_GET['manual_recompile_all_css'])) {
				$pref_array = array();
				$pref_array['manual_recompile_all_css'] = sanitize_text_field(wp_unslash($_GET['manual_recompile_all_css']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// remove_all_bricks_container_hacks
			if (isset($_GET['remove_all_bricks_container_hacks'])) {
				$pref_array = array();
				$pref_array['remove_all_bricks_container_hacks'] = intval($_GET['remove_all_bricks_container_hacks']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// initial_view_set
			if (isset($_GET['initial_view_set'])) {
				$pref_array = array();
				$pref_array['initial_view_set'] = intval($_GET['initial_view_set']);
				$this->savePreferences($pref_array);
				wp_die();
			}

			// active MQ tab
			if (isset($_GET['mq_device_focus'])) {
				$pref_array = array();
				$pref_array['mq_device_focus'] = sanitize_text_field(wp_unslash($_GET['mq_device_focus']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// active MQ tab
			if (isset($_GET['rev_save_status'])) {
				$this->updateRevisionSaveStatus(
					intval($_GET['rev_id']),
					intval($_GET['rev_save_status'])
				);
				wp_die();
			}

			// active CSS tab
			if (isset($_GET['css_focus'])) {
				$pref_array = array();
				$pref_array['css_focus'] = sanitize_text_field(wp_unslash($_GET['css_focus']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// update_default_unit
			if (isset($_GET['update_default_unit'])) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; unslashed, decoded and sanitized by Schemas::defaultUnit()
				$raw_json = isset($_POST['tvr_serialized_data']) && is_string($_POST['tvr_serialized_data']) ? $_POST['tvr_serialized_data'] : '';
				$checked = Request\Schemas::defaultUnit()->sanitizeJson($raw_json);
				$data = $checked['value'];

				// group/prop become array indexes, so refuse the write if either was invalid
				if (!empty($checked['errors']) || empty($data['group']) || empty($data['prop'])) {
					wp_die('Invalid default unit payload');
				}

				$this->preferences['my_props'][$data['group']]['pg_props'][$data['prop']]['default_unit'] = $data['unit'];
				$pref_array['my_props'] = $this->preferences['my_props'];
				$this->savePreferences($pref_array);

				wp_die();
			}

			// MT may update custom paths array via JS (e.g. path clear) and then post full array to replace current
			if (isset($_GET['update_custom_paths'])) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; unslashed, decoded and sanitized by Schemas::customPaths()
				$raw_json = isset($_POST['tvr_serialized_data']) && is_string($_POST['tvr_serialized_data']) ? $_POST['tvr_serialized_data'] : '';
				$checked = Request\Schemas::customPaths()->sanitizeJson(Request\Schemas::wrapJson($raw_json, 'list'));
				if (isset($checked['value']['list'])) {
					$this->savePreferences(array('custom_paths' => $checked['value']['list']));
				}
				wp_die();
			}

			// update draft folder session
			if (isset($_GET['update_draft_folder_session'])) {
				//add_action( 'init', array(&$this, 'updateDraftFolderCookie') );
				$this->updateDraftFolderCookie();
			}

			// update_recent_custom_prefixes
			if (isset($_GET['update_recent_custom_prefixes'])) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; unslashed, decoded and sanitized by Schemas::recentCustomPrefixes()
				$raw_json = isset($_POST['tvr_serialized_data']) && is_string($_POST['tvr_serialized_data']) ? $_POST['tvr_serialized_data'] : '';
				$checked = Request\Schemas::recentCustomPrefixes()->sanitizeJson(Request\Schemas::wrapJson($raw_json, 'list'));
				if (isset($checked['value']['list'])) {
					$this->savePreferences(array('recent_custom_prefixes' => $checked['value']['list']));
				}
				wp_die();
			}

			// update suggested values
			if (isset($_GET['update_sug_values'])) {

				$pref_array = array();
				$root_cat = sanitize_text_field(wp_unslash($_GET["update_sug_values"]));

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; unslashed, decoded and sanitized by Schemas::sugValues()
				$raw_json = isset($_POST['tvr_serialized_data']) && is_string($_POST['tvr_serialized_data']) ? $_POST['tvr_serialized_data'] : '';
				$checked = Request\Schemas::sugValues()->sanitizeJson($raw_json);
				$data = $checked['value'];

				// if we're setting suggested values for all properties
				if ($root_cat == 'all'){
					$this->preferences['my_props']['sug_values'] = isset($data['sug_values']) ? $data['sug_values'] : array();
					$this->preferences['my_props']['sug_variables'] = isset($data['sug_variables']) ? $data['sug_variables'] : array();
				}  elseif ($root_cat == 'synced_set') {
					// a set of fields in one go e.g. padding
					$this->preferences['my_props']['sug_values'] = array_merge(
						$this->preferences['my_props']['sug_values'],
						isset($data['synced_set']) ? $data['synced_set'] : array()
					);
				} else {
					// just setting suggestions for a type of property e.g. site_colors

					if (!empty($data['specific'])){
						$this->preferences['my_props']['sug_values'][$root_cat] = $data['specific'];
					}
				}

				// update variable if passed
				if (isset($data['sug_variables'])){
					$this->preferences['my_props']['sug_variables'] = $data['sug_variables'];
					$pref_array['default_sug_variables_set'] = 1;
				}

				$pref_array['default_sug_values_set'] = 1;
				$pref_array['my_props'] = $this->preferences['my_props'];
				$this->savePreferences($pref_array);

				//echo '<pre>posted array: '.print_r($data, true).'</pre>';

				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// save google/typekit fonts config
			if (isset($_GET['save_font_config'])) {

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; unslashed, decoded and sanitized by Schemas::fontConfig()
				$raw_json = isset($_POST['tvr_serialized_data']) && is_string($_POST['tvr_serialized_data']) ? $_POST['tvr_serialized_data'] : '';
				$checked = Request\Schemas::fontConfig()->sanitizeJson(Request\Schemas::wrapJson($raw_json, 'config'));
				if (isset($checked['value']['config'])) {
					$pref_array = array();
					$key = $_GET['save_font_config'] == 'google' ? 'google' : 'typekit';
					$pref_array['font_config'][$key] = $checked['value']['config'];

					$this->savePreferences($pref_array);
				}

				//echo '<pre>posted array: '.print_r($data, true).'</pre>';

				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// active property group
			if (isset($_GET['pg_focus'])) {
				$pref_array = array();
				$pref_array['pg_focus'] = sanitize_text_field(wp_unslash($_GET['pg_focus']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// active generated_css_focus
			/*if (isset($_GET['generated_css_focus'])) {
				$pref_array = array();
				$pref_array['generated_css_focus'] = intval($_GET['generated_css_focus']);
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}*/

			// remember selector wizard tab
			if (isset($_GET['adv_wizard_tab'])) {
				$pref_array = array();
				$pref_array['adv_wizard_tab'] = sanitize_text_field(wp_unslash($_GET['adv_wizard_tab']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// remember the program settings tab
			if (isset($_GET['program_settings_tab'])) {
				$pref_array = array();
				$pref_array['program_settings_tab'] = sanitize_text_field(wp_unslash($_GET['program_settings_tab']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// remember selector wizard tab
			if (isset($_GET['grid_focus'])) {
				$pref_array = array();
				$pref_array['grid_focus'] = sanitize_text_field(wp_unslash($_GET['grid_focus']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// remember transform tab
			if (isset($_GET['transform_focus'])) {
				$pref_array = array();
				$pref_array['transform_focus'] = sanitize_text_field(wp_unslash($_GET['transform_focus']));
				$this->savePreferences($pref_array);
				// kill the program - this action is always requested via ajax. no message necessary
				wp_die();
			}

			// last viewed selector
			/*if (isset($_GET['last_viewed_selector'])) {
						$pref_array = array();
						$pref_array['last_viewed_selector'] = sanitize_text_field(wp_unslash($_GET['last_viewed_selector']));
						$this->savePreferences($pref_array);
						// kill the program - this action is always requested via ajax. no message necessary
						wp_die();
					}*/

			// download pack
			if (!empty($_GET['mt_action']) and
			    $_GET['mt_action'] == 'tvr_download_pack') {
				$pack = !empty($_GET['dir_name']) ? sanitize_file_name(wp_unslash($_GET["dir_name"])) : '';
				$library_images = false;
				if ($this->isPackFolder($pack)) {
					// first of all, copy any images from the media library
					$dir = $this->micro_root_dir . $pack;
					$json_config_file = $dir . '/config.json';
					if ($library_images = $this->get_linked_library_images($json_config_file)){
						foreach($library_images as $key => $path){
							$root_rel_path = $this->root_rel($path, false, true, true);
							// the media library file (false if the image isn't stored on this site)
							$orig = $this->local_path_from_url($path);
							// name the copy after the file actually being copied (not the URL text, which can
							// carry a query string), and only copy images
							$basename = $orig ? basename($orig) : basename(preg_replace('/[#?].*$/', '', $root_rel_path));
							if ($orig && !in_array($this->get_extension($orig), array('jpg', 'jpeg', 'gif', 'png', 'svg', 'webp', 'avif', 'ico'), true)) {
								$orig = false;
							}
							$img_paths[] = $new = $dir . '/' . $basename;
							$replacements[$path] = $this->root_rel(
								$this->micro_root_url . $pack . '/' . $basename, false, true
							);
							if (!$orig || !copy($orig, $new)){
								$this->log(
									esc_html__('Library image not downloaded', 'microthemer'),
									'<p>' . sprintf(/* translators: %s: image file path */ esc_html__('%s could not be copied to the zip download file', 'microthemer'), $root_rel_path) . '</p>',
									'warning'
								);
								$download_status = 0;
							}
						}
						// cache original config file data
						$orig_json_data = $this->get_file_data($json_config_file);

						// update image paths in config.json for zip only (we'll restore shortly)
						$this->replace_json_paths($json_config_file, $replacements, $orig_json_data);
					}

					// now zip the contents
					if (
						$this->create_zip(
							$this->micro_root_dir,
							$pack,
							$this->micro_root_dir.'zip-exports/')
					){
						$download_status = 1;
					} else {
						$download_status = 0;
					}
				}
				else {
					$download_status = 0;
				}
				// delete any media library images temporarily copied to the directory
				if ($library_images){
					// restore orgin config.json paths
					$this->write_file($json_config_file, $orig_json_data);
					// delete images
					foreach ($img_paths as $key => $path){
						if ( ! wp_delete_file( $path ) ){
							$this->log(
								esc_html__('Temporary image could not be deleted.', 'microthemer'),
								'<p>' . sprintf( /* translators: %s: image file path */ esc_html__('%s was temporarily copied to your theme pack before download but could not be deleted after the download operation finished.', 'microthemer'), $this->root_rel($root_rel_path) ) . '</p>',
								'warning'
							);
						}
					}
				}
				echo wp_kses( '
							<div id="microthemer-notice">'
				     . $this->display_log() . '
								<span id="download-status" rel="'.esc_attr($download_status).'"></span>
							</div>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				wp_die();
			}

			// delete pack
			if (!empty($_GET['mt_action']) and
			    $_GET['mt_action'] == 'tvr_delete_micro_theme') {
				$pack = !empty($_GET['dir_name']) ? sanitize_file_name(wp_unslash($_GET["dir_name"])) : '';
				if ($this->isPackFolder($pack) and $this->tvr_delete_micro_theme($pack)){
					$delete_status = 1;
				} else {
					$delete_status = 0;
				}
				echo wp_kses( '
							<div id="microthemer-notice">'
				     . $this->display_log() . '
								<span id="delete-status" rel="'.esc_attr($delete_status).'"></span>
							</div>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				wp_die();
			}

			// download remote css file
			if (!empty($_GET['mt_action']) and
			    $_GET['mt_action'] == 'tvr_get_remote_css') {
				$config['allowed_ext'] = array('css');
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed then sanitized with esc_url_raw() on this line; rawurldecode() sits in between only so percent-encoded characters survive, which the sniff cannot follow
				$r = $this->get_safe_url(esc_url_raw(rawurldecode(wp_unslash($_GET["url"]))), $config);
				echo wp_kses( '
							<div id="microthemer-notice">'
				     . $this->display_log() . '
								<div id="remote-css">'.(!empty($r['content']) ? esc_html($r['content']) : 0).'</div>
							</div>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() );
				wp_die();
			}

			// if it's an import request
			if ( !empty($_POST['import_pack_or_css']) ){

				// if importing raw CSS
				if (!empty($_POST['stylesheet_import_json'])){

					$context = esc_attr__('Raw CSS', 'microthemer');

					// validate the import payload (size caps, JSON well-formedness, depth/node
					// bounds, null bytes) via the schema before decoding and sanitizing it below
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; validated by Schemas::packImport(), then decoded and sanitized by Schemas::sanitizeSettings()
					$raw_json = isset($_POST['stylesheet_import_json']) && is_string($_POST['stylesheet_import_json']) ? $_POST['stylesheet_import_json'] : '';
					$checked = Request\Schemas::packImport()->sanitizeJson(Request\Schemas::wrapJson($raw_json, 'pack'));
					if (!empty($checked['errors'])) {
						$this->log(
							esc_html__('Import failed', 'microthemer'),
							'<p>' . esc_html__('The import data was not valid JSON or exceeded size limits.', 'microthemer') . '</p>',
							'error'
						);
						$this->cache_global_msg();
						wp_die('Invalid import payload');
					}
					// sanitize the decoded settings, then work with them re-encoded the way the
					// interface encodes JSON (unescaped slashes/unicode), so the image path keys
					// posted below still match exactly
					$json_str = wp_json_encode(
						Request\Schemas::sanitizeSettings(json_decode(wp_unslash($raw_json), true)),
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					);

					// import preferences posted with the form (checkboxes/flags)
					$p_checked = Request\Schemas::preferencesForm()->sanitize(array(
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- unslashed here, sanitized by Schemas::preferencesForm()
						'tvr_preferences' => isset($_POST['tvr_preferences']) && is_array($_POST['tvr_preferences']) ? wp_unslash($_POST['tvr_preferences']) : array(),
					));
					$p = isset($p_checked['value']['tvr_preferences']) ? $p_checked['value']['tvr_preferences'] : array();

					// checkbox values must be explicitly evaluated
					$p['css_imp_only_selected'] = !empty($p['css_imp_only_selected']) ? 1 : 0;

					// handle remote image import. See plugins that do this:
					// https://premium.wpmudev.org/blog/download-remote-images-into-wordpress/
					if (!empty($_POST['get_remote_images'])){

						// Each entry is "pathInData,fullUrl" joined by "|". esc_url_raw on the
						// composite before splitting empties entries (relative path + https URL).
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each half sanitized below after split. Nonce + capability gated.
						$r_images = explode("|", wp_unslash($_POST["get_remote_images"]));
						$do_copy = false;
						$remote_images = array();
						$all_r = array();
						foreach ($r_images as $i => $both){
							$tmp = explode(',', $both, 2);
							// This is an exact CSS/JSON replacement key, not a filesystem path.
							// sanitize_text_field() strips valid URL-encoded segments such as "%20", preventing a match.
							$path_in_data = isset($tmp[0]) ? $tmp[0] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- derived from the nonce/capability-gated composite above; used only as a JSON string-replacement key
							$full_url = isset($tmp[1]) ? esc_url_raw($tmp[1]) : '';
							if ($path_in_data === '' || $full_url === ''){
								continue;
							}
							// save to temp dir first
							$r = $this->get_safe_url($full_url, array(
								'allowed_ext' => array('jpg', 'jpeg', 'gif', 'png', 'svg'),
								'tmp_file' => 1
							));

							if ($r){
								$remote_images[$path_in_data] = $r['tmp_file'];
								$do_copy = true;
								//$all_r[++$i] = $r;
							}

						}

						// do image copy function
						if ($do_copy){

							$updated_json_str = $this->import_pack_images_to_library(
								false,
								'custom',
								$json_str,
								$remote_images
							);

							$json_str = $updated_json_str ? $updated_json_str : $json_str;
						}

						// downloaded files are only needed until they're in the media library
						foreach ($remote_images as $tmp_image){
							if (is_file($tmp_image)){
								wp_delete_file($tmp_image);
							}
						}

					}

					// load the json file
					$this->load_json_file(false, 'custom', $context, $json_str);

					// save the import preferences
					$this->savePreferences($this->preferencesThisUserCanSave($p));
				}

				// if importing an MT design pack
				else {


					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed then sanitized with sanitize_title() + sanitize_file_name() on this line; htmlentities() sits in between, which the sniff cannot follow
					$theme_name = sanitize_file_name(sanitize_title(htmlentities(wp_unslash($_POST['import_from_pack_name']))));


					$json_file = $this->micro_root_dir . $theme_name . '/config.json';

					// Radio values are translated UI strings ("Merge"/"Overwrite"), not machine keys.
					// sanitize_key() would lowercase them and break the Merge comparison.
					$context = sanitize_text_field(wp_unslash($_POST["tvr_import_method"]));

					// import any background images that may need moving to the media library and update json
					$this->import_pack_images_to_library($json_file, $theme_name);

					// load the json file
					$this->load_json_file($json_file, $theme_name, $context);

				}

				// signal that all selectors should be recompiled (to ensure latest data structure)
				$this->update_preference('manual_recompile_all_css', 1);

				// update the revisions DB field
				$revision_id = $this->updateRevisions($this->options, $this->json_format_ua(
					'import-from-pack lg-icon',
					esc_html__('Import', 'microthemer') . ' ('.$context.'):&nbsp;',
					$this->readable_name($theme_name)
				));
				if (!$revision_id) {
					$this->log('','','error', 'revisions');
				}

				// save last message in database so that it can be displayed on page reload (just once)
				$this->cache_global_msg();

				// Return the revision data for indexedDB
				$this->jsonResponse($this->getRevisionData($revision_id));
			}

			// if it's a reset request
			elseif( isset($_GET['mt_action']) and $_GET['mt_action'] == 'tvr_ui_reset'){
				if ($this->resetUiOptions()) {
					$this->update_assets('customised');
					$item = esc_html__('Folders were reset', 'microthemer');
					$this->log(
						$item,
						'<p>' . esc_html__('The default empty folders have been reset.', 'microthemer') . '</p>',
						'notice'
					);
					// update the revisions DB field
					$revision_id = $this->updateRevisions($this->options, $this->json_format_ua(
						'folder-reset lg-icon',
						$item
					));
					if (!$revision_id) {
						$this->log(
							esc_html__('Revision failed to save', 'microthemer'),
							'<p>' . esc_html__('The revisions table could not be updated.', 'microthemer') . '</p>',
							'notice'
						);
					}
				}
				// save last message in database so that it can be displayed on page reload (just once)
				$this->cache_global_msg();

				$this->jsonResponse($this->getRevisionData($revision_id));
			}

			// if it's a restore revision request
			if(isset($_GET['mt_action']) and $_GET['mt_action'] == 'restore_rev'){
				$rev_key = intval($_GET["tvr_rev"]);
				if ($this->restoreRevision($rev_key)) {
					$item = esc_html__('Previous settings restored', 'microthemer');
					$this->log(
						$item,
						'<p>' . esc_html__('Your settings were successfully restored from a previous save.', 'microthemer') . '</p>',
						'notice'
					);
					$this->update_assets('customised');
					// update the revisions DB field
					$revision_id = $this->updateRevisions($this->options, $this->json_format_ua(
						'display-revisions lg-icon',
						$item
					));
					if (!$revision_id) {
						$this->log('','','error', 'revisions');
					}
				}
				else {
					$this->log(
						esc_html__('Settings restore failed', 'microthemer'),
						'<p>' . esc_html__('Data could not be restored from a previous save.', 'microthemer') . '</p>'
					);
				}
				// save last message in database so that it can be displayed on page reload (just once)
				$this->cache_global_msg();

				// Return the revision data for indexedDB
				$this->jsonResponse($this->getRevisionData($revision_id));
			}

			// if it's a get revision ajax request
			elseif(isset($_GET['mt_action']) and $_GET['mt_action'] == 'get_revisions'){
				echo wp_kses( '<div id="tmp-wrap">' . $this->getRevisions() . '</div>', \Microthemer\Helper::uiAllowedHtml(), \Microthemer\Helper::uiAllowedProtocols() ); // outputs table
				wp_die();
			}


			/* PREFERENCES FUNCTIONS MOVED TO MAIN UI */

			// update the MQs
			if (isset($_POST['tvr_media_queries_submit'])){

				$orig_media_queries = $this->preferences['m_queries'];

				// unslash (incl. the interface's own backslash escaping), then sanitize every field
				// against the schema: media queries are typed per field, the rest are text
				$form = $this->posted_preferences_form();

				// get the initial scale and default width for the "All Devices" tab
				$pref_array["initial_scale"] = isset($form['initial_scale']) ? floatval($form['initial_scale']) : 1;
				// May be blank (full width) or e.g. "480px" — do not intval() ('' → 0, strips units).
				$pref_array["all_devices_default_width"] = isset($form['all_devices_default_width'])
					? $form['all_devices_default_width']
					: '';
				// reset default media queries if all empty
				$action = '';
				if (empty($form['m_queries'])) {
					$pref_array['m_queries'] = $this->default_mqs;
					$action = 'reset';
				} else {
					$pref_array['m_queries'] = $form['m_queries'];
					$action = 'update';
				}

				// are we merging/overwriting with a new media query set (one of the known sets)
				$new_set = !empty($form['load_mq_set']) ? $form['load_mq_set'] : '';
				if ($new_set !== '' && isset($this->mq_sets[$new_set])){
					//print_r($this->mq_sets);
					$action = 'load_set';
					$new_mq_set = $this->mq_sets[$new_set];
					$pref_array["overwrite_existing_mqs"] = !empty($form['overwrite_existing_mqs']) ? 1 : 0;
					if (!empty($pref_array['overwrite_existing_mqs'])){
						$pref_array['m_queries'] = $new_mq_set;
						$load_action = esc_html__('replaced', 'microthemer');
					} else {
						$pref_array['m_queries'] = array_merge($pref_array['m_queries'], $new_mq_set);
						$load_action = esc_html__('was merged with', 'microthemer');
					}
				}

				// format media query min/max width (height later) and units
				$pref_array['m_queries'] = $this->mq_min_max($pref_array);

				// save and preset message
				$pref_array['num_saves'] = ++$this->preferences['num_saves'];

				if (empty($this->preferences['auto_publish_mode'])){
					$pref_array['num_unpublished_saves'] = ++$this->preferences['num_unpublished_saves'];
				}

				if ($this->savePreferences($pref_array)) {

					switch ($action) {
						case 'reset':
							$this->log(
								esc_html__('Media queries reset', 'microthemer'),
								'<p>' . esc_html__('The default media queries were successfully reset.', 'microthemer') . '</p>',
								'notice'
							);
							break;
						case 'update':
							$this->log(
								esc_html__('Media queries updated', 'microthemer'),
								'<p>' . esc_html__('Your media queries were successfully updated.', 'microthemer') . '</p>',
								'notice'
							);
							break;
						case 'load_set':
							$this->log(
								esc_html__('Media query set loaded', 'microthemer'),
								'<p>' . sprintf( /* translators: 1: load action (merged/replaced), 2: media query set name */ esc_html__('A new media query set %1$s your existing media queries: %2$s', 'microthemer'), $load_action, esc_html($new_set) ) . '</p>',
								'notice'
							);
							break;
					}

					// if the user deleted a media query, ensure data is cleaned from the ui data
					$this->clean_deleted_media_queries($orig_media_queries, $pref_array['m_queries']);

				}
				// save last message in database so that it can be displayed on page reload (just once)
				$this->cache_global_msg();

				// Return the revision data for indexedDB
				$this->jsonResponse($this->getRevisionData(
					$this->updateRevisions(
						$this->options,
						$this->json_format_ua(
							'mtif-devices lg-icon',
							esc_html__('Media queries updated', 'microthemer')
						)
					)
				));
			}

			// update the enqueued JS files
			if (isset($_POST['mt_enqueue_js_submit'])){
				// script handles WordPress registers, validated per field (see Schemas::enqueuedScriptMap)
				$form = $this->posted_preferences_form();
				$pref_array["enq_js"] = isset($form['enq_js']) ? $form['enq_js'] : array();
				$pref_array['num_saves'] = ++$this->preferences['num_saves'];

				if (empty($this->preferences['auto_publish_mode'])){
					$pref_array['num_unpublished_saves'] = ++$this->preferences['num_unpublished_saves'];
				}

				// save and present message
				if ($this->savePreferences($pref_array)) {
					$this->log(
						esc_html__('Enqueued scripts were updated', 'microthemer'),
						'<p>' . esc_html__('Your enqueued scripts were successfully updated.', 'microthemer') . '</p>',
						'notice'
					);
				}

				// save last message in database so that it can be displayed on page reload (just once)
				$this->cache_global_msg();

				// Return the revision data for indexedDB
				$this->jsonResponse($this->getRevisionData(
					$this->updateRevisions(
						$this->options,
						$this->json_format_ua(
							'mtif-js lg-icon',
							esc_html__('Enqueued scripts updated', 'microthemer')
						)
					)
				));
			}

			// reset default preferences
			if (isset($_POST['tvr_preferences_reset'])) {
				check_admin_referer('tvr_preferences_reset');
				$pref_array = $this->default_preferences;
				if ($this->savePreferences($pref_array)) {
					$this->log(
						esc_html__('Preferences were reset', 'microthemer'),
						'<p>' . esc_html__('The default program preferences were reset.', 'microthemer') . '</p>',
						'notice'
					);
				}
			}

			// save the conditional logic configuration
			/*if (isset($_GET['update_asset_loading'])) {
				$pref_array['asset_loading'] = json_decode(
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON payload (may contain user-authored CSS/JS); structure enforced by json_decode, request nonce + capability gated
					stripslashes($_POST['tvr_serialized_data']),
					true
				);
				$this->savePreferences($pref_array);
				wp_die();
			}*/



			// css filter configs
			$filter_types = array('page_specific', 'pseudo_classes', 'pseudo_elements');
			foreach ($filter_types as $type){
				if (isset($_GET[$type])) {
					// Keys include CSS pseudos like ":hover" / "::before" — do not use sanitize_key().
					$pref_sub_key = sanitize_text_field(wp_unslash($_GET["pref_sub_key"]));
					//$value = $_GET['pref_sub_key'] === 'custom-prefix' ? $_GET[$type] : intval($_GET[$type]);
					$this->preferences[$type][$pref_sub_key] = intval($_GET[$type]);
					$pref_array[$type] = $this->preferences[$type];

					if (isset($_GET['extraValue'])){
						// This is user-authored CSS selector syntax. sanitize_text_field() can strip
						// valid percent-encoded attribute values and silently change the selector,
						// so only what can't belong in a selector is removed (Schemas::cssFragment)
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by Schemas::cssFragment()
						$pref_array[$pref_sub_key.'-extraValue'] = Request\Schemas::cssFragment(wp_unslash($_GET['extraValue']));
					}

					$this->savePreferences( $pref_array );
					//echo '<pre>'. print_r($this->preferences[$type], true).'</pre>';
					wp_die();
				}
			}

			// if we got to hear, the ajax request didn't work as intended, so warn
			echo 'Yo! The Ajax call failed to trigger any function. Sort it out.';
			wp_die();

		}

	}

	// Whether a (sanitized) name is a design pack folder in the storage folder. The storage folder
	// also holds Microthemer's own folders (cache, exports), and an empty name is the storage
	// folder itself: neither may be zipped to the public exports folder or deleted as a pack.
	function isPackFolder($pack){

		if (!is_string($pack) || $pack === '' || $pack[0] === '.' || strpbrk($pack, '/\\') !== false){
			return false;
		}

		if ($this->isReservedStorageFolder($pack)){
			return false;
		}

		return is_dir($this->micro_root_dir . $pack);
	}

	// Microthemer's own folders in the storage folder (also left out of the design pack list)
	function isReservedStorageFolder($name){
		return in_array(strtolower((string) $name), array('mt', 'stock', 'content', 'zip-exports', 'tmp', 'sass', 'scss'), true);
	}

	// phpcs:disable WordPress.Security.NonceVerification -- nonce verified by microthemer_ajax_actions() via check_ajax_referer('mcth_simple_ajax', '_wpnonce'), which gates this whole call path.
	function updateDraftFolderCookie(){

		$key = 'microthemer_draft_folder';

		if (isset($_GET['delete_value'])){
			$this->deleteCookie($key);
		} else {
			// the folder's slug and logic, sanitized; the cookie stores them re-encoded
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- raw JSON string; unslashed, decoded and sanitized by Schemas::draftFolderCookie()
			$raw_json = isset($_POST['tvr_serialized_data']) && is_string($_POST['tvr_serialized_data']) ? $_POST['tvr_serialized_data'] : '';
			$checked = Request\Schemas::draftFolderCookie()->sanitizeJson($raw_json);
			if (empty($checked['errors'])) {
				$this->pluginCookie($key, wp_json_encode($checked['value']));
			}
		}

		wp_die();
	// phpcs:enable WordPress.Security.NonceVerification
	}

}
