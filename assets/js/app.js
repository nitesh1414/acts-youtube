/* PayAccess — public site scripts (checkout, copy buttons, PWA registration) */
(function () {
    'use strict';

    /* --------------------------------------------------------------------- */
    /* Small helpers                                                          */
    /* --------------------------------------------------------------------- */
    function showError(container, message) {
        if (!container) {
            window.alert(message);
            return;
        }
        container.textContent = message;
        container.hidden = false;
    }

    function clearError(container) {
        if (container) {
            container.hidden = true;
            container.textContent = '';
        }
    }

    function setBusy(button, busy, label) {
        if (!button) { return; }
        const labelNode = button.querySelector('.btn__label');
        if (busy) {
            button.dataset.idleLabel = labelNode ? labelNode.textContent : button.textContent;
            button.disabled = true;
            if (labelNode) {
                labelNode.textContent = 'Processing…';
            } else {
                button.textContent = 'Processing…';
            }
            button.classList.add('is-busy');
        } else {
            button.disabled = false;
            if (labelNode && button.dataset.idleLabel) {
                labelNode.textContent = button.dataset.idleLabel;
            }
            button.classList.remove('is-busy');
        }
    }

    async function postJSON(url, payload, csrf) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrf || ''
            },
            body: JSON.stringify(payload || {})
        });

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            // Non-JSON body: usually a PHP fatal error or a server misconfiguration.
            data = {
                error: 'The server returned an unexpected response (HTTP ' + response.status + '). '
                    + 'Please try again — if it keeps failing, the site owner should check storage/logs.'
            };
        }

        if (!response.ok) {
            const error = new Error(data.error || 'Something went wrong. Please try again.');
            error.payload = data;
            throw error;
        }
        return data;
    }

    /* --------------------------------------------------------------------- */
    /* Copy to clipboard                                                      */
    /* --------------------------------------------------------------------- */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
            } catch (e) {
                reject(e);
            } finally {
                document.body.removeChild(area);
            }
        });
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-copy]');
        if (!trigger) { return; }
        event.preventDefault();
        copyText(trigger.getAttribute('data-copy')).then(function () {
            const original = trigger.innerHTML;
            trigger.innerHTML = '✓ Copied';
            trigger.classList.add('is-copied');
            setTimeout(function () {
                trigger.innerHTML = original;
                trigger.classList.remove('is-copied');
            }, 1600);
        }).catch(function () {
            window.alert('Could not copy automatically — please select and copy the text manually.');
        });
    });

    /* --------------------------------------------------------------------- */
    /* Password visibility + select-all inputs                                */
    /* --------------------------------------------------------------------- */
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-toggle-password]');
        if (!toggle) { return; }
        const input = document.querySelector(toggle.getAttribute('data-toggle-password'));
        if (!input) { return; }
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    document.addEventListener('focusin', function (event) {
        if (event.target.matches('[data-select-on-focus]')) {
            event.target.select();
        }
    });

    /* --------------------------------------------------------------------- */
    /* Product selection (videos / bundles → checkout card)                   */
    /* --------------------------------------------------------------------- */
    function selectProduct(trigger) {
        const form = document.getElementById('payment-form');
        if (!form) { return; }

        const type = trigger.getAttribute('data-type') || 'video';
        const id = trigger.getAttribute('data-id') || '0';
        const name = trigger.getAttribute('data-name') || '';
        const label = trigger.getAttribute('data-label') || '';
        const period = trigger.getAttribute('data-period') || '';
        const paise = trigger.getAttribute('data-price') || '0';

        const typeInput = form.querySelector('#item-type');
        const idInput = form.querySelector('#item-id');
        if (typeInput) { typeInput.value = type; }
        if (idInput) { idInput.value = id; }

        form.dataset.amountPaise = paise;
        form.dataset.amountLabel = label;
        form.dataset.productName = name;

        const nameNode = document.getElementById('pay-product-name');
        const priceNode = document.getElementById('pay-price');
        const periodNode = document.getElementById('pay-period');
        const buttonLabel = form.querySelector('#pay-button .btn__label');

        if (nameNode) { nameNode.textContent = name; }
        if (priceNode) { priceNode.textContent = label; }
        if (periodNode) { periodNode.textContent = period ? '/ ' + period : ''; }
        if (buttonLabel) { buttonLabel.textContent = 'Pay ' + label + ' & unlock'; }

        document.querySelectorAll('[data-product-card]').forEach(function (card) {
            card.classList.toggle('is-selected', card === trigger.closest('[data-product-card]'));
        });

        // Keep the URL shareable: ?buy=video:3
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('buy', type + ':' + id);
            window.history.replaceState({}, '', url.toString());
        } catch (e) { /* older browsers: ignore */ }

        const checkout = document.getElementById('checkout');
        if (checkout) {
            checkout.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        const nameField = form.querySelector('#name');
        if (nameField) {
            window.setTimeout(function () { nameField.focus({ preventScroll: true }); }, 350);
        }
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-buy]');
        if (!trigger) { return; }
        // Without JavaScript the link still works: it reloads with ?buy=type:id.
        event.preventDefault();
        selectProduct(trigger);
    });

    /* --------------------------------------------------------------------- */
    /* Checkout                                                               */
    /* --------------------------------------------------------------------- */
    function razorpayConfigured(form) {
        return (form.dataset.key || '').trim() !== '';
    }

    function openRazorpay(form, order) {
        const key = order.key_id || form.dataset.key;
        if (!key) {
            throw new Error('Payment gateway is not configured yet. Please contact support.');
        }

        const options = {
            key: key,
            amount: order.amount || parseInt(form.dataset.amountPaise, 10),
            currency: order.currency || form.dataset.currency || 'INR',
            name: order.name || form.dataset.siteName || 'PayAccess',
            description: order.description || ('Access to ' + (form.dataset.productName || 'the video library')),
            order_id: order.order_id,
            prefill: {
                name: form.querySelector('#name').value.trim(),
                email: form.querySelector('#email').value.trim(),
                contact: form.querySelector('#mobile').value.trim()
            },
            notes: { payment_db_id: String(order.payment_db_id) },
            theme: {
                color: getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#4f46e5'
            },
            modal: {
                ondismiss: function () {
                    setBusy(form.querySelector('#pay-button'), false);
                }
            },
            handler: async function (response) {
                const errorBox = form.querySelector('#form-error');
                try {
                    const result = await postJSON(form.dataset.verifyEndpoint, {
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                        payment_db_id: order.payment_db_id,
                        _token: form.dataset.csrf
                    }, form.dataset.csrf);

                    window.location.href = result.redirect || form.dataset.successUrl;
                } catch (error) {
                    showError(errorBox, error.message + ' If money was deducted, use “Restore access” with your e-mail and mobile number.');
                    setBusy(form.querySelector('#pay-button'), false);
                }
            }
        };

        if (typeof window.Razorpay !== 'function') {
            throw new Error('The payment window could not load. Please check your connection and try again.');
        }

        new window.Razorpay(options).open();
    }

    function initCheckout() {
        const form = document.getElementById('payment-form');
        if (!form) { return; }

        const errorBox = form.querySelector('#form-error');
        const button = form.querySelector('#pay-button');
        const demoMode = form.dataset.demo === '1';

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            clearError(errorBox);

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (!demoMode && !razorpayConfigured(form)) {
                showError(errorBox, 'Payments are not configured yet. Please contact support.');
                return;
            }

            setBusy(button, true);

            try {
                const typeInput = form.querySelector('#item-type');
                const idInput = form.querySelector('#item-id');

                const order = await postJSON(form.dataset.endpoint, {
                    name: form.querySelector('#name').value.trim(),
                    email: form.querySelector('#email').value.trim(),
                    mobile: form.querySelector('#mobile').value.trim(),
                    item_type: typeInput ? typeInput.value : '',
                    item_id: idInput ? idInput.value : '',
                    _token: form.dataset.csrf
                }, form.dataset.csrf);

                if (order.demo && order.demo_url) {
                    window.location.href = order.demo_url;
                    return;
                }

                openRazorpay(form, order);
            } catch (error) {
                showError(errorBox, error.message);
                setBusy(button, false);
            }
        });
    }

    /* --------------------------------------------------------------------- */
    /* PWA                                                                    */
    /* --------------------------------------------------------------------- */
    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) { return; }
        if (window.location.pathname.indexOf('/admin/') !== -1) { return; }
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(new URL('sw.js', document.baseURI).toString(), { scope: './' })
                .catch(function (error) { console.warn('Service worker registration failed:', error); });
        });
    }

    /* --------------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initCheckout();
        registerServiceWorker();
    });
})();
