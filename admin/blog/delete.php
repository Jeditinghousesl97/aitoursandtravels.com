<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$hasGalleryImagesColumn = columnExists($pdo, 'blog_posts', 'gallery_images');

$selectFields = $hasGalleryImagesColumn ? 'cover_image, gallery_images' : 'cover_image';
$stmt = $pdo->prepare("SELECT {$selectFields} FROM blog_posts WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if ($post) {
    if ($post['cover_image']) {
        $file = __DIR__ . '/../../' . $post['cover_image'];
        if (file_exists($file)) unlink($file);
    }
    if ($hasGalleryImagesColumn && !empty($post['gallery_images'])) {
        $gallery = json_decode((string)$post['gallery_images'], true);
        if (is_array($gallery)) {
            foreach ($gallery as $img) {
                $file = __DIR__ . '/../../' . ltrim((string)$img, '/');
                if (file_exists($file)) unlink($file);
            }
        }
    }
    $pdo->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
