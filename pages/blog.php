<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/seo.php';
$pdo = getPDO();
if (file_exists(__DIR__ . '/../maintenance.flag')) { include __DIR__ . '/../maintenance.php'; exit; }

$s   = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $key, string $default = '') => $s[$key] ?? $default;
$whatsapp = $cfg('contact_whatsapp');

$catConfig = [
    'tips'         => ['icon' => 'fa-lightbulb',    'label' => 'Travel Tips',    'css' => 'cat-tips'],
    'destinations' => ['icon' => 'fa-location-dot', 'label' => 'Destinations',   'css' => 'cat-dest'],
    'food'         => ['icon' => 'fa-utensils',     'label' => 'Food & Culture', 'css' => 'cat-food'],
    'wildlife'     => ['icon' => 'fa-paw',          'label' => 'Wildlife',       'css' => 'cat-wildlife'],
    'visa'         => ['icon' => 'fa-passport',     'label' => 'Visa & Planning','css' => 'cat-visa'],
    'honeymoon'    => ['icon' => 'fa-heart',        'label' => 'Honeymoon',      'css' => 'cat-honeymoon'],
];

$activeCat = trim($_GET['cat'] ?? 'all');
if ($activeCat !== 'all' && !isset($catConfig[$activeCat])) $activeCat = 'all';

$allPosts = $pdo->query("SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC")->fetchAll();
$useStatic = empty($allPosts);

function readTime(string $html): int {
    $words = str_word_count(strip_tags($html));
    return max(1, (int)round($words / 200));
}

// Categories present in DB
$dbCats = [];
foreach ($allPosts as $p) {
    if ($p['category'] && isset($catConfig[$p['category']])) {
        $dbCats[$p['category']] = ($dbCats[$p['category']] ?? 0) + 1;
    }
}

$totalArticles = count($allPosts);

// Pagination & filter
$perPage = 9;
$page    = max(1, (int)($_GET['page'] ?? 1));

$featuredPost = $allPosts[0] ?? null;

if ($activeCat === 'all') {
    $gridPosts = array_slice($allPosts, 1);
} else {
    $gridPosts = array_values(array_filter($allPosts, fn($p) => $p['category'] === $activeCat));
}
$totalGrid  = count($gridPosts);
$totalPages = max(1, (int)ceil($totalGrid / $perPage));
$page       = min($page, $totalPages);
$pagePosts  = array_slice($gridPosts, ($page - 1) * $perPage, $perPage);

function pageUrl(int $p, string $cat): string {
    $qs = http_build_query(array_filter(['cat' => $cat === 'all' ? '' : $cat, 'page' => $p > 1 ? $p : null]));
    return 'blog.php' . ($qs ? '?' . $qs : '');
}

