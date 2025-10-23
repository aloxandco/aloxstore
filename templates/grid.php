<?php
/**
 * AloxStore Grid Template
 * Available vars via $vars:
 *   - $q       (WP_Query)
 *   - $columns (int)
 */
use AloxStore\Core\Helpers;

if (!defined('ABSPATH')) exit;

$q       = isset($vars['q']) ? $vars['q'] : null;
$columns = isset($vars['columns']) ? (int) $vars['columns'] : 4;

echo '<div class="row row-cols-1 row-cols-md-' . esc_attr($columns) . ' g-4 aloxstore-grid">';

if ($q && $q->have_posts()) :
    while ($q->have_posts()) :
        $q->the_post();
        $id        = get_the_ID();
        $permalink = get_permalink($id);

        // --- Price ---
        $cents = (int) get_post_meta($id, 'price_cents', true);
        $price_text = Helpers::format_money($cents);

        $price_html = sprintf(
            '<div class="alx-price fw-semibold mt-2" aria-label="%1$s">%1$s</div>',
            esc_html($price_text)
        );

        echo '<div class="col"><div class="card h-100">';

        if (has_post_thumbnail()) {
            $title = get_the_title();
            echo '<div class="card-img-top"><a href="' . esc_url($permalink) . '" aria-label="' . esc_attr($title) . '">'
                . get_the_post_thumbnail($id, 'medium')
                . '</a></div>';
        }

        echo '<div class="card-body">';
        echo '<h5 class="card-title"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title()) . '</a></h5>';
        echo '<div class="card-text">' . wp_kses_post(wpautop(get_the_excerpt())) . '</div>';

        if ($cents > 0) {
            echo $price_html;
        } else {
            echo '<div class="alx-price fw-semibold mt-2">' . esc_html__('Free', 'aloxstore') . '</div>';
        }

        echo '<div class="d-flex mt-3 gap-2">';
        echo '<a href="' . esc_url($permalink) . '" class="btn btn-info">' . esc_html__('View', 'aloxstore') . '</a>';
        echo '<button class="btn btn-primary alx-buy" data-product="' . esc_attr($id) . '">' . esc_html__('Buy', 'aloxstore') . '</button>';
        echo '</div>';

        echo '</div>'; // .card-body
        echo '</div></div>'; // .card / .col
    endwhile;
else :
    echo '<div class="col"><div class="alert alert-secondary m-0">' . esc_html__('No products found.', 'aloxstore') . '</div></div>';
endif;

echo '</div>';
