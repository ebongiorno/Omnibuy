<?php

$pageTitle = 'My Listings | OmniBuy';
$activeNav = 'my-listings';
$isLoggedIn = true;
$currentUser = [
    'username' => 'MisterRoger68',
    'avatar_url' => '/public/assets/default-avatar.png',
    'is_seller' => true,
];
$cartCount = 2;
$unreadCount = 3;
$header = '../components/header.php';
include $header;
?>

<section class="shell">
    <h1>My Listings</h1>
    <p>Your listings will appear here.</p>
</section>

<?php

$footer = '../components/footer.php';
include $footer;