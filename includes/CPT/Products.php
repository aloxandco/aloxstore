<?php
namespace AloxStore\CPT;

if ( ! defined( 'ABSPATH' ) ) exit;

use AloxStore\Tax\Vat;

class Products {
    public static function register() {
        register_post_type( 'alox_product', [
            'labels' => [
                'name'          => __( 'Alox Products', 'aloxstore' ),
                'singular_name' => __( 'Alox Product', 'aloxstore' ),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'menu_icon'    => 'dashicons-cart',
            'rewrite'      => [ 'slug' => 'product' ],
        ] );

        register_taxonomy( 'alox_product_cat', 'alox_product', [
            'label'            => __( 'Product Categories', 'aloxstore' ),
            'hierarchical'     => true,
            'show_in_rest'     => true,
            'show_admin_column'=> true,
        ]);
        register_taxonomy( 'alox_product_tag', 'alox_product', [
            'label'            => __( 'Product Tags', 'aloxstore' ),
            'hierarchical'     => false,
            'show_in_rest'     => true,
            'show_admin_column'=> true,
        ]);
    }
}
//add_action( 'init', [ __NAMESPACE__ . '\\Products', 'register' ] );

/**
 * === META REGISTRATION (single source of truth) ===
 */
add_action( 'init', __NAMESPACE__ . '\\register_product_meta' );
function register_product_meta() {
    $post_type   = 'alox_product';
    $currencies  = apply_filters( 'aloxstore_currencies', [ 'EUR', 'USD', 'GBP' ] );
    // We'll store VAT rate directly as float
    $vat_rates   = Vat::get_available_rates( get_option('alx_vat_country', 'FR') );

    $common = [
        'object_subtype'  => $post_type,
        'single'          => true,
        'show_in_rest'    => true,
        'auth_callback'   => function() { return current_user_can( 'edit_posts' ); },
    ];

    // SKU
    register_post_meta( $post_type, 'sku', $common + [
            'type'              => 'string',
            'description'       => __( 'Stock keeping unit', 'aloxstore' ),
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

    // Price (cents)
    register_post_meta( $post_type, 'price_cents', $common + [
            'type'              => 'integer',
            'description'       => __( 'Price in minor units (cents)', 'aloxstore' ),
            'default'           => 0,
            'sanitize_callback'  => function( $v ) {
                $v = is_numeric( $v ) ? (int) $v : 0;
                return max( 0, $v );
            },
        ]);

    // Currency (ISO)
    register_post_meta( $post_type, 'currency', $common + [
            'type'              => 'string',
            'description'       => __( 'Currency (ISO 4217)', 'aloxstore' ),
            'default'          => apply_filters( 'aloxstore_default_currency', 'EUR' ),
            'validate_callback'  => function( $val ) use ( $currencies ) {
                return in_array( strtoupper($val), $currencies, true );
            },
            'sanitize_callback'  => function( $val ) use ( $currencies ) {
                $val = strtoupper( sanitize_text_field( $val ) );
                return in_array( $val, $currencies, true ) ? $val : apply_filters( 'aloxstore_default_currency', 'EUR' );
            },
        ]);

    // Requires shipping
    register_post_meta( $post_type, 'requires_shipping', $common + [
            'type'              => 'boolean',
            'description'       => __( 'Whether the product requires shipping', 'aloxstore' ),
            'default'           => true,
            'sanitize_callback'  => function( $v ) {
                return (bool) $v;
            },
        ]);

    // Weight (grams)
    register_post_meta( $post_type, 'weight_grams', $common + [
            'type'              => 'integer',
            'description'       => __( 'Weight in grams', 'aloxstore' ),
            'default'           => 0,
            'sanitize_callback'  => function( $v ) {
                $v = is_numeric( $v ) ? (int) $v : 0;
                return max( 0, $v );
            },
        ]);

    // VAT Rate (float)
    register_post_meta( $post_type, 'vat_rate_percent', $common + [
            'type'              => 'number',
            'description'       => __( 'VAT rate (percentage, e.g. 20.0, 10.0, 5.5, 0.0)', 'aloxstore' ),
            'default'           => 0.0,
            'sanitize_callback'  => function( $v ) use ( $vat_rates ) {
                $val = (float) $v;
                return in_array($val, $vat_rates, true) ? $val : 0.0;
            },
        ]);
}

/**
 * === META BOX (editor UI) ===
 */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'aloxstore_product_data',
        __( 'AloxStore – Product Data', 'aloxstore' ),
        __NAMESPACE__ . '\\render_product_meta_box',
        'alox_product',
        'normal',
        'high'
    );
});

