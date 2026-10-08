<?php

declare(strict_types=1);

namespace CartComback\admin;



if (!defined('ABSPATH')) {
    exit();
}

class CCB_Admin
{


    private const OPTION = 'ccb_settings_options';


    public function ccb_schema(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'fields' => [
                    'enable_tracker' => [
                        'type'    =>  'toggle',
                        'label'   =>  'Enable Tracking',
                        'desc'    =>  'Cart will be considered abandoned if order is not completed in cut-off time.',
                        'default' =>  1,
                    ],
                    'cut_off' => [
                        'type'    =>  'number',
                        'label'   =>  'Cart Abandoned Cut-Off Time',
                        'desc'    =>  'Consider Cart abandoned after this many minutes of item being added to cart and order not placed.',
                        'suffix'  =>  'minutes',
                        'min'     =>  1,
                        'default' =>  20,
                    ],
                    'disable_tracking_for' => [
                        'type'         =>  'multiselect',
                        'label'        =>  'Disable Tracking For',
                        'desc'         =>  'Selected user roles are ignored by the abandonment process when logged in.',
                        'placeholder'  =>  'Select user roles',
                        'options'      =>  $this->ccb_get_user_roles(),
                        'default'      =>  [],
                    ],
                    'exclude_email_for' => [
                        'type'        =>  'multiselect',
                        'label'       =>  'Exclude Email Sending For',
                        'desc'        =>  'Future recovery emails are not sent for selected order statuses; the cart is marked as recovered.',
                        'placeholder'  =>  'Select Order Statuses',
                        'options'     =>  $this->ccb_get_order_statuses(),
                        'default'     =>  ['wc-processing', 'wc-completed'],
                    ],
                    'send_recovery_email' => [
                        'type'    => 'toggle',
                        'label'   => 'Recovery Email',
                        'desc'    => 'Send recovery emails for abandoned carts.',
                        'default' => 1,
                    ],
                    'require_consent' => [
                        'type'    => 'toggle',
                        'label'   => 'Require Customer Consent',
                        'desc'    => 'Only send recovery emails to customers who ticked the checkout checkbox. WhatsApp always requires consent.',
                        'default' => 1,
                    ],
                    'consent_text' => [
                        'type'        => 'text',
                        'label'       => 'Checkbox Text',
                        'desc'        => 'Shown next to the checkout checkbox. Leave empty for the default wording.',
                        'placeholder' => 'Remind me about my cart by email or WhatsApp if I don\'t complete my order.',
                        'default'     => '',
                    ],
                ]
            ],
            'gmail_smtp' => [
                'label' => 'Gmail SMTP Settings',
                'fields' => [
                    'enable_smtp' => [
                        'type'    => 'toggle',
                        'label'   => 'Enable Gmail SMTP',
                        'desc'    => 'Route all outbound emails through Gmail SMTP.',
                        'default' => 0,
                    ],
                    'smtp_host' => [
                        'type'        => 'text',
                        'label'       => 'SMTP Host',
                        'desc'        => 'The hostname for the Gmail SMTP server.',
                        'placeholder' => 'smtp.gmail.com',
                        'default'     => 'smtp.gmail.com',
                    ],
                    'smtp_port' => [
                        'type'    => 'number',
                        'label'   => 'SMTP Port',
                        'desc'    => 'Use 587 for TLS or 465 for SSL encryption.',
                        'min'     => 1,
                        'default' => 587,
                    ],
                    'encryption' => [
                        'type'    => 'select',
                        'label'   => 'Encryption Type',
                        'desc'    => 'Select security encryption protocol.',
                        'options' => [
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                        ],
                        'default' => 'tls',
                    ],
                    'from_email' => [
                        'type'        => 'email',
                        'label'       => 'From Email Address',
                        'desc'        => 'Your Gmail or Google Workspace email address.',
                        'placeholder' => 'youremail@gmail.com',
                        'default'     => '',
                    ],
                    'from_name' => [
                        'type'        => 'text',
                        'label'       => 'From Name',
                        'desc'        => 'The name displayed in recipient inboxes.',
                        'placeholder' => 'Your Store Name',
                        'default'     => '',
                    ],
                    'app_password' => [
                        'type'        => 'password',
                        'label'       => 'Google App Password',
                        'desc'        => 'Use a 16-character 2-Step Verification App Password generated from your Google Account settings.',
                        'placeholder' => '•••• •••• •••• ••••',
                        'default'     => '',
                    ],
                ]
            ],
            'whatsapp' => [
                'label' => 'WhatsApp Settings',
                'fields' => [
                    'send_recovery_whatsapp' => [
                        'type'    => 'toggle',
                        'label'   => 'Enable WhatsApp Recovery',
                        'desc'    => 'Send cart recovery messages via the WhatsApp Business Cloud API. Customers must have opted in.',
                        'default' => 0,
                    ],
                    'wa_phone_number_id' => [
                        'type'        => 'text',
                        'label'       => 'Phone Number ID',
                        'desc'        => 'Found in Meta App Dashboard → WhatsApp → API Setup. This is the numeric ID, not the phone number itself.',
                        'placeholder' => '123456789012345',
                        'default'     => '',
                    ],
                    'wa_access_token' => [
                        'type'        => 'password',
                        'label'       => 'Access Token',
                        'desc'        => 'Use a permanent System User token with whatsapp_business_messaging and whatsapp_business_management permissions.',
                        'placeholder' => 'EAAB••••••••••••',
                        'default'     => '',
                    ],
                    'wa_template_name' => [
                        'type'        => 'text',
                        'label'       => 'Template Name',
                        'desc'        => 'The exact name of your approved template in WhatsApp Manager.',
                        'placeholder' => 'cart_recovery',
                        'default'     => 'cart_recovery',
                    ],
                    'wa_language' => [
                        'type'        => 'text',
                        'label'       => 'Template Language Code',
                        'desc'        => 'Must match the language the template was approved in (e.g. en, en_US).',
                        'placeholder' => 'en',
                        'default'     => 'en',
                    ],
                    'wa_default_country_code' => [
                        'type'        => 'text',
                        'label'       => 'Default Country Code',
                        'desc'        => 'Used to convert local numbers starting with 0 into international format. Digits only, no +.',
                        'placeholder' => '92',
                        'default'     => '92',
                    ],
                ]
            ],
        ];
    }

    private function ccb_get_user_roles(): array
    {
        return wp_roles()->get_names();
    }

    private function ccb_get_order_statuses(): array
    {
        return function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
    }

    private function ccb_sanitize_field(array $f, $v)
    {
        switch ($f['type']) {
            case 'toggle':
                return $v ? 1 : 0;
            case 'number':
                return max((int) ($f['min'] ?? 0), absint($v));
            case 'email':
                return sanitize_email((string) $v);
            case 'textarea':
                return sanitize_textarea_field((string) $v);
            case 'password':
                return str_replace(' ', '', sanitize_text_field((string) $v));
            case 'select':
                return (is_string($v) && isset($f['options'][$v])) ? $v : $f['default'];
            case 'multiselect':
                $allowed = array_map('strval', array_keys($f['options']));
                $vals    = is_array($v) ? array_filter($v, 'is_scalar') : [];
                $vals    = array_map(fn($x) => sanitize_text_field((string) $x), $vals);
                return array_values(array_intersect($vals, $allowed));
            default:
                return sanitize_text_field((string) $v);
        }
    }


    private function ccb_all_fields(): array
    {
        $out = [];
        foreach ($this->ccb_schema() as $section) {
            foreach ($section['fields'] as $key => $f) {
                $out[$key] = $f;
            }
        }

        return $out;
    }

    public function ccb_get_settings(): array
    {

        $saved = get_option(self::OPTION);
        $saved = is_array($saved) ? $saved : [];
        $out   = [];
        foreach ($this->ccb_all_fields() as $key => $field) {
            $out[$key] = $saved[$key] ?? $field['default'];
        }
        return $out;
    }

    public function ccb_is_smtp_ready(): bool
    {
        $s = $this->ccb_get_settings();

        return !empty($s['enable_smtp'])
            && is_email($s['from_email'])
            && !empty($s['app_password']);
    }

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
            'dashicons-chart-area',
            70
        );
    }

    // public function ccb_register_settings()
    // {
    //     register_setting('ccb_settings', 'ccb_settings_options', [$this, 'ccb_sanitize_options']);

    //     add_settings_section('ccb_general_settings', 'General', null, 'cart-comback');

    //     add_settings_field('ccb_general_enable_tracker', 'Enable Tracking', [$this, 'ccb_general_tracker_render'], 'cart_comback', 'ccb_general_settings',);
    //     add_settings_field('ccb_general_cut_off', 'Cut-Off Time', [$this, 'ccb_cut_off_render'], 'cart_comback', 'ccb_general_settings',);
    //     add_settings_field('ccb_general_disable_tracking_for', 'Disable Tracking For', [$this, 'ccb_general__disble_tracker_for_render'], 'cart_comback', 'ccb_general_settings',);
    //     add_settings_field('ccb_general_exclude_email', 'Exclude Email for', [$this, 'ccb_general_exclude_email_for'], 'cart_comback', 'ccb_general_settings',);
    //     add_settings_field('ccb_general_send_recovery_email', 'Recovery Email', [$this, 'ccb_general_recovery_email'], 'cart_comback', 'ccb_general_settings',);
    // }




    public function ccb_admin_page_render(): void
    {
        if (!file_exists(CCB_PATH . 'build/index.asset.php')) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Build files missing. Run <code>npm install &amp;&amp; npm run build</code>.</p></div></div>';
            return;
        }
        echo '<div class="wrap" style="margin:0"><div id="ccb-root"></div></div>';
    }

    public function ccb_save_settings(\WP_REST_Request $req): array
    {
        $data  = $req->get_json_params();
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        foreach ($this->ccb_all_fields() as $key => $field) {
            if (is_array($data) && array_key_exists($key, $data)) {
                $saved[$key] = $this->ccb_sanitize_field($field, $data[$key]);
            }
        }

        update_option(self::OPTION, $saved);
        return $this->ccb_get_settings();
    }
}
