(function () {

    function getGlobals() {
        return (typeof window.aloxstore !== 'undefined')
            ? window.aloxstore
            : (typeof window.AloxStore !== 'undefined')
                ? window.AloxStore
                : {
                    rest: '/wp-json/aloxstore/v1/',
                    nonce: '',
                    cart_url: '/cart',
                    checkout_url: '/checkout'
                };
    }

    async function rest(path, method, data) {
        var g = getGlobals();
        var res = await fetch((g.rest || '/wp-json/aloxstore/v1/') + path, {
            method: method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': g.nonce || ''
            },
            body: data ? JSON.stringify(data) : undefined,
            credentials: 'same-origin'
        });

        var txt = await res.text();
        var json = {};
        try {
            json = txt ? JSON.parse(txt) : {};
        } catch (e) {
            json = { message: txt };
        }

        if (!res.ok) {
            var err = new Error(json.message || ('HTTP ' + res.status));
            err.status = res.status;
            err.payload = json;
            throw err;
        }

        return json;
    }

    function findQtyForButton(btn) {
        var attrQty = parseInt(btn.getAttribute('data-qty'), 10);
        if (!isNaN(attrQty) && attrQty > 0) return attrQty;

        var container = btn.closest('.card, .site-main, form') || document;
        var input = container.querySelector('#alx_qty, .alx-qty, input[name="quantity"]');
        var v = input ? parseInt(input.value, 10) : 1;
        return (isNaN(v) || v < 1) ? 1 : v;
    }

    // BUY: add to cart → go to Cart page
    document.addEventListener('click', async function (e) {
        var btn = e.target.closest('.alx-buy');
        if (!btn) return;

        e.preventDefault();
        var productId = parseInt(btn.getAttribute('data-product'), 10);
        if (!productId) return;

        var qty = findQtyForButton(btn);
        try {
            await rest('cart/add', 'POST', { product_id: productId, qty: qty });
            var g = getGlobals();
            window.location.href = g.cart_url || '/cart';
        } catch (err) {
            console.error('[AloxStore] add-to-cart error:', err);
            alert(err.status === 403
                ? 'Session expired. Refresh and try again.'
                : 'Could not add to cart.'
            );
        }
    });

    // CART: update qty
    document.addEventListener('click', async function (e) {
        var btn = e.target.closest('.alx-update-qty');
        if (!btn) return;

        e.preventDefault();
        var container = btn.closest('tr[data-product], .card[data-product]');
        var pid = container ? parseInt(container.getAttribute('data-product'), 10) : 0;
        var input = container ? container.querySelector('.alx-qty') : null;
        var qty = input ? parseInt(input.value, 10) : 1;
        qty = (isNaN(qty) || qty < 0) ? 0 : qty;

        try {
            await rest('cart/set-qty', 'POST', { product_id: pid, qty: qty });
            window.location.reload();
        } catch (err) {
            console.error('[AloxStore] set-qty error:', err);
            alert('Could not update quantity.');
        }
    });

// CART: remove line
    document.addEventListener('click', async function (e) {
        var btn = e.target.closest('.alx-remove');
        if (!btn) return;

        e.preventDefault();
        var container = btn.closest('tr[data-product], .card[data-product]');
        var pid = container ? parseInt(container.getAttribute('data-product'), 10) : 0;

        try {
            await rest('cart/remove', 'POST', { product_id: pid });
            window.location.reload();
        } catch (err) {
            console.error('[AloxStore] remove error:', err);
            alert('Could not remove item.');
        }
    });

// CART: clear
    document.addEventListener('click', async function (e) {
        var btn = e.target.closest('.alx-clear-cart');
        if (!btn) return;

        e.preventDefault();
        try {
            await rest('cart/clear', 'POST', {});
            window.location.reload();
        } catch (err) {
            console.error('[AloxStore] clear error:', err);
            alert('Could not clear cart.');
        }
    });

    // CART: proceed to Checkout page
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.alx-checkout');
        if (!btn) return;

        e.preventDefault();
        var g = getGlobals();
        window.location.href = g.checkout_url || '/checkout';
    });

    // Collect billing + shipping fields
    function getFormData(form) {
        const data = {};
        new FormData(form).forEach((value, key) => data[key] = value.trim());
        return data;
    }

    // CHECKOUT: Save customer + create Stripe session
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.alx-checkout-submit');
        if (!btn) return;

        const form = btn.closest('form.alx-checkout-form');
        if (!form) return;

        e.preventDefault();
        if (btn.disabled) return;

        const payload = getFormData(form);

        // Required billing fields
        const required = [
            'billing_first_name', 'billing_last_name', 'billing_email',
            'billing_address_1', 'billing_postcode', 'billing_city',
            'billing_country', 'billing_phone'
        ];

        for (let i = 0; i < required.length; i++) {
            if (!payload[required[i]]) {
                alert('Please complete all required fields.');
                return;
            }
        }

        // 🔄 Show spinner and disable button
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Processing...
        `;

        try {
            // 1️⃣ Save customer info (creates or updates WP + Stripe)
            await rest('checkout/customer', 'POST', payload);

            // 2️⃣ Create Stripe Checkout Session
            const out = await rest('checkout', 'POST', {});
            if (out && out.url) {
                window.location.href = out.url;
            } else {
                alert('Checkout error: could not create Stripe session.');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        } catch (err) {
            console.error('[AloxStore] checkout error:', err);
            alert(err.status === 403
                ? 'Session expired. Refresh and try again.'
                : (err.payload?.message || 'Could not start checkout.')
        );
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });

})();
