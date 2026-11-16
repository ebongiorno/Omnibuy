<?php

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../../db_connect.php';
$currentUserId = (int) $_SESSION['user_id'];

$listingId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($listingId === false || $listingId === null) {
    http_response_code(400);
    exit('Invalid listing ID.');
}

$stmt = $pdo->prepare(
    "SELECT
        listing_id,
        item_name,
        price,
        listing_status,
        created_at,
        renewed_at
    FROM listings
    WHERE listing_id = :listing_id
      AND seller_user_id = :user_id
    LIMIT 1"
);
$stmt->execute([
    'listing_id' => $listingId,
    'user_id' => $currentUserId
]);
$listing = $stmt->fetch();
if (!$listing) {
    http_response_code(404);
    exit('Listing not found or unavailable.');
}