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
        $title     = get_the_title();

        // === Pricing Logic ===
        $regular_cents = (int) get_post_meta($id, 'price_cents', true);
        $sale_cents    = (int) get_post_meta($id, 'sale_price_cents', true);
        $sale_start    = get_post_meta($id, 'sale_start', true);
        $sale_end      = get_post_meta($id, 'sale_end', true);

        $now = current_time('timestamp');
        $sale_active = $sale_cents > 0 &&
            (empty($sale_start) || strtotime($sale_start) <= $now) &&
            (empty($sale_end) || strtotime($sale_end) >= $now);

        // Effective price
        $effective_cents = Helpers::get_product_price_cents($id);

        // === Build price HTML ===
        if ($sale_active && $sale_cents < $regular_cents) {
            $price_html = sprintf(
                '<div class="alx-price mt-2 fw-semibold">
                    <span class="text-danger">%1$s</span>
                    <span class="text-muted text-decoration-line-through ms-2 small">%2$s</span>
                </div>',
                esc_html(Helpers::format_money($sale_cents)),
                esc_html(Helpers::format_money($regular_cents))
            );
        } elseif ($regular_cents > 0) {
            $price_html = sprintf(
                '<div class="alx-price mt-2 fw-semibold">%1$s</div>',
                esc_html(Helpers::format_money($regular_cents))
            );
        } else {
            $price_html = '<div class="alx-price fw-semibold mt-2">' . esc_html__('Free', 'aloxstore') . '</div>';
        }

        // === Render Card ===
        echo '<div class="col"><div class="card h-100 border-0 shadow-sm position-relative">';

        // SALE badge overlay
        if ($sale_active) {
            echo '<span class="badge bg-danger position-absolute top-0 end-0 m-2 px-2 py-1">' . esc_html__('Sale', 'aloxstore') . '</span>';
        }

        if (has_post_thumbnail()) {
            echo '<a href="' . esc_url($permalink) . '" class="card-img-top d-block text-center">'
                . get_the_post_thumbnail($id, 'medium', ['class' => 'img-fluid rounded-top'])
                . '</a>';
        }

        echo '<div class="card-body">';
        echo '<h5 class="card-title mb-2"><a href="' . esc_url($permalink) . '" class="text-decoration-none text-dark">' . esc_html($title) . '</a></h5>';
        echo '<div class="card-text small text-muted alx-ellipsis-3">' . esc_html(strip_tags(get_the_excerpt())) . '</div>';

        echo $price_html;

        echo '<div class="d-flex mt-3 gap-2">';
        echo '<a href="' . esc_url($permalink) . '" class="btn btn-outline-primary btn-sm flex-fill">' . esc_html__('View', 'aloxstore') . '</a>';
        echo '<button class="btn btn-primary btn-sm flex-fill alx-buy" data-product="' . esc_attr($id) . '">' . esc_html__('Add to cart', 'aloxstore') . '</button>';
        echo '</div>';

        echo '</div></div></div>'; // .card-body / .card / .col

    endwhile;
else :
    echo '<div class="col"><div class="alert alert-secondary m-0">' . esc_html__('No products found.', 'aloxstore') . '</div></div>';
endif;

echo '</div>';
