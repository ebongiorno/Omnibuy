<?php

$pageTitle = 'Create Listing | OmniBuy';
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
<link rel="stylesheet" href="../assets/css/create-listing.css">

<section class="shell create-listing">
  <h1>Create a Listing</h1>

  <form class="create-listing__form" method="post" enctype="multipart/form-data">

    <div class="create-listing__field">
      <label for="title">Listing Title</label>
      <input type="text" id="title" name="title">
    </div>

    <div class="create-listing__field">
      <label for="price">Price</label>
      <input type="number" id="price" name="price" step="0.01">
    </div>

    <div class="create-listing__field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="6"></textarea>
    </div>

    <div class="create-listing__field">
      <label for="photos">Photos</label>
      <input
        type="file"
        id="photos"
        name="photos[]"
        accept="image/*"
        multiple
      >
    </div>

    <button class="create-listing__submit" type="submit">
      Continue
    </button>

  </form>
</section>

<?php

$footer = '../components/footer.php';
include $footer;

?>