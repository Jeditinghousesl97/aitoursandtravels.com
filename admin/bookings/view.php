<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT b.*, p.title AS package_title
    FROM bookings b
    LEFT JOIN packages p ON b.package_id = p.id
    WHERE b.id = ?
');
$stmt->execute([$id]);
$b = $stmt->fetch();

if (!$b) { header('Location: index.php'); exit; }

$success = '';

// Update status + notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? $b['status'];
    $notes     = trim($_POST['admin_notes'] ?? '');

    $pdo->prepare('UPDATE bookings SET status = ?, admin_notes = ? WHERE id = ?')
        ->execute([$newStatus, $notes, $id]);

    $b['status']      = $newStatus;
    $b['admin_notes'] = $notes;
    $success = 'Booking updated successfully.';
}

$statuses   = ['new', 'contacted', 'confirmed', 'cancelled'];
$pageTitle  = 'Booking #' . $b['id'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-calendar-check me-2 text-primary"></i>Booking #<?= $b['id'] ?></h1>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> <?= $success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-3">

  <!-- LEFT, Booking Details -->
  <div class="col-lg-8">

    <!-- Guest Info -->
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-person me-2"></i>Guest Information</div>
      <div class="p-3">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="detail-label">Full Name</div>
            <div class="detail-value"><?= htmlspecialchars($b['full_name']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Email</div>
            <div class="detail-value">
              <a href="mailto:<?= htmlspecialchars($b['email']) ?>">
                <?= htmlspecialchars($b['email']) ?>
              </a>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Phone</div>
            <div class="detail-value">
              <?php if ($b['phone']): ?>
                <a href="tel:<?= htmlspecialchars($b['phone']) ?>"><?= htmlspecialchars($b['phone']) ?></a>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Nationality</div>
            <div class="detail-value"><?= htmlspecialchars($b['nationality'] ?? '—') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tour Details -->
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-suitcase-lg me-2"></i>Tour Details</div>
      <div class="p-3">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="detail-label">Package</div>
            <div class="detail-value">
              <?= $b['package_title']
                ? htmlspecialchars($b['package_title'])
                : '<span class="badge bg-secondary">Custom Tour</span>' ?>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Travel Date</div>
            <div class="detail-value">
              <?= $b['travel_date'] ? date('d M Y', strtotime($b['travel_date'])) : '—' ?>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Duration</div>
            <div class="detail-value"><?= htmlspecialchars($b['duration'] ?? '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Travellers</div>
            <div class="detail-value">
              <?= (int)$b['adults'] ?> Adult<?= $b['adults'] > 1 ? 's' : '' ?>
              <?php if ($b['children'] > 0): ?>
                , <?= (int)$b['children'] ?> Child<?= $b['children'] > 1 ? 'ren' : '' ?>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($b['special_request']): ?>
            <div class="col-12">
              <div class="detail-label">Special Request</div>
              <div class="detail-value p-2 rounded"
                   style="background:#f7fafc;border:1px solid #e2e8f0;">
                <?= nl2br(htmlspecialchars($b['special_request'])) ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Timeline</div>
      <div class="p-3">
        <div class="booking-timeline">
          <?php
          $steps = ['new','contacted','confirmed','cancelled'];
          $icons = [
            'new'       => 'bi-bell',
            'contacted' => 'bi-chat-dots',
            'confirmed' => 'bi-check-circle',
            'cancelled' => 'bi-x-circle',
          ];
          $colors = [
            'new'       => '#3182ce',
            'contacted' => '#d69e2e',
            'confirmed' => '#276749',
            'cancelled' => '#9b2c2c',
          ];
          $currentIdx = array_search($b['status'], $steps);
          if ($b['status'] === 'cancelled') {
              $activeSteps = ['new', 'cancelled'];
          } else {
              $activeSteps = array_slice($steps, 0, $currentIdx + 1);
          }
          ?>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php foreach (['new','contacted','confirmed'] as $i => $step): ?>
              <?php
              $isActive = in_array($step, $activeSteps) && $b['status'] !== 'cancelled';
              $isCurrent = $b['status'] === $step;
              ?>
              <div class="timeline-step <?= $isCurrent ? 'current' : ($isActive ? 'done' : '') ?>"
                   style="--c:<?= $colors[$step] ?>">
                <div class="ts-dot"><i class="bi <?= $icons[$step] ?>"></i></div>
                <div class="ts-label"><?= ucfirst($step) ?></div>
              </div>
              <?php if ($i < 2): ?>
                <div class="timeline-line <?= $isActive && $b['status'] !== 'cancelled' ? 'done' : '' ?>"></div>
              <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($b['status'] === 'cancelled'): ?>
              <div class="timeline-line"></div>
              <div class="timeline-step current" style="--c:#9b2c2c">
                <div class="ts-dot"><i class="bi bi-x-circle"></i></div>
                <div class="ts-label">Cancelled</div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- RIGHT, Update Status & Notes -->
  <div class="col-lg-4">

    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>Booking Info</div>
      <div class="p-3">
        <div class="mb-2">
          <div class="detail-label">Booking ID</div>
          <div class="detail-value fw-bold">#<?= $b['id'] ?></div>
        </div>
        <div class="mb-2">
          <div class="detail-label">Received</div>
          <div class="detail-value"><?= date('d M Y, h:i A', strtotime($b['created_at'])) ?></div>
        </div>
        <div>
          <div class="detail-label">Current Status</div>
          <?php
          $badge = [
            'new'       => 'badge-new',
            'contacted' => 'badge-contacted',
            'confirmed' => 'badge-confirmed',
            'cancelled' => 'badge-cancelled',
          ][$b['status']] ?? '';
          ?>
          <span class="badge rounded-pill <?= $badge ?>">
            <?= ucfirst($b['status']) ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Update Form -->
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-pencil me-2"></i>Update Booking</div>
      <div class="p-3">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>>
                  <?= ucfirst($s) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Admin Notes</label>
            <textarea name="admin_notes" class="form-control" rows="5"
                      placeholder="Add internal notes about this booking..."><?= htmlspecialchars($b['admin_notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> Save Changes
          </button>
        </form>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="admin-card">
      <div class="card-header"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
      <div class="p-3 d-grid gap-2">
        <a href="mailto:<?= htmlspecialchars($b['email']) ?>" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-envelope me-1"></i> Email Guest
        </a>
        <?php if ($b['phone']): ?>
          <a href="tel:<?= htmlspecialchars($b['phone']) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-telephone me-1"></i> Call Guest
          </a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<style>
.detail-label {
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #a0aec0;
  margin-bottom: .2rem;
}
.detail-value {
  font-size: .9rem;
  color: #2d3748;
}

/* Timeline */
.booking-timeline { padding: .5rem 0; }
.timeline-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .3rem;
}
.ts-dot {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #a0aec0;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
  transition: background .3s, color .3s;
}
.timeline-step.done .ts-dot,
.timeline-step.current .ts-dot {
  background: var(--c);
  color: #fff;
}
.ts-label {
  font-size: .72rem;
  font-weight: 600;
  color: #a0aec0;
  text-transform: uppercase;
}
.timeline-step.done .ts-label,
.timeline-step.current .ts-label { color: var(--c); }

.timeline-line {
  flex: 1;
  height: 3px;
  background: #e2e8f0;
  border-radius: 2px;
  min-width: 30px;
  margin-bottom: 1.2rem;
}
.timeline-line.done { background: #276749; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
