<?php
/**
 * Individual message thread UI.
 *
 * Open an existing thread: /pages/conversation.php?conversation_id=42
 * Message a seller:       /pages/conversation.php?listing_id=42
 *
 * All database reads/writes are handled by messaging.php?messaging_api=1.
 * The backend checks session identity and conversation membership.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
startSession();
require_once __DIR__ . '/../components/require-login.php';

$pageTitle = 'Conversation | OmniBuy';
$activeNav = 'messages';

// URLs are only used as routing hints; the API validates them again.
$conversationId = $_GET['conversation_id'] ?? $_GET['id'] ?? null;
$listingId = $_GET['listing_id'] ?? null;
$validId = static function (mixed $value): ?string {
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $value = (string) $value;
    return preg_match('/^[1-9][0-9]*$/D', $value) ? $value : null;
};
$conversationId = $validId($conversationId);
$listingId = $validId($listingId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conversation | OmniBuy</title>
    <link rel="stylesheet" href="/assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="/assets/css/shared-navigation.css">
    <link rel="stylesheet" href="/assets/css/messaging.css">
    <script src="https://kit.fontawesome.com/9e304416f8.js" crossorigin="anonymous"></script>
    <script src="/assets/js/messaging.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php include __DIR__ . '/../components/header.php'; ?>
    <main class="page-main conversation-page" id="main-content"
          data-messaging-page="thread"
          data-conversation-id="<?= htmlspecialchars($conversationId ?? '', ENT_QUOTES, 'UTF-8') ?>"
          data-listing-id="<?= htmlspecialchars($listingId ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="shell">
            <div class="message-card conversation-panel">
                <header class="conversation-panel__header">
                    <a class="conversation-back-link" href="/pages/messaging.php">Back to messages</a>
                    <div class="conversation-summary">
                        <img class="conversation-summary__image" id="thread-image" alt="" hidden>
                        <div class="conversation-summary__text">
                            <h1 id="thread-title">Conversation</h1>
                            <p><a id="thread-listing-link" href="#" hidden>View listing</a>
                               <span id="thread-listing-title"></span></p>
                        </div>
                    </div>
                    <div class="conversation-actions"></div>
                </header>
                <div class="message-thread" id="message-thread" role="log"
                     aria-label="Conversation messages" aria-live="polite" aria-busy="true">
                    <p id="thread-loading">Loading conversation...</p>
                </div>
                <div>
                    <p id="messaging-error" role="alert" hidden style="padding: 0 16px;"></p>
                    <div class="blocked-notice" id="thread-blocked" hidden></div>
                    <div class="message-composer" id="message-composer" hidden>
                        <form class="message-composer__form" id="message-form">
                            <label for="message-input" class="sr-only">Message</label>
                            <textarea id="message-input" name="body" maxlength="2000"
                                      placeholder="Type a message..." required></textarea>
                            <button class="button button--primary" id="message-send" type="submit">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
