<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// define('WINABSPATH', str_replace("\\", "/", ABSPATH) ); // required for Windows & XAMPP

/* discussion of when to use site_url vs home_url: http://premium.wpmudev.org/forums/topic/when-referencing-content-your-plugins-seem-to-use-site_url
See also: http://codex.wordpress.org/Giving_WordPress_Its_Own_Directory
- Use site_url for referencing actual files paths (so currently correct for most of paths below)
- Use home_url for loading the homepage (site preview should use this)
- considerations: multi-site, SSL
*/

// check ssl
if ( ! function_exists( 'is_ssl' ) ) {
	/**
	 * Polyfill of WordPress core's is_ssl(), for the edge case where this file is
	 * reached before wp-includes/load.php has defined it.
	 *
	 * Deliberately avoids wp_unslash()/sanitize_text_field(): if core's is_ssl() is
	 * missing then those may be missing too, and a fatal here would be worse than the
	 * thing being guarded against. Both values are compared against fixed literals and
	 * neither is stored, echoed, or used in a query.
	 */
	function is_ssl() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- see docblock: server-set values compared to literals, in a polyfill that must not depend on WP functions
		if ( isset($_SERVER['HTTPS']) ) {
			if ( 'on' === strtolower( (string) $_SERVER['HTTPS'] ) )
				return true;
			if ( '1' === (string) $_SERVER['HTTPS'] )
				return true;
		} elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' === (string) $_SERVER['SERVER_PORT'] ) ) {
			return true;
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput
		return false;
	}
}

// site URLs
$site_url = untrailingslashit(site_url()). '/';

// the plugin's own location (this file is in the plugin's root folder)
$this->thisplugindir = wp_normalize_path(plugin_dir_path( __FILE__ ));
$this->thispluginurl = plugin_dir_url( __FILE__ );

// for creating root relative paths (good for mixed ssl)
$this->site_url = untrailingslashit($site_url);
$this->home_url = home_url();

// I discovered an instance when home_url() returns http even if it is set with https on WP General settings
// if MT loads in https, the frontend must too to avoid mixed content warning - so ensure https
if (is_ssl() and preg_match('/http(?!s)/', $this->home_url)){
	$this->home_url = str_replace('http', 'https', $this->home_url);
}

// save the admin dashboard url
$this->wp_admin_url = network_admin_url(); // this isn't being used anywhere
$this->wp_blog_admin_url = admin_url(); // A contribution from Abland: http://themeover.com/forum/topic/microthemer-3-0-what-did-we-get-right-what-should-we-improve/?view=all

/* Another Abland contribution: http://themeover.com/forum/topic/settings-options-in-admin/ */
global $blog_id;
$this->multisite_blog_id = $blog_id;

// where Microthemer keeps its files (uploads/microthemer - see Helper::storage)
$this->setStoragePaths();
