(function () {
    'use strict';

    const navToggle = document.getElementById('nav-toggle');
    const primaryNav = document.getElementById('primary-nav');
    const navBackdrop = document.getElementById('nav-backdrop');

    function toggleNav(open) {
        const isOpen = open !== undefined ? open : !primaryNav.classList.contains('is-open');
        primaryNav.classList.toggle('is-open', isOpen);
        navToggle.classList.toggle('is-open', isOpen);
        navToggle.setAttribute('aria-expanded', String(isOpen));
        if (navBackdrop) {
            navBackdrop.classList.toggle('is-visible', isOpen);
        }
        document.body.classList.toggle('nav-open', isOpen);
    }

    if (navToggle && primaryNav) {
        navToggle.addEventListener('click', function () {
            toggleNav();
        });

        if (navBackdrop) {
            navBackdrop.addEventListener('click', function () {
                toggleNav(false);
            });
        }

        primaryNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 1100) {
                    toggleNav(false);
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && primaryNav.classList.contains('is-open') && window.innerWidth <= 1100) {
                toggleNav(false);
            }
        });
    }

    const revealElements = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window && revealElements.length) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.08,
            rootMargin: '0px 0px -40px 0px'
        });

        revealElements.forEach(function (el) {
            observer.observe(el);
        });
    } else {
        revealElements.forEach(function (el) {
            el.classList.add('is-visible');
        });
    }

    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(function (item) {
        const question = item.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', function () {
                const isOpen = item.classList.contains('is-open');

                faqItems.forEach(function (other) {
                    other.classList.remove('is-open');
                });

                if (!isOpen) {
                    item.classList.add('is-open');
                }
            });
        }
    });

    const filterBtns = document.querySelectorAll('.filter-btn');
    const portfolioGrid = document.getElementById('portfolio-grid');
    const stitchLoader = document.getElementById('portfolio-loader');

    if (filterBtns.length && portfolioGrid) {
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const category = btn.dataset.category || 'all';

                filterBtns.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-pressed', 'true');

                if (stitchLoader) {
                    stitchLoader.classList.add('is-visible');
                }

                portfolioGrid.classList.add('is-loading');

                const url = window.location.pathname + '?ajax=1&category=' + encodeURIComponent(category);

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(function (html) {
                        portfolioGrid.innerHTML = html;
                        if (stitchLoader) {
                            stitchLoader.classList.remove('is-visible');
                        }
                        portfolioGrid.classList.remove('is-loading');
                        initPortfolioModal();
                    })
                    .catch(function (error) {
                        console.error('Filter error:', error);
                        if (stitchLoader) {
                            stitchLoader.classList.remove('is-visible');
                        }
                        portfolioGrid.classList.remove('is-loading');
                    });
            });
        });
    }

    function initPortfolioModal() {
        const modal = document.getElementById('portfolio-modal');
        if (!modal) return;

        const modalTriggers = document.querySelectorAll('[data-modal-trigger]');
        const modalBody = document.getElementById('modal-body');
        const modalClose = modal.querySelector('.modal-close');

        function openModal(contentHtml) {
            if (modalBody) {
                modalBody.innerHTML = contentHtml;
            }
            modal.classList.add('is-open');
            document.body.classList.add('modal-open');
        }

        function closeModal() {
            modal.classList.remove('is-open');
            document.body.classList.remove('modal-open');
        }

        modalTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                const itemId = trigger.dataset.id;

                if (modalBody) {
                    modalBody.innerHTML = '<div style="padding:40px;text-align:center;">Loading…</div>';
                }
                modal.classList.add('is-open');

                const linkHref = trigger.closest('a') && trigger.closest('a').getAttribute('href');
                const baseUrl = linkHref ? linkHref.split('?')[0] : (window.location.pathname.includes('portfolio') ? window.location.pathname : '/smartmade/portfolio.php');

                fetch(baseUrl + '?ajax=modal&id=' + encodeURIComponent(itemId), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(function (html) {
                        if (modalBody) {
                            modalBody.innerHTML = html;
                        }
                    })
                    .catch(function () {
                        if (modalBody) {
                            modalBody.innerHTML = '<div style="padding:40px;text-align:center;">Could not load item.</div>';
                        }
                    });
            });
        });

        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    initPortfolioModal();

    const siteHeader = document.getElementById('site-header');
    if (siteHeader) {
        let ticking = false;
        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(function () {
                    siteHeader.classList.toggle('is-scrolled', window.scrollY > 20);
                    ticking = false;
                });
                ticking = true;
            }
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const targetId = anchor.getAttribute('href');
            if (targetId === '#') return;
            const targetEl = document.querySelector(targetId);
            if (targetEl) {
                e.preventDefault();
                targetEl.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    const forms = document.querySelectorAll('form[data-validate]');

    forms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let valid = true;
            const requiredFields = form.querySelectorAll('[required]');

            requiredFields.forEach(function (field) {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!valid) {
                e.preventDefault();
            }
        });
    });

    const style = document.createElement('style');
    style.textContent = '.form-input.is-invalid, .form-textarea.is-invalid, .form-select.is-invalid { border-color: #C62828 !important; }';
    document.head.appendChild(style);
})();
