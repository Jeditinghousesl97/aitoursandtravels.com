<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

// Approve / Unapprove
if (isset($_GET['toggle'])) {
    $pdo->prepare('UPDATE reviews SET is_approved = NOT is_approved WHERE id = ?')
        ->execute([(int)$_GET['toggle']]);
    header('Location: index.php'); exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $row = $pdo->prepare('SELECT avatar FROM reviews WHERE id = ?');
    $row->execute([$id]);
    $row = $row->fetch();
    if ($row) {
        if ($row['avatar']) {
            $f = __DIR__ . '/../../' . $row['avatar'];
            if (file_exists($f)) unlink($f);
        }
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    }
    header('Location: index.php?deleted=1'); exit;
}

// Filter
$filter = $_GET['filter'] ?? '';
$where  = [];
$params = [];

if ($filter === 'approved') {
    $where[] = 'is_approved = 1';
} elseif ($filter === 'pending') {
    $where[] = 'is_approved = 0';
}

$sql = 'SELECT * FROM reviews';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$totalApproved = $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_approved = 1')->fetchColumn();
$totalPending  = $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_approved = 0')->fetchColumn();

$pageTitle = 'Reviews';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-star me-2 text-primary"></i>Reviews & Testimonials</h1>
</div>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Review deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <a href="index.php" class="text-decoration-none">
      <div class="stat-card">
        <div class="stat-icon"><i class="bi bi-star-half"></i></div>
        <div>
          <div class="stat-value"><?= $totalApproved + $totalPending ?></div>
          <div class="stat-label">Total Reviews</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="?filter=approved" class="text-decoration-none">
      <div class="stat-card" style="border-color:#276749;">
        <div class="stat-icon" style="background:#f0fff4;color:#276749;">
          <i class="bi bi-patch-check"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalApproved ?></div>
          <div class="stat-label">Approved</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="?filter=pending" class="text-decoration-none">
      <div class="stat-card" style="border-color:#d69e2e;">
        <div class="stat-icon" style="background:#fefcbf;color:#d69e2e;">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalPending ?></div>
          <div class="stat-label">Pending</div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-3">
  <a href="index.php"
     class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline-secondary' ?>">
    All
  </a>
  <a href="?filter=pending"
     class="btn btn-sm <?= $filter === 'pending' ? 'btn-warning' : 'btn-outline-secondary' ?>">
    <i class="bi bi-hourglass-split me-1"></i>Pending
    <?php if ($totalPending > 0): ?>
      <span class="badge bg-danger ms-1"><?= $totalPending ?></span>
    <?php endif; ?>
  </a>
  <a href="?filter=approved"
     class="btn btn-sm <?= $filter === 'approved' ? 'btn-success' : 'btn-outline-secondary' ?>">
    <i class="bi bi-patch-check me-1"></i>Approved
  </a>
</div>

<!-- Reviews Grid -->
<?php if ($reviews): ?>
  <div class="reviews-grid">
    <?php foreach ($reviews as $r): ?>
      <div class="review-card <?= !$r['is_approved'] ? 'review-pending' : '' ?>">

        <!-- Header -->
        <div class="review-card-header">
          <!-- Avatar -->
          <div class="review-avatar">
            <?php if ($r['avatar']): ?>
              <img src="<?= htmlspecialchars(site_url($r['avatar'])) ?>"
                   alt="<?= htmlspecialchars($r['name']) ?>">
            <?php else: ?>
              <?= strtoupper(substr($r['name'], 0, 1)) ?>
            <?php endif; ?>
          </div>

          <div class="review-meta">
            <div class="review-name"><?= htmlspecialchars($r['name']) ?></div>
            <?php if ($r['country']): ?>
              <div class="review-country">
                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($r['country']) ?>
              </div>
            <?php endif; ?>
            <!-- Stars -->
            <div class="review-stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star<?= $i <= $r['rating'] ? '-fill' : '' ?>"></i>
              <?php endfor; ?>
            </div>
          </div>

          <!-- Status badge -->
          <div class="ms-auto">
            <?php if ($r['is_approved']): ?>
              <span class="badge rounded-pill" style="background:#f0fff4;color:#276749;">
                <i class="bi bi-patch-check me-1"></i>Approved
              </span>
            <?php else: ?>
              <span class="badge rounded-pill" style="background:#fefcbf;color:#744210;">
                <i class="bi bi-hourglass-split me-1"></i>Pending
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Review text -->
        <div class="review-text">
          "<?= nl2br(htmlspecialchars($r['review_text'])) ?>"
        </div>

        <!-- Footer -->
        <div class="review-card-footer">
          <div class="text-muted small">
            <i class="bi bi-clock me-1"></i>
            <?= date('d M Y', strtotime($r['created_at'])) ?>
          </div>
          <div class="tbl-actions">
            <a href="?toggle=<?= $r['id'] ?>"
               class="btn btn-sm <?= $r['is_approved'] ? 'btn-outline-warning' : 'btn-success' ?>"
               title="<?= $r['is_approved'] ? 'Unapprove' : 'Approve' ?>">
              <i class="bi bi-<?= $r['is_approved'] ? 'x-circle' : 'check-circle' ?>"></i>
            </a>
            <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"
               onclick="return confirm('Delete this review permanently?')">
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
      <i class="bi bi-star fs-1 d-block mb-3 opacity-25"></i>
      <p>No reviews found.</p>
    </div>
  </div>
<?php endif; ?>

<style>
.reviews-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 1.25rem;
}

.review-card {
  background: #fff;
  border-radius: 14px;
  padding: 1.25rem;
  box-shadow: 0 2px 10px rgba(0,119,182,.07);
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  transition: box-shadow .2s;
}

.review-card:hover { box-shadow: 0 6px 24px rgba(0,119,182,.12); }

.review-pending {
  border-left: 4px solid #d69e2e;
  background: #fffdf0;
}

.review-card-header {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
}

.review-avatar {
  width: 48px; height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0077B6, #00B4D8);
  color: #fff;
  font-size: 1.2rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.review-avatar img {
  width: 100%; height: 100%;
  object-fit: cover;
}

.review-name {
  font-weight: 700;
  font-size: .95rem;
  color: #1a202c;
}

.review-country {
  font-size: .78rem;
  color: #718096;
  margin-top: .1rem;
}

.review-stars {
  margin-top: .25rem;
  font-size: .8rem;
  color: #f6ad55;
}

.review-text {
  font-size: .88rem;
  color: #4a5568;
  line-height: 1.7;
  font-style: italic;
  padding: .75rem 1rem;
  background: #f7fafc;
  border-radius: 8px;
  border-left: 3px solid #00B4D8;
}

.review-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: .5rem;
  border-top: 1px solid #e2e8f0;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
