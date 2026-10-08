<?php

if (!defined('ABSPATH')) {
    exit();
}


function ccb_uninstall_current_site(): void
{
    global $wpdb;

    $table = $wpdb->prefix . 'ccb_abandoned_carts';
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");

    delete_option('ccb_settings_options');
    delete_option('ccb_activated');
    delete_option('ccb_db_version');

    wp_clear_scheduled_hook('ccb_check_abandoned_carts');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids']) as $ccb_blog_id) {
        switch_to_blog((int) $ccb_blog_id);
        ccb_uninstall_current_site();
        restore_current_blog();
    }
} else {
    ccb_uninstall_current_site();
}
