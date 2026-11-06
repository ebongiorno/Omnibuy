(() => {
    'use strict';

    const fallback = '/assets/icons/default-avatar.png';

    document.querySelectorAll('img[data-profile-avatar]').forEach((image) => {
        const showFallback = () => {
            // Avoid retrying the fallback if it also fails.
            if (image.getAttribute('src') === fallback) {
                return;
            }

            image.src = fallback;
        };

        image.addEventListener('error', showFallback);

        // Handle failures that occurred before the listener was added.
        if (image.complete && image.naturalWidth === 0) {
            showFallback();
        }
    });
})();
