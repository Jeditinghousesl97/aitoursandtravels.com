<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();

// Helper: convert any YouTube URL to embed URL
function toYouTubeEmbed(string $url): string {
    $url = trim($url);
    if (str_contains($url, 'youtube.com/embed/')) return $url;
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})#', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    return '';
}

$errors  = [];
$success = '';

// Delete
if (isset($_GET['delete'])) {
    $row = $pdo->prepare('SELECT video_file FROM gallery_videos WHERE id = ?');
    $row->execute([(int)$_GET['delete']]);
    $row = $row->fetch();
    if ($row && $row['video_file']) {
        $f = __DIR__ . '/../../' . $row['video_file'];
        if (file_exists($f)) unlink($f);
    }
    $pdo->prepare('DELETE FROM gallery_videos WHERE id = ?')->execute([(int)$_GET['delete']]);
    header('Location: videos.php?deleted=1'); exit;
}

// Toggle active
if (isset($_GET['toggle'])) {
    $pdo->prepare('UPDATE gallery_videos SET is_active = NOT is_active WHERE id = ?')
        ->execute([(int)$_GET['toggle']]);
    header('Location: videos.php'); exit;
}

// Save (add or edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $type     = $_POST['video_type'] === 'upload' ? 'upload' : 'youtube';

    $youtubeUrl = '';
    $embedUrl   = '';
    $videoFile  = $id ? ($pdo->prepare('SELECT video_file FROM gallery_videos WHERE id=?')->execute([$id]) ? null : null) : null;

    // Keep existing file path when editing
    if ($id) {
        $cur = $pdo->prepare('SELECT video_file FROM gallery_videos WHERE id=?');
        $cur->execute([$id]);
        $videoFile = $cur->fetchColumn();
    }

    if (!$title) $errors[] = 'Title is required.';

    if ($type === 'youtube') {
        $youtubeUrl = trim($_POST['youtube_url'] ?? '');
        if (!$youtubeUrl) {
            $errors[] = 'YouTube URL is required.';
        } else {
            $embedUrl = toYouTubeEmbed($youtubeUrl);
            if (!$embedUrl) $errors[] = 'Could not recognise that YouTube URL. Paste the full video URL or share link.';
        }
    } else {
        // File upload
        if (!empty($_FILES['video_file']['name'])) {
            $file    = $_FILES['video_file'];
            $allowed = ['video/mp4', 'video/webm', 'video/ogg'];
            if (!in_array($file['type'], $allowed)) {
                $errors[] = 'Only MP4, WEBM or OGG video files are allowed.';
            } elseif ($file['size'] > 200 * 1024 * 1024) {
                $errors[] = 'Video file must be under 200MB.';
            } else {
                $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
                $name = 'video_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = __DIR__ . '/../../uploads/gallery/' . $name;
                if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Delete old file if replacing
                    if ($videoFile) {
                        $old = __DIR__ . '/../../' . $videoFile;
                        if (file_exists($old)) unlink($old);
                    }
                    $videoFile = 'uploads/gallery/' . $name;
                } else {
                    $errors[] = 'File upload failed. Check server permissions.';
                }
            }
        } elseif (!$id) {
            $errors[] = 'Please upload a video file.';
        }
        // No new file uploaded on edit = keep existing
    }

    if (empty($errors)) {
        $maxOrder = $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM gallery_videos')->fetchColumn();
        if ($id) {
            $pdo->prepare('UPDATE gallery_videos SET title=?, description=?, video_type=?, youtube_url=?, embed_url=?, video_file=?, updated_at=NOW() WHERE id=?')
                ->execute([$title, $desc ?: null, $type, $youtubeUrl ?: null, $embedUrl ?: null, $videoFile ?: null, $id]);
            $success = 'Video updated.';
        } else {
            $pdo->prepare('INSERT INTO gallery_videos (title, description, video_type, youtube_url, embed_url, video_file, sort_order) VALUES (?,?,?,?,?,?,?)')
                ->execute([$title, $desc ?: null, $type, $youtubeUrl ?: null, $embedUrl ?: null, $videoFile ?: null, $maxOrder + 1]);
            $success = 'Video added.';
        }
    }
}

// Editing?
$editing = null;
if (isset($_GET['edit'])) {
    $editing = $pdo->prepare('SELECT * FROM gallery_videos WHERE id = ?');
    $editing->execute([(int)$_GET['edit']]);
    $editing = $editing->fetch();
}

$videos = $pdo->query('SELECT * FROM gallery_videos ORDER BY sort_order ASC, id DESC')->fetchAll();

