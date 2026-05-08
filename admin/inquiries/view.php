<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = ?');
$stmt->execute([$id]);
$inq = $stmt->fetch();

if (!$inq) { header('Location: index.php'); exit; }

// Mark as read automatically
if (!$inq['is_read']) {
    $pdo->prepare('UPDATE inquiries SET is_read = 1 WHERE id = ?')->execute([$id]);
    $inq['is_read'] = 1;
}

// Navigate to prev / next
$prev = $pdo->prepare('SELECT id FROM inquiries WHERE id < ? ORDER BY id DESC LIMIT 1');
$prev->execute([$id]);
$prevId = $prev->fetchColumn();

$next = $pdo->prepare('SELECT id FROM inquiries WHERE id > ? ORDER BY id ASC LIMIT 1');
$next->execute([$id]);
$nextId = $next->fetchColumn();

$pageTitle = 'Inquiry #' . $id;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-envelope-open me-2 text-primary"></i>Inquiry #<?= $id ?></h1>
  <div class="d-flex gap-2">
    <?php if ($prevId): ?>
      <a href="view.php?id=<?= $prevId ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-chevron-left"></i> Prev
      </a>
    <?php endif; ?>
    <?php if ($nextId): ?>
      <a href="view.php?id=<?= $nextId ?>" class="btn btn-outline-secondary btn-sm">
        Next <i class="bi bi-chevron-right"></i>
      </a>
    <?php endif; ?>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
  </div>
</div>

<div class="row g-3">

  <!-- LEFT, Message -->
  <div class="col-lg-8">
    <div class="admin-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-chat-text me-2"></i>Message</span>
        <span class="badge rounded-pill bg-success">Read</span>
      </div>
      <div class="p-4">
        <!-- Subject -->
        <h5 class="fw-bold mb-1" style="color:#03045E;">
          <?= htmlspecialchars($inq['subject'] ?? '(No subject)') ?>
        </h5>
        <div class="text-muted small mb-4">
          <i class="bi bi-clock me-1"></i>
          <?= date('d M Y, h:i A', strtotime($inq['created_at'])) ?>
        </div>

        <!-- Message body -->
        <div class="message-body">
          <?= nl2br(htmlspecialchars($inq['message'])) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT, Sender Info & Actions -->
  <div class="col-lg-4">

    <!-- Sender Info -->
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-person me-2"></i>Sender</div>
      <div class="p-3">

        <div class="sender-avatar">
          <?= strtoupper(substr($inq['full_name'], 0, 1)) ?>
        </div>

        <div class="text-center mb-3">
          <div class="fw-bold"><?= htmlspecialchars($inq['full_name']) ?></div>
          <div class="text-muted small"><?= htmlspecialchars($inq['email']) ?></div>
          <?php if ($inq['phone']): ?>
            <div class="text-muted small">
              <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($inq['phone']) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="d-grid gap-2">
          <a href="mailto:<?= htmlspecialchars($inq['email']) ?>?subject=Re: <?= urlencode($inq['subject'] ?? 'Your Inquiry') ?>"
             class="btn btn-primary btn-sm">
            <i class="bi bi-reply me-1"></i> Reply via Email
          </a>
          <?php if ($inq['phone']): ?>
            <a href="tel:<?= htmlspecialchars($inq['phone']) ?>"
               class="btn btn-outline-success btn-sm">
              <i class="bi bi-telephone me-1"></i> Call
            </a>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $inq['phone']) ?>"
               target="_blank"
               class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-whatsapp me-1"></i> WhatsApp
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="admin-card">
      <div class="card-header"><i class="bi bi-gear me-2"></i>Actions</div>
      <div class="p-3 d-grid gap-2">
        <a href="index.php?delete=<?= $id ?>"
           class="btn btn-outline-danger btn-sm"
           onclick="return confirm('Delete this inquiry permanently?')">
          <i class="bi bi-trash me-1"></i> Delete Inquiry
        </a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-list me-1"></i> All Inquiries
        </a>
      </div>
    </div>

  </div>
</div>

<style>
.message-body {
  font-size: .95rem;
  line-height: 1.8;
  color: #2d3748;
  padding: 1.25rem;
  background: #f7fafc;
  border-radius: 10px;
  border-left: 4px solid #0077B6;
}

.sender-avatar {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0077B6, #00B4D8);
  color: #fff;
  font-size: 1.4rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1rem;
  box-shadow: 0 4px 12px rgba(0,119,182,.3);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
