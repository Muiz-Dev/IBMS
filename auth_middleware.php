<?php
require_once 'config.php';

function requireAuth() {
    if (!isset($_COOKIE['auth_token'])) {
        header('Location: index.php');
        exit();
    }

    $user_id = verifyJWT($_COOKIE['auth_token']);
    if (!$user_id) {
        setcookie('auth_token', '', time() - 3600, '/', '', true, true);
        header('Location: index.php');
        exit();
    }

    return $user_id;
}