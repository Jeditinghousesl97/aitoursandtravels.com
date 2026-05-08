<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

// Search & filters
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(b.full_name LIKE ? OR b.email LIKE ? OR b.phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status !== '') {
    $where[]  = 'b.status = ?';
    $params[] = $status;
}

$sql = 'SELECT b.*, p.title AS package_title
        FROM bookings b
        LEFT JOIN packages p ON b.package_id = p.id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY b.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Count by status for summary badges
$counts = $pdo->query('SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status')->fetchAll();
$statusCounts = array_column($counts, 'cnt', 'status');

$pageTitle = 'Bookings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-calendar-check me-2 text-primary"></i>Bookings</h1>
</div>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Booking deleted successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Status summary -->
<div class="row g-3 mb-4">
  <?php
  $summaries = [
    'new'       => ['label' => 'New',       'color' => '#3182ce', 'bg' => '#ebf8ff', 'icon' => 'bi-bell'],
    'contacted' => ['label' => 'Contacted', 'color' => '#d69e2e', 'bg' => '#fefcbf', 'icon' => 'bi-chat-dots'],
    'confirmed' => ['label' => 'Confirmed', 'color' => '#276749', 'bg' => '#f0fff4', 'icon' => 'bi-check-circle'],
    'cancelled' => ['label' => 'Cancelled', 'color' => '#9b2c2c', 'bg' => '#fff5f5', 'icon' => 'bi-x-circle'],
  ];
  foreach ($summaries as $key => $s):
  ?>
    <div class="col-6 col-xl-3">
      <a href="?status=<?= $key ?>" class="text-decoration-none">
        <div class="stat-card" style="border-color:<?= $s['color'] ?>;">
          <div class="stat-icon" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;">
            <i class="bi <?= $s['icon'] ?>"></i>
          </div>
          <div>
            <div class="stat-value"><?= $statusCounts[$key] ?? 0 ?></div>
            <div class="stat-label"><?= $s['label'] ?></div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="admin-card mb-3">
  <div class="card-header">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Search name, email or phone..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach ($summaries as $key => $s): ?>
            <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>>
              <?= $s['label'] ?>
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
          <th>#</th>
          <th>Guest</th>
          <th>Package</th>
          <th>Travel Date</th>
          <th>Pax</th>
          <th>Status</th>
          <th>Received</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($bookings): ?>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td class="text-muted small">#<?= $b['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($b['full_name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($b['email']) ?></div>
                <?php if ($b['phone']): ?>
                  <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($b['phone']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($b['package_title']): ?>
                  <span class="badge rounded-pill" style="background:#e0f4fc;color:#0077B6;">
                    <?= htmlspecialchars($b['package_title']) ?>
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary rounded-pill">Custom Tour</span>
                <?php endif; ?>
              </td>
              <td>
                <?= $b['travel_date'] ? date('d M Y', strtotime($b['travel_date'])) : '<span class="text-muted">—</span>' ?>
              </td>
              <td>
                <i class="bi bi-person me-1 text-muted"></i><?= (int)$b['adults'] ?>
                <?php if ($b['children'] > 0): ?>
                  &nbsp;<i class="bi bi-people me-1 text-muted"></i><?= (int)$b['children'] ?>
                <?php endif; ?>
              </td>
              <td>
                <?php
                $badge = [
                  'new'       => 'badge-new',
                  'contacted' => 'badge-contacted',
                  'confirmed' => 'badge-confirmed',
                  'cancelled' => 'badge-cancelled',
                ][$b['status']] ?? 'bg-secondary';
                ?>
                <span class="badge rounded-pill <?= $badge ?>">
                  <?= ucfirst($b['status']) ?>
                </span>
              </td>
              <td class="text-muted small">
                <?= date('d M Y', strtotime($b['created_at'])) ?>
              </td>
              <td>
                <div class="tbl-actions">
                  <a href="view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-primary" title="View">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="delete.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"
                     onclick="return confirm('Delete this booking?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
              No bookings found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
