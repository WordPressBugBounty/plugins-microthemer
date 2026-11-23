<?php

namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Helper
 *
 * A collection of static helper functions that are used in the admin, frontend, logic (inc stand alone)
 */

class Helper {

	public static $doDebug = false;
	public static $debugOutput = '';

	// Folder slugs name one generated file. Preserve Unicode and ordinary punctuation,
	// but never allow paths (including Windows separators) or traversal fragments.
	public static function isValidFolderSlug($slug){
		return (is_string($slug) || is_int($slug)) && (string) $slug !== ''
			&& !preg_match('/[\/\\\\\0]|\.\./', (string) $slug);
	}

	public static function conditionalStylesheetPath($root, $state, $slug){
		if (!in_array($state, array('active', 'draft'), true) || !self::isValidFolderSlug($slug)){
			return false;
		}

		$root = realpath($root);
		$dir = $root === false ? false : realpath($root . '/mt/conditional/' . $state);
		$file = $dir === false ? false : realpath($dir . '/' . $slug . '.css');
		if ($file === false || !is_file($file)
			|| strpos($dir . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0
			|| strpos($file, $dir . DIRECTORY_SEPARATOR) !== 0){
			return false;
		}

		return $file;
	}

	// Escape HTML closing delimiters (including a trailing partial delimiter), while keeping
	// ordinary '<' comparisons valid in CSS media queries.
	public static function escapeInlineCSS($css){
		return preg_replace('~<(?=/|\z)~', '\\\\3c ', $css);
	}

	// The name of a page's Tailwind cache file (mt/cache/tailwind/{classes,styles}), built the way
	// AdminContent names the files it writes. The id can come from the URL (?tag=a,b is left raw by
	// WordPress), so it must not be able to name a file outside the cache folder.
	public static function tailwindCacheName($type, $id){
		return $type === null || $type === '' ? '' : sanitize_title($type . '-' . $id);
	}

	// The Google Fonts stylesheet URL is built by the editor and saved with the settings (and in
	// design packs), then loaded on every page, so it may only point at Google Fonts.
	public static function isGoogleFontsUrl($url){
		return is_string($url)
		       && preg_match('#^(https:)?//fonts\.googleapis\.com/css2?\?[^\s"\'<>\\\\]*$#i', $url) === 1;
	}

	// The comment and opening line of a media query block in the generated stylesheets. The label
	// and query come from the media query form, preference imports and design packs, so the label
	// can't close its comment and the query can't hold braces or semicolons (a rule of its own).
	public static function mediaQueryOpening($label, $query){
		$label = str_replace('*/', '* /', Request\Schemas::stripControlChars((string) $label));
		$query = Request\Schemas::cssFragment($query);
		return "\n/*( $label )*/\n$query {\n";
	}

	// The active_events setting (a JSON string from the editor, or its decoded array) as an array that
	// is safe to hand to animation-events.js: every string loses tags, and the selectors (code, target)
	// lose any "<", so jQuery can never build elements from them. JSON escapes such as < are
	// decoded first, so they can't slip past.
	public static function sanitizeActiveEvents($events){
		if (is_string($events)){
			$events = json_decode($events, true);
		}
		if (!is_array($events)){
			return array();
		}
		$clean = array();
		foreach ($events as $eventKey => $list){
			if (!is_array($list)){
				continue;
			}
			$eventKey = Request\Schemas::stripTags((string) $eventKey);
			foreach ($list as $event){
				if (!is_array($event)){
					continue;
				}
				array_walk_recursive($event, function (&$value, $key){
					if (is_string($value)){
						$value = Request\Schemas::stripTags($value);
						if ($key === 'code' || $key === 'target'){
							$value = str_replace('<', '', $value);
						}
					}
				});
				$clean[$eventKey][] = $event;
			}
		}
		return $clean;
	}

	// Unserialize the plugin's stored data (revisions, snippets, settings). Only plain stdClass
	// objects are rebuilt: any other class comes back as __PHP_Incomplete_Class, so stored data
	// can't trigger PHP object injection. Values that aren't serialized are returned unchanged.
	public static function unserializeData($data){
		if (!is_string($data) || (function_exists('is_serialized') && !is_serialized($data))){
			return $data;
		}
		return unserialize($data, array('allowed_classes' => array('stdClass'))); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- classes restricted to stdClass
	}

	public static function debug($message, $data = false, $die = false){

		if (Helper::$doDebug){

			$output = $message;

			if ($data){
				$output.= ':<pre>' . print_r($data, 1) . '</pre>';
			}

			if (!$data){
				$output.= "\n\n";
			}

			if ($die){
				wp_die(wp_kses_post($output)); // debug output only
			} else {
				if (Helper::$doDebug !== 'silent'){
					//echo $output;
					error_log($output . "\n\n");
				}
				//Helper::$debugOutput.= $output;
			}
		}

	}

	public static function isBlockAdminPage($id = null){

		global $pagenow;
		$postId = null;
		$isBlockAdmin = false;

		//$regular = $pagenow === 'post.php';

		if (!is_admin()){
			return false;
		}

		if ($pagenow === 'post-new.php'){
			$postId = -1;
			$isBlockAdmin = true;
		} elseif ($pagenow === 'post.php' && isset($_GET['post'])){
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context detection from the core post editor URL; nothing saved, value cast to int
			$postId = intval($_GET["post"]);
			$isBlockAdmin = true;
		} elseif ($pagenow === 'site-editor.php'){
			if (isset($_GET['postId'])){
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context detection from the core site editor URL; nothing saved, value sanitized
				$postId = sanitize_text_field(wp_unslash($_GET["postId"]));
			} else {
				$postId = Helper::homePageData()['id'];
			}
			$isBlockAdmin = true;
		}

		return $isBlockAdmin && ($id === 'global' || $id == $postId);
	}

	// get the template file WordPress is loading e.g. page, single, single-with-sidebar
	// this is duplicate code of method with same name in Common class, which may not be present
	public static function getCurrentTemplateSlug(){

		return !empty(Logic::$cache['settings']['currentTemplate'])
			? Logic::$cache['settings']['currentTemplate']
			: '';

	}

	// determine what type of FSE page we're editing
	public static function getFSEType($postType, $categoryType){
		return $postType === 'wp_block' || $categoryType === 'pattern'
			? 'wp_pattern'
			: $postType;
	}

	// determine what type of FSE page we're editing
	public static function getTemplateFromPostId($postId, $post){

		if (Common::$live_template !== false){
			$currentTemplate = Common::$live_template;
			//echo 'Retrieved from Common::$live_template: ' . $currentTemplate . '<br>';
		} else {
			$currentTemplate = get_post_meta($postId, '_wp_page_template', true);
			//echo 'Retrieved from get_post_meta: ' . $currentTemplate . '<br>';
		}

		if (!$currentTemplate){
			$currentTemplate = $post
				? ($post->post_type === 'post'
					? 'single'
					: $post->post_type)
				: 'page';
		}
		return $currentTemplate;
	}

	// if it's a front page fallback on the FSE admin page
	public static function isFrontOrFallback($orDirectView = true){

		global $pagenow;

		$fse = $pagenow === 'site-editor.php';
		$regular = $pagenow === 'post.php';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only context detection from core editor URLs (post.php / site-editor.php); nothing saved, values sanitized
		$post = isset($_GET["post"]) ? intval($_GET["post"]) : null;
		$postId = isset($_GET["postId"]) ? sanitize_text_field(wp_unslash($_GET["postId"])) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$homePage = Helper::homePageData();
		$fseFallback = $fse && $postId === null;
		$fseDirect = $fse && $postId === $homePage['id'];
		// The request ID is cast above, so normalize the option value before the strict comparison too.
		$regularDirect = $regular && $post === (int) $homePage['id'];
		$frontDirect = !is_admin() && (
			is_front_page() || ( $homePage['id'] > 0 && is_page($homePage['id']) )
		);

		return $fseFallback || ($orDirectView && ($fseDirect || $regularDirect || $frontDirect));
	}

	// Where Microthemer keeps its files (generated CSS/JS, design packs, caches): a folder named after
	// the plugin in the uploads directory, which wp_upload_dir() resolves per site on multisite.
	// Sites that stored files in the old wp-content/micro-themes location keep using it until the
	// files have been moved (see Admin::maybeMoveStorage) - and for good if moving them failed, so a
	// failed move never breaks the site.
	public static function storage(){

		$blogId = get_current_blog_id();

		if (isset(self::$storageCache[$blogId])){
			return self::$storageCache[$blogId];
		}

		// Not moved yet, or moved and then a rollback wrote the old folder again: keep
		// serving it until the move (re)succeeds. A leftover old folder from a delete
		// that failed is older than the move, so it does not count.
		// "Wrote" means newer CSS/JS assets: anything else turning up there (an index.php, what's
		// left of an unfinished delete) must never leave the site without styles.
		$legacy = self::legacyStorage();
		if (is_dir($legacy['dir']) &&
		    (!self::storageMoved() || (self::legacyHasNewerAssets() && self::legacyRewrittenSinceMove()))){
			return self::$storageCache[$blogId] = $legacy;
		}

		return self::$storageCache[$blogId] = self::uploadsStorage();
	}

	public static $storageCache = array();

	// The markup Microthemer's own admin screens are built from: everything its HTML builders emit
	// (form controls, menus, dialogs, icons, the site preview iframe), for escaping their output
	// with wp_kses() where it is echoed. No scripts, no inline event handlers.
	public static function uiAllowedHtml(){

		static $allowed = null;

		if ($allowed !== null){
			return $allowed;
		}

		$common = array_fill_keys(array(
			'class', 'id', 'title', 'style', 'rel', 'role', 'tabindex', 'data-*', 'name', 'value', 'type',
			'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-modal', 'aria-hidden', 'aria-expanded',
			'aria-controls', 'aria-haspopup', 'aria-selected', 'aria-pressed', 'aria-checked', 'hidden', 'lang', 'dir',
			'contenteditable',
		), true);

		$tags = array(
			'div' => array(), 'span' => array(), 'p' => array(), 'ul' => array(), 'ol' => array('start' => true),
			'li' => array(), 'b' => array(), 'strong' => array(), 'i' => array(), 'em' => array(), 'u' => array(),
			'small' => array(), 'sup' => array(), 'sub' => array(), 'code' => array(), 'pre' => array(),
			'kbd' => array(), 'br' => array(), 'hr' => array(), 'blockquote' => array(),
			'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'h5' => array(), 'h6' => array(),
			'section' => array(), 'header' => array(), 'footer' => array(), 'nav' => array(), 'article' => array(),
			'aside' => array(), 'figure' => array(), 'figcaption' => array(), 'details' => array('open' => true),
			'summary' => array(), 'fieldset' => array(), 'legend' => array(),
			'table' => array('cellspacing' => true, 'cellpadding' => true), 'thead' => array(), 'tbody' => array(), 'tfoot' => array(), 'tr' => array(),
			'th' => array('colspan' => true, 'rowspan' => true, 'scope' => true),
			'td' => array('colspan' => true, 'rowspan' => true), 'caption' => array(),
			'a' => array('href' => true, 'target' => true, 'download' => true),
			'img' => array('src' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true),
			'iframe' => array('src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'loading' => true, 'allow' => true),
			'form' => array('action' => true, 'method' => true, 'enctype' => true, 'autocomplete' => true, 'target' => true),
			'label' => array('for' => true),
			'button' => array('disabled' => true),
			'input' => array(
				'checked' => true, 'disabled' => true, 'readonly' => true, 'placeholder' => true, 'autocomplete' => true,
				'spellcheck' => true, 'accept' => true, 'multiple' => true, 'min' => true, 'max' => true, 'step' => true,
				'maxlength' => true, 'size' => true, 'list' => true, 'required' => true, 'pattern' => true,
			),
			'textarea' => array(
				'rows' => true, 'cols' => true, 'placeholder' => true, 'autocomplete' => true, 'spellcheck' => true,
				'readonly' => true, 'disabled' => true, 'maxlength' => true, 'wrap' => true,
			),
			'select' => array('multiple' => true, 'disabled' => true, 'size' => true),
			'option' => array('selected' => true, 'disabled' => true, 'label' => true),
			'optgroup' => array('label' => true, 'disabled' => true),
			'datalist' => array(),
			// icons
			'svg' => array('viewbox' => true, 'xmlns' => true, 'width' => true, 'height' => true, 'fill' => true, 'preserveaspectratio' => true, 'focusable' => true),
			'g' => array('fill' => true, 'transform' => true), 'defs' => array(), 'symbol' => array('viewbox' => true),
			'use' => array('href' => true, 'xlink:href' => true),
			'path' => array('d' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true),
			'circle' => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true),
			'rect' => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true),
			'line' => array('x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true),
			'polygon' => array('points' => true, 'fill' => true, 'stroke' => true),
			'polyline' => array('points' => true, 'fill' => true, 'stroke' => true),
			'lineargradient' => array('gradienttransform' => true, 'gradientunits' => true, 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true),
			'stop' => array('offset' => true, 'stop-color' => true, 'stop-opacity' => true),
		);

		foreach ($tags as $tag => $attributes){
			$allowed[$tag] = array_merge($common, $attributes);
		}

		return $allowed;
	}

	// URL protocols the admin screens use (the site preview starts as about:blank)
	public static function uiAllowedProtocols(){
		return array_merge(wp_allowed_protocols(), array('about'));
	}

	// the uploads folder location, whether or not it is in use yet
	public static function uploadsStorage(){

		$uploads = wp_upload_dir(null, false);

		return array(
			'dir' => trailingslashit(wp_normalize_path($uploads['basedir'])) . 'microthemer/',
			'url' => trailingslashit(set_url_scheme($uploads['baseurl'])) . 'microthemer/',
			'legacy' => false,
		);
	}

	// whether this site's files are in (or have nothing to move to) the uploads folder
	public static function storageMoved(){
		return (int) get_option('microthemer_storage_version') >= 2;
	}

	// The old folder was written again after a completed move (a rollback to a version
	// that still uses it). A directory's mtime does not change when an existing CSS
	// file is overwritten, so compare the remaining files with the post-move baseline.
	public static function legacyRewrittenSinceMove($forceCheck = false){

		if (!self::storageMoved()){
			return false;
		}

		$legacy = self::legacyStorage();
		if (!is_dir($legacy['dir']) || !self::hasMovableFiles($legacy['dir'])){
			return false;
		}

		$baseline = get_option('microthemer_legacy_storage_fingerprint');
		if (is_string($baseline) && $baseline !== ''){
			// A residual folder can be large. Cache a clean result briefly for page
			// loads; the admin migration path forces a fresh comparison before moving.
			$cacheKey = 'microthemer_legacy_storage_checked';
			$cached = get_transient($cacheKey);
			if (!$forceCheck && $cached === $baseline){
				return false;
			}
			$current = self::storageFingerprint($legacy['dir']);
			if ($current === null && $baseline === 'unreadable'){
				return self::legacyHasNewerAssets(); // can't compare contents
			}
			if ($current === $baseline){
				if ($cached !== $baseline){
					set_transient($cacheKey, $baseline, MINUTE_IN_SECONDS);
				}
				return false;
			}
			delete_transient($cacheKey);
			return true;
		}

		// Older moves have no baseline. Check files as well as the directory until
		// the next successful migration records one.
		$latestMtime = self::storageLatestMtime($legacy['dir']);
		$legacyMtime = filemtime($legacy['dir']);
		if ($legacyMtime === false){
			return false;
		}

		$movedAt = (int) get_option('microthemer_storage_moved_at');
		if ($movedAt){
			return max($legacyMtime, $latestMtime) > $movedAt;
		}

		// Moves from before this timestamp existed: compare the two folders.
		$uploads = self::uploadsStorage();
		$uploadsMtime = is_dir($uploads['dir']) ? filemtime($uploads['dir']) : false;

		return $uploadsMtime && max($legacyMtime, $latestMtime) > $uploadsMtime;
	}

	// Whether the old folder has CSS/JS that was written after the move and after the copy in uploads.
	// This runs on page loads while an old folder remains, so it only looks at those few files.
	public static function legacyHasNewerAssets(){

		$from = self::legacyStorage()['dir'];
		$to = self::uploadsStorage()['dir'];
		$movedAt = (int) get_option('microthemer_storage_moved_at');

		$files = array();
		foreach (array('draft', 'active') as $type){
			foreach (array('-styles.css', '-styles.scss', '-scripts.js') as $suffix){
				$files[] = $type . $suffix;
			}
			$subDir = 'mt/conditional/' . $type . '/';
			if (is_dir($from . $subDir) && is_readable($from . $subDir)){
				foreach (scandir($from . $subDir) ?: array() as $name){
					$files[] = $subDir . $name;
				}
			}
		}

		foreach ($files as $file){
			if (!self::isGeneratedAsset($file) || !is_file($from . $file)){
				continue;
			}
			$mtime = filemtime($from . $file);
			if ($movedAt && $mtime <= $movedAt){
				continue;
			}
			if (!is_file($to . $file) || $mtime > filemtime($to . $file)){
				return true;
			}
		}

		return false;
	}

	// a file in the storage folder that saving or publishing writes
	public static function isGeneratedAsset($rel){
		return (bool) preg_match(
			'#^(?:(?:draft|active)-(?:styles\.s?css|scripts\.js)|mt/conditional/(?:draft|active)/[^/]+\.css)$#', $rel
		);
	}

	public static function samePath($a, $b){
		$a = realpath($a);
		$b = realpath($b);
		return $a && $b && $a === $b;
	}

	public static function pathContains($parent, $child){
		$parent = realpath($parent);
		$child = realpath($child);
		return $parent && $child && strpos($child, rtrim($parent, '/\\') . DIRECTORY_SEPARATOR) === 0;
	}

	// a symlink, or a Windows junction (which is_link() doesn't see)
	public static function isLinkedDir($dir){
		$dir = rtrim($dir, '/\\');
		if (is_link($dir)){
			return true;
		}
		$real = realpath($dir);
		$parent = realpath(dirname($dir));
		return $real && $parent && strcasecmp($real, rtrim($parent, '/\\') . DIRECTORY_SEPARATOR . basename($dir)) !== 0;
	}

	// PHP never moves, and Pro keeps Microloader's code in the old folder: a folder with nothing
	// else in it has nothing to migrate.
	private static function hasMovableFiles($dir){
		try {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
			);
			foreach ($items as $item){
				if ($item->isFile() && strtolower($item->getExtension()) !== 'php'){
					return true;
				}
			}
		} catch (\UnexpectedValueException $e){
			return true; // can't tell
		}
		return false;
	}