function render_product_meta_box( \WP_Post $post ) {
    wp_nonce_field( 'aloxstore_save_product_meta', 'aloxstore_product_meta_nonce' );

    $sku               = get_post_meta( $post->ID, 'sku', true );
    $price_cents       = (int) get_post_meta( $post->ID, 'price_cents', true );
    $currency          = get_post_meta( $post->ID, 'currency', true );
    $requires_shipping = (bool) get_post_meta( $post->ID, 'requires_shipping', true );
    $weight_grams      = (int) get_post_meta( $post->ID, 'weight_grams', true );
    $vat_rate_percent  = (float) get_post_meta( $post->ID, 'vat_rate_percent', true );

    $currencies        = apply_filters( 'aloxstore_currencies', [ 'EUR', 'USD', 'GBP' ] );
    $available_rates   = Vat::get_available_rates( get_option('alx_vat_country', 'FR') );

    if ( empty($currency) ) {
        $currency = apply_filters( 'aloxstore_default_currency', 'EUR' );
    }
    ?>
    <style>
        .aloxstore-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .aloxstore-fields .field { display: flex; flex-direction: column; }
        @media (max-width: 900px){ .aloxstore-fields{ grid-template-columns: 1fr; } }
    </style>

    <div class="aloxstore-fields">
        <div class="field">
            <label for="alx_sku"><strong><?php echo esc_html__( 'SKU', 'aloxstore' ); ?></strong></label>
            <input type="text" id="alx_sku" name="alx_sku" value="<?php echo esc_attr( $sku ); ?>" class="regular-text" />
            <p class="description"><?php echo esc_html__( 'Unique identifier for this product.', 'aloxstore' ); ?></p>
        </div>

        <div class="field">
            <label for="alx_price_cents"><strong><?php echo esc_html__( 'Price (cents)', 'aloxstore' ); ?></strong></label>
            <input type="number" min="0" step="1" id="alx_price_cents" name="alx_price_cents" value="<?php echo esc_attr( $price_cents ); ?>" />
            <p class="description"><?php echo esc_html__( 'Enter price in minor units (e.g., €12.34 → 1234).', 'aloxstore' ); ?></p>
        </div>

        <div class="field">
            <label for="alx_currency"><strong><?php echo esc_html__( 'Currency', 'aloxstore' ); ?></strong></label>
            <select id="alx_currency" name="alx_currency">
                <?php foreach ( $currencies as $code ) : ?>
                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $currency, $code ); ?>><?php echo esc_html( $code ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php echo esc_html__( 'Default comes from AloxStore settings.', 'aloxstore' ); ?></p>
        </div>

        <div class="field">
            <label for="alx_requires_shipping">
                <input type="checkbox" id="alx_requires_shipping" name="alx_requires_shipping" value="1" <?php checked( $requires_shipping, true ); ?> />
                <?php echo esc_html__( 'Requires shipping', 'aloxstore' ); ?>
            </label>
            <p class="description"><?php echo esc_html__( 'Disable for virtual products/services.', 'aloxstore' ); ?></p>
        </div>

        <div class="field">
            <label for="alx_weight_grams"><strong><?php echo esc_html__( 'Weight (grams)', 'aloxstore' ); ?></strong></label>
            <input type="number" min="0" step="1" id="alx_weight_grams" name="alx_weight_grams" value="<?php echo esc_attr( $weight_grams ); ?>" />
            <p class="description"><?php echo esc_html__( 'Used for shipping calculations.', 'aloxstore' ); ?></p>
        </div>

        <div class="field">
            <label for="alx_vat_rate_percent"><strong><?php echo esc_html__( 'VAT Rate (%)', 'aloxstore' ); ?></strong></label>
            <select id="alx_vat_rate_percent" name="alx_vat_rate_percent">
                <?php foreach ($available_rates as $rate): ?>
                    <option value="<?php echo esc_attr($rate); ?>" <?php selected($vat_rate_percent, $rate); ?>>
                        <?php echo esc_html($rate . '%'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php echo esc_html__( 'Select the applicable VAT rate for this product.', 'aloxstore' ); ?></p>
        </div>
    </div><!-- .aloxstore-fields -->

    <?php
}

/**
 * === SAVE ===
 */
add_action( 'save_post_alox_product', __NAMESPACE__ . '\\save_product_meta' );
function save_product_meta( $post_id ) {
    if ( ! isset( $_POST['aloxstore_product_meta_nonce'] ) || ! wp_verify_nonce( $_POST['aloxstore_product_meta_nonce'], 'aloxstore_save_product_meta' ) ) {
        return;
    }
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision($post_id) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // SKU
    if ( isset($_POST['alx_sku']) ) {
        update_post_meta($post_id, 'sku', sanitize_text_field( wp_unslash($_POST['alx_sku']) ));
    }

    // Price (cents)
    if ( isset($_POST['alx_price_cents']) ) {
        $price = (int) wp_unslash($_POST['alx_price_cents']);
        update_post_meta($post_id, 'price_cents', max(0, $price));
    }

    // Currency
    if ( isset($_POST['alx_currency']) ) {
        $currencies = apply_filters('aloxstore_currencies', [ 'EUR', 'USD', 'GBP' ]);
        $val = strtoupper( sanitize_text_field( wp_unslash($_POST['alx_currency']) ) );
        if (in_array($val, $currencies, true)) {
            update_post_meta($post_id, 'currency', $val);
        }
    }

    // Requires shipping
    update_post_meta($post_id, 'requires_shipping', !empty($_POST['alx_requires_shipping']) ? 1 : 0);

    // Weight (grams)
    if ( isset($_POST['alx_weight_grams']) ) {
        $w = (int) wp_unslash($_POST['alx_weight_grams']);
        update_post_meta($post_id, 'weight_grams', max(0, $w));
    }

    // VAT Rate
    if ( isset($_POST['alx_vat_rate_percent']) ) {
        $available_rates = Vat::get_available_rates( get_option('alx_vat_country', 'FR') );
        $val = (float) wp_unslash($_POST['alx_vat_rate_percent']);
        if ( in_array($val, $available_rates, true) ) {
            update_post_meta($post_id, 'vat_rate_percent', $val);
        } else {
            update_post_meta($post_id, 'vat_rate_percent', 0.0);
        }
    }
}

/**
 * === ADMIN COLUMNS ===
 */
add_filter( 'manage_edit-alox_product_posts_columns', __NAMESPACE__ . '\\product_columns' );
add_action( 'manage_alox_product_posts_custom_column', __NAMESPACE__ . '\\product_columns_content', 10, 2 );
add_filter( 'manage_edit-alox_product_sortable_columns', __NAMESPACE__ . '\\product_sortable_columns' );
add_action( 'pre_get_posts', __NAMESPACE__ . '\\handle_price_sorting' );

function product_columns( $cols ) {
    $new = [];
    foreach ( $cols as $key => $label ) {
        $new[$key] = $label;
        if ( $key === 'title' ) {
            $new['alx_sku']   = __( 'SKU', 'aloxstore' );
            $new['alx_price'] = __( 'Price', 'aloxstore' );
            $new['alx_vat']   = __( 'VAT %', 'aloxstore' );
        }
    }
    return $new;
}

function product_columns_content( $column, $post_id ) {
    switch ($column) {
        case 'alx_sku':
            echo esc_html( (string) get_post_meta( $post_id, 'sku', true ) );
            break;
        case 'alx_price':
            $cents    = (int) get_post_meta( $post_id, 'price_cents', true );
            $currency = get_post_meta( $post_id, 'currency', true );
            $currency = $currency ? $currency : apply_filters('aloxstore_default_currency','EUR');
            $formatted = number_format_i18n($cents / 100, 2);
            echo esc_html("{$currency} {$formatted}");
            break;
        case 'alx_vat':
            $rate = (float) get_post_meta( $post_id, 'vat_rate_percent', true );
            echo esc_html( $rate . '%' );
            break;
    }
}

function product_sortable_columns( $columns ) {
    $columns['alx_price'] = 'alx_price';
    return $columns;
}

function handle_price_sorting( $query ) {
    if ( is_admin() && $query->is_main_query() && 'alox_product' === ( $query->get('post_type') ?? '' ) ) {
        if ( 'alx_price' === $query->get('orderby') ) {
            $query->set('meta_key', 'price_cents');
            $query->set('orderby', 'meta_value_num');
        }
    }
}