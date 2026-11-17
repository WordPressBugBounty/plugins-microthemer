<?php
/*
Plugin Name: Microthemer
Plugin URI: https://themeover.com/microthemer
Text Domain: microthemer
Domain Path: /languages
Description: Microthemer is a powerful AI & visual CSS editor that cares about site performance.
Version: 7.5.5.8
Author: Themeover
Author URI: https://themeover.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Copyright 2025 by Sebastian Webb @ Themeover

// Stop direct call
if ( !defined( 'ABSPATH') ) exit;

// Include a simple autoload function
require dirname(__FILE__) . '/src/autoload.php';

// Plugin constants
if (!defined('TVR_DEV_MODE')) define('TVR_DEV_MODE', false);
if (!defined('TVR_DEBUG_DATA')) define('TVR_DEBUG_DATA', false);
if (!defined('TVR_CSS_EDITOR')) define('TVR_CSS_EDITOR', true);
if (!defined('MT_IS_ACTIVE')) define('MT_IS_ACTIVE', true);

// Flag activation for the setup that runs on the next admin page load (AdminInitTrait::
// check_microthemer_activation_flag), where all of WordPress is available. Registered here because
// WordPress includes the plugin file when activating it, after the 'init' action has run.
register_activation_hook(__FILE__, function(){
	add_option('microthemer_activation', '1');
});

// Initiate Microthemer functionality
if (!function_exists('initiateMicrothemer')){
	function initiateMicrothemer() {

		// Admin dashboard
		if (is_admin()) {

			// Only run admin code for a logged in Administrator
			if (current_user_can('manage_options')){
				new \Microthemer\Admin();
			}

			// Public ajax
			new \Microthemer\AjaxPublic();

		}

		// Site frontend
		else {

			// logged in Administrator viewing the frontend - include editing assets
			if (current_user_can('manage_options')){
				new \Microthemer\AssetAuth('edit');
			}

			// Non-admin viewing the site - just load minimal assets
			else {
				new \Microthemer\AssetLoad();
			}

		}

		// wp-cron is not an admin request, so the admin loader never registers Pro's
		// update source there. The scheduled check would then rebuild the update list
		// with no Pro update, and background auto-updates would never run. The file
		// is absent from the wp.org build; boot() is a no-op if the admin loader
		// already registered the update source (WP-CLI).
		if ( wp_doing_cron() || ( defined('WP_CLI') && WP_CLI ) ) {
			$pro_updates = dirname(__FILE__) . '/src/Extensions/Licensing/UpdateChecker.php';
			if ( is_readable($pro_updates) ) {
				\Microthemer\Extensions\Licensing\UpdateChecker::boot();
			}
		}
	}

	add_action('plugins_loaded', 'initiateMicrothemer');
}

// Explain how admins should set up both Amender and Microthemer correctly
elseif (is_admin()) {
	add_action(
		'admin_notices',
		function() {
			if (current_user_can('manage_options')){
				echo '
				<div class="notice notice-error">
					<p>' .
					     '<strong>You cannot run Microthemer and Amender as two separate plugins</strong>. Remove one, then enable Amender or Microthemer as an addon via <i>Settings > General > Preferences</i>.' .
					'</p>
				</div>';
			}

		}
	);
}

