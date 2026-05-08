<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$hasGalleryImagesColumn = columnExists($pdo, 'blog_posts', 'gallery_images');

// Delete
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $selectFields = $hasGalleryImagesColumn ? 'cover_image, gallery_images' : 'cover_image';
    $row = $pdo->prepare("SELECT {$selectFields} FROM blog_posts WHERE id = ?");
    $row->execute([$id]);
    $row = $row->fetch();
    if ($row) {
        if ($row['cover_image']) {
            $f = __DIR__ . '/../../' . $row['cover_image'];
            if (file_exists($f)) unlink($f);
        }
        if ($hasGalleryImagesColumn && !empty($row['gallery_images'])) {
            $gallery = json_decode((string)$row['gallery_images'], true);
            if (is_array($gallery)) {
                foreach ($gallery as $img) {
                    $f = __DIR__ . '/../../' . ltrim((string)$img, '/');
                    if (file_exists($f)) unlink($f);
                }
            }
        }
        $pdo->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
    }
    header('Location: index.php?deleted=1'); exit;
}

// Toggle publish
if (isset($_GET['toggle'])) {
    $id  = (int)$_GET['toggle'];
    $row = $pdo->prepare('SELECT is_published FROM blog_posts WHERE id = ?');
    $row->execute([$id]);
    $row = $row->fetch();
    if ($row) {
        $publish = !$row['is_published'];
        $pubAt   = $publish ? date('Y-m-d H:i:s') : null;
        $pdo->prepare('UPDATE blog_posts SET is_published = ?, published_at = ? WHERE id = ?')
            ->execute([$publish, $pubAt, $id]);
    }
    header('Location: index.php'); exit;
}

// Search & filter
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$hasAuthorIdColumn = columnExists($pdo, 'blog_posts', 'author_id');
$hasAuthorColumn   = columnExists($pdo, 'blog_posts', 'author');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(bp.title LIKE ? OR bp.category LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status === 'published') {
    $where[] = 'bp.is_published = 1';
} elseif ($status === 'draft') {
    $where[] = 'bp.is_published = 0';
}

$authorSelect = $hasAuthorColumn
    ? 'COALESCE(NULLIF(bp.author, \'\'), a.name, \'Admin\') AS author_name'
    : 'COALESCE(a.name, \'Admin\') AS author_name';

$sql = "SELECT bp.*, {$authorSelect} FROM blog_posts bp";
if ($hasAuthorIdColumn) {
    $sql .= ' LEFT JOIN admin_users a ON bp.author_id = a.id';
} else {
    $sql .= ' LEFT JOIN admin_users a ON 1 = 0';
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY bp.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$totalPublished = $pdo->query('SELECT COUNT(*) FROM blog_posts WHERE is_published = 1')->fetchColumn();
$totalDraft     = $pdo->query('SELECT COUNT(*) FROM blog_posts WHERE is_published = 0')->fetchColumn();

$pageTitle = 'Blog Posts';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-file-earmark-text me-2 text-primary"></i>Blog Posts</h1>
  <a href="create.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i> New Post
  </a>
</div>

<?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Post created successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php elseif (isset($_GET['updated'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Post updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php elseif (isset($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i> Post deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <a href="index.php" class="text-decoration-none">
      <div class="stat-card">
        <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
        <div>
          <div class="stat-value"><?= $totalPublished + $totalDraft ?></div>
          <div class="stat-label">Total Posts</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="?status=published" class="text-decoration-none">
      <div class="stat-card" style="border-color:#276749;">
        <div class="stat-icon" style="background:#f0fff4;color:#276749;">
          <i class="bi bi-check-circle"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalPublished ?></div>
          <div class="stat-label">Published</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="?status=draft" class="text-decoration-none">
      <div class="stat-card" style="border-color:#718096;">
        <div class="stat-icon" style="background:#f7fafc;color:#718096;">
          <i class="bi bi-pencil-square"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalDraft ?></div>
          <div class="stat-label">Drafts</div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- Filters -->
<div class="admin-card mb-3">
  <div class="card-header">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Search title or category..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Posts</option>
          <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
          <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Drafts</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Posts Table -->
<div class="admin-card">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:70px;">Image</th>
          <th>Title</th>
          <th>Category</th>
          <th>Author</th>
          <th>Status</th>
          <th>Date</th>
          <th style="width:110px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($posts): ?>
          <?php foreach ($posts as $post): ?>
            <tr>
              <td>
                <?php if ($post['cover_image']): ?>
                  <img src="<?= htmlspecialchars(site_url($post['cover_image'])) ?>"
                       style="width:60px;height:44px;object-fit:cover;border-radius:6px;">
                <?php else: ?>
                  <div style="width:60px;height:44px;background:#e2e8f0;border-radius:6px;
                              display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-image text-muted"></i>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($post['title']) ?></div>
                <?php if ($post['excerpt']): ?>
                  <div class="text-muted small text-truncate" style="max-width:280px;">
                    <?= htmlspecialchars($post['excerpt']) ?>
                  </div>
                <?php endif; ?>
                <?php if ($post['tags']): ?>
                  <div class="mt-1">
                    <?php foreach (explode(',', $post['tags']) as $tag): ?>
                      <span class="badge bg-light text-muted border me-1"
                            style="font-size:.7rem;">#<?= htmlspecialchars(trim($tag)) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($post['category']): ?>
                  <span class="badge rounded-pill" style="background:#e0f4fc;color:#0077B6;">
                    <?= htmlspecialchars($post['category']) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-muted small"><?= htmlspecialchars($post['author_name']) ?></td>
              <td>
                <?php if ($post['is_published']): ?>
                  <span class="badge rounded-pill" style="background:#f0fff4;color:#276749;">
                    <i class="bi bi-check-circle me-1"></i>Published
                  </span>
                  <?php if ($post['published_at']): ?>
                    <div class="text-muted" style="font-size:.7rem;">
                      <?= date('d M Y', strtotime($post['published_at'])) ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge rounded-pill bg-light text-muted border">
                    <i class="bi bi-pencil me-1"></i>Draft
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-muted small">
                <?= date('d M Y', strtotime($post['created_at'])) ?>
              </td>
              <td>
                <div class="tbl-actions">
                  <a href="?toggle=<?= $post['id'] ?>"
                     class="btn btn-sm <?= $post['is_published'] ? 'btn-warning' : 'btn-success' ?>"
                     title="<?= $post['is_published'] ? 'Unpublish' : 'Publish' ?>">
                    <i class="bi bi-<?= $post['is_published'] ? 'eye-slash' : 'send' ?>"></i>
                  </a>
                  <?php if ($post['is_published'] && $post['slug']): ?>
                  <a href="<?= htmlspecialchars(site_url('pages/blog-detail.php?slug=' . urlencode($post['slug']))) ?>"
                     class="btn btn-sm btn-outline-secondary" title="View on site" target="_blank">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php endif; ?>
                  <a href="edit.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"
                     onclick="return confirm('Delete this post permanently?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-4">
              <i class="bi bi-file-earmark-text fs-3 d-block mb-2 opacity-50"></i>
              No posts found. <a href="create.php">Write your first post</a>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
