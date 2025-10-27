<?php
namespace AloxStore\Payments;

use WP_REST_Response;

if (!defined('ABSPATH')) exit;

class StripeGateway implements PaymentGatewayInterface {

    protected function keys(): array {
        $mode = get_option('alx_mode', 'test');
        return ($mode === 'live') ? [
            'pk' => get_option('alx_stripe_live_pk', ''),
            'sk' => get_option('alx_stripe_live_sk', ''),
        ] : [
            'pk' => get_option('alx_stripe_test_pk', ''),
            'sk' => get_option('alx_stripe_test_sk', ''),
        ];
    }

    public function id(): string    { return 'stripe'; }
    public function label(): string { return 'Stripe'; }

    public function is_enabled(): bool {
        return !empty($this->keys()['sk']);
    }

    public function create_checkout(array $cart): array {
        $session = self::create_checkout_from_cart($cart);
        return is_wp_error($session) ? [] : $session;
    }

    public static function create_checkout_from_cart(array $cart) {
        if (!class_exists('\AloxStore\Cart\CartPricing')) {
            require_once ALOXSTORE_PATH . 'includes/Cart/CartPricing.php';
        }

        $cart = \AloxStore\Cart\CartPricing::calculate($cart);
        \AloxStore\Cart\Cart::save_cart($cart);

        if (!class_exists('\Stripe\Stripe')) {
            $init = ALOXSTORE_PATH . 'lib/stripe-php/init.php';
            if (file_exists($init)) require_once $init;
        }

        $mode = get_option('alx_mode', 'test');
        $sk   = ($mode === 'live') ? get_option('alx_stripe_live_sk', '') : get_option('alx_stripe_test_sk', '');
        if (empty($sk)) {
            return new \WP_Error('alx_no_stripe_key', 'Stripe API key not set.', ['status' => 500]);
        }

        if (empty($cart['lines']) || !is_array($cart['lines'])) {
            return new \WP_Error('alx_empty_cart', 'Cart is empty.', ['status' => 400]);
        }

        $success_url = apply_filters('aloxstore_checkout_success_url', home_url('/checkout/success'));
        $cancel_url  = apply_filters('aloxstore_checkout_cancel_url', home_url('/checkout/'));

        $gross_cents = (int)($cart['total_cents'] ?? 0);

        $line_items = [[
            'price_data' => [
                'currency'     => strtolower($cart['currency'] ?? 'EUR'),
                'product_data' => ['name' => 'Order Total'],
                'unit_amount'  => $gross_cents,
            ],
            'quantity' => 1,
        ]];

        // === Updated customer schema ===
        $customer     = $cart['customer'] ?? [];
        $billing      = $customer['billing'] ?? [];
        $stripe_cust  = (string) ($customer['stripe_customer_id'] ?? '');
        $email        = (string) ($billing['email'] ?? '');
        $first_name   = (string) ($billing['first_name'] ?? '');
        $last_name    = (string) ($billing['last_name'] ?? '');
        $phone        = (string) ($billing['phone'] ?? '');
        $billing_city = (string) ($billing['city'] ?? '');
        $billing_country = (string) ($billing['country'] ?? '');
        $billing_postcode = (string) ($billing['postcode'] ?? '');
        $billing_address_1 = (string) ($billing['address_1'] ?? '');
        $billing_address_2 = (string) ($billing['address_2'] ?? '');

        $meta = [
            'cart_id'       => sanitize_key($cart['id'] ?? ''),
            'alx_email'     => $email,
            'alx_currency'  => strtoupper($cart['currency'] ?? 'EUR'),
            'alx_total'     => $gross_cents,
        ];

        $params = [
            'mode'        => 'payment',
            'line_items'  => $line_items,
            'success_url' => $success_url . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $cancel_url,
            'metadata'    => $meta,
        ];

        // === Billing & customer info ===
        if ($stripe_cust) {
            $params['customer'] = $stripe_cust;
        } else {
            if ($email) {
                $params['customer_email'] = $email;
            }
            $params['customer_creation'] = 'always';
        }

        // === Optional billing details ===
        $params['payment_intent_data'] = [
            'receipt_email' => $email,
            'description'   => sprintf('Order from %s %s', $first_name, $last_name),
            'shipping' => [
                'name'    => trim($first_name . ' ' . $last_name),
                'phone'   => $phone ?: null,
                'address' => [
                    'line1'       => $billing_address_1,
                    'line2'       => $billing_address_2 ?: null,
                    'city'        => $billing_city,
                    'postal_code' => $billing_postcode,
                    'country'     => $billing_country ?: null,
                ],
            ],
        ];

        $params = apply_filters('aloxstore_stripe_session_params', $params, $cart);

        try {
            \Stripe\Stripe::setApiKey($sk);
            $session = \Stripe\Checkout\Session::create($params);
            return [
                'id'  => $session->id,
                'url' => $session->url,
            ];
        } catch (\Exception $e) {
            return new \WP_Error('alx_stripe_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function handle_webhook(\WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['ok' => true], 200);
    }
}
