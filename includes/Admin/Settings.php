<?php
namespace AloxStore\Admin;

if (!defined('ABSPATH')) exit;

class Settings {

    public static function hooks() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function menu() {
        add_options_page(
            'AloxStore',
            'AloxStore',
            'manage_options',
            'aloxstore',
            [__CLASS__, 'render']
        );
    }

    public static function register() {
        // === GENERAL SETTINGS ===
        register_setting('aloxstore', 'alx_mode', [
            'type'              => 'string',
            'sanitize_callback' => fn($v) => in_array($v, ['test', 'live'], true) ? $v : 'test',
            'default'           => 'test',
        ]);

        register_setting('aloxstore', 'alx_currency', [
            'type'              => 'string',
            'sanitize_callback' => function ($v) {
                $v = strtoupper(sanitize_text_field($v));
                return $v ?: 'EUR';
            },
            'default' => 'EUR',
        ]);

        // ✅ New currency position setting
        register_setting('aloxstore', 'alx_currency_position', [
            'type'              => 'string',
            'sanitize_callback' => fn($v) => in_array($v, ['before', 'after'], true) ? $v : 'before',
            'default'           => 'before',
        ]);

        // === STRIPE SETTINGS ===
        foreach ([
                     'alx_stripe_test_pk', 'alx_stripe_test_sk',
                     'alx_stripe_live_pk', 'alx_stripe_live_sk',
                     'alx_stripe_webhook_secret'
                 ] as $key) {
            register_setting('aloxstore', $key, [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]);
        }

        // === SHIPPING SETTINGS ===
        register_setting('aloxstore', 'alx_flat_rate_cents', [
            'type'              => 'integer',
            'sanitize_callback' => fn($v) => max(0, (int)$v),
            'default'           => 0,
        ]);

        register_setting('aloxstore', 'alx_free_shipping_min_cents', [
            'type'              => 'integer',
            'sanitize_callback' => fn($v) => max(0, (int)$v),
            'default'           => 0,
        ]);

        // === VAT SETTINGS ===
        register_setting('aloxstore', 'alx_vat_mode', [
            'type'              => 'string',
            'sanitize_callback' => function ($v) {
                return in_array($v, ['enabled', 'none'], true) ? $v : 'enabled';
            },
            'default' => 'enabled',
        ]);

        register_setting('aloxstore', 'alx_prices_include_tax', [
            'type'              => 'boolean',
            'sanitize_callback' => fn($v) => (bool)$v,
            'default'           => true,
        ]);

        register_setting('aloxstore', 'alx_vat_country', [
            'type'              => 'string',
            'sanitize_callback' => function ($v) {
                $v = strtoupper(sanitize_text_field($v));
                return preg_match('/^[A-Z]{2}$/', $v) ? $v : 'FR';
            },
            'default' => 'FR',
        ]);
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AloxStore Settings', 'aloxstore'); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields('aloxstore'); ?>

                <!-- === GENERAL SETTINGS === -->
                <h2 class="title"><?php esc_html_e('General', 'aloxstore'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Mode', 'aloxstore'); ?></th>
                        <td>
                            <?php $mode = get_option('alx_mode', 'test'); ?>
                            <select name="alx_mode">
                                <option value="test" <?php selected($mode, 'test'); ?>><?php esc_html_e('Test', 'aloxstore'); ?></option>
                                <option value="live" <?php selected($mode, 'live'); ?>><?php esc_html_e('Live', 'aloxstore'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Currency (ISO)', 'aloxstore'); ?></th>
                        <td>
                            <input type="text" name="alx_currency" value="<?php echo esc_attr(get_option('alx_currency', 'EUR')); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Example: EUR, USD, GBP', 'aloxstore'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Currency Position', 'aloxstore'); ?></th>
                        <td>
                            <?php $pos = get_option('alx_currency_position', 'before'); ?>
                            <select name="alx_currency_position">
                                <option value="before" <?php selected($pos, 'before'); ?>><?php esc_html_e('Before amount (e.g. € 25.00)', 'aloxstore'); ?></option>
                                <option value="after" <?php selected($pos, 'after'); ?>><?php esc_html_e('After amount (e.g. 25.00 €)', 'aloxstore'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose whether the currency symbol appears before or after the amount.', 'aloxstore'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- === STRIPE SETTINGS === -->
                <h2 class="title"><?php esc_html_e('Stripe', 'aloxstore'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th><?php esc_html_e('Test Publishable Key', 'aloxstore'); ?></th><td><input type="text" name="alx_stripe_test_pk" value="<?php echo esc_attr(get_option('alx_stripe_test_pk', '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Test Secret Key', 'aloxstore'); ?></th><td><input type="text" name="alx_stripe_test_sk" value="<?php echo esc_attr(get_option('alx_stripe_test_sk', '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Live Publishable Key', 'aloxstore'); ?></th><td><input type="text" name="alx_stripe_live_pk" value="<?php echo esc_attr(get_option('alx_stripe_live_pk', '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Live Secret Key', 'aloxstore'); ?></th><td><input type="text" name="alx_stripe_live_sk" value="<?php echo esc_attr(get_option('alx_stripe_live_sk', '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Webhook Signing Secret', 'aloxstore'); ?></th><td><input type="text" name="alx_stripe_webhook_secret" value="<?php echo esc_attr(get_option('alx_stripe_webhook_secret', '')); ?>" class="regular-text" placeholder="whsec_..."></td></tr>
                </table>

                <!-- === SHIPPING SETTINGS === -->
                <h2 class="title"><?php esc_html_e('Shipping', 'aloxstore'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th><?php esc_html_e('Flat rate (cents)', 'aloxstore'); ?></th><td><input type="number" min="0" step="1" name="alx_flat_rate_cents" value="<?php echo esc_attr((int)get_option('alx_flat_rate_cents', 0)); ?>"></td></tr>
                    <tr><th><?php esc_html_e('Free shipping threshold (cents)', 'aloxstore'); ?></th><td><input type="number" min="0" step="1" name="alx_free_shipping_min_cents" value="<?php echo esc_attr((int)get_option('alx_free_shipping_min_cents', 0)); ?>"></td></tr>
                </table>

                <!-- === TAX SETTINGS === -->
                <h2 class="title"><?php esc_html_e('Tax (VAT)', 'aloxstore'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e('VAT Mode', 'aloxstore'); ?></th>
                        <td>
                            <?php $mode = get_option('alx_vat_mode', 'enabled'); ?>
                            <select name="alx_vat_mode" id="alx_vat_mode">
                                <option value="enabled" <?php selected($mode, 'enabled'); ?>><?php esc_html_e('Enabled (normal VAT rules)', 'aloxstore'); ?></option>
                                <option value="none" <?php selected($mode, 'none'); ?>><?php esc_html_e('VAT not applicable (auto‑entrepreneur)', 'aloxstore'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Select “VAT not applicable” if you are exempt from VAT (e.g. auto‑entrepreneur, art. 293 B du CGI).', 'aloxstore'); ?></p>
                        </td>
                    </tr>
                    <tr class="vat-enabled-only">
                        <th><?php esc_html_e('Prices include tax (VAT)?', 'aloxstore'); ?></th>
                        <td><label><input type="checkbox" name="alx_prices_include_tax" value="1" <?php checked((bool)get_option('alx_prices_include_tax', true)); ?>> <?php esc_html_e('Yes', 'aloxstore'); ?></label></td>
                    </tr>
                    <tr class="vat-enabled-only">
                        <th><?php esc_html_e('VAT Country', 'aloxstore'); ?></th>
                        <td>
                            <?php
                            $selected = strtoupper(get_option('alx_vat_country', 'FR'));
                            $countries = \AloxStore\Tax\Vat::get_supported_countries();
                            sort($countries);
                            ?>
                            <select name="alx_vat_country" id="alx_vat_country">
                                <?php foreach ($countries as $code): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($selected, $code); ?>>
                                        <?php echo esc_html($code); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const vatMode = document.getElementById('alx_vat_mode');
                const vatRows = document.querySelectorAll('.vat-enabled-only');
                function toggleVatRows() {
                    const show = vatMode.value === 'enabled';
                    vatRows.forEach(row => row.style.display = show ? '' : 'none');
                }
                toggleVatRows();
                vatMode.addEventListener('change', toggleVatRows);
            });
        </script>
        <?php
    }
}
