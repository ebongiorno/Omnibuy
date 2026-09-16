<?php

// Grab .env files
$host = getenv('DB_HOST');
$port = (int) getenv('DB_PORT');
$database = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

// Establish connection between PHP and database
$conn = new mysqli($host, $user, $password, $database, $port);

if($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

?>