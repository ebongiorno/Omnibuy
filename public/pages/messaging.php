<?php
/**
 * OmniBuy messaging API and inbox page.
 *
 * GET   /pages/messaging.php?messaging_api=1
 *       Inbox: latest message, unread count, listing and other participant.
 * GET   /pages/messaging.php?messaging_api=1&conversation_id=123
 *       Read an authorized thread (latest 50, optional before_id cursor).
 * GET   /pages/messaging.php?messaging_api=1&listing_id=123
 *       Resolve an existing buyer thread, or prepare a new one.
 * POST  /pages/messaging.php?messaging_api=1
 *       JSON {listing_id: 123, body: "Hi"} creates thread + first message.
 *       JSON {conversation_id: 123, body: "Hi"} replies to a thread.
 * PATCH /pages/messaging.php?messaging_api=1
 *       JSON {conversation_id: 123} marks messages from the other user read.
 *
 * POST and PATCH require X-CSRF-Token returned by a GET request.
 * Sender identity ALWAYS comes from the login session.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
startSession();
require_once __DIR__ . '/../../db_connect.php';

final class MessagingHttpError extends RuntimeException
{
    public function __construct(public int $httpStatus, string $message)
    {
        parent::__construct($message);
    }
}

function messagingId(mixed $value): ?int
{
    if (!is_int($value) && !is_string($value)) {
        return null;
    }
    $text = (string) $value;
    if (!preg_match('/^[1-9][0-9]*$/D', $text)) {
        return null;
    }
    $id = filter_var($text, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

function messagingDb(): PDO
{
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('The PDO connection was not initialized.');
    }
    return $pdo;
}

function messagingRespond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function messagingFail(int $status, string $message): never
{
    throw new MessagingHttpError($status, $message);
}

/**
 * OmniBuy schema mapping. Column names match omnibuy_database.sql exactly.
 * Do not accept alternate names or silently skip the user_blocks check.
 */
function messagingSchema(): array
{
    return [
        'buyer' => 'buyer_id',
        'seller' => 'seller_id',
        'c_created' => 'created_at',
        'c_updated' => 'updated_at',
        'sender' => 'sender_user_id',
        'body' => 'message_text',
        'sent' => 'sent_at',
        'read_at' => 'seen_at',
        'is_read' => null,
        'block_table' => 'user_blocks',
        'blocker' => 'blocker_user_id',
        'blocked' => 'blocked_user_id',
    ];
}

/** SQL identifiers originate only from the fixed schema mapping above. */
function messagingColumn(string $column, string $alias = ''): string
{
    return ($alias !== '' ? $alias . '.' : '') . '`' . $column . '`';
}

function messagingUnreadSql(array $s, string $alias = 'm'): string
{
    return $s['read_at'] !== null
        ? messagingColumn($s['read_at'], $alias) . ' IS NULL'
        : messagingColumn($s['is_read'], $alias) . ' = 0';
}

function messagingRequestBody(): stdClass
{
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') {
        messagingFail(415, 'Use Content-Type: application/json.');
    }
    $raw = file_get_contents('php://input', false, null, 0, 12001);
    if ($raw === false || strlen($raw) > 12000) {
        messagingFail(413, 'Request body is too large.');
    }
    $data = json_decode($raw);
    if (!$data instanceof stdClass) {
        messagingFail(400, 'Expected a JSON object.');
    }
    return $data;
}

function messagingValidateText(mixed $value): string
{
    if (!is_string($value)) {
        messagingFail(422, 'A message is required.');
    }
    $body = trim($value);
    if ($body === '' || strlen($body) > 8000 || !preg_match('/\A.{1,2000}\z/us', $body)) {
        messagingFail(422, 'Messages must contain 1 to 2000 characters.');
    }
    return $body;
}

