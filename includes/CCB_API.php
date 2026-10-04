<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\admin\CCB_Admin;

if (!defined('ABSPATH')) {
    exit();
}


class CCB_API
{

    private CCB_Admin $ccb_admin;

    public function __construct()
    {
        $this->ccb_admin = new CCB_Admin();
    }

    /**
     * action:rest_api_init
     */
    public function ccb_register_admin_routes(): void
    {

        register_rest_route('cart-comback/v1', '/settings', [
            [
                'methods'             =>  'GET',
                'callback'            =>  fn() => $this->ccb_admin->ccb_get_settings(),
                'permission_callback' =>  fn() => current_user_can('manage_options'),
            ],
            [
                'methods'             =>  'POST',
                'callback'            =>   [$this->ccb_admin,'ccb_save_settings'],
                'permission_callback' =>  fn() => current_user_can('manage_options'),
            ],
        ]);
    }
}
