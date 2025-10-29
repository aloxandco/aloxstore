<?php
namespace AloxStore\CPT\Products;

if (!defined('ABSPATH')) exit;

/**
 * Admin list table enhancements for `alox_product`.
 *
 * Responsibility:
 *  - Add custom columns (SKU, Price, VAT)
 *  - Make Price sortable
 *  - Handle sort requests
 */
class AdminColumns {

    public static function init() {
        add_filter('manage_edit-alox_product_posts_columns', [__CLASS__, 'product_columns']);
        add_action('manage_alox_product_posts_custom_column', [__CLASS__, 'product_columns_content'], 10, 2);
        add_filter('manage_edit-alox_product_sortable_columns', [__CLASS__, 'product_sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'handle_price_sorting']);
    }

    public static function product_columns($cols) {
        $new = [];
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['alx_sku']   = __('SKU', 'aloxstore');
                $new['alx_price'] = __('Price', 'aloxstore');
                $new['alx_vat']   = __('VAT %', 'aloxstore');
            }
        }
        return $new;
    }

    public static function product_columns_content($column, $post_id) {
        switch ($column) {
            case 'alx_sku':
                echo esc_html((string)get_post_meta($post_id, 'sku', true));
                break;

            case 'alx_price':
                $cents    = (int)get_post_meta($post_id, 'price_cents', true);
                $currency = get_post_meta($post_id, 'currency', true);
                $currency = $currency ? $currency : apply_filters('aloxstore_default_currency', 'EUR');
                $formatted = number_format_i18n($cents / 100, 2);
                echo esc_html("{$currency} {$formatted}");
                break;

            case 'alx_vat':
                $rate = (float)get_post_meta($post_id, 'vat_rate_percent', true);
                echo esc_html($rate . '%');
                break;
        }
    }

    public static function product_sortable_columns($columns) {
        $columns['alx_price'] = 'alx_price';
        return $columns;
    }

    public static function handle_price_sorting($query) {
        if (is_admin()
            && $query->is_main_query()
            && 'alox_product' === ($query->get('post_type') ?? '')
        ) {
            if ('alx_price' === $query->get('orderby')) {
                $query->set('meta_key', 'price_cents');
                $query->set('orderby', 'meta_value_num');
            }
        }
    }
}

// Bootstrap
AdminColumns::init();
