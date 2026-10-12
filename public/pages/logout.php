<?php

// UC-02 Log Out

require_once __DIR__ . '/../../includes/auth.php';

// Only log out on POST (the header's "Log out" button). A GET link could be
// triggered by another site, e.g. through an <img src=".../logout.php">.
// The session cookie is SameSite=Lax, so cross-site POSTs don't carry it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

logOutUser();

header('Location: /pages/login.php?logged_out=1');
exit;
