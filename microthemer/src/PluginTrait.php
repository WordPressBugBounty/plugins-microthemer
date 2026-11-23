<?php

/*
 * Common properties and methods for Admin and AssetAuth classes
 */

namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait PluginTrait {

	var $version = '7.5.6.1';
	var $db_chg_in_ver = '7.5.2.8';
	var $minimum_wordpress = '5.6';
	var $preferencesName = 'preferences_themer_loader';
	var $autoloadPreferencesName = 'microthemer_autoload_preferences';
	var $microthemeruipage = 'tvr-microthemer.php';
	var $microthemespage = 'tvr-manage-micro-themes.php';
	var $managesinglepage = 'tvr-manage-single.php';
	var $preferencespage = 'tvr-microthemer-preferences.php';
	var $detachedpreviewpage = 'tvr-microthemer-preview-wrap.php';
	var $docspage = 'tvr-docs.php';
	var $fontspage = 'tvr-fonts.php';
	//var $preferences = array(); PHP warning when AssetLoad runs
	var $current_user_id = -1;
	var $wp_ajax_url = '';

	// Previously dynamic properties
	// @var strings dir/url paths
	var $thistmpdir;
	var $home_url;
	var $wp_admin_url;
	var $wp_blog_admin_url;
	var $country_codes;
	var $nth_formulas;
	var $fav_css_filters;
	var $css_filters;
	var $min_and_max_mqs;
	var $container_queries;
	var $default_dev_preferences;
	var $aiPanel = false;   // set only in AI-enabled builds (src/AIPanel.php present)
	var $moduleUI = false;  // set only in builds with the Amender content classes (src/Content present)
	var $extensions = array(); // components in src/Extensions (not present in every build)
	var $coreOnly = false;  // true while writing data that must not include extension output
	var $browser_events;
	var $browser_event_keys;
	var $system_fonts;
	var $enq_js_structure;
	var $mq_structure;
	var $menu;

	var $thispluginurl = '';
	var $thisplugindir = '';
	var $multisite_blog_id = false;
	var $micro_root_dir = '';
	var $micro_root_url = '';
	var $site_url = '';
	var $content;

	function loadTextDomain(){
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for builds installed outside the plugin directory; redundant but harmless on wp.org (auto-loads via Domain Path since 4.6).
		load_plugin_textdomain(
			'microthemer',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	// Where Microthemer keeps its files (see Helper::storage), incl. a private tmp folder for
	// downloaded files
	function setStoragePaths(){
		$storage = Helper::storage();
		$this->micro_root_dir = $storage['dir'];
		$this->micro_root_url = $storage['url'];
		$this->thistmpdir = $storage['dir'] . 'tmp/';
	}

	// Extension points. Components in src/Extensions (present in some builds only) hook into
	// these through the WordPress hooks API; without them the default value passes through.
	function extFilter($name, $value, ...$args){
		return $this->coreOnly ? $value : apply_filters('microthemer_' . $name, $value, ...$args);
	}

	function extAction($name, ...$args){
		if (!$this->coreOnly){
			do_action('microthemer_' . $name, ...$args);
		}
	}

	// get/set cookie
	function pluginCookie($key, $value = false, $expiration = 0){ // 0 = expire when browser closes

		if ($value) {
			$this->deleteCookie($key);
			// only PHP reads it, and only on this site's own pages
			return setcookie($key, $value, array(
				'expires' => $expiration,
				'path' => '/',
				'domain' => COOKIE_DOMAIN,
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			));
		}

		return isset($_COOKIE[$key]) ? sanitize_text_field(wp_unslash($_COOKIE[$key])) : false;
	}

	// delete cookie
	/*function deleteCookie($key){
		unset($_COOKIE[$key]);
	}*/

	function deleteCookie($key, $expires = 0, $path = '/', $domain = COOKIE_DOMAIN){

		setcookie($key, "", $expires, $path, $domain);

		unset($_COOKIE[$key]);
	}


	function setAdminAjaxUrl(){
		$this->wp_ajax_url = $this->wp_blog_admin_url . 'admin-ajax.php' . '?action=mtui&mcth_simple_ajax=1&page='.$this->microthemeruipage . '&_wpnonce='.wp_create_nonce('mcth_simple_ajax');
	}

	function jsonResponse($data, $clean = true){

		if ($clean){
			// Clean all levels of output buffering - this fixes issues like <p> wrapped around JSON
			while (ob_get_level()) {
				ob_end_clean();
			}
		}

		header('Content-type: application/json');

		http_response_code(200);

		echo wp_json_encode($data);
		exit;
	}



}