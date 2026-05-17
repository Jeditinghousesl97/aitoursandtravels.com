<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$schemaReady = true;

if (!function_exists('ensureHeroBannerMediaSchema')) {
    function ensureHeroBannerMediaSchema(PDO $pdo): bool {
        try {
            if (!columnExists($pdo, 'hero_banners', 'badge_text')) {
                $pdo->exec("ALTER TABLE hero_banners ADD COLUMN badge_text VARCHAR(150) DEFAULT NULL AFTER image_path");
            }
            if (!columnExists($pdo, 'hero_banners', 'media_type')) {
                $pdo->exec("ALTER TABLE hero_banners ADD COLUMN media_type VARCHAR(20) NOT NULL DEFAULT 'image' AFTER image_path");
            }
            if (!columnExists($pdo, 'hero_banners', 'video_path')) {
                $pdo->exec("ALTER TABLE hero_banners ADD COLUMN video_path VARCHAR(255) DEFAULT NULL AFTER media_type");
            }
            if (!columnExists($pdo, 'hero_banners', 'youtube_url')) {
                $pdo->exec("ALTER TABLE hero_banners ADD COLUMN youtube_url VARCHAR(255) DEFAULT NULL AFTER video_path");
            }
        } catch (Throwable $e) {
            // Avoid HTTP 500 on restricted DB users.
        }

        return columnExists($pdo, 'hero_banners', 'badge_text')
            && columnExists($pdo, 'hero_banners', 'media_type')
            && columnExists($pdo, 'hero_banners', 'video_path')
            && columnExists($pdo, 'hero_banners', 'youtube_url');
    }
}
$schemaReady = ensureHeroBannerMediaSchema($pdo);

