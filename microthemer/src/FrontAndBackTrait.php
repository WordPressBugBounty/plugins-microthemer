<?php

// Data and functions needed on both the admin, auth, and non-auth frontend
// Perhaps this could replace Common static functions, then we leave remaining in Plugin trait

namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait FrontAndBackTrait {

	var $appName = 'Microthemer';
	var $appNameFull = 'Microthemer A+';

	function getAppName($shorthand = true){
		return $this->hasCSSCapability() ?
			($this->hasContentCapability() && !$shorthand
				? 'Microthemer A+'
				: 'Microthemer')
			: 'Amender';
	}

	function setAppName(){
		$this->appName = $this->getAppName();
		$this->appNameFull = $this->getAppName(0);
	}

	function isCSSEditor(){
		return defined('TVR_CSS_EDITOR') && !empty(constant('TVR_CSS_EDITOR'));
	}

	function isContentEditor(){
		return defined('TVR_CONTENT_EDITOR') && !empty(constant('TVR_CONTENT_EDITOR'));
	}

	// We want to return true when neither constant is defined - when Microloader is running
	function isFullEditor(){
		return !defined('TVR_CSS_EDITOR') && !defined('TVR_CONTENT_EDITOR');
	}

	function hasContentDir(){
		return is_dir(dirname(__FILE__) . '/Content');
	}

	function hasCSSDir(){
		return is_dir(dirname(__FILE__) . '/CSS');
	}

	function hasContentCapability(){
		return !$this->excludeAmender() && (
			$this->hasContentDir() && ($this->isFullEditor() || $this->isContentEditor() || !empty($this->preferences['content_addon']))
			);
	}

	function hasCSSCapability(){
		return $this->hasCSSDir() && ($this->isFullEditor() || $this->isCSSEditor() || !empty($this->preferences['css_addon']));
	}

	function contentRequiresSetup(){

		global $wpdb;

		if ($this->hasContentCapability()){
			$table = $wpdb->prefix . "micro_content";
			return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like($table) ) ) !== $table;
		}

		return false;
	}

	// whether content modifications can be published (set by an extension, see extFilter())
	function canPublishContent(){
		return $this->contentClass && apply_filters('microthemer_content_publishing', false);
	}

	// whether the extended CSS property groups are active (set by an extension)
	function extendedCSS(){
		return (bool) apply_filters('microthemer_extended_css', false);
	}

	function supportContent($forPublishing = false){
		return $this->contentClass
			&& (!$forPublishing || $this->canPublishContent());
	}

	function supportGUICSS($forExtended = false){
		return $this->hasCSSCapability()
		       && (!$forExtended || $this->extendedCSS());
	}

	// Conditionally run a content method
	function contentMethod($method, $args = array(), $forPublishing = false){

		if (!$this->excludeAmender() && $this->contentClass && method_exists($this->contentClass, $method)
		    && (!$forPublishing || $this->canPublishContent())){
			return call_user_func_array(array(&$this->contentClass, $method), $args);
		}

		return false;
	}

	// Conditionally run a CSS method
	function cssMethod($method, $args = array(), $forExtended = false){

		if ($this->cssClass && method_exists($this->cssClass, $method)
		    && (!$forExtended || $this->extendedCSS())){
			return call_user_func_array(array(&$this->cssClass, $method), $args);
		}

		return false;
	}

	function getDefaultNPM(){

	}

	/* Integrations */

	function isBricksUi(){
		return isset($_GET['bricks']) && $_GET['bricks'] === 'run';
	}

	function isBuilder() {

		foreach (array(
			array('bricks', 'run'),
			array('elementor-preview'),
			array('action', 'elementor'),
			array('et_fb', '1'),
			array('fl_builder'),
			array('ct_builder'),
			array('oxygen_iframe'),
			array('vc_editable'),
			array('tve'),
			array('tcb_editor'),
			array('tve_frontend'),
			array('cornerstone'),
			array('cs_preview'),
			array('fusion_load_frontend'),
			array('fb-edit', 'true'),
			array('zionbuilder-preview'),
			array('zionbuilder', 'true'),
			array('breakdance_editor', 'true')
		) as $p) {

			if (!isset($_GET[$p[0]])) {
				continue;
			}

			if (!isset($p[1]) || (string) $_GET[$p[0]] === $p[1]) {
				return true;
			}
		}

		return false;
	}


	function excludeAmender(){

		// maybe exclude all admin area... At least for server-side
		return $this->isBuilder();
	}

}
