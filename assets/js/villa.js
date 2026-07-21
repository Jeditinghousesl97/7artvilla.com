document.addEventListener('DOMContentLoaded', () => {
    const heroBg = document.querySelector('.villa-hero-bg');
    window.addEventListener('scroll', () => {
        if (!heroBg || window.scrollY > window.innerHeight * 1.2) return;
        heroBg.style.transform = `translateY(${window.scrollY * 0.3}px)`;
    }, { passive: true });

    const infoBar = document.querySelector('.villa-info-bar');
    const navbar = document.getElementById('navbar');
    function updateInfoBarTop() {
        if (!infoBar || !navbar) return;
        infoBar.style.top = navbar.offsetHeight + 'px';
    }
    window.addEventListener('scroll', updateInfoBarTop, { passive: true });
    window.addEventListener('resize', updateInfoBarTop);
    updateInfoBarTop();

    const revealMap = [
        { selector: '.villa-overview-content', cls: 'reveal-left', delay: 0 },
        { selector: '.villa-overview-image', cls: 'reveal-right', delay: 0.1 },
        { selector: '.villa-space-card', cls: 'reveal-up', stagger: 0.1 },
        { selector: '.space-section', cls: 'reveal-up', stagger: 0.12 },
        { selector: '.unit-card', cls: 'reveal-up', stagger: 0.06 },
        { selector: '.villa-list-card', cls: 'reveal-up', stagger: 0.12 },
        { selector: '.villa-info-item', cls: 'reveal-up', stagger: 0.06 },
        { selector: '.contact-form-wrap', cls: 'reveal-right', delay: 0.1 },
        { selector: '.contact-info-item', cls: 'reveal-left', stagger: 0.08 },
    ];

    revealMap.forEach(({ selector, cls, delay = 0, stagger }) => {
        document.querySelectorAll(selector).forEach((el, i) => {
            el.classList.add('reveal-el', cls);
            el.style.transitionDelay = `${stagger !== undefined ? i * stagger : delay}s`;
        });
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -32px 0px' });

    document.querySelectorAll('.reveal-el').forEach((el) => observer.observe(el));

    const unitTriggers = document.querySelectorAll('.unit-inquire-trigger');
    const spaceField = document.getElementById('villaSpaceIdField');
    const unitField = document.getElementById('bookableUnitIdField');
    const subjectField = document.getElementById('subjectLabelField');
    const guestField = document.getElementById('guest_count');
    const selectedView = document.getElementById('selectedUnitView');
    const selectedHeading = document.getElementById('selectedUnitHeading');
    const selectedGuests = document.getElementById('selectedUnitGuests');
    const selectedType = document.getElementById('selectedUnitType');
    const messageField = document.getElementById('message');
    const inquirySection = document.getElementById('stay-inquiry');
    const bookingModal = document.getElementById('bookingModal');
    const bookingModalCloseEls = document.querySelectorAll('[data-booking-modal-close]');
    const bookingModalTitle = document.getElementById('bookingModalTitle');
    const bookingModalSubtitle = document.getElementById('bookingModalSubtitle');
    const bookingModalType = document.getElementById('bookingModalType');
    const bookingModalGuests = document.getElementById('bookingModalGuests');
    const bookingModalPricingShell = document.getElementById('bookingModalPricingShell');
    const bookingModalSlider = document.getElementById('bookingModalSlider');
    const bookingModalPrev = document.getElementById('bookingModalPrev');
    const bookingModalNext = document.getElementById('bookingModalNext');
    const bookingModalProgress = document.getElementById('bookingModalProgress');
    const bookingInquiryForm = document.getElementById('bookingInquiryForm');
    const bookingFormSuccess = document.getElementById('bookingFormSuccess');
    const bookingSpaceField = document.getElementById('bookingVillaSpaceIdField');
    const bookingUnitField = document.getElementById('bookingBookableUnitIdField');
    const bookingSubjectField = document.getElementById('bookingSubjectLabelField');
    const bookingPricingLabelField = document.getElementById('bookingPricingLabelField');
    const bookingPricingSelectGroup = document.getElementById('bookingPricingSelectGroup');
    const bookingPricingSelect = document.getElementById('bookingPricingSelect');
    const bookingGuestField = document.getElementById('bookingGuestCount');
    const bookingAdultsField = document.getElementById('bookingAdults');
    const bookingChildrenField = document.getElementById('bookingChildren');
    const bookingSelectedUnitView = document.getElementById('bookingSelectedUnitView');
    const bookingMessageField = document.getElementById('bookingMessage');
    const gallerySlider = document.getElementById('villaGallerySlider');
    const gallerySlides = Array.from(document.querySelectorAll('[data-villa-gallery-item]'));
    const galleryPrev = document.querySelector('[data-villa-gallery-prev]');
    const galleryNext = document.querySelector('[data-villa-gallery-next]');
    const galleryProgress = document.getElementById('villaGalleryProgress');
    const galleryLightbox = document.getElementById('villaGalleryLightbox');
    const galleryLbImg = document.getElementById('villaGalleryLbImg');
    const galleryLbCaption = document.getElementById('villaGalleryLbCaption');
    const galleryLbCount = document.getElementById('villaGalleryLbCount');
    const galleryLbPrev = document.getElementById('villaGalleryLbPrev');
    const galleryLbNext = document.getElementById('villaGalleryLbNext');
    const galleryLbCloseEls = document.querySelectorAll('[data-villa-gallery-close]');
    const showcaseSlider = document.getElementById('spaceShowcaseSlider');
    const showcaseSlides = Array.from(document.querySelectorAll('[data-space-showcase-item]'));
    const showcasePrev = document.querySelector('[data-space-showcase-prev]');
    const showcaseNext = document.querySelector('[data-space-showcase-next]');
    const showcaseDots = Array.from(document.querySelectorAll('[data-space-showcase-dot]'));
    const listingSpaceSlider = document.querySelector('[data-villa-space-collection-slider]');
    const listingSpaceSlides = Array.from(document.querySelectorAll('[data-villa-space-collection-item]'));
    const listingSpacePrev = document.querySelector('[data-villa-space-collection-prev]');
    const listingSpaceNext = document.querySelector('[data-villa-space-collection-next]');
    const listingSpaceProgress = document.querySelector('[data-villa-space-collection-progress]');
    let activeGalleryIndex = 0;
    let galleryAutoTimer = null;
    let activeShowcaseIndex = 0;
    let activeListingSpaceIndex = 0;
    let bookingModalSlides = [];
    let activeBookingModalSlide = 0;
    let bookingModalScrollY = 0;
    let bookingPricingState = null;

    function updateBookingModalProgress() {
        if (!bookingModalSlider || !bookingModalProgress) return;
        const maxScroll = bookingModalSlider.scrollWidth - bookingModalSlider.clientWidth;
        const percent = maxScroll <= 0 ? 100 : Math.min(100, Math.max(0, (bookingModalSlider.scrollLeft / maxScroll) * 100));
        bookingModalProgress.style.width = percent + '%';
    }

    function scrollBookingModalTo(index, behavior = 'smooth') {
        if (!bookingModalSlider || !bookingModalSlides.length) return;
        activeBookingModalSlide = (index + bookingModalSlides.length) % bookingModalSlides.length;
        const slide = bookingModalSlides[activeBookingModalSlide];
        if (!slide) return;

        bookingModalSlider.scrollTo({
            left: Math.max(0, slide.offsetLeft - bookingModalSlider.offsetLeft),
            behavior
        });
    }

    function closeBookingModal() {
        if (!bookingModal) return;
        bookingModal.classList.remove('active');
        bookingModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.body.style.top = '';
        document.body.style.overflow = '';
        window.scrollTo(0, bookingModalScrollY);
    }

    function syncBookingGuestCount() {
        if (!bookingGuestField) return;
        const adults = Math.max(0, parseInt(bookingAdultsField?.value || '0', 10) || 0);
        const children = Math.max(0, parseInt(bookingChildrenField?.value || '0', 10) || 0);
        const parts = [];
        if (adults > 0) parts.push(`${adults} Adult${adults !== 1 ? 's' : ''}`);
        if (children > 0) parts.push(`${children} Child${children !== 1 ? 'ren' : ''}`);
        bookingGuestField.value = parts.join(', ');
    }

    function parseGuestText(guestText) {
        const value = guestText || '';
        const adultMatch = value.match(/(\d+)\s*adult/i);
        const childMatch = value.match(/(\d+)\s*(child|kid)/i);
        return {
            adults: adultMatch ? parseInt(adultMatch[1], 10) || 0 : 0,
            children: childMatch ? parseInt(childMatch[1], 10) || 0 : 0
        };
    }

    function buildBookingSelectionLabel(stayLabel, pricingSubject) {
        const cleanStay = (stayLabel || '').trim();
        const cleanPricing = (pricingSubject || '').trim();
        if (!cleanPricing) return cleanStay;
        return cleanStay ? `${cleanStay} - ${cleanPricing}` : cleanPricing;
    }

    function updateBookingMessage(selectionLabel) {
        if (!bookingMessageField) return;
        const finalLabel = (selectionLabel || '').trim() || 'this stay option';
        bookingMessageField.value = `I'm interested in booking ${finalLabel}. Please share availability and pricing details.`;
    }

    function syncBookingSelection(stayLabel, pricingSubject = '') {
        const finalSelection = buildBookingSelectionLabel(stayLabel, pricingSubject);
        if (bookingPricingLabelField) bookingPricingLabelField.value = pricingSubject;
        if (bookingSubjectField) bookingSubjectField.value = finalSelection;
        if (bookingSelectedUnitView) bookingSelectedUnitView.value = finalSelection;
        updateBookingMessage(finalSelection);
    }

    function updatePricingDots(activeIndex) {
        if (!bookingPricingState) return;
        bookingPricingState.dots.forEach((dot, index) => {
            const isActive = index === activeIndex;
            dot.classList.toggle('is-active', isActive);
            dot.setAttribute('aria-current', isActive ? 'true' : 'false');
        });
        if (bookingPricingState.progress) {
            const total = bookingPricingState.slides.length;
            const percent = total <= 1 ? 100 : ((activeIndex + 1) / total) * 100;
            bookingPricingState.progress.style.width = `${percent}%`;
        }
    }

    function applySelectedPricing(activeIndex, shouldScroll = false) {
        if (!bookingPricingState || !bookingPricingState.slides.length) return;
        const total = bookingPricingState.slides.length;
        const nextIndex = ((activeIndex % total) + total) % total;
        bookingPricingState.activeIndex = nextIndex;

        bookingPricingState.slides.forEach((slide, index) => {
            const button = slide.querySelector('[data-booking-pricing-select]');
            const isActive = index === nextIndex;
            button?.classList.toggle('is-selected', isActive);
            button?.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        const activeSlide = bookingPricingState.slides[nextIndex];
        updatePricingDots(nextIndex);

        if (shouldScroll) {
            bookingPricingState.slider?.scrollTo({
                left: Math.max(0, activeSlide.offsetLeft - bookingPricingState.slider.offsetLeft),
                behavior: 'smooth'
            });
        }
    }

    function setupBookingPricingSlider(stayLabel) {
        if (!bookingModalPricingShell || bookingModalPricingShell.hidden) {
            bookingPricingState = null;
            if (bookingPricingSelectGroup) bookingPricingSelectGroup.hidden = true;
            if (bookingPricingSelect) {
                bookingPricingSelect.required = false;
                bookingPricingSelect.innerHTML = '<option value="">Choose a pricing package</option>';
            }
            syncBookingSelection(stayLabel, '');
            return;
        }

        const slider = bookingModalPricingShell.querySelector('[data-booking-pricing-slider]');
        const slides = Array.from(bookingModalPricingShell.querySelectorAll('[data-booking-pricing-item]'));
        const dots = Array.from(bookingModalPricingShell.querySelectorAll('[data-booking-pricing-dot]'));
        const progress = bookingModalPricingShell.querySelector('[data-booking-pricing-progress]');
        const prev = bookingModalPricingShell.querySelector('[data-booking-pricing-prev]');
        const next = bookingModalPricingShell.querySelector('[data-booking-pricing-next]');

        bookingPricingState = {
            stayLabel,
            slider,
            slides,
            dots,
            progress,
            prev,
            next,
            activeIndex: 0
        };

        if (!slider || !slides.length) {
            syncBookingSelection(stayLabel, '');
            return;
        }

        dots.forEach((dot) => {
            dot.addEventListener('click', () => {
                const index = parseInt(dot.dataset.bookingPricingDot || '0', 10) || 0;
                applySelectedPricing(index, true);
            });
        });

        prev?.addEventListener('click', () => applySelectedPricing(bookingPricingState.activeIndex - 1, true));
        next?.addEventListener('click', () => applySelectedPricing(bookingPricingState.activeIndex + 1, true));

        slider.addEventListener('scroll', () => {
            if (!bookingPricingState) return;
            const slideWidth = slider.clientWidth || 1;
            const index = Math.round(slider.scrollLeft / slideWidth);
            if (index !== bookingPricingState.activeIndex) {
                applySelectedPricing(index, false);
            } else {
                updatePricingDots(index);
            }
        }, { passive: true });

        const initialIndex = Math.max(0, slides.findIndex((slide) => slide.querySelector('.is-selected')));
        applySelectedPricing(initialIndex, false);
        if (bookingPricingSelect) {
            bookingPricingSelect.innerHTML = '<option value="">Choose a pricing package</option>';
            slides.forEach((slide, index) => {
                const option = document.createElement('option');
                option.value = slide.dataset.pricingSubject || '';
                option.textContent = slide.dataset.pricingSubject || `Package ${index + 1}`;
                option.dataset.pricingIndex = String(index);
                bookingPricingSelect.appendChild(option);
            });
        }
        if (bookingPricingSelectGroup) bookingPricingSelectGroup.hidden = false;
        if (bookingPricingSelect) {
            bookingPricingSelect.required = true;
            bookingPricingSelect.value = '';
        }
        syncBookingSelection(stayLabel, '');
    }

    function openBookingModal(button) {
        if (!bookingModal || !bookingInquiryForm || !bookingModalSlider) return;

        const unitCard = button.closest('.unit-card');
        const mediaSlides = Array.from(unitCard?.querySelectorAll('[data-unit-gallery-item]') || []);
        const unitName = button.dataset.subject || 'Selected stay option';
        const unitSubtitle = button.dataset.unitSubtitle || 'Choose your dates and send your booking request.';
        const unitType = button.dataset.unitType || 'Selected bookable unit';
        const unitGuests = button.dataset.guest || 'Flexible';
        const spaceName = button.dataset.spaceName || '';
        const isSharedUnit = (button.dataset.unitScope || '') === 'shared';
        const selectedStayLabel = isSharedUnit && spaceName ? `${unitName} - ${spaceName}` : unitName;
        const guestSplit = parseGuestText(unitGuests);

        bookingModalTitle.textContent = unitName;
        bookingModalSubtitle.textContent = unitSubtitle || 'Choose your dates and send your booking request.';
        bookingModalType.textContent = unitType;
        bookingModalGuests.textContent = unitGuests;
        if (bookingModalPricingShell) {
            bookingModalPricingShell.innerHTML = '';
            const pricingTemplate = unitCard?.querySelector('.unit-pricing-popup-template');
            if (pricingTemplate) {
                const pricingContent = pricingTemplate.firstElementChild?.cloneNode(true);
                if (pricingContent) bookingModalPricingShell.appendChild(pricingContent);
                bookingModalPricingShell.hidden = false;
            } else {
                bookingModalPricingShell.hidden = true;
            }
        }
        if (bookingPricingSelectGroup) bookingPricingSelectGroup.hidden = true;
        if (bookingPricingSelect) {
            bookingPricingSelect.required = false;
            bookingPricingSelect.innerHTML = '<option value="">Choose a pricing package</option>';
        }
        if (bookingSpaceField) bookingSpaceField.value = button.dataset.spaceId || '';
        if (bookingUnitField) bookingUnitField.value = button.dataset.unitId || '';
        if (bookingAdultsField) bookingAdultsField.value = guestSplit.adults > 0 ? String(guestSplit.adults) : '';
        if (bookingChildrenField) bookingChildrenField.value = guestSplit.children > 0 ? String(guestSplit.children) : '';
        syncBookingGuestCount();
        setupBookingPricingSlider(selectedStayLabel);

        bookingModalSlider.innerHTML = '';
        mediaSlides.forEach((slide) => {
            const img = slide.querySelector('img');
            if (!img) return;
            const fig = document.createElement('figure');
            fig.className = 'booking-modal-slide';

            const modalImg = document.createElement('img');
            modalImg.src = img.src;
            modalImg.alt = img.alt || unitName;
            fig.appendChild(modalImg);

            const captionText = slide.querySelector('figcaption')?.textContent?.trim() || img.alt || '';
            if (captionText) {
                const caption = document.createElement('figcaption');
                caption.textContent = captionText;
                fig.appendChild(caption);
            }
            bookingModalSlider.appendChild(fig);
        });

        bookingModalSlides = Array.from(bookingModalSlider.querySelectorAll('.booking-modal-slide'));
        activeBookingModalSlide = 0;
        scrollBookingModalTo(0, 'auto');
        updateBookingModalProgress();

        const showNav = bookingModalSlides.length > 1;
        if (bookingModalPrev) bookingModalPrev.style.display = showNav ? 'inline-flex' : 'none';
        if (bookingModalNext) bookingModalNext.style.display = showNav ? 'inline-flex' : 'none';
        if (bookingModalProgress?.parentElement) bookingModalProgress.parentElement.style.display = showNav ? 'block' : 'none';

        bookingInquiryForm.style.display = '';
        bookingFormSuccess?.classList.remove('active');
        bookingInquiryForm.querySelector('.form-submit-error')?.remove();
        bookingModal.classList.add('active');
        bookingModal.setAttribute('aria-hidden', 'false');
        bookingModalScrollY = window.scrollY || window.pageYOffset || 0;
        document.body.classList.add('modal-open');
        document.body.style.top = `-${bookingModalScrollY}px`;
        document.body.style.overflow = 'hidden';
    }

    unitTriggers.forEach((button) => {
        button.addEventListener('click', () => {
            if (spaceField) spaceField.value = button.dataset.spaceId || '';
            if (unitField) unitField.value = button.dataset.unitId || '';
            if (subjectField) subjectField.value = button.dataset.subject || '';
            if (selectedView) selectedView.value = button.dataset.subject || '';
            if (selectedHeading) selectedHeading.textContent = button.dataset.subject || '';
            if (selectedGuests) selectedGuests.textContent = button.dataset.guest || 'Flexible';
            if (selectedType) selectedType.textContent = 'Selected bookable unit';
            if (guestField && !guestField.value && button.dataset.guest) guestField.value = button.dataset.guest;
            if (messageField) {
                messageField.value = `I'm interested in booking ${button.dataset.subject || 'this stay option'}. Please share availability and pricing details.`;
            }
            openBookingModal(button);
        });
    });

    bookingModalPrev?.addEventListener('click', () => scrollBookingModalTo(activeBookingModalSlide - 1));
    bookingModalNext?.addEventListener('click', () => scrollBookingModalTo(activeBookingModalSlide + 1));
    bookingModalSlider?.addEventListener('scroll', updateBookingModalProgress, { passive: true });

    if (bookingModalSlider) {
        const bookingModalObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const idx = bookingModalSlides.indexOf(entry.target);
                    if (idx !== -1) activeBookingModalSlide = idx;
                }
            });
        }, {
            root: bookingModalSlider,
            threshold: 0.6
        });

        const observeBookingModalSlides = () => {
            bookingModalSlides.forEach((slide) => bookingModalObserver.observe(slide));
        };
        const bookingModalMutation = new MutationObserver(() => {
            bookingModalObserver.disconnect();
            bookingModalSlides = Array.from(bookingModalSlider.querySelectorAll('.booking-modal-slide'));
            observeBookingModalSlides();
        });
        bookingModalMutation.observe(bookingModalSlider, { childList: true });
    }

    bookingModalCloseEls.forEach((element) => element.addEventListener('click', closeBookingModal));

    document.addEventListener('keydown', (event) => {
        if (!bookingModal || !bookingModal.classList.contains('active')) return;
        if (event.key === 'Escape') closeBookingModal();
    });

    function updateGalleryProgress() {
        if (!gallerySlider || !galleryProgress) return;
        const maxScroll = gallerySlider.scrollWidth - gallerySlider.clientWidth;
        const percent = maxScroll <= 0 ? 100 : Math.min(100, Math.max(0, (gallerySlider.scrollLeft / maxScroll) * 100));
        galleryProgress.style.width = percent + '%';
    }

    function scrollGalleryTo(index, behavior = 'smooth') {
        if (!gallerySlider || !gallerySlides.length) return;
        activeGalleryIndex = (index + gallerySlides.length) % gallerySlides.length;
        const slide = gallerySlides[activeGalleryIndex];
        if (!slide) return;

        const targetLeft = slide.offsetLeft - gallerySlider.offsetLeft;
        gallerySlider.scrollTo({
            left: Math.max(0, targetLeft),
            behavior
        });
    }

    function startGalleryAutoplay() {
        if (!gallerySlider || gallerySlides.length < 2) return;
        window.clearInterval(galleryAutoTimer);
        galleryAutoTimer = window.setInterval(() => {
            scrollGalleryTo(activeGalleryIndex + 1);
        }, 4200);
    }

    function stopGalleryAutoplay() {
        window.clearInterval(galleryAutoTimer);
    }

    function openGalleryLightbox(index) {
        if (!galleryLightbox || !gallerySlides.length) return;
        activeGalleryIndex = (index + gallerySlides.length) % gallerySlides.length;
        const slide = gallerySlides[activeGalleryIndex];
        const img = slide.querySelector('img');
        const caption = slide.querySelector('figcaption')?.textContent || img?.alt || '';
        if (!img || !galleryLbImg) return;

        galleryLbImg.src = img.src;
        galleryLbImg.alt = img.alt || '';
        if (galleryLbCaption) galleryLbCaption.textContent = caption;
        if (galleryLbCount) galleryLbCount.textContent = (activeGalleryIndex + 1) + ' / ' + gallerySlides.length;

        galleryLightbox.classList.add('active');
        galleryLightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeGalleryLightbox() {
        if (!galleryLightbox) return;
        galleryLightbox.classList.remove('active');
        galleryLightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (gallerySlider && gallerySlides.length) {
        updateGalleryProgress();
        startGalleryAutoplay();

        galleryPrev?.addEventListener('click', () => {
            stopGalleryAutoplay();
            scrollGalleryTo(activeGalleryIndex - 1);
            startGalleryAutoplay();
        });

        galleryNext?.addEventListener('click', () => {
            stopGalleryAutoplay();
            scrollGalleryTo(activeGalleryIndex + 1);
            startGalleryAutoplay();
        });

        gallerySlider.addEventListener('scroll', updateGalleryProgress, { passive: true });
        gallerySlider.addEventListener('mouseenter', stopGalleryAutoplay);
        gallerySlider.addEventListener('mouseleave', startGalleryAutoplay);
        gallerySlider.addEventListener('touchstart', stopGalleryAutoplay, { passive: true });
        gallerySlider.addEventListener('touchend', startGalleryAutoplay, { passive: true });

        const galleryObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const idx = gallerySlides.indexOf(entry.target);
                    if (idx !== -1) activeGalleryIndex = idx;
                }
            });
        }, {
            root: gallerySlider,
            threshold: 0.6
        });

        gallerySlides.forEach((slide, index) => {
            galleryObserver.observe(slide);
            slide.addEventListener('click', (event) => {
                if (event.target.closest('.villa-gallery-zoom')) {
                    openGalleryLightbox(index);
                    return;
                }
                openGalleryLightbox(index);
            });
        });
    }

    function updateShowcasePagination() {
        if (!showcaseDots.length) return;
        showcaseDots.forEach((dot, index) => {
            dot.classList.toggle('is-active', index === activeShowcaseIndex);
        });
    }

    function scrollShowcaseTo(index, behavior = 'smooth') {
        if (!showcaseSlider || !showcaseSlides.length) return;
        activeShowcaseIndex = (index + showcaseSlides.length) % showcaseSlides.length;
        updateShowcasePagination();
        const slide = showcaseSlides[activeShowcaseIndex];
        if (!slide) return;

        showcaseSlider.scrollTo({
            left: Math.max(0, slide.offsetLeft - showcaseSlider.offsetLeft),
            behavior
        });
    }

    if (showcaseSlider && showcaseSlides.length) {
        updateShowcasePagination();

        showcasePrev?.addEventListener('click', () => scrollShowcaseTo(activeShowcaseIndex - 1));
        showcaseNext?.addEventListener('click', () => scrollShowcaseTo(activeShowcaseIndex + 1));
        showcaseDots.forEach((dot, index) => {
            dot.addEventListener('click', () => scrollShowcaseTo(index));
        });

        const showcaseObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const idx = showcaseSlides.indexOf(entry.target);
                    if (idx !== -1) {
                        activeShowcaseIndex = idx;
                        updateShowcasePagination();
                    }
                }
            });
        }, {
            root: showcaseSlider,
            threshold: 0.6
        });

        showcaseSlides.forEach((slide) => showcaseObserver.observe(slide));
    }

    function updateListingSpaceProgress() {
        if (!listingSpaceSlider || !listingSpaceProgress) return;
        const maxScroll = listingSpaceSlider.scrollWidth - listingSpaceSlider.clientWidth;
        const percent = maxScroll <= 0 ? 100 : Math.min(100, Math.max(0, (listingSpaceSlider.scrollLeft / maxScroll) * 100));
        listingSpaceProgress.style.width = percent + '%';
    }

    function scrollListingSpaceTo(index, behavior = 'smooth') {
        if (!listingSpaceSlider || !listingSpaceSlides.length) return;
        activeListingSpaceIndex = (index + listingSpaceSlides.length) % listingSpaceSlides.length;
        const slide = listingSpaceSlides[activeListingSpaceIndex];
        if (!slide) return;

        listingSpaceSlider.scrollTo({
            left: Math.max(0, slide.offsetLeft - listingSpaceSlider.offsetLeft),
            behavior
        });
    }

    if (listingSpaceSlider && listingSpaceSlides.length) {
        updateListingSpaceProgress();

        listingSpacePrev?.addEventListener('click', () => scrollListingSpaceTo(activeListingSpaceIndex - 1));
        listingSpaceNext?.addEventListener('click', () => scrollListingSpaceTo(activeListingSpaceIndex + 1));
        listingSpaceSlider.addEventListener('scroll', updateListingSpaceProgress, { passive: true });

        const listingSpaceObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const idx = listingSpaceSlides.indexOf(entry.target);
                    if (idx !== -1) activeListingSpaceIndex = idx;
                }
            });
        }, {
            root: listingSpaceSlider,
            threshold: 0.6
        });

        listingSpaceSlides.forEach((slide) => listingSpaceObserver.observe(slide));
    }

    galleryLbPrev?.addEventListener('click', () => openGalleryLightbox(activeGalleryIndex - 1));
    galleryLbNext?.addEventListener('click', () => openGalleryLightbox(activeGalleryIndex + 1));
    galleryLbCloseEls.forEach((element) => element.addEventListener('click', closeGalleryLightbox));

    document.addEventListener('keydown', (event) => {
        if (!galleryLightbox || !galleryLightbox.classList.contains('active')) return;
        if (event.key === 'Escape') closeGalleryLightbox();
        if (event.key === 'ArrowLeft') openGalleryLightbox(activeGalleryIndex - 1);
        if (event.key === 'ArrowRight') openGalleryLightbox(activeGalleryIndex + 1);
    });

    let galleryTouchStartX = 0;
    galleryLightbox?.addEventListener('touchstart', (event) => {
        galleryTouchStartX = event.changedTouches[0].clientX;
    }, { passive: true });

    galleryLightbox?.addEventListener('touchend', (event) => {
        const diff = galleryTouchStartX - event.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            diff > 0 ? openGalleryLightbox(activeGalleryIndex + 1) : openGalleryLightbox(activeGalleryIndex - 1);
        }
    }, { passive: true });

    if (bookingInquiryForm) {
        const today = new Date().toISOString().split('T')[0];
        const bookingCheckin = bookingInquiryForm.querySelector('#bookingCheckin');
        const bookingCheckout = bookingInquiryForm.querySelector('#bookingCheckout');
        const bookingTurnstileEl = bookingInquiryForm.querySelector('.cf-turnstile');
        const turnstileResponseField = () => bookingInquiryForm.querySelector('[name="cf-turnstile-response"]');

        if (bookingCheckin) bookingCheckin.setAttribute('min', today);
        if (bookingCheckout) bookingCheckout.setAttribute('min', today);

        bookingCheckin?.addEventListener('change', () => {
            if (bookingCheckout && bookingCheckin.value) {
                bookingCheckout.setAttribute('min', bookingCheckin.value);
            }
        });

        bookingPricingSelect?.addEventListener('change', () => {
            const selectedOption = bookingPricingSelect.options[bookingPricingSelect.selectedIndex];
            const pricingSubject = bookingPricingSelect.value || '';
            syncBookingSelection(bookingPricingState?.stayLabel || (bookingSelectedUnitView?.value ?? ''), pricingSubject);

            const pricingIndex = parseInt(selectedOption?.dataset.pricingIndex || '-1', 10);
            if (pricingIndex >= 0) {
                applySelectedPricing(pricingIndex, true);
            }
        });

        bookingInquiryForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            let valid = true;
            bookingInquiryForm.querySelector('.turnstile-error')?.remove();
            bookingInquiryForm.querySelectorAll('[required]').forEach((field) => {
                const hasError = !field.value.trim();
                field.style.borderColor = hasError ? '#e74c3c' : '';
                field.style.boxShadow = hasError ? '0 0 0 3px rgba(231,76,60,0.15)' : '';
                if (hasError) valid = false;
            });

            if (!valid) {
                bookingInquiryForm.querySelector('[required]')?.focus();
                return;
            }

            if (bookingTurnstileEl && !turnstileResponseField()?.value?.trim()) {
                const turnstileError = document.createElement('p');
                turnstileError.className = 'turnstile-error';
                turnstileError.textContent = 'Please complete the security verification before submitting.';
                bookingTurnstileEl.parentElement?.appendChild(turnstileError);
                bookingTurnstileEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            const submitBtn = bookingInquiryForm.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }

            bookingInquiryForm.querySelector('.form-submit-error')?.remove();

            try {
                const response = await fetch('send-inquiry.php', {
                    method: 'POST',
                    body: new FormData(bookingInquiryForm)
                });
                const data = await response.json();

                if (data && data.ok) {
                    bookingInquiryForm.style.display = 'none';
                    bookingFormSuccess?.classList.add('active');
                } else {
                    const err = document.createElement('p');
                    err.className = 'form-submit-error';
                    err.style.cssText = 'color:#e74c3c;font-size:0.85rem;margin-top:10px';
                    err.textContent = (data && data.msg) ? data.msg : 'Something went wrong. Please try again.';
                    bookingInquiryForm.appendChild(err);
                }
            } catch (error) {
                const errorEl = document.createElement('p');
                errorEl.className = 'form-submit-error';
                errorEl.style.cssText = 'color:#e74c3c;font-size:0.85rem;margin-top:10px';
                errorEl.textContent = 'Network error. Please check your connection and try again.';
                bookingInquiryForm.appendChild(errorEl);
            } finally {
                if (window.turnstile && bookingTurnstileEl) {
                    try { window.turnstile.reset(bookingTurnstileEl); } catch (e) {}
                }

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || 'Send Booking Request';
                }
            }
        });

        bookingInquiryForm.querySelectorAll('input, textarea, select').forEach((field) => {
            field.addEventListener('input', () => {
                field.style.borderColor = '';
                field.style.boxShadow = '';
            });
            field.addEventListener('change', () => {
                field.style.borderColor = '';
                field.style.boxShadow = '';
            });
        });

        bookingAdultsField?.addEventListener('input', syncBookingGuestCount);
        bookingChildrenField?.addEventListener('input', syncBookingGuestCount);

        bookingTurnstileEl?.addEventListener('change', () => {
            bookingInquiryForm.querySelector('.turnstile-error')?.remove();
        });
    }

    const unitGalleryLightbox = document.getElementById('unitGalleryLightbox');
    const unitGalleryLbImg = document.getElementById('unitGalleryLbImg');
    const unitGalleryLbCaption = document.getElementById('unitGalleryLbCaption');
    const unitGalleryLbCount = document.getElementById('unitGalleryLbCount');
    const unitGalleryLbPrev = document.getElementById('unitGalleryLbPrev');
    const unitGalleryLbNext = document.getElementById('unitGalleryLbNext');
    const unitGalleryCloseEls = document.querySelectorAll('[data-unit-gallery-close]');
    let activeUnitGallery = null;
    let unitGalleryTouchStartX = 0;

    function closeUnitGalleryLightbox() {
        if (!unitGalleryLightbox) return;
        unitGalleryLightbox.classList.remove('active');
        unitGalleryLightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function openUnitGalleryLightbox(index) {
        if (!activeUnitGallery || !unitGalleryLightbox || !unitGalleryLbImg) return;
        const { slides } = activeUnitGallery;
        activeUnitGallery.activeIndex = (index + slides.length) % slides.length;
        const slide = slides[activeUnitGallery.activeIndex];
        const img = slide.querySelector('img');
        const caption = slide.querySelector('figcaption')?.textContent || img?.alt || '';
        if (!img) return;

        unitGalleryLbImg.src = img.src;
        unitGalleryLbImg.alt = img.alt || '';
        if (unitGalleryLbCaption) unitGalleryLbCaption.textContent = caption;
        if (unitGalleryLbCount) unitGalleryLbCount.textContent = (activeUnitGallery.activeIndex + 1) + ' / ' + slides.length;

        unitGalleryLightbox.classList.add('active');
        unitGalleryLightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    document.querySelectorAll('[data-unit-gallery]').forEach((galleryRoot) => {
        const slider = galleryRoot.querySelector('[data-unit-gallery-slider]');
        const slides = Array.from(galleryRoot.querySelectorAll('[data-unit-gallery-item]'));
        const prevBtn = galleryRoot.querySelector('[data-unit-gallery-prev]');
        const nextBtn = galleryRoot.querySelector('[data-unit-gallery-next]');
        const state = { slider, slides, activeIndex: 0 };

        if (!slider || !slides.length) return;

        function scrollToIndex(index, behavior = 'smooth') {
            state.activeIndex = (index + slides.length) % slides.length;
            const slide = slides[state.activeIndex];
            if (!slide) return;

            slider.scrollTo({
                left: Math.max(0, slide.offsetLeft - slider.offsetLeft),
                behavior
            });
        }

        prevBtn?.addEventListener('click', () => scrollToIndex(state.activeIndex - 1));
        nextBtn?.addEventListener('click', () => scrollToIndex(state.activeIndex + 1));

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const idx = slides.indexOf(entry.target);
                    if (idx !== -1) state.activeIndex = idx;
                }
            });
        }, {
            root: slider,
            threshold: 0.6
        });

        slides.forEach((slide, index) => {
            observer.observe(slide);
            slide.addEventListener('click', () => {
                activeUnitGallery = state;
                openUnitGalleryLightbox(index);
            });
        });
    });

    unitGalleryLbPrev?.addEventListener('click', () => {
        if (!activeUnitGallery) return;
        openUnitGalleryLightbox(activeUnitGallery.activeIndex - 1);
    });

    unitGalleryLbNext?.addEventListener('click', () => {
        if (!activeUnitGallery) return;
        openUnitGalleryLightbox(activeUnitGallery.activeIndex + 1);
    });

    unitGalleryCloseEls.forEach((element) => element.addEventListener('click', closeUnitGalleryLightbox));

    document.addEventListener('keydown', (event) => {
        if (!unitGalleryLightbox || !unitGalleryLightbox.classList.contains('active') || !activeUnitGallery) return;
        if (event.key === 'Escape') closeUnitGalleryLightbox();
        if (event.key === 'ArrowLeft') openUnitGalleryLightbox(activeUnitGallery.activeIndex - 1);
        if (event.key === 'ArrowRight') openUnitGalleryLightbox(activeUnitGallery.activeIndex + 1);
    });

    unitGalleryLightbox?.addEventListener('touchstart', (event) => {
        unitGalleryTouchStartX = event.changedTouches[0].clientX;
    }, { passive: true });

    unitGalleryLightbox?.addEventListener('touchend', (event) => {
        if (!activeUnitGallery) return;
        const diff = unitGalleryTouchStartX - event.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            diff > 0
                ? openUnitGalleryLightbox(activeUnitGallery.activeIndex + 1)
                : openUnitGalleryLightbox(activeUnitGallery.activeIndex - 1);
        }
    }, { passive: true });
});
