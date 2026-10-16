<?php
require_once __DIR__ . '/../../includes/auth.php';
startSession();

require_once __DIR__ . '/../../db_connect.php';

// =========================================
// SEARCH INPUT
// =========================================

$searchQuery = trim($_GET['q'] ?? '');
$searchType = $_GET['search_type'] ?? 'items';
$allowedSearchTypes = [
    'items',
    'seller_profiles'
];

if (!in_array($searchType, $allowedSearchTypes, true)) {
    $searchType = 'items';
}

$conditionFilters = $_GET['condition'] ?? [];
if (!is_array($conditionFilters)) {
    $conditionFilters = [];
}
$fulfillmentFilter = $_GET['fulfillment'] ?? '';
$sortOption = $_GET['sort'] ?? 'recent';

// =========================================
// CATEGORY FILTER INPUT
// =========================================

$categoryInput = $_GET['category'] ?? '';
$selectedCategoryId = null;

if (
    is_string($categoryInput)
    && $categoryInput !== ''
    && ctype_digit($categoryInput)
) {
    $selectedCategoryId = (int)$categoryInput;
}

// =========================================
// PRICE RANGE FILTER INPUT / VALIDATION
// =========================================

$minPriceRaw = $_GET['min_price'] ?? '';
$maxPriceRaw = $_GET['max_price'] ?? '';

$minPriceInput = is_string($minPriceRaw)
    ? trim($minPriceRaw)
    : '';

$maxPriceInput = is_string($maxPriceRaw)
    ? trim($maxPriceRaw)
    : '';

$minPrice = null;
$maxPrice = null;
$priceError = '';

$validPricePattern = '/^\d+(?:\.\d{1,2})?$/';

if ($minPriceInput !== '') {
    if (!preg_match($validPricePattern, $minPriceInput)) {
        $priceError =
            'Enter a valid minimum price using up to two decimal places.';
    } else {
        $minPrice = (float)$minPriceInput;
    }
}

if ($maxPriceInput !== '') {
    if (!preg_match($validPricePattern, $maxPriceInput)) {
        if ($priceError === '') {
            $priceError =
                'Enter a valid maximum price using up to two decimal places.';
        }
    } else {
        $maxPrice = (float)$maxPriceInput;
    }
}

if (
    $priceError === ''
    && $minPrice !== null
    && $maxPrice !== null
    && $minPrice > $maxPrice
) {
    $priceError =
        'Minimum price cannot be greater than maximum price.';
}

$priceFilterActive =
    $searchType === 'items'
    && ($minPrice !== null || $maxPrice !== null);

$listings = [];
$sellers = [];
$searchError = false;
$resultCount = 0;

$browseAll =
    $searchType === 'items'
    && $searchQuery === ''
    && ($_GET['browse'] ?? '') === 'all';

$allowedSortOptions = [
    'recent' => 'l.created_at DESC, l.listing_id DESC',
    'oldest' => 'l.created_at ASC, l.listing_id ASC',
    'price_low' => 'l.price ASC, l.listing_id DESC',
    'price_high' => 'l.price DESC, l.listing_id DESC',
    'distance_near' => "
        CASE
            WHEN l.fulfillment_type = 'meetup'
                AND l.latitude IS NOT NULL
                AND l.longitude IS NOT NULL
            THEN 0
            ELSE 1
        END ASC,

        CASE
            WHEN l.fulfillment_type = 'meetup'
                AND l.latitude IS NOT NULL
                AND l.longitude IS NOT NULL
            THEN ST_Distance_Sphere(
                POINT(l.longitude, l.latitude),
                POINT(:sort_longitude, :sort_latitude)
            )
            ELSE NULL
        END ASC,

        l.created_at DESC,
        l.listing_id DESC
    "
];

if (!array_key_exists($sortOption, $allowedSortOptions)) {
    $sortOption = 'recent';
}

$orderBySql = $allowedSortOptions[$sortOption];

$allowedConditions = [
    'new',
    'open_box',
    'like_new',
    'excellent',
    'good',
    'fair',
    'poor',
    'refurbished',
    'for_parts'
];
$conditionFilters = array_values(
    array_intersect($conditionFilters, $allowedConditions)
);

$conditionSql = '';
$conditionParams = [];
if (!empty($conditionFilters)) {
    $conditionPlaceholders = [];
    foreach ($conditionFilters as $index => $condition) {
        $parameterName = 'condition' . $index;
        $conditionPlaceholders[] = ':' . $parameterName;
        $conditionParams[$parameterName] = $condition;
    }
    $conditionSql =
        'AND l.`condition` IN (' .
        implode(', ', $conditionPlaceholders) .
        ')';
}

