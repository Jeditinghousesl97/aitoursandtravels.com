<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

// Helper: load all service settings
function loadSettings(PDO $pdo): array {
    $rows = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'svc_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    return $rows;
}

// Helper: upsert a setting
function saveSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
        ->execute([$key, $value]);
}

$errors  = [];
$success = false;
$tab     = $_GET['stab'] ?? 'intro';
if (!in_array($tab, ['intro','stats','steps','cta'])) $tab = 'intro';

$defaultSteps = [
    ['id' => 0, 'step_number' => 1, 'icon_class' => 'fa-comment-dots', 'title' => 'Tell Us Your Dream', 'description' => 'Share your travel dates, budget, interests and any special requirements. The more you tell us, the better we can plan.'],
    ['id' => 0, 'step_number' => 2, 'icon_class' => 'fa-pencil-ruler', 'title' => 'We Plan Your Trip', 'description' => 'Our travel experts craft a personalised itinerary with flights, hotels, guides, and activities tailored just for you.'],
    ['id' => 0, 'step_number' => 3, 'icon_class' => 'fa-circle-check', 'title' => 'Book & Confirm', 'description' => 'Review your itinerary, request any tweaks, and confirm your booking. We handle all reservations and send you full confirmation.'],
    ['id' => 0, 'step_number' => 4, 'icon_class' => 'fa-sun', 'title' => 'Enjoy Sri Lanka', 'description' => 'Arrive, relax, and explore. Your driver meets you at the airport and our team is just a message away throughout your trip.'],
];

$stepsTableExists = tableExists($pdo, 'process_steps');
$steps = $defaultSteps;

if ($stepsTableExists) {
    $steps = $pdo->query("SELECT * FROM process_steps ORDER BY sort_order, step_number")->fetchAll();

    // Ensure 4 steps exist as defaults
    while (count($steps) < 4) {
        $n = count($steps) + 1;
        $pdo->prepare('INSERT INTO process_steps (step_number, icon_class, title, description, sort_order) VALUES (?,?,?,?,?)')
            ->execute([$n, 'fa-circle-question', 'Step '.$n, '', $n]);
        $steps = $pdo->query("SELECT * FROM process_steps ORDER BY sort_order, step_number")->fetchAll();
    }
} elseif ($tab === 'steps') {
    $errors[] = 'The "process_steps" table is missing, so the page is showing built-in default steps. Import the latest database schema to enable editing.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['stab'] ?? 'intro';

    if ($tab === 'intro') {
        saveSetting($pdo, 'svc_intro_heading', trim($_POST['svc_intro_heading'] ?? ''));
        saveSetting($pdo, 'svc_intro_text1',   trim($_POST['svc_intro_text1']   ?? ''));
        saveSetting($pdo, 'svc_intro_text2',   trim($_POST['svc_intro_text2']   ?? ''));
        // Trust badges (4 fixed)
        for ($i = 1; $i <= 4; $i++) {
            saveSetting($pdo, "svc_badge{$i}_icon",  trim($_POST["svc_badge{$i}_icon"]  ?? ''));
            saveSetting($pdo, "svc_badge{$i}_title", trim($_POST["svc_badge{$i}_title"] ?? ''));
            saveSetting($pdo, "svc_badge{$i}_text",  trim($_POST["svc_badge{$i}_text"]  ?? ''));
        }
        $success = true;
    }

    if ($tab === 'stats') {
        saveSetting($pdo, 'svc_stat1_number', trim($_POST['svc_stat1_number'] ?? ''));
        saveSetting($pdo, 'svc_stat1_label',  trim($_POST['svc_stat1_label']  ?? ''));
        saveSetting($pdo, 'svc_stat2_number', trim($_POST['svc_stat2_number'] ?? ''));
        saveSetting($pdo, 'svc_stat2_label',  trim($_POST['svc_stat2_label']  ?? ''));
        saveSetting($pdo, 'svc_stat3_number', trim($_POST['svc_stat3_number'] ?? ''));
        saveSetting($pdo, 'svc_stat3_label',  trim($_POST['svc_stat3_label']  ?? ''));
        saveSetting($pdo, 'svc_stat4_number', trim($_POST['svc_stat4_number'] ?? ''));
        saveSetting($pdo, 'svc_stat4_label',  trim($_POST['svc_stat4_label']  ?? ''));
        $success = true;
    }

    if ($tab === 'steps') {
        if (!$stepsTableExists) {
            $errors[] = 'Step settings could not be saved because the "process_steps" table does not exist yet.';
        } else {
            $stepIds = $_POST['step_id'] ?? [];
            foreach ($stepIds as $idx => $sid) {
                $sid = (int)$sid;
                if (!$sid) continue;
                $icon  = trim($_POST['step_icon'][$idx]  ?? '');
                $title = trim($_POST['step_title'][$idx] ?? '');
                $desc  = trim($_POST['step_desc'][$idx]  ?? '');
                $pdo->prepare('UPDATE process_steps SET icon_class=?, title=?, description=? WHERE id=?')
                    ->execute([$icon ?: 'fa-circle-question', $title, $desc ?: null, $sid]);
            }

            $steps = $pdo->query("SELECT * FROM process_steps ORDER BY sort_order, step_number")->fetchAll();
            $success = true;
        }
    }

    if ($tab === 'cta') {
        saveSetting($pdo, 'svc_cta_heading', trim($_POST['svc_cta_heading'] ?? ''));
        saveSetting($pdo, 'svc_cta_text',    trim($_POST['svc_cta_text']    ?? ''));
        $success = true;
    }
}

