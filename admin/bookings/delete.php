<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
