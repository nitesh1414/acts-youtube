/* PayAccess — admin panel scripts */
(function () {
    'use strict';

    /* Sidebar drawer (phones + tablets) ------------------------------------- */
    const sidebar = document.getElementById('admin-sidebar');
    const overlays = Array.prototype.slice.call(document.querySelectorAll('[data-sidebar-close]'));
    const overlay = document.querySelector('.admin-overlay');
    const toggles = Array.prototype.slice.call(document.querySelectorAll('[data-sidebar-toggle]'));

    function openSidebar() {
        if (!sidebar) { return; }
        sidebar.classList.add('is-open');
        if (overlay) { overlay.hidden = false; }
        document.body.style.overflow = 'hidden';
        toggles.forEach(function (button) { button.setAttribute('aria-expanded', 'true'); });
        const first = sidebar.querySelector('a, button');
        if (first) { first.focus({ preventScroll: true }); }
    }

    function closeSidebar() {
        if (!sidebar) { return; }
        sidebar.classList.remove('is-open');
        if (overlay) { overlay.hidden = true; }
        document.body.style.overflow = '';
        toggles.forEach(function (button) { button.setAttribute('aria-expanded', 'false'); });
    }

    toggles.forEach(function (button) {
        button.setAttribute('aria-expanded', 'false');
        button.addEventListener('click', function () {
            sidebar && sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
        });
    });
    overlays.forEach(function (node) {
        node.addEventListener('click', closeSidebar);
    });
    // Tapping a destination in the drawer navigates — close it first so the
    // page behind is not left with a locked scroll when the user comes back.
    if (sidebar) {
        sidebar.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', closeSidebar);
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeSidebar(); }
    });

    /* Copy / reveal --------------------------------------------------------- */
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
        if (trigger) {
            event.preventDefault();
            copyText(trigger.getAttribute('data-copy')).then(function () {
                const original = trigger.innerHTML;
                trigger.innerHTML = '✓ Copied';
                setTimeout(function () { trigger.innerHTML = original; }, 1500);
            }).catch(function () {
                window.alert('Could not copy automatically.');
            });
        }

        const toggle = event.target.closest('[data-toggle-password]');
        if (toggle) {
            const input = document.querySelector(toggle.getAttribute('data-toggle-password'));
            if (input) {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            }
        }
    });

    document.addEventListener('focusin', function (event) {
        if (event.target.matches('[data-select-on-focus]')) {
            event.target.select();
        }
    });

    /* Confirm destructive forms -------------------------------------------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    /* Logo uploader --------------------------------------------------------- */
    const logoInput = document.querySelector('[data-logo-input]');
    const preview = document.querySelector('[data-logo-preview]');
    const dropzone = document.querySelector('[data-dropzone]');

    function previewLogo(file) {
        if (!preview || !file || !file.type || file.type.indexOf('image/') !== 0) {
            return;
        }
        const url = URL.createObjectURL(file);
        preview.classList.add('has-image');
        preview.innerHTML = '<img src="' + url + '" alt="Selected logo preview">';
    }

    if (logoInput) {
        logoInput.addEventListener('change', function () {
            if (logoInput.files && logoInput.files[0]) {
                previewLogo(logoInput.files[0]);
            }
        });
    }

    if (dropzone) {
        ['dragenter', 'dragover'].forEach(function (name) {
            dropzone.addEventListener(name, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            dropzone.addEventListener(name, function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', function (event) {
            const files = event.dataTransfer && event.dataTransfer.files;
            if (files && files.length && logoInput) {
                logoInput.files = files;
                previewLogo(files[0]);
            }
        });
    }

    /* YouTube thumbnail preview -------------------------------------------- */
    const youtubeInput = document.querySelector('[data-youtube-input]');
    const youtubePreview = document.querySelector('[data-youtube-preview]');
    const youtubeThumb = document.querySelector('[data-youtube-thumb]');

    function youtubeId(value) {
        const text = (value || '').trim();
        if (/^[A-Za-z0-9_-]{11}$/.test(text)) { return text; }
        const patterns = [
            /(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|v\/|shorts\/|live\/))([A-Za-z0-9_-]{11})/i,
            /youtu\.be\/([A-Za-z0-9_-]{11})/i
        ];
        for (let i = 0; i < patterns.length; i++) {
            const match = text.match(patterns[i]);
            if (match) { return match[1]; }
        }
        return null;
    }

    function refreshYoutubePreview() {
        if (!youtubeInput || !youtubePreview || !youtubeThumb) { return; }
        const id = youtubeId(youtubeInput.value);
        if (!id) {
            youtubePreview.hidden = true;
            return;
        }
        youtubeThumb.src = 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg';
        youtubePreview.hidden = false;
    }

    if (youtubeInput) {
        youtubeInput.addEventListener('input', refreshYoutubePreview);
        youtubeInput.addEventListener('blur', refreshYoutubePreview);
    }

    /* Bundle: running total of the selected videos ------------------------- */
    const picker = document.querySelector('.picker');
    const bundleTotal = document.getElementById('bundle-single-total');

    if (picker && bundleTotal) {
        const prices = JSON.parse(picker.getAttribute('data-prices') || '{}');
        const currency = bundleTotal.getAttribute('data-currency') || 'INR';
        const format = paise => {
            try {
                return new Intl.NumberFormat('en-IN', {
                    style: 'currency', currency: currency, maximumFractionDigits: 0
                }).format(paise / 100);
            } catch (e) {
                return (paise / 100).toFixed(0) + ' ' + currency;
            }
        };
        const refreshTotal = () => {
            let total = 0;
            let selectedCount = 0;
            let singleCount = 0;
            picker.querySelectorAll('.picker__item').forEach(row => {
                const video = row.querySelector('input[type=checkbox][name="video_ids[]"]');
                if (!video || !video.checked) { return; }
                selectedCount++;
                const availability = row.querySelector('select[name^="allow_single_purchase["]');
                if (availability && availability.value === '1' && !row.classList.contains('is-muted')) {
                    total += Number(prices[video.value] || 0);
                    singleCount++;
                }
            });
            const bundleOnlyCount = selectedCount - singleCount;
            if (total > 0) {
                bundleTotal.textContent = format(total) + ' across ' + singleCount + ' individually available video(s)'
                    + (bundleOnlyCount > 0 ? ' · ' + bundleOnlyCount + ' bundle-only/hidden' : '');
            } else if (selectedCount > 0) {
                bundleTotal.textContent = 'no individual prices (selected videos are bundle-only or hidden)';
            } else {
                bundleTotal.textContent = 'nothing selected yet';
            }
        };
        picker.addEventListener('change', refreshTotal);
        refreshTotal();
    }

    /* Colour picker live label --------------------------------------------- */
    const colourInput = document.getElementById('theme_color');
    if (colourInput) {
        colourInput.addEventListener('input', function () {
            const label = colourInput.parentElement.querySelector('.mono');
            if (label) { label.textContent = colourInput.value; }
        });
    }

    /* Auto-hide flash messages --------------------------------------------- */
    window.setTimeout(function () {
        document.querySelectorAll('.flash-stack .alert').forEach(function (alert) {
            alert.style.transition = 'opacity .4s ease';
            alert.style.opacity = '0';
            window.setTimeout(function () { alert.remove(); }, 450);
        });
    }, 6000);
})();
