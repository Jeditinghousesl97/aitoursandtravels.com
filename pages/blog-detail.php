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

// Load post 
$slug     = trim($_GET['slug'] ?? '');
$useStatic = false;
$post      = null;

if ($slug !== '') {
    $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1');
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if (!$post) {
        // Not found, redirect to blog listing
        header('Location: blog.php');
        exit;
    }
} else {
    $useStatic = true;
}

// Build TOC from content h2 tags 
function buildTOC(string &$content): array {
    $toc = [];
    $n   = 0;
    $content = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/si', function ($m) use (&$toc, &$n) {
        $n++;
        $id   = 'section-' . $n;
        $text = strip_tags($m[2]);
        $toc[] = ['id' => $id, 'text' => $text, 'num' => $n];
        return '<h2' . $m[1] . ' id="' . $id . '">' . $m[2] . '</h2>';
    }, $content);
    return $toc;
}

function readTime(string $html): int {
    return max(1, (int)round(str_word_count(strip_tags($html)) / 200));
}

// Dynamic post meta 
if (!$useStatic) {
    $content   = $post['content'] ?? '';
    $toc       = buildTOC($content);
    $readTime  = readTime($content);
    $pubDate   = $post['published_at'] ? date('F j, Y', strtotime($post['published_at'])) : '';
    $pCat      = $catConfig[$post['category'] ?? ''] ?? ['icon' => 'fa-tag', 'label' => 'General', 'css' => 'cat-tips'];
    $heroImg   = $post['cover_image'] ? site_url($post['cover_image']) : 'assets/images/destinations/sigiriya.jpg';
    $postGallery = [];
    if (!empty($post['gallery_images'])) {
        $decodedGallery = json_decode((string)$post['gallery_images'], true);
        if (is_array($decodedGallery)) {
            foreach ($decodedGallery as $imgPath) {
                if (is_string($imgPath) && $imgPath !== '') {
                    $postGallery[] = site_url($imgPath);
                }
            }
        }
    }
    $pageTitle = htmlspecialchars($post['title']) . ' | ASB Tours Blog';
    $metaDesc  = htmlspecialchars($post['excerpt'] ?? substr(strip_tags($post['content'] ?? ''), 0, 160));

    // Related posts (same category, exclude self, limit 3)
    $related = $pdo->prepare('SELECT * FROM blog_posts WHERE is_published = 1 AND category = ? AND id != ? ORDER BY published_at DESC LIMIT 3');
    $related->execute([$post['category'], $post['id']]);
    $relatedPosts = $related->fetchAll();
    // Fill to 3 from other categories if needed
    if (count($relatedPosts) < 3) {
        $exclude = array_merge([$post['id']], array_column($relatedPosts, 'id'));
        $phs     = implode(',', array_fill(0, count($exclude), '?'));
        $fill    = $pdo->prepare("SELECT * FROM blog_posts WHERE is_published = 1 AND id NOT IN ($phs) ORDER BY published_at DESC LIMIT " . (3 - count($relatedPosts)));
        $fill->execute($exclude);
        $relatedPosts = array_merge($relatedPosts, $fill->fetchAll());
    }
} else {
    $pageTitle = 'The Ultimate Sri Lanka Travel Guide 2026 | ASB Tours Blog';
    $metaDesc  = 'The Ultimate Sri Lanka Travel Guide 2026, Best time to visit, top destinations, visa info, budget tips, food guide, and everything you need to plan the perfect trip.';
    $heroImg   = 'assets/images/packages/cultural-triangle.jpg';
    $pubDate   = 'March 15, 2026';
    $readTime  = 12;
    $pCat      = $catConfig['tips'];
    $toc = [
        ['id'=>'section-1','text'=>'Best Time to Visit','num'=>1],
        ['id'=>'section-2','text'=>'Top Destinations',  'num'=>2],
        ['id'=>'section-3','text'=>'Getting Around',    'num'=>3],
        ['id'=>'section-4','text'=>'Visa & Entry',      'num'=>4],
        ['id'=>'section-5','text'=>'Budget Guide',      'num'=>5],
        ['id'=>'section-6','text'=>'Food & Cuisine',    'num'=>6],
        ['id'=>'section-7','text'=>'Cultural Tips',     'num'=>7],
        ['id'=>'section-8','text'=>'Packing List',      'num'=>8],
    ];
    $relatedPosts = [];
    $postGallery = [];
}
$seoCanonical = $useStatic
    ? absolute_site_url('pages/blog-detail.php')
    : absolute_site_url('pages/blog-detail.php?slug=' . rawurlencode($slug));
