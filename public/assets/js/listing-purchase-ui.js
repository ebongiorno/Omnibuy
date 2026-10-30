/* UC-25 Phase 3 - quantity selection UI only; no API requests or cart/wishlist changes. */
(() => {
    'use strict';

    document.querySelectorAll('[data-quantity-selector]').forEach((selector) => {
        const input = selector.querySelector('[data-quantity-value]');
        const decrease = selector.querySelector('[data-quantity-decrease]');
        const increase = selector.querySelector('[data-quantity-increase]');
        const actions = selector.closest('.listing-detail__summary')?.querySelector('[data-listing-actions]');
        const cartButton = actions?.querySelector('[data-add-to-cart]');
        const maximum = Number(selector.dataset.maxQuantity);

        if (!input || !decrease || !increase || !Number.isSafeInteger(maximum) || maximum < 1) return;

        const clamp = (value) => {
            const number = Number(value);
            if (!Number.isSafeInteger(number)) return 1;
            return Math.min(maximum, Math.max(1, number));
        };

        const update = (value) => {
            const quantity = clamp(value);
            input.value = String(quantity);
            decrease.disabled = quantity <= 1;
            increase.disabled = quantity >= maximum;
            // Exposes the selected quantity for a teammate's future cart integration.
            // This client-side value is not authoritative; cart backend must revalidate.
            if (cartButton) cartButton.dataset.selectedQuantity = String(quantity);
        };

        decrease.addEventListener('click', () => update(clamp(input.value) - 1));
        increase.addEventListener('click', () => update(clamp(input.value) + 1));
        input.addEventListener('change', () => update(input.value));
        input.addEventListener('blur', () => update(input.value));
        update(input.value);
    });
})();