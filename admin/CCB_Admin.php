<?php

declare(strict_types=1);

namespace CartComback\admin;



if (!defined('ABSPATH')) {
    exit();
}

class CCB_Admin
{


    /**
     * action: admin_menu
     */
    public function ccb_register_admin_page()
    {

        add_menu_page(
            'Cart Comback',
            'Cart Comback',
            'manage_options',
            'cart-comback',
            [$this, 'ccb_admin_page_render'],
            'dashicons',
            70
        );
    }

    public function ccb_admin_page_render()
    {

?>
        <h1>Cart comback</h1>
<?php
    }
}