$s = loadSettings($pdo);

// Defaults
$defaults = [
    'svc_intro_heading' => "Sri Lanka's Most Trusted Travel Partner",
    'svc_intro_text1'   => "At ASB Tours, we believe every journey should be effortless and extraordinary. With over a decade of experience crafting travel experiences across Sri Lanka, we've built our reputation on personalised service, deep local knowledge, and an unwavering commitment to making your trip truly unforgettable.",
    'svc_intro_text2'   => "From the moment you contact us to the time you return home, our team is with you every step of the way, handling everything so you can simply focus on soaking in the beauty of Sri Lanka.",
    'svc_badge1_icon'   => 'fa-certificate',
    'svc_badge1_title'  => 'Licensed & Certified Tour Operator',
    'svc_badge1_text'   => 'Registered with Sri Lanka Tourism Development Authority',
    'svc_badge2_icon'   => 'fa-headset',
    'svc_badge2_title'  => '24 / 7 Customer Support',
    'svc_badge2_text'   => 'Always reachable, before, during, and after your trip',
    'svc_badge3_icon'   => 'fa-tag',
    'svc_badge3_title'  => 'Best Price Guarantee',
    'svc_badge3_text'   => 'We match or beat any comparable quoted price',
    'svc_badge4_icon'   => 'fa-shield-halved',
    'svc_badge4_title'  => '100% Secure Bookings',
    'svc_badge4_text'   => 'Fully insured tours with transparent pricing, no hidden fees',
    'svc_stat1_number'  => '10+',
    'svc_stat1_label'   => 'Years of Experience',
    'svc_stat2_number'  => '3,500+',
    'svc_stat2_label'   => 'Happy Travelers',
    'svc_stat3_number'  => '25+',
    'svc_stat3_label'   => 'Destinations Covered',
    'svc_stat4_number'  => '24/7',
    'svc_stat4_label'   => 'Customer Support',
    'svc_cta_heading'   => 'Ready to Explore Sri Lanka?',
    'svc_cta_text'      => "Contact our travel experts today and let us craft your perfect Sri Lanka journey. It's easier than you think.",
];
foreach ($defaults as $k => $v) {
    if (!isset($s[$k]) || $s[$k] === '') $s[$k] = $v;
}

$v = fn(string $key) => htmlspecialchars($s[$key] ?? '');

$pageTitle = 'Services – Page Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-sliders me-2 text-primary"></i>Services, Page Settings</h1>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back to Services
  </a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>Settings saved successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-warning alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>