$seoDesc = html_entity_decode($metaDesc, ENT_QUOTES, 'UTF-8');
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
        'title' => html_entity_decode($pageTitle, ENT_QUOTES, 'UTF-8'),
        'description' => $seoDesc,
        'canonical' => $seoCanonical,
        'image' => $seoImage,
        'type' => 'article',
        'site_name' => $cfg('site_name', 'ASB Tours'),
        'structured_data' => [[
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $useStatic ? 'The Ultimate Sri Lanka Travel Guide 2026, Everything You Need to Know' : $post['title'],
            'description' => $seoDesc,
            'datePublished' => $useStatic ? '2026-03-15' : ($post['published_at'] ?: date('Y-m-d')),
            'dateModified' => $useStatic ? '2026-03-15' : ($post['published_at'] ?: date('Y-m-d')),
            'image' => [absolute_site_url($seoImage)],
            'mainEntityOfPage' => $seoCanonical,
            'author' => [
                '@type' => 'Organization',
                'name' => $cfg('site_name', 'ASB Tours'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $cfg('site_name', 'ASB Tours'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => absolute_site_url($cfg('site_logo', '') ?: 'assets/images/logo.png'),
                ],
            ],
        ]],
    ]); ?>
    <title><?= $pageTitle ?></title>    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <?php include __DIR__ . '/../assets/php/site-theme.php'; ?>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/blog-page.css">
    <link rel="stylesheet" href="../assets/css/blog-detail.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

    <div id="navbar-placeholder"></div>

    <!-- ARTICLE HERO -->
    <div class="article-hero page-hero" style="background-image: url('<?= $heroImg ?>');" id="article-top">
        <div class="container">
            <span class="article-hero-category">
                <i class="fa-solid <?= $pCat['icon'] ?>"></i> <?= $pCat['label'] ?>
            </span>
            <h1><?= $useStatic ? 'The Ultimate Sri Lanka Travel Guide 2026, Everything You Need to Know' : htmlspecialchars($post['title']) ?></h1>
            <div class="article-hero-meta">
                <?php if ($pubDate): ?><span><i class="fa-solid fa-calendar"></i> <?= $pubDate ?></span><?php endif; ?>
                <span><i class="fa-solid fa-clock"></i> <?= $readTime ?> min read</span>
                <?php if ($useStatic): ?>
                <span><i class="fa-solid fa-eye"></i> 8,420 views</span>
                <span><i class="fa-solid fa-comments"></i> 64 comments</span>
                <?php endif; ?>
            </div>
            <div class="article-hero-author">
                <div class="author-avatar"><?= $useStatic ? 'DK' : strtoupper(substr($cfg('about_company_name','NT'),0,2)) ?></div>
                <div>
                    <span class="author-name"><?= $useStatic ? 'Dinesh Kumara' : htmlspecialchars($cfg('about_company_name','ASB Tours')) ?></span>
                    <span class="author-role"><?= $useStatic ? 'Head of Travel, ASB Tours' : 'ASB Tours Team' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- BREADCRUMB BAR -->
    <div class="article-breadcrumb-bar">
        <div class="container">
            <a href="../index.php"><i class="fa fa-house"></i> Home</a>
            <i class="fa fa-chevron-right"></i>
            <a href="blog.php">Blog</a>
            <i class="fa fa-chevron-right"></i>
            <span><?= $useStatic ? 'Travel Guide' : htmlspecialchars($pCat['label']) ?></span>
        </div>
    </div>

    <!-- ARTICLE SECTION -->
    <section class="article-section section-pad">
        <div class="container">
            <div class="article-layout">

                <!-- ARTICLE BODY -->
                <div class="article-content" data-aos="fade-up">
                    <div class="article-body">

