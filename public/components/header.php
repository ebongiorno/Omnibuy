<?php

/**
 * OmniBuy shared site header.
 *
 * Optional variables before include:
 *   $pageTitle       string
 *   $activeNav       string: home|electronics|collectibles|clothing|home-garden|sporting-goods
 *   $isLoggedIn      bool
 *   $currentUser     array with username, avatar_url, is_seller
 *   $cartCount       int
 *   $unreadCount     int
 */

$pageTitle = $pageTitle ?? 'OmniBuy';
$activeNav = $activeNav ?? '';
$isLoggedIn = $isLoggedIn ?? false;
$currentUser = $currentUser ?? null;
$cartCount = $cartCount ?? 0;
$unreadCount = $unreadCount ?? 0;

function navActive(string $key, string $activeNav): string
{
    return $key === $activeNav ? ' is-active' : '';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <link rel="stylesheet" href="../css/omnibuy-design-tokens.css">
  <link rel="stylesheet" href="../css/shared-navigation.css">

  <!-- Enables the use of Font Awesome's Icons -->
  <script src="https://kit.fontawesome.com/9e304416f8.js" crossorigin="anonymous"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>

<header class="site-header">
  <div class="site-header__top shell">
    <a class="brand" href="../index.php" aria-label="OmniBuy home">
      <span class="brand__name">
        OmniBuy 
        <i class="fa-solid fa-box-open"></i>
      </span>
    </a>

    <form class="site-search" action="/pages/search.php" method="get" role="search">
      <label class="sr-only" for="global-search">Search OmniBuy</label>
      <select class="site-search__type" name="type" aria-label="Search type">
        <option value="items">Items</option>
        <option value="profiles">Sellers</option>
      </select>
      <input
        id="global-search"
        class="site-search__input"
        type="search"
        name="q"
        placeholder="Search items or sellers"
        autocomplete="off"
      >
      <button class="site-search__button" type="submit" aria-label="Search">Search</button>
    </form>

    <nav class="header-actions" aria-label="Account and shopping">
      <a class="icon-link" href="../pages/wishlist.php" aria-label="Wishlist">
        <i class="fa-solid fa-heart"></i>
        <span class="icon-link__label">Wishlist</span>
      </a>

      <a class="icon-link" href="../pages/messaging.php" aria-label="Messages">
        <span aria-hidden="true">✉</span>
        <span class="icon-link__label">Messages</span>
        <?php if ($unreadCount > 0): ?>
          <span class="count-badge" aria-label="<?= (int) $unreadCount ?> unread messages"><?= (int) $unreadCount ?></span>
        <?php endif; ?>
      </a>

      <a class="icon-link" href="/cart.php" aria-label="Cart">
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="icon-link__label">Cart</span>
        <?php if ($cartCount > 0): ?>
          <span class="count-badge" aria-label="<?= (int) $cartCount ?> items in cart"><?= (int) $cartCount ?></span>
        <?php endif; ?>
      </a>

      <?php if ($isLoggedIn && $currentUser): ?>
        <div class="account-menu" data-account-menu>
          <button class="account-menu__trigger" type="button" aria-expanded="false" aria-controls="account-menu-panel">
            <img
              class="account-menu__avatar"
              src="<?= htmlspecialchars($currentUser['avatar_url'] ?? '/assets/img/default-avatar.png') ?>"
              alt=""
            >
            <span><?= htmlspecialchars($currentUser['username'] ?? 'Account') ?></span>
            <span aria-hidden="true">▾</span>
          </button>

          <div class="account-menu__panel" id="account-menu-panel" hidden>
            <a href="/account.php">Manage My Account</a>
            <a href="/orders.php">My Orders</a>
            <?php if (!empty($currentUser['is_seller'])): ?>
              <a href="../pages/my-listings.php">My Listings</a>
            <?php endif; ?>
            <a href="/reviews.php">My Reviews</a>
            <form action="/logout.php" method="post">
              <button type="submit">Log out</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a class="text-link" href="/login.php">Log in</a>
        <a class="button button--primary button--small" href="/signup.php">Sign up</a>
      <?php endif; ?>

      <button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" data-mobile-nav-toggle>
        <span class="sr-only">Toggle navigation</span>
        <i class="fa-solid fa-bars"></i>
      </button>
    </nav>
  </div>

  <div class="site-header__nav-wrap">
    <nav class="primary-nav shell" id="primary-nav" aria-label="Primary navigation">
      <a class="primary-nav__link<?= navActive('home', $activeNav) ?>" href="/">Explore</a>
      <a class="primary-nav__link<?= navActive(
          'electronics',
          $activeNav,
      ) ?>" href="/category.php?category=electronics">Electronics</a>
      <a class="primary-nav__link<?= navActive(
          'collectibles',
          $activeNav,
      ) ?>" href="/category.php?category=collectibles">Collectibles</a>
      <a class="primary-nav__link<?= navActive(
          'clothing',
          $activeNav,
      ) ?>" href="/category.php?category=clothing">Clothing, Shoes & Accessories</a>
      <a class="primary-nav__link<?= navActive(
          'home-garden',
          $activeNav,
      ) ?>" href="/category.php?category=home-garden">Home & Garden</a>
      <a class="primary-nav__link<?= navActive(
          'sporting-goods',
          $activeNav,
      ) ?>" href="/category.php?category=sporting-goods">Sporting Goods</a>
      <a class="primary-nav__link" href="/categories.php">More</a>
    </nav>
  </div>
</header>

<main id="main-content" class="page-main">
