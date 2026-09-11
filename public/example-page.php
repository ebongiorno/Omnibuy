<?php

$pageTitle = 'Explore | OmniBuy';
$activeNav = 'home';
$isLoggedIn = true;
$currentUser = [
    'username' => 'MisterRoger68',
    'avatar_url' => '/public/images/default-avatar.png',
    'is_seller' => true,
];
$cartCount = 2;
$unreadCount = 3;
include __DIR__ . '/header.php';
?>

<section class="shell" style="padding-block: var(--space-8);">
  <h1>Explore OmniBuy</h1>
  <p>Page content goes here.</p>
</section>

<?php include __DIR__ . '/footer.php';
