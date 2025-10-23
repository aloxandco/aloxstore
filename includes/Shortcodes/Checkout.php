<?php
namespace AloxStore\Shortcodes;

use AloxStore\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

class CheckoutShortcode {
    public static function register() {
        add_shortcode( 'aloxstore_checkout', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts = [] ) {
        wp_enqueue_style( 'aloxstore' );
        wp_enqueue_script( 'aloxstore' );

        $user_id = get_current_user_id();
        $defaults = [
            'first_name'  => '',
            'last_name'   => '',
            'email'       => '',
            'company'     => '',
            'address_1'   => '',
            'address_2'   => '',
            'postcode'    => '',
            'city'        => '',
            'country'     => '',
            'telephone'   => '',
        ];

        if ( $user_id ) {
            $user = get_userdata( $user_id );
            $defaults['first_name'] = (string) get_user_meta( $user_id, 'first_name', true );
            $defaults['last_name']  = (string) get_user_meta( $user_id, 'last_name', true );
            $defaults['email']      = $user ? (string) $user->user_email : '';
            $defaults['company']    = (string) get_user_meta( $user_id, 'alx_billing_company', true );
            $defaults['address_1']  = (string) get_user_meta( $user_id, 'alx_billing_address_1', true );
            $defaults['address_2']  = (string) get_user_meta( $user_id, 'alx_billing_address_2', true );
            $defaults['postcode']   = (string) get_user_meta( $user_id, 'alx_billing_postcode', true );
            $defaults['city']       = (string) get_user_meta( $user_id, 'alx_billing_city', true );
            $defaults['country']    = (string) get_user_meta( $user_id, 'alx_billing_country', true );
            $defaults['telephone']  = (string) get_user_meta( $user_id, 'alx_billing_phone', true );
        }

        return Helpers::render_template( 'checkout.php', [ 'defaults' => $defaults ] );
    }
}
