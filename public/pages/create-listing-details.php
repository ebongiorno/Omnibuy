
<?php

session_start();

require_once '../../db_connect.php';

$pageTitle = 'Listing Details | OmniBuy';
$activeNav = 'sell';

$currentSellerId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$isLoggedIn = $currentSellerId !== false && $currentSellerId !== null;

$currentUser = [
    'username' => 'Seller',
    'avatar_url' => '/public/assets/icons/default-avatar.png',
    'is_seller' => true,
];

$cartCount = 0;
$unreadCount = 0;
$errors = [];

// Load categories from the database.
$categoryStmt = $pdo->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name"
);

$categories = $categoryStmt->fetchAll();

$validCategoryIds = array_map(
    'strval',
    array_column($categories, 'category_id')
);

$allowedConditions = [
    'new', 'open_box', 'like_new', 'excellent',
    'good', 'fair', 'poor', 'refurbished', 'for_parts'
];

// Restore listing details from session.
$category = (string) ($_SESSION['listing']['category'] ?? '');
$condition = $_SESSION['listing']['condition'] ?? '';
$brand = $_SESSION['listing']['brand'] ?? '';
$size = $_SESSION['listing']['size'] ?? '';
$quantity = (string) ($_SESSION['listing']['quantity'] ?? '1');
$fulfillmentType = $_SESSION['listing']['fulfillment_type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category = trim((string) ($_POST['category'] ?? ''));
    $condition = trim((string) ($_POST['condition'] ?? ''));
    $brand = trim((string) ($_POST['brand'] ?? ''));
    $size = trim((string) ($_POST['size'] ?? ''));
    $quantity = trim((string) ($_POST['quantity'] ?? ''));
    $fulfillmentType = trim(
        (string) ($_POST['fulfillment_type'] ?? '')
    );

    // Validate category.
    if (!in_array($category, $validCategoryIds, true)) {
        $errors['category'] = 'Please select a valid category.';
    }

    // Validate condition.
    if (!in_array($condition, $allowedConditions, true)) {
        $errors['condition'] = 'Please select a valid condition.';
    }

    // Validate quantity.
    if (
        filter_var(
            $quantity,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) === false
    ) {
        $errors['quantity'] = 'Quantity must be at least 1.';
    }

    // Validate fulfillment type.
    if (!in_array(
        $fulfillmentType,
        ['shipping', 'meetup'],
        true
    )) {
        $errors['fulfillment'] =
            'Please select shipping or meetup.';
    }

    // Validate previously entered listing information.
    $listing = $_SESSION['listing'] ?? [];

    $title = trim((string) ($listing['title'] ?? ''));
    $description = trim(
        (string) ($listing['description'] ?? '')
    );
    $price = trim((string) ($listing['price'] ?? ''));

    if ($title === '' || strlen($title) > 100) {
        $errors['listing'] =
            'A valid listing title is required. Return to Step 1.';
    }

    if ($description === '') {
        $errors['listing'] =
            'A listing description is required. Return to Step 1.';
    }

    if (
        !is_numeric($price) ||
        !is_finite((float) $price) ||
        (float) $price <= 0 ||
        (float) $price > 99999999.99
    ) {
        $errors['listing'] =
            'A valid listing price is required. Return to Step 1.';
    }

    // Require a real authenticated seller.
    if (!$isLoggedIn) {
        $errors['seller'] =
            'Please log in as a seller before publishing.';
    } else {
        $sellerStmt = $pdo->prepare(
            "SELECT user_id
             FROM seller_profiles
             WHERE user_id = :user_id"
        );

        $sellerStmt->execute([
            'user_id' => $currentSellerId
        ]);

        if (!$sellerStmt->fetch()) {
            $errors['seller'] =
                'Your account does not have a seller profile.';
        }
    }

    if (empty($errors)) {

        try {
            // Save the listing to the database.
            $stmt = $pdo->prepare(
                "INSERT INTO listings (
                    seller_user_id,
                    category_id,
                    item_name,
                    item_description,
                    sale_type,
                    fulfillment_type,
                    price,
                    `condition`,
                    quantity,
                    listing_status
                ) VALUES (
                    :seller_user_id,
                    :category_id,
                    :item_name,
                    :item_description,
                    'fixed_price',
                    :fulfillment_type,
                    :price,
                    :condition,
                    :quantity,
                    'active'
                )"
            );

            $stmt->execute([
                'seller_user_id' => $currentSellerId,
                'category_id' => (int) $category,
                'item_name' => $title,
                'item_description' => $description,
                'fulfillment_type' => $fulfillmentType,
                'price' => $price,
                'condition' => $condition,
                'quantity' => (int) $quantity,
            ]);

            // Clear the completed listing draft.
            unset($_SESSION['listing']);

            // Redirect after successful publication.
            header('Location: my-listings.php?published=1');
            exit;

        } catch (PDOException $e) {
            error_log('Listing publication failed: ' . $e->getMessage());

            $errors['database'] =
                'Unable to publish your listing. Please try again.';
        }
    }
}

