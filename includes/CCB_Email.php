<?php

declare(strict_types=1);

namespace CartComback\includes;

use CartComback\admin\CCB_Admin;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Email
{
    private CCB_Admin $ccb_admin;

    public function __construct()
    {
        $this->ccb_admin = new CCB_Admin();
    }

    /**
     * action: phpmailer_init
     */
    public function ccb_configure_smtp(object $phpmailer)
    {

        $settings = $this->ccb_admin->ccb_get_settings();
        $smtp_enabled = $settings['enable_smtp'] ? $settings['enable_smtp'] : 0;

        if ($smtp_enabled !== '1') {
            error_log('smtp not enabled ');
            return;
        }

        $host       =  $settings['smtp_host']       ??  'smtp.gmail.com';
        $port       =  (int) $settings['smtp_port'] ??  587;
        $username   =  $settings['from_name']       ??  get_bloginfo('name');
        $password   =  $settings['app_password']    ??  '';
        $from       =  $settings['from_email']      ??  get_option('admin_email');
        $from_name  =  $settings['from_name']       ??  get_bloginfo('name');
        $encryption =  $settings['encryption']      ??  'tls';

        if (empty($username) || empty($password)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = $port;
        $phpmailer->Username   = $username;
        $phpmailer->Password   = $password;
        $phpmailer->SMTPSecure = $encryption; // 'tls' or 'ssl'
        $phpmailer->From       = $from;
        $phpmailer->FromName   = $from_name;
    }


    /**
     * Send the cart recovery email to the customer.
     *
     * @param object $cart_row Row from wp_ccb_abandoned_carts (has cart_contents, email, session_key, etc.)
     */
    public function ccb_send_cart_recovery_email(object|array $cart_row)
    {
        $settings = $this->ccb_admin->ccb_get_settings();

        if (empty($settings['send_recovery_email'])) {
            error_log('ccb: recovery email disabled');
            return false;
        }

        if (empty($cart_row->email)) {
            error_log('ccb: no email on cart row, skipping');
            return false;
        }

        // Skip if the matching order already has an excluded status
        if ($cart_row->order_id) {
            $order = wc_get_order($cart_row->order_id);
            if ($order && in_array('wc-' . $order->get_status(), $settings['exclude_email_for'] ?? [], true)) {
                error_log('ccb: order status excluded, skipping email');
                return false;
            }
        }

        $cart_items = json_decode($cart_row->cart_contents, true);
        if (empty($cart_items)) {
            error_log('ccb: empty cart contents, skipping email');
            return false;
        }

        $products = [];
        foreach ($cart_items as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product) {
                continue;
            }

            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src();

            $products[] = [
                'name'      => $product->get_name(),
                'url'       => $product->get_permalink(),
                'price'     => $product->get_price_html(),
                'quantity'  => $item['quantity'],
                'image_url' => $image_url,
            ];
        }

        if (empty($products)) {
            error_log('ccb: no valid products left in cart, skipping email');
            return false;
        }

        $site_name = get_bloginfo('name');

        // Gmail SMTP enabled: phpmailer_init() already sets From/FromName/auth,
        // so we only need Reply-To here. Otherwise fall back to the Email section's
        // sender_name/reply_to and wp_mail's default From address.
        $smtp_enabled = !empty($settings['enable_smtp']);

        if ($smtp_enabled) {
            $sender_name = !empty($settings['from_name']) ? $settings['from_name'] : $site_name;
            $reply_to    = !empty($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
        } else {
            $sender_name = !empty($settings['sender_name']) ? $settings['sender_name'] : $site_name;
            $reply_to    = !empty($settings['reply_to']) ? $settings['reply_to'] : get_option('admin_email');
        }

        $recovery_url = add_query_arg('ccb_restore', $cart_row->session_key, wc_get_cart_url());

        $subject = sprintf(__('You left something behind at %s', 'cart-comback'), $site_name);

        $body = $this->ccb_get_cart_recovery_email_template([
            'site_name'    => $site_name,
            'products'     => $products,
            'cart_total'   => wc_price($cart_row->cart_total),
            'recovery_url' => $recovery_url,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $reply_to,
        ];

        // Only set From manually when SMTP is off — phpmailer_init() sets it when SMTP is on,
        // and Gmail rejects a From that doesn't match the authenticated account.
        if (!$smtp_enabled) {
            $headers[] = 'From: ' . $sender_name . ' <' . get_option('admin_email') . '>';
        }

        $email_sent = wp_mail($cart_row->email, $subject, $body, $headers);

        error_log('ccb: recovery email sent: ' . $email_sent);

        error_log('email sent : ' . $email_sent);
        return $email_sent;
    }



    /**
     * HTML Template Builder
     */
    function ccb_get_cart_recovery_email_template(array $data)
    {
        ob_start();
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cart Recovery</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background-color: #f4f4f7;
                    color: #51545e;
                    margin: 0;
                    padding: 0;
                    width: 100% !important;
                }

                .container {
                    max-width: 600px;
                    margin: 20px auto;
                    background: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .header {
                    background-color: #222529;
                    color: #ffffff;
                    padding: 24px;
                    text-align: center;
                }

                .header h1 {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 600;
                }

                .content {
                    padding: 32px 24px;
                    text-align: center;
                }

                .product-row {
                    display: flex;
                    align-items: center;
                    text-align: left;
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 8px;
                    padding: 14px;
                    margin: 12px 0;
                }

                .product-image {
                    width: 64px;
                    height: 64px;
                    object-fit: cover;
                    border-radius: 6px;
                    margin-right: 16px;
                }

                .product-title {
                    font-size: 15px;
                    font-weight: bold;
                    color: #2d3748;
                    margin: 0 0 4px;
                }

                .product-meta {
                    font-size: 13px;
                    color: #718096;
                }

                .product-price {
                    font-size: 14px;
                    color: #2b6cb0;
                }

                .cart-total {
                    font-size: 16px;
                    font-weight: bold;
                    color: #2d3748;
                    margin: 20px 0 10px;
                    text-align: right;
                }

                .btn {
                    display: inline-block;
                    background-color: #2563eb;
                    color: #ffffff !important;
                    text-decoration: none;
                    padding: 12px 28px;
                    border-radius: 6px;
                    font-weight: 600;
                    font-size: 15px;
                    margin-top: 16px;
                }

                .footer {
                    padding: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #a0aec0;
                    background: #f8f9fa;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($data['site_name']); ?></h1>
                </div>
                <div class="content">
                    <h2>You left items in your cart</h2>
                    <p>Your cart is still saved. Complete your order before these items sell out.</p>

                    <?php foreach ($data['products'] as $product): ?>
                        <div class="product-row">
                            <img src="<?php echo esc_url($product['image_url']); ?>" alt="<?php echo esc_attr($product['name']); ?>" class="product-image" />
                            <div>
                                <div class="product-title"><?php echo esc_html($product['name']); ?></div>
                                <div class="product-meta">Qty: <?php echo esc_html($product['quantity']); ?></div>
                                <div class="product-price"><?php echo wp_kses_post($product['price']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="cart-total">Total: <?php echo wp_kses_post($data['cart_total']); ?></div>

                    <a href="<?php echo esc_url($data['recovery_url']); ?>" class="btn">Complete Your Order</a>
                </div>
                <div class="footer">
                    <p>You received this email because you left items in your cart at <?php echo esc_html($data['site_name']); ?>.</p>
                </div>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
