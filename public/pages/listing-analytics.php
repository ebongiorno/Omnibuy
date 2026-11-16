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

$viewsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_views
     FROM listing_views
     WHERE listing_id = :listing_id"
);
$viewsStmt->execute([
    'listing_id' => $listingId
]);
$totalViews = (int) $viewsStmt->fetchColumn();

$uniqueViewersStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT user_id) AS unique_viewers
     FROM listing_views
     WHERE listing_id = :listing_id"
);
$uniqueViewersStmt->execute([
    'listing_id' => $listingId
]);
$uniqueViewers = (int) $uniqueViewersStmt->fetchColumn();

$recentViewsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS views_last_7_days
     FROM listing_views
     WHERE listing_id = :listing_id
       AND viewed_at >= NOW() - INTERVAL 7 DAY"
);
$recentViewsStmt->execute([
    'listing_id' => $listingId
]);
$recentViews = (int) $recentViewsStmt->fetchColumn();

$wishlistStmt = $pdo->prepare(
    "SELECT COUNT(*) AS wishlist_saves
     FROM wishlist_items
     WHERE listing_id = :listing_id"
);
$wishlistStmt->execute([
    'listing_id' => $listingId
]);
$wishlistSaves = (int) $wishlistStmt->fetchColumn();

$conversationsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_conversations
     FROM conversations
     WHERE listing_id = :listing_id"
);
$conversationsStmt->execute([
    'listing_id' => $listingId
]);
$totalConversations = (int) $conversationsStmt->fetchColumn();

$messagesStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_messages
     FROM messages AS m
     INNER JOIN conversations AS c
         ON m.conversation_id = c.conversation_id
     WHERE c.listing_id = :listing_id"
);
$messagesStmt->execute([
    'listing_id' => $listingId
]);
$totalMessages = (int) $messagesStmt->fetchColumn();

// Page configuration
$pageTitle = 'Listing Analytics | OmniBuy';
$activeNav = 'my-listings';
$pageStylesheet = '/assets/css/listing-analytics.css';
include __DIR__ . '/../components/header.php';
?>