	// Fingerprint only matters when the best-effort deletion left a legacy folder.
	// Hashing contents catches same-size overwrites even within one timestamp tick.
	public static function storageFingerprint($dir){
		try {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
			);
			$files = array();
			foreach ($items as $item){
				if (!$item->isFile() || strtolower($item->getExtension()) === 'php'){
					continue;
				}
				$hash = hash_file('sha256', $item->getPathname());
				if ($hash === false){
					return null;
				}
				$relative = substr(wp_normalize_path($item->getPathname()), strlen(wp_normalize_path($dir)));
				$files[$relative] = $hash;
			}
			ksort($files);
			return hash('sha256', serialize($files));
		} catch (\UnexpectedValueException $e){
			return null;
		}
	}

	private static function storageLatestMtime($dir){
		$latest = 0;
		try {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
			);
			foreach ($items as $item){
				$latest = max($latest, (int) $item->getMTime());
			}
		} catch (\UnexpectedValueException $e){
			return 0;
		}
		return $latest;
	}

	// The location versions before 7.6 used (wp-content/micro-themes, or blogs.dir / uploads/sites on
	// multisite). Only read to move existing files to the uploads folder, which is why it relies on
	// the content directory constant: no storage files are ever created here any more.
	public static function legacyStorage(){

		$dir = untrailingslashit(wp_normalize_path(WP_CONTENT_DIR));
		$url = untrailingslashit(set_url_scheme(content_url()));
		$sub = '/micro-themes/';
		$aliases = array();

		if (is_multisite()){
			$blogId = get_current_blog_id();
			$sites = file_exists($dir . '/blogs.dir/') ? '/blogs.dir' : '/uploads/sites';
			$sub = $sites . ($blogId === 1 ? '' : '/' . $blogId) . '/micro-themes/';

			// a subdirectory site serves the same files at /site/wp-content/ and at the network's /wp-content/
			if ($blogId !== 1){
				$aliases[] = untrailingslashit(set_url_scheme(network_site_url(basename($dir)))) . $sub;
			}
		}

		return array(
			'dir' => $dir . $sub,
			'url' => $url . $sub,
			'aliases' => $aliases,
			'legacy' => true,
		);
	}

	public static function getTemplateMap($themeSlug = '', &$cache = array()){

		$themeSlug = $themeSlug ?: get_stylesheet();

		// return cache if available
		if (isset($cache['templateMap'][$themeSlug])){
			return $cache['templateMap'][$themeSlug];
		}

		// else read the file
		$map = null;
		$dir = Helper::storage()['dir'];
		$templateMapFile = $dir . 'mt/cache/themes/'.$themeSlug.'/'.Helper::templateMapName().'.json';

		if (file_exists($templateMapFile)){

			$mapString = file_get_contents($templateMapFile);

			if ($mapString){
				$map = json_decode($mapString, true);
			}
		}

		// cache to save reading the file again
		$cache['templateMap'][$themeSlug] = $map;

		return $map;
	}

	// Whether this request carries live block editor content for a folder logic test: posted by the
	// block editor with a nonce (see AssetAuth, mt_live_content) and only honoured for administrators.
	public static function isLiveContentTest(){

		static $verified = null;

		if ($verified === null){
			$nonce = isset($_POST['live_nonce']) ? sanitize_text_field(wp_unslash($_POST['live_nonce'])) : '';
			$verified = isset($_POST['live_post_content'])
				&& current_user_can('manage_options')
				&& wp_verify_nonce($nonce, 'mt_live_content');
		}

		return $verified;
	}

	public static function getFSEPermalink($permalink){

		$themeSlug = get_stylesheet();

		// Get parameters in case we are in the FSE view
		extract(Helper::extractUrlParams($themeSlug));

		if ($postType === 'page' && $postId !== null){
			$permalink = get_permalink($postId);
		}

		return $permalink;
	}

	public static function extractUrlParams($themeSlug){
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only context
		// detection from the WordPress-core-generated site editor URL (site-editor.php?postType=...
		// &postId=...). WP core produces these links so they cannot carry a plugin nonce; nothing
		// is saved from these values and each one is sanitized.
		$postType = isset($_GET["postType"]) ? sanitize_key(wp_unslash($_GET["postType"])) : null;
		$postId = isset($_GET['postId'])
			? Helper::removeRedundantThemePrefix(
				Helper::maybeMakeNumber(sanitize_text_field(wp_unslash($_GET["postId"]))), $themeSlug
			)
			: null;
		$categoryType = isset($_GET["categoryType"]) ? sanitize_key(wp_unslash($_GET["categoryType"])) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$fseType = Helper::getFSEType($postType, $categoryType);

		return array(
			'postType' => $postType,
			'postId' => $postId,
			'categoryType' => $categoryType,
			'fseType' => $fseType,
		);

	}

	public static function templateMapName(){
		return Helper::isLiveContentTest()
			? 'template-map-live'
			: 'template-map';
	}

	public static function extractSyncedPatterns($content){

		$pattern = '/<!-- wp:(block|navigation) {"ref":(\d+)}/';

		preg_match_all($pattern, $content,$matches);

		return isset($matches[2])
			? $matches
			: array('', '');

	}

	// remove redundant theme slug if it matches the name of the file we are working with
	// this is a duplicate of a method in BlockTrait.php - todo create HelpersTrait
	public static function removeRedundantThemePrefix($slug, $themeSlug){

		$redundantPrefix = '/^'.$themeSlug . '\/\/?(.+)$/';

		if ($slug && preg_match($redundantPrefix, $slug, $matches)){
			$slug = $matches[1];
		}

		return $slug;
	}

	// Set numeric values to inter or floats
	public static function maybeMakeNumber($value){

		return is_numeric($value)
			? (strpos($value, '.') !== false
				? (float) $value
				: (int) $value
			)
			: $value;
	}

	public static function homePageData(){

		$id = -1;

		// Blog posts on home
		if (('posts' === get_option( 'show_on_front' ))){
			$title = esc_html__('Blog home', 'microthemer');
			$logic = '\Microthemer\is_post_or_page("front")';
		}

		// Static page on home
		else {
			$id = get_option('page_on_front');
			$post = get_post($id);
			$title = $post->post_title;
			$logic = '\Microthemer\is_post_or_page("'.$id.'")';
		}

		return array(
			'id' => $id,
			'title' => $title,
			'logic' => $logic
		);

	}

}
