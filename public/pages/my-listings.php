<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../db_connect.php';

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
