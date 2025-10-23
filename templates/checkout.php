<?php
/**
 * Checkout Template
 * Shortcode: [aloxstore_checkout]
 */
if (!defined('ABSPATH')) exit;

use AloxStore\Cart\Cart;
use AloxStore\Cart\CartPricing;
use AloxStore\Core\Helpers;

// 🛒 Get active cart (from shortcode or session cookie)
$cart_raw = isset($vars['cart']) && !empty($vars['cart'])
    ? (array)$vars['cart']
    : Cart::get_cart();

// 🔧 Normalize: ensure we have 'lines' instead of only 'items'
if (!empty($cart_raw['items']) && empty($cart_raw['lines'])) {
    $cart_raw['lines'] = [];
    foreach ($cart_raw['items'] as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['qty'] ?? 1);
        if ($pid > 0) {
            $cart_raw['lines'][] = [
                'product_id' => $pid,
                'qty'        => $qty,
                'title'      => get_the_title($pid),
                'unit_cents' => (int)get_post_meta($pid, 'price_cents', true),
                'vat_rate_percent' => (float)get_post_meta($pid, 'vat_rate_percent', true),
            ];
        }
    }
}

// 🧮 Calculate cart totals
$cart = CartPricing::calculate($cart_raw);

$lines    = $cart['lines'] ?? [];
$subtotal = (int)($cart['subtotal_cents'] ?? 0);
$shipping = (int)($cart['shipping_cents'] ?? 0);
$total    = (int)($cart['total_cents'] ?? 0);
$currency = $cart['currency'] ?? get_option('alx_currency', 'EUR');

$vat_mode = get_option('alx_vat_mode', 'enabled');
$incl_vat = (bool)get_option('alx_prices_include_tax', false);
$vat_label = ($vat_mode !== 'none')
    ? ($incl_vat ? __('(incl. VAT)', 'aloxstore') : __('(excl. VAT)', 'aloxstore'))
    : '';

$defaults = isset($vars['defaults']) ? (array)$vars['defaults'] : [];
$c = function ($k) use ($defaults) {
    return isset($defaults[$k]) ? esc_attr($defaults[$k]) : '';
};

// 🌍 Country options
$countries = [
    ''   => __('Select country', 'aloxstore'),
    'NL' => 'Netherlands',
    'BE' => 'Belgium',
    'DE' => 'Germany',
    'FR' => 'France',
    'ES' => 'Spain',
    'IT' => 'Italy',
    'GB' => 'United Kingdom',
    'IE' => 'Ireland',
];

// 🖼️ Payment method icons
$cards_img_url = plugins_url('assets/images/cards-icons.png', WP_PLUGIN_DIR . '/aloxstore/aloxstore.php');
?>

