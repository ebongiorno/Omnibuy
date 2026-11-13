<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db_connect.php';

startSession();

$currentSellerId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($currentSellerId === false || $currentSellerId === null) {
    header('Location: /pages/login.php');
    exit;
}

$listingId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($listingId === false || $listingId === null) {
    http_response_code(400);
    exit('Invalid listing ID.');
}

// Only the owner can edit an active listing.
$stmt = $pdo->prepare(
    "SELECT
        listing_id,
        category_id,
        item_name,
        item_description,
        price,
        `condition`,
        quantity,
        fulfillment_type
     FROM listings
     WHERE listing_id = :listing_id
       AND seller_user_id = :seller_id
       AND listing_status = 'active'"
);

$stmt->execute([
    'listing_id' => $listingId,
    'seller_id' => $currentSellerId,
]);

$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    http_response_code(404);
    exit('Listing not found or unavailable for editing.');
}

// Find the parent category for the existing subcategory.
$categoryStmt = $pdo->prepare(
    "SELECT parent_category_id
     FROM categories
     WHERE category_id = :category_id"
);

$categoryStmt->execute([
    'category_id' => $listing['category_id'],
]);

$parentCategoryId = $categoryStmt->fetchColumn();

$mainCategoryId = $parentCategoryId
    ? (string) $parentCategoryId
    : (string) $listing['category_id'];

// Load the existing photos.
$imageStmt = $pdo->prepare(
    "SELECT image_url
     FROM listing_images
     WHERE listing_id = :listing_id
     ORDER BY display_order ASC, listing_image_id ASC"
);

$imageStmt->execute([
    'listing_id' => $listingId,
]);

$existingPhotos = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare edit-specific session data.
unset($_SESSION['listing']);

$_SESSION['listing_edit'] = [
    'listing_id' => $listingId,
    'seller_user_id' => $currentSellerId,
    'existing_photos' => $existingPhotos,
];

$_SESSION['listing'] = [
    'title' => (string) $listing['item_name'],
    'description' => (string) ($listing['item_description'] ?? ''),
    'price' => (string) $listing['price'],
    'category' => (string) $listing['category_id'],
    'main_category' => $mainCategoryId,
    'condition' => (string) $listing['condition'],
    'quantity' => (string) $listing['quantity'],
    'fulfillment_type' => (string) $listing['fulfillment_type'],
    'photos' => [],
];

// Open the original Create Listing form.
header('Location: /pages/create-listing.php', true, 303);
exit;
