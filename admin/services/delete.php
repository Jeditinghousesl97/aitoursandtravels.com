<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo  = getPDO();
$id   = (int)($_GET['id'] ?? 0);
$tab  = in_array($_GET['tab'] ?? '', ['core','additional']) ? $_GET['tab'] : 'core';

$stmt = $pdo->prepare('SELECT type FROM services WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if ($row) {
    $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
    $tab = $row['type'];
}

header("Location: index.php?tab=$tab&deleted=1");
exit;
