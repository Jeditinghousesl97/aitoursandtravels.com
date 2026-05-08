<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$pkg = $pdo->prepare('SELECT cover_image FROM packages WHERE id = ?');
$pkg->execute([$id]);
$pkg = $pkg->fetch();

if ($pkg) {
    // Delete image file
    if ($pkg['cover_image']) {
        $path = __DIR__ . '/../../' . $pkg['cover_image'];
        if (file_exists($path)) unlink($path);
    }
    $pdo->prepare('DELETE FROM packages WHERE id = ?')->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
