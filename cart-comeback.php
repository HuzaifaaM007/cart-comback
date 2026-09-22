<?php

/**
 * Plugin Name: Cart Comeback
 * Plugin URI: https://cartcomeback.com
 * Description: Notifies customers about their abandoned carts.
 * Version: 1.0.0
 * Author: Huzaifa Murtaza
 * Author URI: https://huzaifamurtaza.com
 * Text Domain: cart-comeback
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit();
}

define('CCB_NAME', plugin_basename(__FILE__));
define('CCB_URL', plugin_dir_url(__FILE__));
define('CCB_PATH', plugin_dir_path(__FILE__));
define('CCB_VERSION', '1.0.0');

// 1. Fixed typo: vendor/autoload.php
if (file_exists(CCB_PATH . 'vendor/autoload.php')) {
    require_once CCB_PATH . 'vendor/autoload.php';
}

use CartComback\includes\CCB_Bootstrap;

// 2. Fixed action link settings
function ccb_add_action_links(array $links): array
{
    $settings_url = admin_url('admin.php?page=cart-comback');
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url($settings_url),
        esc_html__('Settings', 'cart-comeback')
    );

    array_unshift($links, $settings_link);

    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ccb_add_action_links');

function ccb_is_wc_activated(): bool
{
    return class_exists('WooCommerce');
}

function ccb_wc_missing_notice(): void
{
    echo '<div class="notice notice-error"><p>' .
        esc_html__('Cart Comeback requires WooCommerce to be active.', 'cart-comeback') .
        '</p></div>';
}

function ccb_show_admin_notice_and_deactivate(callable $notice_callback, bool $autodeactivate = true): void
{
    if (!is_callable($notice_callback)) {
        return;
    }

    add_action('admin_notices', function () use ($notice_callback, $autodeactivate) {
        $notice_callback();
        if ($autodeactivate) {
            deactivate_plugins(plugin_basename(__FILE__));
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    });
}

function ccb_run_plugin(): void
{
    error_log('ccb_run_plugin 1');


    if (!ccb_is_wc_activated()) {
        error_log('ccd_wc_activated');
        ccb_show_admin_notice_and_deactivate('ccb_wc_missing_notice');
        return;
    }

    error_log('ccb_run_plugin 2');

    if (class_exists(CCB_Bootstrap::class)) {
        error_log('bootstrap class exists');
        $ccb_bootstrap = new CCB_Bootstrap();
        $ccb_bootstrap->run();
    }

}
add_action('plugins_loaded', 'ccb_run_plugin');