<?php if ($useStatic): ?>
                        <p>Sri Lanka, the teardrop-shaped island at the southern tip of India, packs an extraordinary variety of experiences into a country barely the size of Ireland. In a single two-week trip you can climb a 5th-century rock fortress, ride the world's most scenic train, spot leopards on a jungle safari, swim in the Indian Ocean, and sip freshly picked tea on a misty mountain estate. This is your complete guide to making every day count.</p>

                        <h2 id="section-1">1. Best Time to Visit Sri Lanka</h2>
                        <p>Sri Lanka doesn't have a single best season, it depends entirely on <strong>which part of the island</strong> you plan to visit. The island has two distinct monsoon seasons that affect different coasts at different times.</p>
                        <div class="info-grid">
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-sun"></i> West &amp; South Coast</span><span class="info-val">November, April (dry season)</span></div>
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-umbrella-beach"></i> East Coast</span><span class="info-val">April, September (dry season)</span></div>
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-mountain"></i> Hill Country</span><span class="info-val">January, April (best visibility)</span></div>
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-paw"></i> Yala Safari</span><span class="info-val">February, July (peak wildlife)</span></div>
                        </div>
                        <div class="tip-box"><div class="tip-box-header"><i class="fa-solid fa-lightbulb"></i> Pro Tip</div><p>If you want to see the <strong>entire island</strong> in one trip, visit between <strong>December and March</strong>, the west, south, and cultural triangle are all dry, and Yala is good too. Only the east coast is monsoon-affected during this period.</p></div>

                        <h2 id="section-2">2. Top Destinations in Sri Lanka</h2>
                        <p>Sri Lanka rewards slow travelers. Here are the absolute must-visit destinations, each one completely different from the last.</p>
                        <img src="../assets/images/blog/sigiriya.jpg" alt="Sigiriya Rock Fortress" class="article-img">
                        <p class="article-img-caption">Sigiriya Rock Fortress, a UNESCO World Heritage Site and one of Sri Lanka's most iconic landmarks</p>
                        <h3>Sigiriya &amp; the Cultural Triangle</h3>
                        <p>The <strong>Cultural Triangle</strong> in north-central Sri Lanka is home to some of the most remarkable ancient sites in Asia. Sigiriya Rock Fortress (5th century), the Dambulla Cave Temples, the medieval royal city of Polonnaruwa, and the sacred ancient capital of Anuradhapura form a circuit that most visitors spend 3–4 days exploring.</p>
                        <h3>Kandy, The Cultural Capital</h3>
                        <p>Set in a bowl of forested hills around a picturesque lake, Kandy is Sri Lanka's second city and spiritual heart. The <strong>Temple of the Tooth Relic</strong> (Sri Dalada Maligawa) houses what is believed to be the Buddha's tooth and draws thousands of pilgrims daily.</p>
                        <h3>The Hill Country: Ella, Nuwara Eliya &amp; the Train</h3>
                        <p>The <strong>Kandy to Ella train</strong> is consistently rated one of the world's most scenic rail journeys. Winding through emerald tea estates, misty mountains, and past waterfalls for 7 hours, it's an experience in itself.</p>
                        <img src="../assets/images/blog/ella.jpg" alt="Hill Country Tea Estates" class="article-img">
                        <p class="article-img-caption">The emerald tea estates of Sri Lanka's Hill Country, best seen from the train window between Kandy and Ella</p>
                        <div class="pull-quote"><p>"Sri Lanka is like five countries in one, ancient ruins in the morning, tea estates at noon, and a beach sunset to end the day. I've been to 40+ countries and this is the one I keep returning to."</p><cite>— Sarah Mitchell, UK, ASB Tours guest, 2025</cite></div>

                        <h2 id="section-3">3. Getting Around Sri Lanka</h2>
                        <p>Sri Lanka is a small country (roughly 430 km north to south) but roads can be slow. Here are your main transport options:</p>
                        <ul>
                            <li><strong>Private A/C vehicle with driver:</strong> The most comfortable and flexible option, especially for families and couples. This is what all ASB Tours packages include.</li>
                            <li><strong>Trains:</strong> Scenic and affordable but slow. Essential for the Kandy–Ella route.</li>
                            <li><strong>Tuk-tuks:</strong> Perfect for short distances within towns.</li>
                            <li><strong>Buses:</strong> Very cheap but crowded and slow.</li>
                        </ul>
                        <div class="warn-box"><i class="fa-solid fa-triangle-exclamation"></i><p><strong>Driving yourself</strong> in Sri Lanka is not recommended for first-time visitors. Roads are narrow, unmarked, and shared with buses, tuk-tuks, cattle, and pedestrians. Hiring a driver is affordable ($30–50/day) and stress-free.</p></div>

                        <h2 id="section-4">4. Visa &amp; Entry Requirements</h2>
                        <p>Most nationalities can enter Sri Lanka on an <strong>Electronic Travel Authorisation (ETA)</strong>, which must be applied for online before arrival. The process takes 10–15 minutes and approval is usually instant.</p>
                        <div class="info-grid">
                            <div class="info-grid-item"><span class="info-label">ETA Cost</span><span class="info-val">USD $35 (tourist)</span></div>
                            <div class="info-grid-item"><span class="info-label">Stay Allowed</span><span class="info-val">Up to 30 days</span></div>
                            <div class="info-grid-item"><span class="info-label">Apply Online</span><span class="info-val">eta.gov.lk</span></div>
                            <div class="info-grid-item"><span class="info-label">Processing Time</span><span class="info-val">Instant – 24 hours</span></div>
                        </div>

                        <h2 id="section-5">5. Budget Guide, How Much Does Sri Lanka Cost?</h2>
                        <div class="info-grid">
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-tent"></i> Budget Traveler</span><span class="info-val">$30 – $50 / day</span></div>
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-hotel"></i> Mid-Range</span><span class="info-val">$80 – $150 / day</span></div>
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-star"></i> Comfort</span><span class="info-val">$150 – $280 / day</span></div>
                            <div class="info-grid-item"><span class="info-label"><i class="fa-solid fa-gem"></i> Luxury</span><span class="info-val">$280+ / day</span></div>
                        </div>

                        <h2 id="section-6">6. Sri Lanka Food &amp; Cuisine</h2>
                        <p>Sri Lankan food is bold, spicy, and deeply satisfying, a cuisine built around rice, coconut, and a complex layering of spices. Don't leave without trying rice &amp; curry, hoppers, kottu roti, string hoppers, and pol sambol.</p>
                        <img src="../assets/images/blog/food.jpg" alt="Sri Lanka Tea" class="article-img">
                        <p class="article-img-caption">Fresh Ceylon tea from the hill country estates, always served strong and aromatic</p>

                        <h2 id="section-7">7. Cultural Tips &amp; Etiquette</h2>
                        <p>Sri Lanka is a deeply religious country, Buddhism is practiced by ~70% of the population.</p>
                        <ul>
                            <li><strong>Dress modestly at temples:</strong> Cover your shoulders and knees. Remove shoes before entering.</li>
                            <li><strong>Never turn your back to a Buddha statue</strong> for a photo, this is considered disrespectful.</li>
                            <li><strong>Use your right hand</strong> for giving/receiving things and greeting.</li>
                            <li><strong>Bargaining is acceptable</strong> in markets and with tuk-tuk drivers, but not in restaurants.</li>
                        </ul>
                        <div class="pull-quote"><p>"Sri Lanka gave us more warmth, more color, and more soul than we expected. The people are what make this island truly special."</p><cite>— Emma van den Berg, Netherlands, ASB Tours guest, 2025</cite></div>

                        <h2 id="section-8">8. Essential Packing List</h2>
                        <div class="tip-box"><div class="tip-box-header"><i class="fa-solid fa-bag-shopping"></i> What to Pack</div><ul>
                            <li>Lightweight, breathable clothing (linen or cotton)</li>
                            <li>A light layer or jacket for Hill Country evenings</li>
                            <li>Comfortable walking shoes and sandals</li>
                            <li>High-SPF sunscreen, the tropical sun is intense year-round</li>
                            <li>Insect repellent, especially near national parks</li>
                            <li>Sarong, doubles as a temple cover-up and beach towel</li>
                            <li>Reusable water bottle, tap water is not safe to drink</li>
                            <li>Power adapter, Sri Lanka uses Type D/G plugs (230V)</li>
                        </ul></div>

