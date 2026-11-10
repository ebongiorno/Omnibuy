<?php
/**
 * Reusable category and subcategory browsing page.
 * Place in public/pages/category.php (alongside search.php).
 * Requires MySQL 8+, public/components/header.php and the existing listing-card component.
 */
require_once __DIR__ . '/../../includes/auth.php';
startSession();
require_once __DIR__ . '/../../db_connect.php';

function categoryEscape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function categoryUrl(array $params): string {
    return '/pages/category.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
function categorySlug(string $name): string {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    return trim((string) $name, '-');
}
function categoryParams(array $changes = []): array {
    $keep = [];
    foreach (['id', 'category', 'min_price', 'max_price', 'sort', 'condition', 'fulfillment'] as $key) {
        if (isset($_GET[$key])) $keep[$key] = $_GET[$key];
    }
    return array_merge($keep, $changes);
}

$aliases = [
    'home-garden' => 'home-furniture',
    'home-and-garden' => 'home-furniture',
    'home-and-furniture' => 'home-furniture',
    'clothing-shoes-accessories' => 'clothing',
    'clothing-shoes-and-accessories' => 'clothing',
];
$requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$requestedSlug = $_GET['category'] ?? null;
if (!is_string($requestedSlug)) $requestedSlug = null;
$requestedSlug = $requestedSlug === null ? null : strtolower(trim($requestedSlug));
$requestedSlug = $requestedSlug !== null ? ($aliases[$requestedSlug] ?? $requestedSlug) : null;
if ($requestedSlug !== null && !preg_match('/^[a-z0-9-]{1,100}$/', $requestedSlug)) $requestedSlug = '';

$category = null;
$children = [];
$listings = [];
$total = 0;
$error = false;
$notFound = false;
$perPage = 8;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$page = min($page, 100000);
$sortOptions = [
    'recent' => 'l.created_at DESC, l.listing_id DESC',
    'price_low' => 'l.price ASC, l.listing_id ASC',
    'price_high' => 'l.price DESC, l.listing_id DESC',
];
$sort = $_GET['sort'] ?? 'recent';
if (!is_string($sort) || !isset($sortOptions[$sort])) $sort = 'recent';
$conditionsAllowed = ['new', 'open_box', 'like_new', 'excellent', 'good', 'fair', 'poor', 'refurbished', 'for_parts'];
$fulfillmentsAllowed = ['shipping', 'meetup'];
$conditions = $_GET['condition'] ?? [];
$fulfillments = $_GET['fulfillment'] ?? [];
$conditions = is_array($conditions) ? array_values(array_unique(array_intersect($conditionsAllowed, array_filter($conditions, 'is_string')))) : [];
$fulfillments = is_array($fulfillments) ? array_values(array_unique(array_intersect($fulfillmentsAllowed, array_filter($fulfillments, 'is_string')))) : [];
$minPriceRaw = $_GET['min_price'] ?? '';
$maxPriceRaw = $_GET['max_price'] ?? '';
$minPriceRaw = is_string($minPriceRaw) ? trim($minPriceRaw) : '';
$maxPriceRaw = is_string($maxPriceRaw) ? trim($maxPriceRaw) : '';
$priceError = null;
$validPrice = static function (string $value): bool {
    return $value === '' || (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $value) && (float)$value >= 0);
};
if (!$validPrice($minPriceRaw) || !$validPrice($maxPriceRaw) ||
    ($minPriceRaw !== '' && $maxPriceRaw !== '' && (float)$minPriceRaw > (float)$maxPriceRaw)) {
    $priceError = 'Enter valid prices (up to two decimal places), with minimum no greater than maximum.';
}

