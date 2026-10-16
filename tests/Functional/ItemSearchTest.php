<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/TestHelpers.php';
require_once __DIR__ . '/../../db_connect.php';

echo "=== Item Search Tests ===" . PHP_EOL;

// =========================================
// Get predictable fixtures
// =========================================

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

if (!$sampleItem) {
    testFail(
        'No active seeded listing available.'
    );

    finishTests();
}

$partialItem = explode(
    ' ',
    (string)$sampleItem
)[0];

// =========================================
// UC-17: Exact Item Search
// =========================================

$response = httpGet(
    '/pages/search.php',
    [
        'q' => $sampleItem,
        'search_type' => 'items',
    ]
);

if (
    str_contains($response, (string)$sampleItem)
    && !str_contains($response, 'No listings found')
) {
    testPass(
        'Item Search returned the seeded listing.'
    );
} else {
    testFail(
        'Item Search did not return the seeded listing.'
    );
}

// =========================================
// UC-17: Partial Item Search
// =========================================

$response = httpGet(
    '/pages/search.php',
    [
        'q' => $partialItem,
        'search_type' => 'items',
    ]
);

assertContains(
    (string)$sampleItem,
    $response,
    'Partial Item Search returned the expected listing.'
);

// =========================================
// UC-17: No-results state
// =========================================

$response = httpGet(
    '/pages/search.php',
    [
        'q' => 'ci-no-such-item-948372',
        'search_type' => 'items',
    ]
);

assertContains(
    'No listings found',
    $response,
    'Item Search displays the no-results state.'
);

// =========================================
// UC-17: Inactive item exclusion
// =========================================

if ($inactiveItem) {
    $response = httpGet(
        '/pages/search.php',
        [
            'q' => $inactiveItem,
            'search_type' => 'items',
        ]
    );

    assertContains(
        'No listings found',
        $response,
        'Item Search excludes inactive listings.'
    );
} else {
    echo "SKIPPED: No inactive seeded listing available."
        . PHP_EOL;
}

finishTests();