function messagingIsBlocked(PDO $db, array $s, int $one, int $two): bool
{
    $table = messagingColumn($s['block_table']);
    $blocker = messagingColumn($s['blocker']);
    $blocked = messagingColumn($s['blocked']);
    $stmt = $db->prepare("SELECT 1 FROM $table
        WHERE ($blocker = :one_a AND $blocked = :two_a)
           OR ($blocker = :two_b AND $blocked = :one_b)
        LIMIT 1");
    $stmt->execute(['one_a' => $one, 'two_a' => $two, 'two_b' => $two, 'one_b' => $one]);
    return (bool) $stmt->fetchColumn();
}

function messagingUserCanReceive(PDO $db, int $otherId): bool
{
    $stmt = $db->prepare("SELECT 1 FROM users
        WHERE user_id = :id AND account_status = 'active' LIMIT 1");
    $stmt->execute(['id' => $otherId]);
    return (bool) $stmt->fetchColumn();
}

function messagingConversation(PDO $db, array $s, int $conversationId, int $userId): ?array
{
    $buyer = messagingColumn($s['buyer'], 'c');
    $seller = messagingColumn($s['seller'], 'c');
    $stmt = $db->prepare("SELECT c.conversation_id, c.listing_id,
           $buyer AS buyer_user_id, $seller AS seller_user_id,
           l.item_name AS listing_title, l.listing_status,
           u.user_id AS other_user_id, u.username AS other_username,
           (SELECT li.image_url FROM listing_images li
               WHERE li.listing_id = c.listing_id
               ORDER BY li.display_order, li.listing_image_id LIMIT 1) AS image_url
        FROM conversations c
        JOIN listings l ON l.listing_id = c.listing_id
        JOIN users u ON u.user_id = CASE
            WHEN $buyer = :choose_buyer THEN $seller ELSE $buyer END
        WHERE c.conversation_id = :conversation_id
          AND ($buyer = :current_buyer OR $seller = :current_seller)
        LIMIT 1");
    $stmt->execute([
        'choose_buyer' => $userId,
        'conversation_id' => $conversationId,
        'current_buyer' => $userId,
        'current_seller' => $userId,
    ]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conversation) {
        return null;
    }
    $conversation['blocked'] = messagingIsBlocked($db, $s, $userId, (int) $conversation['other_user_id']);
    $conversation['can_send'] = !$conversation['blocked']
        && messagingUserCanReceive($db, $userId)
        && messagingUserCanReceive($db, (int) $conversation['other_user_id']);
    return $conversation;
}

function messagingThread(PDO $db, array $s, int $conversationId, ?int $beforeId): array
{
    $sender = messagingColumn($s['sender'], 'm');
    $body = messagingColumn($s['body'], 'm');
    $sent = messagingColumn($s['sent'], 'm');
    $read = $s['read_at'] !== null
        ? messagingColumn($s['read_at'], 'm')
        : messagingColumn($s['is_read'], 'm');
    $where = $beforeId !== null ? ' AND m.message_id < :before_id' : '';
    $stmt = $db->prepare("SELECT m.message_id, m.conversation_id,
           $sender AS sender_user_id, $body AS body,
           $sent AS sent_at, $read AS read_state
        FROM messages m
        WHERE m.conversation_id = :conversation_id $where
        ORDER BY m.message_id DESC LIMIT 51");
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
    if ($beforeId !== null) {
        $stmt->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasOlder = count($rows) > 50;
    $rows = array_slice($rows, 0, 50);
    return ['messages' => array_reverse($rows), 'has_older' => $hasOlder];
}

function messagingInbox(PDO $db, array $s, int $userId): array
{
    $buyer = messagingColumn($s['buyer'], 'c');
    $seller = messagingColumn($s['seller'], 'c');
    $body = messagingColumn($s['body'], 'lm');
    $sent = messagingColumn($s['sent'], 'lm');
    $sender = messagingColumn($s['sender'], 'um');
    $unread = messagingUnreadSql($s, 'um');
    $fallback = $s['c_updated'] !== null ? messagingColumn($s['c_updated'], 'c')
        : ($s['c_created'] !== null ? messagingColumn($s['c_created'], 'c') : 'NULL');

    $stmt = $db->prepare("SELECT c.conversation_id, c.listing_id,
        l.item_name AS listing_title, u.user_id AS other_user_id,
        u.username AS other_username,
        (SELECT li.image_url FROM listing_images li
            WHERE li.listing_id = c.listing_id
            ORDER BY li.display_order, li.listing_image_id LIMIT 1) AS image_url,
        (SELECT $body FROM messages lm
            WHERE lm.conversation_id = c.conversation_id
            ORDER BY lm.message_id DESC LIMIT 1) AS preview,
        (SELECT $sent FROM messages lm
            WHERE lm.conversation_id = c.conversation_id
            ORDER BY lm.message_id DESC LIMIT 1) AS last_message_at,
        (SELECT COUNT(*) FROM messages um
            WHERE um.conversation_id = c.conversation_id
              AND $sender <> :unread_user AND $unread) AS unread_count
        FROM conversations c
        JOIN listings l ON l.listing_id = c.listing_id
        JOIN users u ON u.user_id = CASE
            WHEN $buyer = :choose_buyer THEN $seller ELSE $buyer END
        WHERE $buyer = :current_buyer OR $seller = :current_seller
        ORDER BY COALESCE(last_message_at, $fallback) DESC, c.conversation_id DESC
        LIMIT 100");
    $stmt->execute([
        'unread_user' => $userId,
        'choose_buyer' => $userId,
        'current_buyer' => $userId,
        'current_seller' => $userId,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function messagingListingDraft(PDO $db, array $s, int $listingId, int $userId): array
{
    // Check for an existing conversation even if its listing has since sold.
    $buyer = messagingColumn($s['buyer']);
    $stmt = $db->prepare("SELECT conversation_id FROM conversations
        WHERE listing_id = :listing_id AND $buyer = :buyer_id
        LIMIT 1");
    $stmt->execute(['listing_id' => $listingId, 'buyer_id' => $userId]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        $conversation = messagingConversation($db, $s, (int) $existing, $userId);
        if ($conversation !== null) {
            return ['conversation' => $conversation, 'new' => false];
        }
    }

    $stmt = $db->prepare("SELECT l.listing_id, l.item_name AS listing_title,
        l.seller_user_id AS other_user_id, u.username AS other_username,
        (SELECT li.image_url FROM listing_images li
            WHERE li.listing_id = l.listing_id
            ORDER BY li.display_order, li.listing_image_id LIMIT 1) AS image_url
        FROM listings l JOIN users u ON u.user_id = l.seller_user_id
        WHERE l.listing_id = :listing_id
          AND l.listing_status = 'active' AND u.account_status = 'active'
        LIMIT 1");
    $stmt->execute(['listing_id' => $listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$listing) {
        messagingFail(404, 'Listing unavailable.');
    }
    if ((int) $listing['other_user_id'] === $userId) {
        messagingFail(422, 'You cannot message your own listing.');
    }
    $listing['conversation_id'] = null;
    $listing['blocked'] = messagingIsBlocked($db, $s, $userId, (int) $listing['other_user_id']);
    $listing['can_send'] = !$listing['blocked'] && messagingUserCanReceive($db, $userId);
    return ['conversation' => $listing, 'new' => true];
}

function messagingInsertMessage(PDO $db, array $s, int $conversationId, int $userId, string $text): int
{
    $cols = ['conversation_id', $s['sender'], $s['body']];
    $vals = [':conversation_id', ':sender', ':body'];
    if ($s['sent'] !== null) {
        $cols[] = $s['sent'];
        $vals[] = 'NOW()';
    }
    if ($s['read_at'] !== null) {
        $cols[] = $s['read_at'];
        $vals[] = 'NULL';
    } elseif ($s['is_read'] !== null) {
        $cols[] = $s['is_read'];
        $vals[] = '0';
    }
    $sql = 'INSERT INTO messages (' . implode(', ', array_map('messagingColumn', $cols)) .
        ') VALUES (' . implode(', ', $vals) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute(['conversation_id' => $conversationId, 'sender' => $userId, 'body' => $text]);
    if ($s['c_updated'] !== null) {
        $updated = messagingColumn($s['c_updated']);
        $stmt = $db->prepare("UPDATE conversations SET $updated = NOW()
            WHERE conversation_id = :conversation_id");
        $stmt->execute(['conversation_id' => $conversationId]);
    }
    return (int) $db->lastInsertId();
}

function messagingReply(PDO $db, array $s, int $conversationId, int $userId, string $text): array
{
    $db->beginTransaction();
    try {
        // Lock the thread to serialize replies against changes to its state.
        $buyer = messagingColumn($s['buyer']);
        $seller = messagingColumn($s['seller']);
        $stmt = $db->prepare("SELECT $buyer AS buyer_user_id, $seller AS seller_user_id
            FROM conversations WHERE conversation_id = :cid FOR UPDATE");
        $stmt->execute(['cid' => $conversationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (!in_array($userId, [(int)$row['buyer_user_id'], (int)$row['seller_user_id']], true))) {
            messagingFail(404, 'Conversation not found.');
        }
        $otherId = (int) $row['buyer_user_id'] === $userId
            ? (int) $row['seller_user_id'] : (int) $row['buyer_user_id'];
        if (messagingIsBlocked($db, $s, $userId, $otherId)) {
            messagingFail(403, 'Messaging is unavailable for this conversation.');
        }
        if (!messagingUserCanReceive($db, $otherId)) {
            messagingFail(409, 'The other account is unavailable.');
        }
        $messageId = messagingInsertMessage($db, $s, $conversationId, $userId, $text);
        $db->commit();
        return ['conversation_id' => $conversationId, 'message_id' => $messageId];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function messagingCreateOrSend(PDO $db, array $s, int $listingId, int $userId, string $text): array
{
    $db->beginTransaction();
    try {
        // FOR UPDATE on the listing prevents concurrent requests from creating
        // multiple conversation rows for the same buyer + listing.
        $stmt = $db->prepare("SELECT l.seller_user_id, l.listing_status,
             u.account_status FROM listings l
             JOIN users u ON u.user_id = l.seller_user_id
             WHERE l.listing_id = :listing_id FOR UPDATE");
        $stmt->execute(['listing_id' => $listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$listing) {
            messagingFail(404, 'Listing unavailable.');
        }
        $sellerId = (int) $listing['seller_user_id'];
        if ($sellerId === $userId) {
            messagingFail(422, 'You cannot message your own listing.');
        }
        if (messagingIsBlocked($db, $s, $userId, $sellerId)) {
            messagingFail(403, 'Messaging is unavailable for this seller.');
        }
        if ($listing['account_status'] !== 'active') {
            messagingFail(409, 'This seller is unavailable.');
        }
        $buyer = messagingColumn($s['buyer']);
        $stmt = $db->prepare("SELECT conversation_id FROM conversations
            WHERE listing_id = :listing_id AND $buyer = :buyer_id LIMIT 1 FOR UPDATE");
        $stmt->execute(['listing_id' => $listingId, 'buyer_id' => $userId]);
        $conversationId = $stmt->fetchColumn();
        $created = false;
        if ($conversationId === false) {
            if ($listing['listing_status'] !== 'active') {
                messagingFail(409, 'This listing is no longer available for new conversations.');
            }
            $cols = ['listing_id', $s['buyer'], $s['seller']];
            $vals = [':listing_id', ':buyer_id', ':seller_id'];
            foreach (array_unique(array_filter([$s['c_created'], $s['c_updated']])) as $name) {
                $cols[] = $name;
                $vals[] = 'NOW()';
            }
            $sql = 'INSERT INTO conversations (' . implode(', ', array_map('messagingColumn', $cols)) .
                ') VALUES (' . implode(', ', $vals) . ')';
            $stmt = $db->prepare($sql);
            $stmt->execute(['listing_id' => $listingId, 'buyer_id' => $userId, 'seller_id' => $sellerId]);
            $conversationId = (int) $db->lastInsertId();
            $created = true;
        } else {
            $conversationId = (int) $conversationId;
        }
        $messageId = messagingInsertMessage($db, $s, $conversationId, $userId, $text);
        $db->commit();
        return ['conversation_id' => $conversationId, 'message_id' => $messageId, 'created' => $created];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function messagingMarkRead(PDO $db, array $s, int $conversationId, int $userId): int
{
    // Verify membership even for a PATCH that updates zero rows.
    if (messagingConversation($db, $s, $conversationId, $userId) === null) {
        messagingFail(404, 'Conversation not found.');
    }
    $sender = messagingColumn($s['sender']);
    $read = $s['read_at'] !== null ? messagingColumn($s['read_at']) . ' = NOW()'
        : messagingColumn($s['is_read']) . ' = 1';
    $unread = messagingUnreadSql($s, 'messages');
    $stmt = $db->prepare("UPDATE messages SET $read
        WHERE conversation_id = :conversation_id AND $sender <> :reader AND $unread");
    $stmt->execute(['conversation_id' => $conversationId, 'reader' => $userId]);
    return $stmt->rowCount();
}

$isMessagingApi = ($_GET['messaging_api'] ?? null) === '1';
if ($isMessagingApi) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Cookie');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST', 'PATCH'], true)) {
        header('Allow: GET, POST, PATCH');
        messagingRespond(405, ['success' => false, 'error' => 'Method not allowed.']);
    }
    $userId = messagingId($_SESSION['user_id'] ?? null);
    if ($userId === null) {
        messagingRespond(401, ['success' => false, 'error' => 'Login required.']);
    }
    if (empty($_SESSION['messaging_csrf_token'])) {
        $_SESSION['messaging_csrf_token'] = bin2hex(random_bytes(32));
    }
    if ($method !== 'GET') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!is_string($token) || !hash_equals($_SESSION['messaging_csrf_token'], $token)) {
            messagingRespond(403, ['success' => false, 'error' => 'Invalid security token. Refresh and try again.']);
        }
    }

    try {
        $db = messagingDb();
        $schema = messagingSchema();
        if ($method === 'GET') {
            $csrf = $_SESSION['messaging_csrf_token'];
            if (array_key_exists('conversation_id', $_GET)) {
                $conversationId = messagingId($_GET['conversation_id']);
                if ($conversationId === null) {
                    messagingFail(422, 'Invalid conversation_id.');
                }
                $before = null;
                if (array_key_exists('before_id', $_GET)) {
                    $before = messagingId($_GET['before_id']);
                    if ($before === null) {
                        messagingFail(422, 'Invalid before_id.');
                    }
                }
                $conversation = messagingConversation($db, $schema, $conversationId, $userId);
                if ($conversation === null) {
                    messagingFail(404, 'Conversation not found.');
                }
                messagingRespond(200, ['success' => true, 'conversation' => $conversation,
                    ...messagingThread($db, $schema, $conversationId, $before), 'current_user_id' => $userId, 'csrf_token' => $csrf]);
            }
            if (array_key_exists('listing_id', $_GET)) {
                $listingId = messagingId($_GET['listing_id']);
                if ($listingId === null) {
                    messagingFail(422, 'Invalid listing_id.');
                }
                $draft = messagingListingDraft($db, $schema, $listingId, $userId);
                $thread = $draft['new'] ? ['messages' => [], 'has_older' => false]
                    : messagingThread($db, $schema, (int) $draft['conversation']['conversation_id'], null);
                messagingRespond(200, ['success' => true, 'conversation' => $draft['conversation'],
                    'new' => $draft['new'], ...$thread, 'current_user_id' => $userId, 'csrf_token' => $csrf]);
            }
            $rows = messagingInbox($db, $schema, $userId);
            $totalUnread = array_sum(array_map(static fn($row): int => (int)$row['unread_count'], $rows));
            messagingRespond(200, ['success' => true, 'conversations' => $rows,
                'count' => count($rows), 'unread_count' => $totalUnread, 'csrf_token' => $csrf]);
        }

        // A login session alone is not permission to send from a suspended,
        // restricted, or deleted account.
        if (!messagingUserCanReceive($db, $userId)) {
            messagingFail(403, 'Messaging is unavailable for this account.');
        }

        $data = messagingRequestBody();
        if ($method === 'POST') {
            $body = messagingValidateText($data->body ?? null);
            $cid = messagingId($data->conversation_id ?? null);
            $lid = messagingId($data->listing_id ?? null);
            if ((property_exists($data, 'conversation_id') && $cid === null)
                || (property_exists($data, 'listing_id') && $lid === null)) {
                messagingFail(422, 'Invalid conversation_id or listing_id.');
            }
            if (($cid === null) === ($lid === null)) {
                messagingFail(422, 'Provide either conversation_id or listing_id.');
            }
            $result = $cid !== null
                ? messagingReply($db, $schema, $cid, $userId, $body)
                : messagingCreateOrSend($db, $schema, $lid, $userId, $body);
            messagingRespond(201, ['success' => true, ...$result]);
        }

        $cid = messagingId($data->conversation_id ?? null);
        if ($cid === null) {
            messagingFail(422, 'A valid conversation_id is required.');
        }
        messagingRespond(200, ['success' => true,
            'updated_count' => messagingMarkRead($db, $schema, $cid, $userId)]);
    } catch (MessagingHttpError $error) {
        messagingRespond($error->httpStatus, ['success' => false, 'error' => $error->getMessage()]);
    } catch (Throwable $error) {
        error_log('OmniBuy messaging API error: ' . $error->getMessage());
        messagingRespond(500, ['success' => false, 'error' => 'Unable to complete the messaging request right now.']);
    }
}

require_once __DIR__ . '/../components/require-login.php';
$pageTitle = 'Messages | OmniBuy';
$activeNav = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages | OmniBuy</title>
    <link rel="stylesheet" href="/assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="/assets/css/shared-navigation.css">
    <link rel="stylesheet" href="/assets/css/messaging.css">
    <script src="https://kit.fontawesome.com/9e304416f8.js" crossorigin="anonymous"></script>
    <script src="/assets/js/messaging.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php include __DIR__ . '/../components/header.php'; ?>
    <main class="page-main messages-page" id="main-content" data-messaging-page="inbox">
        <div class="shell">
            <div class="messages-page__heading">
                <div>
                    <h1>Messages</h1>
                    <p>Your conversations with OmniBuy buyers and sellers.</p>
                </div>
                <span id="inbox-unread-summary" aria-live="polite"></span>
            </div>
            <p id="messaging-error" role="alert" hidden></p>
            <section class="message-card conversation-list" id="conversation-list"
                     aria-label="Inbox conversations" aria-live="polite" aria-busy="true">
                <div class="messages-empty-state"><p>Loading your conversations...</p></div>
            </section>
        </div>
    </main>
    <?php include __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
