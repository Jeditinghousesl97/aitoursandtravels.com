<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT image_path FROM hero_banners WHERE id = ?');
$stmt->execute([$id]);
$banner = $stmt->fetch();

if ($banner) {
    if ($banner['image_path']) {
        $file = __DIR__ . '/../../' . $banner['image_path'];
        if (file_exists($file)) unlink($file);
    }
    $pdo->prepare('DELETE FROM hero_banners WHERE id = ?')->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