<div class="container m-0 p-0 mb-5 alx-checkout-container">
    <form class="alx-checkout-form needs-validation" novalidate>
        <div class="row g-4">
            <!-- Left Column: Billing Form -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-primary"><?php esc_html_e('Contact Details', 'aloxstore'); ?></h6>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e('First name*', 'aloxstore'); ?></label>
                                <input type="text" name="first_name" class="form-control" placeholder="John" value="<?php echo $c('first_name'); ?>" required>
                                <div class="invalid-feedback"><?php esc_html_e('Please enter your first name.', 'aloxstore'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e('Last name*', 'aloxstore'); ?></label>
                                <input type="text" name="last_name" class="form-control" placeholder="Doe" value="<?php echo $c('last_name'); ?>" required>
                                <div class="invalid-feedback"><?php esc_html_e('Please enter your last name.', 'aloxstore'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e('Email*', 'aloxstore'); ?></label>
                                <input type="email" name="email" class="form-control" placeholder="john@example.com" value="<?php echo $c('email'); ?>" required>
                                <div class="invalid-feedback"><?php esc_html_e('Please enter a valid email address.', 'aloxstore'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e('Telephone*', 'aloxstore'); ?></label>
                                <input type="tel" name="telephone" class="form-control" placeholder="+31 6 1234 5678" value="<?php echo $c('telephone'); ?>" required>
                                <div class="invalid-feedback"><?php esc_html_e('Please enter your telephone number.', 'aloxstore'); ?></div>
                            </div>
                        </div>

                        <h6 class="fw-semibold mt-4 mb-3 text-primary"><?php esc_html_e('Company Details', 'aloxstore'); ?></h6>
                        <div class="mb-3">
                            <label class="form-label"><?php esc_html_e('Company (optional)', 'aloxstore'); ?></label>
                            <input type="text" name="company" class="form-control" placeholder="Your company name" value="<?php echo $c('company'); ?>">
                        </div>

                        <h6 class="fw-semibold mt-4 mb-3 text-primary"><?php esc_html_e('Billing Address', 'aloxstore'); ?></h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label"><?php esc_html_e('Address line 1*', 'aloxstore'); ?></label>
                                <input type="text" name="address_1" class="form-control" placeholder="Street and house number" value="<?php echo $c('address_1'); ?>" required>
                                <div class="invalid-feedback"><?php esc_html_e('Please enter your address.', 'aloxstore'); ?></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php esc_html_e('Address line 2', 'aloxstore'); ?></label>
                                <input type="text" name="address_2" class="form-control" placeholder="Apartment, suite, etc." value="<?php echo $c('address_2'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php esc_html_e('Postcode*', 'aloxstore'); ?></label>
                                <input type="text" name="postcode" class="form-control" placeholder="1234 AB" value="<?php echo $c('postcode'); ?>" required>
                                <div class="invalid-feedback"><?php esc_html_e('Enter a valid postcode.', 'aloxstore'); ?></div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label"><?php esc_html_e('City*', 'aloxstore'); ?></label>
                                <input type="text" name="city" class="form-control" placeholder="Amsterdam" value="<?php echo $c('city'); ?>" required>
                                <div class="invalid-feedback"><?php esc_html_e('Please enter your city.', 'aloxstore'); ?></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php esc_html_e('Country*', 'aloxstore'); ?></label>
                                <select name="country" class="form-select" required>
                                    <?php foreach ($countries as $code => $label) :
                                        $selected = (strtoupper($defaults['country'] ?? '') === $code) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php echo $selected; ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?php esc_html_e('Please select your country.', 'aloxstore'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Summary -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-primary"><?php esc_html_e('Order Summary', 'aloxstore'); ?></h6>

                        <?php if (empty($lines)) : ?>
                            <p><?php esc_html_e('Your cart is empty.', 'aloxstore'); ?></p>
                        <?php else : ?>
                            <ul class="list-group list-group-flush mb-3">
                                <?php foreach ($lines as $line) :
                                    $title = $line['title'] ?? '';
                                    $qty   = (int)($line['qty'] ?? 1);
                                    $price = $incl_vat
                                        ? (int)($line['line_gross_cents'] ?? 0)
                                        : (int)($line['line_net_cents'] ?? 0);
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold"><?php echo esc_html($title); ?></div>
                                            <small class="text-muted"><?php echo sprintf(esc_html__('Qty: %d', 'aloxstore'), $qty); ?></small>
                                        </div>
                                        <div><?php echo esc_html(Helpers::format_money($price)); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="border-top pt-3 small">
                                <p class="d-flex justify-content-between mb-2">
                                    <span><?php esc_html_e('Subtotal:', 'aloxstore'); ?></span>
                                    <span><?php echo esc_html(Helpers::format_money($subtotal)); ?></span>
                                </p>
                                <p class="d-flex justify-content-between mb-2">
                                    <span><?php esc_html_e('Shipping:', 'aloxstore'); ?></span>
                                    <span><?php echo esc_html(Helpers::format_money($shipping)); ?></span>
                                </p>

                                <?php if ($vat_mode !== 'none' && !empty($cart['tax_breakdown'])) : ?>
                                    <?php foreach ($cart['tax_breakdown'] as $item) : ?>
                                        <p class="d-flex justify-content-between mb-2">
                                            <span><?php printf(esc_html__('VAT (%s%%):', 'aloxstore'), number_format_i18n($item['rate'], 2)); ?></span>
                                            <span><?php echo esc_html(Helpers::format_money((int)$item['tax_cents'])); ?></span>
                                        </p>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <p class="d-flex justify-content-between fw-bold border-top pt-2 mt-2 fs-5">
                                    <span><?php esc_html_e('Total:', 'aloxstore'); ?></span>
                                    <span><?php echo esc_html(Helpers::format_money($total)); ?></span>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="mt-5 text-center">
                            <button class="btn btn-primary alx-checkout-submit w-100">
                                <i class="bi bi-lock-fill me-2"></i>
                                <?php esc_html_e('Continue to Secure Stripe Payment', 'aloxstore'); ?>
                            </button>
                            <div class="text-success small mt-2">
                                <i class="bi bi-shield-check me-1"></i>
                                <?php esc_html_e('Your payment is protected by 256-bit SSL encryption.', 'aloxstore'); ?>
                            </div>
                            <div class="mt-3">
                                <img src="<?php echo esc_url($cards_img_url); ?>" alt="Payment Methods" class="img-fluid" width="300" style="max-width:300px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    .alx-checkout-submit {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border: none;
        font-weight: 600;
        font-size: 1.05rem;
        padding: 0.9rem 1.8rem;
        border-radius: 6px;
        transition: all 0.25s ease;
    }
    .alx-checkout-submit:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        transform: translateY(-1px);
    }
    .alx-checkout-submit:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('.alx-checkout-form');
    const btn = document.querySelector('.alx-checkout-submit');
    if (!form || !btn) return;

    // Validation
    form.addEventListener('submit', (e) => {
        if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        return false;
    }
    }, false);

    // Spinner on submit
    btn.addEventListener('click', (e) => {
        if (btn.disabled) return;
    btn.disabled = true;
    btn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            <?php echo esc_html__('Processing...', 'aloxstore'); ?>
        `;
    });
    });
</script>
