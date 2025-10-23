<?php
namespace AloxStore\Shortcodes;

use AloxStore\Core\Helpers;
use AloxStore\Cart\Cart;

if ( ! defined( 'ABSPATH' ) ) exit;

class CartShortcode {
    public static function register() {
        add_shortcode( 'aloxstore_cart', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts = [] ) {
        wp_enqueue_style( 'aloxstore' );
        wp_enqueue_script( 'aloxstore' );
        $cart = \AloxStore\REST\Routes::enrich_cart( Cart::get_cart() ); // reuse the enrich logic
        return Helpers::render_template( 'cart.php', [ 'cart' => $cart ] );
    }
}