<!-- Settings tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'intro' ? 'active' : '' ?>" href="settings.php?stab=intro">
      <i class="bi bi-file-text me-1"></i>Intro & Badges
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'stats' ? 'active' : '' ?>" href="settings.php?stab=stats">
      <i class="bi bi-bar-chart me-1"></i>Stats Strip
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'steps' ? 'active' : '' ?>" href="settings.php?stab=steps">
      <i class="bi bi-list-ol me-1"></i>How It Works
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'cta' ? 'active' : '' ?>" href="settings.php?stab=cta">
      <i class="bi bi-megaphone me-1"></i>CTA Banner
    </a>
  </li>
</ul>

<!-- INTRO & TRUST BADGES -->
<?php if ($tab === 'intro'): ?>
<form method="POST">
  <input type="hidden" name="stab" value="intro">

  <div class="admin-card mb-3">
    <div class="card-header">Intro Section</div>
    <div class="p-3">
      <div class="mb-3">
        <label class="form-label fw-semibold">Heading</label>
        <input type="text" name="svc_intro_heading" class="form-control"
               value="<?= $v('svc_intro_heading') ?>">
        <div class="form-text">The <strong><span></span></strong> wrapped word will be highlighted in blue on the page.</div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">First Paragraph</label>
        <textarea name="svc_intro_text1" class="form-control" rows="4"><?= $v('svc_intro_text1') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Second Paragraph</label>
        <textarea name="svc_intro_text2" class="form-control" rows="3"><?= $v('svc_intro_text2') ?></textarea>
      </div>
    </div>
  </div>

  <div class="admin-card mb-3">
    <div class="card-header">Trust Badges (4 fixed badges)</div>
    <div class="p-3">
      <?php for ($i = 1; $i <= 4; $i++): ?>
      <div class="border rounded p-3 mb-3">
        <div class="fw-semibold mb-2 text-primary"><i class="bi bi-shield-check me-1"></i>Badge <?= $i ?></div>
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label small">Icon (FA class)</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">
                <i class="fa-solid <?= $v("svc_badge{$i}_icon") ?>" id="badge<?= $i ?>El"></i>
              </span>
              <input type="text" name="svc_badge<?= $i ?>_icon" class="form-control badge-icon-input"
                     data-preview="badge<?= $i ?>El"
                     value="<?= $v("svc_badge{$i}_icon") ?>" placeholder="fa-certificate">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Title / Heading</label>
            <input type="text" name="svc_badge<?= $i ?>_title" class="form-control form-control-sm"
                   value="<?= $v("svc_badge{$i}_title") ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label small">Sub-text</label>
            <input type="text" name="svc_badge<?= $i ?>_text" class="form-control form-control-sm"
                   value="<?= $v("svc_badge{$i}_text") ?>">
          </div>
        </div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg me-1"></i> Save Intro Settings
  </button>
</form>

<!-- STATS STRIP -->
<?php elseif ($tab === 'stats'): ?>
<form method="POST">
  <input type="hidden" name="stab" value="stats">

  <div class="admin-card mb-3">
    <div class="card-header">Stats Strip (4 stats shown as a dark band)</div>
    <div class="p-3">
      <div class="row g-3">
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="col-md-3">
          <div class="border rounded p-3 text-center">
            <div class="fw-semibold text-primary mb-2">Stat <?= $i ?></div>
            <div class="mb-2">
              <label class="form-label small">Number / Value</label>
              <input type="text" name="svc_stat<?= $i ?>_number" class="form-control form-control-sm text-center"
                     value="<?= $v("svc_stat{$i}_number") ?>" placeholder="10+">
            </div>
            <div>
              <label class="form-label small">Label</label>
              <input type="text" name="svc_stat<?= $i ?>_label" class="form-control form-control-sm text-center"
                     value="<?= $v("svc_stat{$i}_label") ?>" placeholder="Years of Experience">
            </div>
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <!-- Preview -->
  <div class="admin-card mb-3">
    <div class="card-header"><i class="bi bi-eye me-2"></i>Preview</div>
    <div class="p-3">
      <div style="background:linear-gradient(135deg,#03045E,#0077B6);border-radius:12px;padding:1.5rem;">
        <div class="row g-3 text-center text-white">
          <?php for ($i = 1; $i <= 4; $i++): ?>
          <div class="col-md-3">
            <div style="font-size:1.8rem;font-weight:800;"><?= $v("svc_stat{$i}_number") ?></div>
            <div style="font-size:.85rem;opacity:.8;"><?= $v("svc_stat{$i}_label") ?></div>
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg me-1"></i> Save Stats
  </button>
