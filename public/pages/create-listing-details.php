
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/listing-validation.php';

$pageTitle = 'Listing Details | OmniBuy';
$activeNav = 'sell';

$currentSellerId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$isLoggedIn = $currentSellerId !== false
    && $currentSellerId !== null;

$currentUser = [
    'username' => $_SESSION['username'] ?? 'Seller',
    'avatar_url' => $_SESSION['avatar_url']
        ?? '/assets/icons/default-avatar.png',
    'is_seller' => $isLoggedIn,
];

$cartCount = 0;
$unreadCount = 0;
$errors = [];

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
    'new',
    'open_box',
    'like_new',
    'excellent',
    'good',
    'fair',
    'poor',
    'refurbished',
    'for_parts',
];

$listing = $_SESSION['listing'] ?? [];

$category = (string) ($listing['category'] ?? '');
$condition = (string) ($listing['condition'] ?? '');
$brand = (string) ($listing['brand'] ?? '');
$size = (string) ($listing['size'] ?? '');
$quantity = (string) ($listing['quantity'] ?? '1');
$fulfillmentType = (string) (
    $listing['fulfillment_type'] ?? ''
);

$draftDirectory =
    sys_get_temp_dir() . '/omnibuy-listing-drafts';

$allowedImageTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$maxImageSize = 10 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category = trim((string) ($_POST['category'] ?? ''));
    $condition = trim((string) ($_POST['condition'] ?? ''));
    $brand = trim((string) ($_POST['brand'] ?? ''));
    $size = trim((string) ($_POST['size'] ?? ''));
    $quantity = trim((string) ($_POST['quantity'] ?? ''));
    $fulfillmentType = trim(
        (string) ($_POST['fulfillment_type'] ?? '')
    );

    $errors = validateListingDetails(
        [
            'category' => $category,
            'condition' => $condition,
            'quantity' => $quantity,
            'fulfillment_type' => $fulfillmentType,
        ],
        $validCategoryIds
    );

    if (!in_array($condition, $allowedConditions, true)) {
        $errors['condition'] = 'Invalid item condition.';
    }

    if (!in_array(
        $fulfillmentType,
        ['shipping', 'meetup'],
        true
    )) {
        $errors['fulfillment_type'] =
            'Select a valid delivery method.';
    }

    $title = trim((string) ($listing['title'] ?? ''));
    $description = trim(
        (string) ($listing['description'] ?? '')
    );
    $price = trim((string) ($listing['price'] ?? ''));

    if ($title === '' || mb_strlen($title) > 100) {
        $errors['listing'] =
            'A valid title is required. Return to Step 1.';
    }

    if ($description === '') {
        $errors['description'] =
            'A description is required. Return to Step 1.';
    }

    if (
        !is_numeric($price) ||
        !is_finite((float) $price) ||
        (float) $price <= 0 ||
        (float) $price > 99999999.99 ||
        !preg_match('/^\d+(\.\d{1,2})?$/', $price)
    ) {
        $errors['price'] =
            'A valid price is required. Return to Step 1.';
    }

    // Confirm that at least one staged photo exists.
    $photos = $listing['photos'] ?? [];

    if (
        !is_array($photos) ||
        count($photos) < 1 ||
        count($photos) > 10
    ) {
        $errors['photos'] =
            'At least one photo is required. Return to Step 1.';
    } else {

        $realDraftDirectory = realpath($draftDirectory);
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($photos as $photo) {

            $realPhoto = is_string($photo)
                ? realpath($photo)
                : false;

            if (
                !$realDraftDirectory ||
                !$realPhoto ||
                dirname($realPhoto) !== $realDraftDirectory ||
                !is_file($realPhoto) ||
                !is_readable($realPhoto) ||
                filesize($realPhoto) <= 0 ||
                filesize($realPhoto) > $maxImageSize
            ) {
                $errors['photos'] =
                    'A photo is missing or invalid. Return to Step 1.';
                break;
            }

            $mime = $finfo->file($realPhoto);

            if (
                !isset($allowedImageTypes[$mime]) ||
                @getimagesize($realPhoto) === false
            ) {
                $errors['photos'] =
                    'Invalid photo format. Return to Step 1.';
                break;
            }
        }
    }

    if (!$isLoggedIn) {
        $errors['seller'] =
            'Please log in before publishing.';
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

        $uploadDirectory =
            __DIR__ . '/../uploads/listings';

        $movedFiles = [];

        try {

            if (
                !is_dir($uploadDirectory) &&
                !mkdir($uploadDirectory, 0755, true)
            ) {
                throw new RuntimeException(
                    'Unable to create upload directory.'
                );
            }

            $pdo->beginTransaction();

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

            $listingId = (int) $pdo->lastInsertId();

            $imageStmt = $pdo->prepare(
                "INSERT INTO listing_images (
                    listing_id,
                    image_url,
                    display_order
                ) VALUES (
                    :listing_id,
                    :image_url,
                    :display_order
                )"
            );

            foreach ($photos as $index => $photo) {

                $mime = $finfo->file($photo);
                $extension = $allowedImageTypes[$mime];

                $filename = bin2hex(random_bytes(16))
                    . '.' . $extension;

                $destination =
                    $uploadDirectory . '/' . $filename;

                if (!rename($photo, $destination)) {
                    throw new RuntimeException(
                        'Unable to save listing photo.'
                    );
                }

                $movedFiles[] = $destination;

                $imageStmt->execute([
                    'listing_id' => $listingId,
                    'image_url' => '/uploads/listings/' . $filename,
                    'display_order' => $index + 1,
                ]);
            }

            $pdo->commit();

            unset($_SESSION['listing']);

            header('Location: my-listings.php?published=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Remove files associated with the failed listing.
            foreach ($movedFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            error_log(
                'Listing publication failed: '
                . $e->getMessage()
            );

            $errors['database'] =
                'Unable to publish your listing. Please try again.';
        }
    }
}