$allowedFulfillmentTypes = [
    'shipping',
    'meetup'
];
if ($fulfillmentFilter !== '' && !in_array($fulfillmentFilter, $allowedFulfillmentTypes, true)) {
    $fulfillmentFilter = '';
}

// =========================================
// DISTANCE SORTING VALIDATION
// =========================================

// Shipping-only listings cannot be sorted
// by meetup distance.
if (
    $fulfillmentFilter === 'shipping'
    && $sortOption === 'distance_near'
) {
    $sortOption = 'recent';
    $orderBySql = $allowedSortOptions[$sortOption];
}
// =========================================
// MEETUP LOCATION INPUT
// =========================================

$zipInput = $_GET['zip'] ?? '';
$buyerZip = is_string($zipInput)
    ? trim($zipInput)
    : '';

$radiusInput = $_GET['radius'] ?? '';
$radiusMiles = (
    is_string($radiusInput)
    && in_array($radiusInput, ['5', '10', '25', '50'], true)
)
    ? (int)$radiusInput
    : null;
// Shipping-only searches do not use distance.
if ($fulfillmentFilter === 'shipping') {
    $buyerZip = '';
    $radiusMiles = null;
}

$fulfillmentSql = '';
if ($fulfillmentFilter !== '') {
    $fulfillmentSql = 'AND l.fulfillment_type = :fulfillment';
}

// =========================================
// CATEGORY LOOKUP / VALIDATION
// =========================================

$categories = [];
$selectedCategoryName = '';

try {
    $categoryStmt = $pdo->prepare("
        SELECT
            category_id,
            parent_category_id,
            category_name
        FROM categories
        ORDER BY
            COALESCE(parent_category_id, category_id),
            parent_category_id IS NOT NULL,
            category_name ASC
    ");

    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $searchError = true;

    error_log(
        'OmniBuy category lookup failed: '
        . $e->getMessage()
    );
}

// Validate the requested category exists
$validCategoryIds = array_map(
    static fn(array $category): int =>
        (int)$category['category_id'],
    $categories
);

if (
    $selectedCategoryId !== null
    && !in_array($selectedCategoryId, $validCategoryIds, true)
) {
    $selectedCategoryId = null;
}

if ($selectedCategoryId !== null) {
    foreach ($categories as $category) {
        if ((int)$category['category_id'] === $selectedCategoryId) {
            $selectedCategoryName = $category['category_name'];
            break;
        }
    }
}

$categorySql = '';
if ($selectedCategoryId !== null) {
    $categorySql = "
        AND (
            l.category_id = :category_id
            OR c.parent_category_id = :parent_category_id
        )
    ";
}

// =========================================
// PRICE RANGE FILTER SQL
// =========================================

$priceSql = '';

if ($minPrice !== null) {
    $priceSql .= ' AND l.price >= :min_price';
}

if ($maxPrice !== null) {
    $priceSql .= ' AND l.price <= :max_price';
}

// =========================================
// MEETUP DISTANCE LOOKUP
// =========================================

$locationError = '';
$distanceSql = '';
$distanceParams = [];

// Apply distance only when a radius is selected
// and Shipping-only is not selected.
$radiusFilterActive =
    $searchType === 'items'
    && $fulfillmentFilter !== 'shipping'
    && $radiusMiles !== null;

// Distance sorting can work without a radius.
$distanceSortActive =
    $searchType === 'items'
    && $fulfillmentFilter !== 'shipping'
    && $sortOption === 'distance_near';

if ($radiusFilterActive || $distanceSortActive) {
    // Require a valid five-digit ZIP code.
    if (!preg_match('/^[0-9]{5}$/', $buyerZip)) {

        $locationError =
            'Enter a valid 5-digit ZIP code to filter or sort by distance.';

    } else {

        try {

            // Find the buyer's coordinates.
            $zipStmt = $pdo->prepare("
                SELECT latitude, longitude
                FROM zip_code_locations
                WHERE zip_code = :zip
                LIMIT 1
            ");

            $zipStmt->execute([
                'zip' => $buyerZip
            ]);

            $zipLocation = $zipStmt->fetch(PDO::FETCH_ASSOC);

            if (!$zipLocation) {

                $locationError =
                    'ZIP code not found. Please enter another ZIP code.';

            } else {

                $buyerLatitude = $zipLocation['latitude'];
                $buyerLongitude = $zipLocation['longitude'];

            }

        } catch (PDOException $e) {

            $searchError = true;

            error_log(
                'OmniBuy ZIP lookup failed: '
                . $e->getMessage()
            );
        }
    }
}
// =========================================
// BUILD DISTANCE FILTER
// =========================================

if (
    $radiusFilterActive
    && $locationError === ''
    && !$searchError
) {

    $distanceSql = "
        AND (
            l.fulfillment_type = 'shipping'

            OR (
                l.fulfillment_type = 'meetup'

                AND l.latitude IS NOT NULL
                AND l.longitude IS NOT NULL

                AND ST_Distance_Sphere(
                    POINT(l.longitude, l.latitude),
                    POINT(:buyer_longitude, :buyer_latitude)
                ) <= :radius_meters
            )
        )
    ";

    $distanceParams = [
        'buyer_longitude' => $buyerLongitude,
        'buyer_latitude' => $buyerLatitude,
        'radius_meters' => $radiusMiles * 1609.344
    ];
}

$keywordSql = '';

if ($searchQuery !== '') {
    $keywordSql = "
        AND (
            l.item_name LIKE :item_name ESCAPE '!'
            OR l.item_description LIKE :description ESCAPE '!'
            OR c.category_name LIKE :category ESCAPE '!'
        )
    ";
}

// =========================================
// HELPER FUNCTIONS
// =========================================
function executeWithRetry(
    PDO $pdo,
    string $sql,
    array $params = [],
    int $maxAttempts = 2,
    int $delaySeconds = 2
): array {
    $attempt = 0;

    while ($attempt < $maxAttempts) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $attempt++;

            if ($attempt >= $maxAttempts) {
                throw $e;
            }

            sleep($delaySeconds);
        }
    }

    return [];
}

