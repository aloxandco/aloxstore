<?php
namespace AloxStore\CPT\Products;

if (!defined('ABSPATH')) exit;

use AloxStore\Tax\Vat;

/**
 * Product Meta Box (Enhanced)
 *
 * Adds and manages product meta fields for AloxStore products.
 */
class MetaBox {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_box']);
        add_action('save_post_alox_product', [__CLASS__, 'save']);
    }

    public static function add_box() {
        add_meta_box(
            'aloxstore_product_data',
            __('AloxStore – Product Data', 'aloxstore'),
            [__CLASS__, 'render_box'],
            'alox_product',
            'normal',
            'high'
        );
    }

    public static function render_box(\WP_Post $post) {
        wp_nonce_field('aloxstore_save_product_meta', 'aloxstore_product_meta_nonce');

        // Load all fields we care about
        $fields = [
            'sku', 'price_cents', 'currency',
            'sale_price_cents', 'sale_start', 'sale_end',
            'vat_rate_percent', 'gallery_images',
            'product_type', 'requires_shipping',
            'manage_stock', 'stock_qty', 'stock_status', 'backorders_allowed',
            'weight_grams', 'length_cm', 'width_cm', 'height_cm', 'shipping_class',
            'subtitle', 'badge_text', 'short_description',
            'cost_price_cents', 'supplier_name', 'supplier_sku', 'supplier_link'
        ];

        $meta = [];
        foreach ($fields as $field) {
            $meta[$field] = get_post_meta($post->ID, $field, true);
        }

        // Defaults
        $currencies     = apply_filters('aloxstore_currencies', ['EUR', 'USD', 'GBP']);
        $vat_rates      = Vat::get_available_rates(get_option('alx_vat_country', 'FR'));
        $user_is_admin  = current_user_can('manage_options');

        // Ensure gallery_images is always an array for JS
        $gallery_images_ids = is_array($meta['gallery_images']) ? $meta['gallery_images'] : [];
        ?>
        <style>
            .alox-tabs {
                display: flex;
                border-bottom: 1px solid #ddd;
                margin-bottom: 1rem;
            }
            .alox-tab {
                padding: 6px 12px;
                cursor: pointer;
                border: 1px solid transparent;
                border-bottom: none;
                background: #fdfdfd;
                user-select: none;
            }
            .alox-tab.active {
                background: #f0f0f0;
                border-color: #ccc;
                border-bottom: 1px solid #f0f0f0;
            }
            .alox-tab-content { display: none; }
            .alox-tab-content.active { display: block; }

            .aloxstore-fields {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px 24px;
            }
            .aloxstore-fields .field {
                display: flex;
                flex-direction: column;
            }

            /* gallery preview */
            #alx-gallery-preview {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 6px;
            }
            #alx-gallery-preview .alx-gallery-item {
                position: relative;
                width: 70px;
                height: 70px;
                cursor: move;
            }
            #alx-gallery-preview .alx-gallery-item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            #alx-gallery-preview .alx-gallery-item span {
                position: absolute;
                top: 0;
                right: 0;
                background: #dc3545;
                color: #fff;
                border-radius: 50%;
                padding: 0 5px;
                font-size: 11px;
                line-height: 1.4;
                cursor: pointer;
            }

            @media(max-width:900px){
                .aloxstore-fields { grid-template-columns: 1fr; }
            }
        </style>

        <!-- Tabs header -->
        <div class="alox-tabs" id="alx-tabs">
            <div class="alox-tab active" data-tab="tab-general"><?php esc_html_e('Commercial', 'aloxstore'); ?></div>
            <div class="alox-tab" data-tab="tab-stock"><?php esc_html_e('Inventory', 'aloxstore'); ?></div>
            <div class="alox-tab" data-tab="tab-shipping"><?php esc_html_e('Shipping', 'aloxstore'); ?></div>
            <div class="alox-tab" data-tab="tab-marketing"><?php esc_html_e('Marketing', 'aloxstore'); ?></div>
            <?php if ($user_is_admin): ?>
                <div class="alox-tab" data-tab="tab-supplier"><?php esc_html_e('Supplier', 'aloxstore'); ?></div>
            <?php endif; ?>
        </div>

        <!-- TAB: COMMERCIAL -->
        <div id="tab-general" class="alox-tab-content active">
            <div class="aloxstore-fields">

                <div class="field">
                    <label><strong><?php _e('SKU', 'aloxstore'); ?></strong></label>
                    <input type="text" name="alx_sku" value="<?php echo esc_attr($meta['sku']); ?>">
                </div>

                <div class="field">
                    <label><strong><?php _e('Base Price (cents)', 'aloxstore'); ?></strong></label>
                    <input type="number" name="alx_price_cents" value="<?php echo esc_attr($meta['price_cents']); ?>">
                </div>

                <div class="field">
                    <label><strong><?php _e('Sale Price (cents)', 'aloxstore'); ?></strong></label>
                    <input type="number" name="alx_sale_price_cents" value="<?php echo esc_attr($meta['sale_price_cents']); ?>">
                </div>

                <div class="field">
                    <label><strong><?php _e('Sale Period', 'aloxstore'); ?></strong></label>
                    <input type="datetime-local" name="alx_sale_start" value="<?php echo esc_attr($meta['sale_start']); ?>">
                    <input type="datetime-local" name="alx_sale_end" value="<?php echo esc_attr($meta['sale_end']); ?>">
                </div>

                <div class="field">
                    <label for="alx_vat_rate_percent"><strong><?php _e('VAT Rate (%)', 'aloxstore'); ?></strong></label>
                    <select id="alx_vat_rate_percent" name="alx_vat_rate_percent">
                        <?php foreach ($vat_rates as $rate): ?>
                            <option value="<?php echo esc_attr($rate); ?>" <?php selected($meta['vat_rate_percent'], $rate); ?>>
                                <?php echo esc_html($rate . '%'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select applicable VAT rate.', 'aloxstore'); ?></p>
                </div>

                <div class="field">
                    <label><strong><?php _e('Currency', 'aloxstore'); ?></strong></label>
                    <select name="alx_currency">
                        <?php foreach ($currencies as $code): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($meta['currency'], $code); ?>>
                                <?php echo esc_html($code); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label><strong><?php _e('Product Type', 'aloxstore'); ?></strong></label>
                    <select name="alx_product_type" id="alx_product_type">
                        <?php
                        $types = [
                            'simple'       => 'Simple',
                            'service'      => 'Service',
                            'digital'      => 'Digital',
                            'bundle'       => 'Bundle',
                            'subscription' => 'Subscription',
                        ];
                        foreach ($types as $key => $label) {
                            echo '<option value="' . esc_attr($key) . '" ' . selected($meta['product_type'], $key, false) . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <!-- GALLERY -->
                <div class="field" style="grid-column: span 2;">
                    <label><strong><?php _e('Additional Images', 'aloxstore'); ?></strong></label>

                    <div id="alx-gallery-wrapper">
                        <div id="alx-gallery-preview"></div>

                        <button type="button"
                                class="button button-secondary mt-2"
                                id="alx-add-gallery">
                            <?php esc_html_e('Add Images', 'aloxstore'); ?>
                        </button>

                        <!-- hidden field that actually stores the IDs -->
                        <input type="hidden"
                               name="alx_gallery_images"
                               id="alx_gallery_images"
                               value="<?php echo esc_attr( wp_json_encode( $gallery_images_ids ) ); ?>">
                    </div>
                </div>

            </div>
        </div>

        <!-- TAB: INVENTORY -->
        <div id="tab-stock" class="alox-tab-content">
            <div class="aloxstore-fields">
                <div class="field">
                    <label>
                        <input type="checkbox" name="alx_manage_stock" value="1" <?php checked($meta['manage_stock'], true); ?>>
                        <?php _e('Manage Stock', 'aloxstore'); ?>
                    </label>
                </div>

                <div class="field">
                    <label><strong><?php _e('Stock Quantity', 'aloxstore'); ?></strong></label>
                    <input type="number" name="alx_stock_qty" value="<?php echo esc_attr($meta['stock_qty']); ?>">
                </div>

                <div class="field">
                    <label><strong><?php _e('Stock Status', 'aloxstore'); ?></strong></label>
                    <select name="alx_stock_status">
                        <?php
                        $statuses = [
                            'in_stock'      => 'In stock',
                            'out_of_stock'  => 'Out of stock',
                            'on_backorder'  => 'On backorder'
                        ];
                        foreach ($statuses as $k=>$l) {
                            echo '<option value="' . esc_attr($k) . '" ' . selected($meta['stock_status'], $k, false) . '>' . esc_html($l) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="field">
                    <label>
                        <input type="checkbox" name="alx_backorders_allowed" value="1" <?php checked($meta['backorders_allowed'], true); ?>>
                        <?php _e('Allow backorders', 'aloxstore'); ?>
                    </label>
                </div>
            </div>
        </div>

        <!-- TAB: SHIPPING -->
        <div id="tab-shipping" class="alox-tab-content">
            <div class="aloxstore-fields">
                <div class="field">
                    <label>
                        <input type="checkbox" name="alx_requires_shipping" value="1" <?php checked($meta['requires_shipping'], true); ?>>
                        <?php _e('Requires Shipping', 'aloxstore'); ?>
                    </label>
                </div>

                <div class="field">
                    <label><strong><?php _e('Weight (grams)', 'aloxstore'); ?></strong></label>
                    <input type="number" name="alx_weight_grams" value="<?php echo esc_attr($meta['weight_grams']); ?>">
                </div>

                <div class="field">
                    <label><strong><?php _e('Dimensions (cm)', 'aloxstore'); ?></strong></label>
                    <input type="number" placeholder="Length" name="alx_length_cm" value="<?php echo esc_attr($meta['length_cm']); ?>">
                    <input type="number" placeholder="Width"  name="alx_width_cm"  value="<?php echo esc_attr($meta['width_cm']); ?>">
                    <input type="number" placeholder="Height" name="alx_height_cm" value="<?php echo esc_attr($meta['height_cm']); ?>">
                </div>

                <div class="field">
                    <label><strong><?php _e('Shipping Class', 'aloxstore'); ?></strong></label>
                    <input type="text" name="alx_shipping_class" value="<?php echo esc_attr($meta['shipping_class']); ?>">
                </div>
            </div>
        </div>

        <!-- TAB: MARKETING -->
        <div id="tab-marketing" class="alox-tab-content">
            <div class="aloxstore-fields">
                <div class="field">
                    <label><strong><?php _e('Subtitle', 'aloxstore'); ?></strong></label>
                    <input type="text" name="alx_subtitle" value="<?php echo esc_attr($meta['subtitle']); ?>">
                </div>

                <div class="field">
                    <label><strong><?php _e('Badge Text', 'aloxstore'); ?></strong></label>
                    <input type="text" name="alx_badge_text" value="<?php echo esc_attr($meta['badge_text']); ?>">
                </div>

                <div class="field" style="grid-column: span 2;">
                    <label><strong><?php _e('Short Description', 'aloxstore'); ?></strong></label>
                    <textarea name="alx_short_description" rows="3"><?php echo esc_textarea($meta['short_description']); ?></textarea>
                </div>
            </div>
        </div>

        <!-- TAB: SUPPLIER -->
        <?php if ($user_is_admin): ?>
            <div id="tab-supplier" class="alox-tab-content">
                <div class="aloxstore-fields">
                    <div class="field">
                        <label><strong><?php _e('Cost Price (cents)', 'aloxstore'); ?></strong></label>
                        <input type="number" name="alx_cost_price_cents" value="<?php echo esc_attr($meta['cost_price_cents']); ?>">
                    </div>

                    <div class="field">
                        <label><strong><?php _e('Supplier Name', 'aloxstore'); ?></strong></label>
                        <input type="text" name="alx_supplier_name" value="<?php echo esc_attr($meta['supplier_name']); ?>">
                    </div>

                    <div class="field">
                        <label><strong><?php _e('Supplier SKU', 'aloxstore'); ?></strong></label>
                        <input type="text" name="alx_supplier_sku" value="<?php echo esc_attr($meta['supplier_sku']); ?>">
                    </div>

                    <div class="field">
                        <label><strong><?php _e('Supplier Link', 'aloxstore'); ?></strong></label>
                        <input type="url" name="alx_supplier_link" value="<?php echo esc_attr($meta['supplier_link']); ?>">
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabs script (safe, independent) -->
        <script>
            (function() {
                const tabs     = document.querySelectorAll('#alx-tabs .alox-tab');
                const contents = document.querySelectorAll('.alox-tab-content');

                if (!tabs.length || !contents.length) return;

                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                const targetId = tab.getAttribute('data-tab');
                const target   = document.getElementById(targetId);
                if (target) target.classList.add('active');
            });
            });
            })();
        </script>

        <!-- Gallery script (isolated & guarded) -->
        <script>
            (function(){
                // bail if media frame or elements aren't there
                if (typeof wp === 'undefined' || !wp.media) return;

                const addBtn        = document.getElementById('alx-add-gallery');
                const preview       = document.getElementById('alx-gallery-preview');
                const hiddenInput   = document.getElementById('alx_gallery_images');

                if (!addBtn || !preview || !hiddenInput) return;

                // parse stored value safely
                let existing = [];
                try {
                    existing = JSON.parse(hiddenInput.value || '[]');
                    if (!Array.isArray(existing)) existing = [];
                } catch(e) {
                    existing = [];
                }

                function render() {
                    preview.innerHTML = '';

                    existing.forEach((attId, idx) => {
                        const attachment = wp.media.attachment(attId);
                    // fetch() returns a promise to make sure we have URL
                    attachment.fetch().then(() => {
                        const url = attachment.get('url');
                    if (!url) return;

                    const wrap   = document.createElement('div');
                    wrap.className = 'alx-gallery-item';
                    wrap.dataset.index = String(idx);
                    wrap.draggable = true;

                    const img    = document.createElement('img');
                    img.src      = url;
                    img.alt      = '';

                    const remove = document.createElement('span');
                    remove.textContent = '×';
                    remove.title       = '<?php echo esc_js(__('Remove', 'aloxstore')); ?>';
                    remove.addEventListener('click', (e) => {
                        e.stopPropagation();
                    existing.splice(idx, 1);
                    hiddenInput.value = JSON.stringify(existing);
                    render();
                });

                    wrap.appendChild(img);
                    wrap.appendChild(remove);
                    preview.appendChild(wrap);
                });
                });
                }

                render();

                // add images button
                addBtn.addEventListener('click', function(e){
                    e.preventDefault();

                    const frame = wp.media({
                        title:  '<?php echo esc_js(__('Select additional images', 'aloxstore')); ?>',
                        button: { text: '<?php echo esc_js(__('Use these images', 'aloxstore')); ?>' },
                        multiple: true
                    });

                    frame.on('select', function() {
                        const sel = frame.state().get('selection').toJSON();
                        sel.forEach(att => {
                            if (att.id) existing.push(att.id);
                    });
                        hiddenInput.value = JSON.stringify(existing);
                        render();
                    });

                    frame.open();
                });

                // drag + drop reordering
                let draggedEl = null;

                preview.addEventListener('dragstart', function(e){
                    const item = e.target.closest('.alx-gallery-item');
                    if (!item) return;
                    draggedEl = item;
                    e.dataTransfer.effectAllowed = 'move';
                    item.style.opacity = '0.5';
                });

                preview.addEventListener('dragend', function(e){
                    const item = e.target.closest('.alx-gallery-item');
                    if (item) item.style.opacity = '';
                });

                preview.addEventListener('dragover', function(e){
                    e.preventDefault();
                    const overItem = e.target.closest('.alx-gallery-item');
                    if (!overItem || overItem === draggedEl) return;

                    const items     = Array.from(preview.querySelectorAll('.alx-gallery-item'));
                    const fromIndex = items.indexOf(draggedEl);
                    const toIndex   = items.indexOf(overItem);

                    if (fromIndex < toIndex) {
                        preview.insertBefore(draggedEl, overItem.nextSibling);
                    } else {
                        preview.insertBefore(draggedEl, overItem);
                    }
                });

                preview.addEventListener('drop', function(e){
                    e.preventDefault();

                    const newOrder = Array.from(preview.querySelectorAll('.alx-gallery-item')).map(el => {
                        const originalIndex = parseInt(el.dataset.index, 10);
                    return existing[originalIndex];
                }).filter(Boolean);

                    existing = newOrder;
                    hiddenInput.value = JSON.stringify(existing);
                    render();
                });
            })();
        </script>

        <?php
    }


    public static function save($post_id) {
        if (!isset($_POST['aloxstore_product_meta_nonce'])
            || !wp_verify_nonce($_POST['aloxstore_product_meta_nonce'], 'aloxstore_save_product_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $map = [
            'alx_sku' => 'sku',
            'alx_price_cents' => 'price_cents',
            'alx_currency' => 'currency',
            'alx_sale_price_cents' => 'sale_price_cents',
            'alx_sale_start' => 'sale_start',
            'alx_sale_end' => 'sale_end',
            'alx_vat_rate_percent' => 'vat_rate_percent',
            'alx_product_type' => 'product_type',
            'alx_requires_shipping' => 'requires_shipping',
            'alx_manage_stock' => 'manage_stock',
            'alx_stock_qty' => 'stock_qty',
            'alx_stock_status' => 'stock_status',
            'alx_backorders_allowed' => 'backorders_allowed',
            'alx_weight_grams' => 'weight_grams',
            'alx_length_cm' => 'length_cm',
            'alx_width_cm' => 'width_cm',
            'alx_height_cm' => 'height_cm',
            'alx_shipping_class' => 'shipping_class',
            'alx_subtitle' => 'subtitle',
            'alx_badge_text' => 'badge_text',
            'alx_short_description' => 'short_description',
            'alx_cost_price_cents' => 'cost_price_cents',
            'alx_supplier_name' => 'supplier_name',
            'alx_supplier_sku' => 'supplier_sku',
            'alx_supplier_link' => 'supplier_link',
        ];

        foreach ($map as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                $val = wp_unslash($_POST[$post_key]);
                update_post_meta($post_id, $meta_key, $val);
            } else {
                if (in_array($post_key, ['alx_requires_shipping','alx_manage_stock','alx_backorders_allowed'], true)) {
                    update_post_meta($post_id, $meta_key, 0);
                }
            }
        }

        // Save gallery
        if (isset($_POST['alx_gallery_images'])) {
            $images = json_decode(stripslashes($_POST['alx_gallery_images']), true);
            if (is_array($images)) {
                update_post_meta($post_id, 'gallery_images', array_map('intval', $images));
            }
        }
    }
}

MetaBox::init();
