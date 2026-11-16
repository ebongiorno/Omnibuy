<?php

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../../db_connect.php';
$currentUserId = (int) $_SESSION['user_id'];

