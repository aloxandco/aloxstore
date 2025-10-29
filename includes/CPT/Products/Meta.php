<?php
namespace AloxStore\CPT\Products;

if (!defined('ABSPATH')) exit;

use AloxStore\Tax\Vat;

/**
 * Registers all product-level meta fields with WordPress.
 *
 * Responsibility: data schema only.
 *  - register_post_meta
 *  - defaults
 *  - sanitization / validation
 *
 * No UI logic here. UI is handled in MetaBox.php.
 *
 * NOTE: All fields are single-value meta and exposed via REST so that
 * we can build headless admin screens later if we want to.
 */
class Meta {

    public static function init() {
        add_action('init', [__CLASS__, 'register_product_meta']);
    }

    public static function register_product_meta() {

        $post_type  = 'alox_product';

        $currencies = apply_filters('aloxstore_currencies', ['EUR', 'USD', 'GBP']);

        $vat_country = get_option('alx_vat_country', 'FR');
        $vat_rates   = Vat::get_available_rates($vat_country);

        $common = [
            'object_subtype' => $post_type,
            'single'         => true,
            'show_in_rest'   => true,
            'auth_callback'  => function () {
                return current_user_can('edit_posts');
            },
        ];

        //
        // 0. IMAGES
        //

        register_post_meta('alox_product', 'gallery_images', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
        ]);

        //
        // 1. CORE COMMERCIAL FIELDS
        //

