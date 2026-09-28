(function () {
    'use strict';

    var drawer = document.getElementById('cart-drawer');
    if (!drawer) return;

    var backdrop = document.getElementById('cart-drawer-backdrop');
    var content = document.getElementById('cart-drawer-content');
    var trigger = document.getElementById('mini-cart');
    var badge = document.getElementById('mini-cart-count');
    var siteUrl = (document.body.dataset.siteUrl || '').replace(/\/$/, '');
    var lastFocus = null;
    var busy = false;

    function setOpen(open) {
        drawer.classList.toggle('is-open', open);
        if (backdrop) backdrop.classList.toggle('is-visible', open);
        drawer.setAttribute('aria-hidden', String(!open));
        if (trigger) trigger.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('drawer-open', open);

        if (open) {
            lastFocus = document.activeElement;
            refresh();
            window.setTimeout(function () {
                var close = content.querySelector('[data-cart-drawer-close]');
                if (close && close.focus) close.focus();
            }, 60);
        } else if (lastFocus && lastFocus.focus) {
            lastFocus.focus();
        }
    }

    function updateBadge(count) {
        if (!badge) return;
        badge.textContent = String(count);
        badge.classList.toggle('is-empty', count === 0);
        if (trigger) trigger.setAttribute('aria-label', 'Basket, ' + count + ' items');
    }

    function showMessage(text, ok) {
        var old = content.querySelector('.cart-drawer-message');
        if (old) old.parentNode.removeChild(old);
        if (!text) return;
        var el = document.createElement('div');
        el.className = 'cart-drawer-message ' + (ok ? 'is-success' : 'is-error');
        el.setAttribute('role', 'status');
        el.textContent = text;
        var head = content.querySelector('.cart-drawer-head');
        if (head && head.parentNode) head.parentNode.insertBefore(el, head.nextSibling);
        else content.insertBefore(el, content.firstChild);
        window.setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 3200);
    }

    function applyPayload(data, announce) {
        if (!data) return;
        if (typeof data.html === 'string') content.innerHTML = data.html;
        if (typeof data.count === 'number') updateBadge(data.count);
        if (announce && data.message) showMessage(data.message, data.ok !== false);
    }

    function refresh() {
        if (!siteUrl) return Promise.resolve();
        return fetch(siteUrl + '/cart_drawer.php', {
            headers: { 'X-Requested-With': 'xmlhttprequest' },
            credentials: 'same-origin'
        }).then(function (res) { return res.text(); }).then(function (html) {
            if (html && html.indexOf('cart-drawer-head') !== -1) content.innerHTML = html;
        }).catch(function () {});
    }

    if (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            setOpen(!drawer.classList.contains('is-open'));
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () { setOpen(false); });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) setOpen(false);
    });

    document.addEventListener('click', function (e) {
        var closer = e.target.closest ? e.target.closest('[data-cart-drawer-close]') : null;
        if (closer) setOpen(false);
    });

    content.addEventListener('click', function (e) {
        var deltaBtn = e.target.closest ? e.target.closest('[data-qty-delta]') : null;
        if (deltaBtn) {
            var form = deltaBtn.closest('form');
            var input = form ? form.querySelector('.cart-drawer-qty-input') : null;
            if (input) {
                var next = (parseInt(input.value, 10) || 1) + parseInt(deltaBtn.dataset.qtyDelta, 10);
                var min = parseInt(input.min || '1', 10) || 1;
                input.value = String(Math.max(min, next));
            }
        }
    });

    content.addEventListener('submit', function (e) {
        var form = e.target.closest ? e.target.closest('form[action]') : null;
        if (!form) return;
        if (form.action.indexOf('/cart.php') === -1) return;

        e.preventDefault();
        if (busy) return;
        busy = true;
        drawer.classList.add('is-loading');

        var submitter = e.submitter || null;
        if (submitter && submitter.name !== 'action') {
            var fd = new FormData(form);
            fd.append(submitter.name, submitter.value);
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'xmlhttprequest' }
        }).then(function (res) { return res.json(); }).then(function (data) {
            applyPayload(data, true);
        }).catch(function () {
            form.submit();
        }).finally(function () {
            busy = false;
            drawer.classList.remove('is-loading');
        });
    });
})();
