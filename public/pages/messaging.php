<?php

declare(strict_types=1);

session_start();

/*
 * messaging.php
 * OmniBuy conversation hub / inbox skeleton.
 *
 * Production wiring TODO:
 * - Require an authenticated user before loading the inbox.
 * - Query only conversations where the session user is buyer or seller.
 * - Join the other participant, listing context, latest message, and unread count.
 * - Sort by conversations.updated_at DESC.
 * - Use prepared SQL and derive identity from the session only.
 */

// Production auth guard:
// if (!isset($_SESSION['user_id'])) {
//     header('Location: /login.php');
//     exit;
// }

$currentUserId = (int) ($_SESSION['user_id'] ?? 1); // Temporary layout fixture.

// Variables consumed by the existing reusable header.php.
$pageTitle = 'Messages | OmniBuy';
$activeNav = '';
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = $isLoggedIn ? [
    'username' => (string) ($_SESSION['username'] ?? 'Account'),
    'avatar_url' => (string) ($_SESSION['avatar_url'] ?? '/assets/img/default-avatar.png'),
    'is_seller' => (bool) ($_SESSION['is_seller'] ?? false),
] : null;
$cartCount = (int) ($_SESSION['cart_count'] ?? 0);
$unreadCount = 2; // TODO: Replace with unread message query.


// TODO: Replace with a prepared database query.
$conversations = [
    [
        'conversation_id' => 101,
        'other_user_name' => 'Alex Morgan',
        'listing_title' => 'Vintage Typewriter',
        'listing_image' => '/assets/img/placeholder-listing.jpg',
        'last_message' => 'Yes, it is still available.',
        'last_message_at' => 'Today, 4:18 PM',
        'unread_count' => 2,
    ],
    [
        'conversation_id' => 102,
        'other_user_name' => 'Jordan Lee',
        'listing_title' => 'Mechanical Keyboard',
        'listing_image' => '/assets/img/placeholder-listing.jpg',
        'last_message' => 'Would Saturday afternoon work?',
        'last_message_at' => 'Yesterday',
        'unread_count' => 0,
    ],
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require_once __DIR__ . '/../components/header.php';
?>
<link rel="stylesheet" href="../assets/css/messaging.css">

<section class="messages-page shell" aria-labelledby="messages-title">
  <div class="messages-page__heading">
    <div>
      <h1 id="messages-title">Messages</h1>
      <p>View conversations with buyers and sellers.</p>
    </div>
  </div>

  <section class="message-card conversation-list" aria-label="Conversation inbox">
    <?php if (empty($conversations)): ?>
      <div class="messages-empty-state">
        <h2>No messages yet</h2>
        <p>When you message a seller or receive a buyer message, it will appear here.</p>
      </div>
    <?php else: ?>
      <?php foreach ($conversations as $conversation): ?>
        <?php
        $isUnread = (int) $conversation['unread_count'] > 0;
        $conversationUrl = 'conversation.php?id=' . urlencode((string) $conversation['conversation_id']);
        ?>
        <a
          class="conversation-row<?= $isUnread ? ' conversation-row--unread' : '' ?>"
          href="<?= e($conversationUrl) ?>"
          aria-label="Open conversation with <?= e($conversation['other_user_name']) ?>"
        >
          <img
            class="conversation-row__image"
            src="<?= e($conversation['listing_image']) ?>"
            alt=""
          >

          <div class="conversation-row__content">
            <div class="conversation-row__title-line">
              <strong><?= e($conversation['other_user_name']) ?></strong>
              <span><?= e($conversation['listing_title']) ?></span>
            </div>
            <p class="conversation-row__preview"><?= e($conversation['last_message']) ?></p>
          </div>

          <div class="conversation-row__meta">
            <time><?= e($conversation['last_message_at']) ?></time>
            <?php if ($isUnread): ?>
              <span
                class="message-count"
                aria-label="<?= (int) $conversation['unread_count'] ?> unread messages"
              >
                <?= (int) $conversation['unread_count'] ?>
              </span>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</section>

<?php
$footer = '../components/footer.php';
include $footer;
?>
