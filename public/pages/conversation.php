<?php
// Require authentication before loading a conversation.
require_once __DIR__ . '/../components/require-login.php';

// Conversation retrieval and authorization will go here.
// Verify that $currentUserId is a participant in the
// requested conversation before showing any messages.

$header = '/components/header.php';
include $header;
?>
<head>
    <link rel="stylesheet" href="../assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="../assets/css/shared-navigation.css">
    <link rel="stylesheet" href="../assets/css/messaging.css">
</head>

<section class="shell">
    <h1>Conversation</h1>
    <p>Your conversation will appear here.</p>
</section>


<?php
$footer = '../components/footer.php';
include $footer;
?>
