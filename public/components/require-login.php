<?php
// Start the session if one has not already been started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in.
if (empty($_SESSION['user_id'])) {
    // Redirect unauthenticated users to the login page.
    header("Location: /login.php");
    exit();
}

// Store the authenticated user ID for use on the page.
$currentUserId = $_SESSION['user_id'];
?>
