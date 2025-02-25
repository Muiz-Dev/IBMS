<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'invoice');

define('JWT_SECRET', 'da2b760e930bf0145078c8475d73b3004108363dbe7ed2d97c5d6c0be6743ead');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}