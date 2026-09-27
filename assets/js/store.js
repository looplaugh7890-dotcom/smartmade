/* SmartMade — store interactions (vanilla JS, no dependencies) */

(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* Quantity steppers                                                    */
    /* ------------------------------------------------------------------ */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.qty-btn');
        if (!btn) return;
        var wrap = btn.closest('.qty-field');
        var input = wrap && wrap.querySelector('.qty-input');
        if (!input) return;

        var step = parseInt(btn.getAttribute('data-qty'), 10) || 1;
        var min = parseInt(input.min, 10) || 1;
        var max = input.max ? parseInt(input.max, 10) : Infinity;
        var next = (parseInt(input.value, 10) || min) + step;
        input.value = Math.max(min, Math.min(max, next));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    /* ------------------------------------------------------------------ */
    /* Product gallery thumbnails                                           */
    /* ------------------------------------------------------------------ */
    var thumbs = document.querySelectorAll('.product-thumb');
    var mainImage = document.getElementById('product-main-image');
    if (mainImage && thumbs.length) {
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var src = thumb.getAttribute('data-image');
                if (src) mainImage.src = src;
                thumbs.forEach(function (t) { t.classList.remove('is-active'); });
                thumb.classList.add('is-active');
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Variant selection (size + colour)                                    */
    /* ------------------------------------------------------------------ */
    var optionsBox = document.getElementById('product-options');
    if (optionsBox) {
        var variants = [];
        try {
            variants = JSON.parse(optionsBox.getAttribute('data-variants') || '[]');
        } catch (err) {
            variants = [];
        }

        var basePrice = parseFloat(optionsBox.getAttribute('data-base-price')) || 0;
        var symbol = optionsBox.getAttribute('data-symbol') || '£';
        var variantInput = document.getElementById('variant_id');
        var optionError = document.getElementById('option-error');
        var priceEl = document.querySelector('.product-meta-row .price');

        function selectedValue(name) {
            var el = optionsBox.querySelector('input[name="' + name + '"]:checked');
            return el ? el.value : null;
        }

        function matches(variant, size, colour) {
            if (variant.size && size && variant.size !== size) return false;
            if (variant.colour && colour && variant.colour !== colour) return false;
            if (variant.size && !size) return false;
            if (variant.colour && !colour) return false;
            return true;
        }

        function usable(variant) {
            return !variant.manage || variant.stock > 0;
        }

        function updateState() {
            var size = selectedValue('size_choice');
            var colour = selectedValue('colour_choice');

            // Disable option pills/swatches with no matching stock
            optionsBox.querySelectorAll('.option-pill, .option-swatch').forEach(function (label) {
                var input = label.querySelector('input');
                if (!input) return;
                var value = input.value;
                var isSize = input.name === 'size_choice';
                var available = variants.some(function (v) {
                    if (!usable(v)) return false;
                    if (isSize) return v.size === value && (!colour || v.colour === colour);
                    return v.colour === value && (!size || v.size === size);
                });
                label.classList.toggle('is-disabled', !available);
                if (!available) input.disabled = true;
                else input.disabled = false;
            });

            var match = variants.find(function (v) {
                return matches(v, size, colour) && usable(v);
            });

            if (match) {
                if (variantInput) variantInput.value = String(match.id);
                if (priceEl) {
                    priceEl.innerHTML = '<span class="price-now">' + symbol +
                        (basePrice + match.delta).toFixed(2) + '</span>';
                }
                if (optionError) optionError.hidden = true;
            } else if (variantInput) {
                variantInput.value = '';
            }

            var sizeLabel = optionsBox.querySelector('[data-selected-size]');
            var colourLabel = optionsBox.querySelector('[data-selected-colour]');
            if (sizeLabel) sizeLabel.textContent = size ? '— ' + size : '';
            if (colourLabel) colourLabel.textContent = colour ? '— ' + colour : '';
        }

        optionsBox.addEventListener('change', updateState);
        updateState();

        var form = document.getElementById('product-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (variants.length && (!variantInput || !variantInput.value)) {
                    e.preventDefault();
                    if (optionError) {
                        optionError.textContent = 'Please choose an option before adding to your basket.';
                        optionError.hidden = false;
                    }
                }
            });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Lightbox (gallery + any [data-lightbox])                             */
    /* ------------------------------------------------------------------ */
    var lightbox = document.getElementById('lightbox');
    if (lightbox) {
        var lbImage = lightbox.querySelector('.lightbox-image');
        var lbCaption = lightbox.querySelector('.lightbox-caption');

        function openLightbox(src, caption) {
            lbImage.src = src;
            lbImage.alt = caption || '';
            if (lbCaption) lbCaption.textContent = caption || '';
            lightbox.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeLightbox() {
            lightbox.hidden = true;
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-lightbox]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                openLightbox(link.getAttribute('href'), link.getAttribute('data-caption'));
            });
        });

        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox || e.target.classList.contains('lightbox-close')) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !lightbox.hidden) closeLightbox();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Checkout — live shipping cost + total                                */
    /* ------------------------------------------------------------------ */
    var shippingBox = document.getElementById('shipping-options');
    var totalsBox = document.getElementById('checkout-totals');
    if (shippingBox && totalsBox) {
        var subtotal = parseFloat(totalsBox.getAttribute('data-subtotal')) || 0;
        var discount = parseFloat(totalsBox.getAttribute('data-discount')) || 0;
        var tax = parseFloat(totalsBox.getAttribute('data-tax')) || 0;
        var rate = parseFloat(totalsBox.getAttribute('data-rate')) || 0;
        var vatIncluded = totalsBox.getAttribute('data-vat-included') === '1';
        var itemCount = parseInt(totalsBox.getAttribute('data-item-count'), 10) || 1;
        var curSymbol = totalsBox.getAttribute('data-symbol') || '£';
        var net = subtotal - discount;

        function money(n) {
            return curSymbol + n.toFixed(2);
        }

        function recalcShipping() {
            var selected = shippingBox.querySelector('input[name="shipping_method"]:checked');
            if (!selected) return 0;
            var base = parseFloat(selected.getAttribute('data-base')) || 0;
            var perItem = parseFloat(selected.getAttribute('data-per-item')) || 0;
            var freeOver = selected.getAttribute('data-free-over');
            var cost = base + perItem * itemCount;
            if (freeOver !== '' && freeOver !== null && net >= parseFloat(freeOver)) cost = 0;
            return Math.max(0, cost);
        }

        function updateTotals() {
            var shipping = recalcShipping();
            var totalTax = vatIncluded
                ? Math.round((net - net / (1 + rate)) * 100) / 100
                : Math.round(net * rate * 100) / 100;
            var grand = vatIncluded ? net + shipping : net + totalTax + shipping;

            var priceEl = shippingBox.querySelector('input[name="shipping_method"]:checked')
                .closest('.shipping-option').querySelector('[data-shipping-price]');
            if (priceEl) priceEl.textContent = shipping > 0 ? money(shipping) : 'Free';

            var shipCell = totalsBox.querySelector('[data-total-shipping]');
            var taxCell = totalsBox.querySelector('[data-total-tax]');
            var grandCell = totalsBox.querySelector('[data-total-grand]');
            if (shipCell) shipCell.textContent = shipping > 0 ? money(shipping) : 'Free';
            if (taxCell) taxCell.textContent = money(totalTax);
            if (grandCell) grandCell.textContent = money(grand);

            var placeBtn = document.querySelector('button[form="checkout-form"]');
            if (placeBtn) {
                placeBtn.textContent = 'Place order — ' + money(grand);
            }
        }

        shippingBox.addEventListener('change', updateTotals);
        updateTotals();
    }
})();
