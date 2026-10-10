<?php
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
$browseAll =
    $searchType === 'items'
    && $searchQuery === ''
    && ($_GET['browse'] ?? '') === 'all';

$allowedSortOptions = [
    'recent' => 'l.created_at DESC, l.listing_id DESC',
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

$listings = [];
$sellers = [];
$searchError = false;
$resultCount = 0;

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

        $params = array_merge(
            $params,
            $conditionParams
        );

        if ($fulfillmentFilter !== '') {
            $params['fulfillment'] = $fulfillmentFilter;
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
            u.bio

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

                    <?php if ($browseAll): ?>

                        <p class="search-page__summary">
                            Browse all available marketplace listings.
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
                                <?= $resultCount ?>
                                <?= $resultCount === 1 ? 'result' : 'results' ?>
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
                                                <?= htmlspecialchars(
                                                    $seller['store_name']
                                                ) ?>
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

    </script>

</body>
</html>