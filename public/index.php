<?php
    //future php code here
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="css/shared-navigation.css">
    <link rel="stylesheet" href="css/homepage.css">
    <title>OmniBuy</title>
</head>
<body>

    <!-- Accessibility -->
    <a class="skip-link" href="#main-content">
        Skip to main content
    </a>


    <!-- =========================================
         SHARED HEADER
         ========================================= -->

    <header class="site-header">

        <div class="shell site-header__top">

            <!-- Logo -->
            <a class="brand" href="index.php" aria-label="OmniBuy home">
                <span class="brand__mark" aria-hidden="true">◆</span>
                OmniBuy
            </a>


            <!-- Search -->
            <form
                class="site-search"
                action="search.php"
                method="get"
                role="search"
            >
                <label class="sr-only" for="search-type">
                    Search type
                </label>

                <select
                    class="site-search__type"
                    id="search-type"
                    name="search_type"
                >
                    <option value="items">Search Items</option>
                    <option value="seller_profiles">Search Profiles</option>
                </select>


                <label class="sr-only" for="search-query">
                    Search OmniBuy
                </label>

                <input
                    class="site-search__input"
                    id="search-query"
                    name="q"
                    type="search"
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
                    <span aria-hidden="true">♡</span>
                    <span class="icon-link__label">Wishlist</span>
                </a>


                <a
                    class="icon-link"
                    href="cart.php"
                    aria-label="Shopping cart"
                >
                    <span aria-hidden="true">Cart</span>

                    <!-- Temporary/example cart count -->
                    <span class="count-badge">2</span>

                    <span class="icon-link__label">Cart</span>
                </a>


                <!-- Logged-out version -->
                <a class="text-link" href="login.php">
                    Log In
                </a>

                <a
                    class="button button--primary button--small"
                    href="signup.php"
                >
                    Sign Up
                </a>


                <!-- Mobile Navigation Button -->
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


        <!-- =====================================
             PRIMARY NAVIGATION
             ===================================== -->

        <div class="site-header__nav-wrap">
            <nav
                class="shell primary-nav"
                id="primary-navigation"
                aria-label="Primary navigation"
            >
                <a
                    class="primary-nav__link is-active"
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
                        Featured marketplace image
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
                            Electronics image
                        </div>

                        <h3>Electronics</h3>
                    </a>


                    <a
                        class="category-card"
                        href="category.php?id=2"
                    >
                        <div class="category-card__image">
                            Home & Furniture image
                        </div>

                        <h3>Home & Furniture</h3>
                    </a>


                    <a
                        class="category-card"
                        href="category.php?id=3"
                    >
                        <div class="category-card__image">
                            Clothing image
                        </div>

                        <h3>Clothing</h3>
                    </a>


                    <a
                        class="category-card"
                        href="category.php?id=4"
                    >
                        <div class="category-card__image">
                            Books image
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
                    <div class="feed-controls">

                        <label for="feed-sort">
                            Sort by
                        </label>

                        <select id="feed-sort" name="sort">
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


                <!--
                    PHP will eventually loop through database
                    results and generate these cards.
                -->
                <div class="listing-grid">


                    <!-- Listing Card -->
                    <article class="listing-card">

                        <a
                            class="listing-card__image-link"
                            href="listing.php?id=1"
                        >
                            <div class="listing-card__image">
                                Listing image
                            </div>
                        </a>


                        <div class="listing-card__body">

                            <div class="listing-card__top-row">
                                <span class="listing-card__condition">
                                    Excellent
                                </span>

                                <button
                                    class="listing-card__favorite"
                                    type="button"
                                    aria-label="Add Wireless Mechanical Keyboard to wishlist"
                                >
                                    ♡
                                </button>
                            </div>


                            <h3 class="listing-card__title">
                                <a href="listing.php?id=1">
                                    Wireless Mechanical Keyboard
                                </a>
                            </h3>


                            <p class="listing-card__price">
                                $45.00
                            </p>


                            <div class="listing-card__meta">
                                <span>Electronics</span>
                                <span>Shipping</span>
                            </div>

                        </div>

                    </article>


                    <!-- Listing Card -->
                    <article class="listing-card">

                        <a
                            class="listing-card__image-link"
                            href="listing.php?id=6"
                        >
                            <div class="listing-card__image">
                                Listing image
                            </div>
                        </a>


                        <div class="listing-card__body">

                            <div class="listing-card__top-row">
                                <span class="listing-card__condition">
                                    Good
                                </span>

                                <button
                                    class="listing-card__favorite"
                                    type="button"
                                    aria-label="Add Wooden Study Desk to wishlist"
                                >
                                    ♡
                                </button>
                            </div>


                            <h3 class="listing-card__title">
                                <a href="listing.php?id=6">
                                    Wooden Study Desk
                                </a>
                            </h3>


                            <p class="listing-card__price">
                                $70.00
                            </p>


                            <div class="listing-card__meta">
                                <span>Furniture</span>
                                <span>Meetup</span>
                            </div>

                        </div>

                    </article>


                    <!-- Add additional mock cards while DB is unavailable -->

                </div>


                <!-- =================================
                     EMPTY STATE
                     Hidden unless no listings exist.
                     ================================= -->

                <div class="feed-message" hidden>
                    <h3>No listings available</h3>

                    <p>
                        Check back later for newly listed items.
                    </p>
                </div>


                <!-- =================================
                     ERROR STATE
                     Hidden unless retrieval fails.
                     ================================= -->

                <div
                    class="feed-message feed-message--error"
                    role="alert"
                    hidden
                >
                    <h3>Unable to display feed</h3>

                    <p>
                        Try again later.
                    </p>
                </div>


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
                        href="search.php?sort=recent"
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