function toYouTubeEmbed(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (str_contains($url, 'youtube.com/embed/')) return $url;
    if (preg_match('~youtu\.be/([a-zA-Z0-9_-]{6,})~', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('~[?&]v=([a-zA-Z0-9_-]{6,})~', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return '';
}

$stmt = $pdo->prepare('SELECT * FROM hero_banners WHERE id = ?');
$stmt->execute([$id]);
$banner = $stmt->fetch();
if (!$banner) {
    header('Location: index.php');
    exit;
}

$errors = [];
if (!$schemaReady) {
    $errors[] = 'Database schema update is required for video banners. Please run the migration (add media_type, video_path, youtube_url columns to hero_banners) or grant ALTER permission temporarily.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $badge_text = trim($_POST['badge_text'] ?? '');
    $heading    = trim($_POST['heading'] ?? '');
    $subheading = trim($_POST['subheading'] ?? '');
    $btn_label  = trim($_POST['btn_label'] ?? 'Explore Now');
    $btn_link   = trim($_POST['btn_link'] ?? '#packages');
    $is_active  = isset($_POST['is_active']) ? 1 : 0;
    $media_type = $_POST['media_type'] ?? ($banner['media_type'] ?: 'image');
    if (!in_array($media_type, ['image', 'video_upload', 'video_youtube'], true)) {
        $media_type = 'image';
    }

    if ($heading === '') $errors[] = 'Heading is required.';

    $image_path = $banner['image_path'];
    $video_path = $banner['video_path'] ?? null;
    $youtube_url = $banner['youtube_url'] ?? null;

    if ($media_type === 'image') {
        if (!empty($_FILES['image']['name'])) {
            $file    = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($file['type'], $allowed, true)) {
                $errors[] = 'Image must be JPG, PNG or WEBP.';
            } elseif ($file['size'] > 25 * 1024 * 1024) {
                $errors[] = 'Image must be under 25MB.';
            } else {
                $ext  = strtolower((string)pathinfo($file['name'], PATHINFO_EXTENSION));
                $name = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = __DIR__ . '/../../uploads/banners/' . $name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if (!empty($banner['image_path'])) {
                        $old = __DIR__ . '/../../' . $banner['image_path'];
                        if (file_exists($old)) unlink($old);
                    }
                    $image_path = 'uploads/banners/' . $name;
                } else {
                    $errors[] = 'Failed to upload image.';
                }
            }
        } elseif (empty($image_path)) {
            $errors[] = 'Banner image is required.';
        }
    } elseif ($media_type === 'video_upload') {
        if (!empty($_FILES['video']['name'])) {
            $file = $_FILES['video'];
            $allowed = ['video/mp4', 'video/webm', 'video/ogg'];
            if (!in_array($file['type'], $allowed, true)) {
                $errors[] = 'Video must be MP4, WEBM or OGG.';
            } elseif ($file['size'] > 100 * 1024 * 1024) {
                $errors[] = 'Video must be under 100MB.';
            } else {
                $ext  = strtolower((string)pathinfo($file['name'], PATHINFO_EXTENSION));
                $name = 'banner_video_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = __DIR__ . '/../../uploads/banners/' . $name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if (!empty($banner['video_path'])) {
                        $old = __DIR__ . '/../../' . $banner['video_path'];
                        if (file_exists($old)) unlink($old);
                    }
                    $video_path = 'uploads/banners/' . $name;
                } else {
                    $errors[] = 'Failed to upload video.';
                }
            }
        } elseif (empty($video_path)) {
            $errors[] = 'Video file is required.';
        }
    } else {
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        if ($youtube_url === '') {
            $errors[] = 'YouTube URL is required.';
        } elseif (toYouTubeEmbed($youtube_url) === '') {
            $errors[] = 'Please enter a valid YouTube URL.';
        }
    }

    if (empty($errors)) {
        if ($media_type !== 'image') {
            $image_path = null;
        }
        if ($media_type !== 'video_upload') {
            $video_path = null;
        }
        if ($media_type !== 'video_youtube') {
            $youtube_url = null;
        }

        try {
            $pdo->prepare('
                UPDATE hero_banners
                SET badge_text=?, heading=?, subheading=?, image_path=?, media_type=?, video_path=?, youtube_url=?, btn_label=?, btn_link=?, is_active=?
                WHERE id=?
            ')->execute([$badge_text ?: null, $heading, $subheading ?: null, $image_path, $media_type, $video_path, $youtube_url, $btn_label, $btn_link, $is_active, $id]);
        } catch (Throwable $e) {
            $errors[] = 'Failed to update banner: ' . $e->getMessage();
        }

        if (!empty($errors)) {
            $banner = array_merge($banner, $_POST);
            $banner['image_path'] = $image_path;
            $banner['video_path'] = $video_path;
            $banner['youtube_url'] = $youtube_url;
            $banner['media_type'] = $media_type;
            goto end_post;
        }

        if ($media_type !== 'image' && !empty($banner['image_path'])) {
            $old = __DIR__ . '/../../' . $banner['image_path'];
            if (file_exists($old)) unlink($old);
        }
        if ($media_type !== 'video_upload' && !empty($banner['video_path'])) {
            $old = __DIR__ . '/../../' . $banner['video_path'];
            if (file_exists($old)) unlink($old);
        }

        header('Location: index.php?updated=1');
        exit;
    }

    $banner = array_merge($banner, $_POST);
    $banner['image_path'] = $image_path;
    $banner['video_path'] = $video_path;
    $banner['youtube_url'] = $youtube_url;
    $banner['media_type'] = $media_type;
    end_post:
}

