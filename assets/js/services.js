document.addEventListener('DOMContentLoaded', () => {

    // HERO IMAGE ZOOM-IN ON LOAD 
    const hero = document.querySelector('.services-hero');
    if (hero) setTimeout(() => hero.classList.add('loaded'), 100);


    // HERO PARALLAX 
    const heroBg = document.querySelector('.services-hero-bg');
    window.addEventListener('scroll', () => {
        if (!heroBg || window.scrollY > window.innerHeight * 1.2) return;
        heroBg.style.transform = `translateY(${window.scrollY * 0.25}px)`;
    }, { passive: true });


    // SERVICE FILTER 
    const filterBtns  = document.querySelectorAll('.filter-btn');
    const serviceCards = document.querySelectorAll('.service-full-card');
    const noResults    = document.getElementById('noResults');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;

            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            let visible = 0;
            serviceCards.forEach((card, i) => {
                const match = filter === 'all' || card.dataset.category === filter;
                if (match) {
                    card.classList.remove('hidden');
                    // Stagger re-entrance animation
                    card.style.animationDelay = `${visible * 0.07}s`;
                    card.style.animation = 'none';
                    requestAnimationFrame(() => {
                        card.style.animation = '';
                        card.style.animationName = 'fadeInUp';
                        card.style.animationDuration = '0.5s';
                        card.style.animationFillMode = 'both';
                        card.style.animationDelay = `${visible * 0.07}s`;
                    });
                    visible++;
                } else {
                    card.classList.add('hidden');
                }
            });

            noResults.style.display = visible === 0 ? 'block' : 'none';
        });
    });


    // SCROLL REVEAL 
    const revealMap = [
        { selector: '.services-intro-text',  cls: 'reveal-left',  delay: 0 },
        { selector: '.pillar',               cls: 'reveal-up',    stagger: 0.1 },
        { selector: '.service-full-card',    cls: 'reveal-up',    stagger: 0.08 },
        { selector: '.process-step',         cls: 'reveal-up',    stagger: 0.15 },
        { selector: '.occasion-card',        cls: 'reveal-up',    stagger: 0.1 },
        { selector: '.services-intro-pillars', cls: 'reveal-right', delay: 0.1 },
    ];

    revealMap.forEach(({ selector, cls, delay = 0, stagger }) => {
        document.querySelectorAll(selector).forEach((el, i) => {
            // Skip already-hidden cards (filtered out)
            if (el.classList.contains('hidden')) return;
            el.classList.add('reveal-el', cls);
            el.style.transitionDelay = `${stagger !== undefined ? i * stagger : delay}s`;
        });
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.07, rootMargin: '0px 0px -32px 0px' });

    document.querySelectorAll('.reveal-el').forEach(el => observer.observe(el));

});
