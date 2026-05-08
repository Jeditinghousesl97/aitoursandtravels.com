<?php
// Protect every admin page, include this at the top of each admin file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}
