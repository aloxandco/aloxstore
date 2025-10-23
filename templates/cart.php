<?php
if (!defined('ABSPATH')) exit;

use AloxStore\Cart\CartPricing;
use AloxStore\Core\Helpers;

/** @var array $cart from shortcode */
$cart_raw  = isset($vars['cart']) ? $vars['cart'] : [];
$cart      = CartPricing::calculate($cart_raw);
$lines     = $cart['lines'] ?? [];
$currency  = $cart['currency'] ?? (string) get_option('alx_currency', 'EUR');
$incl_vat  = (bool) get_option('alx_prices_include_tax', false);
$vat_mode  = get_option('alx_vat_mode', 'enabled');

$vat_label = ($vat_mode !== 'none')
    ? ($incl_vat ? __('(incl. VAT)', 'aloxstore') : __('(excl. VAT)', 'aloxstore'))
    : '';

echo '<div class="container my-5 alx-cart-container">';
echo '<h1 class="h4 fw-semibold text-primary mb-4">' . esc_html__('Your Shopping Cart', 'aloxstore') . '</h1>';

if (empty($lines)) {
    echo '<div class="alert alert-secondary text-center py-4 mb-5">';
    echo '<p class="mb-2">' . esc_html__('Your cart is empty.', 'aloxstore') . '</p>';
    echo '<a href="' . esc_url(home_url('/boutique')) . '" class="btn btn-outline-primary">';
    esc_html_e('Return to Shop', 'aloxstore');
    echo '</a>';
    echo '</div></div>';
    return;
}
?>

<div class="row g-4">
    <!-- Left Column: Cart Items -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 text-primary"><?php esc_html_e('Cart Items', 'aloxstore'); ?></h6>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th><?php _e('Product', 'aloxstore'); ?></th>
                            <th class="text-end"><?php echo esc_html(sprintf(__('Unit Price %s', 'aloxstore'), $vat_label)); ?></th>
                            <th class="text-center"><?php _e('Quantity', 'aloxstore'); ?></th>
                            <th class="text-end"><?php echo esc_html(sprintf(__('Total %s', 'aloxstore'), $vat_label)); ?></th>
                            <th class="text-end"><?php _e('Actions', 'aloxstore'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line):
                            $pid       = $line['product_id'] ?? 0;
                            $title     = $line['title'] ?? '';
                            $qty       = (int)($line['qty'] ?? 1);
                            $permalink = get_permalink($pid);

                            // Choose display amount depending on global VAT setting
                            $unit_price_cents = $incl_vat
                                ? (int)($line['unit_gross_cents'] ?? 0)
                                : (int)($line['unit_net_cents'] ?? 0);

                            $line_total_cents = $incl_vat
                                ? (int)($line['line_gross_cents'] ?? 0)
                                : (int)($line['line_net_cents'] ?? 0);

                            $unit_txt = Helpers::format_money($unit_price_cents);
                            $line_txt = Helpers::format_money($line_total_cents);
                            ?>
                            <tr data-product="<?php echo esc_attr($pid); ?>">
                                <td>
                                    <a href="<?php echo esc_url($permalink); ?>" class="fw-semibold text-decoration-none text-dark">
                                        <?php echo esc_html($title); ?>
                                    </a>
                                </td>
                                <td class="text-end"><?php echo esc_html($unit_txt); ?></td>
                                <td class="text-center" style="max-width:130px;">
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <input type="number" class="form-control alx-qty text-center" value="<?php echo esc_attr($qty); ?>" min="0" step="1" style="width:80px;">
                                        <button class="btn btn-sm btn-outline-secondary alx-update-qty">
                                            <?php _e('Update', 'aloxstore'); ?>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-end fw-semibold"><?php echo esc_html($line_txt); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-link text-danger alx-remove">
                                        <i class="bi bi-trash me-1"></i> <?php _e('Remove', 'aloxstore'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Cart Totals -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 text-primary"><?php esc_html_e('Order Summary', 'aloxstore'); ?></h6>
                <?php
                $subtotal_txt = Helpers::format_money((int)($cart['subtotal_cents'] ?? 0));
                $shipping_txt = Helpers::format_money((int)($cart['shipping_cents'] ?? 0));
                $total_txt    = Helpers::format_money((int)($cart['total_cents'] ?? 0));
                ?>
                <p class="d-flex justify-content-between mb-2">
                    <span><?php esc_html_e('Subtotal (net):', 'aloxstore'); ?></span>
                    <span><?php echo esc_html($subtotal_txt); ?></span>
                </p>
                <p class="d-flex justify-content-between mb-2">
                    <span><?php esc_html_e('Shipping (net):', 'aloxstore'); ?></span>
                    <span><?php echo esc_html($shipping_txt); ?></span>
                </p>

                <?php if ($vat_mode !== 'none' && !empty($cart['tax_breakdown'])): ?>
                    <?php foreach ($cart['tax_breakdown'] as $item): ?>
                        <p class="d-flex justify-content-between mb-2">
                            <span><?php printf(esc_html__('VAT (%s%%):', 'aloxstore'), number_format_i18n($item['rate'], 2)); ?></span>
                            <span><?php echo esc_html(Helpers::format_money((int)$item['tax_cents'])); ?></span>
                        </p>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p class="d-flex justify-content-between fw-bold border-top pt-2 mt-2">
                    <span><?php esc_html_e('Total (gross):', 'aloxstore'); ?></span>
                    <span><?php echo esc_html($total_txt); ?></span>
                </p>

                <div class="mt-4 d-grid gap-2">
                    <button class="btn btn-primary alx-checkout py-2">
                        <i class="bi bi-lock-fill me-2"></i>
                        <?php esc_html_e('Proceed to Secure Checkout', 'aloxstore'); ?>
                    </button>
                    <button class="btn btn-outline-danger alx-clear-cart py-2">
                        <i class="bi bi-trash3 me-2"></i>
                        <?php esc_html_e('Clear Cart', 'aloxstore'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<style>
    .alx-cart-container .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border: none;
        font-weight: 600;
        transition: all 0.25s ease;
    }
    .alx-cart-container .btn-primary:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        transform: translateY(-1px);
    }
    .alx-cart-container .btn-outline-danger:hover {
        background-color: #ef4444;
        color: #fff;
    }
</style>
