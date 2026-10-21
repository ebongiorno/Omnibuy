<?php

require_once __DIR__ . '/../components/require-login.php';

$pageTitle = 'Create Listing | OmniBuy';
$activeNav = 'sell';

$errors = [];

$title = (string) ($_SESSION['listing']['title'] ?? '');
$price = (string) ($_SESSION['listing']['price'] ?? '');
$description = (string) ($_SESSION['listing']['description'] ?? '');

$allowedImageTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$maxImageSize = 10 * 1024 * 1024;
$maxPhotos = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim((string) ($_POST['title'] ?? ''));
    $price = trim((string) ($_POST['price'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($title === '' || mb_strlen($title) > 100) {
        $errors['title'] =
            'Title is required and must be 100 characters or less.';
    }

    if (
        !is_numeric($price) ||
        !is_finite((float) $price) ||
        (float) $price <= 0 ||
        (float) $price > 99999999.99 ||
        !preg_match('/^\d+(\.\d{1,2})?$/', $price)
    ) {
        $errors['price'] =
            'Enter a valid price greater than 0.';
    }

    if ($description === '') {
        $errors['description'] =
            'Description is required.';
    }

    $photos = $_FILES['photos'] ?? null;
    $validPhotos = [];

    if (
        !$photos ||
        !isset($photos['error']) ||
        !is_array($photos['error'])
    ) {
        $errors['photos'] =
            'Please upload at least one photo.';
    } else {

        $photoCount = count($photos['error']);

        if ($photoCount > $maxPhotos) {
            $errors['photos'] =
                'You can upload up to 10 photos.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($photos['error'] as $index => $error) {

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $errors['photos'] =
                    'One or more photos could not be uploaded.';
                break;
            }

            $tmpName = $photos['tmp_name'][$index] ?? '';
            $size = (int) ($photos['size'][$index] ?? 0);

            if (
                $size <= 0 ||
                $size > $maxImageSize ||
                !is_uploaded_file($tmpName)
            ) {
                $errors['photos'] =
                    'Each photo must be a valid file under 10MB.';
                break;
            }

            $mime = $finfo->file($tmpName);

            if (
                !isset($allowedImageTypes[$mime]) ||
                @getimagesize($tmpName) === false
            ) {
                $errors['photos'] =
                    'Photos must be JPG, PNG, or WEBP images.';
                break;
            }

            $validPhotos[] = [
                'tmp_name' => $tmpName,
                'extension' => $allowedImageTypes[$mime],
            ];
        }

        if (empty($validPhotos) && !isset($errors['photos'])) {
            $errors['photos'] =
                'Please upload at least one photo.';
        }
    }

    if (empty($errors)) {

        $draftDirectory =
            sys_get_temp_dir() . '/omnibuy-listing-drafts';

        if (
            !is_dir($draftDirectory) &&
            !mkdir($draftDirectory, 0700, true)
        ) {
            $errors['photos'] =
                'Unable to prepare photo storage.';
        } else {

            $savedPhotos = [];

            try {

                foreach ($validPhotos as $photo) {

                    $filename = bin2hex(random_bytes(16))
                        . '.' . $photo['extension'];

                    $destination =
                        $draftDirectory . '/' . $filename;

                    if (!move_uploaded_file(
                        $photo['tmp_name'],
                        $destination
                    )) {
                        throw new RuntimeException(
                            'Photo upload failed.'
                        );
                    }

                    $savedPhotos[] = $destination;
                }

                // Remove any previous draft photos.
                foreach (
                    $_SESSION['listing']['photos'] ?? []
                    as $oldPhoto
                ) {
                    if (
                        is_string($oldPhoto) &&
                        dirname($oldPhoto) === $draftDirectory &&
                        is_file($oldPhoto)
                    ) {
                        @unlink($oldPhoto);
                    }
                }

                $_SESSION['listing']['title'] = $title;
                $_SESSION['listing']['price'] = $price;
                $_SESSION['listing']['description'] = $description;
                $_SESSION['listing']['photos'] = $savedPhotos;

                header('Location: create-listing-details.php');
                exit;

            } catch (Throwable $e) {

                foreach ($savedPhotos as $path) {
                    @unlink($path);
                }

                error_log(
                    'Listing photo staging failed: '
                    . $e->getMessage()
                );

                $errors['photos'] =
                    'Unable to save photos. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../components/header.php';

?>

<link rel="stylesheet" href="/assets/css/create-listing.css">

<section class="shell create-listing">

    <h1>Create a Listing</h1>

    <form
        class="create-listing__form"
        method="post"
        enctype="multipart/form-data"
    >

        <div class="create-listing__field">
            <label for="title">Listing Title</label>

            <input
                type="text"
                id="title"
                name="title"
                maxlength="100"
                value="<?= htmlspecialchars(
                    $title, ENT_QUOTES, 'UTF-8'
                ) ?>"
                required
            >

            <?php if (isset($errors['title'])): ?>
                <p role="alert">
                    <?= htmlspecialchars($errors['title']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="create-listing__field">
            <label for="price">Price ($)</label>

            <input
                type="number"
                id="price"
                name="price"
                step="0.01"
                min="0.01"
                max="99999999.99"
                value="<?= htmlspecialchars(
                    $price, ENT_QUOTES, 'UTF-8'
                ) ?>"
                required
            >

            <?php if (isset($errors['price'])): ?>
                <p role="alert">
                    <?= htmlspecialchars($errors['price']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="create-listing__field">
            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                rows="6"
                required
            ><?= htmlspecialchars(
                $description, ENT_QUOTES, 'UTF-8'
            ) ?></textarea>

            <p class="create-listing__hint">
                Having a description helps the item sell faster
            </p>

            <?php if (isset($errors['description'])): ?>
                <p role="alert">
                    <?= htmlspecialchars($errors['description']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="create-listing__field">
            <label for="photos">Photos (Required)</label>

            <input
                type="file"
                id="photos"
                name="photos[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                required
            >

            <p class="create-listing__hint">
                Upload at least one photo.
                JPG, PNG, or WEBP.
                Maximum 10 photos, 10MB each.
            </p>

            <?php if (isset($errors['photos'])): ?>
                <p role="alert">
                    <?= htmlspecialchars($errors['photos']) ?>
                </p>
            <?php endif; ?>
        </div>

        <button
            class="create-listing__submit"
            type="submit"
        >
            Continue
        </button>

    </form>

</section>

<?php
include __DIR__ . '/../components/footer.php';
?>
