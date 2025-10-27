<?php
namespace AloxStore\Admin;

if (!defined('ABSPATH')) exit;

class UserProfile {

    public static function init() {
        add_action('show_user_profile', [__CLASS__, 'render_fields']); // For own profile
        add_action('edit_user_profile', [__CLASS__, 'render_fields']); // For admins editing others

        add_action('personal_options_update', [__CLASS__, 'save_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_fields']);
    }

    /**
     * Output our custom billing/shipping fields in the user profile
     */
    public static function render_fields($user) {
        ?>
        <h2><?php esc_html_e('AloxStore Customer Details', 'aloxstore'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th colspan="2"><h3><?php esc_html_e('Billing Address', 'aloxstore'); ?></h3></th>
            </tr>
            <?php self::text($user, 'alx_user_billing_first_name', 'First name'); ?>
            <?php self::text($user, 'alx_user_billing_last_name', 'Last name'); ?>
            <?php self::text($user, 'alx_user_billing_company', 'Company'); ?>
            <?php self::text($user, 'alx_user_billing_address_1', 'Address 1'); ?>
            <?php self::text($user, 'alx_user_billing_address_2', 'Address 2'); ?>
            <?php self::text($user, 'alx_user_billing_postcode', 'Postcode'); ?>
            <?php self::text($user, 'alx_user_billing_city', 'City'); ?>
            <?php self::text($user, 'alx_user_billing_country', 'Country'); ?>
            <?php self::text($user, 'alx_user_billing_phone', 'Telephone'); ?>

            <tr>
                <th colspan="2"><h3><?php esc_html_e('Shipping Address', 'aloxstore'); ?></h3></th>
            </tr>
            <?php self::text($user, 'alx_user_shipping_first_name', 'First name'); ?>
            <?php self::text($user, 'alx_user_shipping_last_name', 'Last name'); ?>
            <?php self::text($user, 'alx_user_shipping_company', 'Company'); ?>
            <?php self::text($user, 'alx_user_shipping_address_1', 'Address 1'); ?>
            <?php self::text($user, 'alx_user_shipping_address_2', 'Address 2'); ?>
            <?php self::text($user, 'alx_user_shipping_postcode', 'Postcode'); ?>
            <?php self::text($user, 'alx_user_shipping_city', 'City'); ?>
            <?php self::text($user, 'alx_user_shipping_country', 'Country'); ?>
            <?php self::text($user, 'alx_user_shipping_phone', 'Telephone'); ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Helper to render one text input field
     */
    protected static function text($user, $key, $label) {
        $value = esc_attr(get_user_meta($user->ID, $key, true));
        printf(
            '<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" name="%1$s" id="%1$s" value="%3$s" class="regular-text" /></td></tr>',
            esc_attr($key),
            esc_html($label),
            $value
        );
    }

    /**
     * Save the custom meta fields
     */
    public static function save_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) return false;

        $fields = [
            // Billing
            'alx_user_billing_first_name', 'alx_user_billing_last_name', 'alx_user_billing_company',
            'alx_user_billing_address_1', 'alx_user_billing_address_2',
            'alx_user_billing_postcode', 'alx_user_billing_city',
            'alx_user_billing_country', 'alx_user_billing_phone',

            // Shipping
            'alx_user_shipping_first_name', 'alx_user_shipping_last_name', 'alx_user_shipping_company',
            'alx_user_shipping_address_1', 'alx_user_shipping_address_2',
            'alx_user_shipping_postcode', 'alx_user_shipping_city',
            'alx_user_shipping_country', 'alx_user_shipping_phone',
        ];

        foreach ($fields as $key) {
            $value = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
            update_user_meta($user_id, $key, $value);
        }
    }
}

\AloxStore\Admin\UserProfile::init();