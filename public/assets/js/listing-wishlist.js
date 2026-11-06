(() => {
    'use strict';

    const actions = document.querySelector('[data-listing-actions][data-listing-id]');
    if (!actions) return;

    const button = actions.querySelector('[data-add-to-wishlist]');
    const icon = button?.querySelector('[data-wishlist-icon]');
    const label = button?.querySelector('[data-wishlist-label]');
    const feedback = document.querySelector('[data-wishlist-feedback]');
    const listingId = actions.dataset.listingId;

    if (!button || !icon || !label || !feedback ||
        !/^[1-9][0-9]*$/.test(listingId || '')) return;

    // wishlist.php serves both its visible page and the existing JSON API.
    const apiUrl = new URL('/pages/wishlist.php', window.location.origin);
    apiUrl.searchParams.set('wishlist_api', '1');

    let csrfToken = '';
    let saved = false;
    let authenticated = false;
    let initialized = false;
    let busy = false;

    function showMessage(text, isError = false) {
        feedback.textContent = text;
        feedback.setAttribute('role', isError ? 'alert' : 'status');
    }

    function updateButton() {
        button.disabled = busy;
        button.setAttribute('aria-pressed', String(authenticated && saved));
        icon.textContent = authenticated && saved ? '\u2665' : '\u2661';

        if (!initialized) {
            label.textContent = busy ? 'Checking Wishlist...' : 'Retry Wishlist';
        } else if (!authenticated) {
            label.textContent = 'Log In to Save';
        } else {
            label.textContent = saved ? 'Remove from Wishlist' : 'Add to Wishlist';
        }

    }

    async function requestWishlist(method = 'GET') {
        const url = new URL(apiUrl);
        const options = {
            method,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        };

        if (method === 'GET') {
            url.searchParams.set('listing_id', listingId);
        } else {
            options.headers['Content-Type'] = 'application/json';
            options.headers['X-CSRF-Token'] = csrfToken;
            options.body = JSON.stringify({ listing_id: listingId });
        }

        const response = await fetch(url.toString(), options);
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error('Unexpected server response. Please try again.');
        }

        if (response.status === 401) {
            const error = new Error('Log in to save listings to your wishlist.');
            error.loginRequired = true;
            throw error;
        }
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Wishlist request failed.');
        }
        return data;
    }

    async function loadSavedStatus() {
        busy = true;
        updateButton();
        try {
            const data = await requestWishlist();
            csrfToken = data.csrf_token;
            saved = Boolean(data.saved);
            authenticated = true;
            initialized = true;
            showMessage('');
        } catch (error) {
            if (error.loginRequired) {
                authenticated = false;
                initialized = true;
            } else {
                initialized = false;
            }
            showMessage(error.message, true);
        } finally {
            busy = false;
            updateButton();
        }
    }

    button.addEventListener('click', async () => {
        if (busy) return;
        if (!initialized) {
            await loadSavedStatus();
            return;
        }
        if (!authenticated) {
            window.location.assign('/pages/login.php');
            return;
        }

        busy = true;
        updateButton();
        showMessage('');
        try {
            const data = await requestWishlist(saved ? 'DELETE' : 'POST');
            saved = Boolean(data.saved);
            showMessage(saved ? 'Added to your wishlist.' : 'Removed from your wishlist.');
        } catch (error) {
            if (error.loginRequired) {
                authenticated = false;
                initialized = true;
            }
            showMessage(error.message, true);
        } finally {
            busy = false;
            updateButton();
        }
    });

    loadSavedStatus();
})();
