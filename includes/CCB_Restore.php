<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\includes\CCB_Database;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Restore
{
    private CCB_Database $ccb_database;

    public function __construct()
    {
        $this->ccb_database = new CCB_Database();
    }

    /**
     * action: template_redirect
     */
    public function ccb_maybe_restore_cart(): void
    {
        if (!isset($_GET['ccb_restore']) || is_admin() || !function_exists("WC")) {
            return;
        }

        $session_key = sanitize_text_field($_GET['ccb_restore']);

        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $session_key)) {
            $this->ccb_finish(__('That cart link is not valid.', 'cart-comeback'), 'error', wc_get_cart_url());
        }

        if (WC()->cart === null && function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (WC()->cart ===  null) {
            return;
        }

        if (WC()->session) {
            WC()->session->set_customer_session_cookie(true);
        }

        $row = $this->ccb_database->ccb_get_cart_by_session_key($session_key);

        if (!$row) {
            $this->ccb_finish(__('We could not find that cart. It may have expired.', 'cart-comeback'), 'notice', wc_get_page_permalink('shop'));
        }

        if ($row->status === 'ordered') {
            $this->ccb_finish(__('This cart has already been ordered.', 'cart-comeback'), 'notice', wc_get_page_permalink('shop'));
        }

        $items = json_decode((string) $row->cart_contents, true);

        if (!is_array($items) || empty($items)) {
            $this->ccb_finish(__('That cart is empty.', 'cart-comeback'), 'notice', wc_get_page_permalink('shop'));
        }

        WC()->cart->empty_cart();

        $added   = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $product_id   = absint($item['product_id'] ?? 0);
            $quantity     = max(1, absint($item['quantity'] ?? 1));
            $variation_id = absint($item['variation_id'] ?? 0);
            $variation    = is_array($item['variation'] ?? null)
                ? array_map('sanitize_text_field', $item['variation'])
                : [];

            $product = wc_get_product($variation_id ?: $product_id);

            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                $skipped++;
                continue;
            }

            if (WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation)) {
                $added++;
            } else {
                $skipped++;
            }
        }

        if ($added === 0) {
            $this->ccb_finish(__('Sorry, the items in that cart are no longer available.', 'cart-comeback'), 'notice', wc_get_page_permalink('shop'));
        }

        $this->ccb_database->ccb_update_abandoned_cart_table_to_change_status($row, 'recovered');

        if ($skipped > 0) {
            wc_add_notice(__('Some items from your cart are no longer available.', 'cart-comeback'), 'notice');
        }

        $this->ccb_finish(
            __('Welcome back! We restored your cart.', 'cart-comeback'),
            'success',
            (string) apply_filters('ccb_restore_redirect_url', wc_get_checkout_url(), $row)
        );
    }

    /**
     * add a notice and redirect
     */
    private function ccb_finish(string $message, string $type, string $url): void
    {
        wc_add_notice($message, $type);
        wp_safe_redirect($url);
        exit;
    }
}