function escapeLike(string $value): string
{
    return str_replace(
        ['!', '%', '_'],
        ['!!', '!%', '!_'],
        $value
    );
}



// =========================================
// ITEM SEARCH
// =========================================

if (
    $searchType === 'items'
    && (
        $searchQuery !== ''
        || $browseAll
        || $selectedCategoryId !== null
        || $priceFilterActive
    )
    && !$searchError
    && $locationError === ''
    && $priceError === ''
) {

    $sql = "
        SELECT
            l.listing_id,
            l.item_name,
            l.item_description,
            l.price,
            l.`condition`,
            l.fulfillment_type,
            l.category_id,
            l.created_at,
            li.image_url,
            c.category_name
        FROM listings AS l

        LEFT JOIN listing_images AS li
            ON l.listing_id = li.listing_id
            AND li.display_order = 1

        LEFT JOIN categories AS c
            ON l.category_id = c.category_id

        WHERE l.listing_status = 'active'
            $keywordSql
            $categorySql
            $priceSql
            $conditionSql
            $fulfillmentSql
            $distanceSql

        ORDER BY $orderBySql
    ";

    try {
        $params = [];

        if ($searchQuery !== '') {
            $escapedSearchQuery = escapeLike($searchQuery);
            $searchValue = '%' . $escapedSearchQuery . '%';

            $params = [
                'item_name' => $searchValue,
                'description' => $searchValue,
                'category' => $searchValue
            ];
        }

        if ($selectedCategoryId !== null) {
            $params['category_id'] = $selectedCategoryId;
            $params['parent_category_id'] = $selectedCategoryId;
        }

        if ($minPrice !== null) {
            $params['min_price'] = $minPrice;
        }

        if ($maxPrice !== null) {
            $params['max_price'] = $maxPrice;
        }

        $params = array_merge(
            $params,
            $conditionParams
        );

        if ($fulfillmentFilter !== '') {
            $params['fulfillment'] = $fulfillmentFilter;
        }
        // Add buyer coordinates and radius when needed.
        $params = array_merge(
            $params,
            $distanceParams
        );

        // Add coordinates for Near to Far sorting.
        if ($distanceSortActive) {

            $params['sort_longitude'] = $buyerLongitude;
            $params['sort_latitude'] = $buyerLatitude;

        }

        $listings = executeWithRetry(
            $pdo,
            $sql,
            $params
        );

        $resultCount = count($listings);

    } catch (PDOException $e) {
        $searchError = true;

        error_log(
            'OmniBuy item search failed after retry: '
            . $e->getMessage()
        );
    }
}


// =========================================
// SELLER PROFILE SEARCH
// =========================================

