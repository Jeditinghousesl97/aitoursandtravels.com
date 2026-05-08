<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

function normalizePackageCategory(string $category): string
{
    $key = strtolower(trim($category));
    $key = str_replace(['_', ' '], '-', $key);
    if ($key === 'hillcountry' || $key === 'hill-country') {
        return 'hill';
    }
    if ($key === 'roundtour' || $key === 'round-tours') {
        return 'round-tours';
    }
    if ($key === 'mostpopular' || $key === 'most-popular') {
        return 'most-popular';
    }
    if ($key === 'escapetowild' || $key === 'escape-to-wild') {
        return 'escape-to-wild';
    }
    return $key;
}

// Toggle active
if (isset($_GET['toggle_active'])) {
    $id  = (int)$_GET['toggle_active'];
    $pdo->prepare('UPDATE packages SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    header('Location: index.php'); exit;
}

// Toggle featured
if (isset($_GET['toggle_featured'])) {
    $id = (int)$_GET['toggle_featured'];
    $pdo->prepare('UPDATE packages SET is_featured = NOT is_featured WHERE id = ?')->execute([$id]);
    header('Location: index.php'); exit;
}

// Search / filter
$search   = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(title LIKE ? OR duration LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category !== '') {
    $where[]  = 'category = ?';
    $params[] = $category;
}

$sql = 'SELECT * FROM packages';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$packages = $stmt->fetchAll();

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

function packageCategoryLabel(?string $rawCategory, array $categories): string
{
    $raw = trim((string)$rawCategory);
    if ($raw === '') {
        return 'Uncategorized';
    }
    $normalized = normalizePackageCategory($raw);
    if (isset($categories[$normalized])) {
        return $categories[$normalized];
    }
    $pretty = str_replace(['-', '_'], ' ', $raw);
    return ucwords($pretty);
}

$pageTitle = 'Packages';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-suitcase-lg me-2 text-primary"></i>Tour Packages</h1>
  <a href="create.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i> Add Package
  </a>
</div>

<?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Package created successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php elseif (isset($_GET['updated'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Package updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php elseif (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Package deleted successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Filters -->
<div class="admin-card mb-3">
  <div class="card-header">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Search by title or duration..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="category" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat => $catLabel): ?>
            <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
              <?= htmlspecialchars($catLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="admin-card">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:60px;">Image</th>
          <th>Title</th>
          <th>Category</th>
          <th>Duration</th>
          <th>Price</th>
          <th>Featured</th>
          <th>Active</th>
          <th style="width:120px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($packages): ?>
          <?php foreach ($packages as $pkg): ?>
            <tr>
              <td>
                <?php if ($pkg['cover_image']): ?>
                  <img src="<?= htmlspecialchars(site_url($pkg['cover_image'])) ?>"
                       style="width:52px;height:40px;object-fit:cover;border-radius:6px;">
                <?php else: ?>
                  <div style="width:52px;height:40px;background:#e2e8f0;border-radius:6px;
                              display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-image text-muted"></i>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($pkg['title']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($pkg['slug']) ?></div>
              </td>
              <td>
                <span class="badge rounded-pill" style="background:#e0f4fc;color:#0077B6;">
                  <?= htmlspecialchars(packageCategoryLabel($pkg['category'] ?? '', $categories)) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($pkg['duration']) ?></td>
              <td>
                <?php if ($pkg['price'] !== null && $pkg['price'] !== ''): ?>
                  <div class="fw-semibold">$<?= number_format((float)$pkg['price'], 2) ?></div>
                <?php else: ?>
                  <div class="fw-semibold text-muted">Contact Us</div>
                <?php endif; ?>
                <?php if ($pkg['old_price']): ?>
                  <div class="text-muted small text-decoration-line-through">
                    $<?= number_format((float)$pkg['old_price'], 2) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <a href="?toggle_featured=<?= $pkg['id'] ?>"
                   title="Toggle Featured"
                   class="btn btn-sm <?= $pkg['is_featured'] ? 'btn-warning' : 'btn-outline-secondary' ?>">
                  <i class="bi bi-star<?= $pkg['is_featured'] ? '-fill' : '' ?>"></i>
                </a>
              </td>
              <td>
                <a href="?toggle_active=<?= $pkg['id'] ?>"
                   title="Toggle Active"
                   class="btn btn-sm <?= $pkg['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                  <i class="bi bi-<?= $pkg['is_active'] ? 'eye' : 'eye-slash' ?>"></i>
                </a>
              </td>
              <td>
                <div class="tbl-actions">
                  <a href="<?= htmlspecialchars(site_url('pages/package-detail.php?slug=' . urlencode($pkg['slug']))) ?>"
                     class="btn btn-sm btn-outline-secondary" title="View on site" target="_blank">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="edit.php?id=<?= $pkg['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="delete.php?id=<?= $pkg['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"
                     onclick="return confirm('Delete this package?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              <i class="bi bi-suitcase-lg fs-3 d-block mb-2 opacity-50"></i>
              No packages found.
              <a href="create.php">Add your first package</a>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
