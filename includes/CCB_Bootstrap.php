<?php

declare(strict_types=1);

namespace CartComback\includes;

if (!defined('ABSPATH')) {
    exit();
}

use CartComback\includes\CCB_Loader;
use CartComback\admin\CCB_Admin;
use CartComback\includes\CCB_Assets;
use CartComback\includes\CCB_API;
use CartComback\includes\CCB_Core;
use CartComback\includes\CCB_Cron;
use CartComback\includes\CCB_Email;

class CCB_Bootstrap
{

    private CCB_Loader $ccb_loader;

    public function __construct()
    {
        $this->load_dependencies();
        $this->define_wp_hooks();
        error_log('define admin hooks 1');
        $this->define_admin_hooks();
    }

    function load_dependencies()
    {
        //     not using require as using auto loader using namespaces for all the files F
        //     require_once QUERY_BOT . 'includes/class-ccb-loader.php';
        //     require_once QUERY_BOT . 'includes/class-ccb-assets.php';
        //     require_once QUERY_BOT . 'includes/admin/class-ccb-admin.php';
        //     require_once QUERY_BOT . 'includes/class-ccb-ajax_requests.php';


        $this->ccb_loader = new CCB_Loader();
    }

    function define_admin_hooks()
    {

        // error_log('ccb_admin_hooks');

        $ccb_admin = new CCB_Admin();
        $this->ccb_loader->add_action('admin_menu',                                  $ccb_admin,        'ccb_register_admin_page');

        $ccb_assets =  new CCB_Assets();
        $this->ccb_loader->add_action('admin_enqueue_scripts',                       $ccb_assets,      'ccb_enqueue_admin_assets');

        $ccb_api    =  new CCB_API();
        $this->ccb_loader->add_action('rest_api_init',                               $ccb_api,        'ccb_register_admin_routes');

        $ccb_core   =  new CCB_Core();
        $this->ccb_loader->add_action('woocommerce_add_to_cart',                     $ccb_core,               'ccb_snapshot_cart');
        $this->ccb_loader->add_action('woocommerce_cart_item_removed',               $ccb_core,               'ccb_snapshot_cart');
        $this->ccb_loader->add_action('woocommerce_after_cart_item_quantity_update', $ccb_core,               'ccb_snapshot_cart');
        $this->ccb_loader->add_action('woocommerce_check_cart_items',                $ccb_core,               'ccb_snapshot_cart');
        $this->ccb_loader->add_action('woocommerce_thankyou',                        $ccb_core,           'ccb_mark_cart_ordered');
        $this->ccb_loader->add_action('woocommerce_order_status_changed',            $ccb_core,           'ccb_mark_cart_ordered');

        $ccb_cron   =  new CCB_Cron();
        $this->ccb_loader->add_filter('cron_schedules',                              $ccb_cron,           'ccb_add_cron_schedule');
        $this->ccb_loader->add_filter('ccb_check_abandoned_carts',                   $ccb_cron,         'ccb_run_abandoned_check');

        $ccb_email = new CCB_Email();
        $this->ccb_loader->add_action('phpmailer_init',                              $ccb_email,             'ccb_configure_smtp');
        $this->ccb_loader->add_action('ccb_cart_abandoned',                          $ccb_email,   'ccb_send_cart_recovery_email');
    }

    function define_wp_hooks() {}


    function run()
    {
        $this->ccb_loader->run();
    }
}
