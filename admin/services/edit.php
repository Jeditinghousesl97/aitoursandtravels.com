<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
$stmt->execute([$id]);
$service = $stmt->fetch();
if (!$service) { header('Location: index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $icon_class  = trim($_POST['icon_class']  ?? '');
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $features    = trim($_POST['features']    ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '')      $errors[] = 'Title is required.';
    if ($icon_class === '') $errors[] = 'Icon class is required.';

    if (empty($errors)) {
        $pdo->prepare('
            UPDATE services SET icon_class=?, title=?, description=?, features=?, is_active=?
            WHERE id=?
        ')->execute([$icon_class, $title, $description ?: null, $features ?: null, $is_active, $id]);

        header("Location: index.php?tab={$service['type']}&updated=1"); exit;
    }

    $service = array_merge($service, $_POST);
}

$pageTitle = 'Edit Service';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-pencil me-2 text-primary"></i>Edit Service</h1>
  <a href="index.php?tab=<?= htmlspecialchars($service['type']) ?>" class="btn btn-outline-secondary">
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

      <div class="admin-card mb-3">
        <div class="card-header">Service Details
          <span class="badge <?= $service['type'] === 'core' ? 'bg-primary' : 'bg-secondary' ?> ms-2">
            <?= ucfirst($service['type']) ?>
          </span>
        </div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Icon Class <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text" style="width:46px;justify-content:center;font-size:1.2rem;color:#0077B6;">
                <i class="fa-solid <?= htmlspecialchars($service['icon_class']) ?>" id="iconEl"></i>
              </span>
              <input type="text" name="icon_class" id="iconInput" class="form-control"
                     value="<?= htmlspecialchars($service['icon_class']) ?>"
                     placeholder="e.g. fa-plane-departure" required>
            </div>
            <div class="form-text">
              Font Awesome 6 solid icon name (e.g. <code>fa-hotel</code>).
              Browse at <a href="https://fontawesome.com/icons?f=sharp&s=solid" target="_blank">fontawesome.com/icons</a>.
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="<?= htmlspecialchars($service['title']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
          </div>
          <?php if ($service['type'] === 'core'): ?>
          <div class="mb-3">
            <label class="form-label">
              Features / Bullet Points
              <small class="text-muted">(one per line)</small>
            </label>
            <textarea name="features" class="form-control" rows="7"><?= htmlspecialchars($service['features'] ?? '') ?></textarea>
            <div class="form-text">Each line becomes a bullet point on the service card.</div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Visibility</div>
        <div class="p-3">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                   value="1" <?= $service['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">
              <i class="bi bi-eye me-1"></i> Active (show on services page)
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i> Update Service
        </button>
        <a href="index.php?tab=<?= htmlspecialchars($service['type']) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-eye me-2"></i>Icon Preview</div>
      <div class="p-3 text-center">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,#0077B6,#00B4D8);
                    border-radius:18px;display:flex;align-items:center;justify-content:center;
                    font-size:2rem;color:#fff;margin:0 auto 1rem;">
          <i class="fa-solid <?= htmlspecialchars($service['icon_class']) ?>" id="iconBig"></i>
        </div>
        <div class="fw-semibold" id="titlePreview" style="color:#03045E;">
          <?= htmlspecialchars($service['title']) ?>
        </div>
      </div>
    </div>

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

document.querySelectorAll('.icon-pick').forEach(btn => {
  btn.addEventListener('click', function () {
    iconInput.value = this.dataset.icon;
    updateIcon();
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
