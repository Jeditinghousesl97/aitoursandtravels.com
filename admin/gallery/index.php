<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$tab = ($_GET['tab'] ?? 'images') === 'videos' ? 'videos' : 'images';

// Helper: YouTube URL → embed URL 
function toYouTubeEmbed(string $url): string {
    $url = trim($url);
    if (str_contains($url, 'youtube.com/embed/')) return $url;
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})#', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    return '';
}

// IMAGES — actions
if ($tab === 'images') {

    if (isset($_GET['delete'])) {
        $row = $pdo->prepare('SELECT image_path FROM gallery WHERE id = ?');
        $row->execute([(int)$_GET['delete']]);
        $row = $row->fetch();
        if ($row) {
            $f = __DIR__ . '/../../' . $row['image_path'];
            if (file_exists($f)) unlink($f);
            $pdo->prepare('DELETE FROM gallery WHERE id = ?')->execute([(int)$_GET['delete']]);
        }
        header('Location: index.php?deleted=1'); exit;
    }

    if (isset($_GET['toggle'])) {
        $pdo->prepare('UPDATE gallery SET is_active = NOT is_active WHERE id = ?')
            ->execute([(int)$_GET['toggle']]);
        header('Location: index.php'); exit;
    }

}

// VIDEOS — actions
$videoErrors  = [];
$videoSuccess = '';
$editingVideo = null;

