<?php
// ADMIN_URL is defined per-page before including this file
// e.g. define('ADMIN_URL', '/asb-tours/admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> | ASB Tours</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <!-- Font Awesome (for service icons) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <!-- Admin CSS -->
  <link href="<?= ADMIN_URL ?>/assets/css/admin.css" rel="stylesheet" />
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-dark admin-topbar px-3">
  <button class="btn btn-link text-white sidebar-toggle" id="sidebarToggle">
    <i class="bi bi-list fs-4"></i>
  </button>
  <span class="navbar-brand mb-0 fw-semibold">ASB Tours | Admin</span>
  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="text-white-50 small">
      <i class="bi bi-person-circle me-1"></i>
      <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
    </span>
    <a href="<?= ADMIN_URL ?>/logout.php" class="btn btn-sm btn-outline-light">
      <i class="bi bi-box-arrow-right me-1"></i>Logout
    </a>
  </div>
</nav>

<div class="admin-wrapper">
  <!-- SIDEBAR -->
  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- MAIN CONTENT -->
  <main class="admin-content">
