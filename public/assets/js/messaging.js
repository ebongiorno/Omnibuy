/* OmniBuy inbox and conversation UI. Uses the JSON API inside messaging.php. */
(() => {
    'use strict';

    const main = document.querySelector('[data-messaging-page]');
    if (!main) return;

    const apiPath = '/pages/messaging.php?messaging_api=1';
    const errorBox = document.getElementById('messaging-error');

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message || 'Something went wrong. Please try again.';
        errorBox.hidden = false;
    }

    function clearError() {
        if (errorBox) {
            errorBox.textContent = '';
            errorBox.hidden = true;
        }
    }

    function positiveId(value) {
        const text = String(value ?? '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }

    function makeNode(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = String(text ?? '');
        return element;
    }

    // Only show local marketplace images; reject javascript:/data:/external URLs.
    function imagePath(raw) {
        if (typeof raw !== 'string' || !raw.trim()) return null;
        try {
            const text = raw.trim();
            const url = new URL(text.startsWith('/') || /^https?:\/\//i.test(text)
                ? text : '/' + text, window.location.origin);
            return url.origin === window.location.origin && /^https?:$/.test(url.protocol)
                ? url.href : null;
        } catch (_) {
            return null;
        }
    }

    function addImage(container, className, raw) {
        const path = imagePath(raw);
        if (!path) {
            const fallback = makeNode('span', className, '📦');
            fallback.setAttribute('aria-hidden', 'true');
            fallback.style.display = 'grid';
            fallback.style.placeItems = 'center';
            container.append(fallback);
            return;
        }
        const image = makeNode('img', className);
        image.alt = '';
        image.loading = 'lazy';
        image.src = path;
        image.addEventListener('error', () => {
            const fallback = makeNode('span', className, '📦');
            fallback.style.display = 'grid';
            fallback.style.placeItems = 'center';
            fallback.setAttribute('aria-hidden', 'true');
            image.replaceWith(fallback);
        }, { once: true });
        container.append(image);
    }

    function formatTime(value) {
        if (typeof value !== 'string' || !value) return '';
        // MySQL DATETIME has no timezone offset: interpret in browser local time.
        const date = new Date(value.replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString([], {
            month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
    }

    async function request(params = {}, options = {}) {
        const url = new URL(apiPath, window.location.origin);
        for (const [name, value] of Object.entries(params)) {
            if (value !== undefined && value !== null) url.searchParams.set(name, String(value));
        }
        const response = await fetch(url.href, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', ...(options.headers || {}) },
            ...options
        });
        let data;
        try {
            data = await response.json();
        } catch (_) {
            throw new Error('The server did not return a valid messaging response.');
        }
        if (!response.ok || !data.success) {
            if (response.status === 401) {
                throw new Error('Your session has expired. Sign in again to use messages.');
            }
            throw new Error(data.error || 'Unable to complete request.');
        }
        return data;
    }

    function changeRequest(method, token, body) {
        return request({}, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
            },
            body: JSON.stringify(body)
        });
    }

    function renderInbox(conversations) {
        const list = document.getElementById('conversation-list');
        const summary = document.getElementById('inbox-unread-summary');
        if (!list) return;
        list.replaceChildren();
        list.setAttribute('aria-busy', 'false');

        const unreadTotal = conversations.reduce((sum, row) => sum + Number(row.unread_count || 0), 0);
        if (summary) summary.textContent = unreadTotal
            ? `${unreadTotal} unread ${unreadTotal === 1 ? 'message' : 'messages'}` : '';

        if (!conversations.length) {
            const empty = makeNode('div', 'messages-empty-state');
            empty.append(makeNode('h2', null, 'No messages yet'),
                makeNode('p', null, 'Open a listing and choose Message Seller to start a conversation.'));
            list.append(empty);
            return;
        }

        for (const conversation of conversations) {
            const id = positiveId(conversation.conversation_id);
            if (!id) continue;
            const unread = Number(conversation.unread_count || 0);
            const link = makeNode('a', 'conversation-row' + (unread ? ' conversation-row--unread' : ''));
            link.href = `/pages/conversation.php?conversation_id=${encodeURIComponent(id)}`;
            addImage(link, 'conversation-row__image', conversation.image_url);

            const content = makeNode('div', 'conversation-row__content');
            const heading = makeNode('div', 'conversation-row__title-line');
            heading.append(makeNode('strong', null, conversation.other_username || 'User'),
                makeNode('span', null, conversation.listing_title || 'Listing'));
            content.append(heading,
                makeNode('p', 'conversation-row__preview', conversation.preview || 'No messages yet'));

            const metadata = makeNode('div', 'conversation-row__meta');
            metadata.append(makeNode('time', null, formatTime(conversation.last_message_at)));
            if (unread) {
                const badge = makeNode('span', 'message-count', unread > 99 ? '99+' : unread);
                badge.setAttribute('aria-label', `${unread} unread messages`);
                metadata.append(badge);
            }
            link.append(content, metadata);
            list.append(link);
        }
    }

    async function startInbox() {
        async function refresh() {
            try {
                const result = await request();
                renderInbox(Array.isArray(result.conversations) ? result.conversations : []);
                clearError();
            } catch (error) {
                const list = document.getElementById('conversation-list');
                if (list) list.setAttribute('aria-busy', 'false');
                showError(error.message);
            }
        }
        await refresh();
        window.setInterval(() => {
            if (!document.hidden) refresh();
        }, 20000);
    }

    async function startThread() {
        const state = {
            conversationId: positiveId(main.dataset.conversationId),
            listingId: positiveId(main.dataset.listingId),
            currentUser: null,
            csrf: '',
            messages: new Map(),
            hasOlder: false,
            loadedOlder: false,
            loading: false,
            sending: false
        };
        const thread = document.getElementById('message-thread');
        const form = document.getElementById('message-form');
        const input = document.getElementById('message-input');
        const sendButton = document.getElementById('message-send');
        const composer = document.getElementById('message-composer');
        const blockedNotice = document.getElementById('thread-blocked');
        const title = document.getElementById('thread-title');
        const listingName = document.getElementById('thread-listing-title');
        const listingLink = document.getElementById('thread-listing-link');
        const image = document.getElementById('thread-image');

        if (!state.conversationId && !state.listingId) {
            if (thread) thread.replaceChildren(makeNode('p', null, 'No conversation was selected.'));
            showError('Open a conversation from your inbox or choose Message Seller on a listing.');
            return;
        }

        function updateHeader(conversation) {
            if (title) title.textContent = conversation.other_username || 'Conversation';
            if (listingName) listingName.textContent = conversation.listing_title || '';
            const listingId = positiveId(conversation.listing_id);
            if (listingLink && listingId) {
                listingLink.href = `/pages/listing.php?id=${encodeURIComponent(listingId)}`;
                listingLink.hidden = false;
                listingLink.textContent = 'View listing';
                if (listingName) listingName.textContent = ` · ${conversation.listing_title || 'Listing'}`;
            }
            if (image) {
                const src = imagePath(conversation.image_url);
                image.hidden = !src;
                if (src) image.src = src;
                image.onerror = () => { image.hidden = true; };
            }
            const canSend = Boolean(conversation.can_send);
            if (composer) composer.hidden = !canSend;
            if (blockedNotice) {
                blockedNotice.hidden = canSend;
                blockedNotice.textContent = conversation.blocked
                    ? 'This conversation is blocked. New messages cannot be sent.'
                    : 'Messaging is unavailable for this account.';
            }
        }

        function drawMessages(scrollToEnd = false) {
            if (!thread) return;
            const previousHeight = thread.scrollHeight;
            const previousScroll = thread.scrollTop;
            const ordered = [...state.messages.values()]
                .sort((a, b) => Number(a.message_id) - Number(b.message_id));
            thread.replaceChildren();

            if (state.hasOlder && ordered.length) {
                const older = makeNode('button', 'conversation-action', 'Load older messages');
                older.type = 'button';
                older.style.alignSelf = 'center';
                older.addEventListener('click', () => refreshThread(true));
                thread.append(older);
            }
            if (!ordered.length) {
                thread.append(makeNode('p', null, 'No messages yet. Send the first message below.'));
            }
            for (const message of ordered) {
                const own = Number(message.sender_user_id) === Number(state.currentUser);
                const bubble = makeNode('article', `message-bubble message-bubble--${own ? 'sent' : 'received'}`);
                bubble.append(makeNode('p', null, message.body));
                const time = makeNode('time', null, formatTime(message.sent_at));
                if (message.sent_at) time.dateTime = message.sent_at.replace(' ', 'T');
                bubble.append(time);
                thread.append(bubble);
            }
            thread.setAttribute('aria-busy', 'false');
            if (scrollToEnd) {
                thread.scrollTop = thread.scrollHeight;
            } else if (state.loadedOlder) {
                // Preserve the user's scroll position when older rows are prepended.
                thread.scrollTop = previousScroll + (thread.scrollHeight - previousHeight);
            } else {
                thread.scrollTop = previousScroll;
            }
        }

        async function refreshThread(older = false, afterSend = false) {
            if (state.loading || (state.sending && !afterSend) || !thread) return;
            state.loading = true;
            try {
                const params = state.conversationId
                    ? { conversation_id: state.conversationId }
                    : { listing_id: state.listingId };
                if (older) {
                    const firstId = Math.min(...state.messages.keys());
                    if (!Number.isFinite(firstId)) return;
                    params.before_id = firstId;
                }
                const result = await request(params);
                state.csrf = result.csrf_token;
                state.currentUser = result.current_user_id;
                if (result.conversation?.conversation_id) {
                    state.conversationId = positiveId(result.conversation.conversation_id);
                }
                updateHeader(result.conversation);
                const wasNearBottom = (thread.scrollHeight - thread.scrollTop - thread.clientHeight) < 120;
                for (const message of result.messages || []) {
                    state.messages.set(Number(message.message_id), message);
                }
                if (older) {
                    state.loadedOlder = true;
                    state.hasOlder = Boolean(result.has_older);
                } else if (!state.loadedOlder) {
                    state.hasOlder = Boolean(result.has_older);
                }
                drawMessages(!older && (wasNearBottom || state.messages.size <= 50));
                clearError();
                if (!older && state.conversationId) {
                    // Read-state belongs to the recipient and requires a CSRF-protected PATCH.
                    await changeRequest('PATCH', state.csrf, { conversation_id: Number(state.conversationId) });
                }
            } catch (error) {
                thread.setAttribute('aria-busy', 'false');
                showError(error.message);
            } finally {
                state.loading = false;
            }
        }

        if (form) {
            form.addEventListener('submit', async event => {
                event.preventDefault();
                if (state.sending || !state.csrf) return;
                const body = input?.value.trim() || '';
                if (!body) {
                    showError('Enter a message before sending.');
                    return;
                }
                state.sending = true;
                if (sendButton) sendButton.disabled = true;
                clearError();
                try {
                    const payload = state.conversationId
                        ? { conversation_id: Number(state.conversationId), body }
                        : { listing_id: Number(state.listingId), body };
                    const result = await changeRequest('POST', state.csrf, payload);
                    state.conversationId = positiveId(result.conversation_id);
                    if (state.conversationId) {
                        window.history.replaceState(null, '',
                            `/pages/conversation.php?conversation_id=${encodeURIComponent(state.conversationId)}`);
                    }
                    if (input) input.value = '';
                    await refreshThread(false, true);
                    if (thread) thread.scrollTop = thread.scrollHeight;
                    input?.focus();
                } catch (error) {
                    showError(error.message);
                } finally {
                    state.sending = false;
                    if (sendButton) sendButton.disabled = false;
                }
            });
        }

        await refreshThread();
        window.setInterval(() => {
            if (!document.hidden && !state.loading && !state.sending) refreshThread();
        }, 15000);
    }

    if (main.dataset.messagingPage === 'inbox') startInbox();
    if (main.dataset.messagingPage === 'thread') startThread();
})();
