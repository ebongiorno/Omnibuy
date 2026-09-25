<?php
require_once '../../db_connect.php';

$pageTitle = 'My Listings | OmniBuy';
$activeNav = 'my-listings';
$isLoggedIn = true;
$currentUser = [
    'username' => 'MisterRoger68',
    'avatar_url' => '/public/assets/default-avatar.png',
    'is_seller' => true,
];
$currentSellerId = 1;

$stmt = $pdo->prepare(
    "SELECT *
     FROM listings
     WHERE seller_user_id = :seller_user_id
     ORDER BY created_at DESC"
);

$stmt->execute([
    'seller_user_id' => $currentSellerId
]);

$listings = $stmt->fetchAll();
$cartCount = 2;
$unreadCount = 3;
$header = '../components/header.php';
include $header;
?>

<section class="shell">
    <h1>My Listings</h1>
    <?php if (empty($listings)): ?>
    <p>No listings found for this seller.</p>
<?php else: ?>
    <?php foreach ($listings as $listing): ?>
        <div>
            <h2><?php echo htmlspecialchars($listing['item_name']); ?></h2>
            <p>Price: $<?php echo htmlspecialchars($listing['price']); ?></p>
            <p>Status: <?php echo htmlspecialchars($listing['listing_status']); ?></p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</section>

<?php

$footer = '../components/footer.php';
include $footer;