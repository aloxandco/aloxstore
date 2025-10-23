<?php
namespace AloxStore\Tax;

if (!defined('ABSPATH')) exit;

class Vat {
    // 🔧 VAT rates by country (expand as needed)
    protected static $rates = [
        'FR' => [20.0, 10.0, 5.5, 2.1, 0.0],
        'NL' => [21.0, 9.0, 0.0],
    ];

    /**
     * Compute VAT breakdown for a given amount (in cents)
     */
    public static function compute_line(int $gross_cents, float $rate_percent, bool $prices_include_tax = true): array {
        $rate = max(0.0, $rate_percent) / 100.0;

        if ($prices_include_tax) {
            $net = (int) round($gross_cents / (1 + $rate));
            $tax = $gross_cents - $net;
            return ['net' => $net, 'tax' => $tax, 'gross' => $gross_cents];
        } else {
            $tax = (int) round($gross_cents * $rate);
            $gross = $gross_cents + $tax;
            return ['net' => $gross_cents, 'tax' => $tax, 'gross' => $gross];
        }
    }

    /**
     * Get list of available VAT rates (as float values) for a given country
     */
    public static function get_available_rates(string $country): array {
        $country = strtoupper($country);

        if ($country === 'CUSTOM') {
            $custom_rates = get_option('alx_vat_custom_rates', '');
            return self::parse_custom_rates($custom_rates);
        }

        return self::$rates[$country] ?? [];
    }

    /**
     * Convert comma-separated string to float array (e.g. "20,10,5.5,0")
     */
    protected static function parse_custom_rates(string $input): array {
        $parts = array_map('trim', explode(',', $input));
        $rates = [];

        foreach ($parts as $part) {
            if (is_numeric($part)) {
                $rates[] = (float) $part;
            }
        }

        return $rates;
    }

    /**
     * Return all supported VAT countries
     */
    public static function get_supported_countries(): array {
        return array_keys(self::$rates);
    }

}
