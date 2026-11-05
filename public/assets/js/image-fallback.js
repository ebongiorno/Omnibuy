// Handles an image that fails because of a nonexistent file, HTTP error, 
// unsupported format, or another browser loading failure. Also avoids
// infinite loop if the default image itself cannot load
(() => {
    'use strict';

    const fallback = '/assets/images/listing/listing-default-img.jpg';

    document.querySelectorAll('img[data-image-fallback]').forEach((image) => {
        const useFallback = () => {
            if (image.getAttribute('src') === fallback) {
                return;
            }

            image.src = fallback;
        };

        image.addEventListener('error', useFallback);

        // Also handle images that failed before this script ran.
        if (image.complete && image.naturalWidth === 0) {
            useFallback();
        }
    });
})();
