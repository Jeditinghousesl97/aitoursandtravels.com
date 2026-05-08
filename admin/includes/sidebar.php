<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

// Notification counts
$_pdo = getPDO();
$newBookings  = (int)$_pdo->query("SELECT COUNT(*) FROM bookings  WHERE status  = 'new'")->fetchColumn();
$unreadInq    = (int)$_pdo->query("SELECT COUNT(*) FROM inquiries WHERE is_read  = 0")->fetchColumn();
$pendingRev   = (int)$_pdo->query("SELECT COUNT(*) FROM reviews   WHERE is_approved = 0")->fetchColumn();

function navItem(string $href, string $icon, string $label, string $dir, int $badge = 0): string {
    global $currentDir;
    $active     = ($currentDir === $dir) ? 'active' : '';
    $badgeHtml  = $badge > 0
        ? '<span class="badge bg-danger ms-auto" style="font-size:.65rem;">' . $badge . '</span>'
        : '';
    return '<li class="nav-item">
        <a class="nav-link ' . $active . '" href="' . $href . '" style="display:flex;align-items:center;">
            <i class="bi ' . $icon . ' me-2"></i>' . $label . $badgeHtml . '
        </a>
    </li>';
}
?>
<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-brand">
    <i class="bi bi-compass me-2"></i>ASB Tours
  </div>
  <ul class="nav flex-column px-2 mt-2">
    <li class="nav-item">
      <a class="nav-link <?= $currentDir === 'admin' ? 'active' : '' ?>"
         href="<?= ADMIN_URL ?>/index.php">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </a>
    </li>
    <?= navItem(ADMIN_URL . '/packages/index.php',  'bi-suitcase-lg',       'Packages',  'packages') ?>
    <?= navItem(ADMIN_URL . '/services/index.php',  'bi-grid-3x2-gap',     'Services',  'services') ?>
    <?= navItem(ADMIN_URL . '/banners/index.php',   'bi-image',            'Banners',   'banners') ?>
    <?= navItem(ADMIN_URL . '/gallery/index.php',   'bi-images',           'Gallery',   'gallery') ?>
    <?= navItem(ADMIN_URL . '/blog/index.php',      'bi-file-earmark-text','Blog',      'blog') ?>
    <?= navItem(ADMIN_URL . '/reviews/index.php',   'bi-star',             'Reviews',   'reviews',   $pendingRev) ?>
    <?= navItem(ADMIN_URL . '/bookings/index.php',  'bi-calendar-check',   'Bookings',  'bookings',  $newBookings) ?>
    <?= navItem(ADMIN_URL . '/inquiries/index.php', 'bi-envelope',         'Inquiries', 'inquiries', $unreadInq) ?>
    <?= navItem(ADMIN_URL . '/settings/index.php',  'bi-gear',             'Settings',  'settings') ?>
  </ul>

  <div class="sidebar-footer">
    <a href="<?= ADMIN_URL ?>/../index.php" target="_blank" class="btn btn-sm btn-outline-secondary w-100">
      <i class="bi bi-globe me-1"></i> View Website
    </a>
  </div>
</aside>