include __DIR__ . '/../components/header.php';

?>

<link rel="stylesheet" href="/assets/css/create-listing-details.css">

<section class="shell listing-details">

    <h1>Listing Details</h1>

    <p>
        Complete the details below to publish your listing.
    </p>

    <?php foreach ($errors as $error): ?>
        <p role="alert">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
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
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            $option['category_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
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
                        value="<?= htmlspecialchars(
                            $option, ENT_QUOTES, 'UTF-8'
                        ) ?>"
                        <?= $condition === $option
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            ucwords(str_replace('_', ' ', $option)),
                            ENT_QUOTES,
                            'UTF-8'
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
                value="<?= htmlspecialchars(
                    $brand, ENT_QUOTES, 'UTF-8'
                ) ?>"
            >
        </div>

        <div class="listing-details__field">
            <label for="size">Size</label>
            <input
                type="text"
                id="size"
                name="size"
                value="<?= htmlspecialchars(
                    $size, ENT_QUOTES, 'UTF-8'
                ) ?>"
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
                value="<?= htmlspecialchars(
                    $quantity, ENT_QUOTES, 'UTF-8'
                ) ?>"
                required
            >
        </div>

        <div class="listing-details__field">
            <label for="fulfillment_type">
                Delivery Method
            </label>

            <select
                id="fulfillment_type"
                name="fulfillment_type"
                required
            >
                <option value="">Select delivery method</option>

                <option
                    value="shipping"
                    <?= $fulfillmentType === 'shipping'
                        ? 'selected'
                        : '' ?>
                >
                    Shipping
                </option>

                <option
                    value="meetup"
                    <?= $fulfillmentType === 'meetup'
                        ? 'selected'
                        : '' ?>
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

</section>

<?php
include __DIR__ . '/../components/footer.php';
?>