$header = '../components/header.php';
include $header;
?>

<link rel="stylesheet" href="../assets/css/create-listing-details.css">

<section class="shell listing-details">

  <h1>Listing Details</h1>

  <?php foreach ($errors as $error): ?>
    <p role="alert">
      <?= htmlspecialchars($error) ?>
    </p>
  <?php endforeach; ?>

  <form class="listing-details__form" method="post">

    <div class="listing-details__field">
      <label for="category">Category</label>

      <select id="category" name="category" required>
        <option value="">Select a category</option>

        <?php foreach ($categories as $option): ?>
          <option
            value="<?= (int) $option['category_id'] ?>"
            <?= $category === (string) $option['category_id']
                ? 'selected' : '' ?>
          >
            <?= htmlspecialchars($option['category_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="listing-details__field">
      <label for="condition">Condition</label>

      <select id="condition" name="condition" required>
        <option value="">Select condition</option>

        <?php foreach ($allowedConditions as $option): ?>
          <option
            value="<?= htmlspecialchars($option) ?>"
            <?= $condition === $option ? 'selected' : '' ?>
          >
            <?= htmlspecialchars(
                ucwords(str_replace('_', ' ', $option))
            ) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="listing-details__field">
      <label for="brand">Brand</label>

      <input
        type="text"
        id="brand"
        name="brand"
        value="<?= htmlspecialchars($brand) ?>"
      >
    </div>

    <div class="listing-details__field">
      <label for="size">Size</label>

      <input
        type="text"
        id="size"
        name="size"
        value="<?= htmlspecialchars($size) ?>"
      >
    </div>

    <div class="listing-details__field">
      <label for="quantity">Quantity</label>

      <input
        type="number"
        id="quantity"
        name="quantity"
        min="1"
        step="1"
        value="<?= htmlspecialchars($quantity) ?>"
        required
      >
    </div>

    <div class="listing-details__field">
      <label for="fulfillment_type">Delivery Method</label>

      <select
        id="fulfillment_type"
        name="fulfillment_type"
        required
      >
        <option value="">Select delivery method</option>

        <option
          value="shipping"
          <?= $fulfillmentType === 'shipping'
              ? 'selected' : '' ?>
        >
          Shipping
        </option>

        <option
          value="meetup"
          <?= $fulfillmentType === 'meetup'
              ? 'selected' : '' ?>
        >
          Meetup
        </option>
      </select>
    </div>

    <div>
      <a href="create-listing.php">Back</a>

      <button
        class="listing-details__submit"
        type="submit"
        <?= !$isLoggedIn ? 'disabled' : '' ?>
      >
        Publish Listing
      </button>
    </div>

  </form>

  <?php if (!$isLoggedIn): ?>
    <p>
      You must be logged in as a seller to publish a listing.
    </p>
  <?php endif; ?>

</section>

<?php

$footer = '../components/footer.php';
include $footer;

?>
