<?php

session_start();

require_once '../../db_connect.php';

$pageTitle = 'My Listings | OmniBuy';
$activeNav = 'my-listings';

$currentSellerId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$isLoggedIn = $currentSellerId !== false && $currentSellerId !== null;

$currentUser = [
    'username' => 'Seller',
    'avatar_url' => '/public/assets/icons/default-avatar.png',
    'is_seller' => $isLoggedIn,
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

<section class="shell">

    <h1>My Listings</h1>

    <?php if (!$isLoggedIn): ?>

        <p>Please log in to view your listings.</p>

    <?php else: ?>

        <?php if (
            isset($_GET['published']) &&
            $_GET['published'] === '1'
        ): ?>
            <p role="status">
                Your listing was published successfully!
            </p>
        <?php endif; ?>

        <?php if (empty($listings)): ?>

            <p>No listings found for this seller.</p>

            <p>
                <a href="create-listing.php">
                    Create your first listing
                </a>
            </p>

        <?php else: ?>

            <p>
                You have <?= count($listings) ?>
                listing(s).
            </p>

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

        <?php endif; ?>

    <?php endif; ?>

</section>

<?php

$footer = '../components/footer.php';
include $footer;

?>
