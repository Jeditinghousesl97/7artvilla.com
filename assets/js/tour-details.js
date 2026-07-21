document.addEventListener('DOMContentLoaded', () => {
    const albumItems = Array.from(document.querySelectorAll('.tour-album-figure'));
    const itineraryItems = Array.from(document.querySelectorAll('.tour-itinerary-public-images img'));
    const lightboxItems = [
        ...albumItems.map((item) => {
            const img = item.querySelector('img');
            return {
                trigger: item,
                img,
                caption: item.querySelector('figcaption')?.textContent || img?.alt || ''
            };
        }),
        ...itineraryItems.map((img) => ({
            trigger: img,
            img,
            caption: img.closest('.tour-itinerary-public-body')?.querySelector('h3')?.textContent || img.alt || ''
        }))
    ].filter((item) => item.img);
    const lightbox = document.getElementById('tourAlbumLightbox');
    const lbImg = document.getElementById('tourAlbumLbImg');
    const lbCaption = document.getElementById('tourAlbumLbCaption');
    const lbCount = document.getElementById('tourAlbumLbCount');
    const lbPrev = document.getElementById('tourAlbumPrev');
    const lbNext = document.getElementById('tourAlbumNext');
    const lbCloseEls = document.querySelectorAll('[data-album-close]');
    let currentIndex = 0;

    function openLightbox(index) {
        if (!lightbox || !lightboxItems.length) return;
        currentIndex = Math.max(0, Math.min(index, lightboxItems.length - 1));
        const item = lightboxItems[currentIndex];
        const img = item.img;
        const caption = item.caption || img?.alt || '';
        if (!img) return;

        lbImg.src = img.src;
        lbImg.alt = img.alt || '';
        lbCaption.textContent = caption;
        lbCount.textContent = (currentIndex + 1) + ' / ' + lightboxItems.length;

        lightbox.classList.add('active');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.classList.remove('active');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function showNext() {
        if (!lightboxItems.length) return;
        openLightbox((currentIndex + 1) % lightboxItems.length);
    }

    function showPrev() {
        if (!lightboxItems.length) return;
        openLightbox((currentIndex - 1 + lightboxItems.length) % lightboxItems.length);
    }

    lightboxItems.forEach((item, index) => {
        item.trigger.addEventListener('click', () => openLightbox(index));
    });

    if (lbPrev) lbPrev.addEventListener('click', showPrev);
    if (lbNext) lbNext.addEventListener('click', showNext);
    lbCloseEls.forEach(el => el.addEventListener('click', closeLightbox));

    document.addEventListener('keydown', (e) => {
        if (!lightbox || !lightbox.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowRight') showNext();
        if (e.key === 'ArrowLeft') showPrev();
    });

    let touchStartX = 0;
    if (lightbox) {
        lightbox.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].clientX;
        }, { passive: true });
        lightbox.addEventListener('touchend', (e) => {
            const diff = touchStartX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 50) {
                diff > 0 ? showNext() : showPrev();
            }
        });
    }

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
                    submitBtn.innerHTML = submitBtn.dataset.originalText || 'Send Inquiry';
                }
            }
        });
    });

    const inquiryForm = document.getElementById('inquiryForm');
    const inquirySuccess = document.getElementById('formSuccess');

    if (inquiryForm) {
        const today = new Date().toISOString().split('T')[0];
        const checkin = inquiryForm.querySelector('#checkin');
        const checkout = inquiryForm.querySelector('#checkout');
        const turnstileEl = inquiryForm.querySelector('.cf-turnstile');

        if (checkin) checkin.setAttribute('min', today);
        if (checkout) checkout.setAttribute('min', today);

        checkin?.addEventListener('change', () => {
            if (checkout && checkin.value) {
                checkout.setAttribute('min', checkin.value);
            }
        });

        inquiryForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            let valid = true;
            inquiryForm.querySelectorAll('[required]').forEach((field) => {
                const hasError = !field.value.trim();
                field.style.borderColor = hasError ? '#e74c3c' : '';
                field.style.boxShadow = hasError ? '0 0 0 3px rgba(231,76,60,0.15)' : '';
                if (hasError) valid = false;
            });

            if (!valid) {
                inquiryForm.querySelector('[required]')?.focus();
                return;
            }

            const submitBtn = inquiryForm.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }

            inquiryForm.querySelector('.form-submit-error')?.remove();

            try {
                const res = await fetch('send-inquiry.php', {
                    method: 'POST',
                    body: new FormData(inquiryForm)
                });
                const data = await res.json();

                if (data && data.ok) {
                    inquiryForm.style.display = 'none';
                    inquirySuccess?.classList.add('active');
                } else {
                    const err = document.createElement('p');
                    err.className = 'form-submit-error';
                    err.style.cssText = 'color:#e74c3c;font-size:0.85rem;margin-top:10px';
                    err.textContent = (data && data.msg) ? data.msg : 'Something went wrong. Please try again.';
                    inquiryForm.appendChild(err);
                }
            } catch (err) {
                const errorEl = document.createElement('p');
                errorEl.className = 'form-submit-error';
                errorEl.style.cssText = 'color:#e74c3c;font-size:0.85rem;margin-top:10px';
                errorEl.textContent = 'Network error. Please check your connection and try again.';
                inquiryForm.appendChild(errorEl);
            } finally {
                if (window.turnstile && turnstileEl) {
                    try { window.turnstile.reset(turnstileEl); } catch (e) {}
                }

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || 'Send Inquiry';
                }
            }
        });

        inquiryForm.querySelectorAll('input, textarea').forEach((field) => {
            field.addEventListener('input', () => {
                field.style.borderColor = '';
                field.style.boxShadow = '';
            });
        });
    }
});
