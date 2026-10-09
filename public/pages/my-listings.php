
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
        "SELECT *
         FROM listings
         WHERE seller_user_id = :seller_user_id
         ORDER BY created_at DESC"
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

    <?php if (isset($_GET['published']) &&
              $_GET['published'] === '1'): ?>
      <p role="status">
        Your listing was published successfully!
      </p>
    <?php endif; ?>

    <?php if (empty($listings)): ?>

      <p>No listings found for this seller.</p>

    <?php else: ?>

      <?php foreach ($listings as $listing): ?>

        <div>
          <h2>
            <?= htmlspecialchars($listing['item_name']) ?>
          </h2>

          <p>
            Price: $<?= htmlspecialchars($listing['price']) ?>
          </p>

          <p>
            Status:
            <?= htmlspecialchars($listing['listing_status']) ?>
          </p>
        </div>

      <?php endforeach; ?>

    <?php endif; ?>

  <?php endif; ?>

</section>

<?php

$footer = '../components/footer.php';
include $footer;

?>
