<?php

// Search functionality will be implemented next.
// These values let the page preserve what the user searched for.

$searchQuery = trim($_GET['q'] ?? '');
$searchType = $_GET['search_type'] ?? 'items';

// Placeholder values until database search is implemented.
$listings = [];
$searchError = false;
$resultCount = count($listings);

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
    <link
        rel="stylesheet"
        href="assets/css/omnibuy-design-tokens.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/shared-navigation.css"
    >

    <!-- Search page styles -->
    <link
        rel="stylesheet"
        href="assets/css/search.css"
    >

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


    <!-- =========================================
         SHARED HEADER
         ========================================= -->

    <header class="site-header">

        <div class="shell site-header__top">

            <!-- Logo -->
            <a
                class="brand"
                href="index.php"
                aria-label="OmniBuy home"
            >
                <i class="fa-solid fa-box-open"></i>
                OmniBuy
            </a>


            <!-- Main Search -->
            <form
                class="site-search"
                action="search.php"
                method="get"
                role="search"
            >

                <label
                    class="sr-only"
                    for="search-type"
                >
                    Search type
                </label>

                <select
                    class="site-search__type"
                    id="search-type"
                    name="search_type"
                >

                    <option
                        value="items"
                        <?= $searchType === 'items'
                            ? 'selected'
                            : '' ?>
                    >
                        Search Items
                    </option>

                    <option
                        value="seller_profiles"
                        <?= $searchType === 'seller_profiles'
                            ? 'selected'
                            : '' ?>
                    >
                        Search Profiles
                    </option>

                </select>


                <label
                    class="sr-only"
                    for="search-query"
                >
                    Search OmniBuy
                </label>

                <input
                    class="site-search__input"
                    id="search-query"
                    name="q"
                    type="search"
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    placeholder="Search OmniBuy..."
                    autocomplete="off"
                >


                <button
                    class="site-search__button"
                    type="submit"
                >
                    Search
                </button>

            </form>


            <!-- Header Actions -->
            <div class="header-actions">

                <a
                    class="icon-link"
                    href="wishlist.php"
                    aria-label="Wishlist"
                >
                    <i class="fa-solid fa-heart"></i>

                    <span class="icon-link__label">
                        Wishlist
                    </span>
                </a>


                <a
                    class="icon-link"
                    href="cart.php"
                    aria-label="Shopping cart"
                >
                    <i class="fa-solid fa-cart-shopping"></i>

                    <span class="count-badge">
                        2
                    </span>

                    <span class="icon-link__label">
                        Cart
                    </span>
                </a>


                <a
                    class="text-link"
                    href="login.php"
                >
                    Log In
                </a>


                <a
                    class="button button--primary button--small"
                    href="signup.php"
                >
                    Sign Up
                </a>


                <button
                    class="mobile-nav-toggle"
                    type="button"
                    aria-expanded="false"
                    aria-controls="primary-navigation"
                    aria-label="Toggle navigation"
                >
                    ☰
                </button>

            </div>

        </div>


        <!-- Primary Navigation -->
        <div class="site-header__nav-wrap">

            <nav
                class="shell primary-nav"
                id="primary-navigation"
                aria-label="Primary navigation"
            >

                <a
                    class="primary-nav__link"
                    href="index.php"
                >
                    Home
                </a>

                <a
                    class="primary-nav__link"
                    href="category.php?id=1"
                >
                    Electronics
                </a>

                <a
                    class="primary-nav__link"
                    href="category.php?id=2"
                >
                    Home & Furniture
                </a>

                <a
                    class="primary-nav__link"
                    href="category.php?id=3"
                >
                    Clothing
                </a>

                <a
                    class="primary-nav__link"
                    href="category.php?id=4"
                >
                    Books
                </a>

                <a
                    class="primary-nav__link"
                    href="categories.php"
                >
                    All Categories
                </a>

            </nav>

        </div>

    </header>


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
                        Search Results
                    </h1>

                    <?php if ($searchQuery !== ''): ?>

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
                        <div class="search-results__sort">

                            <label for="results-sort">
                                Sort by
                            </label>

                            <select
                                id="results-sort"
                                name="sort"
                            >
                                <option value="recent">
                                    Newest
                                </option>

                                <option value="price_low">
                                    Price: Low to High
                                </option>

                                <option value="price_high">
                                    Price: High to Low
                                </option>
                            </select>

                        </div>

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

                    <?php elseif ($searchQuery === ''): ?>

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
                                    . '/components/listing-card-component.php';
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