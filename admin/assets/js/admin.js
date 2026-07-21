document.addEventListener('DOMContentLoaded', () => {

    // Sidebar toggle (mobile) 
    const sidebar  = document.getElementById('adminSidebar');
    const toggle   = document.getElementById('sidebarToggle');
    const overlay  = document.getElementById('sidebarOverlay');

    function openSidebar()  { sidebar?.classList.add('open'); overlay?.classList.add('open'); }
    function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('open'); }

    toggle?.addEventListener('click', () => {
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay?.addEventListener('click', closeSidebar);

    // Auto-dismiss alerts 
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-4px)';
            setTimeout(() => el.remove(), 400);
        }, 3500);
    });

    // Confirm delete prompts 
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const msg = el.dataset.confirm || 'Are you sure you want to delete this?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // Bar chart entrance animation 
    const bars = document.querySelectorAll('.bar-fill');
    if (bars.length) {
        setTimeout(() => {
            bars.forEach(bar => bar.classList.add('animated'));
        }, 200);
    }

    // Image preview on file input 
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        const previewId = input.dataset.preview;
        const preview   = document.getElementById(previewId);
        if (!preview) return;
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Toggle active state via AJAX 
    document.querySelectorAll('.toggle-active').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const id     = this.dataset.id;
            const table  = this.dataset.table;
            const active = this.checked ? 1 : 0;

            fetch('ajax/toggle-active.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, table, active, csrf: document.querySelector('meta[name="csrf"]')?.content })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    this.checked = !this.checked; // revert
                    alert('Failed to update status.');
                }
            })
            .catch(() => { this.checked = !this.checked; });
        });
    });

    // Settings section nav: highlight active on scroll 
    const ssnLinks = document.querySelectorAll('.ssn-link[href^="#"]');
    if (ssnLinks.length) {
        const ssnSections = Array.from(ssnLinks).map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);
        const topbarH = 64 + 56;

        function updateActiveSection() {
            let current = ssnSections[0];
            ssnSections.forEach(sec => {
                if (sec.getBoundingClientRect().top <= topbarH + 16) current = sec;
            });
            ssnLinks.forEach(a => {
                a.classList.toggle('active', a.getAttribute('href') === '#' + current.id);
            });
        }

        document.addEventListener('scroll', updateActiveSection, { passive: true });
        updateActiveSection();
    }

});