if (
    $searchType === 'seller_profiles'
    && $searchQuery !== ''
) {

    $sql = "
        SELECT
            sp.user_id,
            sp.store_name,
            sp.is_verified,

            u.username,
            u.first_name,

            CASE
                WHEN u.is_last_name_hidden = FALSE
                THEN u.last_name
                ELSE NULL
            END AS last_name,

            u.profile_image_url,

            CASE
                WHEN u.is_bio_hidden = FALSE
                THEN u.bio
                ELSE NULL
            END AS bio

        FROM seller_profiles AS sp

        INNER JOIN users AS u
            ON sp.user_id = u.user_id

        WHERE u.account_status = 'active'
            AND (
                sp.store_name LIKE :store_name ESCAPE '!'
                OR u.username LIKE :username ESCAPE '!'
                OR u.first_name LIKE :first_name ESCAPE '!'

                OR (
                    u.is_last_name_hidden = FALSE
                    AND u.last_name LIKE :last_name ESCAPE '!'
                )

                OR (
                    u.is_last_name_hidden = FALSE
                    AND CONCAT(
                        u.first_name,
                        ' ',
                        u.last_name
                    ) LIKE :full_name ESCAPE '!'
                )
            )

        ORDER BY
            sp.store_name ASC,
            u.username ASC
    ";

    try {
        $escapedSearchQuery = escapeLike($searchQuery);
        $searchValue = '%' . $escapedSearchQuery . '%';

        $params = [
            'store_name' => $searchValue,
            'username' => $searchValue,
            'first_name' => $searchValue,
            'last_name' => $searchValue,
            'full_name' => $searchValue
        ];

        $sellers = executeWithRetry(
            $pdo,
            $sql,
            $params
        );

        $resultCount = count($sellers);

    } catch (PDOException $e) {
        $searchError = true;

        error_log(
            'OmniBuy seller search failed after retry: '
            . $e->getMessage()
        );
    }
}

$hasNoResults =
    $searchType === 'seller_profiles'
        ? empty($sellers)
        : empty($listings);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <!-- Shared OmniBuy styles -->
    <link rel="stylesheet" href="/assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="/assets/css/shared-navigation.css">
    <link rel="stylesheet" href="/assets/css/listing-card.css">
    <link rel="stylesheet" href="/assets/css/seller-card.css">

    <!-- Search page styles -->
    <link rel="stylesheet" href="/assets/css/search.css">

    <!-- Font Awesome -->
    <script
        src="https://kit.fontawesome.com/9e304416f8.js"
        crossorigin="anonymous"
    ></script>

    <title>Search | OmniBuy</title>
</head>


