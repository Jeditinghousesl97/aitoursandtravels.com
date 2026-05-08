<?php
session_start();
session_destroy();
require_once __DIR__ . '/config/db.php';
header('Location: ' . ADMIN_URL . '/login.php');
exit;
