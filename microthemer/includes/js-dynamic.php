<?php
/* Builds $data: JS (printed with wp_print_inline_script_tag() by the including page) defining
the arrays/objects that change as the user updates their settings in Microthemer.
These include: media queries, default CSS units, suggested values, design packs...
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = '';

// Only run performance functions if dev_mode is enabled
$dev_mode = TVR_DEV_MODE ? 'true' : 'false';
$data.= 'TVR_DEV_MODE = ' . $dev_mode . ';' . "\n\n";

// program data needs to be added dynamically if non-english and server doesn't allow writing JS files
// upon activation
if ( !empty($this->preferences['inlineJsProgData']) ){
	$data.= $this->write_mt_version_specific_js(false, true);
}

// add the design pack directories to the TvrMT.data.prog.combo object already defined in the static version JS file
$directories = array();
foreach ($this->file_structure as $dir => $array) {
	$exclude_dirs = array('sass', 'scss');
	if (!empty($dir) && !in_array($dir, $exclude_dirs) && !$this->has_extension($dir)) {
		$directories[] = $this->readable_name($dir);
	}
}
$data.= 'TvrMT.data.prog.combo.directories = ' . wp_json_encode($directories) . ';' . "\n\n";

// the last 20 custom site preview URLs the user enters are saved in DB
$data.= 'TvrMT.data.prog.combo.custom_paths = ' . wp_json_encode($this->preferences['custom_paths']) . ';' . "\n\n";

// the last 30 page logic items
$data.= 'TvrMT.data.prog.combo.recent_logic = ' . wp_json_encode($this->preferences['recent_logic']) . ';' . "\n\n";

// npm libraries - npm_version_input
$data.= 'TvrMT.data.prog.combo.npm_dependencies = ' . wp_json_encode(array()) . ';' . "\n\n";
$data.= 'TvrMT.data.prog.combo.current_npm_dependencies = ' . wp_json_encode(array()) . ';' . "\n\n";
$data.= 'TvrMT.data.prog.combo.npm_version_input = ' . wp_json_encode(array()) . ';' . "\n\n";

// path to the icon font
$data.= 'TvrMT.data.dyn.icon_font_face_style = ' . wp_json_encode( array('css' => $this->load_icon_font(true)) ) . ';' ."\n\n";
//'icon_font_face_style' => $this->load_icon_font(true),

// ready combo for MQ and CSS unit sets (dynamic so they can be translated and added to with integrations)
foreach ($this->mq_sets as $set => $junk){
	$mq_sets[] = $set;
}
$data.= 'TvrMT.data.prog.combo.mq_sets = ' . wp_json_encode($mq_sets) . ';' . "\n\n";

// import css files for quick navigation
$data.= 'TvrMT.data.prog.combo.viewed_import_stylesheets = ' . wp_json_encode($this->preferences['viewed_import_stylesheets']) . ';' . "\n\n";

// user's current media queries (combined with All Devices)
$data.= 'var TvrMQsCombined = ' . wp_json_encode($this->combined_devices()) . ';' . "\n\n";

// available builder integrations
$data.= 'TvrMT.data.dyn.integrations = ' . wp_json_encode($this->integrations) . ';' . "\n\n";
$data.= 'TvrMT.data.dyn.blockBuilders = ' . wp_json_encode($this->blockBuilders) . ';' . "\n\n";

// the full ui options in JS form. Later, this will be used for speed optimisations
$data.= 'TvrMT.data.dyn.builder_breakpoints = ' . wp_json_encode(
	array(
		'gutenberg' => $this->gutenberg_breakpoints,
		'elementor' => $this->elementor_breakpoints,
		'oxygen' => $this->oxygen_breakpoints
	)
) . ';' . "\n\n";

// full preferences (can replace the above), apart from ones the page has no use for that a user
// shouldn't see, e.g. licence keys a super admin entered for this site (see Extensions\Licensing)
$data.= 'TvrMT.data.dyn.pref = ' . wp_json_encode(
	array_diff_key($this->preferences, array_flip($this->extFilter('private_preferences', array())))
) . ';' . "\n\n";

// layout presets depend on whether the AI panel is available (build and preference), so they can't go in the static program data
$data.= 'TvrMT.data.dyn.suggested_screen_layouts = ' . wp_json_encode($this->suggested_screen_layouts) . ';' . "\n\n";

// just sels (going for the full ui)
//$data.= 'window.TvrMT.sels = ' . wp_json_encode($this->sel_lookup) . ';' . "\n\n";

// the full ui options in JS form. Later, this will be used for speed optimisations
$data.= 'TvrMT.data.dyn.ui = ' . wp_json_encode($this->settingsForUI($this->options)) . ';' . "\n\n";

global $is_IE;

// the full ui options in JS form. Later, this will be used for speed optimisations
$data.= 'TvrMT.data.dyn.is_IE = ' . ($is_IE ? 1: 0) . ';' . "\n\n";

// if multi-site, store blog details (use path to correct multi-site relative path)
if (function_exists('get_blog_details')){
	$data.= 'TvrMT.data.dyn.blog_details = ' . wp_json_encode(get_blog_details( $this->multisite_blog_id )) . ';' . "\n\n";
}

// client side SASS stuff
if ($this->client_scss()){

	// store file structure of storage folder so client-side SCSS paths can be cross-checked
	// may also be helpful for page-logic feature
	$data.= 'TvrMT.data.dyn.micro_files = ' . wp_json_encode($this->file_structure) . ';' . "\n\n";

	// replacement content for @import statements
	$data.= 'TvrMT.data.dyn.preloaded_sass_imports = ' . wp_json_encode($this->get_sass_import_content()) . ';' . "\n\n";

	// log that client-side scss is in use
	$data.= 'TvrMT.data.dyn.client_scss = "1";' . "\n\n";
}

// the storage folder and url paths
$data.= 'TvrMT.data.dyn.micro_root_url = ' . wp_json_encode($this->micro_root_url) . ';' . "\n\n";

// the default site pages list (posts and pages limited to 30, more results collected on search)
$data.= 'TvrMT.data.dyn.site_pages = ' . wp_json_encode($this->get_site_pages()) . ';' . "\n\n";

// get a list of snippet names and cache the most recently updated
$data.= 'TvrMT.data.dyn.snippets = ' . wp_json_encode((object) array(
		//'names' => (object) $this->contentMethod('getAllSnippetNames'),
		'cache' => (object) $this->supportContent()
			? $this->contentMethod('getSnippetCache', array('getSnippetsOfType'))
			: array(), // merge snippets loading on the current page with this
		'deleted' => (object) array() // for sending to the server-side script
	)) . ';' . "\n\n";

// the default site pages list (posts and pages limited to 30, more results collected on search)
//$data.= 'TvrMT.data.dyn.site_pages = ' . wp_json_encode($this->get_site_pages()) . ';' . "\n\n";

// URLs for placehold (light, dark, current)
//$data.= 'TvrMT.data.dyn.placeholderURLs = ' . wp_json_encode($this->get_placeholder_urls()) . ';' . "\n\n";

// dynamic menus: enq_js, mqs, custom code, animation, preset
$current_user = wp_get_current_user();
$data.= 'TvrMT.data.dyn.ui_config = ' . wp_json_encode(array(
	'mt_nonlog_nonce' => wp_create_nonce('mt_nonlog_check'), // note this won't work with browser sync enabled
	'mt_builder_redirect_nonce' => wp_create_nonce('mt_builder_redirect_check'), // note this won't work with browser sync enabled
	'preview_item_id' => $this->get_preview_item_id(),
	'reporting' => $this->reporting,
	'errorsRequiringData' => $this->errorsRequiringData,
	'locale' => $this->locale,
	'user_display_name' => !empty($current_user->display_name) ? $current_user->display_name : '',
	'supportContent' => $this->supportContent(),
	'supportGUICSS' => $this->supportGUICSS(),
	'appName' => $this->appNameFull,
	'plugin_version' => $this->version
	)) . ';' . "\n\n";




