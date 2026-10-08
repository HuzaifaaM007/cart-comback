<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\admin\CCB_Admin;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_WhatsApp
{
    private const API_VERSION = 'v21.0'; // check Meta docs for the current version

    private CCB_Admin $ccb_admin;

    public function __construct()
    {
        $this->ccb_admin = new CCB_Admin();
    }

    /**
     * Are the required WhatsApp settings filled in?
     */
    private function ccb_is_whatsapp_ready(array $settings): bool
    {
        return !empty($settings['send_recovery_whatsapp'])
            && !empty($settings['wa_phone_number_id'])
            && !empty($settings['wa_access_token'])
            && !empty($settings['wa_template_name']);
    }

    /**
     * Send the cart recovery WhatsApp message to the customer.
     *
     * @param object $cart_row Row from wp_ccb_abandoned_carts
     */
    public function ccb_send_cart_recovery_whatsapp(object $cart_row): bool
    {
        $settings = $this->ccb_admin->ccb_get_settings();

        if (!$this->ccb_is_whatsapp_ready($settings)) {
            error_log('ccb: whatsapp disabled or not configured');
            return false;
        }

        // Phone number. Adjust the property name to match your table column.
        $phone = $this->ccb_normalize_phone(
            (string) ($cart_row->phone ?? ''),
            (string) ($settings['wa_default_country_code'] ?? '92')
        );

        if ($phone === '') {
            error_log('ccb: no valid phone on cart row, skipping whatsapp');
            return false;
        }

        // Skip if the matching order already has an excluded status
        if (!empty($cart_row->order_id)) {
            $order = wc_get_order($cart_row->order_id);
            if ($order && in_array('wc-' . $order->get_status(), $settings['exclude_email_for'] ?? [], true)) {
                error_log('ccb: order status excluded, skipping whatsapp');
                return false;
            }
        }

        $cart_items = json_decode((string) $cart_row->cart_contents, true);
        if (empty($cart_items)) {
            error_log('ccb: empty cart contents, skipping whatsapp');
            return false;
        }

        // Count valid products only
        $item_count = 0;
        foreach ($cart_items as $item) {
            if (wc_get_product($item['product_id'])) {
                $item_count += (int) $item['quantity'];
            }
        }

        if ($item_count === 0) {
            error_log('ccb: no valid products left in cart, skipping whatsapp');
            return false;
        }

        $current_user = wp_get_current_user();
        $user_name = get_user_meta($current_user->ID, 'billing_first_name', true);
        // Template variables ({{1}}..{{4}}). They must not contain newlines or tabs.
        $first_name = !empty($user_name) ? (string) $user_name : __('there', 'cart-comeback');
        $items_text = sprintf(_n('%d item', '%d items', $item_count, 'cart-comeback'), $item_count);
        $site_name  = get_bloginfo('name');
        $total_text = html_entity_decode(wp_strip_all_tags(wc_price($cart_row->cart_total)), ENT_QUOTES, 'UTF-8');

        $body_params = [$first_name, $items_text, $site_name, $total_text];

        $components = [
            [
                'type'       => 'body',
                'parameters' => array_map(
                    fn($v) => ['type' => 'text', 'text' => $this->ccb_clean_param((string) $v)],
                    $body_params
                ),
            ],
            // URL button with dynamic suffix. The template button URL must be:
            // https://yoursite.com/cart/?ccb_restore={{1}}
            // [
            //     'type'       => 'button',
            //     'sub_type'   => 'url',
            //     'index'      => '0',
            //     'parameters' => [
            //         ['type' => 'text', 'text' => (string) $cart_row->session_key],
            //     ],
            // ],
        ];

        $result = $this->ccb_send_template(
            $settings,
            $phone,
            (string) $settings['wa_template_name'],
            (string) ($settings['wa_language'] ?? 'en'),
            $components
        );

        error_log('ccb: recovery whatsapp sent: ' . var_export($result, true));

        return $result;
    }

    /**
     * Low-level call to the WhatsApp Cloud API.
     */
    private function ccb_send_template(array $settings, string $to, string $template, string $lang, array $components): bool
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            self::API_VERSION,
            rawurlencode((string) $settings['wa_phone_number_id'])
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'       => $template,
                'language'   => ['code' => $lang],
                'components' => $components,
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['wa_access_token'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            error_log('ccb whatsapp: request failed: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            $msg = $data['error']['message'] ?? 'Unknown WhatsApp API error';
            $err = $data['error']['code'] ?? '';
            error_log("ccb whatsapp: API error {$err}: {$msg}");
            return false;
        }

        error_log('ccb whatsapp: message id ' . ($data['messages'][0]['id'] ?? 'n/a'));
        return true;
    }

    /**
     * Digits only, international format, no "+".
     * Converts a local number like 0300 1234567 to 923001234567.
     */
    private function ccb_normalize_phone(string $phone, string $default_cc): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '' || $digits === null) {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = $default_cc . substr($digits, 1);
        }

        // E.164 allows at most 15 digits; reject obviously bad numbers
        return (strlen($digits) >= 8 && strlen($digits) <= 15) ? $digits : '';
    }

    /**
     * Template variables can't contain newlines, tabs, or 4+ consecutive spaces.
     */
    private function ccb_clean_param(string $value): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', $value);
        $value = preg_replace('/ {2,}/', ' ', $value);
        return trim($value);
    }

    /**
     * temporary test just to check the api 
     */
    // public function ccb_send_test_whatsapp(string $to_phone): bool
    // {
    //     $settings = $this->ccb_admin->ccb_get_settings();

    //     if (empty($settings['wa_phone_number_id']) || empty($settings['wa_access_token'])) {
    //         error_log('ccb test: phone number id or token missing');
    //         return false;
    //     }

    //     $to = $this->ccb_normalize_phone($to_phone, (string) ($settings['wa_default_country_code'] ?? '92'));
    //     if ($to === '') {
    //         error_log('ccb test: invalid phone');
    //         return false;
    //     }

    //     $components = [
    //         [
    //             'type'       => 'body',
    //             'parameters' => [
    //                 ['type' => 'text', 'text' => 'Huzaifa'],           // {{1}} name
    //                 ['type' => 'text', 'text' => 'bike ka 150K '],        // {{2}} order number
    //                 ['type' => 'text', 'text' => 'Oct 12, 2026'],  // {{3}} delivery date
    //             ],
    //         ],
    //     ];

    //     $result = $this->ccb_send_template(
    //         $settings,
    //         $to,
    //         'jaspers_market_order_confirmation_v1',
    //         'en_US',
    //         $components
    //     );

    //     error_log('ccb test: whatsapp sent: ' . var_export($result, true));
    //     return $result;
    // }
}
