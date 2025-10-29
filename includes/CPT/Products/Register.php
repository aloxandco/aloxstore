<?php
namespace AloxStore\CPT\Products;

if (!defined('ABSPATH')) exit;

/**
 * Registers the `alox_product` post type and its taxonomies.
 *
 * Responsibility: public-facing model definition only.
 * No meta, no UI, no admin columns here.
 */
class Register {

    public static function init() {
        add_action('init', [__CLASS__, 'register']);
    }

    /**
     * CPT + taxonomies
     */
    public static function register() {

        register_post_type('alox_product', [
            'labels' => [
                'name'          => __('Alox Products', 'aloxstore'),
                'singular_name' => __('Alox Product', 'aloxstore'),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'    => 'dashicons-cart',
            'rewrite'      => ['slug' => 'product'],
        ]);

        register_taxonomy('alox_product_cat', 'alox_product', [
            'label'             => __('Product Categories', 'aloxstore'),
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ]);

        register_taxonomy('alox_product_tag', 'alox_product', [
            'label'             => __('Product Tags', 'aloxstore'),
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ]);
    }
}

// Bootstrap this module.
Register::init();