// Static fallback data 
$staticFeatured = [
    'title'        => 'The Ultimate Sri Lanka Travel Guide 2026, Everything You Need to Know',
    'slug'         => null,
    'excerpt'      => 'Planning a trip to Sri Lanka? This comprehensive guide covers everything, best time to visit, top destinations, budgeting, visa requirements, local customs, transportation, food, and must-have packing tips.',
    'cover_image'  => '../assets/images/packages/cultural-triangle.jpg',
    'category'     => 'tips',
    'published_at' => '2026-03-15',
    'content'      => str_repeat('word ', 2400), // ~12 min
    'author'       => 'Dinesh Kumara',
    'author_role'  => 'Head of Travel, ASB Tours',
    'author_init'  => 'DK',
    'views'        => '8,420',
];
$staticPosts = [
    ['title'=>'Top 10 Things to Do at Sigiriya, Sri Lanka\'s Iconic Rock Fortress','slug'=>null,'excerpt'=>'From climbing the ancient citadel at sunrise to exploring the water gardens and frescoes, here\'s your complete guide.','cover_image'=>'../assets/images/destinations/sigiriya.jpg','category'=>'destinations','published_at'=>'2026-02-28','content'=>str_repeat('word ',1600),'views'=>'5,210'],
    ['title'=>'Best Beaches in Sri Lanka: A Complete Coastal Guide','slug'=>null,'excerpt'=>'Mirissa, Unawatuna, Tangalle, Arugam Bay, Sri Lanka\'s coastline is 1,340 km of golden sand.','cover_image'=>'../assets/images/packages/beach-escape.jpg','category'=>'destinations','published_at'=>'2026-02-14','content'=>str_repeat('word ',2000),'views'=>'7,840'],
    ['title'=>'Kandy to Ella Train: The World\'s Most Scenic Rail Journey','slug'=>null,'excerpt'=>'How to book tickets, which side to sit, best stops, everything before boarding Sri Lanka\'s famous blue train.','cover_image'=>'../assets/images/packages/hill-country.jpg','category'=>'tips','published_at'=>'2026-01-30','content'=>str_repeat('word ',1400),'views'=>'9,130'],
    ['title'=>'Yala National Park Safari Guide: How to Spot a Sri Lankan Leopard','slug'=>null,'excerpt'=>'Yala has one of the highest leopard densities on earth. Here\'s when to go and which zones to visit.','cover_image'=>'../assets/images/packages/yala-safari.jpg','category'=>'wildlife','published_at'=>'2026-01-18','content'=>str_repeat('word ',1800),'views'=>'6,450'],
    ['title'=>'Sri Lanka Visa Guide 2026: ETA, Costs & Step-by-Step Application','slug'=>null,'excerpt'=>'Most visitors can get a Sri Lanka ETA online in minutes. Full breakdown, who needs a visa, how to apply, costs.','cover_image'=>'../assets/images/gallery/gallery-3.jpg','category'=>'visa','published_at'=>'2026-01-05','content'=>str_repeat('word ',1200),'views'=>'11,200'],
    ['title'=>'15 Sri Lankan Dishes You Absolutely Must Try on Your Visit','slug'=>null,'excerpt'=>'Rice & curry, hoppers, kottu, pol sambol, Sri Lankan cuisine is bold, aromatic, and endlessly satisfying.','cover_image'=>'../assets/images/gallery/gallery-4.jpg','category'=>'food','published_at'=>'2025-12-20','content'=>str_repeat('word ',1600),'views'=>'4,780'],
    ['title'=>'Sri Lanka Honeymoon Guide: The Most Romantic Spots for Couples','slug'=>null,'excerpt'=>'Private cliff-top villas in Ella, sunset whale watching in Mirissa, candlelit dinners in Galle Fort.','cover_image'=>'../assets/images/packages/honeymoon.jpg','category'=>'honeymoon','published_at'=>'2025-12-08','content'=>str_repeat('word ',2200),'views'=>'5,960'],
    ['title'=>'Best Time to Visit Sri Lanka: A Month-by-Month Weather Guide','slug'=>null,'excerpt'=>'Sri Lanka has two monsoon seasons affecting different coasts. Here\'s exactly when to visit each region.','cover_image'=>'../assets/images/destinations/kandy.jpg','category'=>'tips','published_at'=>'2025-11-25','content'=>str_repeat('word ',1400),'views'=>'8,300'],
    ['title'=>'Ella, Sri Lanka: Complete Guide to the Island\'s Most Charming Hill Town','slug'=>null,'excerpt'=>'Nine Arches Bridge, Ella Rock hike, Little Adam\'s Peak, tea factory tours, and the freshest air in Sri Lanka.','cover_image'=>'../assets/images/destinations/ella.jpg','category'=>'destinations','published_at'=>'2025-11-10','content'=>str_repeat('word ',1800),'views'=>'6,100'],
];
$seoTitle = 'Travel Blog | ASB Tours Sri Lanka';
$seoDesc = $cfg('blog_meta_desc','ASB Tours Sri Lanka Blog, Travel tips, destination guides, food culture, visa planning, and honeymoon ideas for your perfect Sri Lanka trip.');
$seoCanonical = absolute_site_url('pages/blog.php' . (($activeCat !== 'all' || $page > 1) ? ('?' . http_build_query(array_filter(['cat' => $activeCat === 'all' ? null : $activeCat, 'page' => $page > 1 ? $page : null]))) : ''));
$seoImage = $cfg('seo_image', '') ?: ($cfg('site_logo', '') ?: 'assets/images/logo.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
    <?php include __DIR__ . '/../assets/php/site-meta.php'; ?>
    <?php nt_render_seo_tags([
        'title' => $seoTitle,
        'description' => $seoDesc,
        'canonical' => $seoCanonical,
        'image' => $seoImage,
        'type' => 'website',
        'site_name' => $cfg('site_name', 'ASB Tours'),
        'structured_data' => [[
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => 'ASB Tours Travel Blog',
            'url' => $seoCanonical,
            'description' => $seoDesc,
        ]],
    ]); ?>
    <title><?= htmlspecialchars($seoTitle) ?></title>    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <?php include __DIR__ . '/../assets/php/site-theme.php'; ?>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/services-page.css">
    <link rel="stylesheet" href="../assets/css/blog-page.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

    <div id="navbar-placeholder"></div>

    <!-- PAGE HERO -->
    <section class="page-hero" style="background-image: url('../assets/images/destinations/sigiriya.jpg'); background-position: center 50%;">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <span>Travel Blog</span>
            </div>
            <h1>Stories from <span>Sri Lanka</span></h1>
            <p>Travel tips, destination guides, food culture, and insider knowledge for your perfect Sri Lanka journey.</p>
            <div style="display:flex; gap:22px; flex-wrap:wrap; margin-top:22px;">
                <span style="display:flex; align-items:center; gap:7px; color:rgba(255,255,255,0.9); font-size:0.88rem; font-weight:500;">
                    <i class="fa-solid fa-newspaper" style="color:var(--accent);"></i>
                    <?= $useStatic ? '24+' : $totalArticles ?>+ Articles
                </span>
                <span style="display:flex; align-items:center; gap:7px; color:rgba(255,255,255,0.9); font-size:0.88rem; font-weight:500;">
                    <i class="fa-solid fa-tags" style="color:var(--accent);"></i>
                    <?= $useStatic ? 6 : count($dbCats) ?> Categories
                </span>
                <span style="display:flex; align-items:center; gap:7px; color:rgba(255,255,255,0.9); font-size:0.88rem; font-weight:500;">
                    <i class="fa-solid fa-pen-nib" style="color:var(--accent);"></i> Expert Writers
                </span>
            </div>
        </div>
    </section>

    <!-- BLOG SECTION -->
    <section class="blog-section section-pad">
        <div class="container">

<?php if ($useStatic): ?>
            <!-- FEATURED POST (static fallback) -->
            <div class="featured-post-wrap" data-aos="fade-up">
                <div class="featured-post">
                    <div class="featured-post-img">
                        <span class="featured-label"><i class="fa-solid fa-fire"></i> Featured</span>
                        <img src="<?= $staticFeatured['cover_image'] ?>" alt="<?= htmlspecialchars($staticFeatured['title']) ?>">
                    </div>
                    <div class="featured-post-body">
                        <span class="blog-category-tag"><i class="fa-solid fa-map"></i> Travel Tips</span>
                        <h2><a href="blog-detail.php"><?= htmlspecialchars($staticFeatured['title']) ?></a></h2>
                        <div class="blog-post-meta">
                            <span><i class="fa-solid fa-calendar"></i> March 15, 2026</span>
                            <span><i class="fa-solid fa-clock"></i> 12 min read</span>
                            <span><i class="fa-solid fa-eye"></i> 8,420 views</span>
                        </div>
                        <p><?= htmlspecialchars($staticFeatured['excerpt']) ?></p>
                        <div class="blog-author">
                            <div class="author-avatar">DK</div>
                            <div class="author-info">
                                <span class="author-name">Dinesh Kumara</span>
                                <span class="author-role">Head of Travel, ASB Tours</span>
                            </div>
                        </div>
                        <a href="blog-detail.php" class="read-more-btn">
                            <i class="fa-solid fa-book-open"></i> Read Full Guide
                        </a>
                    </div>
                </div>
            </div>

<?php elseif ($featuredPost && $activeCat === 'all' && $page === 1): ?>
            <!-- FEATURED POST (DB) -->
            <?php
                $fCat      = $featuredPost['category'] ?? '';
                $fCatLabel = $catConfig[$fCat]['label']   ?? 'General';
                $fCatIcon  = $catConfig[$fCat]['icon']    ?? 'fa-tag';
                $fDate     = $featuredPost['published_at'] ? date('F j, Y', strtotime($featuredPost['published_at'])) : '';
                $fReadTime = readTime($featuredPost['content'] ?? '');
                $fInitials = strtoupper(substr($cfg('about_company_name','NT'), 0, 2));
            ?>
            <div class="featured-post-wrap" data-aos="fade-up">
                <div class="featured-post">
                    <div class="featured-post-img">
                        <span class="featured-label"><i class="fa-solid fa-fire"></i> Featured</span>
                        <?php if ($featuredPost['cover_image']): ?>
                            <img src="<?= htmlspecialchars(site_url($featuredPost['cover_image'])) ?>" alt="<?= htmlspecialchars($featuredPost['title']) ?>">
                        <?php else: ?>
                            <img src="../assets/images/destinations/sigiriya.jpg" alt="<?= htmlspecialchars($featuredPost['title']) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="featured-post-body">
                        <span class="blog-category-tag">
                            <i class="fa-solid <?= $fCatIcon ?>"></i> <?= htmlspecialchars($fCatLabel) ?>
                        </span>
                        <h2><a href="blog-detail.php?slug=<?= urlencode($featuredPost['slug']) ?>"><?= htmlspecialchars($featuredPost['title']) ?></a></h2>
                        <div class="blog-post-meta">
                            <?php if ($fDate): ?><span><i class="fa-solid fa-calendar"></i> <?= $fDate ?></span><?php endif; ?>
                            <span><i class="fa-solid fa-clock"></i> <?= $fReadTime ?> min read</span>
                        </div>
                        <p><?= htmlspecialchars($featuredPost['excerpt'] ?? '') ?></p>
                        <div class="blog-author">
                            <div class="author-avatar"><?= $fInitials ?></div>
                            <div class="author-info">
                                <span class="author-name"><?= htmlspecialchars($cfg('about_company_name','ASB Tours')) ?></span>
                                <span class="author-role">ASB Tours Team</span>
                            </div>
                        </div>
                        <a href="blog-detail.php?slug=<?= urlencode($featuredPost['slug']) ?>" class="read-more-btn">
                            <i class="fa-solid fa-book-open"></i> Read Full Article
                        </a>
                    </div>
                </div>
            </div>
<?php endif; ?>

            <!-- FILTER BAR -->
            <div class="blog-filter-bar" data-aos="fade-up">
                <a href="blog.php" class="blog-filter-btn <?= $activeCat === 'all' ? 'active' : '' ?>">
                    <i class="fa-solid fa-globe"></i> All Posts
                </a>
<?php if ($useStatic): ?>
                <a href="blog.php?cat=tips"         class="blog-filter-btn"><i class="fa-solid fa-lightbulb"></i> Travel Tips</a>
                <a href="blog.php?cat=destinations" class="blog-filter-btn"><i class="fa-solid fa-location-dot"></i> Destinations</a>
                <a href="blog.php?cat=food"         class="blog-filter-btn"><i class="fa-solid fa-utensils"></i> Food & Culture</a>
                <a href="blog.php?cat=wildlife"     class="blog-filter-btn"><i class="fa-solid fa-paw"></i> Wildlife</a>
                <a href="blog.php?cat=visa"         class="blog-filter-btn"><i class="fa-solid fa-passport"></i> Visa & Planning</a>
                <a href="blog.php?cat=honeymoon"    class="blog-filter-btn"><i class="fa-solid fa-heart"></i> Honeymoon</a>
<?php else: ?>
                <?php foreach ($catConfig as $key => $cat): if (!isset($dbCats[$key])) continue; ?>
                <a href="blog.php?cat=<?= $key ?>" class="blog-filter-btn <?= $activeCat === $key ? 'active' : '' ?>">
                    <i class="fa-solid <?= $cat['icon'] ?>"></i> <?= $cat['label'] ?>
                </a>
                <?php endforeach; ?>
<?php endif; ?>
            </div>

            <!-- BLOG GRID -->
            <div class="blog-grid" id="blogGrid">

<?php if ($useStatic): ?>
                <?php $delays = [50,100,150]; foreach ($staticPosts as $i => $sp):
                    $sCat  = $catConfig[$sp['category']] ?? ['icon'=>'fa-tag','label'=>'General','css'=>'cat-tips'];
                    $sTime = readTime($sp['content']);
                    $sDate = date('M j, Y', strtotime($sp['published_at']));
                    $delay = $delays[$i % 3];
                ?>
                <div class="blog-card-wrap" data-category="<?= $sp['category'] ?>" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                    <div class="blog-card">
                        <div class="blog-card-img">
                            <img src="<?= $sp['cover_image'] ?>" alt="<?= htmlspecialchars($sp['title']) ?>">
                            <span class="blog-card-category <?= $sCat['css'] ?>"><i class="fa-solid <?= $sCat['icon'] ?>"></i> <?= $sCat['label'] ?></span>
                            <span class="blog-read-time"><i class="fa-solid fa-clock"></i> <?= $sTime ?> min read</span>
                        </div>
                        <div class="blog-card-body">
                            <h3><a href="blog-detail.php"><?= htmlspecialchars($sp['title']) ?></a></h3>
                            <p><?= htmlspecialchars($sp['excerpt']) ?></p>
                            <div class="blog-card-meta">
                                <span><i class="fa-solid fa-calendar"></i> <?= $sDate ?></span>
                                <span><i class="fa-solid fa-eye"></i> <?= $sp['views'] ?> views</span>
                            </div>
                            <a href="blog-detail.php" class="blog-card-link">Read Article <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

<?php else: ?>
                <?php if (empty($pagePosts)): ?>
                <div style="grid-column:1/-1; text-align:center; padding:3rem 0; color:var(--text-light);">
                    <i class="fa-solid fa-newspaper" style="font-size:3rem; opacity:.3; display:block; margin-bottom:1rem;"></i>
                    No articles found in this category yet.
                </div>
                <?php else: $delays = [50,100,150]; foreach ($pagePosts as $i => $post):
                    $pCat    = $catConfig[$post['category'] ?? ''] ?? ['icon'=>'fa-tag','label'=>'General','css'=>'cat-tips'];
                    $pTime   = readTime($post['content'] ?? '');
                    $pDate   = $post['published_at'] ? date('M j, Y', strtotime($post['published_at'])) : '';
                    $pImg    = $post['cover_image'] ? site_url($post['cover_image']) : '../assets/images/destinations/sigiriya.jpg';
                    $delay   = $delays[$i % 3];
                ?>
                <div class="blog-card-wrap" data-category="<?= htmlspecialchars($post['category'] ?? '') ?>" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                    <div class="blog-card">
                        <div class="blog-card-img">
                            <img src="<?= $pImg ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                            <span class="blog-card-category <?= $pCat['css'] ?>">
                                <i class="fa-solid <?= $pCat['icon'] ?>"></i> <?= $pCat['label'] ?>
                            </span>
                            <span class="blog-read-time"><i class="fa-solid fa-clock"></i> <?= $pTime ?> min read</span>
                        </div>
                        <div class="blog-card-body">
                            <h3><a href="blog-detail.php?slug=<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                            <p><?= htmlspecialchars($post['excerpt'] ?? '') ?></p>
                            <div class="blog-card-meta">
                                <?php if ($pDate): ?><span><i class="fa-solid fa-calendar"></i> <?= $pDate ?></span><?php endif; ?>
                            </div>
                            <a href="blog-detail.php?slug=<?= urlencode($post['slug']) ?>" class="blog-card-link">
                                Read Article <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
<?php endif; ?>

            </div><!-- end blog-grid -->

            <!-- PAGINATION (DB only, multi-page) -->
<?php if (!$useStatic && $totalPages > 1): ?>
            <div class="blog-pagination" data-aos="fade-up">
                <?php if ($page > 1): ?>
                <a href="<?= pageUrl($page - 1, $activeCat) ?>" class="page-btn prev-next">
                    <i class="fa-solid fa-chevron-left"></i> Prev
                </a>
                <?php else: ?>
                <span class="page-btn prev-next disabled" style="opacity:.4; pointer-events:none;">
                    <i class="fa-solid fa-chevron-left"></i> Prev
                </span>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($totalPages > 7 && $p > 3 && $p < $totalPages - 1 && abs($p - $page) > 1): ?>
                        <?php if ($p === 4 || $p === $totalPages - 2): ?>
                        <span style="color:var(--text-light); padding:0 4px;">...</span>
                        <?php endif; ?>
                    <?php else: ?>
                    <a href="<?= pageUrl($p, $activeCat) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="<?= pageUrl($page + 1, $activeCat) ?>" class="page-btn prev-next">
                    Next <i class="fa-solid fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="page-btn prev-next disabled" style="opacity:.4; pointer-events:none;">
                    Next <i class="fa-solid fa-chevron-right"></i>
                </span>
                <?php endif; ?>
            </div>
<?php elseif ($useStatic): ?>
            <div class="blog-pagination" data-aos="fade-up">
                <a href="#" class="page-btn prev-next"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                <a href="#" class="page-btn active">1</a>
                <a href="#" class="page-btn">2</a>
                <a href="#" class="page-btn">3</a>
                <span style="color:var(--text-light); padding:0 4px;">...</span>
                <a href="#" class="page-btn">8</a>
                <a href="#" class="page-btn prev-next">Next <i class="fa-solid fa-chevron-right"></i></a>
            </div>
<?php endif; ?>

        </div>
    </section>

    <!-- NEWSLETTER STRIP -->
    <div class="newsletter-strip">
        <div class="container">
            <div class="newsletter-inner">
                <div class="newsletter-text">
                    <h3><i class="fa-solid fa-envelope-open-text"></i> Get Sri Lanka Travel Tips</h3>
                    <p>Join 12,000+ travelers who get our weekly newsletter, destination guides, hidden gems, and exclusive tour deals.</p>
                </div>
                <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                    <input type="email" placeholder="Enter your email address" required>
                    <button type="submit"><i class="fa-solid fa-paper-plane"></i> Subscribe</button>
                </form>
            </div>
        </div>
    </div>

    <!-- BLOG CTA -->
    <section class="blog-cta">
        <div class="container">
            <div data-aos="fade-up">
                <span class="section-tag light">Ready to Explore?</span>
                <h2 style="margin-top:12px;">Stop Reading, Start <span>Exploring</span></h2>
                <p>Every article in our blog is written by people who have been there. Now it's your turn, let us plan your perfect Sri Lanka journey.</p>
                <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
                    <a href="packages.php" class="btn btn-primary">
                        <i class="fa-solid fa-compass"></i> Browse Packages
                    </a>
                    <a href="../index.php#contact" class="btn btn-outline" style="border-color:rgba(255,255,255,0.35); color:white;">
                        <i class="fa-solid fa-headset"></i> Talk to an Expert
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div id="footer-placeholder"></div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/components.js?v=8"></script>
    <script src="../assets/js/navbar.js"></script>
    <script src="../assets/js/animations.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        AOS.init({ duration: 700, once: true, offset: 60 });

        <?php if ($useStatic): ?>
        // Static filter (client-side)
        const filterBtns = document.querySelectorAll('.blog-filter-btn');
        const blogCards  = document.querySelectorAll('.blog-card-wrap');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', e => {
                if (btn.tagName === 'A' && btn.getAttribute('href') === 'blog.php') return;
                e.preventDefault();
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const filter = btn.dataset.filter || new URL(btn.href).searchParams.get('cat') || 'all';
                blogCards.forEach(card => {
                    card.classList.toggle('hidden', filter !== 'all' && card.dataset.category !== filter);
                });
            });
        });
        <?php endif; ?>

        function handleNewsletter(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Subscribed!';
            btn.style.background = '#2D8B5B';
            e.target.querySelector('input').value = '';
            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Subscribe';
                btn.style.background = '';
            }, 3000);
        }
    </script>
</body>
</html>




