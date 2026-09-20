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

<section class="shell">
    <h1>My Wishlist</h1>
    <p>Your Wishlist items will appear here.</p>
</section>

<?php

$footer = '../components/footer.php';
include $footer;
