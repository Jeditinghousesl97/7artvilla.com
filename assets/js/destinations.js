document.addEventListener('DOMContentLoaded', () => {
    const btns = document.querySelectorAll('.dest-filter-btn');
    const cards = document.querySelectorAll('.dest-card');

    if (btns.length && cards.length) {
        btns.forEach((btn) => {
            btn.addEventListener('click', () => {
                const filter = btn.dataset.filter || 'all';
                btns.forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');

                cards.forEach((card) => {
                    const cats = (card.dataset.categories || '').split(',').map((v) => v.trim()).filter(Boolean);
                    const show = filter === 'all' || cats.includes(filter);
                    card.classList.toggle('hidden', !show);
                });
            });
        });
    }

    cards.forEach((card) => {
        const href = card.dataset.href;
        if (!href) return;

        card.addEventListener('click', (event) => {
            if (event.target.closest('a, button')) {
                return;
            }

            window.location.href = href;
        });

        card.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            if (event.target.closest('a, button')) {
                return;
            }

            event.preventDefault();
            window.location.href = href;
        });
    });
});
