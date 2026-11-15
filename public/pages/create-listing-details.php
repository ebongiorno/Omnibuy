<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/listing-validation.php';
require_once __DIR__ . '/../../includes/account.php';

startSession();

$pageTitle = 'Listing Details | OmniBuy';
$activeNav = 'sell';
$currentSellerId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($currentSellerId === false || $currentSellerId === null) {
    header('Location: /pages/login.php');
    exit;
}

$currentUser = [
    'username' => $_SESSION['username'] ?? 'Seller',
    'avatar_url' => $_SESSION['avatar_url'] ?? '/assets/icons/default-avatar.png',
    'is_seller' => true,
];
$cartCount = 0;
$unreadCount = 0;
$errors = [];
$listing = $_SESSION['listing'] ?? [];
$editData = $_SESSION['listing_edit'] ?? null;
$isEditMode = is_array($editData)
    && filter_var(
        $editData['listing_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    ) !== false;
$editingListingId = $isEditMode ? (int) $editData['listing_id'] : null;

if ($isEditMode && (int) ($editData['seller_user_id'] ?? 0) !== (int) $currentSellerId) {
    http_response_code(403);
    exit('Editing session is not authorized.');
}

$categoryStmt = $pdo->query(
    'SELECT category_id, parent_category_id, category_name FROM categories ORDER BY category_name'
);
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
$mainCategories = [];
$subcategories = [];
$categoryParents = [];
$validCategoryIds = [];

foreach ($categories as $option) {
    $id = (string) $option['category_id'];
    $parent = $option['parent_category_id'];
    $categoryParents[$id] = $parent === null ? null : (string) $parent;
    $validCategoryIds[] = $id;

    if ($parent === null) {
        $mainCategories[] = $option;
    } else {
        $subcategories[(string) $parent][] = $option;
    }
}

$clothingCategoryId = null;
foreach ($mainCategories as $option) {
    if (strcasecmp($option['category_name'], 'Clothing') === 0) {
        $clothingCategoryId = (string) $option['category_id'];
        break;
    }
}

$allowedConditions = [
    'new', 'open_box', 'like_new', 'excellent', 'good',
    'fair', 'poor', 'refurbished', 'for_parts',
];
$category = (string) ($listing['category'] ?? '');
$mainCategory = (string) ($listing['main_category'] ?? '');
if ($mainCategory === '' && array_key_exists($category, $categoryParents)) {
    $mainCategory = $categoryParents[$category] ?? $category;
}
$condition = (string) ($listing['condition'] ?? '');
$brand = (string) ($listing['brand'] ?? '');
$size = (string) ($listing['size'] ?? '');
$quantity = (string) ($listing['quantity'] ?? '1');
$fulfillmentType = (string) ($listing['fulfillment_type'] ?? '');
$draftDirectory = sys_get_temp_dir() . '/omnibuy-listing-drafts';
$allowedImageTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$maxImageSize = 10 * 1024 * 1024;
$escape = static fn($value) => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !isValidCsrfToken($token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $category = is_string($_POST['category'] ?? null) ? trim($_POST['category']) : '';
    $mainCategory = is_string($_POST['main_category'] ?? null) ? trim($_POST['main_category']) : '';
    $condition = is_string($_POST['condition'] ?? null) ? trim($_POST['condition']) : '';
    $brand = is_string($_POST['brand'] ?? null) ? trim($_POST['brand']) : '';
    $size = is_string($_POST['size'] ?? null) ? trim($_POST['size']) : '';
    $quantity = is_string($_POST['quantity'] ?? null) ? trim($_POST['quantity']) : '';
    $fulfillmentType = is_string($_POST['fulfillment_type'] ?? null)
        ? trim($_POST['fulfillment_type']) : '';

    $errors = validateListingDetails([
        'category' => $category,
        'condition' => $condition,
        'quantity' => $quantity,
        'fulfillment_type' => $fulfillmentType,
    ], $validCategoryIds);

    if (!array_key_exists($mainCategory, $categoryParents)
        || $categoryParents[$mainCategory] !== null) {
        $errors['main_category'] = 'Please select a valid main category.';
    } elseif (!array_key_exists($category, $categoryParents)) {
        $errors['category'] = 'Please select a valid category.';
    } elseif (($subcategories[$mainCategory] ?? []) !== []) {
        if ($categoryParents[$category] !== $mainCategory) {
            $errors['category'] = 'Select a subcategory from the chosen main category.';
        }
    } elseif ($category !== $mainCategory) {
        $errors['category'] = 'This category does not have subcategories.';
    }

    if ($mainCategory !== $clothingCategoryId) {
        $size = '';
    }

    $title = trim((string) ($listing['title'] ?? ''));
    $description = trim((string) ($listing['description'] ?? ''));
    $price = trim((string) ($listing['price'] ?? ''));

    if ($title === '' || mb_strlen($title) > 100) {
        $errors['title'] = 'A valid title is required. Return to Step 1.';
    }
    if ($description === '') {
        $errors['description'] = 'A description is required. Return to Step 1.';
    }
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $price)
        || (float) $price <= 0 || (float) $price > 99999999.99) {
        $errors['price'] = 'A valid price is required. Return to Step 1.';
    }

    $photos = $listing['photos'] ?? [];
    $removedPhotoIds = $isEditMode
        ? ($_SESSION['listing']['remove_photo_ids'] ?? [])
        : [];
    if (!is_array($removedPhotoIds)) {
        $removedPhotoIds = [];
    }
    if (!is_array($photos)) {
        $photos = [];
        $errors['photos'] = 'Invalid photo data. Return to Step 1.';
    }
    if (count($photos) > 10) {
        $errors['photos'] = 'Maximum 10 photos.';
    }

    $realDraftDirectory = realpath($draftDirectory);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($photos as $photo) {
        $realPhoto = is_string($photo) ? realpath($photo) : false;
        if (!$realDraftDirectory || !$realPhoto
            || dirname($realPhoto) !== $realDraftDirectory
            || !is_file($realPhoto) || !is_readable($realPhoto)
            || filesize($realPhoto) <= 0 || filesize($realPhoto) > $maxImageSize
            || !isset($allowedImageTypes[$finfo->file($realPhoto)])
            || @getimagesize($realPhoto) === false) {
            $errors['photos'] = 'A photo is missing or invalid. Return to Step 1.';
            break;
        }
    }
    if (!$isEditMode && count($photos) === 0) {
        $errors['photos'] = 'At least one photo is required. Return to Step 1.';
    }

    if ($errors === []) {
        $uploadDirectory = __DIR__ . '/../uploads/listings';
        $movedFiles = [];

        try {
            $pdo->beginTransaction();

            if ($isEditMode) {
                $check = $pdo->prepare(
                    "SELECT listing_id FROM listings
                     WHERE listing_id = :id AND seller_user_id = :seller
                       AND listing_status = 'active' FOR UPDATE"
                );
                $check->execute([
                    'id' => $editingListingId,
                    'seller' => $currentSellerId,
                ]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('Listing is not available for editing.');
                }

                $listingId = $editingListingId;
                // Read current images from the database, not from client-provided URLs.
                $imageQuery = $pdo->prepare(
                    'SELECT listing_image_id, image_url FROM listing_images
                     WHERE listing_id = :id
                     ORDER BY display_order, listing_image_id FOR UPDATE'
                );
                $imageQuery->execute(['id' => $listingId]);
                $storedImages = $imageQuery->fetchAll(PDO::FETCH_ASSOC);
                $storedIds = array_map('intval', array_column($storedImages, 'listing_image_id'));
                $removedPhotoIds = array_values(array_unique(array_map('intval', $removedPhotoIds)));
                foreach ($removedPhotoIds as $removeId) {
                    if ($removeId < 1 || !in_array($removeId, $storedIds, true)) {
                        throw new RuntimeException('Invalid photo removal request.');
                    }
                }
                $retained = array_values(array_filter(
                    $storedImages,
                    static fn($image) => !in_array((int) $image['listing_image_id'], $removedPhotoIds, true)
                ));
                $finalPhotoCount = count($retained) + count($photos);
                if ($finalPhotoCount < 1 || $finalPhotoCount > 10) {
                    $errors['photos'] = 'A listing must have between 1 and 10 photos.';
                    throw new RuntimeException('Invalid photo count.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE listings SET category_id = :category_id,
                        item_name = :item_name,
                        item_description = :description,
                        fulfillment_type = :fulfillment_type,
                        price = :price, `condition` = :item_condition,
                        quantity = :quantity
                     WHERE listing_id = :id AND seller_user_id = :seller
                       AND listing_status = 'active'"
                );
                $stmt->execute([
                    'category_id' => (int) $category,
                    'item_name' => $title,
                    'description' => $description,
                    'fulfillment_type' => $fulfillmentType,
                    'price' => $price,
                    'item_condition' => $condition,
                    'quantity' => (int) $quantity,
                    'id' => $listingId,
                    'seller' => $currentSellerId,
                ]);

                // Rebuild ordered references, keeping selected old images and appending new ones.
                // All changes happen in the same transaction as the listing update.
                $pdo->prepare('DELETE FROM listing_images WHERE listing_id = :id')
                    ->execute(['id' => $listingId]);
                $restoreImage = $pdo->prepare(
                    'INSERT INTO listing_images (listing_id, image_url, display_order)
                     VALUES (:id, :url, :position)'
                );
                foreach ($retained as $position => $image) {
                    $restoreImage->execute([
                        'id' => $listingId,
                        'url' => $image['image_url'],
                        'position' => $position + 1,
                    ]);
                }
                $nextOrder = count($retained) + 1;
            } else {
                ensureSellerProfile($pdo, $currentSellerId);
                $stmt = $pdo->prepare(
                    "INSERT INTO listings (
                        seller_user_id, category_id, item_name,
                        item_description, sale_type, fulfillment_type,
                        price, `condition`, quantity, listing_status
                    ) VALUES (
                        :seller, :category, :name, :description,
                        'fixed_price', :fulfillment, :price,
                        :item_condition, :quantity, 'active'
                    )"
                );
                $stmt->execute([
                    'seller' => $currentSellerId,
                    'category' => (int) $category,
                    'name' => $title,
                    'description' => $description,
                    'fulfillment' => $fulfillmentType,
                    'price' => $price,
                    'item_condition' => $condition,
                    'quantity' => (int) $quantity,
                ]);
                $listingId = (int) $pdo->lastInsertId();
                $nextOrder = 1;
            }

            if ($photos !== []) {
                if (!is_dir($uploadDirectory)
                    && !mkdir($uploadDirectory, 0755, true)
                    && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Unable to create upload directory.');
                }
                $imageStmt = $pdo->prepare(
                    'INSERT INTO listing_images (listing_id, image_url, display_order)
                     VALUES (:id, :url, :position)'
                );
                foreach ($photos as $index => $photo) {
                    $extension = $allowedImageTypes[$finfo->file($photo)];
                    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
                    $destination = $uploadDirectory . '/' . $filename;
                    if (!rename($photo, $destination)) {
                        throw new RuntimeException('Unable to save listing photo.');
                    }
                    $movedFiles[] = $destination;
                    $imageStmt->execute([
                        'id' => $listingId,
                        'url' => '/uploads/listings/' . $filename,
                        'position' => $nextOrder + $index,
                    ]);
                }
            }

            $pdo->commit();
            $_SESSION['is_seller'] = true;
            unset($_SESSION['listing'], $_SESSION['listing_edit']);
            header(
                'Location: my-listings.php?' . ($isEditMode ? 'edited=1' : 'published=1'),
                true,
                303
            );
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($movedFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            error_log('Listing save failed: ' . $e->getMessage());
            $errors['database'] = 'Unable to save your listing. Please try again.';
        }
    }
}

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/create-listing-details.css">

<section class="shell listing-details">
    <h1><?= $isEditMode ? 'Edit Listing Details' : 'Listing Details' ?></h1>
    <p><?= $isEditMode
        ? 'Review the details and save your changes.'
        : 'Complete the details below to publish your listing.' ?></p>

    <?php foreach ($errors as $error): ?>
        <p role="alert"><?= $escape($error) ?></p>
    <?php endforeach; ?>

    <form class="listing-details__form" method="post">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">

        <div class="listing-details__field">
            <label for="main_category">Main Category</label>
            <select id="main_category" name="main_category" required>
                <option value="">Select a main category</option>
                <?php foreach ($mainCategories as $option): ?>
                    <option value="<?= (int) $option['category_id'] ?>"
                        <?= $mainCategory === (string) $option['category_id'] ? 'selected' : '' ?>>
                        <?= $escape($option['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="listing-details__field" id="subcategory_field">
            <label for="category">Subcategory</label>
            <select id="category" name="category" required>
                <option value="">Select a subcategory</option>
                <?php foreach ($subcategories as $parentId => $children): ?>
                    <?php foreach ($children as $option): ?>
                        <option value="<?= (int) $option['category_id'] ?>"
                            data-parent="<?= (int) $parentId ?>"
                            <?= $category === (string) $option['category_id'] ? 'selected' : '' ?>>
                            <?= $escape($option['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="listing-details__field">
            <label for="condition">Condition</label>
            <select id="condition" name="condition" required>
                <option value="">Select condition</option>
                <?php foreach ($allowedConditions as $option): ?>
                    <option value="<?= $escape($option) ?>"
                        <?= $condition === $option ? 'selected' : '' ?>>
                        <?= $escape(ucwords(str_replace('_', ' ', $option))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="listing-details__field">
            <label for="brand">Brand</label>
            <input type="text" id="brand" name="brand" value="<?= $escape($brand) ?>">
        </div>

        <div class="listing-details__field" id="size_field"
            <?= $mainCategory === $clothingCategoryId ? '' : 'hidden' ?>>
            <label for="size">Size</label>
            <input type="text" id="size" name="size" value="<?= $escape($size) ?>">
        </div>

        <div class="listing-details__field">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="1" step="1"
                value="<?= $escape($quantity) ?>" required>
        </div>

        <div class="listing-details__field">
            <label for="fulfillment_type">Delivery Method</label>
            <select id="fulfillment_type" name="fulfillment_type" required>
                <option value="">Select delivery method</option>
                <option value="shipping" <?= $fulfillmentType === 'shipping' ? 'selected' : '' ?>>Shipping</option>
                <option value="meetup" <?= $fulfillmentType === 'meetup' ? 'selected' : '' ?>>Meetup</option>
            </select>
        </div>

        <div>
            <a href="create-listing.php">Back</a>
            <button class="listing-details__submit" type="submit">
                <?= $isEditMode ? 'Save Changes' : 'Publish Listing' ?>
            </button>
        </div>
    </form>
</section>

<script>
(() => {
    const main = document.getElementById('main_category');
    const sub = document.getElementById('category');
    const subField = document.getElementById('subcategory_field');
    const sizeField = document.getElementById('size_field');
    const sizeInput = document.getElementById('size');
    const clothingId = <?= json_encode($clothingCategoryId) ?>;
    const options = Array.from(sub.querySelectorAll('option[data-parent]'));
    const emptyOption = sub.querySelector('option[value=""]');

    function updateCategories(reset = false) {
        const parentId = main.value;
        const matching = options.filter(option => option.dataset.parent === parentId);
        const hasChildren = matching.length > 0;
        const oldValue = reset ? '' : sub.value;

        options.forEach(option => {
            option.hidden = option.dataset.parent !== parentId;
            option.disabled = option.dataset.parent !== parentId;
        });

        subField.hidden = !hasChildren;
        sub.required = hasChildren;
        sub.disabled = !hasChildren;
        emptyOption.textContent = 'Select a subcategory';

        if (hasChildren) {
            sub.value = matching.some(option => option.value === oldValue) ? oldValue : '';
        } else {
            sub.value = '';
        }

        const isClothing = parentId !== '' && parentId === clothingId;
        sizeField.hidden = !isClothing;
        sizeInput.disabled = !isClothing;
        if (!isClothing || reset) {
            sizeInput.value = '';
        }
    }

    main.addEventListener('change', () => updateCategories(true));
    updateCategories();
    document.querySelector('.listing-details__form').addEventListener('submit', () => {
        if (sub.disabled) {
            sub.disabled = false;
            sub.value = main.value;
        }
    });
})();
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
