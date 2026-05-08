<?php
require_once __DIR__ . '/admin/config/db.php';
require_once __DIR__ . '/assets/php/turnstile.php';
require_once __DIR__ . '/assets/php/seo.php';
$pdo = getPDO();

// Maintenance check
if (file_exists(__DIR__ . '/maintenance.flag')) {
    include __DIR__ . '/maintenance.php'; exit;
}

// Load all settings
$s = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $key, string $default = '') => $s[$key] ?? $default;
$turnstileSiteKey = nt_turnstile_site_key($s);
$turnstileEnabled = nt_turnstile_enabled($s);
$assetVersion = urlencode((string)($cfg('cache_busted_at', '') ?: '1'));

// Hero banners
$banners = $pdo->query("SELECT * FROM hero_banners WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();

// What We Offer: always pin featured packages first, then newest packages.
$packages = $pdo->query("
    SELECT * FROM packages
    WHERE is_active = 1
    ORDER BY
        CASE WHEN is_featured = 1 THEN 0 ELSE 1 END ASC,
        created_at DESC,
        id DESC
    LIMIT 6
")->fetchAll();

// Latest published blog posts (for destinations section)
$blogs = $pdo->query("SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC LIMIT 5")->fetchAll();

// Gallery images
$gallery = $pdo->query("SELECT * FROM gallery WHERE is_active = 1 ORDER BY id DESC LIMIT 5")->fetchAll();

// Approved reviews
$reviews = $pdo->query("SELECT * FROM reviews WHERE is_approved = 1 ORDER BY id DESC LIMIT 6")->fetchAll();

// Badge classes
$badgeClass = ['popular'=>'badge-popular','bestseller'=>'badge-bestseller','new'=>'badge-new','limited'=>'badge-limited','hotdeal'=>'badge-hot'];
$badgeLabel = ['popular'=>'Popular','bestseller'=>'Best Seller','new'=>'New','limited'=>'Limited','hotdeal'=>'Hot Deal'];

$seoTitle = $cfg('seo_meta_title', 'ASB Tours | Explore the Beauty of Sri Lanka');
$seoDesc  = $cfg('seo_meta_desc',  'ASB Tours, Discover the Pearl of the Indian Ocean. Expertly crafted Sri Lanka tour packages.');
$seoCanonical = absolute_site_url('/');
$seoImage = $cfg('seo_image', '') ?: ($cfg('site_logo', '') ?: 'assets/images/logo.png');
$seoSchemas = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'TravelAgency',
        'name' => $cfg('site_name', 'ASB Tours'),
        'url' => $seoCanonical,
        'logo' => absolute_site_url(public_asset_url($seoImage)),
        'image' => absolute_site_url(public_asset_url($seoImage)),
        'telephone' => $cfg('contact_phone', ''),
        'email' => $cfg('contact_email', ''),
        'address' => $cfg('contact_address', ''),
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $cfg('site_name', 'ASB Tours'),
        'url' => $seoCanonical,
        'description' => $seoDesc,
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
    <?php include __DIR__ . '/assets/php/site-meta.php'; ?>
    <?php nt_render_seo_tags([
        'title' => $seoTitle,
        'description' => $seoDesc,
        'canonical' => $seoCanonical,
        'image' => public_asset_url($seoImage),
        'type' => 'website',
        'site_name' => $cfg('site_name', 'ASB Tours'),
        'structured_data' => $seoSchemas,
    ]); ?>
    <title><?= htmlspecialchars($seoTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <link rel="stylesheet" href="assets/css/variables.css?v=<?= $assetVersion ?>">
    <?php include __DIR__ . '/assets/php/site-theme.php'; ?>
    <link rel="stylesheet" href="assets/css/base.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/hero.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/about.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/services.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/packages.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/partners.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/destinations.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/gallery.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/reviews.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/custom-tour.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/contact.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/footer.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= $assetVersion ?>">
    <?php if ($cfg('google_analytics')): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($cfg('google_analytics')) ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= htmlspecialchars($cfg('google_analytics')) ?>');</script>
    <?php endif; ?>
    <?php if ($turnstileEnabled): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>

    <div id="navbar-placeholder"></div>

    <!-- HERO SLIDER -->
    <section id="hero" class="hero-section">
        <div class="swiper hero-swiper">
            <div class="swiper-wrapper">

            <?php
            function renderHeroHeading(string $heading): string {
                if (strpos($heading, '|') !== false) {
                    [$main, $accent] = array_map('trim', explode('|', $heading, 2));
                    return htmlspecialchars($main) . ' <span>' . htmlspecialchars($accent) . '</span>';
                }
                return htmlspecialchars($heading);
            }
            ?>
            <?php if (!empty($banners)): ?>
                <?php foreach ($banners as $index => $b): ?>
                <?php $heroTag = $index === 0 ? 'h1' : 'h2'; ?>
                <div class="swiper-slide hero-slide"
                     style="background-image: url('<?= htmlspecialchars($b['image_path'] ? site_url($b['image_path']) : 'assets/images/hero/slide-1.jpg') ?>')">
                    <div class="hero-overlay"></div>
                    <div class="container hero-content">
                        <span class="hero-badge"><?= htmlspecialchars($b['badge_text'] ?: $cfg('site_name', 'ASB Tours')) ?></span>
                        <<?= $heroTag ?>><?= renderHeroHeading($b['heading']) ?></<?= $heroTag ?>>
                        <?php if ($b['subheading']): ?>
                        <p><?= htmlspecialchars($b['subheading']) ?></p>
                        <?php endif; ?>
                        <div class="hero-btns">
                            <a href="<?= htmlspecialchars($b['btn_link'] ?: '#packages') ?>" class="btn btn-primary">
                                <i class="fa fa-compass"></i> <?= htmlspecialchars($b['btn_label'] ?: 'Explore Packages') ?>
                            </a>
                            <a href="#contact" class="btn btn-outline">
                                <i class="fa fa-phone"></i> Contact Us
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php else: ?>
                <!-- Fallback static slides -->
                <div class="swiper-slide hero-slide" style="background-image: url('assets/images/hero/slide-1.jpg')">
                    <div class="hero-overlay"></div>
                    <div class="container hero-content">
                        <span class="hero-badge">Welcome to ASB Tours</span>
                        <h1>Discover the Pearl of <span>the Indian Ocean</span></h1>
                        <p>Explore Sri Lanka's ancient wonders, golden beaches, lush tea hills, and incredible wildlife with our expertly crafted tour packages.</p>
                        <div class="hero-btns">
                            <a href="#packages" class="btn btn-primary"><i class="fa fa-compass"></i> Explore Packages</a>
                            <a href="#contact" class="btn btn-outline"><i class="fa fa-phone"></i> Contact Us</a>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide hero-slide" style="background-image: url('assets/images/hero/slide-2.jpg')">
                    <div class="hero-overlay"></div>
                    <div class="container hero-content">
                        <span class="hero-badge">Sun, Sand &amp; Serenity</span>
                        <h2>Sri Lanka's Most <span>Stunning Beaches</span></h2>
                        <p>From the whale-watching shores of Mirissa to the surf breaks of Arugam Bay, Sri Lanka's coastline is truly paradise found.</p>
                        <div class="hero-btns">
                            <a href="#packages" class="btn btn-primary"><i class="fa fa-suitcase"></i> View Packages</a>
                            <a href="#about" class="btn btn-outline"><i class="fa fa-info-circle"></i> About Us</a>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide hero-slide" style="background-image: url('assets/images/hero/slide-3.jpg')">
                    <div class="hero-overlay"></div>
                    <div class="container hero-content">
                        <span class="hero-badge">Custom Sri Lanka Tours</span>
                        <h2>Your Island Journey <span>Crafted for You</span></h2>
                        <p>From the misty peaks of Ella to the sacred temples of Kandy, let us design your perfect Sri Lanka itinerary from scratch.</p>
                        <div class="hero-btns">
                            <a href="#custom-tour" class="btn btn-primary"><i class="fa fa-map"></i> Plan My Tour</a>
                            <a href="#destinations" class="btn btn-outline"><i class="fa fa-globe"></i> Destinations</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            </div>
            <div class="swiper-pagination hero-pagination"></div>
            <div class="swiper-button-next hero-next"></div>
            <div class="swiper-button-prev hero-prev"></div>
        </div>
        <div class="scroll-indicator" title="Scroll down"></div>
    </section>

    <!-- ABOUT US-->
    <section id="about" class="about-section section-pad">
        <div class="container">
            <div class="about-grid">
                <div class="about-image" data-aos="fade-right">
                    <img src="<?= htmlspecialchars($cfg('about_image') ? public_asset_url($cfg('about_image')) : 'assets/images/destinations/sigiriya.jpg') ?>"
                         alt="<?= htmlspecialchars($cfg('site_name','ASB Tours')) ?>, Sri Lanka Travel Specialists">
                    <div class="about-badge">
                        <span class="badge-number"><?= htmlspecialchars($cfg('about_years','10+')) ?></span>
                        <span class="badge-text">Years of Experience</span>
                    </div>
                </div>
                <div class="about-content" data-aos="fade-left">
                    <div class="section-tag">Who We Are</div>
                    <h2 class="section-title">
                        <?= htmlspecialchars($cfg('about_heading', "Sri Lanka's Trusted")) ?>
                        <?php if ($cfg('about_heading_accent', 'Travel Specialists')): ?>
                        <span><?= htmlspecialchars($cfg('about_heading_accent', 'Travel Specialists')) ?></span>
                        <?php endif; ?>
                    </h2>
                    <p><?= nl2br(htmlspecialchars($cfg('about_description', "At ASB Tours, we are passionate local travel experts dedicated to showcasing the very best of Sri Lanka, the Pearl of the Indian Ocean."))) ?></p>
                    <?php if ($cfg('about_mission')): ?>
                    <p><?= nl2br(htmlspecialchars($cfg('about_mission'))) ?></p>
                    <?php endif; ?>
                    <div class="about-stats">
                        <div class="stat-item">
                            <span class="stat-number" data-count="<?= (int)preg_replace('/\D/','', $cfg('about_tours_count','800')) ?>"><?= (int)preg_replace('/\D/','', $cfg('about_tours_count','800')) ?></span><span class="stat-plus">+</span>
                            <span class="stat-label">Tours Done</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" data-count="<?= (int)preg_replace('/\D/','', $cfg('about_clients_count','3500')) ?>"><?= (int)preg_replace('/\D/','', $cfg('about_clients_count','3500')) ?></span><span class="stat-plus">+</span>
                            <span class="stat-label">Happy Guests</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" data-count="<?= (int)preg_replace('/\D/','', $cfg('about_destinations','25')) ?>"><?= (int)preg_replace('/\D/','', $cfg('about_destinations','25')) ?></span><span class="stat-plus">+</span>
                            <span class="stat-label">Destinations</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" data-count="<?= (int)preg_replace('/\D/','', $cfg('about_awards','12')) ?>"><?= (int)preg_replace('/\D/','', $cfg('about_awards','12')) ?></span><span class="stat-plus">+</span>
                            <span class="stat-label">Awards Won</span>
                        </div>
                    </div>
                    <a href="#contact" class="btn btn-primary"><i class="fa fa-envelope"></i> Get In Touch</a>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES -->
    <section id="services" class="services-section section-pad bg-light">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-tag">What We Do</div>
                <h2 class="section-title">Our <span>Services</span></h2>
                <p>Everything you need for a seamless, memorable Sri Lanka journey</p>
            </div>
            <?php
            $coreServices = $pdo->query("SELECT * FROM services WHERE type='core' AND is_active=1 ORDER BY sort_order, id LIMIT 6")->fetchAll();
            if (!empty($coreServices)):
            ?>
            <div class="services-grid">
                <?php foreach ($coreServices as $i => $sv): $delay = ($i+1)*50; ?>
                <div class="service-card" data-aos="fade-up" data-aos-delay="<?= $delay ?>" data-num="<?= str_pad($i+1,2,'0',STR_PAD_LEFT) ?>">
                    <div class="service-icon"><i class="fa-solid <?= htmlspecialchars($sv['icon_class']) ?>"></i></div>
                    <h3><?= htmlspecialchars($sv['title']) ?></h3>
                    <p><?= htmlspecialchars(mb_substr($sv['description'] ?? '', 0, 160)) ?></p>
                    <a href="pages/services.php" class="service-link">Learn More <i class="fa fa-arrow-right"></i></a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="services-grid">
                <div class="service-card" data-aos="fade-up" data-aos-delay="50" data-num="01">
                    <div class="service-icon"><i class="fa-solid fa-plane-departure"></i></div>
                    <h3>Flight Booking</h3>
                    <p>We secure the best flight deals from Colombo (BIA) and major international hubs, making your journey to Sri Lanka smooth and affordable.</p>
                    <a href="pages/services.php" class="service-link">Learn More <i class="fa fa-arrow-right"></i></a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="100" data-num="02">
                    <div class="service-icon"><i class="fa-solid fa-hotel"></i></div>
                    <h3>Hotel &amp; Resort Booking</h3>
                    <p>From boutique colonial villas in Galle to luxury beach resorts in Bentota, we book the finest Sri Lankan accommodations for every budget.</p>
                    <a href="pages/services.php" class="service-link">Learn More <i class="fa fa-arrow-right"></i></a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="150" data-num="03">
                    <div class="service-icon"><i class="fa-solid fa-user-tie"></i></div>
                    <h3>Expert Local Guides</h3>
                    <p>Our certified Sri Lankan guides bring Sigiriya, Kandy, and Galle to life with insider knowledge, authentic stories, and deep local culture.</p>
                    <a href="pages/services.php" class="service-link">Learn More <i class="fa fa-arrow-right"></i></a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="200" data-num="04">
                    <div class="service-icon"><i class="fa-solid fa-passport"></i></div>
                    <h3>Visa Assistance</h3>
                    <p>We guide you through Sri Lanka's ETA and visa-on-arrival process so you can focus on packing, we'll handle the paperwork.</p>
                    <a href="pages/services.php" class="service-link">Learn More <i class="fa fa-arrow-right"></i></a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="250" data-num="05">
                    <div class="service-icon"><i class="fa-solid fa-car-side"></i></div>
                    <h3>Island-Wide Transfers</h3>
                    <p>Air-conditioned vehicles with experienced drivers for comfortable transfers between Colombo, Kandy, Ella, Mirissa, and beyond, 24/7.</p>
                    <a href="pages/services.php" class="service-link">Learn More <i class="fa fa-arrow-right"></i></a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="300" data-num="06">
                    <div class="service-icon"><i class="fa-solid fa-people-group"></i></div>
                    <h3>Group Tours</h3>
                    <p>Explore Sri Lanka's highlights with like-minded travellers on our curated group tours, cultural, wildlife, beach, and adventure packages available.</p>
                    <a href="pages/services.php" class="service-link">Learn More <i class="fa fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
            <div class="section-cta" data-aos="fade-up">
                <a href="pages/services.php" class="btn btn-primary">View All Services</a>
            </div>
        </div>
    </section>

    <!-- PACKAGES SLIDER -->
    <section id="packages" class="packages-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-tag">What We Offer</div>
                <h2 class="section-title">Our Sri Lanka <span>Packages</span></h2>
                <p>Handpicked island experiences for every type of traveller</p>
            </div>
            <div class="swiper-outer" data-aos="fade-up">
            <div class="swiper packages-swiper">
                <div class="swiper-wrapper">
                <?php if (!empty($packages)): ?>
                    <?php foreach ($packages as $p):
                        $pImg   = $p['cover_image'] ? site_url($p['cover_image']) : 'assets/images/packages/cultural-triangle.jpg';
                        $pHasPrice = $p['price'] !== null && $p['price'] !== '';
                        $pPrice = $pHasPrice ? number_format((float)$p['price'], 0) : null;
                        $pBadge = $p['badge'] ?? '';
                        $pRat   = $p['rating'] ? number_format((float)$p['rating'], 1) : null;
                        $pIncs  = $p['inclusions'] ? array_slice(array_filter(array_map('trim', explode("\n", $p['inclusions']))), 0, 3) : [];
                    ?>
                    <div class="swiper-slide">
                        <div class="package-card">
                            <div class="package-image">
                                <img src="<?= htmlspecialchars($pImg) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                                <?php if ($pBadge && isset($badgeLabel[$pBadge])): ?>
                                <span class="package-badge"><?= $badgeLabel[$pBadge] ?></span>
                                <?php endif; ?>
                                <span class="package-price"><?= $pPrice ? 'From $' . $pPrice : 'Contact Us' ?></span>
                            </div>
                            <div class="package-body">
                                <div class="package-meta">
                                    <?php if ($p['duration']): ?>
                                    <span><i class="fa fa-clock"></i> <?= htmlspecialchars($p['duration']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($p['group_size']): ?>
                                    <span><i class="fa fa-users"></i> <?= htmlspecialchars($p['group_size']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($pRat): ?>
                                    <span><i class="fa fa-star"></i> <?= $pRat ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3><?= htmlspecialchars($p['title']) ?></h3>
                                <p><?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 150)) ?></p>
                                <?php if (!empty($pIncs)): ?>
                                <div class="package-highlights">
                                    <?php foreach ($pIncs as $inc): ?>
                                    <span><i class="fa fa-check"></i> <?= htmlspecialchars($inc) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <a href="pages/package-detail.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-primary btn-full">View Package</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
                <div class="swiper-pagination packages-pagination"></div>
            </div>
            <div class="swiper-button-prev packages-prev"></div>
            <div class="swiper-button-next packages-next"></div>
            </div>
            <div class="section-cta" data-aos="fade-up">
                <a href="pages/packages.php" class="btn btn-outline-primary">View All Packages</a>
            </div>
        </div>
    </section>

    <!-- PARTNERS SLIDER (static, no DB needed) -->
    <section id="partners" class="partners-section">
        <div class="container">
            <div class="section-header light" data-aos="fade-up">
                <div class="section-tag light">Our Network</div>
                <h2 class="section-title">Trusted <span style="color:var(--secondary)">Partners</span></h2>
                <p>We partner with Sri Lanka's finest hotels, airlines, and tourism operators for exceptional experiences.</p>
            </div>
            <div class="swiper partners-swiper" data-aos="fade-up">
                <div class="swiper-wrapper">
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-plane"></i><span>SriLankan Airlines</span></div></div>
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-hotel"></i><span>Cinnamon Hotels</span></div></div>
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-hotel"></i><span>Jetwing Hotels</span></div></div>
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-globe"></i><span>Sri Lanka Tourism</span></div></div>
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-umbrella-beach"></i><span>Aitken Spence</span></div></div>
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-car"></i><span>Malkey Rent a Car</span></div></div>
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-ship"></i><span>Quickshaws Tours</span></div></div>
                    <div class="swiper-slide partner-slide"><div class="partner-logo"><i class="fa-solid fa-mountain"></i><span>Browns Tourism</span></div></div>
                </div>
            </div>
        </div>
    </section>

    <!-- DESTINATIONS / BLOG -->
    <section id="destinations" class="destinations-section section-pad bg-light">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-tag">Explore</div>
                <h2 class="section-title">Popular <span>Destinations</span></h2>
                <p>Discover Sri Lanka's most breathtaking and beloved travel spots</p>
            </div>
            <div class="swiper-outer" data-aos="fade-up">
            <div class="swiper destinations-swiper">
                <div class="swiper-wrapper">
                <?php if (!empty($blogs)): ?>
                    <?php foreach ($blogs as $b):
                        $bImg = $b['cover_image'] ? site_url($b['cover_image']) : 'assets/images/destinations/sigiriya.jpg';
                        $bDate = $b['published_at'] ? date('M j, Y', strtotime($b['published_at'])) : '';
                    ?>
                    <div class="swiper-slide">
                        <div class="destination-card">
                            <div class="destination-image">
                                <img src="<?= htmlspecialchars($bImg) ?>" alt="<?= htmlspecialchars($b['title']) ?>">
                                <div class="destination-overlay">
                                    <?php if ($b['category']): ?>
                                    <span class="dest-category"><?= htmlspecialchars($b['category']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="destination-body">
                                <div class="dest-meta">
                                    <?php if ($bDate): ?><span><i class="fa fa-calendar"></i> <?= $bDate ?></span><?php endif; ?>
                                    <?php if (!empty($b['author'])): ?><span><i class="fa fa-user"></i> <?= htmlspecialchars($b['author']) ?></span><?php endif; ?>
                                </div>
                                <h3><?= htmlspecialchars($b['title']) ?></h3>
                                <p><?= htmlspecialchars(mb_substr(strip_tags($b['excerpt'] ?? $b['content'] ?? ''), 0, 130)) ?>…</p>
                                <a href="pages/blog-detail.php?slug=<?= urlencode($b['slug']) ?>" class="dest-link">Read More <i class="fa fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Static fallback destinations -->
                    <?php
                    $staticDests = [
                        ['img'=>'sigiriya','cat'=>'Ancient','date'=>'Mar 10, 2025','loc'=>'Sigiriya','title'=>'Sigiriya: Climbing Sri Lanka\'s Ancient Sky Fortress','desc'=>'Ascend the iconic 200-metre volcanic rock crowned with 5th-century palace ruins and breathtaking frescoes, a true UNESCO World Heritage marvel.'],
                        ['img'=>'mirissa','cat'=>'Beach','date'=>'Feb 15, 2025','loc'=>'Mirissa','title'=>'Mirissa: Whale Watching &amp; Sri Lanka\'s Finest Beach','desc'=>'Watch blue whales breach in the Indian Ocean, surf at Weligama, and unwind on crescent-shaped Mirissa Bay, Sri Lanka\'s south coast gem.'],
                        ['img'=>'ella','cat'=>'Hill Country','date'=>'Jan 20, 2025','loc'=>'Ella','title'=>'Ella: Nine Arches Bridge &amp; Sri Lanka\'s Hill Country','desc'=>'Ride the world\'s most scenic train through lush tea estates, hike Little Adam\'s Peak, and capture the iconic Nine Arches Bridge at golden hour.'],
                        ['img'=>'kandy','cat'=>'Culture','date'=>'Dec 8, 2024','loc'=>'Kandy','title'=>'Kandy: The Sacred City &amp; Temple of the Tooth Relic','desc'=>'Sri Lanka\'s cultural heartland, home to the revered Temple of the Tooth, traditional Kandyan dance, and the spectacular Esala Perahera festival.'],
                        ['img'=>'yala','cat'=>'Wildlife','date'=>'Nov 25, 2024','loc'=>'Yala','title'=>'Yala Safari: Sri Lanka\'s Leopards &amp; Wild Elephants','desc'=>'Track elusive Sri Lankan leopards and massive elephant herds through Yala National Park, one of the world\'s best big cat destinations.'],
                    ];
                    foreach ($staticDests as $d): ?>
                    <div class="swiper-slide">
                        <div class="destination-card">
                            <div class="destination-image">
                                <img src="assets/images/destinations/<?= $d['img'] ?>.jpg" alt="<?= $d['title'] ?>">
                                <div class="destination-overlay"><span class="dest-category"><?= $d['cat'] ?></span></div>
                            </div>
                            <div class="destination-body">
                                <div class="dest-meta">
                                    <span><i class="fa fa-calendar"></i> <?= $d['date'] ?></span>
                                    <span><i class="fa fa-map-marker-alt"></i> <?= $d['loc'] ?></span>
                                </div>
                                <h3><?= $d['title'] ?></h3>
                                <p><?= $d['desc'] ?></p>
                                <a href="pages/blog.php" class="dest-link">Read More <i class="fa fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
                <div class="swiper-pagination destinations-pagination"></div>
            </div>
            <div class="swiper-button-prev destinations-prev"></div>
            <div class="swiper-button-next destinations-next"></div>
            </div>
            <div class="section-cta" data-aos="fade-up">
                <a href="pages/blog.php" class="btn btn-outline-primary">View All Destinations</a>
            </div>
        </div>
    </section>

    <!-- PHOTO GALLERY -->
    <section id="gallery" class="gallery-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-tag">Our Gallery</div>
                <h2 class="section-title">Sri Lanka Through <span>Our Lens</span></h2>
                <p>Moments captured across the island, from misty hill tops to golden shores</p>
            </div>
            <div class="gallery-grid" data-aos="fade-up">
            <?php if (!empty($gallery)): ?>
                <?php foreach ($gallery as $g): ?>
                <div class="gallery-item">
                    <img src="<?= htmlspecialchars(site_url($g['image_path'])) ?>"
                         alt="<?= htmlspecialchars($g['caption'] ?? 'Sri Lanka Gallery') ?>">
                    <div class="gallery-overlay"><i class="fa fa-expand"></i><span>View</span></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php for ($gi = 1; $gi <= 5; $gi++): ?>
                <div class="gallery-item">
                    <img src="assets/images/gallery/gallery-<?= $gi ?>.jpg" alt="Sri Lanka Gallery">
                    <div class="gallery-overlay"><i class="fa fa-expand"></i><span>View</span></div>
                </div>
                <?php endfor; ?>
            <?php endif; ?>
            </div>
            <div class="section-cta" data-aos="fade-up">
                <a href="pages/gallery.php" class="btn btn-outline-primary">
                    <i class="fa fa-images"></i> View Full Gallery
                </a>
            </div>
        </div>
    </section>

    <div class="gallery-lightbox" id="galleryLightbox">
        <span class="lightbox-close" id="lightboxClose"><i class="fa fa-times"></i></span>
        <img src="" alt="Gallery Image" id="lightboxImg">
    </div>

    <!-- REVIEWS -->
    <section id="reviews" class="reviews-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-label">HAPPY TRAVELERS</span>
                <h2 class="section-title">What Our <span>Travelers Say</span></h2>
                <p>Real experiences from real adventurers who explored Sri Lanka with ASB Tours.</p>
            </div>

            <div class="reviews-layout" data-aos="fade-up">

                <!-- LEFT: auto-scrolling review cards -->
                <div class="rv-scroll-col">
                    <div class="rv-fade-top"></div>
                    <div class="rv-scroll-wrap">
                        <div class="rv-scroll-track" id="rvTrack">
                            <?php
                            $staticRevs = [
                                ['stars'=>5,'text'=>'Absolutely breathtaking! Our guide took us through Sigiriya at sunrise and the views were unlike anything I\'ve ever seen. Every detail was perfectly arranged. ASB Tours exceeded every expectation.','tour'=>'Cultural Triangle Explorer','name'=>'Sarah Mitchell','loc'=>'London, United Kingdom','av'=>1,'init'=>'SM'],
                                ['stars'=>5,'text'=>'The beach package was pure paradise. Mirissa, Unawatuna, Galle, all stunning. The team handled everything flawlessly from airport pickup to hotel check-ins. I\'ll be booking again for sure!','tour'=>'South Coast Beach Escape','name'=>'David Chen','loc'=>'Singapore','av'=>2,'init'=>'DC'],
                                ['stars'=>5,'text'=>'The Ella train ride was magical, misty mountains, endless tea estates, and the Nine Arches Bridge. Our guide knew every hidden viewpoint. A truly immersive Sri Lankan experience.','tour'=>'Hill Country & Tea Trails','name'=>'Emma van den Berg','loc'=>'Amsterdam, Netherlands','av'=>3,'init'=>'EB'],
                                ['stars'=>5,'text'=>'Yala National Park was incredible, we spotted leopards, elephants, and crocodiles all in one morning! The jeep safari was well organised and our naturalist guide was exceptionally knowledgeable.','tour'=>'Yala Safari Adventure','name'=>'James O\'Brien','loc'=>'Sydney, Australia','av'=>4,'init'=>'JO'],
                                ['stars'=>5,'text'=>'Our honeymoon was absolutely perfect. The candlelit dinner by the beach, the luxury villa, whale watching at Mirissa, every moment was thoughtfully curated. We felt so special throughout the trip.','tour'=>'Sri Lanka Honeymoon Package','name'=>'Priya & Rohan Sharma','loc'=>'Mumbai, India','av'=>5,'init'=>'PS'],
                            ];
                            $displayRevs = !empty($reviews)
                                ? array_map(fn($rv) => [
                                    'stars' => (int)($rv['rating'] ?? 5),
                                    'text'  => $rv['review_text'] ?? '',
                                    'tour'  => $rv['tour_name'] ?? '',
                                    'name'  => $rv['name'],
                                    'loc'   => $rv['country'] ?? '',
                                    'av'    => (crc32($rv['name']) % 6) + 1,
                                    'init'  => strtoupper(substr($rv['name'],0,1) . (strpos($rv['name'],' ') ? substr($rv['name'],strpos($rv['name'],' ')+1,1) : '')),
                                  ], $reviews)
                                : $staticRevs;
                            // Duplicate for seamless loop
                            $loopRevs = array_merge($displayRevs, $displayRevs);
                            foreach ($loopRevs as $rv): ?>
                            <div class="rv-card">
                                <div class="review-stars">
                                    <?php for ($rs = 0; $rs < $rv['stars']; $rs++) echo '<i class="fa-solid fa-star"></i>'; ?>
                                    <?php for ($rs = $rv['stars']; $rs < 5; $rs++) echo '<i class="fa-regular fa-star"></i>'; ?>
                                </div>
                                <p class="review-text">"<?= htmlspecialchars($rv['text']) ?>"</p>
                                <?php if (!empty($rv['tour'])): ?>
                                <span class="review-tour"><i class="fa-solid fa-route"></i> <?= htmlspecialchars($rv['tour']) ?></span>
                                <?php endif; ?>
                                <div class="review-author">
                                    <div class="review-avatar av-<?= $rv['av'] ?>"><?= htmlspecialchars($rv['init']) ?></div>
                                    <div class="review-info">
                                        <h4><?= htmlspecialchars($rv['name']) ?></h4>
                                        <?php if (!empty($rv['loc'])): ?>
                                        <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($rv['loc']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="rv-fade-bottom"></div>
                </div>

                <!-- RIGHT: summary + form -->
                <div class="rv-form-col">
                    <div class="rv-form-card">
                        <!-- Stats bar -->
                        <div class="rv-stats-bar">
                            <div class="rv-score">
                                <span class="rv-score-num">4.9</span>
                                <div class="rv-score-stars">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star-half-stroke"></i>
                                </div>
                                <span class="rv-score-label">Average Rating</span>
                            </div>
                            <div class="rv-mini-stats">
                                <div class="rv-mini-stat"><strong>500+</strong><span>Reviews</span></div>
                                <div class="rv-mini-stat"><strong>98%</strong><span>Recommend</span></div>
                                <div class="rv-mini-stat"><strong><?= htmlspecialchars($cfg('about_clients_count','3,500+')) ?></strong><span>Travelers</span></div>
                            </div>
                        </div>

                        <div class="rv-form-divider"></div>

                        <!-- Form -->
                        <div class="rv-form-body">
                            <h3 class="rv-form-title"><i class="fa-solid fa-pen-to-square"></i> Share Your Experience</h3>
                            <p class="rv-form-sub">Traveled with us? Your review helps others plan their perfect Sri Lanka trip.</p>

                            <div id="reviewSuccess" class="rv-success" style="display:none;">
                                <i class="fa-solid fa-circle-check"></i>
                                <h4>Thank you for your review!</h4>
                                <p>It will appear on the site once approved by our team.</p>
                            </div>

                            <form id="reviewForm" novalidate>
                                <div class="rv-field-row">
                                    <div class="rv-field">
                                        <label>Your Name <span>*</span></label>
                                        <input type="text" name="name" placeholder="e.g. Sarah Mitchell" required>
                                    </div>
                                    <div class="rv-field">
                                        <label>Country</label>
                                        <input type="text" name="country" placeholder="e.g. United Kingdom">
                                    </div>
                                </div>
                                <div class="rv-field">
                                    <label>Rating <span>*</span></label>
                                    <div class="rv-star-picker" id="starPicker">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-solid fa-star review-star" data-val="<?= $i ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="rating" id="ratingInput" value="5">
                                </div>
                                <div class="rv-field">
                                    <label>Your Review <span>*</span></label>
                                    <textarea name="review_text" rows="4" placeholder="Tell us about your experience traveling with ASB Tours..." required></textarea>
                                </div>
                                <?php if ($turnstileEnabled): ?>
                                <div class="rv-field">
                                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
                                </div>
                                <?php endif; ?>
                                <div id="reviewError" class="rv-error" style="display:none;"></div>
                                <button type="submit" class="btn btn-primary rv-submit">
                                    <i class="fa-solid fa-paper-plane"></i> Submit Review
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- CUSTOMIZED TOUR FORM -->
    <section id="custom-tour" class="custom-tour-section">
        <div class="custom-tour-bg"></div>
        <div class="custom-tour-overlay"></div>
        <div class="container">
            <div class="custom-tour-grid">
                <div class="custom-tour-content" data-aos="fade-right">
                    <div class="section-tag light">Plan Your Sri Lanka Trip</div>
                    <h2>Need a Customized Sri Lanka Tour?</h2>
                    <p>Tell us your dream Sri Lanka experience, beaches, safaris, temples, tea trails, or everything in between, and we'll craft your perfect itinerary from scratch.</p>
                    <ul class="custom-benefits">
                        <li><i class="fa fa-check-circle"></i> 100% Personalized Itinerary</li>
                        <li><i class="fa fa-check-circle"></i> 24/7 Island-Wide Support</li>
                        <li><i class="fa fa-check-circle"></i> Best Price Guarantee</li>
                        <li><i class="fa fa-check-circle"></i> Flexible Date Changes</li>
                        <li><i class="fa fa-check-circle"></i> No Hidden Fees</li>
                    </ul>
                </div>
                <div class="custom-tour-form" data-aos="fade-left">
                    <h3><i class="fa fa-map-marked-alt" style="color:var(--primary);margin-right:8px"></i>Tell Us Your Dream</h3>
                    <form id="customTourForm" novalidate>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" id="ctName" placeholder="Your Full Name" required>
                            </div>
                            <div class="form-group">
                                <input type="tel" id="ctPhone" placeholder="Phone Number" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="text" id="ctDests" placeholder="Preferred Destinations (e.g. Sigiriya, Ella, Mirissa)">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="date" id="ctDate">
                            </div>
                            <div class="form-group">
                                <select id="ctPax">
                                    <option value="">Number of Travellers</option>
                                    <option>1 Person</option><option>2 People</option>
                                    <option>3–5 People</option><option>6–10 People</option><option>10+ People</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <textarea id="ctNotes" placeholder="Any special requirements or preferences?" rows="3"></textarea>
                        </div>
                        <?php if ($turnstileEnabled): ?>
                        <div class="form-group">
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fa fa-paper-plane"></i> Send My Request
                        </button>
                        <div id="ctMsg" style="display:none;margin-top:1rem;padding:.75rem;border-radius:8px;text-align:center;"></div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT -->
    <?php
    $rawMap = $cfg('maps_link', '');
    // Accept only proper embed URLs (containing /maps/embed)
    // If admin pastes a regular Google Maps URL, show the default embed
    if ($rawMap && strpos($rawMap, '/maps/embed') !== false) {
        $mapSrc = $rawMap;
    } else {
        $mapSrc = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d253682.4501000206!2d79.7728800!3d6.9218376!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae253d10f7a7003%3A0x320b2e4d32d3838d!2sColombo%2C%20Sri%20Lanka!5e0!3m2!1sen!2s!4v1710000000000';
    }
    ?>
    <section id="contact" class="contact-section section-pad">
        <div class="container">

            <!-- Header -->
            <div class="section-header" data-aos="fade-up">
                <div class="section-tag">Get In Touch</div>
                <h2 class="section-title">Contact <span>Us</span></h2>
                <p>We'd love to help you plan your Sri Lanka adventure, reach out and we'll respond within 24 hours.</p>
            </div>

            <!-- Info cards row -->
            <div class="contact-cards-row" data-aos="fade-up">
                <?php if ($cfg('contact_address')): ?>
                <div class="contact-card">
                    <div class="cc-icon"><i class="fa fa-map-marker-alt"></i></div>
                    <div class="cc-body">
                        <h4>Our Office</h4>
                        <p><?= nl2br(htmlspecialchars($cfg('contact_address'))) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($cfg('contact_phone')): ?>
                <div class="contact-card">
                    <div class="cc-icon"><i class="fa fa-phone"></i></div>
                    <div class="cc-body">
                        <h4>Phone</h4>
                        <p><a href="tel:<?= htmlspecialchars($cfg('contact_phone')) ?>"><?= htmlspecialchars($cfg('contact_phone')) ?></a></p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($cfg('contact_whatsapp')): ?>
                <div class="contact-card">
                    <div class="cc-icon cc-icon-wa"><i class="fab fa-whatsapp"></i></div>
                    <div class="cc-body">
                        <h4>WhatsApp</h4>
                        <p><a href="https://wa.me/<?= preg_replace('/\D/','',$cfg('contact_whatsapp')) ?>" target="_blank"><?= htmlspecialchars($cfg('contact_whatsapp')) ?></a></p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($cfg('contact_email')): ?>
                <div class="contact-card">
                    <div class="cc-icon"><i class="fa fa-envelope"></i></div>
                    <div class="cc-body">
                        <h4>Email Us</h4>
                        <p><a href="mailto:<?= htmlspecialchars($cfg('contact_email')) ?>"><?= htmlspecialchars($cfg('contact_email')) ?></a></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Map + Form -->
            <div class="contact-bottom" data-aos="fade-up">

                <!-- Map -->
                <div class="contact-map-wrap">
                    <iframe src="<?= htmlspecialchars($mapSrc) ?>"
                            width="100%" height="100%" style="border:0;" allowfullscreen
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="Our Location"></iframe>
                </div>

                <!-- Form -->
                <div class="contact-form-wrap">
                    <div class="cf-header">
                        <h3><i class="fa fa-paper-plane"></i> Send Us a Message</h3>
                        <p>Fill in the form and our team will get back to you within 24 hours.</p>
                    </div>
                    <form id="contactForm" class="contact-form" novalidate>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <input type="text" id="cfName" placeholder="John Doe" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address <span>*</span></label>
                                <input type="email" id="cfEmail" placeholder="john@example.com" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" id="cfPhone" placeholder="+94 77 123 4567">
                            </div>
                            <div class="form-group">
                                <label>Subject</label>
                                <select id="cfSubject">
                                    <option>Package Inquiry</option>
                                    <option>Custom Tour Request</option>
                                    <option>Booking Support</option>
                                    <option>General Question</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Message <span>*</span></label>
                            <textarea id="cfMessage" placeholder="Tell us about your dream Sri Lanka trip..." rows="4" required></textarea>
                        </div>
                        <?php if ($turnstileEnabled): ?>
                        <div class="form-group">
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fa fa-paper-plane"></i> Send Message
                        </button>
                        <div id="cfMsg" style="display:none;margin-top:1rem;padding:.75rem;border-radius:8px;text-align:center;font-size:.9rem;"></div>
                    </form>
                </div>

            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <div id="footer-placeholder"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/components.js?v=8"></script>
    <script src="assets/js/navbar.js"></script>
    <script src="assets/js/animations.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    // AOS
    AOS.init({ duration: 700, once: true });

    // Swipers
    new Swiper('.hero-swiper', {
        loop: true, autoplay: { delay: 5000, disableOnInteraction: false },
        pagination: { el: '.hero-pagination', clickable: true },
        navigation: { nextEl: '.hero-next', prevEl: '.hero-prev' },
        effect: 'fade', fadeEffect: { crossFade: true }
    });
    new Swiper('.packages-swiper', {
        loop: true, slidesPerView: 1, spaceBetween: 24,
        pagination: { el: '.packages-pagination', clickable: true },
        navigation: { nextEl: '.packages-next', prevEl: '.packages-prev' },
        breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } }
    });
    new Swiper('.partners-swiper', {
        loop: true, slidesPerView: 2, spaceBetween: 20,
        autoplay: { delay: 2000, disableOnInteraction: false },
        breakpoints: { 480: { slidesPerView: 3 }, 768: { slidesPerView: 4 }, 1024: { slidesPerView: 5 } }
    });
    new Swiper('.destinations-swiper', {
        loop: true, slidesPerView: 1, spaceBetween: 24,
        pagination: { el: '.destinations-pagination', clickable: true },
        navigation: { nextEl: '.destinations-next', prevEl: '.destinations-prev' },
        breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } }
    });
    new Swiper('.reviews-swiper', {
        loop: true, slidesPerView: 1, spaceBetween: 24,
        pagination: { el: '.reviews-pagination', clickable: true },
        navigation: { nextEl: '.reviews-next', prevEl: '.reviews-prev' },
        autoplay: { delay: 4000, disableOnInteraction: false },
        breakpoints: { 768: { slidesPerView: 2 }, 1200: { slidesPerView: 3 } }
    });

    // Gallery lightbox
    document.querySelectorAll('.gallery-item').forEach(item => {
        item.addEventListener('click', () => {
            const img = item.querySelector('img');
            document.getElementById('lightboxImg').src = img.src;
            document.getElementById('galleryLightbox').classList.add('active');
        });
    });
    document.getElementById('lightboxClose').addEventListener('click', () => {
        document.getElementById('galleryLightbox').classList.remove('active');
    });
    document.getElementById('galleryLightbox').addEventListener('click', e => {
        if (e.target === e.currentTarget) e.currentTarget.classList.remove('active');
    });

    // Stat counter animation
    const counters = document.querySelectorAll('.stat-number');
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.dataset.count) || 0;
                let count = 0;
                const step = Math.ceil(target / 60);
                const timer = setInterval(() => {
                    count = Math.min(count + step, target);
                    el.textContent = count.toLocaleString();
                    if (count >= target) clearInterval(timer);
                }, 20);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });
    counters.forEach(c => observer.observe(c));

    // Contact form 
    function showMsg(id, ok, text) {
        const el = document.getElementById(id);
        el.style.display = 'block';
        el.style.background = ok ? '#d1fae5' : '#fee2e2';
        el.style.color = ok ? '#065f46' : '#991b1b';
        el.textContent = text;
    }

    function getTurnstileToken(form) {
        const field = form.querySelector('[name="cf-turnstile-response"]');
        return field ? field.value : '';
    }

    function resetTurnstile(form) {
        if (!window.turnstile) return;
        const widget = form.querySelector('.cf-turnstile');
        if (widget) window.turnstile.reset(widget);
    }

    // Star picker 
    (function () {
        const stars   = document.querySelectorAll('#starPicker .review-star');
        const input   = document.getElementById('ratingInput');
        let current   = 5;
        function paint(val) {
            stars.forEach(s => s.style.color = +s.dataset.val <= val ? '#F7C948' : '#dde3ed');
        }
        paint(5);
        stars.forEach(s => {
            s.addEventListener('mouseenter', () => paint(+s.dataset.val));
            s.addEventListener('mouseleave', () => paint(current));
            s.addEventListener('click', () => { current = +s.dataset.val; input.value = current; paint(current); });
        });
    })();

    // Review scroll animation 
    (function () {
        const track = document.getElementById('rvTrack');
        if (!track) return;
        const totalCards = track.children.length; // doubled already
        const speed      = 40; // px per second
        let pos          = 0;
        let paused       = false;
        const halfH      = () => track.scrollHeight / 2;
        track.parentElement.addEventListener('mouseenter', () => paused = true);
        track.parentElement.addEventListener('mouseleave', () => paused = false);
        let last = null;
        function step(ts) {
            if (last !== null && !paused) {
                pos += speed * (ts - last) / 1000;
                if (pos >= halfH()) pos -= halfH();
                track.style.transform = `translateY(-${pos}px)`;
            }
            last = ts;
            requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    })();

    // Review form submit 
    document.getElementById('reviewForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn   = this.querySelector('button[type=submit]');
        const errEl = document.getElementById('reviewError');
        const name  = this.name.value.trim();
        const text  = this.review_text.value.trim();
        const turnstileToken = getTurnstileToken(this);
        errEl.style.display = 'none';
        if (!name || !text) { errEl.textContent = 'Please fill in your name and review.'; errEl.style.display = 'block'; return; }
        btn.disabled = true;
        try {
            const res  = await fetch('submit-review.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({
                    name,
                    country: this.country.value.trim(),
                    rating: this.rating.value,
                    review_text: text,
                    'cf-turnstile-response': turnstileToken,
                })
            });
            const json = await res.json();
            if (json.success) {
                this.style.display = 'none';
                document.getElementById('reviewSuccess').style.display = 'flex';
                resetTurnstile(this);
            } else {
                errEl.textContent = json.error || 'Something went wrong. Please try again.';
                errEl.style.display = 'block';
                resetTurnstile(this);
                btn.disabled = false;
            }
        } catch {
            errEl.textContent = 'Network error. Please try again.';
            errEl.style.display = 'block';
            resetTurnstile(this);
            btn.disabled = false;
        }
    });

    document.getElementById('contactForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type=submit]');
        btn.disabled = true;
        const turnstileToken = getTurnstileToken(this);
        const res = await fetch('submit-inquiry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name:    document.getElementById('cfName').value,
                email:   document.getElementById('cfEmail').value,
                phone:   document.getElementById('cfPhone').value,
                subject: document.getElementById('cfSubject').value,
                message: document.getElementById('cfMessage').value,
                'cf-turnstile-response': turnstileToken,
            })
        });
        const data = await res.json();
        if (data.success) {
            showMsg('cfMsg', true, 'Thank you! We\'ll get back to you within 24 hours.');
            this.reset();
            resetTurnstile(this);
        } else {
            showMsg('cfMsg', false, data.error || 'Something went wrong. Please try again.');
            resetTurnstile(this);
        }
        btn.disabled = false;
    });

    // Custom tour form 
    document.getElementById('customTourForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type=submit]');
        btn.disabled = true;
        const turnstileToken = getTurnstileToken(this);
        const dests = document.getElementById('ctDests').value;
        const date  = document.getElementById('ctDate').value;
        const pax   = document.getElementById('ctPax').value;
        const notes = document.getElementById('ctNotes').value;
        const msg   = [dests && 'Destinations: ' + dests, date && 'Travel Date: ' + date, pax && 'Travelers: ' + pax, notes].filter(Boolean).join('\n');
        const res = await fetch('submit-inquiry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name:    document.getElementById('ctName').value,
                phone:   document.getElementById('ctPhone').value,
                subject: 'Custom Tour Request',
                message: msg || 'Custom tour request',
                'cf-turnstile-response': turnstileToken,
            })
        });
        const data = await res.json();
        if (data.success) {
            showMsg('ctMsg', true, 'Request sent! Our team will contact you shortly.');
            this.reset();
            resetTurnstile(this);
        } else {
            showMsg('ctMsg', false, data.error || 'Something went wrong. Please try again.');
            resetTurnstile(this);
        }
        btn.disabled = false;
    });
    </script>

</body>
</html>




