<?php

declare(strict_types=1);

namespace CartComback\includes;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Database
{

    /**
     * action: register_activation_hook(__FILE__, 'ccb_create_abandoned_cart_table');
     */
    public function ccb_create_abandoned_cart_table(): void
    {

        global $wpdb;
        $table   =  $wpdb->prefix . 'ccb_abandoned_carts';
        $charset =  $wpdb->get_charset_collate();

        $sql     =  "CREATE TABLE $table(
        id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_key VARCHAR(100) NOT NULL,
        user_id BIGINT UNSIGNED DEFAULT 0,
        email VARCHAR(100) DEFAULT '',
        cart_contents LONGTEXT NOT NULL,
        cart_total DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active', -- active | abandoned | recovered | ordered
        order_id BIGINT UNSIGNED DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY session_key (session_key)
        ) $charset;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);
    }

    public function ccb_insert_into_abandoned_cart_table(string $session_key, string|int $user_id, string $email, array $cart_data, string $current_date_time): bool
    {

        global $wpdb;
        $table       =  $wpdb->prefix . 'ccb_abandoned_carts';
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $table (session_key, user_id, email, cart_contents, cart_total, status, created_at, updated_at)
         VALUES (%s, %d, %s, %s, %f, 'active', %s, %s)
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            email = VALUES(email),
            cart_contents = VALUES(cart_contents),
            cart_total = VALUES(cart_total),
            status = 'active',
            updated_at = VALUES(updated_at)",
                $session_key,
                $user_id,
                $email,
                wp_json_encode($cart_data),
                WC()->cart->get_total('edit'),
                $current_date_time,
                $current_date_time
            )
        );

        return $result !== false;
    }


    public function ccb_update_abandoned_cart_table_if_ordered(string $session_key, int|string $order_id): bool
    {

        global $wpdb;
        $table = $wpdb->prefix . 'ccb_abandoned_carts';

        $result = false;
        if ($session_key && $order_id) {
            $result = $wpdb->update(
                $table,
                ['status' => 'ordered', 'order_id' => $order_id, 'updated_at' => current_time('mysql')],
                ['session_key' => $session_key]
            );
        }




        return $result !== false;
    }

    public function ccb_fetch_abandoned_carts(string $status, string $cutoff_time): bool|array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ccb_abandoned_carts';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s AND updated_at < %s",
            $status,
            $cutoff_time
        ));



        return $rows ?? [];
    }

    public function ccb_update_abandoned_cart_table_to_change_status(object $row, string $status): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ccb_abandoned_carts';

        $result = false;
        $result = $wpdb->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $row->id]
        );
        return $result !== false;
    }
}
