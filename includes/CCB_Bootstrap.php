<?php

declare(strict_types=1);

namespace CartComback\includes;

if (!defined('ABSPATH')) {
    exit();
}

use CartComback\includes\CCB_Loader;
use CartComback\admin\CCB_Admin;

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
        $ccb_admin = new CCB_Admin();

        error_log('ccb_admin_hooks');
        $this->ccb_loader->add_action('admin_menu', $ccb_admin, 'ccb_register_admin_page');
        // $this->ccb_loader->add_action('admin_init', $ccb_admin, 'ccb_chat_register_settings');

    }

    function define_wp_hooks()
    {
        
    }


    function run()
    {
        $this->ccb_loader->run();
    }
}
