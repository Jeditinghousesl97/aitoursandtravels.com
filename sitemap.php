<?php
require_once __DIR__ . '/admin/config/db.php';

header('Content-Type: application/xml; charset=UTF-8');

$pdo = getPDO();
$baseUrl = rtrim(site_origin(), '/');
$now = gmdate('c');

$staticPages = [
    ['loc' => absolute_site_url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => absolute_site_url('pages/services.php'), 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => absolute_site_url('pages/packages.php'), 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => absolute_site_url('pages/blog.php'), 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => absolute_site_url('pages/gallery.php'), 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => absolute_site_url('pages/privacy-policy.php'), 'changefreq' => 'yearly', 'priority' => '0.3'],
    ['loc' => absolute_site_url('pages/terms-of-service.php'), 'changefreq' => 'yearly', 'priority' => '0.3'],
    ['loc' => absolute_site_url('pages/cookie-policy.php'), 'changefreq' => 'yearly', 'priority' => '0.3'],
];

$urls = $staticPages;

try {
    $packageLastmodColumn = columnExists($pdo, 'packages', 'updated_at') ? 'updated_at' : 'NULL AS updated_at';
    $packages = $pdo->query("SELECT slug, {$packageLastmodColumn} FROM packages WHERE is_active = 1 AND slug <> '' ORDER BY id DESC")->fetchAll();
    foreach ($packages as $package) {
        $urls[] = [
            'loc' => absolute_site_url('pages/package-detail.php?slug=' . rawurlencode((string)$package['slug'])),
            'lastmod' => !empty($package['updated_at']) ? gmdate('c', strtotime((string)$package['updated_at'])) : $now,
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];
    }
} catch (Throwable $e) {
}

try {
    $postLastmodColumn = columnExists($pdo, 'blog_posts', 'updated_at') ? 'updated_at' : 'NULL AS updated_at';
    $posts = $pdo->query("SELECT slug, published_at, {$postLastmodColumn} FROM blog_posts WHERE is_published = 1 AND slug <> '' ORDER BY published_at DESC")->fetchAll();
    foreach ($posts as $post) {
        $lastmodSource = $post['updated_at'] ?? $post['published_at'] ?? null;
        $urls[] = [
            'loc' => absolute_site_url('pages/blog-detail.php?slug=' . rawurlencode((string)$post['slug'])),
            'lastmod' => $lastmodSource ? gmdate('c', strtotime((string)$lastmodSource)) : $now,
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ];
    }
} catch (Throwable $e) {
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= htmlspecialchars($url['loc'], ENT_XML1) ?></loc>
<?php if (!empty($url['lastmod'])): ?>
    <lastmod><?= htmlspecialchars($url['lastmod'], ENT_XML1) ?></lastmod>
<?php endif; ?>
<?php if (!empty($url['changefreq'])): ?>
    <changefreq><?= htmlspecialchars($url['changefreq'], ENT_XML1) ?></changefreq>
<?php endif; ?>
<?php if (!empty($url['priority'])): ?>
    <priority><?= htmlspecialchars($url['priority'], ENT_XML1) ?></priority>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>
