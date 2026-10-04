<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\admin\CCB_Admin;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Assets
{
    private CCB_Admin $ccb_admin;

    public function __construct()
    {
        $this->ccb_admin = new CCB_Admin();
    }
    
    /**
     * action:admin_enqueue_scripts
     */
    public function ccb_enqueue_admin_assets(string $hook): void
    {
        $ccb_admin = new CCB_Admin();

        if ($hook !== 'toplevel_page_cart-comback') {
            return;
        }

        $asset_file = CCB_PATH . 'build/index.asset.php';
        if (!file_exists($asset_file)) {
            return;
        }

        $asset = include $asset_file;

        wp_enqueue_script(
            'ccb-app',
            CCB_URL . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'ccb-style',
            CCB_URL . 'build/style-index.css',
            [],
            $asset['version'],
        );

        wp_localize_script('ccb-app', 'CCB', [
            'schema'   =>  $this->ccb_admin->ccb_schema(),
            'settings' =>  $this->ccb_admin->ccb_get_settings(),
        ]);
    }
}
