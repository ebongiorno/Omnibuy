<?php
$header = '../components/header.php';
include $header;
?>

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
<link rel="stylesheet" href="../css/messaging.css">

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