</form>

<!-- HOW IT WORKS STEPS -->
<?php elseif ($tab === 'steps'): ?>
<form method="POST">
  <input type="hidden" name="stab" value="steps">

  <div class="admin-card mb-3">
    <div class="card-header">How It Works, 4 Process Steps</div>
    <div class="p-3">
      <div class="row g-3">
        <?php foreach ($steps as $idx => $step): ?>
        <div class="col-md-6">
          <div class="border rounded p-3">
            <input type="hidden" name="step_id[]" value="<?= $step['id'] ?>">
            <div class="fw-semibold text-primary mb-2">
              <i class="fa-solid <?= htmlspecialchars($step['icon_class']) ?>"
                 id="stepIcon<?= $idx ?>"></i>
              Step <?= $step['step_number'] ?>
            </div>
            <div class="mb-2">
              <label class="form-label small">Icon (FA class)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">
                  <i class="fa-solid <?= htmlspecialchars($step['icon_class']) ?>"
                     id="stepIconEl<?= $idx ?>"></i>
                </span>
                <input type="text" name="step_icon[]" class="form-control step-icon-input"
                       data-preview="stepIconEl<?= $idx ?>"
                       value="<?= htmlspecialchars($step['icon_class']) ?>"
                       placeholder="fa-comment-dots">
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small">Title</label>
              <input type="text" name="step_title[]" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($step['title']) ?>" placeholder="Step title">
            </div>
            <div>
              <label class="form-label small">Description</label>
              <textarea name="step_desc[]" class="form-control form-control-sm" rows="2"
                        placeholder="Brief description..."><?= htmlspecialchars($step['description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg me-1"></i> Save Steps
  </button>
  <?php if (!$stepsTableExists): ?>
  <div class="form-text mt-2 text-warning">
    Import the latest schema before saving these step settings.
  </div>
  <?php endif; ?>
</form>

<!-- CTA BANNER -->
<?php elseif ($tab === 'cta'): ?>
<form method="POST">
  <input type="hidden" name="stab" value="cta">

  <div class="admin-card mb-3">
    <div class="card-header">Call-to-Action Banner</div>
    <div class="p-3">
      <div class="mb-3">
        <label class="form-label fw-semibold">Heading</label>
        <input type="text" name="svc_cta_heading" class="form-control"
               value="<?= $v('svc_cta_heading') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Supporting Text</label>
        <textarea name="svc_cta_text" class="form-control" rows="3"><?= $v('svc_cta_text') ?></textarea>
      </div>
    </div>
  </div>

  <!-- CTA Preview -->
  <div class="admin-card mb-3">
    <div class="card-header"><i class="bi bi-eye me-2"></i>Preview</div>
    <div class="p-3">
      <div style="background:linear-gradient(135deg,#03045E,#0077B6);border-radius:12px;
                  padding:2.5rem;text-align:center;color:#fff;">
        <h3 style="font-weight:800;margin-bottom:.75rem;"><?= $v('svc_cta_heading') ?></h3>
        <p style="opacity:.85;margin-bottom:1.5rem;"><?= $v('svc_cta_text') ?></p>
        <span style="background:#fff;color:#0077B6;font-weight:700;padding:.6rem 1.8rem;
                     border-radius:8px;display:inline-block;">Contact Us Today</span>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg me-1"></i> Save CTA
  </button>
</form>
<?php endif; ?>

<script>
// Live icon preview for badge icons
document.querySelectorAll('.badge-icon-input').forEach(input => {
  input.addEventListener('input', function () {
    const el = document.getElementById(this.dataset.preview);
    if (el) el.className = 'fa-solid ' + (this.value.trim() || 'fa-circle-question');
  });
});

// Live icon preview for step icons
document.querySelectorAll('.step-icon-input').forEach(input => {
  input.addEventListener('input', function () {
    const el = document.getElementById(this.dataset.preview);
    if (el) el.className = 'fa-solid ' + (this.value.trim() || 'fa-circle-question');
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
