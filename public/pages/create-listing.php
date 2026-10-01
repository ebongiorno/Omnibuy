<?php

session_start();

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

$errors = [];

// Restore previously entered listing data from the session.
$title = $_SESSION['listing']['title'] ?? '';
$price = $_SESSION['listing']['price'] ?? '';
$description = $_SESSION['listing']['description'] ?? '';

$allowedImageTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

$maxImageSize = 10 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        $errors['title'] = 'Listing title is required.';
    }

    if ($price === '') {
        $errors['price'] = 'Price is required.';
    } elseif (!is_numeric($price) || (float) $price <= 0) {
        $errors['price'] = 'Price must be greater than 0.';
    }

    if ($description === '') {
        $errors['description'] = 'Description is required.';
    }

    if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
        foreach ($_FILES['photos']['tmp_name'] as $index => $tmpName) {
            $uploadError = $_FILES['photos']['error'][$index];
            $fileSize = $_FILES['photos']['size'][$index];

            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors['photos'] = 'One or more photos could not be uploaded.';
                break;
            }

            if ($fileSize > $maxImageSize) {
                $errors['photos'] = 'Each photo must be 10MB or smaller.';
                break;
            }

            $mimeType = mime_content_type($tmpName);

            if (!in_array($mimeType, $allowedImageTypes, true)) {
                $errors['photos'] = 'Photos must be JPG, PNG, or WEBP images.';
                break;
            }
        }
    }

    if (empty($errors)) {
        // Preserve Step 1 listing data for the next step.
        $_SESSION['listing']['title'] = $title;
        $_SESSION['listing']['price'] = $price;
        $_SESSION['listing']['description'] = $description;

        // Continue to Step 2.
        header('Location: create-listing-details.php');
        exit;
    }
}

$header = '../components/header.php';
include $header;
?>

<link rel="stylesheet" href="../assets/css/create-listing.css">

<section class="shell create-listing">
  <h1>Create a Listing</h1>

  <form class="create-listing__form" method="post" enctype="multipart/form-data">

    <div class="create-listing__field">
      <label for="title">Listing Title</label>

      <input
        type="text"
        id="title"
        name="title"
        value="<?= htmlspecialchars($title) ?>"
        required
      >

      <?php if (isset($errors['title'])): ?>
        <p><?= htmlspecialchars($errors['title']) ?></p>
      <?php endif; ?>
    </div>

    <div class="create-listing__field">
      <label for="price">Price</label>

      <input
        type="number"
        id="price"
        name="price"
        step="0.01"
        min="0.01"
        value="<?= htmlspecialchars($price) ?>"
        required
      >

      <?php if (isset($errors['price'])): ?>
        <p><?= htmlspecialchars($errors['price']) ?></p>
      <?php endif; ?>
    </div>

    <div class="create-listing__field">
      <label for="description">Description</label>

      <textarea
        id="description"
        name="description"
        rows="6"
        required
      ><?= htmlspecialchars($description) ?></textarea>

      <?php if (isset($errors['description'])): ?>
        <p><?= htmlspecialchars($errors['description']) ?></p>
      <?php endif; ?>
    </div>

    <div class="create-listing__field">
      <label for="photos">Photos</label>

      <input
        type="file"
        id="photos"
        name="photos[]"
        accept=".jpg,.jpeg,.png,.webp"
        multiple
      >

      <?php if (isset($errors['photos'])): ?>
        <p><?= htmlspecialchars($errors['photos']) ?></p>
      <?php endif; ?>
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