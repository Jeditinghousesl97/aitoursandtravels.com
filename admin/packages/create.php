<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../assets/php/package-itinerary.php';

$pdo        = getPDO();
$errors     = [];

function normalizePackageCategory(string $category): string
{
    $key = strtolower(trim($category));
    $key = str_replace(['_', ' '], '-', $key);
    if ($key === 'hillcountry' || $key === 'hill-country') return 'hill';
    if ($key === 'roundtour' || $key === 'round-tours') return 'round-tours';
    if ($key === 'mostpopular' || $key === 'most-popular') return 'most-popular';
    if ($key === 'escapetowild' || $key === 'escape-to-wild') return 'escape-to-wild';
    return $key;
}
$categories = [
    'cultural' => 'Cultural',
    'beach' => 'Beach',
    'wildlife' => 'Wildlife',
    'hill' => 'Hill',
    'honeymoon' => 'Honeymoon',
    'adventure' => 'Adventure',
    'sightseeing' => 'Sightseeing',
    'leisure' => 'Leisure',
    'round-tours' => 'Round Tours',
    'most-popular' => 'Most Popular',
    'escape-to-wild' => 'Escape to Wild',
];
$badges     = ['','popular','bestseller','new','limited','hotdeal'];
$badgeLabels= ['' => 'None', 'popular' => 'Popular', 'bestseller' => 'Best Seller',
               'new' => 'New', 'limited' => 'Limited Spots', 'hotdeal' => 'Hot Deal'];
$difficulties = ['easy' => 'Easy', 'moderate' => 'Moderate', 'challenging' => 'Challenging'];
$itineraryFormItems = nt_package_itinerary_form_items($_POST);

function sanitizePackageDescriptionHtml(string $html): string
{
    // Remove executable/style blocks first.
    $html = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\\1\s*>#is', '', $html) ?? '';
    // Keep a safe subset of formatting tags.
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a>';
    $html = strip_tags($html, $allowed);
    // Trim but keep internal spacing/newlines.
    return trim($html);
}

function ensurePackageUploadDir(array &$errors): ?string
{
    $dir = SITE_ROOT . 'uploads/packages/';

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $errors[] = 'Package upload folder could not be created: ' . $dir;
        return null;
    }

    if (!is_writable($dir)) {
        $errors[] = 'Package upload folder is not writable: ' . $dir;
        return null;
    }

    return $dir;
}

function ensurePackagePriceColumnAllowsNull(PDO $pdo, array &$errors): bool
{
    $allowsNull = columnAllowsNull($pdo, 'packages', 'price');

    if ($allowsNull === true) {
        return true;
    }

    try {
        $pdo->exec('ALTER TABLE packages MODIFY price DECIMAL(10,2) NULL DEFAULT NULL');
    } catch (Throwable $e) {
        $errors[] = 'Package price column still requires a value. Please update the database schema for packages.price to allow NULL.';
        return false;
    }

    return true;
}

