<?php
if (!defined('ABSPATH')) exit;

use AloxStore\Cart\CartPricing;
use AloxStore\Core\Helpers;

$cart_raw  = isset($vars['cart']) ? $vars['cart'] : [];
$cart      = CartPricing::calculate($cart_raw);
$lines     = $cart['lines'] ?? [];
$currency  = $cart['currency'] ?? (string)get_option('alx_currency', 'EUR');
$incl_vat  = (bool)get_option('alx_prices_include_tax', false);
$vat_mode  = get_option('alx_vat_mode', 'enabled');

$vat_label = ($vat_mode !== 'none')
    ? ($incl_vat ? __('(incl. VAT)', 'aloxstore') : __('(excl. VAT)', 'aloxstore'))
    : '';

echo '<div class="alx-cart-container">';

if (empty($lines)) {
    echo '<div class="alert alert-secondary text-center py-4 mb-5">';
    echo '<p class="mb-2">' . esc_html__('Your cart is empty.', 'aloxstore') . '</p>';
    echo '<a href="' . esc_url(home_url('/boutique')) . '" class="btn btn-outline-primary">';
    esc_html_e('Return to Shop', 'aloxstore');
    echo '</a></div></div>';
    return;
}
?>

<div class="row g-4">
    <!-- Left Column: Cart Items -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 text-primary"><?php esc_html_e('Your Cart', 'aloxstore'); ?></h6>

                <?php foreach ($lines as $line):
                    $pid       = $line['product_id'] ?? 0;
                    $title     = $line['title'] ?? '';
                    $qty       = (int)($line['qty'] ?? 1);
                    $permalink = get_permalink($pid);
                    $thumb     = get_the_post_thumbnail_url($pid, 'thumbnail');

                    // Effective and regular prices
                    $regular_cents = (int)get_post_meta($pid, 'price_cents', true);
                    $sale_cents    = (int)get_post_meta($pid, 'sale_price_cents', true);
                    $sale_start    = get_post_meta($pid, 'sale_start', true);
                    $sale_end      = get_post_meta($pid, 'sale_end', true);

                    // Determine if sale is active
                    $now = current_time('timestamp');
                    $sale_active = $sale_cents > 0 &&
                        (empty($sale_start) || strtotime($sale_start) <= $now) &&
                        (empty($sale_end) || strtotime($sale_end) >= $now);

                    // Actual price used in cart (already includes sale if active)
                    $unit_price_cents = $incl_vat
                        ? (int)($line['unit_gross_cents'] ?? 0)
                        : (int)($line['unit_net_cents'] ?? 0);
                    $line_total_cents = $incl_vat
                        ? (int)($line['line_gross_cents'] ?? 0)
                        : (int)($line['line_net_cents'] ?? 0);

                    $unit_txt = Helpers::format_money($unit_price_cents);
                    $line_txt = Helpers::format_money($line_total_cents);
                    ?>
                    <div class="alx-cart-item card border-0 mb-3" data-product="<?php echo esc_attr($pid); ?>">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <div class="d-flex align-items-center">
                                    <!-- Product Thumbnail -->
                                    <?php if ($thumb): ?>
                                        <a href="<?php echo esc_url($permalink); ?>" class="me-3">
                                            <img src="<?php echo esc_url($thumb); ?>" alt="" class="rounded" width="80" height="80">
                                        </a>
                                    <?php endif; ?>

                                    <!-- Product Info -->
                                    <div class="flex-grow-1">
                                        <a href="<?php echo esc_url($permalink); ?>" class="fw-semibold text-decoration-none text-dark d-block mb-1 lh-sm">
                                            <?php echo esc_html($title); ?>
                                        </a>

                                        <!-- Price display -->
                                        <?php if ($sale_active && $sale_cents < $regular_cents): ?>
                                            <div class="text-danger fw-semibold">
                                                <?php echo Helpers::format_money($sale_cents); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <del><?php echo Helpers::format_money($regular_cents); ?></del>
                                                <span class="ms-1"><?php echo esc_html($vat_label); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted">
                                                <?php echo esc_html($unit_txt . ' ' . $vat_label); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <div class="d-flex align-items-center">
                                    <!-- Quantity + Update -->
                                    <div class="flex-shrink-0">
                                        <div class="input-group input-group-sm alx-qty-group">
                                            <input
                                                    type="number"
                                                    class="form-control text-center alx-qty"
                                                    value="<?php echo esc_attr($qty); ?>"
                                                    min="0"
                                                    step="1"
                                                    aria-label="<?php esc_attr_e('Quantity', 'aloxstore'); ?>"
                                                    style="max-width: 80px;"
                                            >
                                            <button class="btn btn-outline-secondary alx-update-qty" type="button">
                                                <?php _e('Update', 'aloxstore'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Line Total -->
                                    <div class="flex-grow-1 fw-semibold text-end fs-6" style="min-width:90px;">
                                        <?php echo esc_html($line_txt); ?>
                                    </div>

                                    <!-- Remove -->
                                    <button class="btn btn-sm btn-link text-danger alx-remove ms-3" title="<?php esc_attr_e('Remove this item', 'aloxstore'); ?>">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-none d-md-flex mt-3 flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-danger alx-clear-cart">
                        <i class="bi bi-trash3 me-2"></i><?php esc_html_e('Clear Cart', 'aloxstore'); ?>
                    </button>
                    <a href="<?php echo esc_url(home_url('/boutique')); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-shop me-2"></i><?php esc_html_e('Continue Shopping', 'aloxstore'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Cart Totals -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 text-primary"><?php esc_html_e('Order Summary', 'aloxstore'); ?></h6>
                <?php
                $subtotal_txt = Helpers::format_money((int)($cart['subtotal_cents'] ?? 0));
                $shipping_txt = Helpers::format_money((int)($cart['shipping_cents'] ?? 0));
                $total_txt    = Helpers::format_money((int)($cart['total_cents'] ?? 0));
                ?>
                <p class="d-flex justify-content-between mb-2 small">
                    <span><?php esc_html_e('Subtotal:', 'aloxstore'); ?></span>
                    <span><?php echo esc_html($subtotal_txt); ?></span>
                </p>
                <p class="d-flex justify-content-between mb-2 small">
                    <span><?php esc_html_e('Shipping:', 'aloxstore'); ?></span>
                    <span><?php echo esc_html($shipping_txt); ?></span>
                </p>

                <?php if ($vat_mode !== 'none' && !empty($cart['tax_breakdown'])): ?>
                    <?php foreach ($cart['tax_breakdown'] as $item): ?>
                        <p class="d-flex justify-content-between small mb-2">
                            <span><?php printf(esc_html__('VAT (%s%%):', 'aloxstore'), number_format_i18n($item['rate'], 2)); ?></span>
                            <span><?php echo esc_html(Helpers::format_money((int)$item['tax_cents'])); ?></span>
                        </p>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p class="d-flex justify-content-between fw-bold border-top pt-2 mt-2 fs-5">
                    <span><?php esc_html_e('Total:', 'aloxstore'); ?></span>
                    <span><?php echo esc_html($total_txt); ?></span>
                </p>

                <div class="mt-4 d-grid gap-2">
                    <button class="btn btn-primary alx-checkout py-2">
                        <i class="bi bi-lock-fill me-2"></i><?php esc_html_e('Proceed to Secure Checkout', 'aloxstore'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Feedback alert -->
<div id="alx-cart-alert" class="alert alert-success text-center mt-3 d-none" role="alert">
    <i class="bi bi-check-circle me-2"></i><?php esc_html_e('Cart updated successfully!', 'aloxstore'); ?>
</div>

<style>
    #alx-cart-alert {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
    }
    .alx-cart-item del {
        opacity: 0.65;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const alertBox = document.getElementById('alx-cart-alert');
    const showAlert = () => {
        if (!alertBox) return;
        alertBox.classList.remove('d-none');
        setTimeout(() => alertBox.classList.add('d-none'), 2000);
    };
    document.querySelectorAll('.alx-update-qty, .alx-remove, .alx-clear-cart')
        .forEach(btn => btn.addEventListener('click', () => showAlert()));
    });
</script>
