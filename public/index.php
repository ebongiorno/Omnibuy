<?php
require_once __DIR__ . '/../includes/auth.php';
startSession();
require_once __DIR__ . '/../db_connect.php';
$activeNav = 'home';
$pageTitle = 'OmniBuy';
$pageStylesheet = '/assets/css/homepage.css';
$extraStylesheets = [
    '/assets/css/listing-card.css',
    '/assets/css/homepage-category-layout.css'
];



// UC-16: Use recorded search and listing-view activity to personalize
// the homepage feed. Guests and members without usable category interests
// sort all eligible listings before taking ten. Members with interests
// select their top ten recommendations first, then sort those ten.
require_once __DIR__ . '/../includes/recommendations.php';

$sort = $_GET['sort'] ?? 'recent';
$allowedSortOptions = ['recent', 'price_low', 'price_high'];

if (!is_string($sort) || !in_array($sort, $allowedSortOptions, true)) {
    $sort = 'recent';
}

// The recommendation helper accepts null for guests. It also excludes
// the signed-in user's own listings and enforces listing/seller eligibility.
$recommendationUserId = null;
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    $sessionUserId = filter_var(
        $_SESSION['user_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($sessionUserId !== false) {
        $recommendationUserId = $sessionUserId;
    }
}

$listings = [];
$feedError = false;

try {
    $listings = getPersonalizedRecommendations(
        $pdo,
        $recommendationUserId,
        10,
        $sort
    );
} catch (PDOException $exception) {
    $feedError = true;
    error_log('OmniBuy homepage recommendation feed failed: ' . $exception->getMessage());
}


include __DIR__ . '/components/header.php';
?>
<!-- =========================================
         HOMEPAGE
         ========================================= -->

    


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

                </div>


                <div class="category-grid">

                    <a
                        class="category-card"
                        href="/pages/category.php?category=electronics"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-electronics.jpg" alt="Electronics">
                        </div>

                        <h3>Electronics</h3>
                    </a>


                    <a
                        class="category-card"
                        href="/pages/category.php?category=home-furniture"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-home-furniture.jpg" alt="Furniture">
                        </div>

                        <h3>Home & Furniture</h3>
                    </a>


                    <a
                        class="category-card"
                        href="/pages/category.php?category=clothing"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-clothing.jpg" alt="Clothing">
                        </div>

                        <h3>Clothing</h3>
                    </a>


                    <a
                        class="category-card"
                        href="/pages/category.php?category=collectibles"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-collectibles.jpg" alt="Collectibles">
                        </div>

                        <h3>Collectibles</h3>
                    </a>


                    <a
                        class="category-card"
                        href="/pages/category.php?category=books"
                    >
                        <div class="category-card__image">
                            <img src="assets/images/category/category-parent-books.jpg" alt="Books">
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


                    <!-- General feeds sort before LIMIT; personalized feeds sort their selected ten. -->
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
                        href="pages/search.php?browse=all&sort=recent"
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

    <script src="/assets/js/image-fallback.js" defer></script>
    <!-- =========================================
         MOBILE NAVIGATION AND ACCOUNT MENU
         Same script the shared footer loads on every other page.
         ========================================= -->

    <script src="/assets/js/shared-navigation.js" defer></script>

</body>
</html>