function ensurePackageCategoryEnum(PDO $pdo, array &$errors): bool
{
    $allowedCategories = [
        'cultural', 'beach', 'wildlife', 'hill', 'honeymoon', 'adventure',
        'sightseeing', 'leisure', 'round-tours', 'most-popular', 'escape-to-wild',
    ];

    try {
        $column = $pdo->query("SHOW COLUMNS FROM packages LIKE 'category'")->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string)($column['Type'] ?? ''));
        $isEnum = str_starts_with($type, 'enum(');
        $isMissingAny = false;
        foreach ($allowedCategories as $cat) {
            if (strpos($type, "'" . strtolower($cat) . "'") === false) {
                $isMissingAny = true;
                break;
            }
        }

        if (!$isEnum || $isMissingAny) {
            $enumSql = "ENUM('" . implode("','", $allowedCategories) . "')";
            $pdo->exec("ALTER TABLE packages MODIFY category {$enumSql} NOT NULL");
        }
    } catch (Throwable $e) {
        $errors[] = 'Package category schema is out of date. Please update the packages.category column.';
        return false;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $slug     = trim($_POST['slug'] ?? '');
    $categoryRaw = (string)($_POST['category'] ?? '');
    $category = normalizePackageCategory($categoryRaw);
    $duration = trim($_POST['duration'] ?? '');
    $price    = ($_POST['price'] ?? '') !== '' ? $_POST['price'] : null;
    $old_price    = ($_POST['old_price'] ?? '') !== '' ? $_POST['old_price'] : null;
    $group_size   = trim($_POST['group_size']   ?? '');
    $description  = sanitizePackageDescriptionHtml((string)($_POST['description'] ?? ''));
    $highlights   = trim($_POST['highlights']   ?? '');
    $itinerary    = trim($_POST['itinerary']    ?? '');
    $inclusions   = trim($_POST['inclusions']   ?? '');
    $exclusions   = trim($_POST['exclusions']   ?? '');
    $badge        = $_POST['badge']      ?? '';
    $best_season  = trim($_POST['best_season']  ?? '');
    $difficulty   = $_POST['difficulty'] ?? 'moderate';
    $rating       = ($_POST['rating'] ?? '') !== '' ? (float)$_POST['rating'] : null;
    $review_count = (int)($_POST['review_count'] ?? 0);
    $is_featured  = isset($_POST['is_featured']) ? 1 : 0;
    $is_active    = isset($_POST['is_active'])   ? 1 : 0;

    // Validation
    if ($title === '')    $errors[] = 'Title is required.';
    if ($slug === '')     $errors[] = 'Slug is required.';
    if ($category === '') $errors[] = 'Category is required.';
    if ($category !== '' && !isset($categories[$category])) {
        $errors[] = 'Invalid category selected.';
    }
    if ($duration === '') $errors[] = 'Duration is required.';
    if ($description === '') $errors[] = 'Description is required.';

    // Check slug unique
    if ($slug !== '') {
        $chk = $pdo->prepare('SELECT id FROM packages WHERE slug = ?');
        $chk->execute([$slug]);
        if ($chk->fetch()) $errors[] = 'Slug already exists. Use a different one.';
    }

    // Handle image upload
    $cover_image = null;
    if (!empty($_FILES['cover_image']['name'])) {
        $file    = $_FILES['cover_image'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Image must be JPG, PNG or WEBP.';
        } elseif ($file['size'] > 25 * 1024 * 1024) {
            $errors[] = 'Image must be under 25MB.';
        } else {
            $uploadDir = ensurePackageUploadDir($errors);
            if ($uploadDir !== null) {
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $name = 'pkg_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . $name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $cover_image = 'uploads/packages/' . $name;
                } else {
                    $errors[] = 'Failed to upload image. Check folder permissions.';
                }
            }
        }
    }

    if (empty($errors) && ensurePackageCategoryEnum($pdo, $errors) && ensurePackagePriceColumnAllowsNull($pdo, $errors)) {
        $stmt = $pdo->prepare('
            INSERT INTO packages
              (title, slug, category, duration, price, old_price, group_size,
               description, highlights, itinerary, inclusions, exclusions,
               badge, best_season, difficulty, rating, review_count,
               cover_image, is_featured, is_active)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $title, $slug, $category, $duration, $price, $old_price, $group_size,
            $description, $highlights, $itinerary, $inclusions, $exclusions,
            $badge ?: null, $best_season ?: null,
            $difficulty, $rating, $review_count,
            $cover_image, $is_featured, $is_active
        ]);
        $packageId = (int)$pdo->lastInsertId();
        nt_save_package_itinerary($pdo, $packageId, $_POST, $_FILES, [], $errors);
        if (empty($errors)) {
            header('Location: index.php?created=1');
            exit;
        }

        $pdo->prepare('DELETE FROM packages WHERE id = ?')->execute([$packageId]);
        $itineraryFormItems = nt_package_itinerary_form_items($_POST);
    }
}