if ($tab === 'videos') {

    if (isset($_GET['delete'])) {
        $row = $pdo->prepare('SELECT video_file FROM gallery_videos WHERE id = ?');
        $row->execute([(int)$_GET['delete']]);
        $row = $row->fetch();
        if ($row && $row['video_file']) {
            $f = __DIR__ . '/../../' . $row['video_file'];
            if (file_exists($f)) unlink($f);
        }
        $pdo->prepare('DELETE FROM gallery_videos WHERE id = ?')->execute([(int)$_GET['delete']]);
        header('Location: index.php?tab=videos&deleted=1'); exit;
    }

    if (isset($_GET['toggle'])) {
        $pdo->prepare('UPDATE gallery_videos SET is_active = NOT is_active WHERE id = ?')
            ->execute([(int)$_GET['toggle']]);
        header('Location: index.php?tab=videos'); exit;
    }

    if (isset($_GET['edit'])) {
        $s = $pdo->prepare('SELECT * FROM gallery_videos WHERE id = ?');
        $s->execute([(int)$_GET['edit']]);
        $editingVideo = $s->fetch();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_video'])) {
        $vid   = (int)($_POST['vid'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $vtype = ($_POST['video_type'] ?? 'youtube') === 'upload' ? 'upload' : 'youtube';

        $youtubeUrl = $embedUrl = $videoFile = null;

        if ($vid) {
            $cur = $pdo->prepare('SELECT video_file FROM gallery_videos WHERE id = ?');
            $cur->execute([$vid]);
            $videoFile = $cur->fetchColumn() ?: null;
        }

        if (!$title) $videoErrors[] = 'Title is required.';

        if ($vtype === 'youtube') {
            $youtubeUrl = trim($_POST['youtube_url'] ?? '');
            if (!$youtubeUrl) {
                $videoErrors[] = 'YouTube URL is required.';
            } else {
                $embedUrl = toYouTubeEmbed($youtubeUrl);
                if (!$embedUrl) $videoErrors[] = 'Could not recognise that YouTube URL.';
            }
        } else {
            if (!empty($_FILES['video_file']['name'])) {
                $file    = $_FILES['video_file'];
                $allowed = ['video/mp4','video/webm','video/ogg'];
                if (!in_array($file['type'], $allowed)) {
                    $videoErrors[] = 'Only MP4, WEBM or OGG files are allowed.';
                } elseif ($file['size'] > 200 * 1024 * 1024) {
                    $videoErrors[] = 'Video must be under 200MB.';
                } else {
                    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $name = 'video_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = __DIR__ . '/../../uploads/gallery/' . $name;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        if ($videoFile && file_exists(__DIR__ . '/../../' . $videoFile))
                            unlink(__DIR__ . '/../../' . $videoFile);
                        $videoFile = 'uploads/gallery/' . $name;
                    } else {
                        $videoErrors[] = 'Upload failed. Check folder permissions.';
                    }
                }
            } elseif (!$vid) {
                $videoErrors[] = 'Please upload a video file.';
            }
        }

        if (empty($videoErrors)) {
            $max = $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM gallery_videos')->fetchColumn();
            if ($vid) {
                $pdo->prepare('UPDATE gallery_videos SET title=?,description=?,video_type=?,youtube_url=?,embed_url=?,video_file=?,updated_at=NOW() WHERE id=?')
                    ->execute([$title, $desc ?: null, $vtype, $youtubeUrl ?? '', $embedUrl ?? '', $videoFile, $vid]);
                $videoSuccess = 'Video updated.';
            } else {
                $pdo->prepare('INSERT INTO gallery_videos (title,description,video_type,youtube_url,embed_url,video_file,sort_order) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$title, $desc ?: null, $vtype, $youtubeUrl ?? '', $embedUrl ?? '', $videoFile, $max + 1]);
                $videoSuccess = 'Video added.';
            }
            $editingVideo = null;
        }
    }
}

// Fetch data 
$uploadErrors  = [];
$uploadSuccess = 0;

if ($tab === 'images' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['images']['name'][0])) {
    $category  = trim($_POST['category'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $allowed   = ['image/jpeg','image/png','image/webp'];
    $files     = $_FILES['images'];
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if (!in_array($files['type'][$i], $allowed)) { $uploadErrors[] = $files['name'][$i] . ': must be JPG, PNG or WEBP.'; continue; }
        if ($files['size'][$i] > 25 * 1024 * 1024)  { $uploadErrors[] = $files['name'][$i] . ': exceeds 25MB.'; continue; }

        $ext  = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $name = 'gallery_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = __DIR__ . '/../../uploads/gallery/' . $name;
        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $maxOrder = $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM gallery')->fetchColumn();
            $pdo->prepare('INSERT INTO gallery (title,category,image_path,sort_order) VALUES (?,?,?,?)')
                ->execute([$title ?: null, $category ?: null, 'uploads/gallery/' . $name, $maxOrder + 1]);
            $uploadSuccess++;
        }
    }
    if ($uploadSuccess > 0) {
        header('Location: index.php?uploaded=' . $uploadSuccess);
        exit;
    }
}

$filterCat = $_GET['cat'] ?? '';
$where  = $filterCat ? 'WHERE category = ?' : '';
$params = $filterCat ? [$filterCat] : [];
$stmt   = $pdo->prepare("SELECT * FROM gallery $where ORDER BY sort_order ASC, created_at DESC");
$stmt->execute($params);
$images = $stmt->fetchAll();

$cats = $pdo->query("SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category != '' ORDER BY category")
            ->fetchAll(PDO::FETCH_COLUMN);

$videos = $pdo->query('SELECT * FROM gallery_videos ORDER BY sort_order ASC, id DESC')->fetchAll();

$pageTitle = 'Gallery';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-images me-2 text-primary"></i>Gallery</h1>
  <?php if ($tab === 'images'): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
    <i class="bi bi-cloud-upload me-1"></i> Upload Images
  </button>
  <?php endif; ?>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'images' ? 'active' : '' ?>" href="index.php">
      <i class="bi bi-images me-1"></i> Images
      <span class="badge bg-secondary ms-1"><?= count($images) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'videos' ? 'active' : '' ?>" href="index.php?tab=videos">
      <i class="bi bi-play-circle me-1"></i> Videos
      <span class="badge bg-secondary ms-1"><?= count($videos) ?></span>
    </a>
  </li>
</ul>

<?php if ($uploadSuccess > 0): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> <?= $uploadSuccess ?> image<?= $uploadSuccess > 1 ? 's' : '' ?> uploaded.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($uploadErrors): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <ul class="mb-0 ps-3"><?php foreach ($uploadErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (isset($_GET['uploaded'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> <?= (int)$_GET['uploaded'] ?> image<?= (int)$_GET['uploaded'] !== 1 ? 's' : '' ?> uploaded successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> <?= $tab === 'videos' ? 'Video' : 'Image' ?> deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($videoSuccess): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($videoSuccess) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($videoErrors): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <ul class="mb-0 ps-3"><?php foreach ($videoErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if ($tab === 'images'): ?>
<!-- IMAGES TAB  -->

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div class="text-muted small">
    <i class="bi bi-images me-1"></i>
    <strong><?= count($images) ?></strong> image<?= count($images) !== 1 ? 's' : '' ?>
    <?= $filterCat ? 'in <strong>' . htmlspecialchars($filterCat) . '</strong>' : 'total' ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="index.php" class="btn btn-sm <?= !$filterCat ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
    <?php foreach ($cats as $cat): ?>
    <a href="?cat=<?= urlencode($cat) ?>" class="btn btn-sm <?= $filterCat === $cat ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <?= htmlspecialchars(ucfirst($cat)) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($images): ?>
<div class="gallery-grid" id="galleryGrid">
  <?php foreach ($images as $img): ?>
  <div class="gallery-item <?= !$img['is_active'] ? 'gallery-inactive' : '' ?>" data-id="<?= $img['id'] ?>">
    <div class="gallery-thumb">
      <img src="<?= htmlspecialchars(site_url($img['image_path'])) ?>" alt="<?= htmlspecialchars($img['title'] ?? '') ?>" loading="lazy">
      <div class="gallery-overlay">
        <a href="<?= htmlspecialchars(site_url($img['image_path'])) ?>" target="_blank" class="gallery-btn" title="View full"><i class="bi bi-fullscreen"></i></a>
        <a href="?toggle=<?= $img['id'] ?>" class="gallery-btn" title="Toggle visible"><i class="bi bi-<?= $img['is_active'] ? 'eye' : 'eye-slash' ?>"></i></a>
        <a href="?delete=<?= $img['id'] ?>" class="gallery-btn gallery-btn-danger" title="Delete" onclick="return confirm('Delete this image?')"><i class="bi bi-trash"></i></a>
      </div>
      <?php if (!$img['is_active']): ?><div class="gallery-hidden-badge">Hidden</div><?php endif; ?>
    </div>
    <div class="gallery-info">
      <?php if ($img['title']): ?><div class="gallery-title"><?= htmlspecialchars($img['title']) ?></div><?php endif; ?>
      <?php if ($img['category']): ?><span class="gallery-cat"><?= htmlspecialchars(ucfirst($img['category'])) ?></span><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="admin-card">
  <div class="text-center text-muted py-5">
    <i class="bi bi-images fs-1 d-block mb-3 opacity-25"></i>
    <p class="mb-2">No images yet.</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
      <i class="bi bi-cloud-upload me-1"></i> Upload your first image
    </button>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- VIDEOS TAB -->

<div class="row g-4">
  <!-- Form -->
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-<?= $editingVideo ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editingVideo ? 'Edit Video' : 'Add New Video' ?>
      </div>
      <div class="p-4">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="save_video" value="1">
          <?php if ($editingVideo): ?>
          <input type="hidden" name="vid" value="<?= $editingVideo['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold">Video Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="<?= htmlspecialchars($editingVideo['title'] ?? '') ?>"
                   placeholder="e.g. Sri Lanka, Pearl of the Indian Ocean" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Video Source <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="video_type" id="typeYoutube" value="youtube"
                  <?= (!$editingVideo || $editingVideo['video_type'] === 'youtube') ? 'checked' : '' ?>
                  onchange="toggleVideoSource(this.value)">
                <label class="form-check-label" for="typeYoutube">
                  <i class="bi bi-youtube text-danger me-1"></i> YouTube URL
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="video_type" id="typeUpload" value="upload"
                  <?= ($editingVideo && $editingVideo['video_type'] === 'upload') ? 'checked' : '' ?>
                  onchange="toggleVideoSource(this.value)">
                <label class="form-check-label" for="typeUpload">
                  <i class="bi bi-upload me-1"></i> Upload File
                </label>
              </div>
            </div>
          </div>

          <div id="youtubeField" class="mb-3">
            <label class="form-label fw-semibold">YouTube URL</label>
            <input type="url" name="youtube_url" class="form-control"
                   value="<?= htmlspecialchars($editingVideo['youtube_url'] ?? '') ?>"
                   placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/...">
            <div class="form-text">Any YouTube URL auto-converted to embed.</div>
          </div>

          <div id="uploadField" class="mb-3" style="display:none;">
            <label class="form-label fw-semibold">Video File</label>
            <?php if ($editingVideo && $editingVideo['video_type'] === 'upload' && $editingVideo['video_file']): ?>
            <div class="mb-2 p-2 bg-light rounded d-flex align-items-center gap-2 small text-muted">
              <i class="bi bi-film text-primary"></i>
              Current: <?= htmlspecialchars(basename($editingVideo['video_file'])) ?>
              <span class="badge bg-info ms-auto">Uploaded</span>
            </div>
            <div class="form-text mb-2">Leave empty to keep current file.</div>
            <?php endif; ?>
            <input type="file" name="video_file" class="form-control" accept="video/mp4,video/webm,video/ogg">
            <div class="form-text">MP4, WEBM or OGG · Max 200MB.</div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3"
                      placeholder="Short caption shown below the video..."><?= htmlspecialchars($editingVideo['description'] ?? '') ?></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-<?= $editingVideo ? 'save' : 'plus-lg' ?> me-1"></i>
              <?= $editingVideo ? 'Save Changes' : 'Add Video' ?>
            </button>
            <?php if ($editingVideo): ?>
            <a href="index.php?tab=videos" class="btn btn-outline-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- List -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-collection-play me-2"></i>All Videos
        <span class="badge bg-primary ms-2"><?= count($videos) ?></span>
      </div>
      <?php if (empty($videos)): ?>
      <div class="p-5 text-center text-muted">
        <i class="bi bi-play-circle" style="font-size:2.5rem;opacity:.3;"></i>
        <p class="mt-3">No videos yet. Add your first video on the left.</p>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Preview</th><th>Title</th><th>Type</th><th>Status</th><th>Actions</th></tr>
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
              <td><?= $v['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>' ?></td>
              <td>
                <div class="tbl-actions">
                  <a href="?tab=videos&edit=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <a href="?tab=videos&toggle=<?= $v['id'] ?>" class="btn btn-sm <?= $v['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                    <?= $v['is_active'] ? 'Hide' : 'Show' ?>
                  </a>
                  <a href="?tab=videos&delete=<?= $v['id'] ?>" onclick="return confirm('Delete this video?')" class="btn btn-sm btn-outline-danger">Delete</a>
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
toggleVideoSource(document.querySelector('input[name="video_type"]:checked')?.value || 'youtube');
</script>

<?php endif; ?>

<!-- UPLOAD MODAL (images)  -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Upload Images</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="index.php" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="drop-zone" id="dropZone">
            <i class="bi bi-cloud-upload fs-2 text-primary mb-2"></i>
            <p class="mb-1 fw-semibold">Drag & drop images here</p>
            <p class="text-muted small mb-3">or click to browse</p>
            <input type="file" name="images[]" id="fileInput" multiple accept="image/jpeg,image/png,image/webp" style="display:none">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('fileInput').click()">Browse Files</button>
          </div>
          <div id="previewStrip" class="preview-strip d-none mt-3"></div>
          <div class="row g-2 mt-2">
            <div class="col-sm-6">
              <label class="form-label">Category <small class="text-muted">(optional)</small></label>
              <input type="text" name="category" class="form-control form-control-sm" placeholder="e.g. beaches, wildlife" list="catSuggestions">
              <datalist id="catSuggestions">
                <?php foreach ($cats as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                <option value="beaches"><option value="wildlife"><option value="cultural"><option value="hills"><option value="adventure">
              </datalist>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Title <small class="text-muted">(optional)</small></label>
              <input type="text" name="title" class="form-control form-control-sm" placeholder="Image title">
            </div>
          </div>
          <div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>JPG, PNG or WEBP · Max 25MB · Multiple allowed.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
            <i class="bi bi-cloud-upload me-1"></i> Upload <span id="fileCount"></span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
.gallery-item { position: relative; }
.gallery-thumb { position: relative; border-radius: 10px; overflow: hidden; aspect-ratio: 4/3; background: #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,.08); cursor: pointer; }
.gallery-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .35s ease; }
.gallery-thumb:hover img { transform: scale(1.06); }
.gallery-overlay { position: absolute; inset: 0; background: rgba(3,4,94,.55); display: flex; align-items: center; justify-content: center; gap: .5rem; opacity: 0; transition: opacity .25s ease; }
.gallery-thumb:hover .gallery-overlay { opacity: 1; }
.gallery-btn { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.2); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,.3); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .9rem; text-decoration: none; transition: background .2s; }
.gallery-btn:hover { background: rgba(255,255,255,.35); color: #fff; }
.gallery-btn-danger:hover { background: rgba(220,38,38,.7) !important; }
.gallery-hidden-badge { position: absolute; top: 8px; left: 8px; background: rgba(0,0,0,.6); color: #fff; font-size: .7rem; padding: .15rem .5rem; border-radius: 999px; }
.gallery-inactive .gallery-thumb { opacity: .5; }
.gallery-info { padding: .4rem .2rem 0; display: flex; align-items: center; justify-content: space-between; gap: .3rem; }
.gallery-title { font-size: .8rem; color: #4a5568; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.gallery-cat { font-size: .7rem; background: #e0f4fc; color: #0077B6; padding: .1rem .45rem; border-radius: 999px; white-space: nowrap; }
.drop-zone { border: 2px dashed #cbd5e0; border-radius: 12px; padding: 2rem; text-align: center; background: #f7fafc; transition: border-color .2s, background .2s; cursor: pointer; }
.drop-zone.dragover { border-color: #0077B6; background: #e0f4fc; }
.preview-strip { display: flex; gap: .5rem; flex-wrap: wrap; }
.preview-strip img { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; }
</style>

<script>
const fileInput    = document.getElementById('fileInput');
const dropZone     = document.getElementById('dropZone');
const uploadBtn    = document.getElementById('uploadBtn');
const fileCountEl  = document.getElementById('fileCount');
const previewStrip = document.getElementById('previewStrip');

if (fileInput) {
    function handleFiles(files) {
        if (!files.length) return;
        uploadBtn.disabled = false;
        fileCountEl.textContent = '(' + files.length + ' file' + (files.length > 1 ? 's' : '') + ')';
        previewStrip.innerHTML = '';
        previewStrip.classList.remove('d-none');
        Array.from(files).forEach(file => {
            const reader = new FileReader();
            reader.onload = e => { const img = document.createElement('img'); img.src = e.target.result; previewStrip.appendChild(img); };
            reader.readAsDataURL(file);
        });
    }
    fileInput.addEventListener('change', () => handleFiles(fileInput.files));
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); fileInput.files = e.dataTransfer.files; handleFiles(e.dataTransfer.files); });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
