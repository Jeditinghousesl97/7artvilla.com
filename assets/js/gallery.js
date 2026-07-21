document.addEventListener('DOMContentLoaded', () => {

    /* HERO: slow zoom-in on load */
    const hero = document.getElementById('galleryHero');
    if (hero) requestAnimationFrame(() => hero.classList.add('loaded'));

    /* HERO: parallax */
    const heroBg = hero ? hero.querySelector('.gallery-hero-bg') : null;
    let heroH    = hero ? hero.offsetHeight : 0;

    function heroParallax() {
        if (!heroBg || window.scrollY > heroH) return;
        heroBg.style.transform = `translateY(${window.scrollY * 0.3}px)`;
    }

    /* COLLECT ITEMS & BUILD INDEX */
    const masonry    = document.getElementById('galleryMasonry');
    const allItems   = masonry ? Array.from(masonry.querySelectorAll('.gal-item')) : [];
    const emptyState = document.getElementById('galleryEmpty');

    // Nothing to do if no images
    if (!allItems.length) return;

    // Staggered reveal on load
    allItems.forEach((item, i) => {
        setTimeout(() => item.classList.add('visible'), 80 + i * 40);
    });

    /*  FILTER */
    const filterBtns = document.querySelectorAll('.gallery-filter-btn');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;

            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            let visibleCount = 0;

            allItems.forEach((item, i) => {
                const cat = item.dataset.category;
                const show = filter === 'all' || cat === filter;

                if (show) {
                    item.classList.remove('hidden-filter');
                    // Re-trigger entrance animation
                    item.classList.remove('visible');
                    setTimeout(() => item.classList.add('visible'), 30 + i * 35);
                    visibleCount++;
                } else {
                    item.classList.add('hidden-filter');
                    item.classList.remove('visible');
                }
            });

            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        });
    });

    /* LIGHTBOX */
    const lightbox   = document.getElementById('galLightbox');
    const backdrop   = document.getElementById('galLightboxBackdrop');
    const lbImg      = document.getElementById('galLbImg');
    const lbPh       = document.getElementById('galLbPlaceholder');
    const lbCaption  = document.getElementById('galLbCaption');
    const lbCounter  = document.getElementById('galLbCounter');
    const lbClose    = document.getElementById('galLbClose');
    const lbPrev     = document.getElementById('galLbPrev');
    const lbNext     = document.getElementById('galLbNext');

    let currentIndex = 0;

    // Build ordered list of visible items for navigation
    function visibleItems() {
        return allItems.filter(item => !item.classList.contains('hidden-filter'));
    }

    function openLightbox(index) {
        const items = visibleItems();
        if (!items.length) return;
        currentIndex = Math.max(0, Math.min(index, items.length - 1));

        const item    = items[currentIndex];
        const imgEl   = item.querySelector('img');
        const caption = item.querySelector('.gal-caption p')?.textContent || '';

        lbCaption.textContent = caption;
        lbCounter.textContent = `${currentIndex + 1} / ${items.length}`;

        if (imgEl && imgEl.src && !imgEl.src.endsWith('/')) {
            // Real image
            lbImg.classList.add('loading');
            lbImg.style.display = 'block';
            lbPh.classList.remove('show');

            lbImg.onload = () => lbImg.classList.remove('loading');
            lbImg.onerror = () => {
                lbImg.style.display = 'none';
                lbPh.classList.add('show');
            };
            lbImg.src = imgEl.src;
            lbImg.alt = imgEl.alt || caption;
        } else {
            // Placeholder — show coming soon panel
            lbImg.style.display = 'none';
            lbPh.classList.add('show');
        }

        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
        lbImg.src = '';
        lbPh.classList.remove('show');
    }

    function showNext() {
        const items = visibleItems();
        openLightbox((currentIndex + 1) % items.length);
    }

    function showPrev() {
        const items = visibleItems();
        openLightbox((currentIndex - 1 + items.length) % items.length);
    }

    // Open on click
    allItems.forEach((item, i) => {
        item.addEventListener('click', () => {
            const items = visibleItems();
            const visIdx = items.indexOf(item);
            if (visIdx !== -1) openLightbox(visIdx);
        });
    });

    lbClose.addEventListener('click', closeLightbox);
    backdrop.addEventListener('click', closeLightbox);
    lbNext.addEventListener('click', showNext);
    lbPrev.addEventListener('click', showPrev);

    // Keyboard nav
    document.addEventListener('keydown', (e) => {
        if (!lightbox.classList.contains('active')) return;
        if (e.key === 'Escape')      closeLightbox();
        if (e.key === 'ArrowRight')  showNext();
        if (e.key === 'ArrowLeft')   showPrev();
    });

    // Touch swipe
    let touchStartX = 0;
    lightbox.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].clientX;
    }, { passive: true });
    lightbox.addEventListener('touchend', (e) => {
        const diff = touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) diff > 0 ? showNext() : showPrev();
    });

    /* SCROLL REVEAL */
    const revealEls = document.querySelectorAll('.reveal-el');
    if (revealEls.length) {
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        revealEls.forEach(el => obs.observe(el));
    }

    /* UNIFIED SCROLL HANDLER */
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                heroParallax();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    window.addEventListener('resize', () => { heroH = hero ? hero.offsetHeight : 0; });

});
