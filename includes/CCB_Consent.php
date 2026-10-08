<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\admin\CCB_Admin;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Consent
{
    public const FIELD        = 'ccb_consent';
    private const SESSION_KEY = 'ccb_consent';

    private CCB_Admin $ccb_admin;

    public function __construct()
    {
        $this->ccb_admin = new CCB_Admin();
    }

    
    private function ccb_checkbox_enabled(): bool
    {
        $s = $this->ccb_admin->ccb_get_settings();

        return !empty($s['require_consent']) || !empty($s['send_recovery_whatsapp']);
    }

    /**
     * action: woocommerce_after_checkout_billing_form
     */
    public function ccb_render_checkbox(): void
    {
        if (!$this->ccb_checkbox_enabled()) {
            return;
        }

        $settings = $this->ccb_admin->ccb_get_settings();
        $label    = trim((string) ($settings['consent_text'] ?? ''));

        if ($label === '') {
            $label = __('Remind me about my cart by email or WhatsApp if I don\'t complete my order.', 'cart-comeback');
        }

        woocommerce_form_field(self::FIELD, [
            'type'        => 'checkbox',
            'class'       => ['form-row-wide', 'update_totals_on_change'],
            'label_class' => ['woocommerce-form__label', 'woocommerce-form__label-for-checkbox', 'checkbox'],
            'input_class' => ['woocommerce-form__input', 'woocommerce-form__input-checkbox', 'input-checkbox'],
            'label'       => $label,
            'required'    => false,
        ], self::ccb_get_session_consent());
    }

    /**
     * action: woocommerce_checkout_update_order_review  (priority 5, before the email capture)
     */
    public function ccb_store_consent(string $posted_data): void
    {
        if (!$this->ccb_checkbox_enabled() || !function_exists('WC') || WC()->session === null) {
            return;
        }

        parse_str($posted_data, $data);

        WC()->session->set(self::SESSION_KEY, empty($data[self::FIELD]) ? 0 : 1);
    }

    public static function ccb_get_session_consent(): int
    {
        if (!function_exists('WC') || WC()->session === null) {
            return 0;
        }

        return (int) WC()->session->get(self::SESSION_KEY, 0);
    }

    
    public function ccb_has_consent(object $row, string $channel = 'email'): bool
    {
        $consented = !empty($row->consent);

        if ($channel === 'whatsapp') {
            return $consented;
        }

        $s = $this->ccb_admin->ccb_get_settings();

        return empty($s['require_consent']) || $consented;
    }
}
