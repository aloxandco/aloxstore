<?php
/**
 * Single Product Template for AloxStore
 * Override by copying to: yourtheme/aloxstore/single-product.php
 */

if (!defined('ABSPATH')) exit;

use AloxStore\Core\Helpers;

get_header();

while (have_posts()) : the_post();

    $id        = get_the_ID();
    $title     = get_the_title();
    $permalink = get_permalink();

    // === Product Meta ===
    $sku               = get_post_meta($id, 'sku', true);
    $price_cents       = (int) get_post_meta($id, 'price_cents', true);
    $sale_cents        = (int) get_post_meta($id, 'sale_price_cents', true);
    $sale_start        = get_post_meta($id, 'sale_start', true);
    $sale_end          = get_post_meta($id, 'sale_end', true);
    $product_type      = get_post_meta($id, 'product_type', true);
    $requires_shipping = (bool) get_post_meta($id, 'requires_shipping', true);
    $weight_grams      = (int) get_post_meta($id, 'weight_grams', true);
    $vat_rate          = (float) get_post_meta($id, 'vat_rate_percent', true);
    $subtitle          = get_post_meta($id, 'subtitle', true);
    $badge_text        = get_post_meta($id, 'badge_text', true);
    $short_description = get_post_meta($id, 'short_description', true);

    // === Global Settings ===
    $currency           = get_option('alx_currency', 'EUR');
    $display_currency   = apply_filters('aloxstore_grid_currency_display', $currency, $id);
    $prices_include_tax = (bool) get_option('alx_prices_include_tax', true);
    $vat_mode           = get_option('alx_vat_mode', 'enabled');

    // === Handle Sale Logic ===
    $now = current_time('timestamp');
    $is_sale_active = false;
    if ($sale_cents > 0) {
        $start_ok = empty($sale_start) || strtotime($sale_start) <= $now;
        $end_ok   = empty($sale_end)   || strtotime($sale_end) >= $now;
        $is_sale_active = $start_ok && $end_ok;
    }

    $display_cents = ($is_sale_active && $sale_cents > 0) ? $sale_cents : $price_cents;

    // === Price Formatting ===
    $price_text     = Helpers::format_money($display_cents);
    $regular_text   = Helpers::format_money($price_cents);
    $sale_text      = $is_sale_active ? Helpers::format_money($sale_cents) : '';

    // === VAT Note ===
    $vat_note = '';
    if ($vat_mode !== 'none') {
        $vat_label = $prices_include_tax ? __('incl. VAT', 'aloxstore') : __('excl. VAT', 'aloxstore');
        $vat_note  = $vat_rate > 0
            ? sprintf(esc_html__('%s (%.2f%%)', 'aloxstore'), $vat_label, $vat_rate)
            : sprintf(esc_html__('%s (no VAT)', 'aloxstore'), $vat_label);
    }

    // === Shipping Message ===
    if ($product_type === 'service' || $product_type === 'digital') {
        $shipping_blurb = __('No shipping required.', 'aloxstore');
    } else {
        $shipping_blurb = $requires_shipping
            ? __('Ships with flat-rate. Free shipping threshold may apply.', 'aloxstore')
            : __('No shipping required (virtual/service).', 'aloxstore');
    }
    ?>
    <main id="primary" class="site-main container my-5">
        <div class="row g-4 align-items-start">
            <div class="col-12 col-md-7">
                <?php
                $gallery = (array) get_post_meta($id, 'gallery_images', true);
                $main_image = has_post_thumbnail($id)
                    ? wp_get_attachment_image_url(get_post_thumbnail_id($id), 'large')
                    : 'https://via.placeholder.com/600?text=No+Image';
                ?>

                <div class="row g-3 align-items-start">
                    <!-- Thumbnails (Left Column) -->
                    <div class="col-2 d-flex flex-column gap-3">
                        <?php
                        // Main image as first thumb
                        echo '<div class="ratio ratio-1x1 border rounded overflow-hidden thumb active">';
                        echo '<img src="' . esc_url($main_image) . '" class="w-100 h-100 object-fit-cover" alt="' . esc_attr($title) . '" data-image="' . esc_url($main_image) . '">';
                        echo '</div>';

                        // Gallery images
                        if (!empty($gallery)) :
                            foreach ($gallery as $img_id) :
                                $thumb_url = wp_get_attachment_image_url($img_id, 'medium');
                                $full_url  = wp_get_attachment_image_url($img_id, 'large');
                                if ($thumb_url && $full_url) :
                                    ?>
                                    <div class="ratio ratio-1x1 border rounded overflow-hidden thumb">
                                        <img src="<?php echo esc_url($thumb_url); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo esc_attr($title); ?>" data-image="<?php echo esc_url($full_url); ?>">
                                    </div>
                                    <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </div>

                    <!-- Main Image (Right Column) -->
                    <div class="col-10">
                        <div class="ratio ratio-1x1 border rounded overflow-hidden">
                            <img id="mainImage" src="<?php echo esc_url($main_image); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo esc_attr($title); ?>">
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const thumbs = document.querySelectorAll('.thumb img');
                        const main = document.getElementById('mainImage');

                        thumbs.forEach(img => {
                            img.addEventListener('click', () => {
                            thumbs.forEach(t => t.closest('.thumb').classList.remove('active'));
                        img.closest('.thumb').classList.add('active');
                        main.src = img.dataset.image;
                    });
                    });
                    });
                </script>

                <style>
                    .thumb.active { outline: 2px solid #0d6efd; }
                    .thumb img { cursor: pointer; transition: opacity 0.2s ease-in-out; }
                    .thumb img:hover { opacity: 0.85; }
                </style>

            </div>

            <div class="col-12 col-md-5">
                <?php if (!empty($badge_text)) : ?>
                    <span class="badge bg-info text-dark mb-2"><?php echo esc_html($badge_text); ?></span>
                <?php endif; ?>

                <h1 class="h2 mb-3"><?php echo esc_html($title); ?></h1>

                <?php if (!empty($subtitle)) : ?>
                    <p class="mb-3"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>

                <div class="mb-3">
                    <?php if ($is_sale_active) : ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-4 fw-semibold text-danger"><?php echo esc_html($sale_text); ?></span>
                            <del class="text-muted small"><?php echo esc_html($regular_text); ?></del>
                        </div>
                    <?php else : ?>
                        <div class="fs-4 fw-semibold mb-1"><?php echo esc_html($price_text); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($vat_note)) : ?>
                        <div class="text-muted"><small><?php echo esc_html($vat_note); ?></small></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($short_description)) : ?>
                    <p class="text-muted mb-4"><?php echo wp_kses_post($short_description); ?></p>
                <?php endif; ?>

                <?php if ($product_type !== 'subscription') : ?>
                    <form class="d-flex align-items-center gap-2 mb-4" onsubmit="return false;">
                        <label class="visually-hidden" for="alx_qty"><?php esc_html_e('Quantity', 'aloxstore'); ?></label>
                        <input type="number" id="alx_qty" class="form-control form-control-lg" value="1" min="1" step="1" style="max-width:100px;">
                        <button class="btn btn-lg w-100 btn-primary alx-buy" data-product="<?php echo esc_attr($id); ?>">
                            <i class="bi bi-bag-plus me-3"></i><?php esc_html_e('Add to Cart', 'aloxstore'); ?>
                        </button>
                    </form>
                <?php else : ?>
                    <div class="alert alert-info"><?php esc_html_e('Subscription product – available soon.', 'aloxstore'); ?></div>
                <?php endif; ?>

                <div class="mb-3">
                    <?php if ($product_type === 'service') : ?>
                        <span class="badge bg-primary"><?php esc_html_e('Service', 'aloxstore'); ?></span>
                    <?php elseif ($product_type === 'digital') : ?>
                        <span class="badge bg-warning text-dark"><?php esc_html_e('Digital Product', 'aloxstore'); ?></span>
                    <?php elseif ($requires_shipping) : ?>
                        <span class="badge bg-secondary"><?php esc_html_e('Physical Product', 'aloxstore'); ?></span>
                    <?php endif; ?>

                    <?php if ($weight_grams > 0 && $requires_shipping) : ?>
                        <span class="ms-2 text-muted"><small><?php printf(esc_html__('Weight: %dg', 'aloxstore'), (int) $weight_grams); ?></small></span>
                    <?php endif; ?>
                </div>

                <div class="text-muted mb-4"><small><?php echo esc_html($shipping_blurb); ?></small></div>

                <ul class="list-unstyled small text-muted">
                    <?php if ($vat_mode !== 'none') : ?>
                        <li><?php esc_html_e('VAT Rate:', 'aloxstore'); ?> <?php echo esc_html($vat_rate . '%'); ?></li>
                    <?php endif; ?>

                    <?php if (!empty($sku)) : ?>
                        <li><?php esc_html_e('SKU:', 'aloxstore'); ?> <?php echo esc_html($sku); ?></li>
                    <?php endif; ?>

                    <li><?php esc_html_e('Product ID:', 'aloxstore'); ?> <?php echo (int) $id; ?></li>
                </ul>
            </div>
            <div class="col-12 mb-4">
                <?php
                $content = get_the_content();
                echo wp_kses_post(apply_filters('the_content', $content));
                ?>
            </div>
        </div>
    </main>

    <?php
// === JSON-LD Schema ===
    $schema = [
        '@context' => 'https://schema.org/',
        '@type'    => 'Product',
        'name'     => wp_strip_all_tags($title),
        'sku'      => $sku,
        'url'      => $permalink,
    ];

    if (has_post_thumbnail($id)) {
        $img = wp_get_attachment_image_url(get_post_thumbnail_id($id), 'full');
        if ($img) $schema['image'] = $img;
    }

    $schema['offers'] = [
        '@type'         => 'Offer',
        'priceCurrency' => strtoupper($currency),
        'price'         => number_format($display_cents / 100, 2, '.', ''),
        'availability'  => 'https://schema.org/InStock',
        'url'           => $permalink,
    ];

    if ($is_sale_active) {
        $schema['offers']['priceValidUntil'] = !empty($sale_end) ? date('c', strtotime($sale_end)) : date('c', strtotime('+30 days'));
    }
    ?>
    <script type="application/ld+json"><?php echo wp_json_encode($schema); ?></script>

<?php endwhile;
get_footer();
