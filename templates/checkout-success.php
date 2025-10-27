<?php
/**
 * Checkout Success Template
 * @package AloxStore
 */

get_header();

use AloxStore\Cart\CartPricing;
use AloxStore\Core\Helpers;

if (!defined('ABSPATH')) exit;

// === Identify order from session_id ===
$session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
$order_id   = 0;

if ($session_id) {
    $query = new WP_Query([
        'post_type'      => 'alox_order',
        'post_status'    => 'publish',
        'meta_key'       => '_alx_stripe_session_id',
        'meta_value'     => $session_id,
        'fields'         => 'ids',
        'posts_per_page' => 1,
    ]);
    if (!empty($query->posts)) {
        $order_id = (int)$query->posts[0];
    }
}
?>

<div class="container py-5" id="alx-order-confirmation">
    <?php if ($order_id) :

        // === Load cart and customer data ===
        $cart       = get_post_meta($order_id, '_alx_cart', true);
        $subtotal   = (int)($cart['subtotal_cents'] ?? 0);
        $shipping   = (int)($cart['shipping_cents'] ?? 0);
        $tax        = (int)($cart['tax_cents'] ?? 0);
        $total      = (int)($cart['total_cents'] ?? 0);
        $currency   = strtoupper($cart['currency'] ?? 'EUR');
        $lines      = $cart['lines'] ?? [];
        $vat_mode   = get_option('alx_vat_mode', 'enabled');
        $incl_vat   = (bool)get_option('alx_prices_include_tax', false);
        $tax_break  = $cart['tax_breakdown'] ?? [];

        $billing    = [];
        $shipping_i = [];

        foreach (get_post_meta($order_id) as $key => $val) {
            if (str_starts_with($key, '_alx_billing_')) {
                $billing[str_replace('_alx_billing_', '', $key)] = $val[0];
            }
            if (str_starts_with($key, '_alx_shipping_')) {
                $shipping_i[str_replace('_alx_shipping_', '', $key)] = $val[0];
            }
        }

        // === If no shipping info, fallback to billing ===
        $use_billing_for_shipping = true;
        foreach ($shipping_i as $v) {
            if (!empty($v)) {
                $use_billing_for_shipping = false;
                break;
            }
        }
        if ($use_billing_for_shipping) {
            $shipping_i = $billing;
        }

        // === Generate order number ===
        $order_title = get_the_title($order_id);
        ?>

        <div class="text-center">
            <i class="bi bi-check-circle-fill text-success display-4"></i>
            <h1 class="mb-1">Thank you for your order!</h1>
            <p class="lead mb-0">Your payment has been confirmed. We’re now preparing your order.</p>

            <!-- Print Button -->
            <button onclick="window.print()" class="btn btn-outline-secondary mt-4 d-print-none">
                <i class="bi bi-printer me-2"></i> Print Confirmation
            </button>
        </div>

        <div class="row g-4" style="max-width: 700px; margin: auto;">
            <!-- ORDER SUMMARY -->
            <div class="col-12">
                <div class="card border-0 mb-4">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-primary">
                            <i class="bi bi-bag-check me-2"></i><?php echo esc_html($order_title); ?>
                        </h6>
                        <?php if (empty($lines)) : ?>
                            <p>Your order has no items recorded.</p>
                        <?php else : ?>
                            <ul class="list-group list-group-flush mb-3">
                                <?php foreach ($lines as $line) :
                                    $pid        = (int)($line['product_id'] ?? 0);
                                    $title      = $line['title'] ?? get_the_title($pid);
                                    $qty        = (int)($line['qty'] ?? 1);
                                    $thumb      = get_the_post_thumbnail_url($pid, 'thumbnail');
                                    $price      = $incl_vat
                                        ? (int)($line['line_gross_cents'] ?? 0)
                                        : (int)($line['line_net_cents'] ?? 0);
                                    ?>
                                    <li class="list-group-item d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <?php if ($thumb) : ?>
                                                <img src="<?php echo esc_url($thumb); ?>" alt="" class="me-3 rounded" width="60" height="60">
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold"><?php echo esc_html($title); ?></div>
                                                <small class="text-muted">
                                                    <?php echo sprintf(__('Qty: %d', 'aloxstore'), $qty); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="fw-semibold"><?php echo esc_html(Helpers::format_money($price)); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="border-top pt-3 small">
                                <p class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span><?php echo esc_html(Helpers::format_money($subtotal)); ?></span>
                                </p>
                                <p class="d-flex justify-content-between mb-2">
                                    <span>Shipping:</span>
                                    <span><?php echo esc_html(Helpers::format_money($shipping)); ?></span>
                                </p>

                                <?php if ($vat_mode !== 'none' && !empty($tax_break)) : ?>
                                    <?php foreach ($tax_break as $item) : ?>
                                        <p class="d-flex justify-content-between mb-2">
                                            <span><?php printf(__('VAT (%s%%):', 'aloxstore'), number_format_i18n($item['rate'], 2)); ?></span>
                                            <span><?php echo esc_html(Helpers::format_money((int)$item['tax_cents'])); ?></span>
                                        </p>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <p class="d-flex justify-content-between fw-bold border-top pt-2 mt-2 fs-5">
                                    <span>Total:</span>
                                    <span><?php echo esc_html(Helpers::format_money($total)); ?></span>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- BILLING -->
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-primary">
                            <i class="bi bi-receipt me-2"></i>Billing Details
                        </h6>
                        <p class="mb-1"><strong><?php echo esc_html(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')); ?></strong></p>
                        <p class="mb-1"><?php echo esc_html($billing['address_1'] ?? ''); ?></p>
                        <?php if (!empty($billing['address_2'])) : ?><p class="mb-1"><?php echo esc_html($billing['address_2']); ?></p><?php endif; ?>
                        <p class="mb-1"><?php echo esc_html(($billing['postcode'] ?? '') . ' ' . ($billing['city'] ?? '')); ?></p>
                        <p class="mb-1"><?php echo esc_html($billing['country'] ?? ''); ?></p>
                        <p class="mb-0"><strong>Email:</strong> <?php echo esc_html($billing['email'] ?? ''); ?></p>
                        <?php if (!empty($billing['phone'])) : ?>
                            <p class="mb-0"><strong>Phone:</strong> <?php echo esc_html($billing['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SHIPPING -->
            <div class="col-md-6">
                <div class="card border-0 mb-4">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-primary">
                            <i class="bi bi-truck me-2"></i>Shipping Details
                        </h6>
                        <p class="mb-1"><strong><?php echo esc_html(($shipping_i['first_name'] ?? '') . ' ' . ($shipping_i['last_name'] ?? '')); ?></strong></p>
                        <p class="mb-1"><?php echo esc_html($shipping_i['address_1'] ?? ''); ?></p>
                        <?php if (!empty($shipping_i['address_2'])) : ?><p class="mb-1"><?php echo esc_html($shipping_i['address_2']); ?></p><?php endif; ?>
                        <p class="mb-1"><?php echo esc_html(($shipping_i['postcode'] ?? '') . ' ' . ($shipping_i['city'] ?? '')); ?></p>
                        <p class="mb-1"><?php echo esc_html($shipping_i['country'] ?? ''); ?></p>
                        <?php if (!empty($shipping_i['phone'])) : ?>
                            <p class="mb-0"><strong>Phone:</strong> <?php echo esc_html($shipping_i['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else : ?>
        <div class="text-center py-5">
            <i class="bi bi-info-circle text-warning display-4 mb-3"></i>
            <h1 class="mb-3">Order not found</h1>
            <p class="lead">We couldn’t find your order details. If you’ve just completed payment, please wait a few seconds and refresh this page.</p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-outline-primary mt-3">Back to Shop</a>
        </div>
    <?php endif; ?>
</div>

<!-- Print Styles -->
<style>
    @media print {
        body {
            background: #fff !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
        header, footer, .d-print-none, .btn, nav, .site-header, .site-footer {
            display: none !important;
        }
        #alx-order-confirmation {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        .text-primary {
            color: #000 !important;
        }
        .list-group-item {
            border-color: #ccc !important;
        }
        .container {
            max-width: 800px !important;
        }
    }
</style>

<?php get_footer(); ?>