<body>

    <!-- =========================================
         ACCESSIBILITY
         ========================================= -->

    <a class="skip-link" href="#main-content">
        Skip to main content
    </a>

    <?php
        $header = __DIR__ . '/../components/header.php';
        include $header;
    ?>

    <!-- =========================================
         SEARCH RESULTS
         ========================================= -->

    <main
        class="page-main search-page"
        id="main-content"
    >

        <div class="shell">


            <!-- =================================
                 SEARCH PAGE HEADING
                 ================================= -->

            <section class="search-page__header">

                <div>

                    <p class="search-page__eyebrow">
                        Marketplace Search
                    </p>

                    <h1>
                        <?php if ($browseAll): ?>
                            All Listings
                        <?php elseif ($searchType === 'seller_profiles'): ?>
                            Seller Search Results
                        <?php else: ?>
                            Search Results
                        <?php endif; ?>
                    </h1>

                    <?php if ($browseAll && $selectedCategoryId !== null): ?>

                        <p class="search-page__summary">
                            Browsing available listings in
                            <strong>
                                <?= htmlspecialchars(
                                    $selectedCategoryName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>.
                        </p>

                    <?php elseif ($browseAll): ?>

                        <p class="search-page__summary">
                            Browse all available marketplace listings.
                        </p>

                    <?php elseif (
                        $selectedCategoryId !== null
                        && $searchQuery === ''
                    ): ?>

                        <p class="search-page__summary">
                            Browsing listings in
                            <strong>
                                <?= htmlspecialchars(
                                    $selectedCategoryName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>.
                        </p>

                    <?php elseif ($searchQuery !== ''): ?>

                        <p class="search-page__summary">

                            <?php if ($searchType === 'seller_profiles'): ?>
                                Seller profiles matching
                            <?php else: ?>
                                Results for
                            <?php endif; ?>

                            <strong>
                                “<?= htmlspecialchars($searchQuery) ?>”
                            </strong>

                        </p>

                    <?php else: ?>

                        <p class="search-page__summary">

                            <?php if ($searchType === 'seller_profiles'): ?>
                                Search OmniBuy to find marketplace sellers.
                            <?php else: ?>
                                Search OmniBuy to find marketplace listings.
                            <?php endif; ?>

                        </p>

                    <?php endif; ?>

                </div>

            </section>


            <!-- =================================
                 SEARCH BODY
                 ================================= -->

            <div class="search-layout">


                <!-- =============================
                     FILTER SIDEBAR
                     ============================= -->

                <aside
                    class="search-filters"
                    aria-labelledby="filter-heading"
                >
                    <?php if ($searchType === 'seller_profiles'): ?>

                        <div class="search-filters__header">
                            <h2 id="filter-heading">
                                Seller Search
                            </h2>
                        </div>

                        <p class="filter-group__note">
                            Listing filters do not apply when searching
                            seller profiles.
                        </p>

                    <?php else: ?>
                        <div class="search-filters__header">

                            <h2 id="filter-heading">
                                Filters
                            </h2>

                            <button
                                class="search-filters__clear"
                                type="button"
                            >
                                Clear
                            </button>

                        </div>
                    <form
                        class="search-filters__form"
                        action="search.php"
                        method="get"
                    >
                        <input
                            type="hidden"
                            name="sort"
                            value="<?= htmlspecialchars($sortOption) ?>"
                        >
                    
                            <?php if ($browseAll): ?>
                                <input
                                    type="hidden"
                                    name="browse"
                                    value="all"
                                >
                            <?php endif; ?>

                            <!-- Preserve the existing search -->
                            <input
                                type="hidden"
                                name="q"
                                value="<?= htmlspecialchars($searchQuery) ?>"
                            >

                            <input
                                type="hidden"
                                name="search_type"
                                value="<?= htmlspecialchars($searchType) ?>"
                            >
                            
                            <!-- Condition filter -->
                            <div class="filter-group">
                                <fieldset class="filter-options">
                                    <legend>
                                        Condition
                                    </legend>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="new"
                                            <?= in_array('new', $conditionFilters, true) ? 'checked' : '' ?>
                                        >
                                        New
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="open_box"
                                            <?= in_array('open_box', $conditionFilters, true) ? 'checked' : '' ?>
                                        >
                                        Open Box
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="like_new"
                                            <?= in_array('like_new', $conditionFilters, true)  ? 'checked' : '' ?>
                                        >
                                        Like New
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="excellent"
                                            <?= in_array('excellent', $conditionFilters, true)  ? 'checked' : '' ?>
                                        >
                                        Excellent
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="good"
                                            <?= in_array('good', $conditionFilters, true) ? 'checked' : '' ?>
                                        >
                                        Good
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="fair"
                                            <?= in_array('fair', $conditionFilters, true) ? 'checked' : '' ?>
                                        >
                                        Fair
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="poor"
                                            <?= in_array('poor', $conditionFilters, true) ? 'checked' : '' ?>
                                        >
                                        Poor
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="refurbished"
                                            <?= in_array('refurbished', $conditionFilters, true) ? 'checked' : '' ?>
                                        >
                                        Refurbished
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="condition[]"
                                            value="for_parts"
                                            <?= in_array('for_parts', $conditionFilters, true) ? 'checked' : '' ?>
                                        >
                                        For Parts
                                    </label>

                                </fieldset>
                            </div>

                            <!-- Delivery filter -->
                            <div class="filter-group">
                                <fieldset class="filter-options">
                                    <legend>
                                        Delivery Method
                                    </legend>

                                    <label>
                                        <input
                                            type="radio"
                                            name="fulfillment"
                                            value="shipping"
                                            <?= $fulfillmentFilter === 'shipping' ? 'checked' : '' ?>
                                        >
                                        Shipping
                                    </label>

                                    <label>
                                        <input
                                            type="radio"
                                            name="fulfillment"
                                            value="meetup"
                                            <?= $fulfillmentFilter === 'meetup' ? 'checked' : '' ?>
                                        >
                                        Meetup
                                    </label>

                                </fieldset>
                            </div>

                            <!-- Meetup distance filter -->
                            <div
                                class="filter-group"
                                id="meetup-distance-filter"
                                <?= $fulfillmentFilter === 'shipping' ? 'hidden' : '' ?>
                            >
                                <fieldset class="filter-options">

                                    <legend>
                                        Meetup Distance
                                    </legend>

                                    <!-- Buyer ZIP code -->
                                    <label for="buyer-zip">
                                        Your ZIP Code
                                    </label>

                                    <input
                                        type="text"
                                        id="buyer-zip"
                                        name="zip"
                                        inputmode="numeric"
                                        pattern="[0-9]{5}"
                                        maxlength="5"
                                        placeholder="Enter ZIP code"
                                        value="<?= htmlspecialchars($buyerZip, ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <!-- Distance selection -->
                                    <label for="radius-filter">
                                        Distance Range
                                    </label>

                                    <select
                                        id="radius-filter"
                                        name="radius"
                                    >
                                        <option value="">
                                            Any Distance
                                        </option>

                                        <?php foreach ([5, 10, 25, 50] as $distance): ?>
                                            <option
                                                value="<?= $distance ?>"
                                                <?= $radiusMiles === $distance
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Within <?= $distance ?> miles
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                    <p class="filter-group__note">
                                        Distance filtering applies only to
                                        meetup listings.
                                    </p>

                                </fieldset>
                            </div>

                            <!-- Category filter -->
                            <div class="filter-group">

                                <label for="category-filter">
                                    Category
                                </label>

                                <select
                                    id="category-filter"
                                    name="category"
                                >
                                    <option value="">
                                        All Categories
                                    </option>

                                    <?php foreach ($categories as $category): ?>
                                        <option
                                            value="<?= (int)$category['category_id'] ?>"
                                            <?= $selectedCategoryId ===
                                                (int)$category['category_id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $category['parent_category_id'] !== null
                                                ? '— '
                                                : '' ?>
                                            <?= htmlspecialchars(
                                                $category['category_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>

                                <p class="filter-group__note">
                                    Select a category to narrow marketplace listings.
                                </p>

                            </div>


                            <!-- Price filter -->
                            <div class="filter-group">

                                <span class="filter-group__label">
                                    Price Range
                                </span>

                                <div class="price-filter">

                                    <label for="min-price" class="sr-only">
                                        Minimum price
                                    </label>

                                    <input
                                        type="number"
                                        id="min-price"
                                        name="min_price"
                                        min="0"
                                        step="0.01"
                                        placeholder="Min"
                                        value="<?= htmlspecialchars(
                                            $minPriceInput,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <span aria-hidden="true">
                                        -
                                    </span>

                                    <label for="max-price" class="sr-only">
                                        Maximum price
                                    </label>

                                    <input
                                        type="number"
                                        id="max-price"
                                        name="max_price"
                                        min="0"
                                        step="0.01"
                                        placeholder="Max"
                                        value="<?= htmlspecialchars(
                                            $maxPriceInput,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                </div>

                                <p class="filter-group__note">
                                    Enter a minimum price, maximum price, or both.
                                </p>

                            </div>

                            <button
                                class="button button--primary"
                                type="submit"
                            >
                                Apply Filters
                            </button>
                        </form>

                    <?php endif; ?>
                </aside>


                <!-- =============================
                    RESULTS AREA
                    ============================= -->

                <section
                    class="search-results"
                    aria-labelledby="results-heading"
                >

                    <div class="search-results__toolbar">

                        <div>

                            <h2 id="results-heading">
                                <?= $searchType === 'seller_profiles'
                                    ? 'Seller Profiles'
                                    : 'Listings' ?>
                            </h2>

                            <p class="search-results__count">
                                <?= $resultCount . ' ' . ($resultCount === 1 ? 'result' : 'results') ?>
                            </p>

                        </div>

                        <?php if ($searchType === 'items'): ?>
                            <form
                                class="search-results__sort"
                                action="search.php"
                                method="get"
                            >

                                <!-- Preserve current search -->
                                <input
                                    type="hidden"
                                    name="q"
                                    value="<?= htmlspecialchars($searchQuery) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="search_type"
                                    value="<?= htmlspecialchars($searchType) ?>"
                                >

                                <!-- Preserve active filters -->
                                <?php foreach ($conditionFilters as $condition): ?>
                                    <input
                                        type="hidden"
                                        name="condition[]"
                                        value="<?= htmlspecialchars($condition) ?>"
                                    >
                                <?php endforeach; ?>

                                <?php if ($fulfillmentFilter !== ''): ?>
                                    <input
                                        type="hidden"
                                        name="fulfillment"
                                        value="<?= htmlspecialchars($fulfillmentFilter) ?>"
                                    >
                                <?php endif; ?>

                                <?php if ($selectedCategoryId !== null): ?>
                                    <input
                                        type="hidden"
                                        name="category"
                                        value="<?= $selectedCategoryId ?>"
                                    >
                                <?php endif; ?>

                                <?php if ($minPrice !== null): ?>
                                    <input
                                        type="hidden"
                                        name="min_price"
                                        value="<?= htmlspecialchars(
                                            $minPriceInput,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                <?php endif; ?>

                                <?php if ($maxPrice !== null): ?>
                                    <input
                                        type="hidden"
                                        name="max_price"
                                        value="<?= htmlspecialchars(
                                            $maxPriceInput,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                <?php endif; ?>

                                <!-- Preserve meetup distance settings -->
                                <?php if ($fulfillmentFilter !== 'shipping'): ?>

                                    <input
                                        type="hidden"
                                        name="zip"
                                        value="<?= htmlspecialchars($buyerZip, ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <?php if ($radiusMiles !== null): ?>
                                        <input
                                            type="hidden"
                                            name="radius"
                                            value="<?= $radiusMiles ?>"
                                        >
                                    <?php endif; ?>

                                <?php endif; ?>
                                    
                                <!-- Preserves browse mode -->
                                <?php if ($browseAll): ?>
                                    <input
                                        type="hidden"
                                        name="browse"
                                        value="all"
                                    >
                                <?php endif; ?>

                                <label for="results-sort">
                                    Sort by
                                </label>
                            
                                <select
                                    id="results-sort"
                                    name="sort"
                                    onchange="this.form.submit()"
                                >
                                    <option
                                        value="recent"
                                        <?= $sortOption === 'recent' ? 'selected' : '' ?>
                                    >
                                        Newest
                                    </option>

                                    <option
                                    value="oldest"
                                    <?= $sortOption === 'oldest' ? 'selected' : '' ?>
                                    >
                                        Oldest
                                    </option>

                                    <option
                                        value="price_low"
                                        <?= $sortOption === 'price_low' ? 'selected' : '' ?>
                                    >
                                        Price: Low to High
                                    </option>

                                    <option
                                        value="price_high"
                                        <?= $sortOption === 'price_high' ? 'selected' : '' ?>
                                    >
                                        Price: High to Low
                                    </option>

                                    <?php if ($fulfillmentFilter !== 'shipping'): ?>
                                        <option
                                            value="distance_near"
                                            <?= $sortOption === 'distance_near' ? 'selected' : '' ?>
                                        >
                                            Distance: Near to Far (Meetup First)
                                        </option>
                                    <?php endif; ?>
                                </select>

                            </form>
                        <?php endif; ?>
                    </div>


                    <!-- =========================
                         ERROR STATE
                         ========================= -->

                    <?php if ($searchError): ?>

                        <div
                            class="search-message search-message--error"
                            role="alert"
                        >

                            <i
                                class="fa-solid fa-circle-exclamation"
                                aria-hidden="true"
                            ></i>

                            <h3>
                                Unable to retrieve search results
                            </h3>

                            <p>
                                Please try your search again later.
                            </p>

                        </div>

                    <?php elseif ($locationError !== ''): ?>

                        <div
                            class="search-message search-message--error"
                            role="alert"
                        >

                            <i
                                class="fa-solid fa-circle-exclamation"
                                aria-hidden="true"
                            ></i>

                            <h3>
                                Invalid Meetup Location
                            </h3>

                            <p>
                                <?= htmlspecialchars(
                                    $locationError,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                        </div>

                    <?php elseif ($priceError !== ''): ?>

                        <div
                            class="search-message search-message--error"
                            role="alert"
                        >

                            <i
                                class="fa-solid fa-circle-exclamation"
                                aria-hidden="true"
                            ></i>

                            <h3>
                                Invalid Price Range
                            </h3>

                            <p>
                                <?= htmlspecialchars(
                                    $priceError,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                        </div>

                    <!-- =========================
                         EMPTY / INITIAL STATE
                         ========================= -->

                    <?php elseif (
                        $searchQuery === ''
                        && !$browseAll
                        && $selectedCategoryId === null
                        && !$priceFilterActive
                    ): ?>

                        <div class="search-message">

                            <i
                                class="fa-solid fa-magnifying-glass"
                                aria-hidden="true"
                            ></i>

                            <h3>
                                Start searching
                            </h3>

                            <p>
                                <?php if ($searchType === 'seller_profiles'): ?>
                                    Enter a seller name, store name, or username
                                    to search OmniBuy sellers.
                                <?php else: ?>
                                    Enter an item name or keyword to search
                                    the OmniBuy marketplace.
                                <?php endif; ?>
                            </p>
                        </div>


                    <!-- =========================
                         NO RESULTS STATE
                         ========================= -->

                    <?php elseif ($hasNoResults): ?>

                        <div class="search-message">

                            <i
                                class="fa-solid fa-box-open"
                                aria-hidden="true"
                            ></i>

                            <h3>
                                <?= $searchType === 'seller_profiles'
                                    ? 'No sellers found'
                                    : 'No listings found' ?>
                            </h3>

                            <p>
                                <?php if ($searchType === 'seller_profiles'): ?>
                                    Try another seller name, store name, or username.
                                <?php else: ?>
                                    Try another keyword or adjust your search criteria.
                                <?php endif; ?>
                            </p>

                        </div>


                    <!-- =========================
                         LISTING RESULTS
                         ========================= -->

                    <?php else: ?>
                        <?php if ($searchType === 'seller_profiles'): ?>
                            <div class="seller-grid">
                                <?php foreach ($sellers as $seller): ?>
                                    <article class="seller-card">
                                        <div class="seller-card__image">
                                            <?php if (!empty($seller['profile_image_url'])): ?>
                                                <img
                                                    src="<?= htmlspecialchars(
                                                        $seller['profile_image_url']
                                                        ) ?>"
                                                    alt=""
                                                    >
                                            <?php else: ?>
                                                <i
                                                    class="fa-solid fa-user"
                                                    aria-hidden="true"
                                                ></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="seller-card__content">
                                            <h3 class="seller-card__store">
                                                <a
                                                    class="seller-card__link"
                                                    href="/pages/profile.php?id=<?= (int) $seller['user_id'] ?>"
                                                ><?= htmlspecialchars(
                                                    $seller['store_name']
                                                ) ?></a>
                                                <?php if ($seller['is_verified']): ?>
                                                    <i
                                                        class="fa-solid fa-circle-check"
                                                        aria-label="Verified seller"
                                                    ></i>
                                                <?php endif; ?>
                                            </h3>

                                            <p class="seller-card__username">
                                                @<?= htmlspecialchars(
                                                    $seller['username']
                                                ) ?>
                                            </p>

                                            <p class="seller-card__name">
                                                <?= htmlspecialchars(
                                                    $seller['first_name']
                                                ) ?>

                                                <?php if (
                                                    !empty($seller['last_name'])
                                                ): ?>
                                                    <?= htmlspecialchars(
                                                        $seller['last_name']
                                                    ) ?>
                                                <?php endif; ?>
                                            </p>

                                            <?php if (!empty($seller['bio'])): ?>
                                                <p class="seller-card__bio">
                                                    <?= htmlspecialchars(
                                                        $seller['bio']
                                                    ) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                        <?php else: ?>
                            <div class="listing-grid">
                                <?php foreach ($listings as $listing): ?>
                                    <?php
                                    include __DIR__
                                        . '/../components/listing-card-component.php';
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </section>

            </div>

        </div>

    </main>


    <!-- =========================================
         MOBILE NAVIGATION
         ========================================= -->

    <script>

        const navToggle =
            document.querySelector('.mobile-nav-toggle');

        const primaryNav =
            document.querySelector('.primary-nav');

        navToggle?.addEventListener('click', () => {

            const isOpen =
                primaryNav.classList.toggle('is-open');

            navToggle.setAttribute(
                'aria-expanded',
                String(isOpen)
            );

        });
        
        // =========================================
        // MEETUP DISTANCE FILTER VISIBILITY
        // =========================================

        const deliveryOptions = document.querySelectorAll(
            'input[name="fulfillment"]'
        );

        const meetupDistanceFilter = document.getElementById(
            'meetup-distance-filter'
        );

        function updateMeetupDistanceVisibility() {

            const selectedDelivery = document.querySelector(
                'input[name="fulfillment"]:checked'
            );

            // No selection means both shipping and meetup.
            const isShipping =
                selectedDelivery?.value === 'shipping';

            if (!meetupDistanceFilter) {
                return;
            }

            // Hide only when Shipping is selected.
            meetupDistanceFilter.hidden = isShipping;

            // Disable location controls for shipping-only searches.
            meetupDistanceFilter
                .querySelectorAll('input, select')
                .forEach(control => {
                    control.disabled = isShipping;
                });
        }

        // Update immediately when the delivery selection changes.
        deliveryOptions.forEach(option => {
            option.addEventListener(
                'change',
                updateMeetupDistanceVisibility
            );
        });

        // Set the correct state when the page loads.
        updateMeetupDistanceVisibility();

        // =========================================
        // CLEAR SEARCH FILTERS
        // =========================================

        const clearFiltersButton = document.querySelector(
            '.search-filters__clear'
        );

        const filterForm = document.querySelector(
            '.search-filters__form'
        );

        clearFiltersButton?.addEventListener('click', () => {

            if (!filterForm) {
                return;
            }

            // Clear condition and delivery selections.
            filterForm
                .querySelectorAll(
                    'input[type="checkbox"], input[type="radio"]'
                )
                .forEach(input => {
                    input.checked = false;
                });

            // Clear ZIP code.
            const zipField = filterForm.querySelector(
                'input[name="zip"]'
            );

            if (zipField) {
                zipField.value = '';
            }

            // Clear radius.
            const radiusField = filterForm.querySelector(
                'select[name="radius"]'
            );

            if (radiusField) {
                radiusField.value = '';
            }

            // Clear category filter.
            const categoryField = filterForm.querySelector(
                'select[name="category"]'
            );

            if (categoryField) {
                categoryField.value = '';
            }

            // Clear price range.
            const minPriceField = filterForm.querySelector(
                'input[name="min_price"]'
            );

            const maxPriceField = filterForm.querySelector(
                'input[name="max_price"]'
            );

            if (minPriceField) {
                minPriceField.value = '';
            }

            if (maxPriceField) {
                maxPriceField.value = '';
            }

            // Restore default filter visibility.
            updateMeetupDistanceVisibility();

            // Reset distance sorting if location is cleared.
            const sortField = filterForm.querySelector(
                'input[name="sort"]'
            );

            if (sortField?.value === 'distance_near') {
                sortField.value = 'recent';
            }

            // Apply the cleared filters.
            filterForm.requestSubmit();
        });
    </script>

</body>
</html>