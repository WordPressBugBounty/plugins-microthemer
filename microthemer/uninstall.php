<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Recursively delete a directory and it's contents
function mt_destroy_dir($dir) {
    if (!is_dir($dir) || is_link($dir)) {
        return \Microthemer\Helper::deleteFile($dir);
    }
    foreach (scandir($dir) as $file) {
        if ($file == '.' || $file == '..') continue;
        if (!mt_destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- uninstall recursive cleanup when a path is not writable.
            chmod($dir . DIRECTORY_SEPARATOR . $file, is_dir($dir . DIRECTORY_SEPARATOR . $file) ? 0755 : 0644);
            if (!mt_destroy_dir($dir . DIRECTORY_SEPARATOR . $file)) return false;
        };
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall recursive cleanup of empty directories under micro-themes.
    return rmdir($dir);
}

// storage folder locations (current and pre-7.6) - see Helper::storage
// (Microloader, if active, has loaded its copy of the class already)
if (!class_exists('Microthemer\Helper', false)){
    require_once __DIR__ . '/src/Helper.php';
}

// Run the cleanup code when the site asked for a clean uninstall.
// On multisite, uninstall.php runs once, so each site is checked on its own.
if (is_multisite()){
    $offset = 0;
    do {
        $site_ids = get_sites(array('fields' => 'ids', 'number' => 100, 'offset' => $offset));
        foreach ($site_ids as $site_id){
            switch_to_blog((int) $site_id);
            mt_clean_uninstall_current_site();
            restore_current_blog();
        }
        $offset += 100;
    } while (count($site_ids) === 100);
    delete_site_option('microthemer_code_review_dismissed');
    delete_site_transient('microthemer_sites_with_code');
} else {
    mt_clean_uninstall_current_site();
}

function mt_clean_uninstall_current_site(){
    $p_name = 'preferences_themer_loader';
    $options_name = 'microthemer_ui_settings';
    $p = get_option($p_name);
    if (empty($p['clean_uninstall'])){
        return;
    }
    delete_option($p_name);
    delete_option($options_name);
    delete_option('microthemer_autoload_preferences');
    delete_option('micro_revisions_version');
    delete_option('micro_html_mods_version');
    delete_option('microthemer_activation');
    global $wpdb;
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'micro_revisions'));
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'micro_content'));
    delete_option('microthemer_storage_version');
    delete_option('microthemer_storage_moved_at');
    delete_option('microthemer_storage_repair');
    delete_option('microthemer_legacy_storage_fingerprint');
    delete_transient('microthemer_legacy_storage_checked');
    delete_transient('microthemer_storage_repair_wait');
    delete_transient('microthemer_orphaned_pro_data');
    delete_option('microthemer_storage_move_error');
    delete_option('microthemer_storage_moving');
    delete_option('microthemer_storage_rewrite_pending');
    delete_option('microthemer_storage_rewrite_snapshot');
    delete_option('microthemer_published_css_snapshot');
    delete_option('microthemer_lite_css_cleaned');
    delete_option('microthemer_lite_css_retry');
    delete_option('microthemer_microloader_signature');
    foreach (array(\Microthemer\Helper::uploadsStorage(), \Microthemer\Helper::legacyStorage()) as $storage){
        if (is_dir($storage['dir'])){
            mt_destroy_dir(untrailingslashit($storage['dir']));
        }
    }
}
