<?php

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
        <option value="electronics">Electronics</option>
        <option value="collectibles">Collectibles</option>
        <option value="clothing">Clothing, Shoes & Accessories</option>
        <option value="home-garden">Home & Garden</option>
        <option value="sporting-goods">Sporting Goods</option>
      </select>
    </div>

    <div class="listing-details__field">
      <label for="condition">Condition</label>
      <select id="condition" name="condition">
        <option value="">Select condition</option>
        <option value="new">New</option>
        <option value="like-new">Like New</option>
        <option value="good">Good</option>
        <option value="fair">Fair</option>
        <option value="poor">Poor</option>
      </select>
    </div>

    <div class="listing-details__field">
      <label for="brand">Brand</label>
      <input type="text" id="brand" name="brand">
    </div>

    <div class="listing-details__field">
      <label for="size">Size</label>
      <input type="text" id="size" name="size">
    </div>

    <button class="listing-details__submit" type="submit">
      Continue
    </button>

  </form>
</section>

<?php

$footer = '../components/footer.php';
include $footer;

?>