<?php
namespace AloxStore\Core;

if (!defined('ABSPATH')) exit;

class Helpers {

    /**
     * Get an option with default (wrapper in case we change storage later).
     */
    public static function get_option($key, $default = '') {
        $val = get_option($key, $default);
        return $val;
    }

    /**
     * Format money (EUR) from cents using WP i18n.
     * Use only where you explicitly want €.
     */
    public static function money_format_eur($cents) {
        $amount = ((int)$cents) / 100;
        return sprintf('€%s', number_format_i18n($amount, 2));
    }

    /**
     * Sanitize integer cents (>= 0).
     */
    public static function sanitize_money_cents($v) {
        $v = is_numeric($v) ? (int)$v : 0;
        return max(0, $v);
    }

    /**
     * Active currency (filterable).
     */
    public static function get_active_currency() {
        $cur = get_option('alx_currency', 'EUR');
        return apply_filters('aloxstore_currency', strtoupper($cur));
    }

    /**
     * Force boolean.
     */
    public static function bool($v) {
        return (bool)$v;
    }

    /**
     * Locate a template with theme override support.
     * Theme override path: /wp-content/themes/<theme>/aloxstore/<template>
     */
    public static function locate_template($relative) {
        $relative = ltrim($relative, '/');

        $theme_path_child  = trailingslashit(get_stylesheet_directory()) . 'aloxstore/' . $relative;
        $theme_path_parent = trailingslashit(get_template_directory())   . 'aloxstore/' . $relative;

        if (file_exists($theme_path_child))  return $theme_path_child;
        if (file_exists($theme_path_parent)) return $theme_path_parent;

        // Plugin fallback
        $plugin_root = defined('ALOXSTORE_PATH')
            ? rtrim(ALOXSTORE_PATH, '/\\')
            : dirname(dirname(__DIR__)); // /aloxstore

        $plugin_path = trailingslashit($plugin_root) . 'templates/' . $relative;
        return $plugin_path;
    }

    /**
     * Render a template and return HTML (variables available as $vars array).
     */
    public static function render_template($relative, array $vars = []) {
        $template = self::locate_template($relative);
        if (!file_exists($template)) {
            return '';
        }
        ob_start();
        include $template; // Templates can read $vars directly.
        return ob_get_clean();
    }

    /**
     * Format price with global currency and position (before/after).
     */
    public static function format_money($cents) {
        $amount    = ((int)$cents) / 100;
        $currency  = strtoupper(get_option('alx_currency', 'EUR'));
        $symbol    = apply_filters('alx_currency_symbol', $currency);
        $position  = get_option('alx_currency_position', 'before');

        $formatted_amount = number_format_i18n($amount, 2);

        return $position === 'after'
            ? sprintf('%s %s', $formatted_amount, $symbol)
            : sprintf('%s %s', $symbol, $formatted_amount);
    }

    /**
     * Get the current effective product price in cents.
     * Applies sale price if active within valid date range.
     *
     * @param int $product_id
     * @return int Price in cents
     */
    public static function get_product_price_cents(int $product_id): int {
        $price_cents = (int) get_post_meta($product_id, 'price_cents', true);
        $sale_cents  = (int) get_post_meta($product_id, 'sale_price_cents', true);
        $sale_start  = get_post_meta($product_id, 'sale_start', true);
        $sale_end    = get_post_meta($product_id, 'sale_end', true);

        if ($sale_cents > 0) {
            $now = current_time('timestamp');
            $start_ok = empty($sale_start) || strtotime($sale_start) <= $now;
            $end_ok   = empty($sale_end) || strtotime($sale_end) >= $now;

            if ($start_ok && $end_ok) {
                return $sale_cents;
            }
        }

        return $price_cents;
    }
}
