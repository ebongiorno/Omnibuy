(() => {
    'use strict';

    // This same PHP file handles both the visible page and all wishlist API calls.
    const apiUrl = new URL(window.location.href);
    apiUrl.search = '';
    apiUrl.hash = '';
    apiUrl.searchParams.set('wishlist_api', '1');

    // Change only this value if your listing detail page uses another route.
    const listingPage = 'listing.php';
    const container = document.getElementById('wishlist-items');
    const message = document.getElementById('wishlist-message');
    const count = document.getElementById('wishlist-count');
    const currency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
    let csrfToken = '';
    let savedItems = [];

    function showMessage(text, isError = false) {
        message.textContent = text;
        message.setAttribute('role', isError ? 'alert' : 'status');
    }

    async function requestWishlist(method = 'GET', listingId = null) {
        const options = {
            method,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        };
        if (method !== 'GET') {
            options.headers['Content-Type'] = 'application/json';
            options.headers['X-CSRF-Token'] = csrfToken;
            options.body = JSON.stringify({ listing_id: listingId });
        }
        const response = await fetch(apiUrl, options);
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error('Invalid server response. Check the database connection.');
        }
        if (response.status === 401) {
            throw new Error('Your session expired. Log in again to view your wishlist.');
        }
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Wishlist request failed.');
        }
        return data;
    }

    function imageUrlFromDatabase(value) {
        if (typeof value !== 'string' || !value.trim()) return null;
        try {
            const url = new URL(value, document.baseURI);
            // Images may be local or remotely hosted over HTTPS; never allow script URLs.
            if (url.origin === location.origin || url.protocol === 'https:') return url.href;
        } catch { /* No valid image path. */ }
        return null;
    }

    function readableStatus(value) {
        return typeof value === 'string' ? value.replaceAll('_', ' ') : '';
    }

    function renderWishlist() {
        container.replaceChildren();
        container.setAttribute('aria-busy', 'false');
        count.textContent = `(${savedItems.length} saved)`;
        document.querySelectorAll('[data-wishlist-count]').forEach(el => {
            el.textContent = String(savedItems.length);
        });

        if (!savedItems.length) {
            const empty = document.createElement('p');
            empty.className = 'wishlist-empty';
            empty.textContent = 'Your wishlist is empty. Save items using the heart icon on a listing.';
            container.append(empty);
            return;
        }

        const fragment = document.createDocumentFragment();
        savedItems.forEach(item => {
            const id = String(item.listing_id);
            const card = document.createElement('article');
            card.className = 'wishlist-card';

            const link = document.createElement('a');
            const listingUrl = new URL(listingPage, document.baseURI);
            listingUrl.searchParams.set('listing_id', id);
            link.href = listingUrl.href;
            link.className = 'wishlist-card__link';

            const imageUrl = imageUrlFromDatabase(item.image_url);
            if (imageUrl) {
                const image = document.createElement('img');
                image.src = imageUrl;
                image.alt = '';
                image.loading = 'lazy';
                image.className = 'wishlist-card__image';
                link.append(image);
            } else {
                const placeholder = document.createElement('span');
                placeholder.className = 'wishlist-card__no-image';
                placeholder.textContent = 'No image available';
                link.append(placeholder);
            }

            const title = document.createElement('h3');
            title.className = 'wishlist-card__title';
            title.textContent = item.title || 'Untitled listing';
            link.append(title);

            const price = document.createElement('p');
            price.className = 'wishlist-card__price';
            const priceNumber = Number(item.price);
            price.textContent = Number.isFinite(priceNumber) && item.price !== null
                ? currency.format(priceNumber) : 'Price unavailable';
            link.append(price);

            const details = [item.category, readableStatus(item.item_condition)].filter(Boolean);
            if (details.length) {
                const meta = document.createElement('p');
                meta.className = 'wishlist-card__meta';
                meta.textContent = details.join(' · ');
                link.append(meta);
            }
            card.append(link);

            if (String(item.status).toLowerCase() !== 'active') {
                const status = document.createElement('p');
                status.className = 'wishlist-card__status';
                status.textContent = `Unavailable (${readableStatus(item.status) || 'inactive'})`;
                card.append(status);
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'wishlist-card__remove';
            remove.dataset.listingId = id;
            remove.textContent = 'Remove from wishlist';
            card.append(remove);
            fragment.append(card);
        });
        container.append(fragment);
    }

    async function loadWishlist() {
        try {
            const result = await requestWishlist();
            csrfToken = result.csrf_token;
            savedItems = result.items || [];
            renderWishlist();
            showMessage('');
        } catch (error) {
            container.setAttribute('aria-busy', 'false');
            container.textContent = 'Unable to load your wishlist.';
            showMessage(error.message, true);
        }
    }

    container.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target.closest('button[data-listing-id]') : null;
        if (!target || target.disabled) return;
        const listingId = target.dataset.listingId;
        target.disabled = true;
        try {
            await requestWishlist('DELETE', listingId);
            savedItems = savedItems.filter(item => String(item.listing_id) !== listingId);
            renderWishlist();
            showMessage('Removed from your wishlist.');
        } catch (error) {
            target.disabled = false;
            showMessage(error.message, true);
        }
    });

    loadWishlist();
})();
