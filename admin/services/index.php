<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

$tab = $_GET['tab'] ?? 'core';
if (!in_array($tab, ['core','additional'])) $tab = 'core';

// Toggle active
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    $pdo->prepare('UPDATE services SET is_active = 1 - is_active WHERE id = ?')->execute([$sid]);
    header("Location: index.php?tab=$tab"); exit;
}

// Reorder (swap sort_order with previous/next)
if (isset($_GET['move']) && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    $dir = $_GET['move'] === 'up' ? 'up' : 'down';
    $row = $pdo->prepare('SELECT sort_order FROM services WHERE id = ?');
    $row->execute([$sid]);
    $cur = $row->fetchColumn();
    if ($cur !== false) {
        if ($dir === 'up') {
            $swap = $pdo->prepare('SELECT id, sort_order FROM services WHERE type = (SELECT type FROM services WHERE id=?) AND sort_order < ? ORDER BY sort_order DESC LIMIT 1');
        } else {
            $swap = $pdo->prepare('SELECT id, sort_order FROM services WHERE type = (SELECT type FROM services WHERE id=?) AND sort_order > ? ORDER BY sort_order ASC LIMIT 1');
        }
        $swap->execute([$sid, $cur]);
        $other = $swap->fetch();
        if ($other) {
            $pdo->prepare('UPDATE services SET sort_order=? WHERE id=?')->execute([$other['sort_order'], $sid]);
            $pdo->prepare('UPDATE services SET sort_order=? WHERE id=?')->execute([$cur, $other['id']]);
        }
    }
    header("Location: index.php?tab=$tab"); exit;
}

$coreServices       = $pdo->query("SELECT * FROM services WHERE type='core'       ORDER BY sort_order, id")->fetchAll();
$additionalServices = $pdo->query("SELECT * FROM services WHERE type='additional' ORDER BY sort_order, id")->fetchAll();

$pageTitle = 'Services';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-grid-3x2-gap me-2 text-primary"></i>Services</h1>
  <div class="d-flex gap-2">
    <a href="settings.php" class="btn btn-outline-secondary">
      <i class="bi bi-sliders me-1"></i> Page Settings
    </a>
    <a href="create.php?type=<?= $tab ?>" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i> Add <?= $tab === 'core' ? 'Core' : 'Additional' ?> Service
    </a>
  </div>
</div>

<?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>Service created successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>Service updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-warning alert-dismissible fade show">
    <i class="bi bi-trash me-2"></i>Service deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'core' ? 'active' : '' ?>" href="index.php?tab=core">
      <i class="bi bi-star me-1"></i> Core Services
      <span class="badge bg-primary ms-1"><?= count($coreServices) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'additional' ? 'active' : '' ?>" href="index.php?tab=additional">
      <i class="bi bi-plus-square me-1"></i> Additional Services
      <span class="badge bg-secondary ms-1"><?= count($additionalServices) ?></span>
    </a>
  </li>
</ul>

<?php $services = $tab === 'core' ? $coreServices : $additionalServices; ?>

<div class="admin-card">
  <?php if (empty($services)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
      No <?= $tab ?> services yet.
      <a href="create.php?type=<?= $tab ?>">Add one now</a>
    </div>
  <?php else: ?>
  <table class="table admin-table mb-0">
    <thead>
      <tr>
        <th style="width:50px">Order</th>
        <th style="width:60px">Icon</th>
        <th>Title</th>
        <?php if ($tab === 'core'): ?>
        <th>Features</th>
        <?php endif; ?>
        <th style="width:90px">Status</th>
        <th style="width:130px">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($services as $i => $s): ?>
      <tr>
        <td>
          <div class="d-flex flex-column gap-1">
            <?php if ($i > 0): ?>
            <a href="index.php?tab=<?= $tab ?>&move=up&id=<?= $s['id'] ?>"
               class="btn btn-xs btn-outline-secondary p-0 px-1" title="Move up">
              <i class="bi bi-chevron-up"></i>
            </a>
            <?php endif; ?>
            <?php if ($i < count($services) - 1): ?>
            <a href="index.php?tab=<?= $tab ?>&move=down&id=<?= $s['id'] ?>"
               class="btn btn-xs btn-outline-secondary p-0 px-1" title="Move down">
              <i class="bi bi-chevron-down"></i>
            </a>
            <?php endif; ?>
          </div>
        </td>
        <td>
          <div style="width:42px;height:42px;background:linear-gradient(135deg,#0077B6,#00B4D8);
                      border-radius:10px;display:flex;align-items:center;justify-content:center;
                      color:#fff;font-size:1.1rem;">
            <i class="fa-solid <?= htmlspecialchars($s['icon_class']) ?>"></i>
          </div>
        </td>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($s['title']) ?></div>
          <?php if ($s['description']): ?>
          <div class="text-muted small" style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= htmlspecialchars($s['description']) ?>
          </div>
          <?php endif; ?>
        </td>
        <?php if ($tab === 'core'): ?>
        <td>
          <?php
            $features = $s['features'] ? array_filter(array_map('trim', explode("\n", $s['features']))) : [];
            echo '<span class="text-muted small">' . count($features) . ' feature' . (count($features) !== 1 ? 's' : '') . '</span>';
          ?>
        </td>
        <?php endif; ?>
        <td>
          <a href="index.php?tab=<?= $tab ?>&toggle=1&id=<?= $s['id'] ?>"
             class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?> text-decoration-none">
            <?= $s['is_active'] ? 'Active' : 'Hidden' ?>
          </a>
        </td>
        <td>
          <div class="tbl-actions">
            <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="delete.php?id=<?= $s['id'] ?>&tab=<?= $tab ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this service?')" title="Delete">
              <i class="bi bi-trash"></i>
            </a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