try {
    if ($requestedId) {
        $stmt = $pdo->prepare('SELECT category_id, parent_category_id, category_name, description, image_url FROM categories WHERE category_id = :id');
        $stmt->execute(['id' => $requestedId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif ($requestedSlug) {
        // Resolve IDs for legacy homepage categories, but only if they still name the expected root.
        // Prefer resolving by actual database category name so future ID changes don't break links.
        $roots = $pdo->query('SELECT category_id, parent_category_id, category_name, description, image_url FROM categories WHERE parent_category_id IS NULL')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roots as $root) {
            $dbSlug = categorySlug((string)$root['category_name']);
            $normalized = $aliases[$dbSlug] ?? $dbSlug;
            if ($normalized === $requestedSlug ||
                ($requestedSlug === 'clothing' && str_starts_with($dbSlug, 'clothing')) ||
                ($requestedSlug === 'home-furniture' && (str_contains($dbSlug, 'home') && (str_contains($dbSlug, 'furniture') || str_contains($dbSlug, 'garden'))))) {
                $category = $root;
                break;
            }
        }

    }

    if (!$category) {
        $notFound = true;
        http_response_code(404);
    } else {
        $categoryId = (int)$category['category_id'];
        $childrenStmt = $pdo->prepare('SELECT category_id, category_name, description, image_url FROM categories WHERE parent_category_id = :id ORDER BY category_name');
        $childrenStmt->execute(['id' => $categoryId]);
        $children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

        // MySQL 8 recursive CTE includes listings in children, grandchildren, etc.
        // UNION DISTINCT prevents loops if malformed parent relationships exist.
        $categoryCTE = 'WITH RECURSIVE category_tree (category_id) AS (
            SELECT category_id FROM categories WHERE category_id = :root_id
            UNION DISTINCT
            SELECT c.category_id FROM categories c
            INNER JOIN category_tree ct ON c.parent_category_id = ct.category_id
        )';
        $where = ["l.listing_status = 'active'", "u.account_status = 'active'", 'l.category_id IN (SELECT category_id FROM category_tree)'];
        $params = ['root_id' => $categoryId];
        if ($priceError === null) {
            if ($minPriceRaw !== '') { $where[] = 'l.price >= :min_price'; $params['min_price'] = (float)$minPriceRaw; }
            if ($maxPriceRaw !== '') { $where[] = 'l.price <= :max_price'; $params['max_price'] = (float)$maxPriceRaw; }
        }
        foreach (['condition' => $conditions, 'fulfillment_type' => $fulfillments] as $field => $values) {
            if (!$values) continue;
            $placeholders = [];
            foreach ($values as $i => $value) {
                $param = $field . '_' . $i;
                $placeholders[] = ':' . $param;
                $params[$param] = $value;
            }
            $where[] = 'l.`' . $field . '` IN (' . implode(', ', $placeholders) . ')';
        }
        $whereSql = implode(' AND ', $where);
        if ($priceError === null) {
            $countSQL = $categoryCTE . ' SELECT COUNT(*) FROM listings l INNER JOIN users u ON u.user_id = l.seller_user_id WHERE ' . $whereSql;
            $countStmt = $pdo->prepare($countSQL);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $totalPages = max(1, (int)ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;
            $sql = $categoryCTE . ' SELECT l.listing_id, l.`condition`, l.item_name, l.price, l.category_id,
                l.fulfillment_type, li.image_url, c.category_name
                FROM listings l
                INNER JOIN users u ON u.user_id = l.seller_user_id
                LEFT JOIN listing_images li ON li.listing_id = l.listing_id AND li.display_order = 1
                LEFT JOIN categories c ON c.category_id = l.category_id
                WHERE ' . $whereSql . ' ORDER BY ' . $sortOptions[$sort] . ' LIMIT :limit_rows OFFSET :offset_rows';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
            $stmt->bindValue(':limit_rows', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset_rows', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log('Category page database query failed: ' . $e->getMessage());
    $error = true;
    http_response_code(503);
}

if ($category) {
    $pageTitle = (string)$category['category_name'] . ' | OmniBuy';
    $activeName = categorySlug((string)$category['category_name']);
    $activeNav = $aliases[$activeName] ?? $activeName;
    if ($activeNav === 'home-furniture') $activeNav = 'home-furniture';
    if (str_starts_with($activeNav, 'clothing')) $activeNav = 'clothing';
    if ($category['parent_category_id'] !== null) {
        // Highlight only a top-level section in navigation (if known).
        $activeNav = '';
    }
} else {
    $pageTitle = 'Category not found | OmniBuy';
    $activeNav = '';
}
$pageStylesheet = '/assets/css/category.css?v=20261109-banner-fix';
$extraStylesheets = ['/assets/css/listing-card.css'];
include __DIR__ . '/../components/header.php';

$bannerImages = [
    'electronics' => '/assets/images/category/category-parent-electronics.jpg',
    'home-furniture' => '/assets/images/category/category-parent-home-furniture.jpg',
    'clothing' => '/assets/images/category/category-parent-clothing.jpg',
    'books' => '/assets/images/category/category-parent-books.jpg',
    'collectibles' => '/assets/images/category/category-parent-collectibles.jpg',
    'audio' => '/assets/images/category/subcategory-audio.jpg',
    'computers' => '/assets/images/category/subcategory-computers.jpg',
    'furniture' => '/assets/images/category/subcategory-furniture.jpg',
    'kitchen' => '/assets/images/category/subcategory-kitchen.jpg',
    'mens-clothing' => '/assets/images/category/subcategory-mens-clothing.jpg',
    'womens-clothing' => '/assets/images/category/subcategory-womens-clothing.jpg',
    'trading-cards' => '/assets/images/category/subcategory-trading-cards.jpg',
    'action-figures' => '/assets/images/category/subcategory-action-figures.jpg'
];
$heroSlug = $category ? ( $aliases[categorySlug((string)$category['category_name'])] ?? categorySlug((string)$category['category_name'])) : '';
$heroImage = $category ? ($category['image_url'] ?: ($bannerImages[$heroSlug] ?? '/assets/images/homepage-image.jpg')) : '';

// Font Awesome icons for known subcategories. Add new slugs here as the
// category table grows. Unknown categories use their image or a tag icon.
$subcategoryIconClasses = [
    'audio' => 'fa-headphones',
    'computers' => 'fa-laptop',
    'computers-laptops' => 'fa-laptop',
    'phones-accessories' => 'fa-mobile-screen-button',
    'gaming' => 'fa-gamepad',
    'cameras-photography' => 'fa-camera',
    'furniture' => 'fa-couch',
    'kitchen' => 'fa-utensils',
    'home-decor' => 'fa-house',
    'mens-clothing' => 'fa-shirt',
    'men-s-clothing' => 'fa-shirt',
    'womens-clothing' => 'fa-person-dress',
    'women-s-clothing' => 'fa-person-dress',
    'shoes' => 'fa-shoe-prints',
    'action-figures' => 'fa-robot',
    'trading-cards' => 'fa-layer-group',
    'collectible-coins' => 'fa-coins',
    'comics' => 'fa-book-open',
    'fiction' => 'fa-book-open',
    'nonfiction' => 'fa-book',
];
?>
<div class="category-layout shell">
    <?php if ($error): ?>
        <div class="category-notice" role="alert"><h1>Category temporarily unavailable</h1><p>Please try again later.</p></div>
    <?php elseif ($notFound): ?>
        <div class="category-notice"><h1>Category not found</h1><p>That category does not exist.</p><a href="/">Return home</a></div>
    <?php else: ?>
        <nav class="category-breadcrumbs" aria-label="Breadcrumb"><a href="/">Home</a><span>›</span><span aria-current="page"><?= categoryEscape($category['category_name']) ?></span></nav>
        <section class="category-hero" aria-labelledby="category-heading">
            <div class="category-hero__content"><p class="category-eyebrow">Explore this category</p><h1 id="category-heading"><?= categoryEscape($category['category_name']) ?></h1><p><?= categoryEscape($category['description'] ?: 'Browse new and pre-owned finds from OmniBuy sellers.') ?></p></div>
            <div class="category-hero__media"><img src="<?= categoryEscape($heroImage) ?>" alt="" onerror="this.onerror=null;this.src='/assets/images/homepage-image.jpg'"></div>
        </section>
        <?php if ($children): ?>
            <section class="category-subcategories" aria-labelledby="subcategory-heading"><h2 id="subcategory-heading">Subcategories</h2><div class="category-subcategory-grid">
                <?php foreach ($children as $child): ?>
                    <?php
                        $childSlug = categorySlug((string)$child['category_name']);
                        $iconClass = $subcategoryIconClasses[$childSlug] ?? null;
                    ?>
                    <a
                        class="category-subcategory-card"
                        href="<?= categoryEscape(categoryUrl(['id' => (int)$child['category_id']])) ?>"
                    >
                        <?php if ($iconClass !== null): ?>
                            <span class="category-subcategory-icon" aria-hidden="true">
                                <i class="fa-solid <?= categoryEscape($iconClass) ?>"></i>
                            </span>
                        <?php elseif (!empty($child['image_url'])): ?>
                            <img
                                src="<?= categoryEscape($child['image_url']) ?>"
                                alt=""
                                loading="lazy"
                            >
                        <?php else: ?>
                            <span class="category-subcategory-icon" aria-hidden="true">
                                <i class="fa-solid fa-tag"></i>
                            </span>
                        <?php endif; ?>

                        <span><?= categoryEscape($child['category_name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div></section>
        <?php endif; ?>
        <div class="category-results-layout">
            <aside class="category-filter-panel" aria-label="Listing filters">
                <form action="/pages/category.php" method="get">
                    <input type="hidden" name="id" value="<?= (int)$category['category_id'] ?>">
                    <div class="category-filters-heading"><h2>Filters</h2><a href="<?= categoryEscape(categoryUrl(['id' => (int)$category['category_id']])) ?>">Clear</a></div>
                    <fieldset><legend>Price range</legend><div class="category-price-inputs"><label>Min <input name="min_price" type="number" min="0" step="0.01" placeholder="$ Min" value="<?= categoryEscape($minPriceRaw) ?>"></label><label>Max <input name="max_price" type="number" min="0" step="0.01" placeholder="$ Max" value="<?= categoryEscape($maxPriceRaw) ?>"></label></div></fieldset>
                    <fieldset><legend>Condition</legend><?php foreach ($conditionsAllowed as $option): ?><label class="category-check"><input type="checkbox" name="condition[]" value="<?= categoryEscape($option) ?>" <?= in_array($option, $conditions, true) ? 'checked' : '' ?>> <?= categoryEscape(ucwords(str_replace('_', ' ', $option))) ?></label><?php endforeach; ?></fieldset>
                    <fieldset><legend>Fulfillment</legend><?php foreach ($fulfillmentsAllowed as $option): ?><label class="category-check"><input type="checkbox" name="fulfillment[]" value="<?= categoryEscape($option) ?>" <?= in_array($option, $fulfillments, true) ? 'checked' : '' ?>> <?= categoryEscape($option === 'meetup' ? 'Local Meetup' : 'Shipping') ?></label><?php endforeach; ?></fieldset>
                    <input type="hidden" name="sort" value="<?= categoryEscape($sort) ?>">
                    <button class="category-submit" type="submit">Apply Filters</button>
                </form>
            </aside>
            <section class="category-results" aria-labelledby="category-results-heading">
                <div class="category-results-heading"><div><h2 id="category-results-heading"><?= categoryEscape($category['category_name']) ?> Listings</h2><p><?= $priceError === null ? number_format($total) . ' results' : 'Correct the price range to view results' ?></p></div>
                <form method="get" action="/pages/category.php" class="category-sort-form"><input type="hidden" name="id" value="<?= (int)$category['category_id'] ?>"><input type="hidden" name="min_price" value="<?= categoryEscape($minPriceRaw) ?>"><input type="hidden" name="max_price" value="<?= categoryEscape($maxPriceRaw) ?>"><?php foreach ($conditions as $condition): ?><input type="hidden" name="condition[]" value="<?= categoryEscape($condition) ?>"><?php endforeach; ?><?php foreach ($fulfillments as $fulfillment): ?><input type="hidden" name="fulfillment[]" value="<?= categoryEscape($fulfillment) ?>"><?php endforeach; ?><label for="category-sort">Sort by</label><select name="sort" id="category-sort" onchange="this.form.submit()"><option value="recent" <?= $sort==='recent'?'selected':'' ?>>Newest</option><option value="price_low" <?= $sort==='price_low'?'selected':'' ?>>Price: Low to High</option><option value="price_high" <?= $sort==='price_high'?'selected':'' ?>>Price: High to Low</option></select><noscript><button type="submit">Sort</button></noscript></form></div>
                <?php if ($priceError !== null): ?><div class="category-notice" role="alert"><?= categoryEscape($priceError) ?></div>
                <?php elseif (!$listings): ?><div class="category-notice"><h3>No listings found</h3><p>Try clearing some filters or browsing another category.</p></div>
                <?php else: ?><div class="listing-grid category-listing-grid"><?php foreach ($listings as $listing): ?><?php include __DIR__ . '/../components/listing-card-component.php'; ?><?php endforeach; ?></div>
                    <?php if ($totalPages > 1): ?><nav class="category-pagination" aria-label="Listing pages">
                        <?php if ($page > 1): ?><a href="<?= categoryEscape(categoryUrl(array_merge(categoryParams(), ['id'=>$categoryId, 'page'=>$page-1]))) ?>">‹ Previous</a><?php endif; ?>
                        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?><a href="<?= categoryEscape(categoryUrl(array_merge(categoryParams(), ['id'=>$categoryId, 'page'=>$p]))) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a><?php endfor; ?>
                        <?php if ($page < $totalPages): ?><a href="<?= categoryEscape(categoryUrl(array_merge(categoryParams(), ['id'=>$categoryId, 'page'=>$page+1]))) ?>">Next ›</a><?php endif; ?>
                    </nav><?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>
</main>
<script src="/assets/js/image-fallback.js" defer></script>
<script src="/assets/js/shared-navigation.js" defer></script>
</body></html>
