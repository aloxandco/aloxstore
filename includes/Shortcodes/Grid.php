<?php
namespace AloxStore\Shortcodes;

use WP_Query;
use AloxStore\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

class Grid {

    public static function register() {
        add_shortcode( 'aloxstore_grid', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts = [] ) {
        wp_enqueue_style( 'aloxstore' );
        wp_enqueue_script( 'aloxstore' );

        // Attributes: columns, per_page, orderby, order, cat
        $atts = shortcode_atts([
            'columns'   => 4,
            'per_page'  => -1,
            'orderby'   => 'date',   // date|title|price
            'order'     => 'DESC',   // ASC|DESC
            'cat'       => '',       // alox_product_cat slug
        ], $atts, 'aloxstore_grid' );

        // Sanitize
        $columns  = max( 1, min( 6, (int) $atts['columns'] ) );
        $per_page = (int) $atts['per_page'];
        $order    = strtoupper( $atts['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $orderby  = in_array( $atts['orderby'], [ 'date', 'title', 'price' ], true ) ? $atts['orderby'] : 'date';
        $cat_slug = sanitize_title( $atts['cat'] );

        // Base query
        $args = [
            'post_type'      => 'alox_product',
            'posts_per_page' => $per_page,
            'post_status'    => 'publish',
            'order'          => $order,
        ];

        if ( $orderby === 'price' ) {
            $args['orderby']  = 'meta_value_num';
            $args['meta_key'] = 'price_cents';
        } else {
            $args['orderby']  = $orderby;
        }

        if ( ! empty( $cat_slug ) ) {
            $args['tax_query'] = [[
                'taxonomy' => 'alox_product_cat',
                'field'    => 'slug',
                'terms'    => $cat_slug,
            ]];
        }

        $q = new WP_Query( $args );

        // Pass variables to template
        $vars = [
            'q'        => $q,
            'columns'  => $columns,
        ];

        $html = Helpers::render_template( 'grid.php', $vars );
        wp_reset_postdata();

        return $html;
    }
}

// Bootstrap this shortcode from main plugin file:
# add_action( 'init', [ '\AloxStore\Shortcodes\Grid', 'register' ] );
