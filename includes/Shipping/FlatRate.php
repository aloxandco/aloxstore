<?php
namespace AloxStore\Shipping;

if (!defined('ABSPATH')) exit;

class FlatRate {
    public static function compute(array $cart): array {
        $rate_cents = (int) get_option('alx_flat_rate_cents', 0);
        $free_min   = (int) get_option('alx_free_shipping_min_cents', 0);
        $subtotal   = self::subtotal_cents($cart);

        $amount = ($free_min > 0 && $subtotal >= $free_min) ? 0 : $rate_cents;
        return ['label' => __('Standard Shipping','aloxstore'), 'amount_cents' => max(0,(int)$amount)];
    }

    protected static function subtotal_cents(array $cart): int {
        $sum = 0;
        foreach ($cart['items'] as $it) {
            $pid = (int)$it['product_id'];
            $qty = (int)$it['qty'];
            $price = (int) get_post_meta($pid, 'price_cents', true);
            $sum += max(0,$price) * max(1,$qty);
        }
        return $sum;
    }
}
