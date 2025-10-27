<?php
namespace AloxStore\Cart;

use AloxStore\Tax\Vat;

if (!defined('ABSPATH')) exit;

class CartPricing
{
    public static function calculate(array $cart): array
    {
        $currency     = get_option('alx_currency', 'EUR');
        $vat_mode     = get_option('alx_vat_mode', 'enabled');
        $include_tax  = (bool) get_option('alx_prices_include_tax', false);
        $net_total    = 0;
        $gross_total  = 0;
        $totals_by_rate = [];

        // Normalize input: accept either ['items'] or ['lines'] from saved cart
        if (empty($cart['lines']) && !empty($cart['items']) && is_array($cart['items'])) {
            // Convert legacy or raw cart structure
            $cart['lines'] = [];
            foreach ($cart['items'] as $item) {
                $cart['lines'][] = [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'qty'        => max(1, (int) ($item['qty'] ?? 1)),
                ];
            }
        }

// Ensure $cart['lines'] exists and is an array
        if (empty($cart['lines']) || !is_array($cart['lines'])) {
            $cart['lines'] = [];
        }


        // === LINE CALCULATION ===
        foreach ($cart['lines'] as &$line) {
            $pid = (int)($line['product_id'] ?? 0);
            $qty = max(1, (int)($line['qty'] ?? 1));

            // Force currency to global store currency
            $line['currency'] = $currency;

            // Get product base price
            $unit_cents = (int)($line['unit_cents'] ?? 0);
            if ($pid && $unit_cents <= 0) {
                $unit_cents = (int)get_post_meta($pid, 'price_cents', true);
            }

            // VAT rate (default to 0 if disabled)
            $vat_rate = ($vat_mode === 'enabled')
                ? (isset($line['vat_rate_percent']) ? (float)$line['vat_rate_percent'] : (float)get_post_meta($pid, 'vat_rate_percent', true))
                : 0.0;

            // Compute VAT/net/gross depending on store setting
            $calc = Vat::compute_line($unit_cents, $vat_rate, $include_tax);
            $unit_net   = $calc['net'];
            $unit_tax   = $calc['tax'];
            $unit_gross = $calc['gross'];

            $line_net   = $unit_net * $qty;
            $line_gross = $unit_gross * $qty;
            $line_tax   = $unit_tax * $qty;

            // Group totals per VAT rate (if active)
            if ($vat_mode === 'enabled') {
                $totals_by_rate[$vat_rate] = ($totals_by_rate[$vat_rate] ?? 0) + $line_net;
            }

            // Store line info
            $line['qty']              = $qty;
            $line['unit_net_cents']   = $unit_net;
            $line['unit_tax_cents']   = $unit_tax;
            $line['unit_gross_cents'] = $unit_gross;
            $line['vat_rate_percent'] = $vat_rate;
            $line['line_net_cents']   = $line_net;
            $line['line_tax_cents']   = $line_tax;
            $line['line_gross_cents'] = $line_gross;

            $net_total   += $line_net;
            $gross_total += $line_gross;
        }

        // === SHIPPING ===
        $shipping_net = (int)get_option('alx_flat_rate_cents', 0);
        $free_min     = (int)get_option('alx_free_shipping_min_cents', 0);
        if ($free_min > 0 && $net_total >= $free_min) {
            $shipping_net = 0;
        }

        $rates = Vat::get_available_rates(get_option('alx_vat_country', 'FR'));
        $shipping_vat_rate = ($vat_mode === 'enabled' && !empty($rates))
            ? (float)max($rates)
            : 0.0;

        $shipping_calc = Vat::compute_line($shipping_net, $shipping_vat_rate, $include_tax);
        $shipping_net   = $shipping_calc['net'];
        $shipping_tax   = $shipping_calc['tax'];
        $shipping_gross = $shipping_calc['gross'];

        if ($vat_mode === 'enabled') {
            $totals_by_rate[$shipping_vat_rate] = ($totals_by_rate[$shipping_vat_rate] ?? 0) + $shipping_net;
        }

        // === VAT BREAKDOWN (if active) ===
        $tax_cents = 0;
        $cart['tax_breakdown'] = [];

        if ($vat_mode === 'enabled') {
            foreach ($totals_by_rate as $rate => $base_net) {
                $tax = (int) round($base_net * ($rate / 100));
                $cart['tax_breakdown'][] = [
                    'rate'       => $rate,
                    'base_cents' => $base_net,
                    'tax_cents'  => $tax,
                ];
                $tax_cents += $tax;
            }
        }

        // === FINAL TOTALS ===
        $total_net   = $net_total + $shipping_net;
        $total_gross = ($include_tax || $vat_mode === 'none')
            ? $gross_total + $shipping_gross
            : $total_net + $tax_cents;

        // === FINAL CART STRUCTURE ===
        $cart['currency']        = $currency;
        $cart['subtotal_cents']  = $net_total;
        $cart['shipping_cents']  = $shipping_net;
        $cart['tax_cents']       = $tax_cents;
        $cart['total_cents']     = $total_gross;

        return $cart;
    }
}
