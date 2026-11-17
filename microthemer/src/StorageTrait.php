<?php

namespace Microthemer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Moves Microthemer's files from the location versions before 7.6 used (wp-content/micro-themes,
 * or the multisite equivalents) to a "microthemer" folder in the uploads directory.
 *
 * Runs once per site, the first time an administrator loads the admin area after updating. The old
 * folder stays in use (Helper::storage) until the move has fully succeeded, so a failure never
 * breaks the site: an admin notice explains it and offers to try again.
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
 * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
 */
trait StorageTrait {

	// text files that can hold the folder's own URL or path (generated CSS/JS, design pack data)
	var $storage_text_extensions = array('css', 'scss', 'js', 'json', 'txt');

	function maybeMoveStorage(){

		if (is_network_admin()){
			return;
		}

		// A previous attempt died while rewriting the database. The lock it held has expired,
		// so put the stored URLs back before anything reads them. The old folder is still in use.
		if (get_option('microthemer_storage_rewrite_pending') && !$this->storageMoveLockIsFresh()){
			$this->restoreRewriteSnapshot();
			update_option(
				'microthemer_storage_move_error',
				__('The move was interrupted and has been undone. Your site is still using the old folder.', 'microthemer'),
				false
			);
			$this->hookStorageMoveNotice();
			return;
		}

		$legacy = Helper::legacyStorage();
		// version 2 normally means "done", but a rollback to a pre-7.6 build writes the old
		// folder again. Those files are the ones the site is using (see Helper::storage).
		// Force a fresh comparison on plugin upgrades and explicit retries. Ordinary
		// admin requests share the short cache with frontend requests.
		$forceLegacyCheck = empty($this->preferences['version'])
			|| $this->preferences['version'] != $this->version
			|| isset($_GET['mt_retry_storage_move']);
		$rewriteLegacy = Helper::storageMoved() && Helper::legacyRewrittenSinceMove($forceLegacyCheck);

		if (Helper::storageMoved() && !$rewriteLegacy){
			return;
		}

		// nothing to move: a new install, or the old folder is already gone
		if (!is_dir($legacy['dir'])){
			$this->storageMoved();
			delete_option('microthemer_legacy_storage_fingerprint');
			return;
		}

		// after a failure, only try again when asked (it would otherwise retry on every admin page)
		$retry = isset($_GET['mt_retry_storage_move']) && check_admin_referer('mt_retry_storage_move');
		if (get_option('microthemer_storage_move_error') && !$retry){
			$this->hookStorageMoveNotice();
			return;
		}

		// add_option is atomic, so two admin requests (the editor and a heartbeat) cannot both move
		if (!$this->acquireStorageMoveLock()){
			return;
		}

		$error = $this->moveStorage($legacy, Helper::uploadsStorage());

		$this->releaseStorageMoveLock();

		if ($error){
			update_option('microthemer_storage_move_error', $error, false);
			$this->hookStorageMoveNotice();
			return;
		}

		$this->storageMoved();

		// best effort: the files are already in use from their new location
		$this->deleteStorageFolder($legacy['dir']);
		if (is_dir($legacy['dir'])){
			$fingerprint = Helper::storageFingerprint($legacy['dir']);
			// An unreadable residual folder must not silently rely on timestamps.
			update_option('microthemer_legacy_storage_fingerprint', $fingerprint === null ? 'unreadable' : $fingerprint, false);
		} else {
			delete_option('microthemer_legacy_storage_fingerprint');
		}
	}

	function storageMoved(){
		delete_option('microthemer_storage_move_error');
		update_option('microthemer_storage_version', 2);
		update_option('microthemer_storage_moved_at', time());
		Helper::$storageCache = array();
		$this->setStoragePaths();
	}

	// true when this request holds the lock, or nobody does
	function storageMoveLockIsFresh(){
		$started = (int) get_option('microthemer_storage_moving');
		return $started > time() - 5 * MINUTE_IN_SECONDS;
	}

