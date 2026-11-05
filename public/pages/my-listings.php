<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db_connect.php';

startSession();

$pageTitle = 'My Listings | OmniBuy';
$activeNav = 'my-listings';
$pageStylesheet = '/assets/css/my-listings.css';

$currentSellerId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$isLoggedIn = $currentSellerId !== false && $currentSellerId !== null;

// SOC 2 CC6.3: Require authentication before accessing seller listings.
if (!$isLoggedIn) {
    header('Location: /pages/login.php');
    exit;
}

// Mark as sold logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (($_POST['action'] ?? null) !== 'mark_sold') {
        http_response_code(400);
        exit('Invalid action.');
    }

    // Validate CSRF token.
    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !isValidCsrfToken($token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    // Validate listing ID.
    $listingId = filter_var(
        $_POST['listing_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($listingId === false || $listingId === null) {
        http_response_code(400);
        exit('Invalid listing ID.');
    }

    // Update only an active listing owned by this seller.
    $stmt = $pdo->prepare(
        "UPDATE listings
         SET listing_status = 'sold'
         WHERE listing_id = :listing_id
           AND seller_user_id = :seller_id
           AND listing_status = 'active'"
    );

    $stmt->execute([
        'listing_id' => $listingId,
        'seller_id' => $currentSellerId
    ]);

    if ($stmt->rowCount() !== 1) {
        http_response_code(404);
        exit('Listing not found or unavailable for this action.');
    }

    // Redirect to avoid resubmitting the form on refresh.
    header('Location: /pages/my-listings.php?sold=1', true, 303);
    exit;
}

$currentUser = [
    'username' => $_SESSION['username'] ?? 'Seller',
    'avatar_url' => $_SESSION['avatar_url']
        ?? '/assets/icons/default-avatar.png',
    'is_seller' => $_SESSION['is_seller'] ?? $isLoggedIn,
];

$cartCount = 0;
$unreadCount = 0;
$listings = [];

if ($isLoggedIn) {
    $stmt = $pdo->prepare(
        "SELECT
            listing_id,
            item_name,
            price,
            `condition`,
            quantity,
            fulfillment_type,
            listing_status,
            created_at
         FROM listings
         WHERE seller_user_id = :seller_user_id
         ORDER BY created_at DESC, listing_id DESC"
    );

    $stmt->execute([
        'seller_user_id' => $currentSellerId
    ]);

    $listings = $stmt->fetchAll();
}

$header = '../components/header.php';
include $header;

?>

<section class="shell my-listings-page">

    <div class="my-listings-heading">

        <div>
            <h1>My Listings</h1>

            <p class="my-listings-subtitle">
                View and manage your OmniBuy listings.
            </p>
        </div>

        <?php if ($isLoggedIn): ?>

            <a
                class="my-listings-create"
                href="create-listing.php"
            >
                + Create Listing
            </a>

        <?php endif; ?>

    </div>

    <?php if (!$isLoggedIn): ?>

        <div class="my-listings-message">

            <h2>Log in to view your listings</h2>

            <p>
                Please log in to access your listings.
            </p>

            <a href="/pages/login.php">
                Log in
            </a>

        </div>

    <?php else: ?>

        <?php if (
            isset($_GET['published']) &&
            $_GET['published'] === '1'
        ): ?>

            <p class="my-listings-success" role="status">
                Your listing was published successfully!
            </p>

        <?php endif; ?>

        <?php if (empty($listings)): ?>

            <div class="my-listings-message">

                <h2>No listings yet</h2>

                <p>
                    You haven't created any listings yet.
                </p>

                <a href="create-listing.php">
                    Create your first listing
                </a>

            </div>

        <?php else: ?>

            <p class="my-listings-subtitle">
                You have <?= count($listings) ?> listing(s).
            </p>

            <div class="my-listings-grid">

                <?php foreach ($listings as $listing): ?>

                    <article class="my-listings__item">

                        <h2>
                            <?= htmlspecialchars(
                                (string) $listing['item_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h2>

                        <p>
                            <strong>Price:</strong>

                            $<?= number_format(
                                (float) $listing['price'],
                                2
                            ) ?>
                        </p>

                        <p>
                            <strong>Condition:</strong>

                            <?= htmlspecialchars(
                                ucwords(str_replace(
                                    '_',
                                    ' ',
                                    (string) $listing['condition']
                                )),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <p>
                            <strong>Quantity:</strong>

                            <?= (int) $listing['quantity'] ?>
                        </p>

                        <p>
                            <strong>Delivery Method:</strong>

                            <?= htmlspecialchars(
                                ucfirst(
                                    (string) $listing['fulfillment_type']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <p>
                            <strong>Status:</strong>

                            <?= htmlspecialchars(
                                ucfirst(
                                    (string) $listing['listing_status']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <?php if ($listing['listing_status'] === 'active'): ?>

                            <form
                                action="/pages/my-listings.php"
                                method="POST"
                                onsubmit="return confirm('Are you sure you want to mark this listing as sold?');"
                            >
                                <input
                                    type="hidden"
                                    name="action"
                                    value="mark_sold"
                                >

                                <input
                                    type="hidden"
                                    name="listing_id"
                                    value="<?= (int) $listing['listing_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                        csrfToken(),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                <button type="submit" class="my-listings__mark-sold">
                                    Mark as Sold
                                </button>
                            </form>

                        <?php endif; ?>

                         <p>
                            <strong>Created:</strong>

                            <?= htmlspecialchars(
                                (string) $listing['created_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</section>

<?php

$footer = '../components/footer.php';
include $footer;

?>
