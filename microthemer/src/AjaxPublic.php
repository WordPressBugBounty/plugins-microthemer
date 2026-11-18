<?php

// This is mainly to allow lazy-loading content, but may have other uses if we need a non-auth ajax request
namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AjaxPublic {

	public function __construct() {
		add_action('admin_init', array($this, 'hookPublicAjax'));
	}

	// Register AJAX actions for public and logged-in users
	public function hookPublicAjax() {
		add_action('wp_ajax_nopriv_tvra_request', array($this, 'processPublicAjax'));
		add_action('wp_ajax_tvra_request', array($this, 'processPublicAjax'));
	}

	// the request body is JSON: {"lazy_ids": [...]}, sanitized against Schemas::lazyContent()
	function processPublicAjax(){
		$body = file_get_contents('php://input');
		// php://input is not magic-quoted. sanitizeJson() unslashes, which is correct for $_POST
		// and would turn JSON escapes (\uXXXX, \") into something else here, so slash it first.
		$body = is_string($body) ? wp_slash($body) : '';
		$request = Request\Schemas::lazyContent()->sanitizeJson($body, 65536);
		if (empty($request['errors'])){
			$this->getLazyContent($request['value']);
		}
	}

	function getLazyContent($request){

		// Set the response content type to JSON
		header('Content-Type: application/json');

		if (empty($request['lazy_ids']) || !is_array($request['lazy_ids'])) {
			http_response_code(400);
			echo wp_json_encode(['error' => 'Invalid request']);
			exit;
		}

		// already sanitized and capped at 50 (Schemas::lazyContent)
		$response = $this->lazyContentResponse(
			array_values($request['lazy_ids']), $this->requestedLazyState($request)
		);

		// Send the JSON response
		echo wp_json_encode($response);
		exit;

	}

	// Visitors only get content stored by a published render. Draft content (from an
	// administrator's preview) is only served to users who can see drafts (see tvr-microthemer.php)
	function requestedLazyState($request){
		return isset($request['published']) && (int) $request['published'] === 0
		       && current_user_can('manage_options') ? 0 : 1;
	}

	function lazyContentResponse($lazy_ids, $published){

		global $wpdb;

		// Prepare the query to fetch the lazy content based on the provided IDs
		$placeholders = implode(', ', array_fill(0, count($lazy_ids), '%s'));

		// Fetch the lazy content from the database
		$results = $wpdb->get_results(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- dynamic IN (%s,...) list for lazy content slugs.
			$wpdb->prepare(
				"SELECT slug, content FROM {$wpdb->prefix}micro_content
                     WHERE type = 'lazy' AND published = %d AND slug IN ($placeholders)",
				...array_merge(array((int) $published), $lazy_ids)
			)
			// phpcs:enable
		);

		// Prepare the data for the response
		$response = array('store' => []);
		foreach ($results as $row) {
			// content is already the list of HTML strings, one per element that was lazy loaded
			$response['store'][$row->slug] = json_decode($row->content);
		}

		return $response;
	}
}
