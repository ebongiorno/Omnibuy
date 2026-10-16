<?php
/**
 * INCLUDED API FUNCTIONS
 *
 * GET      wishlist.php?wishlist_api=1                 List saved listings
 * GET      wishlist.php?wishlist_api=1&listing_id=42   Check saved status
 * POST     wishlist.php?wishlist_api=1                 Add listing (JSON body)
 * DELETE   wishlist.php?wishlist_api=1                 Remove listing (JSON body)
 *
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
startSession();
require_once __DIR__ . '/../../db_connect.php';

function wishlistPositiveId(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $text = (string) $value;
    if (!preg_match('/^[1-9][0-9]*$/D', $text)) {
        return null;
    }

    $id = filter_var($text, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $id === false ? null : $id;
}

function wishlistDatabase(): PDO
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('db_connect.php did not provide a PDO connection in $pdo.');
    }

    return $pdo;
}

function wishlistJsonResponse(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function wishlistRequestListingId(): int
{
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') {
        wishlistJsonResponse(415, [
            'success' => false,
            'error' => 'Use Content-Type: application/json.',
        ]);
    }

    // Wishlist requests should only contain one small listing ID.
    $raw = file_get_contents('php://input', false, null, 0, 4097);
    if ($raw === false || strlen($raw) > 4096) {
        wishlistJsonResponse(413, [
            'success' => false,
            'error' => 'Request body is too large.',
        ]);
    }

    $data = json_decode($raw);
    if (!$data instanceof stdClass) {
        wishlistJsonResponse(400, [
            'success' => false,
            'error' => 'Expected a JSON object.',
        ]);
    }

    $listingId = wishlistPositiveId($data->listing_id ?? null);
    if ($listingId === null) {
        wishlistJsonResponse(422, [
            'success' => false,
            'error' => 'A valid listing_id is required.',
        ]);
    }

    return $listingId;
}

function wishlistIsSaved(PDO $db, int $userId, int $listingId): bool
{
    $statement = $db->prepare('
        SELECT 1
        FROM wishlist_items
        WHERE user_id = :user_id AND listing_id = :listing_id
        LIMIT 1
    ');
    $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);

    return (bool) $statement->fetchColumn();
}

function wishlistIsAvailable(PDO $db, int $listingId): bool
{
    $statement = $db->prepare("
        SELECT 1
        FROM listings
        WHERE listing_id = :listing_id AND listing_status = 'active'
        LIMIT 1
    ");
    $statement->execute(['listing_id' => $listingId]);

    return (bool) $statement->fetchColumn();
}

function wishlistGetItems(PDO $db, int $userId): array
{
    // Keep previously saved sold/hidden items visible so users can remove them.
    // A subquery gets only the first photo without duplicating listing rows.
    $statement = $db->prepare('
        SELECT
            w.wishlist_item_id,
            w.listing_id,
            w.added_at AS saved_at,
            l.item_name AS title,
            l.price,
            l.`condition` AS item_condition,
            l.listing_status AS status,
            c.category_name AS category,
            (
                SELECT li.image_url
                FROM listing_images AS li
                WHERE li.listing_id = l.listing_id
                ORDER BY li.display_order ASC, li.listing_image_id ASC
                LIMIT 1
            ) AS image_url
        FROM wishlist_items AS w
        INNER JOIN listings AS l ON l.listing_id = w.listing_id
        LEFT JOIN categories AS c ON c.category_id = l.category_id
        WHERE w.user_id = :user_id
        ORDER BY w.added_at DESC, w.wishlist_item_id DESC
    ');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function wishlistItemCount(PDO $db, int $userId): int
{
    $statement = $db->prepare('
        SELECT COUNT(*) FROM wishlist_items WHERE user_id = :user_id
    ');
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}


$isWishlistApi = ($_GET['wishlist_api'] ?? null) === '1';

if ($isWishlistApi) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Cookie');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
        header('Allow: GET, POST, DELETE');
        wishlistJsonResponse(405, [
            'success' => false,
            'error' => 'Method not allowed.',
        ]);
    }

    // Always use the logged-in session. Do not accept a browser-supplied user_id.
    $userId = wishlistPositiveId($_SESSION['user_id'] ?? null);
    if ($userId === null) {
        wishlistJsonResponse(401, [
            'success' => false,
            'error' => 'Login required.',
        ]);
    }

    if (empty($_SESSION['wishlist_csrf_token'])) {
        $_SESSION['wishlist_csrf_token'] = bin2hex(random_bytes(32));
    }

    if ($method !== 'GET') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['wishlist_csrf_token'], $token)) {
            wishlistJsonResponse(403, [
                'success' => false,
                'error' => 'Invalid security token. Refresh and try again.',
            ]);
        }
    }

    try {
        $db = wishlistDatabase();

        if ($method === 'GET') {
            // Heart buttons on other pages can check one listing by ID.
            if (array_key_exists('listing_id', $_GET)) {
                $listingId = wishlistPositiveId($_GET['listing_id']);
                if ($listingId === null) {
                    wishlistJsonResponse(422, [
                        'success' => false,
                        'error' => 'Invalid listing_id.',
                    ]);
                }

                wishlistJsonResponse(200, [
                    'success' => true,
                    'listing_id' => $listingId,
                    'saved' => wishlistIsSaved($db, $userId, $listingId),
                    'csrf_token' => $_SESSION['wishlist_csrf_token'],
                ]);
            }

            $items = wishlistGetItems($db, $userId);
            wishlistJsonResponse(200, [
                'success' => true,
                'items' => $items,
                'count' => count($items),
                'csrf_token' => $_SESSION['wishlist_csrf_token'],
            ]);
        }

        $listingId = wishlistRequestListingId();

        if ($method === 'POST') {
            if (wishlistIsSaved($db, $userId, $listingId)) {
                wishlistJsonResponse(200, [
                    'success' => true,
                    'listing_id' => $listingId,
                    'saved' => true,
                    'already_saved' => true,
                ]);
            }

            if (!wishlistIsAvailable($db, $listingId)) {
                wishlistJsonResponse(404, [
                    'success' => false,
                    'error' => 'Listing unavailable.',
                ]);
            }

            try {
                $insert = $db->prepare('
                    INSERT INTO wishlist_items (user_id, listing_id)
                    VALUES (:user_id, :listing_id)
                ');
                $insert->execute(['user_id' => $userId, 'listing_id' => $listingId]);
            } catch (PDOException $error) {
                $mysqlCode = (int) ($error->errorInfo[1] ?? 0);
                if ($error->getCode() === '23000' && $mysqlCode === 1062) {
                    // Concurrent request already saved it; unique constraint prevents duplicates.
                    wishlistJsonResponse(200, [
                        'success' => true,
                        'listing_id' => $listingId,
                        'saved' => true,
                        'already_saved' => true,
                    ]);
                }
                if ($error->getCode() === '23000' && $mysqlCode === 1452) {
                    wishlistJsonResponse(409, [
                        'success' => false,
                        'error' => 'Listing unavailable.',
                    ]);
                }
                throw $error;
            }

            wishlistJsonResponse(201, [
                'success' => true,
                'listing_id' => $listingId,
                'saved' => true,
                'already_saved' => false,
            ]);
        }

        // DELETE only changes this user's saved relationship, not the listing itself.
        $delete = $db->prepare('
            DELETE FROM wishlist_items
            WHERE user_id = :user_id AND listing_id = :listing_id
        ');
        $delete->execute(['user_id' => $userId, 'listing_id' => $listingId]);
        wishlistJsonResponse(200, [
            'success' => true,
            'listing_id' => $listingId,
            'saved' => false,
            'removed' => $delete->rowCount() > 0,
        ]);
    } catch (Throwable $error) {
        error_log('OmniBuy wishlist API error: ' . $error->getMessage());
        wishlistJsonResponse(500, [
            'success' => false,
            'error' => 'Unable to load or update your wishlist right now.',
        ]);
    }
}

require_once __DIR__ . '/../components/require-login.php';

$pageTitle = 'Wishlist | OmniBuy';
$activeNav = 'wishlist';
$itemCount = 0;

$pageUserId = wishlistPositiveId($_SESSION['user_id'] ?? null);
if ($pageUserId !== null) {
    try {
        $itemCount = wishlistItemCount(wishlistDatabase(), $pageUserId);
    } catch (Throwable $error) {
        error_log('OmniBuy wishlist count error: ' . $error->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wishlist | OmniBuy</title>
>
    <link rel="stylesheet" href="/assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="/assets/css/shared-navigation.css">
    <link rel="stylesheet" href="/assets/css/wishlist.css">
    <script src="https://kit.fontawesome.com/9e304416f8.js" crossorigin="anonymous"></script>
    <script src="/assets/js/wishlist.js" defer></script>
</head>

<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <?php
        $header = __DIR__ . '/../components/header.php';
        include $header;
    ?>

    <main id="main-content" class="page-main wishlist-page">
        <div class="shell">
            <div class="wishlist-heading">
                <h1>My Wishlist</h1>
                <p>Saved listings you can return to later. <span id="wishlist-count"></span></p>
            </div>

            <p id="wishlist-message" class="wishlist-message" role="status" aria-live="polite"></p>

            <section
                id="wishlist-items"
                class="wishlist-grid"
                aria-label="Your saved listings"
                aria-live="polite"
                aria-busy="true"
            >
                <p>Loading wishlist...</p>
            </section>
        </div>
    </main>

    <?php
        $footer = __DIR__ . '/../components/footer.php';
        include $footer;
    ?>
</body>
</html>
