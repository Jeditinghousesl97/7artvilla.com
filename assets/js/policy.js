document.addEventListener('DOMContentLoaded', () => {

    const sections  = document.querySelectorAll('.policy-section[id]');
    const navLinks  = document.querySelectorAll('.policy-nav-link');

    function updateActiveSection() {
        let current = '';
        sections.forEach(section => {
            if (window.scrollY >= section.offsetTop - 140) {
                current = section.getAttribute('id');
            }
        });
        navLinks.forEach(link => {
            link.classList.toggle(
                'active',
                link.getAttribute('href') === `#${current}`
            );
        });
    }

    window.addEventListener('scroll', updateActiveSection, { passive: true });
    updateActiveSection();

    // Smooth scroll for sidebar links
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const target = document.querySelector(link.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

});
