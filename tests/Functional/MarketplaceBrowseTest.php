<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/TestHelpers.php';
require_once __DIR__ . '/../../db_connect.php';

echo "=== Marketplace Browse Feed Tests ===" . PHP_EOL;

// =========================================
// Get predictable fixtures
// =========================================

$stmt = $pdo->query("
    SELECT item_name
    FROM listings
    WHERE listing_status = 'active'
    ORDER BY created_at DESC, listing_id DESC
    LIMIT 1
");

$browseItem = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT item_name
    FROM listings
    WHERE listing_status = 'active'
    ORDER BY listing_id ASC
    LIMIT 1
");

$sampleItem = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT item_name
    FROM listings
    WHERE listing_status <> 'active'
    ORDER BY listing_id ASC
    LIMIT 1
");

$inactiveItem = $stmt->fetchColumn();

if (!$browseItem || !$sampleItem) {
    testFail(
        'Required active listing fixtures are missing.'
    );

    finishTests();
}

// =========================================
// UC-16: Browse Feed
// =========================================

$browseResponse = httpGet('/');

assertContains(
    (string)$browseItem,
    $browseResponse,
    'Browse Feed displays an active seeded listing.'
);

// =========================================
// UC-16: Inactive listings excluded
// =========================================

if ($inactiveItem) {
    assertNotContains(
        (string)$inactiveItem,
        $browseResponse,
        'Browse Feed excludes inactive listings.'
    );
} else {
    echo "SKIPPED: No inactive seeded listing available."
        . PHP_EOL;
}

// =========================================
// UC-16: Browse All
// =========================================

$browseAllResponse = httpGet(
    '/pages/search.php',
    [
        'browse' => 'all',
        'search_type' => 'items',
    ]
);

assertContains(
    (string)$sampleItem,
    $browseAllResponse,
    'Browse All displays an active seeded listing.'
);

finishTests();