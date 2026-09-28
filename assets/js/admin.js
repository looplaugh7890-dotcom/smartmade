(function () {
    'use strict';

    const navToggle = document.getElementById('admin-nav-toggle');
    const adminNav = document.getElementById('admin-nav');
    const adminBackdrop = document.getElementById('admin-nav-backdrop');

    function toggleAdminNav(open) {
        const isOpen = open !== undefined ? open : !adminNav.classList.contains('is-open');
        adminNav.classList.toggle('is-open', isOpen);
        navToggle.setAttribute('aria-expanded', String(isOpen));
        if (adminBackdrop) {
            adminBackdrop.classList.toggle('is-visible', isOpen);
        }
        document.body.classList.toggle('nav-open', isOpen);
    }

    if (navToggle && adminNav) {
        navToggle.addEventListener('click', function () {
            toggleAdminNav();
        });

        if (adminBackdrop) {
            adminBackdrop.addEventListener('click', function () {
                toggleAdminNav(false);
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && adminNav.classList.contains('is-open') && window.innerWidth <= 1024) {
                toggleAdminNav(false);
            }
        });
    }

    document.querySelectorAll('[data-confirm]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            const message = link.dataset.confirm || 'Are you sure you want to delete this?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            const targetSelector = input.dataset.preview;
            const target = document.querySelector(targetSelector);
            if (target && input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    target.src = e.target.result;
                    target.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        });
    });

    const slugSource = document.querySelector('.js-slug-source');
    const slugTarget = document.querySelector('.js-slug-target');

    if (slugSource && slugTarget) {
        slugSource.addEventListener('input', function () {
            slugTarget.value = slugSource.value
                .toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* Breadcrumb from the active sidebar item */
    const breadcrumb = document.getElementById('admin-breadcrumb');
    if (breadcrumb) {
        const activeLink = document.querySelector('.admin-nav li.is-active > a');
        if (activeLink) {
            const label = activeLink.textContent.trim();
            let section = '';
            let node = activeLink.parentElement.previousElementSibling;
            while (node) {
                if (node.classList.contains('admin-nav-heading')) {
                    section = node.textContent.trim();
                    break;
                }
                node = node.previousElementSibling;
            }
            const logo = document.querySelector('.admin-logo');
            const home = logo ? logo.getAttribute('href') : '#';
            let html = '<a href="' + escapeHtml(home) + '">Dashboard</a>';
            if (label !== 'Dashboard') {
                if (section) {
                    html += '<span class="crumb-sep" aria-hidden="true">&#8250;</span><span>' + escapeHtml(section) + '</span>';
                }
                html += '<span class="crumb-sep" aria-hidden="true">&#8250;</span><span class="crumb-current">' + escapeHtml(label) + '</span>';
            }
            breadcrumb.innerHTML = html;
        }
    }

    const topbar = document.getElementById('admin-topbar');
    if (topbar && !topbar.textContent.trim() && !breadcrumb.innerHTML.trim()) {
        topbar.style.display = 'none';
    }

    /* Dismissable flash messages (success fades away on its own) */
    document.querySelectorAll('.admin-main .alert').forEach(function (alert) {
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'alert-close';
        close.setAttribute('aria-label', 'Dismiss message');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () {
            alert.remove();
        });
        alert.appendChild(close);

        if (alert.classList.contains('alert-success')) {
            window.setTimeout(function () {
                alert.classList.add('is-hiding');
                window.setTimeout(function () {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 400);
            }, 6000);
        }
    });

    /* Filter dropdowns apply immediately (Filter button still works) */
    document.querySelectorAll('form.admin-toolbar').forEach(function (form) {
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') {
            return;
        }
        form.querySelectorAll('select.admin-input').forEach(function (select) {
            select.addEventListener('change', function () {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
        const search = form.querySelector('input[name="q"]');
        if (search) {
            search.setAttribute('title', 'Press Enter to search, or / to jump here');
        }
    });

    /* Press / to jump to the search box */
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        const tag = document.activeElement ? document.activeElement.tagName : '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }
        const search = document.querySelector('.admin-toolbar input[name="q"]');
        if (search) {
            e.preventDefault();
            search.focus();
            search.select();
        }
    });

    /* Scroll shadows on wide tables */
    function refreshTableShadows() {
        document.querySelectorAll('.admin-table-wrap').forEach(function (wrap) {
            const scrollable = wrap.scrollWidth > wrap.clientWidth + 2;
            wrap.classList.toggle('is-scrollable', scrollable);
            wrap.classList.toggle('at-end', scrollable && wrap.scrollLeft + wrap.clientWidth >= wrap.scrollWidth - 2);
        });
    }
    window.addEventListener('resize', refreshTableShadows);
    document.addEventListener('scroll', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('admin-table-wrap')) {
            refreshTableShadows();
        }
    }, true);
    refreshTableShadows();

    /* Back to top (long pages) */
    const toTop = document.createElement('button');
    toTop.type = 'button';
    toTop.className = 'admin-to-top';
    toTop.setAttribute('aria-label', 'Back to top');
    toTop.textContent = '\u2191';
    toTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    document.body.appendChild(toTop);
    window.addEventListener('scroll', function () {
        toTop.classList.toggle('is-visible', window.scrollY > 700);
    });

    /* Show/hide password fields */
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        const wrap = document.createElement('span');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'pw-toggle';
        toggle.textContent = 'Show';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.addEventListener('click', function () {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.textContent = showing ? 'Show' : 'Hide';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
        wrap.appendChild(toggle);
    });
})();
