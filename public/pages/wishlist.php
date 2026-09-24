<?php

$pageTitle = 'Wishlist | OmniBuy';
$activeNav = 'wishlist';
$isLoggedIn = true;
$currentUser = [
    'username' => 'MisterRoger68',
    'avatar_url' => '/public/assets/default-avatar.png',
    'is_seller' => true,
];
$itemCount = 2;
$header = '../components/header.php';
include $header;
?>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS Stylesheets -->
    <link rel="stylesheet" href="../assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="../assets/css/shared-navigation.css">
    <link rel="stylesheet" href="../assets/css/homepage.css">

    <!-- Enables the use of Font Awesome's Icons -->
    <script src="https://kit.fontawesome.com/9e304416f8.js" crossorigin="anonymous"></script>
</head>

<section class="shell">
    <h1>My Wishlist</h1>
    <p>Your Wishlist items will appear here.</p>
</section>

<?php

$footer = '../components/footer.php';
include $footer;
