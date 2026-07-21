document.addEventListener('DOMContentLoaded', () => {
    const preloader = document.getElementById('sitePreloader');

    function hidePreloader() {
        if (!preloader) return;
        preloader.classList.add('is-hidden');
        document.body.classList.remove('site-loading');
        window.setTimeout(() => {
            preloader.remove();
        }, 400);
    }

    if (document.readyState === 'complete') {
        hidePreloader();
    } else {
        window.addEventListener('load', hidePreloader, { once: true });
        window.setTimeout(hidePreloader, 1600);
    }

    // SCROLL PROGRESS BAR 
    const scrollProgress = document.getElementById('scrollProgress');

    function updateScrollProgress() {
        if (!scrollProgress) return;
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
        scrollProgress.style.width = pct + '%';
    }


    // NAVBAR 
    const navbar   = document.getElementById('navbar');
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');

    function updateNavbar() {
        navbar.classList.toggle('scrolled', window.scrollY > 60);
    }

    function updateActiveLink() {
        const currentFile = window.location.pathname.split('/').pop() || 'index.php';

        // On a sub-page (villa, services, tours, policy) — match by filename
        if (currentFile && currentFile !== 'index.html' && currentFile !== 'index.php') {
            navLinks.forEach(link => {
                const href      = link.getAttribute('href') || '';
                const linkFile  = href.split('#')[0].split('/').pop();
                link.classList.toggle('active', linkFile === currentFile);
            });
            return;
        }

        // On homepage — highlight by scroll position
        let current = '';
        sections.forEach(section => {
            if (window.scrollY >= section.offsetTop - 120) {
                current = section.getAttribute('id');
            }
        });
        navLinks.forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
        });
    }


    // MOBILE NAV 
    const navToggle  = document.getElementById('navToggle');
    const navMenu    = document.getElementById('navMenu');
    const navOverlay = document.getElementById('navOverlay');

    function openNav() {
        navMenu.classList.add('open');
        navToggle.classList.add('open');
        navOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        navToggle.setAttribute('aria-expanded', 'true');
    }

    function closeNav() {
        navMenu.classList.remove('open');
        navToggle.classList.remove('open');
        navOverlay.classList.remove('active');
        document.body.style.overflow = '';
        navToggle.setAttribute('aria-expanded', 'false');
    }

    navToggle.addEventListener('click', () => {
        navMenu.classList.contains('open') ? closeNav() : openNav();
    });

    navLinks.forEach(link => link.addEventListener('click', closeNav));
    navOverlay.addEventListener('click', closeNav);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeNav();
    });


    // BACK TO TOP + WHATSAPP FLOAT
    const backToTop = document.getElementById('backToTop');
    const waFloat   = document.getElementById('waFloat');

    function updateScrollButtons() {
        const show = window.scrollY > 400;
        backToTop.classList.toggle('visible', show);
        if (waFloat) waFloat.classList.toggle('visible', show);
    }

    backToTop.addEventListener('click', (e) => {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });


    // HERO PARALLAX 
    const heroBg = document.querySelector('.hero-bg');

    function updateParallax() {
        if (!heroBg) return;
        const scrollY = window.scrollY;
        // Only apply within the hero viewport
        if (scrollY < window.innerHeight * 1.2) {
            heroBg.style.transform = `translateY(${scrollY * 0.28}px)`;
        }
    }


    // SCROLL REVEAL SYSTEM 
    // Assign reveal classes to elements with directional intent
    const revealMap = [
        { selector: '.about-image-block',   cls: 'reveal-left',  delay: 0 },
        { selector: '.about-content',       cls: 'reveal-right', delay: 0.1 },
        { selector: '.section-header',      cls: 'reveal-up',    delay: 0 },
        { selector: '.about-stats',         cls: 'reveal-up',    delay: 0.2 },
        { selector: '.highlight-item',      cls: 'reveal-up',    stagger: 0.08 },
        { selector: '.service-card',        cls: 'reveal-up',    stagger: 0.1 },
        { selector: '.villa-feature',       cls: 'reveal-left',  stagger: 0.09 },
        { selector: '.villa-main-image',    cls: 'reveal-scale', delay: 0 },
        { selector: '.tour-card',           cls: 'reveal-up',    stagger: 0.12 },
        { selector: '.gallery-item',        cls: 'reveal-scale', stagger: 0.07 },
        { selector: '.contact-info-item',   cls: 'reveal-left',  stagger: 0.1 },
        { selector: '.contact-form-wrap',   cls: 'reveal-right', delay: 0.1 },
        { selector: '.footer-grid > *',     cls: 'reveal-up',    stagger: 0.12 },
    ];

    revealMap.forEach(({ selector, cls, delay = 0, stagger }) => {
        document.querySelectorAll(selector).forEach((el, i) => {
            el.classList.add('reveal-el', cls);
            const d = stagger !== undefined ? i * stagger : delay;
            el.style.transitionDelay = `${d}s`;
        });
    });

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -32px 0px' });

    document.querySelectorAll('.reveal-el').forEach(el => revealObserver.observe(el));


    // STAT COUNTER ANIMATION 
    const statNumbers = document.querySelectorAll('.stat-number[data-count]');

    function animateCounter(el) {
        const target  = parseInt(el.dataset.count, 10);
        const suffix  = el.dataset.suffix || '';
        const duration = 1800;
        const start   = performance.now();

        function step(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(eased * target);
            el.textContent = current + suffix;
            if (progress < 1) requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    }

    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    statNumbers.forEach(el => counterObserver.observe(el));


    // GALLERY LIGHTBOX 
    const galleryItems  = document.querySelectorAll('.gallery-item');
    const lightbox      = document.getElementById('lightbox');
    const lightboxImg   = document.getElementById('lightboxImg');
    const lightboxClose = document.getElementById('lightboxClose');
    const lightboxPrev  = document.getElementById('lightboxPrev');
    const lightboxNext  = document.getElementById('lightboxNext');

    let currentIndex  = 0;
    const imageItems  = Array.from(galleryItems);

    function openLightbox(index) {
        currentIndex = index;
        const item  = imageItems[index];
        const imgEl = item.querySelector('img');
        const src   = imgEl ? imgEl.src : item.dataset.src;
        if (!src || src.includes('undefined') || !imgEl) return;

        lightboxImg.src = src;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
        lightboxImg.src = '';
    }

    const showNext = () => { currentIndex = (currentIndex + 1) % imageItems.length; openLightbox(currentIndex); };
    const showPrev = () => { currentIndex = (currentIndex - 1 + imageItems.length) % imageItems.length; openLightbox(currentIndex); };

    galleryItems.forEach((item, i) => item.addEventListener('click', () => openLightbox(i)));

    if (lightbox) {
        lightboxClose.addEventListener('click', closeLightbox);
        lightboxNext.addEventListener('click', showNext);
        lightboxPrev.addEventListener('click', showPrev);
        lightbox.addEventListener('click', (e) => { if (e.target === lightbox) closeLightbox(); });

        // Swipe support for lightbox (touch)
        let touchStartX = 0;
        lightbox.addEventListener('touchstart', (e) => { touchStartX = e.changedTouches[0].clientX; }, { passive: true });
        lightbox.addEventListener('touchend', (e) => {
            const diff = touchStartX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 50) diff > 0 ? showNext() : showPrev();
        });

        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('active')) return;
            if (e.key === 'Escape')     closeLightbox();
            if (e.key === 'ArrowRight') showNext();
            if (e.key === 'ArrowLeft')  showPrev();
        });
    }

    // HOME STAYS SLIDER
    const staysSlider = document.querySelector('[data-stays-slider]');
    const staysSlides = Array.from(document.querySelectorAll('[data-stays-slide]'));
    const staysPrev = document.querySelector('[data-stays-prev]');
    const staysNext = document.querySelector('[data-stays-next]');
    let activeStayIndex = 0;
    let isDraggingStays = false;
    let draggedStays = false;
    let staysDragStartX = 0;
    let staysDragStartScrollLeft = 0;

    function updateStaysControls() {
        if (!staysSlider || !staysSlides.length) return;
        const maxScroll = Math.max(0, staysSlider.scrollWidth - staysSlider.clientWidth);
        const atStart = staysSlider.scrollLeft <= 8;
        const atEnd = staysSlider.scrollLeft >= maxScroll - 8;

        if (staysPrev) staysPrev.disabled = atStart;
        if (staysNext) staysNext.disabled = atEnd || staysSlides.length <= 1;
    }

    function scrollStaysTo(index, behavior = 'smooth') {
        if (!staysSlider || !staysSlides.length) return;
        activeStayIndex = Math.max(0, Math.min(index, staysSlides.length - 1));
        const slide = staysSlides[activeStayIndex];
        if (!slide) return;

        const targetLeft = slide.offsetLeft - staysSlider.offsetLeft;
        staysSlider.scrollTo({
            left: Math.max(0, targetLeft),
            behavior
        });
    }

    if (staysSlider && staysSlides.length) {
        updateStaysControls();

        staysPrev?.addEventListener('click', () => {
            scrollStaysTo(activeStayIndex - 1);
        });

        staysNext?.addEventListener('click', () => {
            scrollStaysTo(activeStayIndex + 1);
        });

        staysSlider.addEventListener('scroll', updateStaysControls, { passive: true });
        window.addEventListener('resize', updateStaysControls);

        const staysObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const idx = staysSlides.indexOf(entry.target);
                    if (idx !== -1) {
                        activeStayIndex = idx;
                        updateStaysControls();
                    }
                }
            });
        }, {
            root: staysSlider,
            threshold: 0.65
        });

        staysSlides.forEach((slide) => staysObserver.observe(slide));

        staysSlider.addEventListener('mousedown', (event) => {
            if (event.button !== 0) return;
            isDraggingStays = true;
            draggedStays = false;
            staysDragStartX = event.pageX;
            staysDragStartScrollLeft = staysSlider.scrollLeft;
            staysSlider.classList.add('is-dragging');
        });

        window.addEventListener('mousemove', (event) => {
            if (!isDraggingStays) return;
            const deltaX = event.pageX - staysDragStartX;
            if (Math.abs(deltaX) > 6) draggedStays = true;
            staysSlider.scrollLeft = staysDragStartScrollLeft - deltaX;
        });

        const stopStaysDrag = () => {
            if (!isDraggingStays) return;
            isDraggingStays = false;
            staysSlider.classList.remove('is-dragging');
            window.setTimeout(() => {
                draggedStays = false;
            }, 0);
        };

        window.addEventListener('mouseup', stopStaysDrag);
        staysSlider.addEventListener('mouseleave', stopStaysDrag);

        staysSlider.addEventListener('dragstart', (event) => {
            event.preventDefault();
        });

        staysSlider.addEventListener('click', (event) => {
            if (!draggedStays) return;
            event.preventDefault();
            event.stopPropagation();
        }, true);
    }


    // INQUIRY FORM 
    const form        = document.getElementById('inquiryForm');
    const formSuccess = document.getElementById('formSuccess');

    if (form) {
        const today    = new Date().toISOString().split('T')[0];
        const checkin  = document.getElementById('checkin');
        const checkout = document.getElementById('checkout');
        if (checkin)  checkin.setAttribute('min', today);
        if (checkout) checkout.setAttribute('min', today);
        checkin?.addEventListener('change', () => {
            if (checkout && checkin.value) checkout.setAttribute('min', checkin.value);
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Client-side validation
            let valid = true;
            form.querySelectorAll('[required]').forEach(field => {
                const hasError = !field.value.trim();
                field.style.borderColor = hasError ? '#e74c3c' : '';
                field.style.boxShadow   = hasError ? '0 0 0 3px rgba(231,76,60,0.15)' : '';
                if (hasError) valid = false;
            });
            if (!valid) {
                form.querySelectorAll('[required]')[0]?.focus();
                return;
            }

            const btn = form.querySelector('[type="submit"]');
            btn.disabled  = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            // Remove any previous error banner
            form.querySelector('.form-submit-error')?.remove();

            try {
                const res  = await fetch('send-inquiry.php', {
                    method: 'POST',
                    body:   new FormData(form)
                });
                const data = await res.json();

                if (data.ok) {
                    form.style.display = 'none';
                    formSuccess.classList.add('active');
                } else {
                    const err = document.createElement('p');
                    err.className   = 'form-submit-error';
                    err.style.cssText = 'color:#e74c3c;font-size:0.85rem;margin-top:10px';
                    err.textContent = data.msg || 'Something went wrong. Please try again.';
                    form.appendChild(err);
                    btn.disabled  = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Inquiry';
                }
            } catch {
                const err = document.createElement('p');
                err.className   = 'form-submit-error';
                err.style.cssText = 'color:#e74c3c;font-size:0.85rem;margin-top:10px';
                err.textContent = 'Network error. Please check your connection and try again.';
                form.appendChild(err);
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Inquiry';
            }
        });

        // Clear error on input
        form.querySelectorAll('input, textarea').forEach(field => {
            field.addEventListener('input', () => {
                field.style.borderColor = '';
                field.style.boxShadow   = '';
            });
        });
    }


    // SMOOTH CARD TILT (Desktop only) 
    if (window.matchMedia('(hover: hover)').matches) {
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width  - 0.5;
                const y = (e.clientY - rect.top)  / rect.height - 0.5;
                card.style.transform = `translateY(-8px) rotateX(${-y * 6}deg) rotateY(${x * 6}deg)`;
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
            });
        });
    }


    // UNIFIED SCROLL HANDLER (throttled) 
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                updateScrollProgress();
                updateNavbar();
                updateActiveLink();
                updateScrollButtons();
                updateParallax();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // Initial calls
    updateScrollProgress();
    updateNavbar();
    updateActiveLink();
    updateScrollButtons();

});