        // SKU
        register_post_meta($post_type, 'sku', $common + [
                'type'              => 'string',
                'description'       => __('Stock keeping unit', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ]);

        // Base Price (cents)
        register_post_meta($post_type, 'price_cents', $common + [
                'type'              => 'integer',
                'description'       => __('Base price in minor units (cents)', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        // Currency (ISO 4217)
        register_post_meta($post_type, 'currency', $common + [
                'type'               => 'string',
                'description'        => __('Currency (ISO 4217)', 'aloxstore'),
                'default'            => apply_filters('aloxstore_default_currency', 'EUR'),
                'validate_callback'  => function ($val) use ($currencies) {
                    return in_array(strtoupper($val), $currencies, true);
                },
                'sanitize_callback'  => function ($val) use ($currencies) {
                    $val = strtoupper(sanitize_text_field($val));
                    return in_array($val, $currencies, true)
                        ? $val
                        : apply_filters('aloxstore_default_currency', 'EUR');
                },
            ]);

        // VAT Rate (% as float)
        register_post_meta($post_type, 'vat_rate_percent', $common + [
                'type'              => 'number',
                'description'       => __('VAT rate (percentage, e.g. 21.0, 9.0, 0.0)', 'aloxstore'),
                'default'           => 0.0,
                'sanitize_callback' => function ($v) use ($vat_rates) {
                    $val = (float)$v;
                    return in_array($val, $vat_rates, true) ? $val : 0.0;
                },
            ]);

        //
        // 2. SALE / PROMOTION
        //

        // Sale price (cents)
        register_post_meta($post_type, 'sale_price_cents', $common + [
                'type'              => 'integer',
                'description'       => __('Sale price in minor units (cents). If >0 and active, overrides base price.', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        // Sale start datetime (ISO8601)
        register_post_meta($post_type, 'sale_start', $common + [
                'type'              => 'string',
                'description'       => __('Sale start (ISO8601 datetime, store in site timezone)', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    $v = sanitize_text_field($v);
                    // basic sanity: allow empty or ISO-like
                    // dev note: we are not enforcing strict datetime parsing here to avoid false negatives in admin
                    return $v;
                },
            ]);

        // Sale end datetime (ISO8601)
        register_post_meta($post_type, 'sale_end', $common + [
                'type'              => 'string',
                'description'       => __('Sale end (ISO8601 datetime, store in site timezone)', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    $v = sanitize_text_field($v);
                    return $v;
                },
            ]);

        //
        // 3. PRODUCT TYPE / LOGIC
        //

        // Product type: simple, service, digital, bundle, subscription
        register_post_meta($post_type, 'product_type', $common + [
                'type'              => 'string',
                'description'       => __('Product type (simple, service, digital, bundle, subscription)', 'aloxstore'),
                'default'           => 'simple',
                'sanitize_callback' => function ($v) {
                    $allowed = ['simple', 'service', 'digital', 'bundle', 'subscription'];
                    $v = sanitize_text_field($v);
                    return in_array($v, $allowed, true) ? $v : 'simple';
                },
            ]);

        // Requires shipping (physical fulfillment required)
        register_post_meta($post_type, 'requires_shipping', $common + [
                'type'              => 'boolean',
                'description'       => __('Whether the product requires shipping', 'aloxstore'),
                'default'           => true,
                'sanitize_callback' => function ($v) {
                    return (bool)$v;
                },
            ]);

        //
        // 4. STOCK / INVENTORY
        //

        // Manage stock (bool)
        register_post_meta($post_type, 'manage_stock', $common + [
                'type'              => 'boolean',
                'description'       => __('Track stock levels for this product', 'aloxstore'),
                'default'           => false,
                'sanitize_callback' => function ($v) {
                    return (bool)$v;
                },
            ]);

        // Stock quantity (int)
        register_post_meta($post_type, 'stock_qty', $common + [
                'type'              => 'integer',
                'description'       => __('Current stock quantity', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        // Stock status enum
        register_post_meta($post_type, 'stock_status', $common + [
                'type'              => 'string',
                'description'       => __('Stock status', 'aloxstore'),
                'default'           => 'in_stock',
                'sanitize_callback' => function ($v) {
                    $allowed = ['in_stock', 'out_of_stock', 'on_backorder'];
                    $v = sanitize_text_field($v);
                    return in_array($v, $allowed, true) ? $v : 'in_stock';
                },
            ]);

        // Allow backorders
        register_post_meta($post_type, 'backorders_allowed', $common + [
                'type'              => 'boolean',
                'description'       => __('Allow purchases when out of stock', 'aloxstore'),
                'default'           => false,
                'sanitize_callback' => function ($v) {
                    return (bool)$v;
                },
            ]);

        //
        // 5. SHIPPING / LOGISTICS
        //

        // Weight (grams)
        register_post_meta($post_type, 'weight_grams', $common + [
                'type'              => 'integer',
                'description'       => __('Weight in grams', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        // Dimensions (cm)
        register_post_meta($post_type, 'length_cm', $common + [
                'type'              => 'integer',
                'description'       => __('Package length (cm)', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        register_post_meta($post_type, 'width_cm', $common + [
                'type'              => 'integer',
                'description'       => __('Package width (cm)', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        register_post_meta($post_type, 'height_cm', $common + [
                'type'              => 'integer',
                'description'       => __('Package height (cm)', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        // Shipping class / profile
        register_post_meta($post_type, 'shipping_class', $common + [
                'type'              => 'string',
                'description'       => __('Shipping class / tariff bucket (e.g. small-parcel, oversize, digital)', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return sanitize_text_field($v);
                },
            ]);

        //
        // 6. DIGITAL PRODUCT DELIVERY
        //

        // Download URL / file reference
        register_post_meta($post_type, 'download_url', $common + [
                'type'              => 'string',
                'description'       => __('Downloadable file URL or internal reference', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return esc_url_raw($v);
                },
            ]);

        // Max number of downloads allowed per order
        register_post_meta($post_type, 'download_limit', $common + [
                'type'              => 'integer',
                'description'       => __('Maximum number of downloads allowed per purchase (0 = unlimited)', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        // Download expiry (days after purchase)
        register_post_meta($post_type, 'download_expiry_days', $common + [
                'type'              => 'integer',
                'description'       => __('Download link expiry in days (0 = never expires)', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        //
        // 7. MARKETING / MERCHANDISING
        //

        // Subtitle / tagline under title
        register_post_meta($post_type, 'subtitle', $common + [
                'type'              => 'string',
                'description'       => __('Short subtitle / tagline for product cards', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return sanitize_text_field($v);
                },
            ]);

        // Badge text (ex: "NEW", "Bestseller", "Limited")
        register_post_meta($post_type, 'badge_text', $common + [
                'type'              => 'string',
                'description'       => __('Small badge/label to highlight this product', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return sanitize_text_field($v);
                },
            ]);

        // Short description (for cards / category grids)
        register_post_meta($post_type, 'short_description', $common + [
                'type'              => 'string',
                'description'       => __('Short marketing description for list/grid views', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    // allow a bit more than plain text but still keep it safe
                    return wp_kses_post($v);
                },
            ]);

        // Related products (CSV of post IDs or stored as JSON string)
        register_post_meta($post_type, 'related_products', $common + [
                'type'              => 'string',
                'description'       => __('Comma-separated product IDs that are "related"', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    // normalize to comma-separated positive ints
                    $ids = array_filter(array_map('absint', explode(',', $v)));
                    return implode(',', $ids);
                },
            ]);

        // Upsell products (CSV of post IDs)
        register_post_meta($post_type, 'upsell_products', $common + [
                'type'              => 'string',
                'description'       => __('Comma-separated product IDs to upsell after add-to-cart', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    $ids = array_filter(array_map('absint', explode(',', $v)));
                    return implode(',', $ids);
                },
            ]);

        //
        // 8. SEO
        //

        register_post_meta($post_type, 'meta_title', $common + [
                'type'              => 'string',
                'description'       => __('Custom meta title (SEO)', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return sanitize_text_field($v);
                },
            ]);

        register_post_meta($post_type, 'meta_description', $common + [
                'type'              => 'string',
                'description'       => __('Custom meta description (SEO)', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return sanitize_text_field($v);
                },
            ]);

        register_post_meta($post_type, 'canonical_url', $common + [
                'type'              => 'string',
                'description'       => __('Canonical URL override for SEO', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return esc_url_raw($v);
                },
            ]);

        //
        // 9. SUPPLIER / INTERNAL
        //

        register_post_meta($post_type, 'cost_price_cents', $common + [
                'type'              => 'integer',
                'description'       => __('Internal: cost price in cents (not shown to customer)', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = is_numeric($v) ? (int)$v : 0;
                    return max(0, $v);
                },
            ]);

        register_post_meta($post_type, 'supplier_name', $common + [
                'type'              => 'string',
                'description'       => __('Internal: supplier name / source', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return sanitize_text_field($v);
                },
            ]);

        register_post_meta($post_type, 'supplier_sku', $common + [
                'type'              => 'string',
                'description'       => __('Internal: supplier SKU / reference', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return sanitize_text_field($v);
                },
            ]);

        register_post_meta($post_type, 'supplier_link', $common + [
                'type'              => 'string',
                'description'       => __('Internal: supplier/product URL for reordering', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    return esc_url_raw($v);
                },
            ]);

        //
        // 10. VARIATIONS (future)
        // We’ll register them now so front end / API consumers can already rely on the keys,
        // even if UI comes later.
        //

        // Whether this product has variations (parent product)
        register_post_meta($post_type, 'product_has_variations', $common + [
                'type'              => 'boolean',
                'description'       => __('If true, this product is a parent with selectable variations', 'aloxstore'),
                'default'           => false,
                'sanitize_callback' => function ($v) {
                    return (bool)$v;
                },
            ]);

        // Variation attributes schema (JSON string)
        // Example: {"color":["Red","Blue"],"size":["S","M","L"]}
        register_post_meta($post_type, 'variation_attributes', $common + [
                'type'              => 'string',
                'description'       => __('JSON definition of variation attributes/options', 'aloxstore'),
                'default'           => '',
                'sanitize_callback' => function ($v) {
                    // We accept JSON or empty. We do not deep-validate yet.
                    $v = wp_unslash($v);
                    if ($v === '' || $v === '{}') {
                        return '';
                    }
                    // basic safety
                    $decoded = json_decode($v, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // store normalized
                        return wp_json_encode($decoded);
                    }
                    // reject invalid JSON
                    return '';
                },
            ]);

        // Variation parent (for child variation products)
        register_post_meta($post_type, 'variation_parent', $common + [
                'type'              => 'integer',
                'description'       => __('Parent product ID if this is a variation SKU', 'aloxstore'),
                'default'           => 0,
                'sanitize_callback' => function ($v) {
                    $v = absint($v);
                    return $v > 0 ? $v : 0;
                },
            ]);
    }
}

// Bootstrap
Meta::init();
