<?php
namespace AloxStore\Frontend;

use AloxStore\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

class Templates {

    public static function hooks() {
        // Single product template override
        add_filter( 'single_template', [ __CLASS__, 'single_product_template' ] );

        // Enqueue CSS/JS
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Custom checkout success route
        add_action( 'init', [ __CLASS__, 'add_success_rewrite' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_success_query_var' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_success_template' ] );
    }

    public static function single_product_template( $template ) {
        if ( is_singular( 'alox_product' ) ) {
            $located = Helpers::locate_template( 'single-product.php' );
            if ( file_exists( $located ) ) {
                return $located;
            }
        }
        return $template;
    }

    public static function enqueue_assets() {
        if ( is_singular( 'alox_product' ) ) {
            wp_enqueue_style( 'aloxstore' );
            wp_enqueue_script( 'aloxstore' );
        }
    }

    public static function add_success_rewrite() {
        add_rewrite_rule( '^checkout/success/?$', 'index.php?aloxstore_checkout_success=1', 'top' );
    }

    public static function add_success_query_var( $vars ) {
        $vars[] = 'aloxstore_checkout_success';
        return $vars;
    }

    public static function handle_success_template() {
        if ( get_query_var( 'aloxstore_checkout_success' ) ) {
            $template = Helpers::locate_template( 'checkout-success.php' );
            if ( file_exists( $template ) ) {
                include $template;
            } else {
                status_header( 200 );
                echo '<div class="container py-5"><h1>Thank you!</h1><p>Your order was successful.</p></div>';
            }
            exit;
        }
    }
}
