<?php
namespace AloxStore; // optional but consistent with the rest of your plugin

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Swap ISO codes (EUR/USD/GBP) for symbols in the grid.
 * Falls back to the ISO code if not mapped.
 */
add_filter( 'aloxstore_grid_currency_display', __NAMESPACE__ . '\\aloxstore_grid_currency_display', 10, 2 );
function aloxstore_grid_currency_display( $currency, $post_id ) {
    $map = apply_filters( 'aloxstore_currency_symbol_map', [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
    ] );

    $upper = strtoupper( (string) $currency );
    return isset( $map[ $upper ] ) ? $map[ $upper ] : $upper;
}

/* NEW VERSION */

add_filter( 'alx_currency_symbol', __NAMESPACE__ . '\\alx_currency_symbol', 10, 2 );
function alx_currency_symbol( $currency, $context = null ) {
    $map = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
    ];

    $upper = strtoupper( (string) $currency );
    return isset( $map[ $upper ] ) ? $map[ $upper ] : $upper;
}