<?php else: ?>
                        <?= $content ?>
<?php endif; ?>

                        <?php if (!$useStatic && !empty($postGallery)): ?>
                        <div class="article-gallery-wrap">
                            <h3 style="margin-top:2rem;">Photo Gallery</h3>
                            <div class="article-gallery-masonry">
                                <?php foreach ($postGallery as $galleryImage): ?>
                                <div class="gallery-item">
                                    <img src="<?= htmlspecialchars($galleryImage) ?>" alt="Blog gallery image">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Share Bar -->
                        <div class="article-share-bar">
                            <span class="share-label"><i class="fa-solid fa-share-nodes"></i> Share:</span>
                            <a href="#" class="share-btn facebook" onclick="shareOn('facebook');return false;"><i class="fab fa-facebook-f"></i> Facebook</a>
                            <a href="#" class="share-btn whatsapp" onclick="shareOn('whatsapp');return false;"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                            <a href="#" class="share-btn twitter"  onclick="shareOn('twitter');return false;"><i class="fab fa-x-twitter"></i> Twitter</a>
                            <button class="share-btn copy" onclick="copyLink()"><i class="fa-solid fa-link"></i> Copy Link</button>
                        </div>

                        <!-- Author Bio -->
                        <div class="author-bio-card">
                            <div class="author-bio-avatar"><?= $useStatic ? 'DK' : strtoupper(substr($cfg('about_company_name','NT'),0,2)) ?></div>
                            <div class="author-bio-text">
                                <h4><?= $useStatic ? 'Dinesh Kumara' : htmlspecialchars($cfg('about_company_name','ASB Tours')) ?></h4>
                                <span class="author-bio-role"><?= $useStatic ? 'Head of Travel, ASB Tours' : 'ASB Tours Team' ?></span>
                                <p><?= htmlspecialchars($cfg('about_paragraph_1', 'Our team has been guiding travelers through Sri Lanka for over 10 years, sharing its culture, cuisine, and hidden gems with the world.')) ?></p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- STICKY SIDEBAR -->
                <aside class="article-sidebar" data-aos="fade-up" data-aos-delay="100">

                    <!-- Table of Contents -->
                    <?php if (!empty($toc)): ?>
                    <div class="sidebar-card">
                        <div class="sidebar-card-header"><i class="fa-solid fa-list"></i> Table of Contents</div>
                        <div class="sidebar-card-body">
                            <ul class="toc-list">
                                <?php foreach ($toc as $item): ?>
                                <li>
                                    <a href="#<?= $item['id'] ?>">
                                        <span class="toc-num"><?= $item['num'] ?></span>
                                        <?= htmlspecialchars($item['text']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Share -->
                    <div class="sidebar-card">
                        <div class="sidebar-card-header"><i class="fa-solid fa-share-nodes"></i> Share This Article</div>
                        <div class="sidebar-card-body">
                            <div class="sidebar-share-btns">
                                <a href="#" class="sidebar-share-btn facebook" onclick="shareOn('facebook');return false;"><i class="fab fa-facebook-f"></i> Share on Facebook</a>
                                <a href="#" class="sidebar-share-btn whatsapp" onclick="shareOn('whatsapp');return false;"><i class="fab fa-whatsapp"></i> Share on WhatsApp</a>
                                <a href="#" class="sidebar-share-btn twitter"  onclick="shareOn('twitter');return false;"><i class="fab fa-x-twitter"></i> Share on Twitter</a>
                                <button class="sidebar-share-btn copy" onclick="copyLink()"><i class="fa-solid fa-link"></i> Copy Link</button>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Card -->
                    <div class="sidebar-cta-card">
                        <h4>Ready to Visit Sri Lanka?</h4>
                        <p>Let our expert team plan your perfect itinerary, all destinations from this guide, zero stress.</p>
                        <a href="packages.php" class="sidebar-cta-btn"><i class="fa-solid fa-compass"></i> View Packages</a>
                        <a href="../index.php#contact" class="sidebar-cta-link"><i class="fa-solid fa-headset"></i> Talk to an Expert</a>
                    </div>

                </aside>

            </div><!-- end .article-layout -->
        </div>
    </section>

    <!-- RELATED POSTS -->
    <section class="related-posts-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Keep Reading</span>
                <h2 class="section-title">Related Articles</h2>
                <p class="section-subtitle">More Sri Lanka guides you'll find useful</p>
            </div>
            <div class="related-posts-grid" data-aos="fade-up" data-aos-delay="100">

<?php if ($useStatic || empty($relatedPosts)): ?>
                <div class="blog-card">
                    <div class="blog-card-img">
                        <img src="../assets/images/destinations/sigiriya.jpg" alt="Sigiriya Guide">
                        <span class="blog-card-category cat-dest"><i class="fa-solid fa-location-dot"></i> Destinations</span>
                        <span class="blog-read-time"><i class="fa-solid fa-clock"></i> 8 min</span>
                    </div>
                    <div class="blog-card-body">
                        <h3><a href="blog-detail.php">Top 10 Things to Do at Sigiriya Rock Fortress</a></h3>
                        <p>Sunrise climbs, ancient frescoes, mirror wall poetry, everything you need to know before visiting.</p>
                        <div class="blog-card-meta"><span><i class="fa-solid fa-calendar"></i> Feb 28, 2026</span></div>
                        <a href="blog-detail.php" class="blog-card-link">Read Article <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="blog-card">
                    <div class="blog-card-img">
                        <img src="../assets/images/packages/beach-escape.jpg" alt="Best Beaches">
                        <span class="blog-card-category cat-dest"><i class="fa-solid fa-umbrella-beach"></i> Destinations</span>
                        <span class="blog-read-time"><i class="fa-solid fa-clock"></i> 10 min</span>
                    </div>
                    <div class="blog-card-body">
                        <h3><a href="blog-detail.php">Best Beaches in Sri Lanka: A Complete Coastal Guide</a></h3>
                        <p>From Mirissa whale watching to the undiscovered coves of Tangalle, find your perfect beach.</p>
                        <div class="blog-card-meta"><span><i class="fa-solid fa-calendar"></i> Feb 14, 2026</span></div>
                        <a href="blog-detail.php" class="blog-card-link">Read Article <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="blog-card">
                    <div class="blog-card-img">
                        <img src="../assets/images/packages/yala-safari.jpg" alt="Yala Safari">
                        <span class="blog-card-category cat-wildlife"><i class="fa-solid fa-paw"></i> Wildlife</span>
                        <span class="blog-read-time"><i class="fa-solid fa-clock"></i> 9 min</span>
                    </div>
                    <div class="blog-card-body">
                        <h3><a href="blog-detail.php">Yala Safari Guide: How to Spot a Sri Lankan Leopard</a></h3>
                        <p>Which zones to enter, best times of day, and what to expect on a Yala National Park jeep safari.</p>
                        <div class="blog-card-meta"><span><i class="fa-solid fa-calendar"></i> Jan 18, 2026</span></div>
                        <a href="blog-detail.php" class="blog-card-link">Read Article <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>

<?php else: ?>
                <?php foreach ($relatedPosts as $r):
                    $rCat  = $catConfig[$r['category'] ?? ''] ?? ['icon'=>'fa-tag','label'=>'General','css'=>'cat-tips'];
                    $rTime = readTime($r['content'] ?? '');
                    $rDate = $r['published_at'] ? date('M j, Y', strtotime($r['published_at'])) : '';
                    $rImg  = $r['cover_image'] ? site_url($r['cover_image']) : '../assets/images/destinations/sigiriya.jpg';
                ?>
                <div class="blog-card">
                    <div class="blog-card-img">
                        <img src="<?= $rImg ?>" alt="<?= htmlspecialchars($r['title']) ?>">
                        <span class="blog-card-category <?= $rCat['css'] ?>">
                            <i class="fa-solid <?= $rCat['icon'] ?>"></i> <?= $rCat['label'] ?>
                        </span>
                        <span class="blog-read-time"><i class="fa-solid fa-clock"></i> <?= $rTime ?> min</span>
                    </div>
                    <div class="blog-card-body">
                        <h3><a href="blog-detail.php?slug=<?= urlencode($r['slug']) ?>"><?= htmlspecialchars($r['title']) ?></a></h3>
                        <p><?= htmlspecialchars($r['excerpt'] ?? '') ?></p>
                        <div class="blog-card-meta">
                            <?php if ($rDate): ?><span><i class="fa-solid fa-calendar"></i> <?= $rDate ?></span><?php endif; ?>
                        </div>
                        <a href="blog-detail.php?slug=<?= urlencode($r['slug']) ?>" class="blog-card-link">
                            Read Article <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
<?php endif; ?>

            </div>
        </div>
    </section>

    <!-- BLOG DETAIL CTA -->
    <section class="blog-detail-cta">
        <div class="container">
            <div data-aos="fade-up">
                <span class="section-tag light">Plan Your Trip</span>
                <h2 style="margin-top:12px;">This Guide Is Just the <span>Beginning</span></h2>
                <p>Our team has been living and breathing Sri Lanka for over 10 years. Let us turn this guide into your personal itinerary.</p>
                <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
                    <a href="packages.php" class="btn btn-primary">
                        <i class="fa-solid fa-compass"></i> Browse All Packages
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
    <style>
        .article-gallery-masonry {
            column-count: 3;
            column-gap: 16px;
            margin-top: 14px;
        }
        .article-gallery-masonry .gallery-item {
            break-inside: avoid;
            margin-bottom: 16px;
        }
        .article-gallery-masonry .gallery-item img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            object-fit: cover;
        }
        @media (max-width: 991.98px) {
            .article-gallery-masonry {
                column-count: 2;
            }
        }
        @media (max-width: 575.98px) {
            .article-gallery-masonry {
                column-count: 1;
            }
        }
    </style>
    <script>
        AOS.init({ duration: 700, once: true, offset: 60 });

        // Copy article link
        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                document.querySelectorAll('.share-btn.copy, .sidebar-share-btn.copy').forEach(btn => {
                    const orig = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                    btn.style.background = 'rgba(45,139,91,0.12)';
                    btn.style.color = '#2D8B5B';
                    setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; btn.style.color = ''; }, 2500);
                });
            });
        }

        // Social share
        function shareOn(platform) {
            const url   = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(document.title);
            const urls  = {
                facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url,
                whatsapp: 'https://wa.me/?text=' + title + '%20' + url,
                twitter:  'https://twitter.com/intent/tweet?url=' + url + '&text=' + title,
            };
            if (urls[platform]) window.open(urls[platform], '_blank', 'width=600,height=400');
        }

        // Active TOC on scroll
        const sections = document.querySelectorAll('h2[id]');
        const tocLinks = document.querySelectorAll('.toc-list a');
        if (sections.length && tocLinks.length) {
            window.addEventListener('scroll', () => {
                let current = '';
                sections.forEach(sec => { if (window.scrollY >= sec.offsetTop - 140) current = sec.id; });
                tocLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + current) link.classList.add('active');
                });
            });
        }
    </script>
</body>
</html>


