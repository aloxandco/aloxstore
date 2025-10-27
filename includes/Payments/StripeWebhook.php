<?php
namespace AloxStore\Payments;

use AloxStore\Cart\CartPricing;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class StripeWebhook {

    public static function handle(WP_REST_Request $request) {
        $payload    = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret     = trim(get_option('alx_stripe_webhook_secret', ''));

        if (empty($secret)) {
            return new \WP_Error('alx_no_secret', 'Stripe webhook secret not set.', ['status' => 500]);
        }

        if (!class_exists('\Stripe\Webhook')) {
            require_once ALOXSTORE_PATH . 'lib/stripe-php/init.php';
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
        } catch (\Exception $e) {
            error_log('[AloxStore] Stripe signature error: ' . $e->getMessage());
            return new \WP_Error('alx_invalid_sig', 'Invalid Stripe signature.', ['status' => 400]);
        }

        // Only handle completed checkouts
        if ($event->type !== 'checkout.session.completed') {
            return rest_ensure_response(['ok' => true, 'ignored' => true]);
        }

        $session     = $event->data->object;
        $session_id  = $session->id ?? '';
        $cart_id     = $session->metadata->cart_id ?? '';
        $email       = $session->customer_details->email ?? '';
        $cart        = get_transient('alx_cart_' . sanitize_key($cart_id));

        if (empty($cart) || empty($cart['lines'])) {
            error_log("[AloxStore] Empty/missing cart for session: $session_id");
            return new \WP_Error('alx_empty_cart', 'Cart not found or empty.', ['status' => 400]);
        }

        // Prevent duplicate orders
        $existing = new \WP_Query([
            'post_type'   => 'alox_order',
            'post_status' => 'any',
            'meta_key'    => '_alx_stripe_session_id',
            'meta_value'  => $session_id,
            'fields'      => 'ids',
        ]);

        if (!empty($existing->posts)) {
            return rest_ensure_response([
                'ok' => true,
                'already_processed' => true,
                'order_id' => $existing->posts[0]
            ]);
        }

        // Normalize cart totals with pricing logic
        $cart = CartPricing::calculate($cart);

        // Ensure CPT is registered
        if (!post_type_exists('alox_order')) {
            \AloxStore\CPT\Orders::register();
        }

        // Generate new Order number
        $order_num = absint(get_option('alx_order_last_num', 0)) + 1;
        update_option('alx_order_last_num', $order_num);

        // Create the order post
        $order_id = wp_insert_post([
            'post_type'   => 'alox_order',
            'post_status' => 'publish',
            'post_title'  => sprintf('Order #%06d', $order_num),
        ]);

        if (is_wp_error($order_id)) {
            return $order_id;
        }

        // === SAVE CORE META ===
        update_post_meta($order_id, '_alx_stripe_session_id', $session_id);
        update_post_meta($order_id, '_alx_cart', $cart);
        update_post_meta($order_id, '_alx_subtotal', (int)($cart['subtotal_cents'] ?? 0));
        update_post_meta($order_id, '_alx_shipping', (int)($cart['shipping_cents'] ?? 0));
        update_post_meta($order_id, '_alx_tax', (int)($cart['tax_cents'] ?? 0));
        update_post_meta($order_id, '_alx_total', (int)($cart['total_cents'] ?? 0));
        update_post_meta($order_id, '_alx_paid', true);

        // === SAVE CUSTOMER DETAILS ===
        if (!empty($cart['customer'])) {
            $cust = $cart['customer'];

            // Stripe Customer ID
            if (!empty($cust['stripe_customer_id'])) {
                update_post_meta($order_id, '_alx_stripe_customer_id', sanitize_text_field($cust['stripe_customer_id']));
            }

            // Billing info
            if (!empty($cust['billing']) && is_array($cust['billing'])) {
                foreach ($cust['billing'] as $key => $value) {
                    $val = is_email($value) ? sanitize_email($value) : sanitize_text_field($value);
                    update_post_meta($order_id, '_alx_billing_' . $key, $val);
                }
            }

            // Shipping info
            if (!empty($cust['shipping']) && is_array($cust['shipping'])) {
                foreach ($cust['shipping'] as $key => $value) {
                    $val = sanitize_text_field($value);
                    update_post_meta($order_id, '_alx_shipping_' . $key, $val);
                }
            }

            // Also keep a top-level reference to billing email
            if (!empty($cust['billing']['email'])) {
                update_post_meta($order_id, '_alx_billing_email', sanitize_email($cust['billing']['email']));
            }
        }

        // === STRIPE META ===
        update_post_meta($order_id, '_alx_stripe_meta', (array)($session->metadata ?? []));
        if (!empty($session->amount_total)) {
            update_post_meta($order_id, '_alx_stripe_amount_total', (int)$session->amount_total);
        }
        if (!empty($session->currency)) {
            update_post_meta($order_id, '_alx_stripe_currency', strtoupper($session->currency));
        }

        // Clean up transient cart
        delete_transient('alx_cart_' . sanitize_key($cart_id));

        return rest_ensure_response([
            'ok'       => true,
            'order_id' => $order_id,
            'session'  => $session_id,
        ]);
    }
}
