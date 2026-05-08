<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo    = getPDO();
$errors = [];
$type   = in_array($_GET['type'] ?? '', ['core','additional']) ? ($_GET['type'] ?? 'core') : 'core';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = in_array($_POST['type'] ?? '', ['core','additional']) ? $_POST['type'] : 'core';
    $icon_class  = trim($_POST['icon_class']  ?? '');
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $features    = trim($_POST['features']    ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '')      $errors[] = 'Title is required.';
    if ($icon_class === '') $errors[] = 'Icon class is required.';

    if (empty($errors)) {
        $maxOrder = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM services WHERE type = ?');
        $maxOrder->execute([$type]);
        $next = (int)$maxOrder->fetchColumn() + 1;

        $pdo->prepare('
            INSERT INTO services (type, icon_class, title, description, features, sort_order, is_active)
            VALUES (?,?,?,?,?,?,?)
        ')->execute([$type, $icon_class, $title, $description ?: null, $features ?: null, $next, $is_active]);

        header("Location: index.php?tab=$type&created=1"); exit;
    }
}

$pageTitle = 'Add Service';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-plus-circle me-2 text-primary"></i>Add Service</h1>
  <a href="index.php?tab=<?= htmlspecialchars($type) ?>" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="POST">
      <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

      <div class="admin-card mb-3">
        <div class="card-header">Service Type</div>
        <div class="p-3">
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" id="typeCore"
                     value="core" <?= $type === 'core' ? 'checked' : '' ?>>
              <label class="form-check-label" for="typeCore">
                <i class="bi bi-star me-1 text-primary"></i><strong>Core Service</strong>
                <div class="text-muted small">Main service with detailed features list</div>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" id="typeAdditional"
                     value="additional" <?= $type === 'additional' ? 'checked' : '' ?>>
              <label class="form-check-label" for="typeAdditional">
                <i class="bi bi-plus-square me-1 text-secondary"></i><strong>Additional Service</strong>
                <div class="text-muted small">Compact card with brief description</div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Service Details</div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Icon Class <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text" id="iconPreview" style="width:46px;justify-content:center;font-size:1.2rem;color:#0077B6;">
                <i class="fa-solid fa-circle-question" id="iconEl"></i>
              </span>
              <input type="text" name="icon_class" id="iconInput" class="form-control"
                     value="<?= htmlspecialchars($_POST['icon_class'] ?? '') ?>"
                     placeholder="e.g. fa-plane-departure" required>
            </div>
            <div class="form-text">
              Use Font Awesome 6 solid icons. Browse at
              <a href="https://fontawesome.com/icons?f=sharp&s=solid" target="_blank">fontawesome.com/icons</a>.
              Enter only the icon name (e.g. <code>fa-hotel</code>).
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                   placeholder="e.g. Flight Booking" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4"
                      placeholder="Brief description of this service..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          </div>
          <div id="featuresGroup" class="mb-3">
            <label class="form-label">
              Features / Bullet Points
              <small class="text-muted">(one per line)</small>
            </label>
            <textarea name="features" class="form-control" rows="7"
                      placeholder="International & connecting flight search from 200+ airlines&#10;Best-fare guarantee with flexible date comparison&#10;Economy, business, and first-class bookings"><?= htmlspecialchars($_POST['features'] ?? '') ?></textarea>
            <div class="form-text">Each line becomes a bullet point on the service card.</div>
          </div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Visibility</div>
        <div class="p-3">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                   value="1" <?= !isset($_POST['title']) || isset($_POST['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">
              <i class="bi bi-eye me-1"></i> Active (show on services page)
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i> Save Service
        </button>
        <a href="index.php?tab=<?= htmlspecialchars($type) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <!-- Icon preview card -->
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-eye me-2"></i>Icon Preview</div>
      <div class="p-3 text-center">
        <div id="iconPreviewBox"
             style="width:80px;height:80px;background:linear-gradient(135deg,#0077B6,#00B4D8);
                    border-radius:18px;display:flex;align-items:center;justify-content:center;
                    font-size:2rem;color:#fff;margin:0 auto 1rem;">
          <i class="fa-solid fa-circle-question" id="iconBig"></i>
        </div>
        <div class="fw-semibold" id="titlePreview" style="color:#03045E;">Service Title</div>
      </div>
    </div>

    <!-- Hints -->
    <div class="admin-card">
      <div class="card-header"><i class="bi bi-lightbulb me-2 text-warning"></i>Icon Examples</div>
      <div class="p-3">
        <div class="row g-2">
          <?php
          $examples = [
            'fa-plane-departure' => 'Flight',
            'fa-hotel'           => 'Hotel',
            'fa-user-tie'        => 'Guide',
            'fa-passport'        => 'Visa',
            'fa-car-side'        => 'Transfer',
            'fa-people-group'    => 'Group',
            'fa-shield-heart'    => 'Insurance',
            'fa-camera'          => 'Photo',
            'fa-spa'             => 'Wellness',
            'fa-heart'           => 'Wedding',
            'fa-graduation-cap'  => 'School',
            'fa-briefcase'       => 'Corporate',
          ];
          foreach ($examples as $cls => $label):
          ?>
          <div class="col-4 text-center">
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 icon-pick"
                    data-icon="<?= $cls ?>" style="font-size:.7rem;padding:.3rem .1rem">
              <i class="fa-solid <?= $cls ?> d-block mb-1" style="font-size:1rem;"></i>
              <?= $label ?>
            </button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Icon live preview
const iconInput = document.getElementById('iconInput');
const iconEl    = document.getElementById('iconEl');
const iconBig   = document.getElementById('iconBig');
const titlePrev = document.getElementById('titlePreview');

function updateIcon() {
  const val = iconInput.value.trim();
  iconEl.className  = 'fa-solid ' + (val || 'fa-circle-question');
  iconBig.className = 'fa-solid ' + (val || 'fa-circle-question');
}
iconInput.addEventListener('input', updateIcon);

document.querySelector('[name="title"]').addEventListener('input', function () {
  titlePrev.textContent = this.value || 'Service Title';
});

// Quick-pick icons
document.querySelectorAll('.icon-pick').forEach(btn => {
  btn.addEventListener('click', function () {
    iconInput.value = this.dataset.icon;
    updateIcon();
  });
});

// Show/hide features based on type
function toggleFeatures() {
  const type = document.querySelector('[name="type"]:checked').value;
  document.getElementById('featuresGroup').style.display = type === 'core' ? '' : 'none';
}
document.querySelectorAll('[name="type"]').forEach(r => r.addEventListener('change', toggleFeatures));
toggleFeatures();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