$pageTitle = 'Add Package';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Package</h1>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <div class="row g-3">

    <!-- LEFT COLUMN -->
    <div class="col-lg-8">

      <!-- Basic Info -->
      <div class="admin-card mb-3">
        <div class="card-header">Basic Information</div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Package Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="titleInput" class="form-control"
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                   placeholder="e.g. Cultural Triangle Explorer" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Slug <span class="text-danger">*</span>
              <small class="text-muted">(URL-friendly, auto-generated)</small>
            </label>
            <input type="text" name="slug" id="slugInput" class="form-control"
                   value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>"
                   placeholder="cultural-triangle-explorer" required>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select name="category" class="form-select" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $cat => $catLabel): ?>
                  <option value="<?= $cat ?>"
                    <?= normalizePackageCategory((string)($_POST['category'] ?? '')) === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($catLabel) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Duration <span class="text-danger">*</span></label>
              <input type="text" name="duration" class="form-control"
                     value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>"
                     placeholder="e.g. 5 Days / 4 Nights" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Best Season</label>
              <input type="text" name="best_season" class="form-control"
                     value="<?= htmlspecialchars($_POST['best_season'] ?? '') ?>"
                     placeholder="e.g. December – April">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Difficulty</label>
              <select name="difficulty" class="form-select">
                <?php foreach ($difficulties as $val => $label): ?>
                  <option value="<?= $val ?>" <?= ($_POST['difficulty'] ?? 'moderate') === $val ? 'selected' : '' ?>>
                    <?= $label ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Pricing -->
      <div class="admin-card mb-3">
        <div class="card-header">Pricing</div>
        <div class="p-3">
          <div class="row g-3">
            <div class="col-sm-4">
              <label class="form-label">Price (USD) <small class="text-muted">(optional)</small></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="price" class="form-control" step="0.01" min="0"
                       value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
              </div>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Old Price <small class="text-muted">(optional)</small></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="old_price" class="form-control" step="0.01" min="0"
                       value="<?= htmlspecialchars($_POST['old_price'] ?? '') ?>">
              </div>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Group Size</label>
              <input type="text" name="group_size" class="form-control"
                     value="<?= htmlspecialchars($_POST['group_size'] ?? '') ?>"
                     placeholder="e.g. 2–15 People">
            </div>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div class="admin-card mb-3">
        <div class="card-header">Description <span class="text-danger">*</span></div>
        <div class="p-3">
          <textarea name="description" class="form-control" rows="5"
                    placeholder="Write a detailed package description..."
                    required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Highlights -->
      <div class="admin-card mb-3">
        <div class="card-header">Highlights
          <small class="text-muted fw-normal ms-2">One per line</small>
        </div>
        <div class="p-3">
          <textarea name="highlights" class="form-control" rows="5"
                    placeholder="Visit Sigiriya Rock Fortress&#10;Explore Dambulla Cave Temple&#10;Sunset at Polonnaruwa"><?= htmlspecialchars($_POST['highlights'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Itinerary -->
      <div class="admin-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Itinerary Stops</span>
          <button type="button" class="btn btn-sm btn-outline-primary" id="addItineraryItem">
            <i class="bi bi-plus-lg me-1"></i>Add Stop
          </button>
        </div>
        <div class="p-3">
          <div class="form-text mb-3">Add each itinerary stop with a title and description. Photos are optional.</div>
          <div id="itineraryItems">
            <?php foreach ($itineraryFormItems as $index => $item): ?>
            <div class="itinerary-editor-item" data-index="<?= $index ?>">
              <div class="itinerary-editor-head">
                <strong>Stop <span class="itinerary-item-number"><?= $index + 1 ?></span></strong>
                <button type="button" class="btn btn-sm btn-outline-danger remove-itinerary-item">Remove</button>
              </div>
              <input type="hidden" name="itinerary_item_id[]" value="0">
              <input type="hidden" name="itinerary_existing_image_1[]" value="">
              <input type="hidden" name="itinerary_existing_image_2[]" value="">
              <input type="hidden" name="itinerary_remove_image_1[]" value="0" class="itinerary-remove-1">
              <input type="hidden" name="itinerary_remove_image_2[]" value="0" class="itinerary-remove-2">
              <div class="row g-3">
                <div class="col-md-5">
                  <label class="form-label">Title</label>
                  <input type="text" name="itinerary_title[]" class="form-control"
                         placeholder="01 - Visit Kandy"
                         value="<?= htmlspecialchars($item['title']) ?>">
                </div>
                <div class="col-md-7">
                  <label class="form-label">Description</label>
                  <textarea name="itinerary_description[]" class="form-control" rows="3"
                            placeholder="Description goes here."><?= htmlspecialchars($item['description']) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Photo 1</label>
                  <input type="file" name="itinerary_image_1[]" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Photo 2</label>
                  <input type="file" name="itinerary_image_2[]" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <textarea name="itinerary" class="form-control mt-3" rows="5"
                    placeholder="Optional legacy itinerary text for older packages."><?= htmlspecialchars($_POST['itinerary'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Inclusions & Exclusions -->
      <div class="row g-3">
        <div class="col-md-6">
          <div class="admin-card mb-3">
            <div class="card-header">Inclusions
              <small class="text-muted fw-normal ms-2">One per line</small>
            </div>
            <div class="p-3">
              <textarea name="inclusions" class="form-control" rows="6"
                        placeholder="Accommodation&#10;All meals&#10;Transport&#10;Tour guide"><?= htmlspecialchars($_POST['inclusions'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="admin-card mb-3">
            <div class="card-header">Exclusions
              <small class="text-muted fw-normal ms-2">One per line</small>
            </div>
            <div class="p-3">
              <textarea name="exclusions" class="form-control" rows="6"
                        placeholder="International flights&#10;Travel insurance&#10;Personal expenses"><?= htmlspecialchars($_POST['exclusions'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-lg-4">

      <!-- Cover Image -->
      <div class="admin-card mb-3">
        <div class="card-header">Cover Image</div>
        <div class="p-3">
          <div id="imagePreviewWrap" class="mb-3 d-none">
            <img id="imagePreview" src="" alt="Preview"
                 style="width:100%;height:180px;object-fit:cover;border-radius:8px;">
          </div>
          <input type="file" name="cover_image" id="imageInput"
                 class="form-control" accept="image/jpeg,image/png,image/webp">
          <div class="form-text">JPG, PNG or WEBP. Max 3MB.</div>
        </div>
      </div>

      <!-- Badge & Rating -->
      <div class="admin-card mb-3">
        <div class="card-header">Badge & Rating</div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Card Badge</label>
            <select name="badge" class="form-select">
              <?php foreach ($badgeLabels as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($_POST['badge'] ?? '') === $val ? 'selected' : '' ?>>
                  <?= $label ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Shown as a label on the package image.</div>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Rating</label>
              <input type="number" name="rating" class="form-control" step="0.1" min="0" max="5"
                     value="<?= htmlspecialchars($_POST['rating'] ?? '') ?>"
                     placeholder="4.9">
            </div>
            <div class="col-6">
              <label class="form-label">Review Count</label>
              <input type="number" name="review_count" class="form-control" min="0"
                     value="<?= htmlspecialchars($_POST['review_count'] ?? '0') ?>"
                     placeholder="128">
            </div>
          </div>
        </div>
      </div>

      <!-- Settings -->
      <div class="admin-card mb-3">
        <div class="card-header">Settings</div>
        <div class="p-3">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_active"
                   id="isActive" value="1"
                   <?= !isset($_POST['title']) || isset($_POST['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">
              <i class="bi bi-eye me-1"></i> Active (visible on website)
            </label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_featured"
                   id="isFeatured" value="1"
                   <?= isset($_POST['is_featured']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isFeatured">
              <i class="bi bi-star me-1"></i> Featured package
            </label>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i> Save Package
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>

    </div>
  </div>
</form>

<template id="itineraryItemTemplate">
  <div class="itinerary-editor-item" data-index="__INDEX__">
    <div class="itinerary-editor-head">
      <strong>Stop <span class="itinerary-item-number">__NUMBER__</span></strong>
      <button type="button" class="btn btn-sm btn-outline-danger remove-itinerary-item">Remove</button>
    </div>
    <input type="hidden" name="itinerary_item_id[]" value="0">
    <input type="hidden" name="itinerary_existing_image_1[]" value="">
    <input type="hidden" name="itinerary_existing_image_2[]" value="">
    <input type="hidden" name="itinerary_remove_image_1[]" value="0" class="itinerary-remove-1">
    <input type="hidden" name="itinerary_remove_image_2[]" value="0" class="itinerary-remove-2">
    <div class="row g-3">
      <div class="col-md-5">
        <label class="form-label">Title</label>
        <input type="text" name="itinerary_title[]" class="form-control" placeholder="01 - Visit Kandy">
      </div>
      <div class="col-md-7">
        <label class="form-label">Description</label>
        <textarea name="itinerary_description[]" class="form-control" rows="3" placeholder="Description goes here."></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Photo 1</label>
        <input type="file" name="itinerary_image_1[]" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>
      <div class="col-md-6">
        <label class="form-label">Photo 2</label>
        <input type="file" name="itinerary_image_2[]" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>
    </div>
  </div>
</template>

<script>
// Auto-generate slug from title
document.getElementById('titleInput').addEventListener('input', function () {
  const slugInput = document.getElementById('slugInput');
  if (slugInput.dataset.manual) return;
  slugInput.value = this.value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
});

document.getElementById('slugInput').addEventListener('input', function () {
  this.dataset.manual = 'true';
});

// Image preview
document.getElementById('imageInput').addEventListener('change', function () {
  const wrap    = document.getElementById('imagePreviewWrap');
  const preview = document.getElementById('imagePreview');
  if (this.files && this.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      preview.src = e.target.result;
      wrap.classList.remove('d-none');
    };
    reader.readAsDataURL(this.files[0]);
  }
});

const itineraryWrap = document.getElementById('itineraryItems');
const itineraryTemplate = document.getElementById('itineraryItemTemplate');
const addItineraryBtn = document.getElementById('addItineraryItem');

function refreshItineraryNumbers() {
  document.querySelectorAll('.itinerary-editor-item').forEach((item, index) => {
    item.dataset.index = String(index);
    const numberEl = item.querySelector('.itinerary-item-number');
    if (numberEl) numberEl.textContent = String(index + 1);
  });
}

function bindItineraryItem(item) {
  const removeBtn = item.querySelector('.remove-itinerary-item');
  if (!removeBtn) return;

  removeBtn.addEventListener('click', () => {
    item.remove();
    if (!itineraryWrap.children.length) {
      addItineraryBtn.click();
    }
    refreshItineraryNumbers();
  });
}

document.querySelectorAll('.itinerary-editor-item').forEach(bindItineraryItem);

addItineraryBtn.addEventListener('click', () => {
  const index = itineraryWrap.children.length;
  const html = itineraryTemplate.innerHTML
    .replace('__INDEX__', String(index))
    .replace('__NUMBER__', String(index + 1));
  itineraryWrap.insertAdjacentHTML('beforeend', html);
  bindItineraryItem(itineraryWrap.lastElementChild);
  refreshItineraryNumbers();
});
</script>

<style>
.itinerary-editor-item {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1rem;
  background: #f8fbff;
}
.itinerary-editor-item + .itinerary-editor-item {
  margin-top: 1rem;
}
.itinerary-editor-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
