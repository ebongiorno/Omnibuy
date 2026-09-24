<?php

$pageTitle = 'Explore | OmniBuy';
$activeNav = 'home';
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

<section class="shell" style="padding-block: var(--space-8);">
  <h1>Explore OmniBuy</h1>
  <p>Page content goes here.</p>
</section>

<?php

$footer = '../components/footer.php';
include $footer;
?>
