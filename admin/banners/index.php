<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

if (!columnExists($pdo, 'hero_banners', 'badge_text')) {
    $pdo->exec("ALTER TABLE hero_banners ADD COLUMN badge_text VARCHAR(150) DEFAULT NULL AFTER image_path");
}

// Toggle active
if (isset($_GET['toggle'])) {
    $pdo->prepare('UPDATE hero_banners SET is_active = NOT is_active WHERE id = ?')
        ->execute([(int)$_GET['toggle']]);
    header('Location: index.php'); exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $row = $pdo->prepare('SELECT image_path FROM hero_banners WHERE id = ?');
    $row->execute([$id]);
    $row = $row->fetch();
    if ($row) {
        $f = __DIR__ . '/../../' . $row['image_path'];
        if (file_exists($f)) unlink($f);
        $pdo->prepare('DELETE FROM hero_banners WHERE id = ?')->execute([$id]);
    }
    header('Location: index.php?deleted=1'); exit;
}

// Move order up/down
if (isset($_GET['move'])) {
    $id        = (int)$_GET['id'];
    $direction = $_GET['move'];
    $current   = $pdo->prepare('SELECT sort_order FROM hero_banners WHERE id = ?');
    $current->execute([$id]);
    $current = $current->fetchColumn();

    if ($direction === 'up') {
        $swap = $pdo->prepare('SELECT id, sort_order FROM hero_banners WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1');
    } else {
        $swap = $pdo->prepare('SELECT id, sort_order FROM hero_banners WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1');
    }
    $swap->execute([$current]);
    $swap = $swap->fetch();

    if ($swap) {
        $pdo->prepare('UPDATE hero_banners SET sort_order = ? WHERE id = ?')->execute([$swap['sort_order'], $id]);
        $pdo->prepare('UPDATE hero_banners SET sort_order = ? WHERE id = ?')->execute([$current, $swap['id']]);
    }
    header('Location: index.php'); exit;
}

$banners = $pdo->query('SELECT * FROM hero_banners ORDER BY sort_order ASC, id ASC')->fetchAll();

$pageTitle = 'Hero Banners';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-image me-2 text-primary"></i>Hero Banners</h1>
  <a href="create.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i> Add Banner
  </a>
</div>

<?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Banner created successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php elseif (isset($_GET['updated'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Banner updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php elseif (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Banner deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="alert alert-info d-flex align-items-center gap-2 py-2">
  <i class="bi bi-info-circle-fill"></i>
  <span>These banners appear in the homepage hero slider. Use the arrows to reorder them.</span>
</div>

<!-- Banner Cards -->
<?php if ($banners): ?>
  <div class="banners-list">
    <?php foreach ($banners as $i => $b): ?>
      <div class="banner-card <?= !$b['is_active'] ? 'banner-inactive' : '' ?>">

        <!-- Image Preview -->
        <div class="banner-image">
          <img src="<?= htmlspecialchars(site_url($b['image_path'])) ?>"
               alt="<?= htmlspecialchars($b['heading']) ?>">
          <div class="banner-image-overlay">
            <span class="banner-order-badge">#<?= $i + 1 ?></span>
            <?php if (!$b['is_active']): ?>
              <span class="banner-hidden-badge">
                <i class="bi bi-eye-slash me-1"></i>Hidden
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Content -->
        <div class="banner-content">
          <?php if (!empty($b['badge_text'])): ?>
            <div class="banner-subheading"><i class="bi bi-bookmark-star me-1"></i><?= htmlspecialchars($b['badge_text']) ?></div>
          <?php endif; ?>
          <div class="banner-heading"><?= htmlspecialchars($b['heading']) ?></div>
          <?php if ($b['subheading']): ?>
            <div class="banner-subheading"><?= htmlspecialchars($b['subheading']) ?></div>
          <?php endif; ?>
          <div class="banner-btn-preview">
            <i class="bi bi-cursor me-1 text-muted"></i>
            <span class="badge bg-light text-dark border">
              <?= htmlspecialchars($b['btn_label']) ?>
            </span>
            <span class="text-muted small ms-1">→ <?= htmlspecialchars($b['btn_link']) ?></span>
          </div>
        </div>

        <!-- Actions -->
        <div class="banner-actions">
          <!-- Reorder -->
          <div class="d-flex flex-column gap-1">
            <a href="?move=up&id=<?= $b['id'] ?>"
               class="btn btn-sm btn-outline-secondary <?= $i === 0 ? 'disabled' : '' ?>" title="Move Up">
              <i class="bi bi-chevron-up"></i>
            </a>
            <a href="?move=down&id=<?= $b['id'] ?>"
               class="btn btn-sm btn-outline-secondary <?= $i === count($banners) - 1 ? 'disabled' : '' ?>" title="Move Down">
              <i class="bi bi-chevron-down"></i>
            </a>
          </div>

          <div class="vr mx-1"></div>

          <!-- Toggle / Edit / Delete -->
          <div class="tbl-actions">
            <a href="?toggle=<?= $b['id'] ?>"
               class="btn btn-sm <?= $b['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
               title="<?= $b['is_active'] ? 'Hide' : 'Show' ?>">
              <i class="bi bi-<?= $b['is_active'] ? 'eye' : 'eye-slash' ?>"></i>
            </a>
            <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"
               onclick="return confirm('Delete this banner?')">
              <i class="bi bi-trash"></i>
            </a>
          </div>
        </div>

      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="admin-card">
    <div class="text-center text-muted py-5">
      <i class="bi bi-image fs-1 d-block mb-3 opacity-25"></i>
      <p class="mb-2">No banners yet.</p>
      <a href="create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add your first banner
      </a>
    </div>
  </div>
<?php endif; ?>

<style>
.banners-list { display: flex; flex-direction: column; gap: 1rem; }

.banner-card {
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 2px 10px rgba(0,119,182,.07);
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  overflow: hidden;
  transition: box-shadow .2s;
}

.banner-card:hover { box-shadow: 0 6px 24px rgba(0,119,182,.13); }
.banner-inactive { opacity: .6; }

.banner-image {
  position: relative;
  width: 220px;
  height: 110px;
  flex-shrink: 0;
  background: #e2e8f0;
}

.banner-image img {
  width: 100%; height: 100%;
  object-fit: cover;
  display: block;
}

.banner-image-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(3,4,94,.45) 0%, transparent 60%);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: .5rem;
}

.banner-order-badge {
  background: rgba(255,255,255,.9);
  color: #03045E;
  font-size: .75rem;
  font-weight: 700;
  padding: .15rem .5rem;
  border-radius: 999px;
}

.banner-hidden-badge {
  background: rgba(0,0,0,.55);
  color: #fff;
  font-size: .72rem;
  padding: .15rem .5rem;
  border-radius: 999px;
}

.banner-content {
  flex: 1;
  padding: .5rem 0;
  min-width: 0;
}

.banner-heading {
  font-size: 1rem;
  font-weight: 700;
  color: #03045E;
  margin-bottom: .2rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.banner-subheading {
  font-size: .82rem;
  color: #718096;
  margin-bottom: .4rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.banner-btn-preview { font-size: .8rem; }

.banner-actions {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: 1rem;
  flex-shrink: 0;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
