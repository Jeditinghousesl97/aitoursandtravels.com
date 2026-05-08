<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../assets/php/theme-settings.php';
require_once __DIR__ . '/../../assets/php/mailer.php';

$pdo     = getPDO();
$success = '';
$errors  = [];
$themeSettings = theme_setting_definitions();

// Load all settings
$rows = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll();
$s    = array_column($rows, 'value', 'key');

function setting(array $s, string $key, string $default = ''): string {
    return htmlspecialchars($s[$key] ?? $default);
}

function saveSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?')
        ->execute([$key, $value, $value]);
}

function removeUploadedAssetFromSetting(array $s, string $settingKey): void {
    $assetUrl = trim((string)($s[$settingKey] ?? ''));
    if ($assetUrl === '') {
        return;
    }
    $assetPath = SITE_ROOT . ltrim(parse_url($assetUrl, PHP_URL_PATH) ?? '', '/');
    if (is_file($assetPath)) {
        unlink($assetPath);
    }
}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'general';

    // GENERAL
    if ($tab === 'general') {
        saveSetting($pdo, 'site_name',    trim($_POST['site_name'] ?? ''));
        saveSetting($pdo, 'site_tagline', trim($_POST['site_tagline'] ?? ''));

        if (!empty($_FILES['logo']['name'])) {
            $file    = $_FILES['logo'];
            $allowed = ['image/jpeg','image/png','image/webp','image/svg+xml'];
            if (!in_array($file['type'], $allowed)) {
                $errors[] = 'Logo: JPG, PNG, WEBP or SVG only.';
            } elseif ($file['size'] > 25 * 1024 * 1024) {
                $errors[] = 'Logo must be under 25MB.';
            } else {
                $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $preferredDir = SITE_ROOT . 'uploads/branding/';
                $fallbackDir  = SITE_ROOT . 'uploads/';
                $logoDir      = $preferredDir;

                if (!is_dir($logoDir) && !mkdir($logoDir, 0775, true) && !is_dir($logoDir)) {
                    $logoDir = $fallbackDir;
                }

                $dest        = $logoDir . 'logo.' . $ext;
                $oldLogos    = glob($logoDir . 'logo.*') ?: [];
                $publicPath  = str_starts_with($logoDir, $preferredDir)
                    ? 'uploads/branding/logo.' . $ext
                    : 'uploads/logo.' . $ext;

                if (!is_dir($logoDir)) {
                    $errors[] = 'Logo upload folder is missing: ' . $logoDir;
                } elseif (!is_writable($logoDir)) {
                    $errors[] = 'Logo upload folder is not writable: ' . $logoDir;
                } elseif (move_uploaded_file($file['tmp_name'], $dest)) {
                    foreach ($oldLogos as $oldLogo) {
                        if ($oldLogo !== $dest && is_file($oldLogo)) {
                            unlink($oldLogo);
                        }
                    }
                    saveSetting($pdo, 'site_logo', site_url($publicPath));
                    saveSetting($pdo, 'cache_busted_at', date('Y-m-d H:i:s'));
                } else {
                    $errors[] = 'Logo upload failed. Please check folder permissions for ' . $logoDir;
                }
            }
        }

        if (!empty($_FILES['favicon']['name'])) {
            $file    = $_FILES['favicon'];
            $allowed = ['image/png','image/x-icon','image/vnd.microsoft.icon','image/svg+xml','image/webp'];
            if (!in_array($file['type'], $allowed)) {
                $errors[] = 'Favicon: PNG, ICO, SVG or WEBP only.';
            } elseif ($file['size'] > 25 * 1024 * 1024) {
                $errors[] = 'Favicon must be under 25MB.';
            } else {
                $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $preferredDir = SITE_ROOT . 'uploads/branding/';
                $fallbackDir  = SITE_ROOT . 'uploads/';
                $faviconDir   = $preferredDir;

                if (!is_dir($faviconDir) && !mkdir($faviconDir, 0775, true) && !is_dir($faviconDir)) {
                    $faviconDir = $fallbackDir;
                }

                $dest       = $faviconDir . 'favicon.' . $ext;
                $oldFiles   = glob($faviconDir . 'favicon.*') ?: [];
                $publicPath = str_starts_with($faviconDir, $preferredDir)
                    ? 'uploads/branding/favicon.' . $ext
                    : 'uploads/favicon.' . $ext;

                if (!is_dir($faviconDir)) {
                    $errors[] = 'Favicon upload folder is missing: ' . $faviconDir;
                } elseif (!is_writable($faviconDir)) {
                    $errors[] = 'Favicon upload folder is not writable: ' . $faviconDir;
                } elseif (move_uploaded_file($file['tmp_name'], $dest)) {
                    foreach ($oldFiles as $oldFile) {
                        if ($oldFile !== $dest && is_file($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    saveSetting($pdo, 'site_favicon', site_url($publicPath));
                    saveSetting($pdo, 'cache_busted_at', date('Y-m-d H:i:s'));
                } else {
                    $errors[] = 'Favicon upload failed. Please check folder permissions for ' . $faviconDir;
                }
            }
        }

        if (!empty($_FILES['seo_image']['name'])) {
            $file    = $_FILES['seo_image'];
            $allowed = ['image/jpeg','image/png','image/webp'];
            if (!in_array($file['type'], $allowed)) {
                $errors[] = 'SEO image: JPG, PNG or WEBP only.';
            } elseif ($file['size'] > 25 * 1024 * 1024) {
                $errors[] = 'SEO image must be under 25MB.';
            } else {
                $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $preferredDir = SITE_ROOT . 'uploads/branding/';
                $fallbackDir  = SITE_ROOT . 'uploads/';
                $seoDir       = $preferredDir;

                if (!is_dir($seoDir) && !mkdir($seoDir, 0775, true) && !is_dir($seoDir)) {
                    $seoDir = $fallbackDir;
                }

                $dest       = $seoDir . 'seo-image.' . $ext;
                $oldFiles   = glob($seoDir . 'seo-image.*') ?: [];
                $publicPath = str_starts_with($seoDir, $preferredDir)
                    ? 'uploads/branding/seo-image.' . $ext
                    : 'uploads/seo-image.' . $ext;

                if (!is_dir($seoDir)) {
                    $errors[] = 'SEO image upload folder is missing: ' . $seoDir;
                } elseif (!is_writable($seoDir)) {
                    $errors[] = 'SEO image upload folder is not writable: ' . $seoDir;
                } elseif (move_uploaded_file($file['tmp_name'], $dest)) {
                    foreach ($oldFiles as $oldFile) {
                        if ($oldFile !== $dest && is_file($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    saveSetting($pdo, 'seo_image', site_url($publicPath));
                    saveSetting($pdo, 'cache_busted_at', date('Y-m-d H:i:s'));
                } else {
                    $errors[] = 'SEO image upload failed. Please check folder permissions for ' . $seoDir;
                }
            }
        }

        if (isset($_POST['remove_favicon'])) {
            removeUploadedAssetFromSetting($s, 'site_favicon');
            saveSetting($pdo, 'site_favicon', '');
            saveSetting($pdo, 'cache_busted_at', date('Y-m-d H:i:s'));
        }

        if (isset($_POST['remove_seo_image'])) {
            removeUploadedAssetFromSetting($s, 'seo_image');
            saveSetting($pdo, 'seo_image', '');
            saveSetting($pdo, 'cache_busted_at', date('Y-m-d H:i:s'));
        }
        if (empty($errors)) { $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key'); $success = 'General settings saved.'; }
    }

    // CONTACT
    elseif ($tab === 'contact') {
        foreach (['contact_email','contact_phone','contact_whatsapp','contact_address','maps_link'] as $k) {
            saveSetting($pdo, $k, trim($_POST[$k] ?? ''));
        }
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');
        $success = 'Contact settings saved.';
    }

    // SOCIAL
    elseif ($tab === 'social') {
        foreach (['social_facebook','social_instagram','social_youtube','social_tripadvisor','social_twitter'] as $k) {
            saveSetting($pdo, $k, trim($_POST[$k] ?? ''));
        }
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');
        $success = 'Social media links saved.';
    }

    // APPEARANCE
    elseif ($tab === 'appearance') {
        foreach ($themeSettings as $key => $meta) {
            $color = theme_normalize_hex_color($_POST[$key] ?? '', $meta['default']);
            saveSetting($pdo, $key, $color);
        }
        saveSetting($pdo, 'cache_busted_at', date('Y-m-d H:i:s'));
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');
        $success = 'Appearance settings saved.';
    }

    // SEO
    elseif ($tab === 'seo') {
        foreach (['seo_meta_title','seo_meta_desc','google_analytics'] as $k) {
            saveSetting($pdo, $k, trim($_POST[$k] ?? ''));
        }
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');
        $success = 'SEO settings saved.';
    }

    // MAINTENANCE
    elseif ($tab === 'maintenance') {
        $mode    = isset($_POST['maintenance_mode']) ? '1' : '0';
        $message = trim($_POST['maintenance_message'] ?? '');
        saveSetting($pdo, 'maintenance_mode',    $mode);
        saveSetting($pdo, 'maintenance_message', $message);
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');

        // Write / remove maintenance flag file
        $flagFile = SITE_ROOT . 'maintenance.flag';
        if ($mode === '1') {
            file_put_contents($flagFile, $message ?: 'Site under maintenance.');
        } else {
            if (file_exists($flagFile)) unlink($flagFile);
        }
        $success = $mode === '1' ? 'Maintenance mode is now ON.' : 'Maintenance mode is now OFF.';
    }

    // SMTP
    elseif ($tab === 'smtp') {
        foreach (['smtp_host','smtp_port','smtp_username','smtp_from_name','smtp_from_email','smtp_encryption','smtp_notify_email'] as $k) {
            saveSetting($pdo, $k, trim($_POST[$k] ?? ''));
        }
        $newPw = $_POST['smtp_password'] ?? '';
        if ($newPw !== '') saveSetting($pdo, 'smtp_password', $newPw);
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');

        // Send test email
        if (isset($_POST['send_test'])) {
            $testTo = trim($_POST['test_email'] ?? '');
            if ($testTo === '') {
                $errors[] = 'Enter a test email address.';
            } else {
                $mailResult = nt_send_mail_with_settings($s, [
                    'to' => [
                        ['email' => $testTo, 'name' => 'SMTP Test Recipient'],
                    ],
                    'subject' => 'SMTP Test Email from ASB Tours',
                    'html' => '<h2>SMTP Test Successful</h2><p>This test email was sent from the ASB Tours admin settings page.</p>',
                    'text' => "SMTP Test Successful\n\nThis test email was sent from the ASB Tours admin settings page.",
                ]);

                if ($mailResult['success']) {
                    $success = 'Test email sent successfully to ' . htmlspecialchars($testTo) . '.';
                } else {
                    $errors[] = 'Test email failed: ' . ($mailResult['error'] ?? 'Unknown error');
                }
            }
        } else {
            $success = 'SMTP settings saved.';
        }
    }

    // SECURITY / BOT
    elseif ($tab === 'security') {
        foreach (['turnstile_site_key','turnstile_secret_key','max_login_attempts','lockout_minutes'] as $k) {
            saveSetting($pdo, $k, trim($_POST[$k] ?? ''));
        }
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');
        $success = 'Security settings saved.';
    }

    // ABOUT
    elseif ($tab === 'about') {
        foreach (['about_heading','about_heading_accent','about_description','about_mission','about_years','about_tours_count','about_clients_count','about_destinations','about_awards'] as $k) {
            saveSetting($pdo, $k, trim($_POST[$k] ?? ''));
        }
        // About image upload
        if (!empty($_FILES['about_image']['name'])) {
            $file    = $_FILES['about_image'];
            $allowed = ['image/jpeg','image/png','image/webp'];
            if (!in_array($file['type'], $allowed)) {
                $errors[] = 'About image must be JPG, PNG or WEBP.';
            } elseif ($file['size'] > 25 * 1024 * 1024) {
                $errors[] = 'About image must be under 25MB.';
            } else {
                $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
                $name = 'about_' . time() . '.' . $ext;
                $dest = SITE_ROOT . 'uploads/about/' . $name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Delete old image
                    $oldImg = $s['about_image'] ?? '';
                    if ($oldImg) {
                        $oldPath = SITE_ROOT . ltrim(parse_url($oldImg, PHP_URL_PATH) ?? '', '/');
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    saveSetting($pdo, 'about_image', site_url('uploads/about/' . $name));
                } else {
                    $errors[] = 'Failed to upload about image.';
                }
            }
        }
        // Remove about image
        if (isset($_POST['remove_about_image'])) {
            $oldImg = $s['about_image'] ?? '';
            if ($oldImg) {
                $oldPath = SITE_ROOT . ltrim(parse_url($oldImg, PHP_URL_PATH) ?? '', '/');
                if (file_exists($oldPath)) unlink($oldPath);
            }
            saveSetting($pdo, 'about_image', '');
        }
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');
        if (empty($errors)) $success = 'About section saved.';
    }

    // PURGE CACHE
    elseif ($tab === 'purge_cache') {
        $cleared = [];
        // PHP OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $cleared[] = 'PHP OPcache';
        }
        // Clear any temp files in uploads cache if exists
        $cacheDir = SITE_ROOT . 'cache/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            foreach ($files as $f) { if (is_file($f)) unlink($f); }
            $cleared[] = 'Cache files (' . count($files) . ' files removed)';
        }
        // Write cache-bust timestamp to settings
        saveSetting($pdo, 'cache_busted_at', date('Y-m-d H:i:s'));
        $cleared[] = 'Cache version updated';
        $s = array_column($pdo->query('SELECT `key`,`value` FROM settings')->fetchAll(), 'value', 'key');
        $success = 'Cache purged: ' . implode(', ', $cleared) . '.';
        header('Location: ?tab=purge_cache&purged=1'); exit;
    }

    // ACCOUNT (password / username)
    elseif ($tab === 'account') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $currentPw   = $_POST['current_password'] ?? '';
        $newPw       = $_POST['new_password'] ?? '';
        $confirmPw   = $_POST['confirm_password'] ?? '';

        $admin = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $admin->execute([$_SESSION['admin_id']]);
        $admin = $admin->fetch();

        if ($newUsername !== '' && $newUsername !== $admin['username']) {
            $chk = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ?');
            $chk->execute([$newUsername, $admin['id']]);
            if ($chk->fetch()) { $errors[] = 'Username already taken.'; }
            else {
                $pdo->prepare('UPDATE admin_users SET username = ? WHERE id = ?')->execute([$newUsername, $admin['id']]);
                $success = 'Username updated.';
            }
        }

        if ($newPw !== '') {
            if (!password_verify($currentPw, $admin['password']))  { $errors[] = 'Current password is incorrect.'; }
            elseif (strlen($newPw) < 8)  { $errors[] = 'New password must be at least 8 characters.'; }
            elseif ($newPw !== $confirmPw) { $errors[] = 'Passwords do not match.'; }
            else {
                $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
                    ->execute([password_hash($newPw, PASSWORD_BCRYPT), $admin['id']]);
                $success = 'Password changed successfully.';
            }
        }
    }
}

$activeTab = $_GET['tab'] ?? ($_POST['tab'] ?? 'general');
$maintenanceOn = ($s['maintenance_mode'] ?? '0') === '1';

$adminInfo = $pdo->prepare('SELECT username, name, email, role FROM admin_users WHERE id = ?');
$adminInfo->execute([$_SESSION['admin_id']]);
$adminInfo = $adminInfo->fetch();

$pageTitle = 'Settings';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($maintenanceOn): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2">
    <i class="bi bi-cone-striped fs-5"></i>
    <strong>Maintenance mode is currently ON.</strong> Your website is showing the maintenance page to visitors.
    <a href="?tab=maintenance" class="ms-auto btn btn-sm btn-warning">Manage</a>
  </div>
<?php endif; ?>

<div class="page-header">
  <h1><i class="bi bi-gear me-2 text-primary"></i>Settings</h1>
</div>

<?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show py-2">
    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger alert-dismissible fade show py-2">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-3">

  <!-- Sidebar -->
  <div class="col-lg-3">
    <div class="admin-card">
      <div class="settings-nav">
        <?php
        $tabs = [
          'general'     => ['icon'=>'bi-house',         'label'=>'General'],
          'contact'     => ['icon'=>'bi-telephone',      'label'=>'Contact'],
          'social'      => ['icon'=>'bi-share',          'label'=>'Social Media'],
          'appearance'  => ['icon'=>'bi-palette',        'label'=>'Appearance'],
          'seo'         => ['icon'=>'bi-search',         'label'=>'SEO'],
          'maintenance' => ['icon'=>'bi-cone-striped',   'label'=>'Maintenance', 'badge' => $maintenanceOn ? 'ON' : ''],
          'smtp'        => ['icon'=>'bi-envelope-at',    'label'=>'SMTP Email'],
          'security'    => ['icon'=>'bi-shield-lock',    'label'=>'Security'],
          'about'       => ['icon'=>'bi-info-circle',    'label'=>'About Section'],
          'account'     => ['icon'=>'bi-person-gear',    'label'=>'My Account'],
          'purge_cache' => ['icon'=>'bi-lightning-charge','label'=>'Purge Cache'],
        ];
        foreach ($tabs as $key => $tab):
          if ($key === 'purge_cache'): ?>
            <div style="padding:.5rem .25rem 0;margin-top:.25rem;border-top:1px solid #e2e8f0;">
              <form method="POST">
                <input type="hidden" name="tab" value="purge_cache">
                <button type="submit"
                        class="settings-nav-item w-100 border-0 text-start"
                        style="background:<?= $activeTab==='purge_cache' ? '#e53e3e' : '#fff5f5' ?>;color:<?= $activeTab==='purge_cache' ? '#fff' : '#c53030' ?>;"
                        onclick="return confirm('Purge all caches now?')">
                  <i class="bi bi-lightning-charge me-2"></i> Purge Cache
                </button>
              </form>
            </div>
          <?php else: ?>
          <a href="?tab=<?= $key ?>"
             class="settings-nav-item <?= $activeTab === $key ? 'active' : '' ?>">
            <i class="bi <?= $tab['icon'] ?> me-2"></i>
            <?= $tab['label'] ?>
            <?php if (!empty($tab['badge'])): ?>
              <span class="badge bg-warning text-dark ms-auto" style="font-size:.65rem;"><?= $tab['badge'] ?></span>
            <?php endif; ?>
          </a>
          <?php endif;
        endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Tab Content -->
  <div class="col-lg-9">

    <?php if ($activeTab === 'general'): ?>
    <!-- ══ GENERAL ══ -->
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="tab" value="general">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-house me-2"></i>General Settings</div>
        <div class="p-4">
          <div class="mb-4">
            <label class="form-label fw-semibold">Site Logo</label>
            <div class="d-flex align-items-center gap-3 mb-2">
              <?php
                $logo = public_asset_url($s['site_logo'] ?? '');
                if ($logo && !empty($s['cache_busted_at'])) {
                    $logo .= (str_contains($logo, '?') ? '&' : '?') . 'v=' . rawurlencode((string)$s['cache_busted_at']);
                }
              ?>
              <?php if ($logo): ?>
                <img src="<?= htmlspecialchars($logo) ?>" style="max-height:48px;max-width:180px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;padding:4px;">
                <span class="text-success small"><i class="bi bi-check-circle me-1"></i>Logo set</span>
              <?php else: ?>
                <div style="width:80px;height:48px;background:#e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-image text-muted"></i></div>
                <span class="text-muted small">No logo uploaded</span>
              <?php endif; ?>
            </div>
            <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml">
            <div class="form-text">PNG or SVG recommended. Max 25MB.</div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Website Favicon</label>
            <div class="d-flex align-items-center gap-3 mb-2">
              <?php
                $favicon = public_asset_url($s['site_favicon'] ?? '');
                if ($favicon && !empty($s['cache_busted_at'])) {
                    $favicon .= (str_contains($favicon, '?') ? '&' : '?') . 'v=' . rawurlencode((string)$s['cache_busted_at']);
                }
              ?>
              <?php if ($favicon): ?>
                <img src="<?= htmlspecialchars($favicon) ?>" style="width:40px;height:40px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;padding:4px;background:#fff;">
                <span class="text-success small"><i class="bi bi-check-circle me-1"></i>Favicon set</span>
              <?php else: ?>
                <div style="width:40px;height:40px;background:#e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-image text-muted"></i></div>
                <span class="text-muted small">No favicon uploaded</span>
              <?php endif; ?>
            </div>
            <input type="file" name="favicon" class="form-control" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,image/webp">
            <div class="form-text">Use square image (recommended 32x32 or 64x64). PNG or ICO recommended.</div>
            <?php if (!empty($s['site_favicon'])): ?>
              <div class="mt-2">
                <label class="form-check-label small">
                  <input type="checkbox" name="remove_favicon" value="1" class="form-check-input me-2">
                  Remove current favicon on save
                </label>
              </div>
            <?php endif; ?>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">SEO Image (Open Graph / Social Share)</label>
            <div class="d-flex align-items-center gap-3 mb-2">
              <?php
                $seoImagePreview = public_asset_url($s['seo_image'] ?? '');
                if ($seoImagePreview && !empty($s['cache_busted_at'])) {
                    $seoImagePreview .= (str_contains($seoImagePreview, '?') ? '&' : '?') . 'v=' . rawurlencode((string)$s['cache_busted_at']);
                }
              ?>
              <?php if ($seoImagePreview): ?>
                <img src="<?= htmlspecialchars($seoImagePreview) ?>" style="width:120px;height:63px;object-fit:cover;border:1px solid #e2e8f0;border-radius:8px;background:#fff;">
                <span class="text-success small"><i class="bi bi-check-circle me-1"></i>SEO image set</span>
              <?php else: ?>
                <div style="width:120px;height:63px;background:#e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-image text-muted"></i></div>
                <span class="text-muted small">No SEO image uploaded</span>
              <?php endif; ?>
            </div>
            <input type="file" name="seo_image" class="form-control" accept="image/jpeg,image/png,image/webp">
            <div class="form-text">Recommended size: 1200x630px for Facebook/LinkedIn previews.</div>
            <?php if (!empty($s['seo_image'])): ?>
              <div class="mt-2">
                <label class="form-check-label small">
                  <input type="checkbox" name="remove_seo_image" value="1" class="form-check-input me-2">
                  Remove current SEO image on save
                </label>
              </div>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Site Name</label>
            <input type="text" name="site_name" class="form-control" value="<?= setting($s,'site_name','ASB Tours') ?>">
          </div>
          <div>
            <label class="form-label fw-semibold">Tagline</label>
            <input type="text" name="site_tagline" class="form-control" value="<?= setting($s,'site_tagline') ?>" placeholder="Come as a guest - Leave as a friend.">
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save General</button>
    </form>

    <?php elseif ($activeTab === 'contact'): ?>
    <!-- CONTACT  -->
    <form method="POST">
      <input type="hidden" name="tab" value="contact">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-telephone me-2"></i>Contact & Location</div>
        <div class="p-4">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Email Address</label>
              <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="contact_email" class="form-control" value="<?= setting($s,'contact_email') ?>" placeholder="info@asbtours.com"></div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Phone Number</label>
              <div class="input-group"><span class="input-group-text"><i class="bi bi-telephone"></i></span>
              <input type="text" name="contact_phone" class="form-control" value="<?= setting($s,'contact_phone') ?>" placeholder="+94 77 123 4567"></div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">WhatsApp Number</label>
              <div class="input-group"><span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
              <input type="text" name="contact_whatsapp" class="form-control" value="<?= setting($s,'contact_whatsapp') ?>" placeholder="+94771234567"></div>
              <div class="form-text">With country code, no spaces.</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Google Maps Embed URL</label>
              <div class="input-group"><span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
              <input type="url" name="maps_link" class="form-control" value="<?= setting($s,'maps_link') ?>" placeholder="https://www.google.com/maps/embed?pb=..."></div>
              <div class="form-text">Go to Google Maps, click <strong>Share &rarr; Embed a map</strong>, then copy only the <code>src="..."</code> URL from the iframe code.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Office Address</label>
              <textarea name="contact_address" class="form-control" rows="3" placeholder="No. 123, Galle Road, Colombo"><?= setting($s,'contact_address') ?></textarea>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Contact</button>
    </form>

    <?php elseif ($activeTab === 'social'): ?>
    <!-- SOCIAL  -->
    <form method="POST">
      <input type="hidden" name="tab" value="social">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-share me-2"></i>Social Media Links</div>
        <div class="p-4">
          <div class="row g-3">
            <?php $socials = [
              'social_facebook'    => ['bi-facebook',  'Facebook',    'https://facebook.com/...'],
              'social_instagram'   => ['bi-instagram', 'Instagram',   'https://instagram.com/...'],
              'social_youtube'     => ['bi-youtube',   'YouTube',     'https://youtube.com/...'],
              'social_tripadvisor' => ['bi-globe',     'TripAdvisor', 'https://tripadvisor.com/...'],
              'social_twitter'     => ['bi-twitter-x', 'Twitter / X', 'https://x.com/...'],
            ];
            foreach ($socials as $k => [$icon, $label, $ph]): ?>
              <div class="col-sm-6">
                <label class="form-label fw-semibold"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></label>
                <input type="url" name="<?= $k ?>" class="form-control" value="<?= setting($s,$k) ?>" placeholder="<?= $ph ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <div class="alert alert-info mt-4 mb-0 py-2 small"><i class="bi bi-info-circle me-1"></i>Leave blank to hide that icon from the website footer.</div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Social</button>
    </form>

    <?php elseif ($activeTab === 'appearance'): ?>
    <form method="POST">
      <input type="hidden" name="tab" value="appearance">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-palette me-2"></i>Global Appearance</div>
        <div class="p-4">
          <div class="alert alert-info py-2 mb-4 small">
            <i class="bi bi-info-circle me-1"></i>
            These colours update the website's global theme. Save changes, then refresh the website to see the new styling.
          </div>
          <div class="row g-3">
            <?php foreach ($themeSettings as $key => $meta): ?>
              <?php $value = theme_normalize_hex_color($s[$key] ?? $meta['default'], $meta['default']); ?>
              <div class="col-md-6">
                <label class="form-label fw-semibold"><?= htmlspecialchars($meta['label']) ?></label>
                <div class="theme-color-input">
                  <input type="color" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>" class="form-control form-control-color">
                  <input type="text" value="<?= htmlspecialchars($value) ?>" class="form-control theme-hex-input" data-sync-color="<?= htmlspecialchars($key) ?>">
                </div>
                <div class="form-text"><?= htmlspecialchars($meta['description']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-eye me-2"></i>Live Preview Guide</div>
        <div class="p-4">
          <div class="theme-preview">
            <div class="theme-preview__hero">
              <span class="theme-preview__badge">Brand Preview</span>
              <h4><?= htmlspecialchars($s['site_name'] ?? 'ASB Tours') ?></h4>
              <p>Primary, secondary and dark colours will affect buttons, highlights, sections and overlays across the website.</p>
              <div class="d-flex flex-wrap gap-2">
                <span class="btn btn-primary btn-sm">Primary Button</span>
                <span class="btn btn-outline-primary btn-sm theme-preview__outline">Outline Button</span>
              </div>
            </div>
            <div class="theme-preview__card">
              <div class="theme-preview__swatches">
                <?php foreach (array_slice($themeSettings, 0, 6, true) as $key => $meta): ?>
                  <?php $value = theme_normalize_hex_color($s[$key] ?? $meta['default'], $meta['default']); ?>
                  <div class="theme-preview__swatch">
                    <span style="background: <?= htmlspecialchars($value) ?>;"></span>
                    <small><?= htmlspecialchars($meta['label']) ?></small>
                  </div>
                <?php endforeach; ?>
              </div>
              <p class="mb-0 small text-muted">Tip: if you want a lighter brand look, start with <strong>Primary</strong>, <strong>Primary Light</strong>, and <strong>Accent</strong>.</p>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Appearance</button>
    </form>

    <?php elseif ($activeTab === 'seo'): ?>
    <!-- SEO  -->
    <form method="POST">
      <input type="hidden" name="tab" value="seo">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-search me-2"></i>SEO Settings</div>
        <div class="p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Meta Title</label>
            <input type="text" name="seo_meta_title" id="metaTitleInput" class="form-control" maxlength="70"
                   value="<?= setting($s,'seo_meta_title') ?>" placeholder="ASB Tours, Sri Lanka Tour Packages">
            <div class="d-flex justify-content-between mt-1">
              <div class="form-text">Shown in browser tab &amp; Google results.</div>
              <div class="form-text" id="titleCount">0 / 70</div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Meta Description</label>
            <textarea name="seo_meta_desc" id="metaDescInput" class="form-control" rows="3" maxlength="160"
                      placeholder="Discover the best Sri Lanka tour packages..."><?= setting($s,'seo_meta_desc') ?></textarea>
            <div class="d-flex justify-content-between mt-1">
              <div class="form-text">Ideal: 150–160 characters.</div>
              <div class="form-text" id="descCount">0 / 160</div>
            </div>
          </div>
          <div>
            <label class="form-label fw-semibold">Google Analytics ID</label>
            <div class="input-group"><span class="input-group-text"><i class="bi bi-bar-chart"></i></span>
            <input type="text" name="google_analytics" class="form-control" value="<?= setting($s,'google_analytics') ?>" placeholder="G-XXXXXXXXXX"></div>
            <div class="form-text">Your GA4 Measurement ID.</div>
          </div>
        </div>
      </div>
      <!-- Google preview -->
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-google me-2"></i>Google Preview</div>
        <div class="p-4">
          <div class="google-preview">
            <div class="gp-url">asbtours.com</div>
            <div class="gp-title" id="gpTitle"><?= setting($s,'seo_meta_title','Page Title') ?></div>
            <div class="gp-desc"  id="gpDesc"><?= setting($s,'seo_meta_desc','Your meta description will appear here...') ?></div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save SEO</button>
    </form>

    <?php elseif ($activeTab === 'maintenance'): ?>
    <!-- MAINTENANCE -->
    <form method="POST">
      <input type="hidden" name="tab" value="maintenance">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-cone-striped me-2"></i>Maintenance Mode</div>
        <div class="p-4">

          <!-- Big toggle -->
          <div class="maintenance-toggle-wrap <?= $maintenanceOn ? 'maintenance-on' : '' ?>">
            <div class="maintenance-icon">
              <i class="bi bi-<?= $maintenanceOn ? 'cone-striped' : 'check-circle' ?>"></i>
            </div>
            <div>
              <div class="maintenance-status">
                Site is currently <strong><?= $maintenanceOn ? 'UNDER MAINTENANCE' : 'LIVE' ?></strong>
              </div>
              <div class="text-muted small">
                <?= $maintenanceOn
                  ? 'Visitors see the maintenance message. Admins can still access the backend.'
                  : 'Your website is fully accessible to all visitors.' ?>
              </div>
            </div>
            <div class="ms-auto">
              <div class="form-check form-switch form-check-lg">
                <input class="form-check-input" type="checkbox" name="maintenance_mode"
                       id="maintenanceToggle" style="width:3rem;height:1.5rem;"
                       <?= $maintenanceOn ? 'checked' : '' ?>>
              </div>
            </div>
          </div>

          <div class="mt-4">
            <label class="form-label fw-semibold">Maintenance Message</label>
            <textarea name="maintenance_message" class="form-control" rows="4"
                      placeholder="We are currently performing maintenance. We'll be back shortly!"><?= setting($s,'maintenance_message') ?></textarea>
            <div class="form-text">This message is shown to visitors when maintenance mode is ON.</div>
          </div>

        </div>
      </div>

      <div class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Warning:</strong> Turning ON maintenance mode will show a maintenance page to all visitors.
        Make sure to turn it OFF when your work is done.
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save me-1"></i> Save Maintenance Settings
      </button>
    </form>

    <?php elseif ($activeTab === 'smtp'): ?>
    <!-- SMTP -->
    <form method="POST">
      <input type="hidden" name="tab" value="smtp">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-envelope-at me-2"></i>SMTP Email Settings</div>
        <div class="p-4">
          <div class="alert alert-info py-2 mb-4 small">
            <i class="bi bi-info-circle me-1"></i>
            Used for sending booking confirmations and inquiry notifications.
            Works with Gmail, cPanel Mail, SendGrid, Mailgun etc.
          </div>
          <div class="row g-3">
            <div class="col-sm-8">
              <label class="form-label fw-semibold">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control"
                     value="<?= setting($s,'smtp_host') ?>"
                     placeholder="mail.asbtours.com  or  smtp.gmail.com">
            </div>
            <div class="col-sm-4">
              <label class="form-label fw-semibold">Port</label>
              <input type="number" name="smtp_port" class="form-control"
                     value="<?= setting($s,'smtp_port','587') ?>"
                     placeholder="587">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Username</label>
              <input type="text" name="smtp_username" class="form-control"
                     value="<?= setting($s,'smtp_username') ?>"
                     placeholder="info@asbtours.com">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Password</label>
              <input type="password" name="smtp_password" class="form-control"
                     placeholder="<?= ($s['smtp_password'] ?? '') ? '••••••••  (saved)' : 'Enter password' ?>">
              <div class="form-text">Leave blank to keep existing password.</div>
            </div>
            <div class="col-sm-4">
              <label class="form-label fw-semibold">Encryption</label>
              <select name="smtp_encryption" class="form-select">
                <?php foreach (['tls'=>'TLS (Recommended)','ssl'=>'SSL','none'=>'None'] as $v=>$l): ?>
                  <option value="<?= $v ?>" <?= ($s['smtp_encryption']??'tls') === $v ? 'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label fw-semibold">From Name</label>
              <input type="text" name="smtp_from_name" class="form-control"
                     value="<?= setting($s,'smtp_from_name','ASB Tours') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label fw-semibold">From Email</label>
              <input type="email" name="smtp_from_email" class="form-control"
                     value="<?= setting($s,'smtp_from_email') ?>"
                     placeholder="no-reply@asbtours.com">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">
                Notification Email
                <small class="text-muted fw-normal">(receive booking & inquiry alerts)</small>
              </label>
              <input type="email" name="smtp_notify_email" class="form-control"
                     value="<?= setting($s,'smtp_notify_email') ?>"
                     placeholder="owner@asbtours.com">
            </div>
          </div>
        </div>
      </div>

      <!-- Test email -->
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-send me-2"></i>Send Test Email</div>
        <div class="p-4">
          <div class="row g-2 align-items-end">
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Test Email Address</label>
              <input type="email" name="test_email" class="form-control"
                     placeholder="your@email.com">
            </div>
            <div class="col-sm-4">
              <button type="submit" name="send_test" class="btn btn-outline-primary w-100">
                <i class="bi bi-send me-1"></i> Send Test
              </button>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save SMTP Settings</button>
    </form>

    <?php elseif ($activeTab === 'security'): ?>
    <!-- SECURITY  -->
    <form method="POST">
      <input type="hidden" name="tab" value="security">

      <!-- Turnstile -->
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-cloud-check me-2"></i>Bot Protection, Cloudflare Turnstile</div>
        <div class="p-4">
          <div class="alert alert-info py-2 mb-4 small">
            <i class="bi bi-info-circle me-1"></i>
            Protects the contact form, booking form, and admin login page from spam and abuse.
            Get your keys from <strong>dash.cloudflare.com</strong> and create a <strong>Turnstile</strong> widget for this domain.
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Site Key <small class="text-muted">(public)</small></label>
              <input type="text" name="turnstile_site_key" class="form-control"
                     value="<?= setting($s,'turnstile_site_key') ?>"
                     placeholder="0x4AAAA...">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Secret Key <small class="text-muted">(private)</small></label>
              <input type="text" name="turnstile_secret_key" class="form-control"
                     value="<?= setting($s,'turnstile_secret_key') ?>"
                     placeholder="0x4AAAA...">
            </div>
          </div>
        </div>
      </div>

      <!-- Login protection -->
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Login Brute Force Protection</div>
        <div class="p-4">
          <div class="row g-3">
            <div class="col-sm-5">
              <label class="form-label fw-semibold">Max Login Attempts</label>
              <input type="number" name="max_login_attempts" class="form-control"
                     value="<?= setting($s,'max_login_attempts','5') ?>"
                     min="3" max="20">
              <div class="form-text">Attempts before lockout.</div>
            </div>
            <div class="col-sm-5">
              <label class="form-label fw-semibold">Lockout Duration (minutes)</label>
              <input type="number" name="lockout_minutes" class="form-control"
                     value="<?= setting($s,'lockout_minutes','15') ?>"
                     min="5" max="1440">
              <div class="form-text">How long to block the IP.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Security tips -->
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-lightbulb me-2 text-warning"></i>Security Checklist</div>
        <div class="p-4">
          <ul class="list-unstyled mb-0" style="font-size:.875rem;line-height:2.2;">
            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Admin panel protected by session auth</li>
            <li><i class="bi bi-check-circle-fill text-success me-2"></i>Passwords stored as bcrypt hashes</li>
            <li><i class="bi bi-check-circle-fill text-success me-2"></i>All user inputs sanitized with htmlspecialchars</li>
            <li><i class="bi bi-check-circle-fill text-success me-2"></i>DB queries use prepared statements (PDO)</li>
            <li><i class="bi bi-<?= ($s['turnstile_site_key']??'') ? 'check-circle-fill text-success' : 'x-circle-fill text-warning' ?> me-2"></i>Cloudflare Turnstile configured</li>
            <li><i class="bi bi-info-circle text-primary me-2"></i>On cPanel: enable SSL/HTTPS from hosting panel</li>
            <li><i class="bi bi-info-circle text-primary me-2"></i>Rename /admin folder for extra security (optional)</li>
          </ul>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Security Settings</button>
    </form>

    <?php elseif ($activeTab === 'about'): ?>
    <!-- ABOUT  -->
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="tab" value="about">

      <!-- About Image -->
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-image me-2"></i>About Section Image</div>
        <div class="p-4">
          <div class="row g-3 align-items-center">
            <div class="col-sm-5">
              <?php $aboutImg = public_asset_url($s['about_image'] ?? ''); ?>
              <?php if ($aboutImg): ?>
                <img src="<?= htmlspecialchars($aboutImg) ?>" id="aboutImgPreview"
                     style="width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0;">
              <?php else: ?>
                <div id="aboutImgPreview"
                     style="width:100%;height:180px;background:#e2e8f0;border-radius:10px;
                            display:flex;align-items:center;justify-content:center;flex-direction:column;gap:.5rem;">
                  <i class="bi bi-image fs-2 text-muted"></i>
                  <span class="text-muted small">No image</span>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-sm-7">
              <label class="form-label fw-semibold">Upload Image</label>
              <input type="file" name="about_image" id="aboutImgInput"
                     class="form-control mb-2" accept="image/jpeg,image/png,image/webp">
              <div class="form-text mb-3">JPG, PNG or WEBP. Max 4MB. Recommended: 800×600px.</div>
              <?php if ($aboutImg): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="remove_about_image" id="removeAboutImg">
                  <label class="form-check-label text-danger small" for="removeAboutImg">
                    <i class="bi bi-trash me-1"></i>Remove current image
                  </label>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-info-circle me-2"></i>About Section Content</div>
        <div class="p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Section Heading <span class="text-muted fw-normal">(first part, dark colour)</span></label>
            <input type="text" name="about_heading" class="form-control"
                   value="<?= setting($s,'about_heading',"Sri Lanka's Trusted") ?>">
            <div class="form-text">e.g. <em>Sri Lanka's Trusted</em></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Section Heading Accent <span class="text-muted fw-normal">(second part, blue highlight)</span></label>
            <input type="text" name="about_heading_accent" class="form-control"
                   value="<?= setting($s,'about_heading_accent','Travel Specialists') ?>">
            <div class="form-text">e.g. <em>Travel Specialists</em>, shown in accent blue colour</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Main Description</label>
            <textarea name="about_description" class="form-control" rows="5"
                      placeholder="Tell your story, who you are, your passion for travel, why customers should choose you..."><?= setting($s,'about_description') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Our Mission</label>
            <textarea name="about_mission" class="form-control" rows="3"
                      placeholder="Our mission is to create unforgettable Sri Lanka experiences..."><?= setting($s,'about_mission') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Stats counters -->
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-bar-chart-line me-2"></i>Stats Counters</div>
        <div class="p-4">
          <div class="row g-3">
            <div class="col-sm-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-calendar-check text-primary me-1"></i>Years of Experience
              </label>
              <input type="text" name="about_years" class="form-control"
                     value="<?= setting($s,'about_years') ?>" placeholder="10">
              <div class="form-text">Shown as badge next to about image.</div>
            </div>
            <div class="col-sm-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-suitcase text-primary me-1"></i>Tours Done
              </label>
              <input type="text" name="about_tours_count" class="form-control"
                     value="<?= setting($s,'about_tours_count') ?>" placeholder="800">
              <div class="form-text">Number only (e.g. 800)</div>
            </div>
            <div class="col-sm-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-emoji-smile text-primary me-1"></i>Happy Guests
              </label>
              <input type="text" name="about_clients_count" class="form-control"
                     value="<?= setting($s,'about_clients_count') ?>" placeholder="3500">
              <div class="form-text">Number only (e.g. 3500)</div>
            </div>
            <div class="col-sm-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-geo-alt text-primary me-1"></i>Destinations
              </label>
              <input type="text" name="about_destinations" class="form-control"
                     value="<?= setting($s,'about_destinations') ?>" placeholder="25">
              <div class="form-text">Number only (e.g. 25)</div>
            </div>
            <div class="col-sm-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-trophy text-primary me-1"></i>Awards Won
              </label>
              <input type="text" name="about_awards" class="form-control"
                     value="<?= setting($s,'about_awards') ?>" placeholder="12">
              <div class="form-text">Number only (e.g. 12)</div>
            </div>
          </div>
          <div class="alert alert-info py-2 mt-3 mb-0 small">
            <i class="bi bi-info-circle me-1"></i>
            Enter numbers only, the <strong>+</strong> sign is added automatically by the website counter animation.
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save About Section</button>
    </form>

    <?php elseif ($activeTab === 'purge_cache'): ?>
    <!-- PURGE CACHE -->
    <div class="admin-card mb-3">
      <div class="card-header" style="color:#c53030;"><i class="bi bi-lightning-charge me-2"></i>Cache Purged Successfully</div>
      <div class="p-4 text-center">
        <div style="width:72px;height:72px;background:#fff5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:2rem;color:#c53030;">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <h5 class="fw-bold text-success">All caches cleared!</h5>
        <p class="text-muted small mt-2">PHP OPcache reset, cache version updated.</p>
        <a href="?tab=purge_cache" class="btn btn-outline-secondary mt-3">
          <i class="bi bi-arrow-left me-1"></i> Back to Settings
        </a>
      </div>
    </div>

    <?php elseif ($activeTab === 'account'): ?>
    <!-- ACCOUNT  -->
    <div class="admin-card mb-3">
      <div class="card-header"><i class="bi bi-person-badge me-2"></i>Account Info</div>
      <div class="p-4">
        <div class="row g-3">
          <div class="col-sm-4">
            <div class="text-muted small text-uppercase fw-semibold mb-1">Name</div>
            <div><?= htmlspecialchars($adminInfo['name']) ?></div>
          </div>
          <div class="col-sm-4">
            <div class="text-muted small text-uppercase fw-semibold mb-1">Username</div>
            <div><?= htmlspecialchars($adminInfo['username']) ?></div>
          </div>
          <div class="col-sm-4">
            <div class="text-muted small text-uppercase fw-semibold mb-1">Role</div>
            <span class="badge" style="background:#e0f4fc;color:#0077B6;"><?= ucfirst(str_replace('_',' ',$adminInfo['role'])) ?></span>
          </div>
        </div>
      </div>
    </div>

    <form method="POST" class="mb-3">
      <input type="hidden" name="tab" value="account">
      <div class="admin-card">
        <div class="card-header"><i class="bi bi-person me-2"></i>Change Username</div>
        <div class="p-4">
          <div class="col-sm-6">
            <label class="form-label fw-semibold">New Username</label>
            <input type="text" name="new_username" class="form-control"
                   value="<?= htmlspecialchars($adminInfo['username']) ?>">
          </div>
          <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Update Username</button>
        </div>
      </div>
    </form>

    <form method="POST">
      <input type="hidden" name="tab" value="account">
      <div class="admin-card mb-3">
        <div class="card-header"><i class="bi bi-key me-2"></i>Change Password</div>
        <div class="p-4">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Current Password</label>
              <input type="password" name="current_password" class="form-control" placeholder="Enter current password">
            </div>
            <div class="col-12"></div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">New Password</label>
              <input type="password" name="new_password" class="form-control" placeholder="Min. 8 characters">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i>Change Password</button>
    </form>

    <?php endif; ?>

  </div>
</div>

<style>
.settings-nav { padding: .5rem; }
.settings-nav-item {
  display: flex; align-items: center;
  padding: .7rem 1rem; border-radius: 8px;
  color: #4a5568; text-decoration: none;
  font-size: .875rem; font-weight: 500;
  transition: background .15s, color .15s;
  margin-bottom: 2px;
}
.settings-nav-item:hover  { background: #f0f8ff; color: #0077B6; }
.settings-nav-item.active { background: #0077B6; color: #fff; font-weight: 600; }
.settings-nav-item.active:hover { color: #fff; }

.theme-color-input {
  display: grid;
  grid-template-columns: 64px 1fr;
  gap: .75rem;
  align-items: center;
}
.theme-color-input .form-control-color {
  width: 64px;
  height: 44px;
  padding: .35rem;
}
.theme-preview {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 1rem;
}
.theme-preview__hero {
  padding: 1.5rem;
  border-radius: 18px;
  background: linear-gradient(135deg,
    <?= htmlspecialchars(theme_normalize_hex_color($s['theme_dark'] ?? '#03045E', '#03045E')) ?> 0%,
    <?= htmlspecialchars(theme_normalize_hex_color($s['theme_primary'] ?? '#0077B6', '#0077B6')) ?> 100%);
  color: #fff;
}
.theme-preview__badge {
  display: inline-block;
  padding: .35rem .75rem;
  border-radius: 999px;
  background: rgba(255,255,255,.18);
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
}
.theme-preview__hero h4 {
  margin: 1rem 0 .5rem;
}
.theme-preview__hero p {
  margin-bottom: 1rem;
  color: rgba(255,255,255,.88);
}
.theme-preview__outline {
  border-color: rgba(255,255,255,.65);
  color: #fff;
}
.theme-preview__outline:hover {
  background: rgba(255,255,255,.12);
  color: #fff;
}
.theme-preview__card {
  padding: 1.25rem;
  border-radius: 18px;
  border: 1px solid #e2e8f0;
  background: #fff;
}
.theme-preview__swatches {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .75rem;
  margin-bottom: 1rem;
}
.theme-preview__swatch {
  display: flex;
  align-items: center;
  gap: .6rem;
  font-size: .875rem;
  color: #4a5568;
}
.theme-preview__swatch span {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 1px solid rgba(0,0,0,.08);
  flex-shrink: 0;
}
@media (max-width: 768px) {
  .theme-color-input {
    grid-template-columns: 56px 1fr;
  }
  .theme-preview {
    grid-template-columns: 1fr;
  }
}

/* Maintenance toggle card */
.maintenance-toggle-wrap {
  display: flex; align-items: center; gap: 1rem;
  padding: 1.25rem; border-radius: 12px;
  background: #f0fff4; border: 2px solid #9ae6b4;
  transition: background .3s, border-color .3s;
}
.maintenance-toggle-wrap.maintenance-on {
  background: #fffbeb; border-color: #f6ad55;
}
.maintenance-icon {
  width: 50px; height: 50px; border-radius: 12px;
  background: #9ae6b4; color: #276749;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; flex-shrink: 0;
}
.maintenance-on .maintenance-icon { background: #f6ad55; color: #744210; }
.maintenance-status { font-weight: 600; font-size: .95rem; margin-bottom: .15rem; }

/* Google preview */
.google-preview { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.25rem; max-width:600px; }
.gp-url   { font-size:.8rem; color:#188038; margin-bottom:.2rem; }
.gp-title { font-size:1.1rem; color:#1a0dab; margin-bottom:.2rem; }
.gp-desc  { font-size:.85rem; color:#4d5156; line-height:1.5; }
</style>

<script>
// SEO counters & preview
const titleInput = document.getElementById('metaTitleInput');
const descInput  = document.getElementById('metaDescInput');
if (titleInput) {
  const cnt = document.getElementById('titleCount');
  const gp  = document.getElementById('gpTitle');
  const upd = () => {
    cnt.textContent = titleInput.value.length + ' / 70';
    cnt.style.color = titleInput.value.length > 70 ? '#c53030' : titleInput.value.length > 60 ? '#d69e2e' : '#718096';
    if (gp) gp.textContent = titleInput.value || 'Page Title';
  };
  titleInput.addEventListener('input', upd); upd();
}
if (descInput) {
  const cnt = document.getElementById('descCount');
  const gp  = document.getElementById('gpDesc');
  const upd = () => {
    cnt.textContent = descInput.value.length + ' / 160';
    cnt.style.color = descInput.value.length > 160 ? '#c53030' : descInput.value.length > 150 ? '#d69e2e' : '#718096';
    if (gp) gp.textContent = descInput.value || 'Meta description...';
  };
  descInput.addEventListener('input', upd); upd();
}

// About image preview
const aboutImgInput = document.getElementById('aboutImgInput');
if (aboutImgInput) {
  aboutImgInput.addEventListener('change', function () {
    if (this.files[0]) {
      const r = new FileReader();
      r.onload = e => {
        const prev = document.getElementById('aboutImgPreview');
        if (prev.tagName === 'IMG') {
          prev.src = e.target.result;
        } else {
          const img    = document.createElement('img');
          img.src      = e.target.result;
          img.id       = 'aboutImgPreview';
          img.style.cssText = 'width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0;';
          prev.replaceWith(img);
        }
      };
      r.readAsDataURL(this.files[0]);
    }
  });
}

document.querySelectorAll('[data-sync-color]').forEach(hexInput => {
  const colorInput = document.querySelector(`input[type="color"][name="${hexInput.dataset.syncColor}"]`);
  if (!colorInput) return;

  colorInput.addEventListener('input', () => {
    hexInput.value = colorInput.value.toUpperCase();
  });

  hexInput.addEventListener('input', () => {
    if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hexInput.value)) {
      colorInput.value = hexInput.value;
    }
  });

  hexInput.addEventListener('blur', () => {
    hexInput.value = colorInput.value.toUpperCase();
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
