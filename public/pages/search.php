<?php
require_once __DIR__ . '/../../db_connect.php';

// =========================================
// SEARCH INPUT
// =========================================

$searchQuery = trim($_GET['q'] ?? '');
$searchType = $_GET['search_type'] ?? 'items';
$conditionFilters = $_GET['condition'] ?? [];
if (!is_array($conditionFilters)) {
    $conditionFilters = [];
}
$fulfillmentFilter = $_GET['fulfillment'] ?? '';
$sortOption = $_GET['sort'] ?? 'recent';
$browseAll = ($_GET['browse'] ?? '') === 'all';

$allowedSortOptions = [
    'recent' => 'l.created_at DESC, l.listing_id DESC',
    'oldest' => 'l.created_at ASC, l.listing_id ASC',
    'price_low' => 'l.price ASC, l.listing_id DESC',
    'price_high' => 'l.price DESC, l.listing_id DESC'
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

$fulfillmentSql = '';
if ($fulfillmentFilter !== '') {
    $fulfillmentSql = 'AND l.fulfillment_type = :fulfillment';
}

$allowedFulfillmentTypes = [
    'shipping',
    'meetup'
];
if ($fulfillmentFilter !== '' && !in_array($fulfillmentFilter, $allowedFulfillmentTypes, true)) {
    $fulfillmentFilter = '';
}

$fulfillmentSql = '';
if ($fulfillmentFilter !== '') {
    $fulfillmentSql = 'AND l.fulfillment_type = :fulfillment';
}

$listings = [];
$searchError = false;
$resultCount = 0;

$keywordSql = '';

if ($searchQuery !== '') {
    $keywordSql = "
        AND (
            l.item_name LIKE :item_name
            OR l.item_description LIKE :description
            OR c.category_name LIKE :category
        )
    ";
}


// =========================================
// ITEM SEARCH
// =========================================

if (
    $searchType === 'items'
    && ($searchQuery !== '' || $browseAll)
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
            $conditionSql
            $fulfillmentSql
            

        ORDER BY $orderBySql
    ";

    try {

        $stmt = $pdo->prepare($sql);

        $params = [];

        if ($searchQuery !== '') {

            $searchValue = '%' . $searchQuery . '%';

            $params = [
                'item_name' => $searchValue,
                'description' => $searchValue,
                'category' => $searchValue
            ];
        }

        $params = array_merge(
            $params,
            $conditionParams
        );

        if ($fulfillmentFilter !== '') {
            $params['fulfillment'] = $fulfillmentFilter;
        }


        $stmt->execute($params);

        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultCount = count($listings);

    } catch (PDOException $e) {

        $searchError = true;

        // Log technical details server-side instead of
        // displaying database errors to the user.
        error_log(
            'OmniBuy item search failed: ' . $e->getMessage()
        );
    }
}

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
                        <?= $browseAll ? 'All Listings' : 'Search Results' ?>
                    </h1>

                    <?php if ($browseAll): ?>

                        <p class="search-page__summary">
                            Browse all available marketplace listings.
                        </p>

                    <?php elseif ($searchQuery !== ''): ?>

                        <p class="search-page__summary">
                            Results for
                            <strong>
                                “<?= htmlspecialchars($searchQuery) ?>”
                            </strong>
                        </p>

                    <?php else: ?>

                        <p class="search-page__summary">
                            Search OmniBuy to find marketplace listings.
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
                                        <?= $conditionFilters === 'new' ? 'checked' : '' ?>
                                    >
                                    New
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="open_box"
                                        <?= $conditionFilters === 'open_box' ? 'checked' : '' ?>
                                    >
                                    Open Box
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="like_new"
                                        <?= $conditionFilters === 'like_new' ? 'checked' : '' ?>
                                    >
                                    Like New
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="excellent"
                                        <?= $conditionFilters === 'excellent' ? 'checked' : '' ?>
                                    >
                                    Excellent
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="good"
                                        <?= $conditionFilters === 'good' ? 'checked' : '' ?>
                                    >
                                    Good
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="fair"
                                        <?= $conditionFilters === 'fair' ? 'checked' : '' ?>
                                    >
                                    Fair
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="poor"
                                        <?= $conditionFilters === 'poor' ? 'checked' : '' ?>
                                    >
                                    Poor
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="refurbished"
                                        <?= $conditionFilters === 'refurbished' ? 'checked' : '' ?>
                                    >
                                    Refurbished
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="condition[]"
                                        value="for_parts"
                                        <?= $conditionFilters === 'for_parts' ? 'checked' : '' ?>
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

                        <!-- Category filter -->
                        <div class="filter-group">

                            <label for="category-filter">
                                Category
                            </label>

                            <select
                                id="category-filter"
                                name="category"
                                disabled
                            >
                                <option>
                                    All Categories
                                </option>
                            </select>

                            <p class="filter-group__note">
                                Category filtering will be added later.
                            </p>

                        </div>


                        <!-- Price filter -->
                        <div class="filter-group">

                            <span class="filter-group__label">
                                Price Range
                            </span>

                            <div class="price-filter">

                                <input
                                    type="number"
                                    min="0"
                                    placeholder="Min"
                                    disabled
                                >

                                <span aria-hidden="true">
                                    -
                                </span>

                                <input
                                    type="number"
                                    min="0"
                                    placeholder="Max"
                                    disabled
                                >

                            </div>

                            <p class="filter-group__note">
                                Price filtering will be added later.
                            </p>

                        </div>

                        <button
                            class="button button--primary"
                            type="submit"
                        >
                            Apply Filters
                        </button>
                    </form>
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
                                Listings
                            </h2>

                            <p class="search-results__count">
                                <?= $resultCount ?> results
                            </p>

                        </div>


                        <!-- Sorting placeholder -->
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
                            </select>

                        </form>

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


                    <!-- =========================
                         EMPTY / INITIAL STATE
                         ========================= -->

                    <?php elseif ($searchQuery === '' && !$browseAll): ?>

                        <div class="search-message">

                            <i
                                class="fa-solid fa-magnifying-glass"
                                aria-hidden="true"
                            ></i>

                            <h3>
                                Start searching
                            </h3>

                            <p>
                                Enter an item name or keyword to search
                                the OmniBuy marketplace.
                            </p>

                        </div>


                    <!-- =========================
                         NO RESULTS STATE
                         ========================= -->

                    <?php elseif (empty($listings)): ?>

                        <div class="search-message">

                            <i
                                class="fa-solid fa-box-open"
                                aria-hidden="true"
                            ></i>

                            <h3>
                                No listings found
                            </h3>

                            <p>
                                Try another keyword or adjust your
                                search criteria.
                            </p>

                        </div>


                    <!-- =========================
                         LISTING RESULTS
                         ========================= -->

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

    </script>

</body>
</html>