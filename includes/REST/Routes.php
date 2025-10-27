<?php
namespace AloxStore\REST;

use WP_REST_Request;
use AloxStore\Cart\Cart;
use AloxStore\Payments\StripeGateway;
use AloxStore\Payments\StripeWebhook;

if ( ! defined( 'ABSPATH' ) ) exit;

class Routes {

    public static function register() {

        register_rest_route( 'aloxstore/v1', '/cart', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_cart' ],
            'permission_callback' => [ __CLASS__, 'perm_public_nonce' ],
        ] );

        register_rest_route( 'aloxstore/v1', '/cart/add', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'cart_add' ],
            'permission_callback' => [ __CLASS__, 'perm_public_nonce' ],
            'args'                => [
                'product_id' => [ 'type' => 'integer', 'required' => true ],
                'qty'        => [ 'type' => 'integer', 'required' => false, 'default' => 1 ],
            ],
        ] );

        register_rest_route( 'aloxstore/v1', '/cart/set-qty', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'cart_set_qty' ],
            'permission_callback' => [ __CLASS__, 'perm_public_nonce' ],
            'args'                => [
                'product_id' => [ 'type' => 'integer', 'required' => true ],
                'qty'        => [ 'type' => 'integer', 'required' => true ],
            ],
        ] );

        register_rest_route( 'aloxstore/v1', '/cart/remove', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'cart_remove' ],
            'permission_callback' => [ __CLASS__, 'perm_public_nonce' ],
            'args'                => [
                'product_id' => [ 'type' => 'integer', 'required' => true ],
            ],
        ] );

        register_rest_route( 'aloxstore/v1', '/cart/clear', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'cart_clear' ],
            'permission_callback' => [ __CLASS__, 'perm_public_nonce' ],
        ] );

        register_rest_route( 'aloxstore/v1', '/checkout', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout' ],
            'permission_callback' => [ __CLASS__, 'perm_public_nonce' ],
        ] );

        register_rest_route( 'aloxstore/v1', '/checkout/customer', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_customer' ],
            'permission_callback' => [ __CLASS__, 'perm_public_nonce' ],
        ] );

        register_rest_route( 'aloxstore/v1', '/webhook/stripe', [
            'methods'             => 'POST',
            'callback'            => [ StripeWebhook::class, 'handle' ],
            'permission_callback' => '__return_true', // Webhooks are unauthenticated
        ] );

    }

    /** Public routes but CSRF-protected via REST nonce */
    public static function perm_public_nonce( \WP_REST_Request $request ): bool {
        // 1. Allow admin / authenticated users with a valid WP REST nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if ( wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        // 2. Allow guests with a valid cart session token cookie
        if ( isset( $_COOKIE['alx_cart'] ) && preg_match( '/^[a-f0-9\-]{32,}$/i', $_COOKIE['alx_cart'] ) ) {
            return true;
        }

        // 3. Deny anything else
        return false;
    }

    /** GET /cart */
    public static function get_cart( WP_REST_Request $request ) {
        $cart = Cart::get_cart();
        return rest_ensure_response( self::enrich_cart( $cart ) );
    }

    /** POST /cart/add */
    public static function cart_add( WP_REST_Request $request ) {
        $product_id = max( 0, (int) $request->get_param( 'product_id' ) );
        $qty        = max( 1, (int) $request->get_param( 'qty' ) );

        if ( ! self::is_valid_product_id( $product_id ) ) {
            return new \WP_Error( 'alx_bad_product', __( 'Invalid product.', 'aloxstore' ), [ 'status' => 400 ] );
        }

        $cart = Cart::add( $product_id, $qty );
        return rest_ensure_response( self::enrich_cart( $cart ) );
    }

    /** POST /cart/set-qty */
    public static function cart_set_qty( WP_REST_Request $request ) {
        $product_id = max( 0, (int) $request->get_param( 'product_id' ) );
        $qty        = (int) $request->get_param( 'qty' );

        if ( ! self::is_valid_product_id( $product_id ) ) {
            return new \WP_Error( 'alx_bad_product', __( 'Invalid product.', 'aloxstore' ), [ 'status' => 400 ] );
        }

        $cart = Cart::set_qty( $product_id, $qty );
        return rest_ensure_response( self::enrich_cart( $cart ) );
    }

    /** POST /cart/remove */
    public static function cart_remove( WP_REST_Request $request ) {
        $product_id = max( 0, (int) $request->get_param( 'product_id' ) );
        if ( ! self::is_valid_product_id( $product_id ) ) {
            return new \WP_Error( 'alx_bad_product', __( 'Invalid product.', 'aloxstore' ), [ 'status' => 400 ] );
        }
        $cart = Cart::remove( $product_id );
        return rest_ensure_response( self::enrich_cart( $cart ) );
    }

    /** POST /cart/clear */
    public static function cart_clear( WP_REST_Request $request ) {
        $cart = Cart::clear(); // use a dedicated clearer
        return rest_ensure_response( self::enrich_cart( $cart ) );
    }

    /** POST /checkout */
    public static function checkout( WP_REST_Request $request ) {
        $cart = self::enrich_cart( Cart::get_cart() );

        if ( empty( $cart['lines'] ) ) {
            return new \WP_Error( 'alx_empty_cart', __( 'Your cart is empty.', 'aloxstore' ), [ 'status' => 400 ] );
        }

        // Stripe can't mix currencies
        $lineCurrencies = array_unique( array_map( function( $l ){ return strtoupper( (string) ( $l['currency'] ?? '' ) ); }, $cart['lines'] ) );
        if ( count( $lineCurrencies ) > 1 ) {
            return new \WP_Error( 'alx_mixed_currency', __( 'Your cart contains items with different currencies.', 'aloxstore' ), [ 'status' => 400 ] );
        }

        $session = StripeGateway::create_checkout_from_cart( $cart );
        if ( is_wp_error( $session ) ) return $session;

        return rest_ensure_response( [
            'session_id' => $session['id'],
            'url'        => $session['url'],
        ] );
    }

    /** Expand cart lines and basic totals
     *
     * NOTE: pricing and canonical totals are computed in Cart::save_cart() via CartPricing.
     * To avoid accidental overwrites we now return the saved cart as-is. If you need
     * presentation-only fields (formatted strings, thumbnails) add them here without
     * touching the canonical numeric keys.
     */
    public static function enrich_cart( array $cart ): array {
        // Always re-sync the canonical cart data from pricing engine
        $cart = \AloxStore\Cart\CartPricing::calculate( $cart );

        // Add presentation-only details (no numeric recalculation)
        $lines = [];
        foreach ( $cart['lines'] ?? [] as $line ) {
            $pid = (int) ( $line['product_id'] ?? 0 );
            if ( ! $pid || get_post_type( $pid ) !== 'alox_product' ) {
                continue;
            }

            $thumb_url = get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '';
            $permalink = get_permalink( $pid );

            $lines[] = array_merge( $line, [
                'title'     => get_the_title( $pid ),
                'thumbnail' => $thumb_url,
                'permalink' => $permalink,
                // Keep backward-compatibility aliases
                'line_cents' => $line['line_gross_cents'] ?? 0,
            ]);
        }

        $cart['lines'] = $lines;
        return $cart;
    }

    protected static function pick_cart_currency( array $lines, string $fallback ): string {
        if ( empty( $lines ) ) return $fallback;
        return strtoupper( (string) ( $lines[0]['currency'] ?? $fallback ) );
    }

    protected static function is_valid_product_id( int $product_id ): bool {
        return ( $product_id > 0 && get_post_type( $product_id ) === 'alox_product' && get_post_status( $product_id ) === 'publish' );
    }

    /** Handle checkout/customer — create or update customer + save billing & shipping info */
    public static function checkout_customer( \WP_REST_Request $request ) {
        $body = $request->get_json_params();

        // === BILLING DATA ===
        $billing_first_name = sanitize_text_field( $body['billing_first_name'] ?? '' );
        $billing_last_name  = sanitize_text_field( $body['billing_last_name'] ?? '' );
        $billing_email      = sanitize_email( $body['billing_email'] ?? '' );
        $billing_company    = sanitize_text_field( $body['billing_company'] ?? '' );
        $billing_address_1  = sanitize_text_field( $body['billing_address_1'] ?? '' );
        $billing_address_2  = sanitize_text_field( $body['billing_address_2'] ?? '' );
        $billing_postcode   = sanitize_text_field( $body['billing_postcode'] ?? '' );
        $billing_city       = sanitize_text_field( $body['billing_city'] ?? '' );
        $billing_country    = strtoupper( sanitize_text_field( $body['billing_country'] ?? '' ) );
        $billing_phone      = sanitize_text_field( $body['billing_phone'] ?? '' );

        // === SHIPPING DATA (optional — may default to billing) ===
        $shipping_first_name = sanitize_text_field( $body['shipping_first_name'] ?? $billing_first_name );
        $shipping_last_name  = sanitize_text_field( $body['shipping_last_name'] ?? $billing_last_name );
        $shipping_company    = sanitize_text_field( $body['shipping_company'] ?? $billing_company );
        $shipping_address_1  = sanitize_text_field( $body['shipping_address_1'] ?? $billing_address_1 );
        $shipping_address_2  = sanitize_text_field( $body['shipping_address_2'] ?? $billing_address_2 );
        $shipping_postcode   = sanitize_text_field( $body['shipping_postcode'] ?? $billing_postcode );
        $shipping_city       = sanitize_text_field( $body['shipping_city'] ?? $billing_city );
        $shipping_country    = strtoupper( sanitize_text_field( $body['shipping_country'] ?? $billing_country ) );
        $shipping_phone      = sanitize_text_field( $body['shipping_phone'] ?? $billing_phone );

        // === Required billing fields ===
        $required = compact(
            'billing_first_name', 'billing_last_name', 'billing_email',
            'billing_address_1', 'billing_postcode', 'billing_city', 'billing_country', 'billing_phone'
        );
        foreach ( $required as $k => $v ) {
            if ( empty( $v ) ) {
                return new \WP_Error(
                    'alx_missing_fields',
                    __( 'Please complete all required billing fields.', 'aloxstore' ),
                    [ 'status' => 400, 'field' => $k ]
                );
            }
        }

        // === Resolve or create WP user ===
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            $existing = get_user_by( 'email', $billing_email );
            if ( $existing ) {
                $user_id = $existing->ID;
            } else {
                $username = sanitize_user( current( explode( '@', $billing_email ) ), true );
                if ( username_exists( $username ) ) {
                    $username .= '_' . wp_generate_password( 6, false, false );
                }
                $password = wp_generate_password( 20, true, false );
                $user_id  = wp_create_user( $username, $password, $billing_email );
                if ( is_wp_error( $user_id ) ) {
                    return new \WP_Error( 'alx_user_create_failed', __( 'Could not create account.', 'aloxstore' ), [ 'status' => 500 ] );
                }
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id );
            }
        }

        // === Update user profile & billing/shipping meta ===
        update_user_meta( $user_id, 'alx_user_billing_first_name', $billing_first_name );
        update_user_meta( $user_id, 'alx_user_billing_last_name',  $billing_last_name );
        update_user_meta( $user_id, 'alx_user_billing_email',      $billing_email );
        update_user_meta( $user_id, 'alx_user_billing_company',    $billing_company );
        update_user_meta( $user_id, 'alx_user_billing_address_1',  $billing_address_1 );
        update_user_meta( $user_id, 'alx_user_billing_address_2',  $billing_address_2 );
        update_user_meta( $user_id, 'alx_user_billing_postcode',   $billing_postcode );
        update_user_meta( $user_id, 'alx_user_billing_city',       $billing_city );
        update_user_meta( $user_id, 'alx_user_billing_country',    $billing_country );
        update_user_meta( $user_id, 'alx_user_billing_phone',      $billing_phone );

        update_user_meta( $user_id, 'alx_user_shipping_first_name', $shipping_first_name );
        update_user_meta( $user_id, 'alx_user_shipping_last_name',  $shipping_last_name );
        update_user_meta( $user_id, 'alx_user_shipping_company',    $shipping_company );
        update_user_meta( $user_id, 'alx_user_shipping_address_1',  $shipping_address_1 );
        update_user_meta( $user_id, 'alx_user_shipping_address_2',  $shipping_address_2 );
        update_user_meta( $user_id, 'alx_user_shipping_postcode',   $shipping_postcode );
        update_user_meta( $user_id, 'alx_user_shipping_city',       $shipping_city );
        update_user_meta( $user_id, 'alx_user_shipping_country',    $shipping_country );
        update_user_meta( $user_id, 'alx_user_shipping_phone',      $shipping_phone );

        // === Stripe Customer sync ===
        if ( ! class_exists( '\Stripe\Stripe' ) ) {
            $stripe_init = ALOXSTORE_PATH . 'lib/stripe-php/init.php';
            if ( file_exists( $stripe_init ) ) {
                require_once $stripe_init;
            }
        }

        $mode = get_option( 'alx_mode', 'test' );
        $sk   = ( $mode === 'live' ) ? get_option( 'alx_stripe_live_sk', '' ) : get_option( 'alx_stripe_test_sk', '' );

        if ( $sk ) {
            try {
                \Stripe\Stripe::setApiKey( $sk );

                $billing_address = [
                    'line1'       => $billing_address_1,
                    'line2'       => $billing_address_2 ?: null,
                    'postal_code' => $billing_postcode,
                    'city'        => $billing_city,
                    'country'     => $billing_country ?: null,
                ];

                $shipping_address = [
                    'line1'       => $shipping_address_1,
                    'line2'       => $shipping_address_2 ?: null,
                    'postal_code' => $shipping_postcode,
                    'city'        => $shipping_city,
                    'country'     => $shipping_country ?: null,
                ];

                $shipping = [
                    'name'    => trim( $shipping_first_name . ' ' . $shipping_last_name ),
                    'phone'   => $shipping_phone ?: null,
                    'address' => $shipping_address,
                ];

                $cust_id = (string) get_user_meta( $user_id, 'alx_user_stripe_customer_id', true );

                if ( $cust_id ) {
                    \Stripe\Customer::update( $cust_id, array_filter( [
                        'email'    => $billing_email,
                        'name'     => trim( $billing_first_name . ' ' . $billing_last_name ),
                        'phone'    => $billing_phone ?: null,
                        'address'  => $billing_address,
                        'shipping' => $shipping,
                    ] ) );
                } else {
                    $created = \Stripe\Customer::create( array_filter( [
                        'email'    => $billing_email,
                        'name'     => trim( $billing_first_name . ' ' . $billing_last_name ),
                        'phone'    => $billing_phone ?: null,
                        'address'  => $billing_address,
                        'shipping' => $shipping,
                    ] ) );
                    if ( ! empty( $created->id ) ) {
                        $cust_id = $created->id;
                        update_user_meta( $user_id, 'alx_user_stripe_customer_id', $cust_id );
                    }
                }

                // === Persist on cart ===
                $cart = \AloxStore\Cart\Cart::get_cart();
                $cart['customer'] = [
                    'user_id'               => (int) $user_id,
                    'stripe_customer_id'    => $cust_id ?: '',
                    'billing' => [
                        'first_name' => $billing_first_name,
                        'last_name'  => $billing_last_name,
                        'email'      => $billing_email,
                        'company'    => $billing_company,
                        'address_1'  => $billing_address_1,
                        'address_2'  => $billing_address_2,
                        'postcode'   => $billing_postcode,
                        'city'       => $billing_city,
                        'country'    => $billing_country,
                        'phone'      => $billing_phone,
                    ],
                    'shipping' => [
                        'first_name' => $shipping_first_name,
                        'last_name'  => $shipping_last_name,
                        'company'    => $shipping_company,
                        'address_1'  => $shipping_address_1,
                        'address_2'  => $shipping_address_2,
                        'postcode'   => $shipping_postcode,
                        'city'       => $shipping_city,
                        'country'    => $shipping_country,
                        'phone'      => $shipping_phone,
                    ],
                ];
                \AloxStore\Cart\Cart::save_cart( $cart );

            } catch ( \Exception $e ) {
                // Gracefully ignore Stripe customer sync issues
                // error_log( 'Stripe customer error: ' . $e->getMessage() );
            }
        }

        return rest_ensure_response( [ 'ok' => true, 'user_id' => (int) $user_id ] );
    }

}