$pageTitle = 'Gallery Videos';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-play-circle me-2 text-primary"></i>Gallery Videos</h1>
</div>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Video deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?>
      <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- Add / Edit Form -->
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-<?= $editing ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editing ? 'Edit Video' : 'Add New Video' ?>
      </div>
      <div class="p-4">
        <form method="POST" enctype="multipart/form-data">
          <?php if ($editing): ?>
          <input type="hidden" name="id" value="<?= $editing['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold">Video Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="<?= htmlspecialchars($editing['title'] ?? '') ?>"
                   placeholder="e.g. Sri Lanka, Pearl of the Indian Ocean" required>
          </div>

          <!-- Source toggle -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Video Source <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="video_type" id="typeYoutube" value="youtube"
                  <?= (!$editing || $editing['video_type'] === 'youtube') ? 'checked' : '' ?>
                  onchange="toggleVideoSource(this.value)">
                <label class="form-check-label" for="typeYoutube">
                  <i class="bi bi-youtube text-danger me-1"></i> YouTube URL
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="video_type" id="typeUpload" value="upload"
                  <?= ($editing && $editing['video_type'] === 'upload') ? 'checked' : '' ?>
                  onchange="toggleVideoSource(this.value)">
                <label class="form-check-label" for="typeUpload">
                  <i class="bi bi-upload me-1"></i> Upload File
                </label>
              </div>
            </div>
          </div>

          <!-- YouTube URL -->
          <div id="youtubeField" class="mb-3">
            <label class="form-label fw-semibold">YouTube URL</label>
            <input type="url" name="youtube_url" class="form-control"
                   value="<?= htmlspecialchars($editing['youtube_url'] ?? '') ?>"
                   placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/...">
            <div class="form-text">Paste any YouTube video URL converted to embed automatically.</div>
          </div>

          <!-- File Upload -->
          <div id="uploadField" class="mb-3" style="display:none;">
            <label class="form-label fw-semibold">Video File</label>
            <?php if ($editing && $editing['video_type'] === 'upload' && $editing['video_file']): ?>
            <div class="mb-2 p-2 bg-light rounded d-flex align-items-center gap-2">
              <i class="bi bi-film text-primary"></i>
              <span class="small text-muted">Current: <?= htmlspecialchars(basename($editing['video_file'])) ?></span>
              <span class="badge bg-info ms-auto">Uploaded</span>
            </div>
            <div class="form-text mb-2">Leave empty to keep the current file.</div>
            <?php endif; ?>
            <input type="file" name="video_file" class="form-control" accept="video/mp4,video/webm,video/ogg">
            <div class="form-text">MP4, WEBM or OGG · Max 200MB.</div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3"
                      placeholder="Short caption shown below the video..."><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-<?= $editing ? 'save' : 'plus-lg' ?> me-1"></i>
              <?= $editing ? 'Save Changes' : 'Add Video' ?>
            </button>
            <?php if ($editing): ?>
            <a href="videos.php" class="btn btn-outline-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Video List -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-collection-play me-2"></i>All Videos
        <span class="badge bg-primary ms-2"><?= count($videos) ?></span>
      </div>
      <?php if (empty($videos)): ?>
      <div class="p-5 text-center text-muted">
        <i class="bi bi-play-circle" style="font-size:2.5rem;opacity:.3;"></i>
        <p class="mt-3">No videos yet. Add your first video above.</p>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Preview</th>
              <th>Title</th>
              <th>Type</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($videos as $v):
                preg_match('#/embed/([a-zA-Z0-9_-]+)#', $v['embed_url'] ?? '', $tm);
                $thumb = isset($tm[1]) ? "https://img.youtube.com/vi/{$tm[1]}/mqdefault.jpg" : '';
            ?>
            <tr>
              <td>
                <?php if ($thumb): ?>
                  <img src="<?= $thumb ?>" alt="" style="width:90px;height:55px;object-fit:cover;border-radius:6px;">
                <?php elseif ($v['video_file']): ?>
                  <div style="width:90px;height:55px;background:#1e293b;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-film text-white" style="font-size:1.3rem;"></i>
                  </div>
                <?php else: ?>
                  <div style="width:90px;height:55px;background:#e2e8f0;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-play-circle text-muted"></i>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= htmlspecialchars($v['title']) ?></strong>
                <?php if ($v['description']): ?>
                <div class="text-muted small"><?= htmlspecialchars(mb_substr($v['description'], 0, 55)) ?>…</div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($v['video_type'] === 'youtube'): ?>
                <span class="badge bg-danger"><i class="bi bi-youtube me-1"></i>YouTube</span>
                <?php else: ?>
                <span class="badge bg-primary"><i class="bi bi-upload me-1"></i>Uploaded</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($v['is_active']): ?>
                <span class="badge bg-success">Active</span>
                <?php else: ?>
                <span class="badge bg-secondary">Hidden</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="tbl-actions">
                  <a href="videos.php?edit=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <a href="videos.php?toggle=<?= $v['id'] ?>" class="btn btn-sm <?= $v['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                    <?= $v['is_active'] ? 'Hide' : 'Show' ?>
                  </a>
                  <a href="videos.php?delete=<?= $v['id'] ?>"
                     onclick="return confirm('Delete this video?')"
                     class="btn btn-sm btn-outline-danger">Delete</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function toggleVideoSource(val) {
    document.getElementById('youtubeField').style.display = val === 'youtube' ? '' : 'none';
    document.getElementById('uploadField').style.display  = val === 'upload'  ? '' : 'none';
}
// Init on page load
toggleVideoSource(document.querySelector('input[name="video_type"]:checked')?.value || 'youtube');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
