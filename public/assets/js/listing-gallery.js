/* UC-25 Phase 2 — progressive-enhancement gallery, no dependencies. */
(() => {
    'use strict';

    document.querySelectorAll('[data-listing-gallery]').forEach((gallery) => {
        const mainImage = gallery.querySelector('[data-gallery-main]');
        const thumbs = Array.from(gallery.querySelectorAll('[data-gallery-thumbnail]'));
        const controls = gallery.querySelector('[data-gallery-controls]');
        const counter = gallery.querySelector('[data-gallery-counter]');
        const previous = gallery.querySelector('[data-gallery-prev]');
        const next = gallery.querySelector('[data-gallery-next]');
        const fallback = gallery.dataset.fallbackSrc || '/assets/images/listing/listing-default-img.jpg';

        if (!mainImage) return;

        // Broken image paths get a placeholder without triggering an error loop.
        const useFallback = (image) => {
            if (!image) return;
            image.addEventListener('error', () => {
                if (image.getAttribute('src') !== fallback) image.src = fallback;
            });
        };
        useFallback(mainImage);
        gallery.querySelectorAll('.listing-gallery__thumbnail img').forEach(useFallback);

        if (thumbs.length < 2) return;

        let selected = 0;
        const baseAlt = (mainImage.alt || 'Listing image').replace(/\s+[—-]\s+image\s+\d+\s+of\s+\d+$/i, '');

        const show = (index) => {
            selected = (index + thumbs.length) % thumbs.length;
            const choice = thumbs[selected];
            const src = choice.getAttribute('href');
            if (src) mainImage.src = src;
            mainImage.alt = `${baseAlt} — image ${selected + 1} of ${thumbs.length}`;
            if (counter) counter.textContent = `${selected + 1} / ${thumbs.length}`;

            thumbs.forEach((thumb, i) => {
                thumb.classList.toggle('is-active', i === selected);
                if (i === selected) thumb.setAttribute('aria-current', 'true');
                else thumb.removeAttribute('aria-current');
            });
        };

        thumbs.forEach((thumb, index) => {
            thumb.addEventListener('click', (event) => {
                event.preventDefault();
                show(index);
            });
        });

        if (previous) previous.addEventListener('click', () => show(selected - 1));
        if (next) next.addEventListener('click', () => show(selected + 1));

        // Arrow-key navigation when the image gallery has keyboard focus.
        gallery.addEventListener('keydown', (event) => {
            if (event.altKey || event.ctrlKey || event.metaKey) return;
            if (event.key === 'ArrowLeft') { event.preventDefault(); show(selected - 1); }
            if (event.key === 'ArrowRight') { event.preventDefault(); show(selected + 1); }
        });

        if (controls) controls.hidden = false;
    });
})();