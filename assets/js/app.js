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
    /**
     * Show what the bundle alternative would cost (single video) or how much the
     * bundle itself saves. Copied verbatim from data-* attributes so the wording
     * matches the server-rendered version exactly.
     */
    function updateSaveHint(trigger) {
        const hint = document.getElementById('save-hint');
        if (!hint) { return; }

        const attr = name => trigger.getAttribute(name) || '';
        const set = (id, text) => {
            const node = document.getElementById(id);
            if (node) { node.textContent = text; }
        };

        const cta = document.getElementById('save-hint-cta');

        const suggestId = attr('data-suggest-id');
        const savingsPaise = Number(attr('data-savings') || 0);

        if (suggestId) {
            // A single video that lives inside a bundle: offer the upgrade, but
            // never force it.
            hint.hidden = false;
            set('save-hint-badge-text', attr('data-suggest-badge'));
            set('save-hint-title', attr('data-suggest-title'));
            set('save-hint-text', attr('data-suggest-text'));
            set('save-hint-note', attr('data-suggest-note'));
            if (cta) {
                cta.hidden = false;
                cta.textContent = 'See the bundle';
                cta.setAttribute('data-buy', '');
                cta.setAttribute('data-type', attr('data-suggest-type') || 'bundle');
                cta.setAttribute('data-id', suggestId);
                cta.setAttribute('data-name', attr('data-suggest-name'));
                cta.setAttribute('data-price', attr('data-suggest-price'));
                cta.setAttribute('data-label', attr('data-suggest-label'));
                cta.setAttribute('data-period', attr('data-suggest-period'));
                // Switching back to a bundle keeps its own saving message.
                cta.setAttribute('data-savings', attr('data-suggest-savings-paise') || '0');
                cta.setAttribute('data-savings-badge', attr('data-suggest-savings-badge'));
                cta.setAttribute('data-savings-title', attr('data-suggest-name'));
                cta.setAttribute('data-savings-text', attr('data-suggest-text'));
                cta.setAttribute('data-savings-note', 'One payment unlocks every video in the bundle.');
            }
            return;
        }

        if (savingsPaise > 0) {
            hint.hidden = false;
            set('save-hint-badge-text', attr('data-savings-badge'));
            set('save-hint-title', attr('data-savings-title'));
            set('save-hint-text', attr('data-savings-text'));
            set('save-hint-note', attr('data-savings-note'));
        } else {
            hint.hidden = true;
        }
        if (cta) { cta.hidden = true; }
    }

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

        // Keep the phone action bar in step with what will be charged.
        const barName = document.getElementById('buy-bar-name');
        const barPrice = document.getElementById('buy-bar-price');
        if (barName) { barName.textContent = name; }
        if (barPrice) { barPrice.textContent = label; }

        const key = type + ':' + id;
        document.querySelectorAll('[data-product-card]').forEach(function (card) {
            card.classList.toggle('is-selected', card.getAttribute('data-product-card') === key);
        });

        updateSaveHint(trigger);

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

    /* --------------------------------------------------------------------- */
    /* Mobile chrome: bottom tab bar + "you are buying" action bar            */
    /* --------------------------------------------------------------------- */
    function initMobileChrome() {
        // Hide the action bar once the real checkout form is on screen.
        const buyBar = document.querySelector('[data-buy-bar]');
        const checkout = document.getElementById('checkout');
        if (buyBar) {
            // Reserve room so the floating bar never covers the last section.
            document.body.classList.add('has-buy-bar');
        }
        if (buyBar && checkout && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                buyBar.hidden = entries[0].isIntersecting;
            }, { rootMargin: '-64px 0px -35% 0px' }).observe(checkout);
        }

        // Highlight the tab for the section in view (storefront only).
        const tabbar = document.querySelector('[data-tabbar]');
        if (!tabbar) { return; }
        const items = Array.prototype.slice.call(tabbar.querySelectorAll('[data-tab-anchor]'));
        const sections = [];
        items.forEach(function (item) {
            const node = document.getElementById(item.getAttribute('data-tab-anchor') || '');
            if (node) { sections.push(node); }
        });
        if (!sections.length) { return; }

        function highlight(anchor) {
            items.forEach(function (item) {
                const on = item.getAttribute('data-tab-anchor') === anchor;
                item.classList.toggle('is-active', on);
                if (on) {
                    item.setAttribute('aria-current', 'true');
                } else {
                    item.removeAttribute('aria-current');
                }
            });
        }

        let frame = 0;
        function update() {
            frame = 0;
            let current = 'top';
            sections.forEach(function (section) {
                if (section.getBoundingClientRect().top <= 140) { current = section.id; }
            });
            highlight(current);
        }
        window.addEventListener('scroll', function () {
            if (!frame) { frame = window.requestAnimationFrame(update); }
        }, { passive: true });
        update();
    }

    initMobileChrome();

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-buy]');
        if (!trigger) { return; }
        // When the product on screen is already owned there is no pay form to
        // update — let the browser follow the link to ?buy=video:N so the page
        // re-renders with the checkout for the item they picked.
        if (!document.getElementById('payment-form')) { return; }
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
