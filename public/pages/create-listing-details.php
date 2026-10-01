<?php

session_start();

$pageTitle = 'Listing Details | OmniBuy';
$activeNav = 'sell';
$isLoggedIn = true;

$currentUser = [
    'username' => 'MisterRoger68',
    'avatar_url' => '/public/assets/icons/default-avatar.png',
    'is_seller' => true,
];

$cartCount = 2;
$unreadCount = 3;

$category = $_SESSION['listing']['category'] ?? '';
$condition = $_SESSION['listing']['condition'] ?? '';
$brand = $_SESSION['listing']['brand'] ?? '';
$size = $_SESSION['listing']['size'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $condition = trim($_POST['condition'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $size = trim($_POST['size'] ?? '');

    $_SESSION['listing']['category'] = $category;
    $_SESSION['listing']['condition'] = $condition;
    $_SESSION['listing']['brand'] = $brand;
    $_SESSION['listing']['size'] = $size;
}

$header = '../components/header.php';
include $header;
?>

<link rel="stylesheet" href="../assets/css/create-listing-details.css">

<section class="shell listing-details">
  <h1>Listing Details</h1>

  <form class="listing-details__form" method="post">

    <div class="listing-details__field">
      <label for="category">Category</label>
      <select id="category" name="category">
        <option value="">Select a category</option>
        <option value="electronics" <?= $category === 'electronics' ? 'selected' : '' ?>>
          Electronics
        </option>
        <option value="collectibles" <?= $category === 'collectibles' ? 'selected' : '' ?>>
          Collectibles
        </option>
        <option value="clothing" <?= $category === 'clothing' ? 'selected' : '' ?>>
          Clothing, Shoes & Accessories
        </option>
        <option value="home-garden" <?= $category === 'home-garden' ? 'selected' : '' ?>>
          Home & Garden
        </option>
        <option value="sporting-goods" <?= $category === 'sporting-goods' ? 'selected' : '' ?>>
          Sporting Goods
        </option>
      </select>
    </div>

    <div class="listing-details__field">
      <label for="condition">Condition</label>
      <select id="condition" name="condition">
        <option value="">Select condition</option>
        <option value="new" <?= $condition === 'new' ? 'selected' : '' ?>>New</option>
        <option value="like-new" <?= $condition === 'like-new' ? 'selected' : '' ?>>Like New</option>
        <option value="good" <?= $condition === 'good' ? 'selected' : '' ?>>Good</option>
        <option value="fair" <?= $condition === 'fair' ? 'selected' : '' ?>>Fair</option>
        <option value="poor" <?= $condition === 'poor' ? 'selected' : '' ?>>Poor</option>
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

    <div>
      <a href="create-listing.php">Back</a>

      <button class="listing-details__submit" type="submit">
        Continue
      </button>
    </div>

  </form>
</section>

<?php

$footer = '../components/footer.php';
include $footer;

?>