(() => {
    'use strict';

    document.querySelectorAll('[data-seller-image]').forEach((image) => {
        const container = image.closest('.seller-card__image');

        if (!container) return;

        const placeholder = container.querySelector(
            '[data-seller-placeholder]'
        );

        if (!placeholder) return;

        const showImage = () => {
            // The actual image loaded successfully.
            // Remove the unused fallback icon entirely.
            placeholder.remove();
        };

        const showFallback = () => {
            // The image failed to load.
            // Remove the broken image and reveal the user icon.
            image.remove();
            placeholder.hidden = false;
        };

        image.addEventListener('load', showImage, { once: true });
        image.addEventListener('error', showFallback, { once: true });

        // Handle images that finished loading before this script ran.
        if (image.complete) {
            if (image.naturalWidth > 0) {
                showImage();
            } else {
                showFallback();
            }
        }
    });
})();
