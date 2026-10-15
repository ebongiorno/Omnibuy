<?php
// Include at the top of any page that needs a logged-in user.
// Logged-out visitors go to the login page and come back here afterwards.
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

// Store the authenticated user for use on the page and in the shared header.
$currentUser = currentUser();
$currentUserId = $currentUser['user_id'];
$isLoggedIn = true;
