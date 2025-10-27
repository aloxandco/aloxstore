<?php
/*
Plugin Name: aloxstore
Plugin URI: https://alox.co
Description: Lightweight, Woo-free shop for WordPress. Physical/services only (v0). Stripe Checkout by default, REST API only. EUR/VAT friendly. Flat-rate shipping to start.
Version: 0.1.1
Author: Alox & Co
Author URI: https://alox.co
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: aloxstore
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ALOXSTORE_VERSION', '0.1.1' );
define( 'ALOXSTORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALOXSTORE_URL',  plugin_dir_url( __FILE__ ) );

// --- Load Textdomain ---
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'aloxstore', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// --- Require Core files (order matters: Helpers first) ---
require_once ALOXSTORE_PATH . 'includes/Core/Helpers.php';
require_once ALOXSTORE_PATH . 'includes/Admin/Settings.php';
require_once ALOXSTORE_PATH . 'includes/Admin/UserProfile.php';
require_once ALOXSTORE_PATH . 'includes/CPT/Products.php';
require_once ALOXSTORE_PATH . 'includes/CPT/Orders.php';
require_once ALOXSTORE_PATH . 'includes/CPT/Coupons.php';
require_once ALOXSTORE_PATH . 'includes/Cart/Cart.php';
require_once ALOXSTORE_PATH . 'includes/Cart/CartPricing.php';
require_once ALOXSTORE_PATH . 'includes/Shipping/FlatRate.php';
require_once ALOXSTORE_PATH . 'includes/Tax/Vat.php';
require_once ALOXSTORE_PATH . 'includes/Payments/PaymentGatewayInterface.php';
require_once ALOXSTORE_PATH . 'includes/Payments/StripeGateway.php';
require_once ALOXSTORE_PATH . 'includes/Payments/StripeWebhook.php';
require_once ALOXSTORE_PATH . 'includes/REST/Routes.php';
require_once ALOXSTORE_PATH . 'includes/Filters.php';
require_once ALOXSTORE_PATH . 'includes/Shortcodes/Grid.php';
require_once ALOXSTORE_PATH . 'includes/Shortcodes/Cart.php';
require_once ALOXSTORE_PATH . 'includes/Shortcodes/Checkout.php';

// --- Bootstrap (single source of truth) ---
add_action( 'init', function() {
    \AloxStore\CPT\Products::register();
    \AloxStore\CPT\Orders::register();
    \AloxStore\CPT\Coupons::register();
    \AloxStore\Shortcodes\Grid::register();
    \AloxStore\Shortcodes\CartShortcode::register();
    \AloxStore\Shortcodes\CheckoutShortcode::register();
}, 5 );

require_once ALOXSTORE_PATH . 'includes/Frontend/Templates.php';
\AloxStore\Frontend\Templates::hooks();

// --- Assets ---
add_action( 'wp_enqueue_scripts', function() {
    wp_register_style( 'aloxstore', ALOXSTORE_URL . 'assets/css/aloxstore.css', [], ALOXSTORE_VERSION );
    wp_register_script( 'aloxstore', ALOXSTORE_URL . 'assets/js/app.js', [], ALOXSTORE_VERSION, true );

    // Localize the script:
    wp_localize_script( 'aloxstore', 'aloxstore', [
        'rest'         => esc_url_raw( rest_url( 'aloxstore/v1/' ) ),
        'nonce'        => wp_create_nonce( 'wp_rest' ),
        'cart_url'     => apply_filters( 'aloxstore_cart_url', home_url( '/cart' ) ),
        'checkout_url' => apply_filters( 'aloxstore_checkout_url', home_url( '/checkout' ) ),
    ] );
} );

// --- Settings hooks ---
\AloxStore\Admin\Settings::hooks();

// --- Register REST routes ---
add_action( 'rest_api_init', [ '\AloxStore\REST\Routes', 'register' ] );
