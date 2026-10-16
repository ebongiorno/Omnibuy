<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/TestHelpers.php';
require_once __DIR__ . '/../../db_connect.php';

echo "=== Seller Search Tests ===" . PHP_EOL;

// =========================================
// Active seller fixture
// =========================================

$stmt = $pdo->query("
    SELECT
        sp.user_id,
        sp.store_name,
        u.password_hash,
        u.birth_date,
        sp.payout_account_reference

    FROM seller_profiles AS sp

    INNER JOIN users AS u
        ON sp.user_id = u.user_id

    WHERE u.account_status = 'active'

    ORDER BY sp.user_id ASC

    LIMIT 1
");

$activeSeller = $stmt->fetch(PDO::FETCH_ASSOC);

// =========================================
// Inactive seller fixture
// =========================================

$stmt = $pdo->query("
    SELECT sp.store_name

    FROM seller_profiles AS sp

    INNER JOIN users AS u
        ON sp.user_id = u.user_id

    WHERE u.account_status <> 'active'

    ORDER BY sp.user_id ASC

    LIMIT 1
");

$inactiveSeller = $stmt->fetchColumn();

if (!$activeSeller) {
    testFail(
        'No active seeded seller available.'
    );

    finishTests();
}

$sampleSeller = $activeSeller['store_name'];

$partialSeller = substr(
    $sampleSeller,
    0,
    4
);

// =========================================
// UC-18: Exact Seller Search
// =========================================

$response = httpGet(
    '/pages/search.php',
    [
        'q' => $sampleSeller,
        'search_type' => 'seller_profiles',
    ]
);

if (
    str_contains($response, $sampleSeller)
    && !str_contains($response, 'No sellers found')
) {
    testPass(
        'Seller Search returned the seeded seller.'
    );
} else {
    testFail(
        'Seller Search did not return the seeded seller.'
    );
}

// =========================================
// UC-18: Partial Seller Search
// =========================================

$partialResponse = httpGet(
    '/pages/search.php',
    [
        'q' => $partialSeller,
        'search_type' => 'seller_profiles',
    ]
);

assertContains(
    $sampleSeller,
    $partialResponse,
    'Partial Seller Search returned the expected seller.'
);

// =========================================
// UC-18: No-results state
// =========================================

$emptyResponse = httpGet(
    '/pages/search.php',
    [
        'q' => 'ci-no-such-seller-948372',
        'search_type' => 'seller_profiles',
    ]
);

assertContains(
    'No sellers found',
    $emptyResponse,
    'Seller Search displays the no-results state.'
);

// =========================================
// UC-18: Inactive seller exclusion
// =========================================

if ($inactiveSeller) {
    $inactiveResponse = httpGet(
        '/pages/search.php',
        [
            'q' => $inactiveSeller,
            'search_type' => 'seller_profiles',
        ]
    );

    assertContains(
        'No sellers found',
        $inactiveResponse,
        'Seller Search excludes inactive sellers.'
    );
} else {
    echo "SKIPPED: No inactive seeded seller available."
        . PHP_EOL;
}

// =========================================
// UC-18: Privacy regression
// =========================================

$privateValues = [
    $activeSeller['password_hash'],
    $activeSeller['birth_date'],
    $activeSeller['payout_account_reference'],
];

$privacyFailure = false;

foreach ($privateValues as $privateValue) {
    if (
        $privateValue !== null
        && $privateValue !== ''
        && str_contains(
            $response,
            (string)$privateValue
        )
    ) {
        $privacyFailure = true;
    }
}

$privateFieldNames = [
    'password_hash',
    'birth_date',
    'payout_account_reference',
];

foreach ($privateFieldNames as $fieldName) {
    if (stripos($response, $fieldName) !== false) {
        $privacyFailure = true;
    }
}

if ($privacyFailure) {
    testFail(
        'Seller Search exposed restricted seller data.'
    );
} else {
    testPass(
        'Seller Search does not expose restricted seller data.'
    );
}

finishTests();