$pageTitle = 'Edit Banner';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-pencil me-2 text-primary"></i>Edit Banner</h1>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="POST" enctype="multipart/form-data">
      <div class="admin-card mb-3">
        <div class="card-header">Banner Content</div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Slide Media Type</label>
            <?php $currentMedia = $banner['media_type'] ?? 'image'; ?>
            <div class="d-flex gap-3 flex-wrap">
              <div class="form-check"><input class="form-check-input" type="radio" name="media_type" value="image" id="mediaImage" <?= $currentMedia === 'image' ? 'checked' : '' ?>><label class="form-check-label" for="mediaImage">Image</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="media_type" value="video_upload" id="mediaVideoUpload" <?= $currentMedia === 'video_upload' ? 'checked' : '' ?>><label class="form-check-label" for="mediaVideoUpload">Upload Video</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="media_type" value="video_youtube" id="mediaYouTube" <?= $currentMedia === 'video_youtube' ? 'checked' : '' ?>><label class="form-check-label" for="mediaYouTube">YouTube Link</label></div>
            </div>
          </div>

          <div class="mb-3" id="youtubeField" style="display:none;">
            <label class="form-label">YouTube URL <span class="text-danger">*</span></label>
            <input type="url" name="youtube_url" class="form-control" value="<?= htmlspecialchars($banner['youtube_url'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Hero Badge Text <small class="text-muted">(optional)</small></label>
            <input type="text" name="badge_text" class="form-control" value="<?= htmlspecialchars($banner['badge_text'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Heading <span class="text-danger">*</span></label>
            <input type="text" name="heading" class="form-control" value="<?= htmlspecialchars($banner['heading']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Subheading</label>
            <input type="text" name="subheading" class="form-control" value="<?= htmlspecialchars($banner['subheading'] ?? '') ?>">
          </div>
          <div class="row g-3">
            <div class="col-sm-5"><label class="form-label">Button Label</label><input type="text" name="btn_label" class="form-control" value="<?= htmlspecialchars($banner['btn_label']) ?>"></div>
            <div class="col-sm-7"><label class="form-label">Button Link</label><input type="text" name="btn_link" class="form-control" value="<?= htmlspecialchars($banner['btn_link']) ?>"></div>
          </div>
        </div>
      </div>

      <div class="admin-card mb-3" id="imageUploadCard">
        <div class="card-header">Banner Image</div>
        <div class="p-3">
          <?php if (!empty($banner['image_path'])): ?>
          <div class="mb-3"><div class="text-muted small mb-2">Current image:</div><img src="<?= htmlspecialchars(site_url($banner['image_path'])) ?>" id="preview" style="width:100%;max-height:200px;object-fit:cover;border-radius:10px;"></div>
          <?php else: ?>
          <img id="preview" src="" style="display:none;width:100%;max-height:200px;object-fit:cover;border-radius:10px;">
          <?php endif; ?>
          <label class="form-label">Replace Image <small class="text-muted">(optional)</small></label>
          <input type="file" name="image" id="fileInput" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
      </div>

      <div class="admin-card mb-3" id="videoUploadCard" style="display:none;">
        <div class="card-header">Banner Video</div>
        <div class="p-3">
          <?php if (!empty($banner['video_path'])): ?>
          <video controls style="width:100%;max-height:220px;border-radius:10px;" class="mb-3"><source src="<?= htmlspecialchars(site_url($banner['video_path'])) ?>"></video>
          <?php endif; ?>
          <label class="form-label">Replace Video <small class="text-muted">(optional)</small></label>
          <input type="file" name="video" class="form-control" accept="video/mp4,video/webm,video/ogg">
          <div class="form-text mt-2">MP4, WEBM or OGG. Max 100MB.</div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Settings</div>
        <div class="p-3">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?= $banner['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive"><i class="bi bi-eye me-1"></i> Active (show on homepage)</label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Banner</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
function toggleMediaFields() {
  const mediaType = document.querySelector('input[name="media_type"]:checked')?.value || 'image';
  document.getElementById('imageUploadCard').style.display = mediaType === 'image' ? '' : 'none';
  document.getElementById('videoUploadCard').style.display = mediaType === 'video_upload' ? '' : 'none';
  document.getElementById('youtubeField').style.display = mediaType === 'video_youtube' ? '' : 'none';
}
document.querySelectorAll('input[name="media_type"]').forEach(el => el.addEventListener('change', toggleMediaFields));
toggleMediaFields();

const fileInput = document.getElementById('fileInput');
if (fileInput) {
  fileInput.addEventListener('change', function () {
    if (this.files[0]) {
      const r = new FileReader();
      r.onload = e => {
        const preview = document.getElementById('preview');
        preview.style.display = 'block';
        preview.src = e.target.result;
      };
      r.readAsDataURL(this.files[0]);
    }
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
