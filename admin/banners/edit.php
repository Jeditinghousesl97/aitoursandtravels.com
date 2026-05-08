<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

if (!columnExists($pdo, 'hero_banners', 'badge_text')) {
    $pdo->exec("ALTER TABLE hero_banners ADD COLUMN badge_text VARCHAR(150) DEFAULT NULL AFTER image_path");
}

$stmt = $pdo->prepare('SELECT * FROM hero_banners WHERE id = ?');
$stmt->execute([$id]);
$banner = $stmt->fetch();
if (!$banner) { header('Location: index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $badge_text = trim($_POST['badge_text'] ?? '');
    $heading    = trim($_POST['heading'] ?? '');
    $subheading = trim($_POST['subheading'] ?? '');
    $btn_label  = trim($_POST['btn_label'] ?? 'Explore Now');
    $btn_link   = trim($_POST['btn_link'] ?? '#packages');
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    if ($heading === '') $errors[] = 'Heading is required.';

    $image_path = $banner['image_path'];

    if (!empty($_FILES['image']['name'])) {
        $file    = $_FILES['image'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Image must be JPG, PNG or WEBP.';
        } elseif ($file['size'] > 25 * 1024 * 1024) {
            $errors[] = 'Image must be under 25MB.';
        } else {
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $name = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = __DIR__ . '/../../uploads/banners/' . $name;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $old = __DIR__ . '/../../' . $banner['image_path'];
                if (file_exists($old)) unlink($old);
                $image_path = 'uploads/banners/' . $name;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    }

    if (empty($errors)) {
        $pdo->prepare('
            UPDATE hero_banners
            SET badge_text=?, heading=?, subheading=?, image_path=?, btn_label=?, btn_link=?, is_active=?
            WHERE id=?
        ')->execute([$badge_text ?: null, $heading, $subheading ?: null, $image_path, $btn_label, $btn_link, $is_active, $id]);
        header('Location: index.php?updated=1'); exit;
    }

    $banner = array_merge($banner, $_POST);
}

$pageTitle = 'Edit Banner';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-pencil me-2 text-primary"></i>Edit Banner</h1>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="POST" enctype="multipart/form-data">

      <div class="admin-card mb-3">
        <div class="card-header">Banner Content</div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Hero Badge Text <small class="text-muted">(optional)</small></label>
            <input type="text" name="badge_text" class="form-control"
                   value="<?= htmlspecialchars($banner['badge_text'] ?? '') ?>"
                   placeholder="e.g. Welcome to ASB Tours">
            <div class="form-text">Small text shown above the main heading on this slide.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Heading <span class="text-danger">*</span></label>
            <input type="text" name="heading" class="form-control"
                   value="<?= htmlspecialchars($banner['heading']) ?>" required>
            <div class="form-text">Use <code>|</code> to split into two colours. Example: <em>Sri Lanka's Most | Stunning Beaches</em> — text after <code>|</code> shows in accent colour.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Subheading</label>
            <input type="text" name="subheading" class="form-control"
                   value="<?= htmlspecialchars($banner['subheading'] ?? '') ?>">
          </div>
          <div class="row g-3">
            <div class="col-sm-5">
              <label class="form-label">Button Label</label>
              <input type="text" name="btn_label" class="form-control"
                     value="<?= htmlspecialchars($banner['btn_label']) ?>">
            </div>
            <div class="col-sm-7">
              <label class="form-label">Button Link</label>
              <input type="text" name="btn_link" class="form-control"
                     value="<?= htmlspecialchars($banner['btn_link']) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Current image -->
      <div class="admin-card mb-3">
        <div class="card-header">Banner Image</div>
        <div class="p-3">
          <div class="mb-3">
            <div class="text-muted small mb-2">Current image:</div>
            <img src="<?= htmlspecialchars(site_url($banner['image_path'])) ?>"
                 id="preview"
                 style="width:100%;max-height:200px;object-fit:cover;border-radius:10px;">
          </div>
          <label class="form-label">Replace Image <small class="text-muted">(optional)</small></label>
          <input type="file" name="image" id="fileInput"
                 class="form-control" accept="image/jpeg,image/png,image/webp">
          <div class="form-text">JPG, PNG or WEBP. Max 5MB. Leave empty to keep current.</div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Settings</div>
        <div class="p-3">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active"
                   id="isActive" value="1" <?= $banner['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">
              <i class="bi bi-eye me-1"></i> Active (show on homepage)
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i> Update Banner
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>

    </form>
  </div>

  <!-- Preview panel -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="card-header"><i class="bi bi-eye me-2"></i>Live Preview</div>
      <div class="p-3">
        <div class="banner-preview-box">
          <img src="<?= htmlspecialchars(site_url($banner['image_path'])) ?>"
               id="previewLarge"
               style="width:100%;height:200px;object-fit:cover;border-radius:10px;display:block;">
          <div class="banner-preview-overlay">
            <div class="banner-preview-heading" id="previewHeading">
              <?= htmlspecialchars($banner['heading']) ?>
            </div>
            <div class="banner-preview-sub" id="previewSub">
              <?= htmlspecialchars($banner['subheading'] ?? '') ?>
            </div>
            <div class="banner-preview-btn" id="previewBtn">
              <?= htmlspecialchars($banner['btn_label']) ?>
            </div>
          </div>
        </div>
        <div class="text-muted text-center small mt-2">
          <i class="bi bi-info-circle me-1"></i>Updates as you type
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.banner-preview-box { position: relative; border-radius: 10px; overflow: hidden; }
.banner-preview-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to bottom, rgba(3,4,94,.2) 0%, rgba(3,4,94,.7) 100%);
  display: flex; flex-direction: column;
  align-items: flex-start; justify-content: flex-end;
  padding: 1rem 1.25rem;
}
.banner-preview-heading {
  color: #fff; font-size: 1.1rem; font-weight: 800;
  text-shadow: 0 2px 6px rgba(0,0,0,.4); margin-bottom: .25rem;
}
.banner-preview-sub {
  color: rgba(255,255,255,.8); font-size: .78rem; margin-bottom: .6rem;
}
.banner-preview-btn {
  background: #0077B6; color: #fff;
  padding: .3rem .85rem; border-radius: 6px;
  font-size: .78rem; font-weight: 600;
}
</style>

<script>
// Live preview
const fields = {
  heading:    { input: 'heading',    preview: 'previewHeading' },
  subheading: { input: 'subheading', preview: 'previewSub' },
  btn_label:  { input: 'btn_label',  preview: 'previewBtn' },
};

Object.values(fields).forEach(({ input, preview }) => {
  const el = document.querySelector(`[name="${input}"]`);
  if (!el) return;
  el.addEventListener('input', function () {
    const el = document.getElementById(preview);
    if (input === 'heading' && this.value.includes('|')) {
      const [main, accent] = this.value.split('|');
      el.innerHTML = main.trim() + ' <span style="color:#00B4D8;">' + accent.trim() + '</span>';
    } else {
      el.textContent = this.value;
    }
  });
});

// Image preview on replace
document.getElementById('fileInput').addEventListener('change', function () {
  if (this.files[0]) {
    const r = new FileReader();
    r.onload = e => {
      document.getElementById('preview').src = e.target.result;
      document.getElementById('previewLarge').src = e.target.result;
    };
    r.readAsDataURL(this.files[0]);
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
