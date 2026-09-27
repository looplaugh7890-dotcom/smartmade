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
            if (e.key === 'Escape' && adminNav.classList.contains('is-open') && window.innerWidth <= 768) {
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
})();
