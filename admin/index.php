<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getPDO();

// Stats
$stats = [
    'packages'  => $pdo->query('SELECT COUNT(*) FROM packages')->fetchColumn(),
    'bookings'  => $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
    'inquiries' => $pdo->query('SELECT COUNT(*) FROM inquiries WHERE is_read = 0')->fetchColumn(),
    'reviews'   => $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_approved = 0')->fetchColumn(),
];

// Latest 5 bookings
$latestBookings = $pdo->query(
    'SELECT b.id, b.full_name, b.travel_date, b.status, p.title AS package_title
     FROM bookings b
     LEFT JOIN packages p ON b.package_id = p.id
     ORDER BY b.created_at DESC LIMIT 5'
)->fetchAll();

// Latest 5 inquiries
$latestInquiries = $pdo->query(
    'SELECT id, full_name, subject, is_read, created_at
     FROM inquiries ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</h1>
  <span class="text-muted small">Welcome back, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</span>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon"><i class="bi bi-suitcase-lg"></i></div>
      <div>
        <div class="stat-value"><?= $stats['packages'] ?></div>
        <div class="stat-label">Tour Packages</div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card" style="border-color:#3182ce;">
      <div class="stat-icon" style="background:#ebf8ff;color:#3182ce;">
        <i class="bi bi-calendar-check"></i>
      </div>
      <div>
        <div class="stat-value"><?= $stats['bookings'] ?></div>
        <div class="stat-label">Total Bookings</div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card" style="border-color:#d69e2e;">
      <div class="stat-icon" style="background:#fefcbf;color:#d69e2e;">
        <i class="bi bi-envelope"></i>
      </div>
      <div>
        <div class="stat-value"><?= $stats['inquiries'] ?></div>
        <div class="stat-label">Unread Inquiries</div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card" style="border-color:#805ad5;">
      <div class="stat-icon" style="background:#faf5ff;color:#805ad5;">
        <i class="bi bi-star"></i>
      </div>
      <div>
        <div class="stat-value"><?= $stats['reviews'] ?></div>
        <div class="stat-label">Pending Reviews</div>
      </div>
    </div>
  </div>

</div>

<!-- LATEST BOOKINGS & INQUIRIES -->
<div class="row g-3">

  <!-- Latest Bookings -->
  <div class="col-lg-7">
    <div class="admin-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-check me-2 text-primary"></i>Latest Bookings</span>
        <a href="<?= ADMIN_URL ?>/bookings/index.php" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Package</th>
              <th>Travel Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($latestBookings): ?>
              <?php foreach ($latestBookings as $b): ?>
                <tr>
                  <td class="text-muted"><?= $b['id'] ?></td>
                  <td><?= htmlspecialchars($b['full_name']) ?></td>
                  <td><?= $b['package_title'] ? htmlspecialchars($b['package_title']) : '<span class="text-muted">Custom</span>' ?></td>
                  <td><?= $b['travel_date'] ? date('d M Y', strtotime($b['travel_date'])) : '—' ?></td>
                  <td>
                    <span class="badge rounded-pill badge-<?= $b['status'] ?>">
                      <?= ucfirst($b['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No bookings yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Latest Inquiries -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-envelope me-2 text-warning"></i>Latest Inquiries</span>
        <a href="<?= ADMIN_URL ?>/inquiries/index.php" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Subject</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($latestInquiries): ?>
              <?php foreach ($latestInquiries as $inq): ?>
                <tr>
                  <td><?= htmlspecialchars($inq['full_name']) ?></td>
                  <td class="text-truncate" style="max-width:150px;">
                    <?= htmlspecialchars($inq['subject'] ?? '—') ?>
                  </td>
                  <td>
                    <?php if ($inq['is_read']): ?>
                      <span class="badge bg-secondary">Read</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">New</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" class="text-center text-muted py-3">No inquiries yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
