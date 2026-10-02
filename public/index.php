<?php
    $header = 'components/header.php';
    include $header;
require_once __DIR__ . '/../db_connect.php';

$sort = $_GET['sort'] ?? 'recent';

$sortOptions = [
    'recent' => 'l.created_at DESC, l.listing_id DESC',
    'price_low' => 'l.price ASC, l.created_at DESC',
    'price_high' => 'l.price DESC, l.created_at DESC'
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'recent';
}

$sortType = $sortOptions[$sort];

$sql = "SELECT l.listing_id, l.`condition`, l.item_name, 
            l.price, l.category_id, l.fulfillment_type,
            li.image_url, c.category_name
            FROM listings as l
            LEFT JOIN listing_images as li
                ON l.listing_id = li.listing_id 
                AND li.display_order = 1
            LEFT JOIN categories as c 
                ON l.category_id = c.category_id
            WHERE listing_status = 'active'
            ORDER BY $sortType
            LIMIT 10";

$listings = [];
$feedError = false;

try {
    // establish connection and query
    $stmt = $pdo->query($sql);
    $listings = $stmt->fetchAll();
} catch (PDOException $e) {
    $feedError = true;
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS Stylesheets -->
    <link rel="stylesheet" href="assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="assets/css/shared-navigation.css">
    <link rel="stylesheet" href="assets/css/homepage.css">
    <link rel="stylesheet" href="/assets/css/listing-card.css">

    <!-- Enables the use of Font Awesome's Icons -->
    <script src="https://kit.fontawesome.com/9e304416f8.js" crossorigin="anonymous"></script>

    <title>OmniBuy</title>
</head>
<body>
    <!-- =========================================
         HOMEPAGE
         ========================================= -->

    <main class="page-main" id="main-content">


        <!-- =====================================
             FEATURED / HERO SECTION
             ===================================== -->

        <section class="homepage-section homepage-hero">
            <div class="shell">

                <div class="hero-card">

                    <div class="hero-card__content">
                        <p class="section-eyebrow">
                            Discover something new
                        </p>

                        <h1>
                            Buy, sell, and discover items on OmniBuy
                        </h1>

                        <p>
                            Browse new and second-hand items from
                            sellers in the OmniBuy marketplace.
                        </p>

                        <a
                            class="button button--primary"
                            href="#browse-feed"
                        >
                            Start Browsing
                        </a>
                    </div>

                    <div
                        class="hero-card__media"
                        aria-hidden="true"
                    >
                        <img src="assets/images/homepage-image.jpg">
                    </div>

                </div>

            </div>
        </section>

        <!-- =====================================
             POPULAR CATEGORIES
             ===================================== -->

        <section
            class="homepage-section"
            aria-labelledby="popular-categories-heading"
        >
            <div class="shell">

                <div class="section-heading">
                    <div>
                        <p class="section-eyebrow">
                            Explore
                        </p>

                        <h2 id="popular-categories-heading">
                            Popular Categories
                        </h2>
                    </div>

                    <a
                        class="text-link"
                        href="categories.php"
                    >
                        View all categories
                    </a>
                </div>


                <div class="category-grid">

                    <a
                        class="category-card"
                        href="category.php?id=1"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-electronics.jpg">
                        </div>

                        <h3>Electronics</h3>
                    </a>


                    <a
                        class="category-card"
                        href="category.php?id=2"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-home-furniture.jpg">
                        </div>

                        <h3>Home & Furniture</h3>
                    </a>


                    <a
                        class="category-card"
                        href="category.php?id=3"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-clothing.jpg">
                        </div>

                        <h3>Clothing</h3>
                    </a>


                    <a
                        class="category-card"
                        href="category.php?id=4"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-books.jpg">
                        </div>

                        <h3>Books</h3>
                    </a>

                </div>

            </div>
        </section>

        <!-- =====================================
             BROWSE / RECOMMENDED FEED
             ===================================== -->

        <section
            class="homepage-section"
            id="browse-feed"
            aria-labelledby="browse-feed-heading"
        >
            <div class="shell">

                <div class="section-heading">

                    <div>
                        <p class="section-eyebrow">
                            Marketplace
                        </p>

                        <h2 id="browse-feed-heading">
                            Recommended for You
                        </h2>

                        <p class="section-description">
                            Discover listings based on your interests
                            and recent marketplace activity.
                        </p>
                    </div>


                    <!-- Initial/simple sorting control -->
                    <form class="feed-controls" method="get" action="index.php#browse-feed">
                        <label for="feed-sort">
                            Sort by
                        </label>

                        <select id="feed-sort" name="sort" onchange="this.form.submit()">
                            <option 
                                value="recent"
                                <?= $sort === 'recent' ? 'selected' : '' ?> 
                                >
                                Newest
                            </option>

                            <option 
                                value="price_low"
                                <?= $sort === 'price_low' ? 'selected' : '' ?>>
                                Price: Low to High
                            </option>

                            <option 
                                value="price_high"
                                <?= $sort === 'price_high' ? 'selected' : '' ?>>
                                Price: High to Low
                            </option>
                        </select>

                    </form>

                </div>

                <?php if ($feedError): ?>
                    <!-- =================================
                     ERROR STATE
                     Hidden unless retrieval fails.
                     ================================= -->

                    <div
                        class="feed-message feed-message--error"
                        role="alert"
                    >
                        <h3>Unable to display feed</h3>

                        <p>
                            Try again later.
                        </p>
                    </div>
                <?php elseif (empty($listings)): ?>
                    <!-- =================================
                     EMPTY STATE
                     Hidden unless no listings exist.
                     ================================= -->
                    <div class="feed-message">
                        <h3>No listings available</h3>

                        <p>
                            Check back later for newly listed items.
                        </p>
                    </div>
                <?php else: ?>
                    <!--
                    PHP will eventually loop through database
                    results and generate these cards.
                    -->
                    <div class="listing-grid">
                        <?php foreach ($listings as $listing): ?>
                            <?php include 'components/listing-card-component.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </section>

        <!-- =====================================
             LATEST ITEMS
             ===================================== -->

        <section
            class="homepage-section homepage-section--secondary"
            aria-labelledby="latest-items-heading"
        >
            <div class="shell">

                <div class="section-heading">
                    <div>
                        <p class="section-eyebrow">
                            Recently Listed
                        </p>

                        <h2 id="latest-items-heading">
                            Latest Items
                        </h2>
                    </div>

                    <a
                        class="text-link"
                        href="pages/search.php?sort=recent"
                    >
                        View more
                    </a>
                </div>


                <div class="listing-grid">
                    <!--
                        Same reusable listing-card markup
                        can be rendered here.
                    -->
                </div>

            </div>
        </section>

    </main>

    <!-- =========================================
         MOBILE NAVIGATION
         ========================================= -->

    <script>
        const navToggle = document.querySelector('.mobile-nav-toggle');
        const primaryNav = document.querySelector('.primary-nav');

        navToggle?.addEventListener('click', () => {
            const isOpen = primaryNav.classList.toggle('is-open');

            navToggle.setAttribute(
                'aria-expanded',
                String(isOpen)
            );
        });
    </script>

</body>
</html>

<?php
$footer = 'components/footer.php';
include $footer;
?>
