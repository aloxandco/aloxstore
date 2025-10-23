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

    // Meta
    $sku               = get_post_meta($id, 'sku', true);
    $cents             = (int) get_post_meta($id, 'price_cents', true);
    $requires_shipping = (bool) get_post_meta($id, 'requires_shipping', true);
    $weight_grams      = (int) get_post_meta($id, 'weight_grams', true);
    $vat_rate          = (float) get_post_meta($id, 'vat_rate_percent', true);

    // Global settings
    $currency           = get_option('alx_currency', 'EUR');
    $display_currency   = apply_filters('aloxstore_grid_currency_display', $currency, $id);
    $prices_include_tax = (bool) get_option('alx_prices_include_tax', true);
    $vat_mode           = get_option('alx_vat_mode', 'enabled');

    // Price formatting
    $price_text = Helpers::format_money( $cents );


    // VAT note (only if VAT mode is active)
    $vat_note = '';
    if ($vat_mode !== 'none') {
        $vat_label = $prices_include_tax ? __('incl. VAT', 'aloxstore') : __('excl. VAT', 'aloxstore');
        $vat_note = $vat_rate > 0
            ? sprintf(esc_html__('%s (%.2f%%)', 'aloxstore'), $vat_label, $vat_rate)
            : sprintf(esc_html__('%s (no VAT)', 'aloxstore'), $vat_label);
    }

    // Shipping blurb
    $shipping_blurb = $requires_shipping
        ? __('Ships with flat-rate. Free shipping threshold may apply.', 'aloxstore')
        : __('No shipping required (virtual/service).', 'aloxstore');
    ?>

    <main id="primary" class="site-main container my-5">
        <div class="row g-5">
            <div class="col-12 col-md-6">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="ratio ratio-1x1 border rounded overflow-hidden">
                        <?php echo get_the_post_thumbnail($id, 'large', ['class' => 'w-100 h-100 object-fit-cover']); ?>
                    </div>
                <?php else : ?>
                    <div class="border rounded p-5 text-center text-muted"><?php esc_html_e('No image available', 'aloxstore'); ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <h1 class="h2 mb-3"><?php echo esc_html($title); ?></h1>

                <?php if (!empty($sku)) : ?>
                    <div class="text-muted mb-2">
                        <small><?php esc_html_e('SKU:', 'aloxstore'); ?> <?php echo esc_html($sku); ?></small>
                    </div>
                <?php endif; ?>

                <div class="lead fw-semibold mb-1" aria-label="<?php echo esc_attr($price_text); ?>">
                    <?php echo esc_html($price_text); ?>
                </div>

                <?php if (!empty($vat_note)) : ?>
                    <div class="text-muted mb-3"><small><?php echo esc_html($vat_note); ?></small></div>
                <?php endif; ?>

                <div class="mb-4">
                    <?php
                    $content = get_the_content();
                    echo wp_kses_post(apply_filters('the_content', $content));
                    ?>
                </div>

                <?php if ($requires_shipping) : ?>
                    <div class="mb-3">
                        <span class="badge bg-secondary"><?php esc_html_e('Physical product', 'aloxstore'); ?></span>
                        <?php if ($weight_grams > 0) : ?>
                            <span class="ms-2 text-muted"><small><?php printf(esc_html__('Weight: %dg', 'aloxstore'), (int) $weight_grams); ?></small></span>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="mb-3">
                        <span class="badge bg-info"><?php esc_html_e('Virtual/Service', 'aloxstore'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="text-muted mb-4"><small><?php echo esc_html($shipping_blurb); ?></small></div>

                <form class="d-flex align-items-center gap-2 mb-4" onsubmit="return false;">
                    <label class="visually-hidden" for="alx_qty"><?php esc_html_e('Quantity', 'aloxstore'); ?></label>
                    <input type="number" id="alx_qty" class="form-control" value="1" min="1" step="1" style="max-width:100px;">
                    <button class="btn btn-primary alx-buy" data-product="<?php echo esc_attr($id); ?>">
                        <?php esc_html_e('Buy', 'aloxstore'); ?>
                    </button>
                </form>

                <ul class="list-unstyled small text-muted">
                    <?php if ($vat_mode !== 'none') : ?>
                        <li><?php esc_html_e('VAT Rate:', 'aloxstore'); ?> <?php echo esc_html($vat_rate . '%'); ?></li>
                    <?php endif; ?>
                    <li><?php esc_html_e('Product ID:', 'aloxstore'); ?> <?php echo (int) $id; ?></li>
                </ul>
            </div>
        </div>
    </main>

    <?php
    // === Basic Product schema (for SEO)
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
        'price'         => number_format($cents / 100, 2, '.', ''),
        'availability'  => 'https://schema.org/InStock',
        'url'           => $permalink,
    ];
    ?>
    <script type="application/ld+json"><?php echo wp_json_encode($schema); ?></script>

<?php endwhile;

get_footer();