	function acquireStorageMoveLock(){

		$now = time();
		if (add_option('microthemer_storage_moving', $now, '', false)){
			return true;
		}

		$started = (int) get_option('microthemer_storage_moving');
		if ($started > $now - 5 * MINUTE_IN_SECONDS){
			return false;
		}

		// the previous attempt died and left the lock; only one request may take it over
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap on the plugin's own lock option
		$took = $wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
			(string) $now,
			'microthemer_storage_moving',
			(string) $started
		));

		return (bool) $took;
	}

	function releaseStorageMoveLock(){
		delete_option('microthemer_storage_moving');
	}

	// copy, verify, then point every stored reference at the new location (returns an error or '')
	function moveStorage($from, $to){

		if (!wp_mkdir_p($to['dir'])){
			/* translators: %s: folder path */
			return sprintf(__('The folder %s could not be created.', 'microthemer'), $this->display_path($to['dir']));
		}

		$error = $this->copyStorageFiles($from['dir'], $to['dir']);
		if ($error){
			return $error;
		}

		$replacements = $this->storageReplacements($from, $to);

		$error = $this->rewriteStorageFiles($to['dir'], $replacements);
		if ($error){
			return $error;
		}

		$error = $this->rewriteStoredReferences($replacements);
		if ($error){
			return $error;
		}

		return '';
	}

	// copy everything except PHP (earlier versions copied loader code here, which no longer runs
	// from this folder), checking each file arrived intact
	function copyStorageFiles($from, $to){

		$error = $this->eachStorageItem($from, \RecursiveIteratorIterator::SELF_FIRST, function ($item) use ($from, $to) {

			$rel = substr(wp_normalize_path($item->getPathname()), strlen(wp_normalize_path($from)));
			$target = $to . $rel;

			if ($item->isDir()){
				if (!is_dir($target) && !wp_mkdir_p($target)){
					/* translators: %s: folder path */
					return sprintf(__('The folder %s could not be created.', 'microthemer'), $this->display_path($target));
				}
				return '';
			}

			if (strtolower($item->getExtension()) === 'php'){
				return '';
			}

			if (!copy($item->getPathname(), $target) || filesize($target) !== $item->getSize()){
				/* translators: %s: file path */
				return sprintf(__('The file %s could not be copied.', 'microthemer'), $this->display_path($item->getPathname()));
			}

			return '';
		});

		if ($error){
			return $error;
		}

		// the loader code copies lived in Content/ (now empty)
		if (is_dir($to . 'Content') && !(new \FilesystemIterator($to . 'Content'))->valid()){
			rmdir($to . 'Content');
		}

		return '';
	}

	// old URL/path => new, most specific first
	function storageReplacements($from, $to){

		$replacements = array();

		// full URLs, whichever scheme they were saved with
		foreach (array('http://', 'https://') as $scheme){
			$replacements[preg_replace('#^https?://#i', $scheme, $from['url'])] = $to['url'];
		}

		// root-relative URLs, when both locations are on the same host
		$fromParts = wp_parse_url($from['url']);
		$toParts = wp_parse_url($to['url']);
		if (!empty($fromParts['host']) && !empty($toParts['host']) &&
		    strtolower($fromParts['host']) === strtolower($toParts['host'])){
			$replacements[$fromParts['path']] = $toParts['path'];
		}

		// file paths (stored in some preferences), with either kind of slash
		$replacements[$from['dir']] = $to['dir'];
		$replacements[str_replace('/', '\\', $from['dir'])] = str_replace('/', '\\', $to['dir']);

		// design-pack config.json is written with wp_json_encode, which escapes slashes (\/).
		// Built separately: escaping an already-escaped URL still finds "/" and would loop.
		$escaped = array();
		foreach ($replacements as $old => $new){
			$oldEscaped = str_replace('/', '\\/', $old);
			if ($oldEscaped !== (string) $old){
				$escaped[$oldEscaped] = str_replace('/', '\\/', $new);
			}
		}

		return $replacements + $escaped;
	}

	function rewriteStorageFiles($dir, $replacements){

		return $this->eachStorageItem($dir, \RecursiveIteratorIterator::LEAVES_ONLY, function ($file) use ($replacements) {

			if (!in_array(strtolower($file->getExtension()), $this->storage_text_extensions, true)){
				return '';
			}

			$path = $file->getPathname();
			$contents = file_get_contents($path);
			if ($contents === false){
				/* translators: %s: file path */
				return sprintf(__('The file %s could not be read.', 'microthemer'), $this->display_path($path));
			}

			$updated = str_replace(array_keys($replacements), array_values($replacements), $contents);
			if ($updated !== $contents && file_put_contents($path, $updated) === false){
				/* translators: %s: file path */
				return sprintf(__('The file %s could not be updated.', 'microthemer'), $this->display_path($path));
			}

			return '';
		});
	}

	// settings, preferences and revisions can all hold URLs of files in the folder (e.g. design
	// pack images, generated stylesheets)
	function rewriteStoredReferences($replacements){

		global $wpdb;

		$options = array($this->optionsName, $this->preferencesName, 'microthemer_autoload_preferences', 'microthemer_published_css_snapshot');
		$snapshot = array();
		foreach ($options as $option){
			$snapshot[$option] = get_option($option, null);
		}
		// So a timeout mid-loop can be undone on the next request. The old folder stays in use
		// until storageMoved(), which only runs after this returns no error.
		update_option('microthemer_storage_rewrite_snapshot', $snapshot, false);
		update_option('microthemer_storage_rewrite_pending', 1, false);

		foreach ($options as $option){
			if ($snapshot[$option] !== null){
				update_option($option, $this->replaceInData($snapshot[$option], $replacements));
			}
		}

		$table = $wpdb->prefix . 'micro_revisions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table){
			$this->clearRewriteSnapshot();
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
		$ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM %i', $table));

		foreach ($ids as $id){

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
			$row = $wpdb->get_row($wpdb->prepare('SELECT settings, preferences FROM %i WHERE id = %d', $table, $id), ARRAY_A);
			if (!$row){
				continue;
			}

			$update = array();
			foreach ($row as $column => $serialized){
				if (empty($serialized)){
					continue;
				}
				$data = maybe_unserialize($serialized);
				$updated = $this->replaceInData($data, $replacements);
				if ($updated !== $data){
					$update[$column] = maybe_serialize($updated);
				}
			}

			if ($update){
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
				$saved = $wpdb->update($table, $update, array('id' => $id));
				if ($saved === false){
					// Live settings go back to the old URLs. Revision rows already written point at
					// the new folder; a retry rewrites any row that still has the old URL.
					$this->restoreRewriteSnapshot();
					/* translators: %s: database table name */
					return sprintf(__('A row in %s could not be updated.', 'microthemer'), $table);
				}
			}
		}

		$this->clearRewriteSnapshot();

		return '';
	}

	function restoreRewriteSnapshot(){

		$snapshot = get_option('microthemer_storage_rewrite_snapshot', null);
		if (is_array($snapshot)){
			foreach ($snapshot as $option => $value){
				if ($value === null){
					delete_option($option);
				} else {
					update_option($option, $value);
				}
			}
		}

		$this->clearRewriteSnapshot();
	}

	function clearRewriteSnapshot(){
		delete_option('microthemer_storage_rewrite_pending');
		delete_option('microthemer_storage_rewrite_snapshot');
	}

	function replaceInData($data, $replacements){

		if (is_string($data)){
			return str_replace(array_keys($replacements), array_values($replacements), $data);
		}

		if (is_array($data)){
			foreach ($data as $key => $value){
				$data[$key] = $this->replaceInData($value, $replacements);
			}
		}

		elseif (is_object($data)){
			foreach (get_object_vars($data) as $key => $value){
				$data->$key = $this->replaceInData($value, $replacements);
			}
		}

		return $data;
	}

	function deleteStorageFolder($dir){

		if (!is_dir($dir)){
			return;
		}

		$this->eachStorageItem($dir, \RecursiveIteratorIterator::CHILD_FIRST, function ($item) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best effort clean-up of the old folder
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
			return '';
		});

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best effort clean-up of the old folder
		@rmdir($dir);
	}

	/**
	 * Walk a storage folder. An unreadable directory throws from the iterator; that becomes
	 * an error string instead of a fatal, so the old folder stays in use.
	 *
	 * @param callable $callback function (\SplFileInfo $item): string error, or '' to continue
	 */
	function eachStorageItem($dir, $mode, $callback){

		try {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
				$mode
			);

			foreach ($items as $item){
				$error = $callback($item);
				if ($error){
					return $error;
				}
			}
		} catch (\UnexpectedValueException $e){
			/* translators: %s: folder path */
			return sprintf(__('A folder inside %s could not be read.', 'microthemer'), $this->display_path($dir));
		}

		return '';
	}

	function hookStorageMoveNotice(){
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only routes to the notice
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if ($page === $this->microthemeruipage){
			$this->log(esc_html__('Your Microthemer files could not be moved', 'microthemer'), $this->storageMoveNoticeBody(), 'warning');
		} elseif ($pagenow === 'plugins.php'){
			add_action('admin_notices', array($this, 'renderStorageMoveNotice'));
		}
	}

	function renderStorageMoveNotice(){
		echo '<div class="notice notice-warning"><p><strong>' .
			esc_html__('Your Microthemer files could not be moved', 'microthemer') . '</strong></p>' .
			wp_kses($this->storageMoveNoticeBody(), array('p' => array(), 'a' => array('href' => true))) .
			'</div>';
	}

	function storageMoveNoticeBody(){

		$retry_url = wp_nonce_url(
			add_query_arg('mt_retry_storage_move', '1', admin_url('plugins.php')),
			'mt_retry_storage_move'
		);

		return '<p>' . sprintf(
				/* translators: 1: old folder path, 2: new folder path */
				esc_html__('Microthemer keeps its files in %2$s now, but could not move them from %1$s, so it is still using the old folder. Your site is not affected.', 'microthemer'),
				esc_html($this->display_path(Helper::legacyStorage()['dir'])),
				esc_html($this->display_path(Helper::uploadsStorage()['dir']))
			) . '</p>' .
			'<p>' . esc_html((string) get_option('microthemer_storage_move_error')) . '</p>' .
			'<p><a href="' . esc_url($retry_url) . '">' . esc_html__('Try again', 'microthemer') . '</a></p>';
	}
}
