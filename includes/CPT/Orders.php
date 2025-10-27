<?php
namespace AloxStore\CPT;

use AloxStore\Core\Helpers;

if (!defined('ABSPATH')) exit;

class Orders {

    public static function register() {
        register_post_type('alox_order', [
            'labels' => [
                'name'          => __('Alox Orders', 'aloxstore'),
                'singular_name' => __('Alox Order', 'aloxstore'),
            ],
            'public'    => false,
            'show_ui'   => true,
            'supports'  => ['title'],
            'menu_icon' => 'dashicons-clipboard',
        ]);

        add_action('add_meta_boxes', [__CLASS__, 'meta_boxes']);
    }

    public static function meta_boxes() {
        add_meta_box(
            'alox_order_details',
            __('Order Details', 'aloxstore'),
            [__CLASS__, 'render_meta_box'],
            'alox_order',
            'normal',
            'high'
        );
    }

    public static function render_meta_box($post) {
        $session_id = get_post_meta($post->ID, '_alx_stripe_session_id', true);
        $paid       = get_post_meta($post->ID, '_alx_paid', true) ? '✔️ Yes' : '❌ No';

        $subtotal   = (int) get_post_meta($post->ID, '_alx_subtotal', true);
        $shipping   = (int) get_post_meta($post->ID, '_alx_shipping', true);
        $tax        = (int) get_post_meta($post->ID, '_alx_tax', true);
        $total      = (int) get_post_meta($post->ID, '_alx_total', true);
        $cart       = get_post_meta($post->ID, '_alx_cart', true);
        $currency   = $cart['currency'] ?? get_option('alx_currency', 'EUR');
        $incl_vat   = (bool) get_option('alx_prices_include_tax', false);
        $vat_mode   = get_option('alx_vat_mode', 'enabled');

        // === BILLING INFORMATION ===
        echo '<h4>Billing Information</h4><table class="widefat striped"><tbody>';
        $billing_fields = [
            'first_name', 'last_name', 'email', 'company',
            'address_1', 'address_2', 'postcode', 'city', 'country', 'phone'
        ];
        foreach ($billing_fields as $key) {
            $val = get_post_meta($post->ID, '_alx_billing_' . $key, true);
            if ($val !== '') {
                echo '<tr><td><strong>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</strong></td><td>' . esc_html($val) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        // === SHIPPING INFORMATION ===
        echo '<h4>Shipping Information</h4><table class="widefat striped"><tbody>';
        $shipping_fields = [
            'first_name', 'last_name', 'company',
            'address_1', 'address_2', 'postcode', 'city', 'country', 'phone'
        ];
        foreach ($shipping_fields as $key) {
            $val = get_post_meta($post->ID, '_alx_shipping_' . $key, true);
            if ($val !== '') {
                echo '<tr><td><strong>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</strong></td><td>' . esc_html($val) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        // === CART SUMMARY ===
        echo '<h4>Cart Summary</h4>';
        if (is_array($cart) && !empty($cart['lines'])) {
            $vat_suffix = $vat_mode === 'none' ? '' : ($incl_vat ? '(incl.)' : '(excl.)');
            echo '<table class="widefat striped"><thead><tr><th>Product</th><th>Qty</th><th>Unit ' . esc_html($vat_suffix) . '</th><th>Total</th></tr></thead><tbody>';

            foreach ($cart['lines'] as $item) {
                $pid      = (int) ($item['product_id'] ?? 0);
                $qty      = (int) ($item['qty'] ?? 1);
                $title    = get_the_title($pid);

                $unit     = $incl_vat ? ($item['unit_gross_cents'] ?? 0) : ($item['unit_net_cents'] ?? 0);
                $line_tot = $incl_vat ? ($item['line_gross_cents'] ?? 0) : ($item['line_net_cents'] ?? 0);

                echo '<tr>';
                echo '<td>' . esc_html($title) . '</td>';
                echo '<td>' . esc_html($qty) . '</td>';
                echo '<td>' . esc_html(Helpers::format_money($unit)) . '</td>';
                echo '<td>' . esc_html(Helpers::format_money($line_tot)) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            echo '<ul style="list-style:none;padding-left:0;margin-top:1em;">';
            echo '<li><strong>Subtotal:</strong> ' . esc_html(Helpers::format_money($subtotal)) . '</li>';
            echo '<li><strong>Shipping:</strong> ' . esc_html(Helpers::format_money($shipping)) . '</li>';

            if ($vat_mode !== 'none' && !empty($cart['tax_breakdown'])) {
                foreach ($cart['tax_breakdown'] as $item) {
                    echo '<li><strong>VAT (' . number_format($item['rate'], 2) . '%):</strong> '
                        . esc_html(Helpers::format_money($item['tax_cents'])) .
                        ' <small>(on ' . esc_html(Helpers::format_money($item['base_cents'])) . ')</small></li>';
                }
            }

            echo '<li><strong>Total:</strong> <strong>' . esc_html(Helpers::format_money($total)) . '</strong></li>';
            echo '</ul>';
        } else {
            echo '<p>No cart items found.</p>';
        }

        // === STRIPE SECTION ===
        echo '<h4>Stripe</h4>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><td><strong>Session ID:</strong></td><td>' . esc_html($session_id) . '</td></tr>';
        echo '<tr><td><strong>Paid:</strong></td><td>' . esc_html($paid) . '</td></tr>';

        $stripe_paid_cents = (int) get_post_meta($post->ID, '_alx_stripe_amount_total', true);
        $stripe_currency   = strtoupper(get_post_meta($post->ID, '_alx_stripe_currency', true) ?: $currency);

        if ($stripe_paid_cents > 0) {
            echo '<tr><td><strong>Paid to Stripe:</strong></td><td>' .
                esc_html(number_format($stripe_paid_cents / 100, 2)) . ' ' . esc_html($stripe_currency) . '</td></tr>';
        }

        echo '<tr><td><strong>Order Total:</strong></td><td>' . esc_html(Helpers::format_money($total)) . '</td></tr>';
        echo '</tbody></table>';
    }
}

// === Admin List Table Columns ===
add_filter('manage_edit-alox_order_columns', function ($columns) {
    return [
        'cb'       => $columns['cb'],
        'title'    => __('Order #', 'aloxstore'),
        'customer' => __('Customer', 'aloxstore'),
        'location' => __('Location', 'aloxstore'),
        'total'    => __('Total', 'aloxstore'),
        'paid'     => __('Paid', 'aloxstore'),
        'date'     => $columns['date'],
    ];
});

add_action('manage_alox_order_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'customer':
            $first = get_post_meta($post_id, '_alx_billing_first_name', true);
            $last  = get_post_meta($post_id, '_alx_billing_last_name', true);
            echo esc_html(trim("$first $last"));
            break;

        case 'location':
            $city    = get_post_meta($post_id, '_alx_billing_city', true);
            $country = get_post_meta($post_id, '_alx_billing_country', true);
            echo esc_html(trim("$city, $country"));
            break;

        case 'total':
            $total = (int) get_post_meta($post_id, '_alx_total', true);
            echo esc_html(\AloxStore\Core\Helpers::format_money($total));
            break;

        case 'paid':
            echo get_post_meta($post_id, '_alx_paid', true) ? '✔️' : '❌';
            break;
    }
}, 10, 2);
