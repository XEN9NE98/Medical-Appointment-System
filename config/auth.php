<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function requireUserType($type) {
    requireLogin();
    if (getUserType() !== $type) {
        header('Location: login.php');
        exit();
    }
}
?>