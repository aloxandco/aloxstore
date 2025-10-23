<?php
namespace AloxStore\Cart;

if ( ! defined( 'ABSPATH' ) ) exit;

class Cart {
    const COOKIE = 'alx_cart';
    const EXPIRY = 60 * 60 * 24 * 7; // 7 days

    /**
     * Get the current cart (creates a new one + token if absent).
     */
    public static function get_cart(): array {
        $token = self::ensure_token();
        $cart  = get_transient( self::tkey( $token ) );
        if ( ! is_array( $cart ) ) {
            $cart = [ 'items' => [], 'coupon' => null ];
            set_transient( self::tkey( $token ), $cart, self::EXPIRY );
        }
        return $cart;
    }

    /**
     * Persist the cart; will ensure a token exists if missing.
     */
    public static function save_cart( array $cart ): void {
        $token = self::ensure_token();

        if ( empty( $cart['id'] ) ) {
            $cart['id'] = sanitize_key( $token );
        }

        set_transient( self::tkey( $token ), $cart, self::EXPIRY );
    }

    /**
     * Add a product to the cart (increments qty if present).
     */
    public static function add( $product_id, $qty = 1 ): array {
        $pid  = (int) $product_id;
        $qty  = max( 1, (int) $qty );
        $cart = self::get_cart();

        $found = false;
        foreach ( $cart['items'] as &$it ) {
            if ( (int) $it['product_id'] === $pid ) {
                $it['qty'] = max( 1, (int) $it['qty'] + $qty );
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            $cart['items'][] = [ 'product_id' => $pid, 'qty' => $qty ];
        }

        self::save_cart( $cart );
        return $cart;
    }

    /**
     * Set quantity for a product (0 removes it).
     */
    public static function set_qty( $product_id, $qty ): array {
        $pid  = (int) $product_id;
        $qty  = (int) $qty;
        $cart = self::get_cart();

        foreach ( $cart['items'] as $k => &$it ) {
            if ( (int) $it['product_id'] === $pid ) {
                if ( $qty <= 0 ) {
                    unset( $cart['items'][ $k ] );
                } else {
                    $it['qty'] = $qty;
                }
                break;
            }
        }

        // Re-index array to keep it tidy if an item was removed.
        if ( ! empty( $cart['items'] ) ) {
            $cart['items'] = array_values( $cart['items'] );
        }

        self::save_cart( $cart );
        return $cart;
    }

    /**
     * Remove a product from the cart.
     */
    public static function remove( $product_id ): array {
        return self::set_qty( $product_id, 0 );
    }

    /**
     * Clear the cart (keeps the token so the flow continues smoothly).
     */
    public static function clear(): array {
        $token = self::ensure_token();
        $cart  = [ 'items' => [], 'coupon' => null ];
        set_transient( self::tkey( $token ), $cart, self::EXPIRY );
        return $cart;
    }

    /* ===== Internals ===== */

    /**
     * Ensure we have a cart token cookie; create and seed an empty cart if missing.
     */
    protected static function ensure_token(): string {
        $token = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( (string) $_COOKIE[ self::COOKIE ] ) : '';

        if ( $token ) {
            return $token;
        }

        $token = wp_generate_uuid4();

        // Set for both COOKIEPATH and SITECOOKIEPATH (mirrors WP core patterns).
        $expire = time() + self::EXPIRY;
        $secure = is_ssl();
        $httponly = true;

        @setcookie( self::COOKIE, $token, $expire, ( defined('COOKIEPATH') && COOKIEPATH ) ? COOKIEPATH : '/', COOKIE_DOMAIN, $secure, $httponly );
        @setcookie( self::COOKIE, $token, $expire, ( defined('SITECOOKIEPATH') && SITECOOKIEPATH ) ? SITECOOKIEPATH : '/', COOKIE_DOMAIN, $secure, $httponly );

        // Seed an empty cart initially.
        set_transient( self::tkey( $token ), [ 'items' => [], 'coupon' => null ], self::EXPIRY );

        return $token;
    }

    /**
     * Build the transient key for a token.
     */
    protected static function tkey( string $token ): string {
        return 'alx_cart_' . $token;
    }
}
