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
 * The rules that keep it safe:
 * - the old folder is only served again if a rollback wrote newer assets there (Helper::storage)
 * - moving again never replaces a newer file with an older one
 * - only what was copied is deleted: not PHP of the site's own, not what's behind a link
 * - sites moved by a version whose move missed some references are repaired once
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

	// bump when already moved sites need their stored references looking at again
	var $storage_repair_version = 1;

	// the PHP that versions before 7.5.5.2 copied into the folder. Any other PHP there is the site's own.
	var $storage_loader_files = array(
		'autoload.php', 'AssetLoad.php', 'Logic.php', 'Helper.php', 'FrontAndBackTrait.php', 'TimerTrait.php',
		'Content/AssetLoadContent.php', 'Content/HTML.php', 'Content/ContentHelper.php', 'animation-code.inc.php',
	);

	function maybeMoveStorage(){

		if (is_network_admin()){
			return;
		}

		// A previous attempt died while rewriting the database. The lock it held has expired,
		// so put the stored URLs back before anything reads them. The old folder is still in use.
		if (get_option('microthemer_storage_rewrite_pending') && !$this->storageMoveLockIsFresh()){
			$this->restoreRewriteSnapshot();

			// it was repairing, or moving again after a rollback: the files are in uploads already
			if (Helper::storageMoved()){
				set_transient('microthemer_storage_repair_wait', 1, HOUR_IN_SECONDS);
				return;
			}

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
		$alreadyMoved = Helper::storageMoved();
		$rewriteLegacy = $alreadyMoved
			&& is_dir($legacy['dir'])
			&& !Helper::samePath($legacy['dir'], Helper::uploadsStorage()['dir']) // linked to each other
			&& Helper::legacyRewrittenSinceMove($forceLegacyCheck);

		if ($alreadyMoved && !$rewriteLegacy){
			$this->maybeRepairStorageReferences($legacy);
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

		$to = Helper::uploadsStorage();
		$started = time();

		$error = $this->moveStorage($legacy, $to, $alreadyMoved);

		if ($error && $this->storageMoveLockLost){
			return;
		}

		if ($error){
			$this->releaseStorageMoveLock();
			update_option('microthemer_storage_move_error', $error, false);
			$this->hookStorageMoveNotice();
			return;
		}

		$this->storageMoved();

		// A save during the move wrote to the old folder. Fetch those files before deleting it.
		// If that fails, the old folder is kept.
		if (!$this->copyStorageFiles($legacy['dir'], $to['dir'], $alreadyMoved, $started) &&
		    !$this->rewriteStorageFiles($to['dir'], new StorageReferences($legacy, $to), get_option($this->optionsName), $alreadyMoved)){

			// best effort: the files are already in use from their new location
			$this->deleteStorageFolder($legacy['dir'], $to['dir']);
		}

		$this->releaseStorageMoveLock();

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
		update_option('microthemer_storage_repair', $this->storage_repair_version);
		Helper::$storageCache = array();
		$this->setStoragePaths();
		$this->reloadPreferences();
	}

	// preferences are read before the move, and saving a stale copy would bring the old URLs back
	function reloadPreferences(){
		if (!empty($this->preferencesName)){
			$this->preferences = get_option($this->preferencesName);
		}
	}

	// Sites moved by 7.5.5.2 - 7.5.6.0 can have references that move missed
	function maybeRepairStorageReferences($legacy){

		if ((int) get_option('microthemer_storage_repair') >= $this->storage_repair_version ||
		    get_transient('microthemer_storage_repair_wait') ||
		    !$this->acquireStorageMoveLock()){
			return;
		}

		$to = Helper::uploadsStorage();
		$references = new StorageReferences($legacy, $to);
		$error = is_dir($to['dir'])
			? $this->rewriteStorageFiles($to['dir'], $references, get_option($this->optionsName), true)
			: '';
		$error = $error ? $error : $this->rewriteStoredReferences($references, true);

		if ($error){
			set_transient('microthemer_storage_repair_wait', 1, HOUR_IN_SECONDS);
		} else {

			update_option('microthemer_storage_repair', $this->storage_repair_version);

			// repaired files keep their URLs, so browsers and CDNs need a new cache parameter
			foreach (array($this->preferencesName, 'microthemer_autoload_preferences') as $name){
				$preferences = get_option($name);
				if (is_array($preferences) && isset($preferences['num_saves'])){
					$preferences['num_saves']++;
					update_option($name, $preferences);
				}
			}
			$this->reloadPreferences();
		}

		$this->releaseStorageMoveLock();
	}

	// Settings from an editor tab that was open during the move, or from a pack exported before it
	function updateOldStorageReferences($data){

		$to = Helper::uploadsStorage();
		if (Helper::storage()['dir'] !== $to['dir']){
			return $data; // the old folder is still in use
		}

		$references = new StorageReferences(Helper::legacyStorage(), $to);

		return $references->replaceInData($data);
	}

	// true when this request holds the lock, or nobody does
	function storageMoveLockIsFresh(){
		$started = (int) get_option('microthemer_storage_moving');
		return $started > time() - 5 * MINUTE_IN_SECONDS;
	}

	function acquireStorageMoveLock(){

		$now = time();
		$this->storageMoveLockLost = false;
		if (add_option('microthemer_storage_moving', $now, '', false)){
			$this->storageMoveLockHeld = $now;
			return true;
		}

		$started = (int) get_option('microthemer_storage_moving');
		if ($started > $now - 5 * MINUTE_IN_SECONDS){
			return false;
		}

		// the previous attempt died and left the lock; only one request may take it over
		if ($this->swapStorageMoveLock($started, $now)){
			$this->storageMoveLockHeld = $now;
			return true;
		}

		return false;
	}

	// the lock's value while this request holds it
	var $storageMoveLockHeld = 0;

	// another request took the lock over, so the move's state is that request's to undo or finish
	var $storageMoveLockLost = false;

	// compare-and-swap, so a lock another request has taken is never overwritten
	function swapStorageMoveLock($from, $to){
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap on the plugin's own lock option
		$took = $wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
			(string) $to,
			'microthemer_storage_moving',
			(string) $from
		));
		wp_cache_delete('microthemer_storage_moving', 'options');
		return (bool) $took;
	}

	// A big folder or table can take longer than the lock's five minutes, so the loops call this
	// to keep it fresh (another request would otherwise take over mid-move). Returns an error if
	// another request already has, so this one stops instead of mixing its writes with that one's.
	function keepStorageMoveLock(){

		$now = time();
		if (!$this->storageMoveLockHeld || $now - $this->storageMoveLockHeld < 30){
			return '';
		}

		if ($this->swapStorageMoveLock($this->storageMoveLockHeld, $now)){
			$this->storageMoveLockHeld = $now;
			return '';
		}

		$this->storageMoveLockHeld = 0;
		$this->storageMoveLockLost = true;
		return __('The move took too long and another request took it over.', 'microthemer');
	}

	// a lock another request took over is that request's to release
	function releaseStorageMoveLock(){
		if ($this->storageMoveLockHeld){
			delete_option('microthemer_storage_moving');
		}
		$this->storageMoveLockHeld = 0;
	}

	// copy, verify, then point every stored reference at the new location (returns an error or '')
	function moveStorage($from, $to, $alreadyMoved = false){

		if (!wp_mkdir_p($to['dir'])){
			/* translators: %s: folder path */
			return sprintf(__('The folder %s could not be created.', 'microthemer'), $this->display_path($to['dir']));
		}

		// linked to each other: nothing to copy, but the URLs still change
		if (!Helper::samePath($from['dir'], $to['dir'])){

			if (Helper::pathContains($from['dir'], $to['dir'])){
				/* translators: %s: folder path */
				return sprintf(__('The folder %s is inside the old folder.', 'microthemer'), $this->display_path($to['dir']));
			}

			$error = $this->copyStorageFiles($from['dir'], $to['dir'], $alreadyMoved);
			if ($error){
				return $error;
			}
		}

		$references = new StorageReferences($from, $to);

		$error = $this->rewriteStorageFiles($to['dir'], $references, get_option($this->optionsName), $alreadyMoved);
		if ($error){
			return $error;
		}

		return $this->rewriteStoredReferences($references, $alreadyMoved);
	}

	/**
	 * Copy everything except PHP, which never goes in uploads, checking each file arrived intact.
	 *
	 * @param bool $alreadyMoved the new folder is in use, so only take what is newer in the old one
	 * @param int  $since        only take files changed since this time
	 */
	function copyStorageFiles($from, $to, $alreadyMoved = false, $since = 0){

		if (Helper::samePath($from, $to)){
			return '';
		}

		$movedAt = $alreadyMoved ? (int) get_option('microthemer_storage_moved_at') : 0;

		try {
			$error = $this->copyStorageFolder($from, $to, '', array(), $alreadyMoved, $movedAt, $since);
		} catch (\UnexpectedValueException $e){
			/* translators: %s: folder path */
			return sprintf(__('A folder inside %s could not be read.', 'microthemer'), $this->display_path($from));
		}

		if ($error){
			return $error;
		}

		// the loader code copies lived in Content/ (now empty)
		if (is_dir($to . 'Content') && !(new \FilesystemIterator($to . 'Content'))->valid()){
			rmdir($to . 'Content');
		}

		return '';
	}

	// recursion rather than an iterator, as linked folders have to be followed, without looping
	function copyStorageFolder($from, $to, $rel, $parents, $alreadyMoved, $movedAt, $since){

		$source = rtrim($from, '/\\') . '/' . $rel;
		$items = new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS);

		$real = realpath($source);
		if ($real && (in_array($real, $parents, true) || Helper::pathContains($real, $to) || Helper::samePath($real, $to))){
			return ''; // a link back to a parent, or to where the files are going
		}
		$parents[] = $real;

		foreach ($items as $item){

			$error = $this->keepStorageMoveLock();
			if ($error){
				return $error;
			}

			$itemRel = $rel . $item->getFilename();
			$target = $to . $itemRel;

			if ($item->isDir()){

				if (!is_dir($target) && !wp_mkdir_p($target)){
					/* translators: %s: folder path */
					return sprintf(__('The folder %s could not be created.', 'microthemer'), $this->display_path($target));
				}

				$error = $this->copyStorageFolder($from, $to, $itemRel . '/', $parents, $alreadyMoved, $movedAt, $since);
				if ($error){
					return $error;
				}
				continue;
			}

			if (strtolower($item->getExtension()) === 'php' || !$item->isFile()){
				continue; // PHP, or a link to nothing
			}

			$mtime = $item->getMTime();
			if ($since && $mtime < $since){
				continue;
			}

			if ($alreadyMoved){
				if (is_file($target) && $mtime < filemtime($target)){
					continue; // a stale leftover
				}
				if (!is_file($target) && $movedAt && $mtime <= $movedAt && Helper::isGeneratedAsset($itemRel)){
					continue; // removed on purpose since the move
				}
			}

			if (!copy($item->getPathname(), $target) || filesize($target) !== $item->getSize()){
				/* translators: %s: file path */
				return sprintf(__('The file %s could not be copied.', 'microthemer'), $this->display_path($item->getPathname()));
			}
		}

		return '';
	}

	function rewriteStorageFiles($dir, $references, $settings = array(), $alreadyMoved = false){

		$root = rtrim(str_replace('\\', '/', $dir), '/') . '/';

		return $this->eachStorageItem($dir, \RecursiveIteratorIterator::LEAVES_ONLY, function ($file) use ($root, $references, $settings, $alreadyMoved) {

			$extension = strtolower($file->getExtension());
			if (!$file->isFile() || !in_array($extension, $this->storage_text_extensions, true)){
				return '';
			}

			$path = $file->getPathname();
			$contents = file_get_contents($path);
			if ($contents === false){
				/* translators: %s: file path */
				return sprintf(__('The file %s could not be read.', 'microthemer'), $this->display_path($path));
			}

			$updated = $contents;
			$rel = substr(str_replace('\\', '/', $path), strlen($root));
			$subDir = $this->relativeUrlBase($rel, $extension, $settings);
			if ($subDir !== null){
				$updated = $references->fixRelative($updated, $subDir, $alreadyMoved);
			}
			$updated = $references->replace($updated);

			if ($updated !== $contents && file_put_contents($path, $updated) === false){
				/* translators: %s: file path */
				return sprintf(__('The file %s could not be updated.', 'microthemer'), $this->display_path($path));
			}

			return '';
		});
	}

	// the folder a file's relative url()s are resolved from, or null if it isn't the storage folder
	function relativeUrlBase($rel, $extension, $settings){

		// a pack's settings end up in the global stylesheet
		if ($extension === 'json'){
			return basename($rel) === 'config.json' ? '' : null;
		}

		if ($extension !== 'css' && $extension !== 'scss'){
			return null; // in JS they're relative to the page
		}

		if (preg_match('#^mt/conditional/(?:draft|active)/(.+)\.css$#', $rel, $match)){
			$logic = isset($settings[$match[1]]['this']['logic']) ? $settings[$match[1]]['this']['logic'] : null;

			return StorageReferences::folderSubDir($logic) === null ? null : dirname($rel) . '/';
		}

		return strpos($rel, '/') === false ? '' : dirname($rel) . '/';
	}

	// settings, preferences, revisions and Amender's content can all hold URLs of files in the folder
	// (e.g. design pack images, generated stylesheets)
	function rewriteStoredReferences($references, $alreadyMoved = false){

		$options = array($this->optionsName, $this->preferencesName, 'microthemer_autoload_preferences', 'microthemer_published_css_snapshot');
		$snapshot = $updated = $written = array();

		foreach ($options as $option){

			$value = $snapshot[$option] = get_option($option, null);
			if ($value === null){
				continue;
			}

			if ($option === $this->optionsName){
				$value = $references->fixRelativeInSettings($value, $alreadyMoved);
			} elseif ($option === 'microthemer_published_css_snapshot' && isset($value['settings'])){
				$value['settings'] = $references->fixRelativeInSettings($value['settings'], $alreadyMoved);
			}

			$updated[$option] = $references->replaceInData($value);
			$written[$option] = md5(serialize($updated[$option]));
		}

		// So a timeout mid-loop can be undone on the next request. The old folder stays in use
		// until storageMoved(), which only runs after this returns no error.
		// The hashes show whether anyone has saved since (see restoreRewriteSnapshot).
		update_option('microthemer_storage_rewrite_snapshot', $snapshot, false);
		update_option('microthemer_storage_rewrite_pending', $written ? $written : 1, false);

		foreach ($updated as $option => $value){
			if (serialize($value) !== serialize($snapshot[$option])){
				update_option($option, $value);
			}
		}

		$error = $this->rewriteRevisions($references, $alreadyMoved);
		$error = $error ? $error : $this->rewriteContentTable($references, $alreadyMoved);

		if ($error){
			// Live settings go back to the old URLs. Rows already written point at the new folder,
			// which has the files. A retry rewrites any row that still has the old URL.
			if (!$this->storageMoveLockLost){
				$this->restoreRewriteSnapshot();
			}
			return $error;
		}

		$this->clearRewriteSnapshot();

		return '';
	}

	function storageTableExists($table){
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
		return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
	}

	function rewriteRevisions($references, $alreadyMoved){

		global $wpdb;

		$table = $wpdb->prefix . 'micro_revisions';
		if (!$this->storageTableExists($table)){
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
		$ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM %i', $table));

		foreach ($ids as $id){

			$error = $this->keepStorageMoveLock();
			if ($error){
				return $error;
			}

			// All columns, as tables from older versions don't have every one until they're upgraded.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
			$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $id), ARRAY_A);
			if (!$row){
				continue;
			}

			$update = array();
			foreach (array('settings', 'preferences', 'snippets') as $column){
				if (empty($row[$column])){
					continue;
				}
				$data = Helper::unserializeData($row[$column]);
				if ($data === false || $data === null){
					continue;
				}
				$updated = $column === 'settings' ? $references->fixRelativeInSettings($data, $alreadyMoved) : $data;
				$updated = $references->replaceInData($updated);
				if (serialize($updated) !== serialize($data)){
					$update[$column] = maybe_serialize($updated);
				}
			}

			if ($update){
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
				$saved = $wpdb->update($table, $update, array('id' => $id));
				if ($saved === false){
					/* translators: %s: database table name */
					return sprintf(__('A row in %s could not be updated.', 'microthemer'), $table);
				}
			}
		}

		return '';
	}

	// Amender's snippets and HTML changes, draft and published
	function rewriteContentTable($references, $alreadyMoved){

		global $wpdb;

		$table = $wpdb->prefix . 'micro_content';
		if (!$this->storageTableExists($table)){
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
		$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i', $table), ARRAY_A);

		foreach ((array) $rows as $row){

			$error = $this->keepStorageMoveLock();
			if ($error){
				return $error;
			}

			$update = array();
			foreach (array('content', 'meta') as $column){
				if (empty($row[$column])){
					continue;
				}
				$value = $row[$column];
				if ($column === 'content' && isset($row['aspect']) && $row['aspect'] === 'css'){
					$value = $references->fixRelative($value, '', $alreadyMoved);
				}
				$value = $references->replace($value);
				if ($value !== $row[$column]){
					$update[$column] = $value;
				}
			}

			if (!$update){
				continue;
			}

			$where = array('slug' => $row['slug'], 'type' => $row['type'], 'published' => $row['published']);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration of the plugin's own table
			if ($wpdb->update($table, $update, $where) === false){
				/* translators: %s: database table name */
				return sprintf(__('A row in %s could not be updated.', 'microthemer'), $table);
			}
		}

		return '';
	}

	function restoreRewriteSnapshot(){

		$snapshot = get_option('microthemer_storage_rewrite_snapshot', null);
		$written = get_option('microthemer_storage_rewrite_pending');
		if (is_array($snapshot)){
			foreach ($snapshot as $option => $value){

				// saved since the move was interrupted: that is newer than the snapshot
				if (is_array($written) && isset($written[$option])){
					$current = md5(serialize(get_option($option, null)));
					if ($current !== $written[$option] && $current !== md5(serialize($value))){
						continue;
					}
				}

				if ($value === null){
					delete_option($option);
				} else {
					update_option($option, $value);
				}
			}
		}

		$this->clearRewriteSnapshot();
		$this->reloadPreferences();
	}

	function clearRewriteSnapshot(){
		delete_option('microthemer_storage_rewrite_pending');
		delete_option('microthemer_storage_rewrite_snapshot');
	}

	function deleteStorageFolder($dir, $keep = ''){

		// never empty the folder that's in use, or what a link points to
		if (!is_dir($dir) || Helper::isLinkedDir($dir) ||
		    ($keep && (Helper::samePath($dir, $keep) || Helper::pathContains($dir, $keep)))){
			return;
		}

		$root = rtrim(str_replace('\\', '/', $dir), '/') . '/';

		$this->eachStorageItem($dir, \RecursiveIteratorIterator::CHILD_FIRST, function ($item) use ($root) {

			$path = $item->getPathname();
			$rel = substr(str_replace('\\', '/', $path), strlen($root));

			if ($item->isFile() && strtolower($item->getExtension()) === 'php'
			    && !in_array($rel, $this->storage_loader_files, true)){
				return ''; // the site's own code, which wasn't copied
			}

			if ($item->isLink()){
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best effort clean-up of the old folder (removes the link only)
				@unlink($path) || @rmdir($path);
				return '';
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best effort clean-up of the old folder
			$item->isDir() ? @rmdir($path) : @unlink($path);
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
				$error = $this->keepStorageMoveLock();
				$error = $error ? $error : $callback($item);
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
