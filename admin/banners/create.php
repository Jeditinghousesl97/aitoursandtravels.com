<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo    = getPDO();
$errors = [];

if (!columnExists($pdo, 'hero_banners', 'badge_text')) {
    $pdo->exec("ALTER TABLE hero_banners ADD COLUMN badge_text VARCHAR(150) DEFAULT NULL AFTER image_path");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $badge_text = trim($_POST['badge_text'] ?? '');
    $heading    = trim($_POST['heading'] ?? '');
    $subheading = trim($_POST['subheading'] ?? '');
    $btn_label  = trim($_POST['btn_label'] ?? 'Explore Now');
    $btn_link   = trim($_POST['btn_link'] ?? '#packages');
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    if ($heading === '') $errors[] = 'Heading is required.';

    // Image upload
    $image_path = null;
    if (empty($_FILES['image']['name'])) {
        $errors[] = 'Banner image is required.';
    } else {
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
                $image_path = 'uploads/banners/' . $name;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    }

    if (empty($errors)) {
        $maxOrder = $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM hero_banners')->fetchColumn();
        $pdo->prepare('
            INSERT INTO hero_banners (badge_text, heading, subheading, image_path, btn_label, btn_link, sort_order, is_active)
            VALUES (?,?,?,?,?,?,?,?)
        ')->execute([$badge_text ?: null, $heading, $subheading ?: null, $image_path, $btn_label, $btn_link, $maxOrder + 1, $is_active]);
        header('Location: index.php?created=1'); exit;
    }
}

$pageTitle = 'Add Banner';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-plus-circle me-2 text-primary"></i>Add Banner</h1>
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
                   value="<?= htmlspecialchars($_POST['badge_text'] ?? '') ?>"
                   placeholder="e.g. Welcome to ASB Tours">
            <div class="form-text">Small text shown above the main heading on this slide.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Heading <span class="text-danger">*</span></label>
            <input type="text" name="heading" class="form-control"
                   value="<?= htmlspecialchars($_POST['heading'] ?? '') ?>"
                   placeholder="e.g. Discover the Pearl of | the Indian Ocean" required>
            <div class="form-text">Use <code>|</code> to split into two colours — text before <code>|</code> is white, text after is the accent colour. Example: <em>Discover the Pearl of | the Indian Ocean</em></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Subheading <small class="text-muted">(optional)</small></label>
            <input type="text" name="subheading" class="form-control"
                   value="<?= htmlspecialchars($_POST['subheading'] ?? '') ?>"
                   placeholder="e.g. Experience the beauty of the pearl of the Indian Ocean">
          </div>
          <div class="row g-3">
            <div class="col-sm-5">
              <label class="form-label">Button Label</label>
              <input type="text" name="btn_label" class="form-control"
                     value="<?= htmlspecialchars($_POST['btn_label'] ?? 'Explore Now') ?>">
            </div>
            <div class="col-sm-7">
              <label class="form-label">Button Link</label>
              <input type="text" name="btn_link" class="form-control"
                     value="<?= htmlspecialchars($_POST['btn_link'] ?? '#packages') ?>"
                     placeholder="#packages or /pages/packages.html">
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Banner Image <span class="text-danger">*</span>
          <small class="text-muted fw-normal ms-2">Recommended: 1920×900px</small>
        </div>
        <div class="p-3">
          <div class="drop-zone" id="dropZone">
            <i class="bi bi-cloud-upload fs-2 text-primary mb-2"></i>
            <p class="mb-1 fw-semibold">Drag & drop image here</p>
            <p class="text-muted small mb-3">or click to browse</p>
            <input type="file" name="image" id="fileInput"
                   accept="image/jpeg,image/png,image/webp" style="display:none">
            <button type="button" class="btn btn-outline-primary btn-sm"
                    onclick="document.getElementById('fileInput').click()">
              Browse Image
            </button>
          </div>
          <div id="previewWrap" class="mt-3 d-none">
            <img id="preview" src=""
                 style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;">
          </div>
          <div class="form-text mt-2">JPG, PNG or WEBP. Max 5MB. Use wide landscape images.</div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Settings</div>
        <div class="p-3">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active"
                   id="isActive" value="1"
                   <?= !isset($_POST['heading']) || isset($_POST['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">
              <i class="bi bi-eye me-1"></i> Active (show on homepage)
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i> Save Banner
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>

    </form>
  </div>

  <!-- Tips -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="card-header"><i class="bi bi-lightbulb me-2 text-warning"></i>Image Tips</div>
      <div class="p-3">
        <ul class="list-unstyled mb-0" style="font-size:.875rem;line-height:2;">
          <li><i class="bi bi-check-circle text-success me-2"></i>Use <strong>landscape</strong> images (wide)</li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Recommended size: <strong>1920 × 900px</strong></li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Keep file under <strong>5MB</strong></li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Use high-quality travel photos</li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Avoid text-heavy images (heading overlays)</li>
          <li><i class="bi bi-info-circle text-primary me-2"></i>JPG is best for photos</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<style>
.drop-zone {
  border: 2px dashed #cbd5e0;
  border-radius: 12px;
  padding: 2rem;
  text-align: center;
  background: #f7fafc;
  cursor: pointer;
  transition: border-color .2s, background .2s;
}
.drop-zone.dragover { border-color: #0077B6; background: #e0f4fc; }
</style>

<script>
const fileInput  = document.getElementById('fileInput');
const dropZone   = document.getElementById('dropZone');
const previewWrap = document.getElementById('previewWrap');
const preview    = document.getElementById('preview');

function handleFile(file) {
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    preview.src = e.target.result;
    previewWrap.classList.remove('d-none');
  };
  reader.readAsDataURL(file);
}

fileInput.addEventListener('change', () => handleFile(fileInput.files[0]));
dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('dragover');
  if (e.dataTransfer.files[0]) {
    fileInput.files = e.dataTransfer.files;
    handleFile(e.dataTransfer.files[0]);
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
