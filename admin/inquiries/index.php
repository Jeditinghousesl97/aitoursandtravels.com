<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

// Delete
if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM inquiries WHERE id = ?')->execute([(int)$_GET['delete']]);
    header('Location: index.php?deleted=1'); exit;
}

// Search & filter
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? ''; // unread | read

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(full_name LIKE ? OR email LIKE ? OR subject LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter === 'unread') {
    $where[]  = 'is_read = 0';
} elseif ($filter === 'read') {
    $where[]  = 'is_read = 1';
}

$sql = 'SELECT * FROM inquiries';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

$totalUnread = $pdo->query('SELECT COUNT(*) FROM inquiries WHERE is_read = 0')->fetchColumn();
$totalRead   = $pdo->query('SELECT COUNT(*) FROM inquiries WHERE is_read = 1')->fetchColumn();
$totalAll    = $pdo->query('SELECT COUNT(*) FROM inquiries')->fetchColumn();

$pageTitle = 'Inquiries';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-envelope me-2 text-primary"></i>Inquiries</h1>
</div>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Inquiry deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Summary -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <a href="index.php" class="text-decoration-none">
      <div class="stat-card">
        <div class="stat-icon"><i class="bi bi-envelope-open"></i></div>
        <div>
          <div class="stat-value"><?= $totalAll ?></div>
          <div class="stat-label">Total Inquiries</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="?filter=unread" class="text-decoration-none">
      <div class="stat-card" style="border-color:#d69e2e;">
        <div class="stat-icon" style="background:#fefcbf;color:#d69e2e;">
          <i class="bi bi-envelope-exclamation"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalUnread ?></div>
          <div class="stat-label">Unread</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="?filter=read" class="text-decoration-none">
      <div class="stat-card" style="border-color:#276749;">
        <div class="stat-icon" style="background:#f0fff4;color:#276749;">
          <i class="bi bi-envelope-check"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalRead ?></div>
          <div class="stat-label">Read</div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- Filters -->
<div class="admin-card mb-3">
  <div class="card-header">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Search name, email or subject..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="filter" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread</option>
          <option value="read"   <?= $filter === 'read'   ? 'selected' : '' ?>>Read</option>
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
          <th>#</th>
          <th>Sender</th>
          <th>Subject</th>
          <th>Status</th>
          <th>Received</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($inquiries): ?>
          <?php foreach ($inquiries as $inq): ?>
            <tr class="<?= !$inq['is_read'] ? 'table-unread' : '' ?>">
              <td class="text-muted small">#<?= $inq['id'] ?></td>
              <td>
                <div class="fw-<?= !$inq['is_read'] ? 'bold' : 'normal' ?>">
                  <?= htmlspecialchars($inq['full_name']) ?>
                </div>
                <div class="text-muted small"><?= htmlspecialchars($inq['email']) ?></div>
                <?php if ($inq['phone']): ?>
                  <div class="text-muted small">
                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($inq['phone']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div class="<?= !$inq['is_read'] ? 'fw-semibold' : 'text-muted' ?>">
                  <?= htmlspecialchars($inq['subject'] ?? '(No subject)') ?>
                </div>
                <div class="text-muted small text-truncate" style="max-width:220px;">
                  <?= htmlspecialchars(substr($inq['message'], 0, 80)) ?>...
                </div>
              </td>
              <td>
                <?php if (!$inq['is_read']): ?>
                  <span class="badge rounded-pill" style="background:#fefcbf;color:#744210;">
                    <i class="bi bi-dot"></i> New
                  </span>
                <?php else: ?>
                  <span class="badge rounded-pill bg-light text-muted">Read</span>
                <?php endif; ?>
              </td>
              <td class="text-muted small">
                <?= date('d M Y', strtotime($inq['created_at'])) ?><br>
                <span class="text-muted"><?= date('h:i A', strtotime($inq['created_at'])) ?></span>
              </td>
              <td>
                <div class="tbl-actions">
                  <a href="view.php?id=<?= $inq['id'] ?>" class="btn btn-sm btn-primary" title="View">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="index.php?delete=<?= $inq['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"
                     onclick="return confirm('Delete this inquiry?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">
              <i class="bi bi-envelope fs-3 d-block mb-2 opacity-50"></i>
              No inquiries found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.table-unread { background: #f0f8ff; }
.table-unread:hover { background: #e0f0ff !important; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
