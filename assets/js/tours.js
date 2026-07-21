document.addEventListener('DOMContentLoaded', () => {

    /* HERO: slow zoom-in on load  */
    const hero = document.getElementById('toursHero');
    if (hero) {
        requestAnimationFrame(() => hero.classList.add('loaded'));
    }

    /* HERO: parallax on scroll  */
    const heroBg = hero ? hero.querySelector('.tours-hero-bg') : null;
    let heroH = hero ? hero.offsetHeight : 0;

    function heroParallax() {
        if (!heroBg || window.scrollY > heroH) return;
        heroBg.style.transform = `translateY(${window.scrollY * 0.35}px)`;
    }

    /* TOUR CARD FILTER  */
    const filterBtns = document.querySelectorAll('.tour-filter-btn');
    const tourCards  = document.querySelectorAll('.tour-card-full');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;

            // Update active button
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Determine visible cards
            const toShow = [];
            const toHide = [];

            tourCards.forEach(card => {
                const cat = card.dataset.category;
                if (filter === 'all' || cat === filter) {
                    toShow.push(card);
                } else {
                    toHide.push(card);
                }
            });

            // Hide non-matching
            toHide.forEach(card => {
                card.classList.remove('re-entering');
                card.classList.add('hidden');
            });

            // Show matching with stagger
            toShow.forEach((card, i) => {
                card.classList.remove('hidden');
                // Reset animation
                card.classList.remove('re-entering');
                card.style.animationDelay = `${i * 0.07}s`;
                // RAF to trigger reflow before re-adding class
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        card.classList.add('re-entering');
                    });
                });
            });
        });
    });

    /* SMOOTH SCROLL for "View All Packages" */
    document.querySelectorAll('.smooth-scroll').forEach(link => {
        link.addEventListener('click', e => {
            const href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const navH = document.getElementById('navbar')?.offsetHeight || 70;
                    window.scrollTo({
                        top: target.getBoundingClientRect().top + window.scrollY - navH - 20,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    /* SCROLL REVEAL */
    const revealEls = document.querySelectorAll('.reveal-el');
    if (revealEls.length) {
        const revealObs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        revealEls.forEach(el => revealObs.observe(el));
    }

    /* UNIFIED SCROLL HANDLER (RAF-throttled) */
    let ticking = false;

    function onScroll() {
        if (!ticking) {
            requestAnimationFrame(() => {
                heroParallax();
                ticking = false;
            });
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', () => { heroH = hero ? hero.offsetHeight : 0; });

    /* TOUR INQUIRY FORM SUBMIT */
    document.querySelectorAll('[data-tour-inquiry-form]').forEach(form => {
        const feedback = form.querySelector('[data-tour-inquiry-feedback]');
        const submitBtn = form.querySelector('button[type="submit"]');
        const turnstileEl = form.querySelector('.cf-turnstile');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (feedback) {
                feedback.classList.remove('success', 'error');
                feedback.textContent = '';
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }

            try {
                const formData = new FormData(form);
                const res = await fetch('send-tour-inquiry.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data && data.ok) {
                    if (feedback) {
                        feedback.classList.add('success');
                        feedback.textContent = data.msg || 'Inquiry sent successfully.';
                    }
                    form.reset();
                } else {
                    if (feedback) {
                        feedback.classList.add('error');
                        feedback.textContent = (data && data.msg) ? data.msg : 'Failed to send inquiry.';
                    }
                }
            } catch (err) {
                if (feedback) {
                    feedback.classList.add('error');
                    feedback.textContent = 'Failed to send inquiry. Please try again.';
                }
            } finally {
                if (window.turnstile && turnstileEl) {
                    try { window.turnstile.reset(turnstileEl); } catch (e) {}
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || 'Send Tour Inquiry';
                }
            }
        });
    });

});
