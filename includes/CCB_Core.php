<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\includes\CCB_Database;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Core
{

    private CCB_Database $ccb_database;

    public function __construct()
    {
        $this->ccb_database = new CCB_Database();
    }

    /**
     * action: woocommerce_add_to_cart
     * action: woocommerce_cart_item_removed
     * action: woocommerce_after_cart_item_quantity_update
     * action: woocommerce_check_cart_items
     */
    public function ccb_snapshot_cart(): void
    {

        if (is_admin() || WC()->cart === null || WC()->cart->is_empty()) {
            return;
        }


        $session_key =  WC()->session->generate_customer_id();
        $user_id     =  get_current_user_id();
        $email       =  '';

        if ($user_id) {
            $user  =  get_userdata($user_id);
            $email =  $user->user_email;
        } elseif (WC()->customer && WC()->customer->get_billing_email()) {
            $email = WC()->customer->get_billing_email();
        }

        $cart_data = [];
        foreach (WC()->cart->get_cart() as $item) {
            $cart_data[] = [
                'product_id' =>  $item['product_id'],
                'quantity'   =>  $item['quantity'],
            ];
        }

        $now = current_time('mysql');

        $result =  $this->ccb_database->ccb_insert_into_abandoned_cart_table($session_key, $user_id, $email, $cart_data, $now);

        error_log('inserted rows in abandoned cart table :' . $result . ' time: ' . $now);
    }

    /**
     * action : woocommerce_thankyou
     * action : woocommerce_order_status_changed
     */
    public function ccb_mark_cart_ordered(int $order_id): void
    {
        if (!$order_id) {
            return;
        }

        $session_key = WC()->session ? WC()->session->get_customer_id() : null;

        if ($session_key) {
            $result = $this->ccb_database->ccb_update_abandoned_cart_table_if_ordered($session_key, $order_id);
            error_log('inserted rows in abandoned cart table :' . $result . ' time: ' . current_time('mysql'));
        }
    }


}
