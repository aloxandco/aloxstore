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
    public static function perm_public_nonce( WP_REST_Request $request ): bool {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
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
     // Return canonical cart (Cart::save_cart ensures pricing & totals are present).
        $lines           = [];
        $defaultCurrency = strtoupper( apply_filters( 'aloxstore_default_currency', 'EUR' ) );
        $subtotal_cents  = 0;

        foreach ( $cart['items'] as $row ) {
            $pid = (int) ( $row['product_id'] ?? 0 );
            $qty = max( 1, (int) ( $row['qty'] ?? 1 ) );
            if ( ! $pid ) continue;

            if ( get_post_type( $pid ) !== 'alox_product' || 'publish' !== get_post_status( $pid ) ) {
                continue;
            }

            $title     = get_the_title( $pid );
            $unit      = (int) get_post_meta( $pid, 'price_cents', true );
            $item_cur  = get_post_meta( $pid, 'currency', true );
            $cur       = $item_cur ? strtoupper( $item_cur ) : $defaultCurrency;

            $line_total = max( 0, $unit ) * $qty;

            $lines[] = [
                'product_id' => $pid,
                'title'      => (string) $title,
                'qty'        => $qty,
                'unit_cents' => max( 0, (int) $unit ),
                'currency'   => $cur,
                'line_cents' => max( 0, (int) $line_total ),
            ];

            $subtotal_cents += max( 0, (int) $line_total );
        }

        $shipping = (int)get_option('alx_flat_rate_cents', 0);
        $free_min = (int)get_option('alx_free_shipping_min_cents', 0);
        if ($free_min > 0 && $subtotal_cents >= $free_min) {
            $shipping = 0;
        }
        $tax = 0;
        if (get_option('alx_prices_include_tax', true)) {
            $tax = 0;
        }

        $cart['lines']          = $lines;
        $cart['currency']       = self::pick_cart_currency( $lines, $defaultCurrency );
        $cart['subtotal_cents'] = $subtotal_cents;
        $cart['discount_cents'] = 0;
        $cart['shipping_cents'] = $shipping;
        $cart['tax_cents']      = $tax;
        $cart['total_cents']    = $subtotal_cents + $shipping + $tax;

        return $cart;
    }

    protected static function pick_cart_currency( array $lines, string $fallback ): string {
        if ( empty( $lines ) ) return $fallback;
        return strtoupper( (string) ( $lines[0]['currency'] ?? $fallback ) );
    }

    protected static function is_valid_product_id( int $product_id ): bool {
        return ( $product_id > 0 && get_post_type( $product_id ) === 'alox_product' && get_post_status( $product_id ) === 'publish' );
    }

    /** Add the handler method for checkout/customer*/
    public static function checkout_customer( \WP_REST_Request $request ) {
        $body = $request->get_json_params();

        $first_name = sanitize_text_field( $body['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $body['last_name'] ?? '' );
        $email      = sanitize_email( $body['email'] ?? '' );
        $company    = sanitize_text_field( $body['company'] ?? '' );
        $address_1  = sanitize_text_field( $body['address_1'] ?? '' );
        $address_2  = sanitize_text_field( $body['address_2'] ?? '' );
        $postcode   = sanitize_text_field( $body['postcode'] ?? '' );
        $city       = sanitize_text_field( $body['city'] ?? '' );
        $country    = strtoupper( sanitize_text_field( $body['country'] ?? '' ) );
        $telephone  = sanitize_text_field( $body['telephone'] ?? '' );

        // Required fields
        $required = compact( 'first_name','last_name','email','address_1','postcode','city','country','telephone' );
        foreach ( $required as $k => $v ) {
            if ( empty( $v ) ) {
                return new \WP_Error( 'alx_missing_fields', __( 'Please complete all required fields.', 'aloxstore' ), [ 'status' => 400, 'field' => $k ] );
            }
        }

        // Resolve/create WP user
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            $existing = get_user_by( 'email', $email );
            if ( $existing ) {
                $user_id = $existing->ID;
            } else {
                $username = sanitize_user( current( explode( '@', $email ) ), true );
                if ( username_exists( $username ) ) {
                    $username .= '_' . wp_generate_password( 6, false, false );
                }
                $password = wp_generate_password( 20, true, false );
                $user_id  = wp_create_user( $username, $password, $email );
                if ( is_wp_error( $user_id ) ) {
                    return new \WP_Error( 'alx_user_create_failed', __( 'Could not create account.', 'aloxstore' ), [ 'status' => 500 ] );
                }
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id );
            }
        }

        // Save profile + billing meta
        update_user_meta( $user_id, 'first_name', $first_name );
        update_user_meta( $user_id, 'last_name',  $last_name );
        $user = get_userdata( $user_id );
        if ( $user && strtolower( $user->user_email ) !== strtolower( $email ) ) {
            wp_update_user( [ 'ID' => $user_id, 'user_email' => $email ] );
        }

        update_user_meta( $user_id, 'alx_billing_company',   $company );
        update_user_meta( $user_id, 'alx_billing_address_1', $address_1 );
        update_user_meta( $user_id, 'alx_billing_address_2', $address_2 );
        update_user_meta( $user_id, 'alx_billing_postcode',  $postcode );
        update_user_meta( $user_id, 'alx_billing_city',      $city );
        update_user_meta( $user_id, 'alx_billing_country',   $country );
        update_user_meta( $user_id, 'alx_billing_phone',     $telephone );

        // --- Create/Update Stripe Customer so Checkout is prefilled ---
        // Load Stripe lib if needed
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

                $address = [
                    'line1'       => $address_1,
                    'line2'       => $address_2 ?: null,
                    'postal_code' => $postcode,
                    'city'        => $city,
                    'country'     => $country ?: null,
                ];

                $shipping = [
                    'name'    => trim( $first_name . ' ' . $last_name ),
                    'phone'   => $telephone ?: null,
                    'address' => $address,
                ];

                $cust_id = (string) get_user_meta( $user_id, 'alx_stripe_customer_id', true );

                if ( $cust_id ) {
                    // Update existing customer
                    \Stripe\Customer::update( $cust_id, array_filter( [
                        'email'    => $email,
                        'name'     => trim( $first_name . ' ' . $last_name ),
                        'phone'    => $telephone ?: null,
                        'address'  => $address,
                        'shipping' => $shipping,
                    ] ) );
                } else {
                    // Create new customer
                    $created = \Stripe\Customer::create( array_filter( [
                        'email'    => $email,
                        'name'     => trim( $first_name . ' ' . $last_name ),
                        'phone'    => $telephone ?: null,
                        'address'  => $address,
                        'shipping' => $shipping,
                    ] ) );
                    if ( ! empty( $created->id ) ) {
                        $cust_id = $created->id;
                        update_user_meta( $user_id, 'alx_stripe_customer_id', $cust_id );
                    }
                }

                // Persist on cart
                $cart = \AloxStore\Cart\Cart::get_cart();
                $cart['customer'] = [
                    'user_id'            => (int) $user_id,
                    'stripe_customer_id' => $cust_id ?: '',
                    'first_name'         => $first_name,
                    'last_name'          => $last_name,
                    'email'              => $email,
                    'company'            => $company,
                    'address_1'          => $address_1,
                    'address_2'          => $address_2,
                    'postcode'           => $postcode,
                    'city'               => $city,
                    'country'            => $country,
                    'telephone'          => $telephone,
                ];
                \AloxStore\Cart\Cart::save_cart( $cart );

            } catch ( \Exception $e ) {
                // Don’t hard-fail checkout on customer creation issues; just continue without Stripe customer
                // You can log this if needed:
                // error_log( 'Stripe customer error: ' . $e->getMessage() );
            }
        }

        return rest_ensure_response( [ 'ok' => true, 'user_id' => (int) $user_id ] );
    }

}
