<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/seo.php';
$pdo = getPDO();
if (file_exists(__DIR__ . '/../maintenance.flag')) { include __DIR__ . '/../maintenance.php'; exit; }

$s   = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $key, string $default = '') => (isset($s[$key]) && $s[$key] !== '') ? $s[$key] : $default;
$whatsapp = $cfg('contact_whatsapp');

// Gallery items 
$images    = $pdo->query("SELECT * FROM gallery WHERE is_active = 1 ORDER BY sort_order, id DESC")->fetchAll();
$useStatic = empty($images);

// Videos 
$videos = $pdo->query("SELECT * FROM gallery_videos WHERE is_active = 1 ORDER BY sort_order ASC, id DESC")->fetchAll();

// Categories present in DB
$dbCats = [];
foreach ($images as $img) {
    if ($img['category']) $dbCats[$img['category']] = true;
}

$catConfig = [
    'cultural'   => ['icon' => 'fa-landmark',        'label' => 'Cultural Sites'],
    'beach'      => ['icon' => 'fa-umbrella-beach',   'label' => 'Beaches'],
    'wildlife'   => ['icon' => 'fa-paw',              'label' => 'Wildlife'],
    'hillcountry'=> ['icon' => 'fa-mountain',         'label' => 'Hill Country'],
    'honeymoon'  => ['icon' => 'fa-heart',            'label' => 'Honeymoon'],
];

// Stats from settings
$totalPhotos      = $useStatic ? '500+' : count($images) . '+';
$destinations     = $cfg('about_destinations', '25+');
$clientsCount     = $cfg('about_clients_count', '3,500+');

// Static fallback items 
$staticImages = [
    ['image_path'=>'../assets/images/destinations/sigiriya.jpg',    'title'=>'Sigiriya Rock Fortress, Cultural Triangle', 'category'=>'cultural',    'layout'=>'tall'],
    ['image_path'=>'../assets/images/packages/cultural-triangle.jpg','title'=>'Cultural Triangle Explorer, Sri Lanka',    'category'=>'cultural',    'layout'=>'wide'],
    ['image_path'=>'../assets/images/gallery/gallery-2.jpg',         'title'=>'Golden Beach, Southern Coast Sri Lanka',   'category'=>'beach',       'layout'=>''],
    ['image_path'=>'../assets/images/destinations/kandy.jpg',        'title'=>'Kandy, Cultural Capital of Sri Lanka',    'category'=>'cultural',    'layout'=>''],
    ['image_path'=>'../assets/images/packages/beach-escape.jpg',     'title'=>'South Coast Beach Escape, Sri Lanka',      'category'=>'beach',       'layout'=>'wide'],
    ['image_path'=>'../assets/images/destinations/mirissa.jpg',      'title'=>'Mirissa Beach, Whale Watching Paradise',  'category'=>'beach',       'layout'=>''],
    ['image_path'=>'../assets/images/packages/hill-country.jpg',     'title'=>'Hill Country Tea Estates, Sri Lanka',      'category'=>'hillcountry', 'layout'=>'tall'],
    ['image_path'=>'../assets/images/gallery/gallery-3.jpg',         'title'=>'Ancient Temple, Sri Lanka',                'category'=>'cultural',    'layout'=>''],
    ['image_path'=>'../assets/images/packages/yala-safari.jpg',      'title'=>'Yala Safari, Leopards & Elephants',       'category'=>'wildlife',    'layout'=>'wide'],
    ['image_path'=>'../assets/images/destinations/yala.jpg',         'title'=>'Wild Elephants, Yala National Park',       'category'=>'wildlife',    'layout'=>''],
    ['image_path'=>'../assets/images/destinations/ella.jpg',         'title'=>'Ella, Nine Arches Bridge & Misty Mountains','category'=>'hillcountry','layout'=>''],
    ['image_path'=>'../assets/images/gallery/gallery-4.jpg',         'title'=>'Tea Plantation, Nuwara Eliya',             'category'=>'hillcountry', 'layout'=>''],
    ['image_path'=>'../assets/images/packages/honeymoon.jpg',        'title'=>'Romantic Sunset, Sri Lanka Honeymoon',    'category'=>'honeymoon',   'layout'=>'wide'],
    ['image_path'=>'../assets/images/gallery/gallery-5.jpg',         'title'=>'Tropical Coastline, Sri Lanka',            'category'=>'beach',       'layout'=>''],
    ['image_path'=>'../assets/images/hero/slide-2.jpg',              'title'=>'Ancient Ruins, Sri Lanka',                 'category'=>'cultural',    'layout'=>''],
    ['image_path'=>'../assets/images/gallery/gallery-6.jpg',         'title'=>'Luxury Beach Villa, Sri Lanka',            'category'=>'honeymoon',   'layout'=>''],
    ['image_path'=>'../assets/images/hero/slide-3.jpg',              'title'=>'Sri Lanka Coastal Paradise',               'category'=>'beach',       'layout'=>''],
    ['image_path'=>'../assets/images/gallery/gallery-1.jpg',         'title'=>'Sri Lanka Cultural Heritage',              'category'=>'cultural',    'layout'=>''],
    ['image_path'=>'../assets/images/hero/slide-1.jpg',              'title'=>'Sri Lanka Natural Wonders',                'category'=>'wildlife',    'layout'=>'wide'],
    ['image_path'=>'../assets/images/destinations/sigiriya.jpg',     'title'=>'Your ASB Tours Team',                 'category'=>'honeymoon',   'layout'=>''],
];

$displayImages = $useStatic ? $staticImages : $images;

// Assign layout classes for DB items in a repeating pattern
$layoutPattern = ['tall','wide','','','wide','','','tall','wide','',''];
$seoTitle = 'Photo Gallery | ASB Tours Sri Lanka';
$seoDesc = 'ASB Tours Sri Lanka Photo Gallery, Beaches, Cultural Sites, Wildlife Safaris, Hill Country & more. Browse stunning moments from our tours.';
$seoCanonical = absolute_site_url('pages/gallery.php');
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
            '@type' => 'ImageGallery',
            'name' => 'ASB Tours Sri Lanka Photo Gallery',
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
    <link rel="stylesheet" href="../assets/css/gallery-page.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

    <div id="navbar-placeholder"></div>

    <!-- PAGE HERO -->
    <section class="page-hero" style="background-image: url('../assets/images/gallery/gallery-1.jpg'); background-position: center 60%;">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <span>Gallery</span>
            </div>
            <h1>Sri Lanka Through <span>Our Lens</span></h1>
            <p>Real moments from real journeys, every photo tells a story worth living.</p>
            <div style="display:flex; gap:22px; flex-wrap:wrap; margin-top:22px;">
                <span style="display:flex; align-items:center; gap:7px; color:rgba(255,255,255,0.9); font-size:0.88rem; font-weight:500;">
                    <i class="fa-solid fa-images" style="color:var(--accent);"></i> <?= htmlspecialchars($totalPhotos) ?> Photos
                </span>
                <span style="display:flex; align-items:center; gap:7px; color:rgba(255,255,255,0.9); font-size:0.88rem; font-weight:500;">
                    <i class="fa-solid fa-location-dot" style="color:var(--accent);"></i> <?= htmlspecialchars($destinations) ?> Destinations
                </span>
                <span style="display:flex; align-items:center; gap:7px; color:rgba(255,255,255,0.9); font-size:0.88rem; font-weight:500;">
                    <i class="fa-solid fa-users" style="color:var(--accent);"></i> <?= htmlspecialchars($clientsCount) ?> Travelers
                </span>
            </div>
        </div>
    </section>

    <!-- STATS STRIP -->
    <div class="gallery-stats-strip">
        <div class="container">
            <div class="gallery-stats-inner">
                <div class="gallery-stat-item">
                    <i class="fa-solid fa-camera"></i>
                    <div>
                        <span class="stat-val"><?= htmlspecialchars($totalPhotos) ?></span>
                        <span class="stat-txt">Tour Photos</span>
                    </div>
                </div>
                <div class="gallery-stat-item">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <div>
                        <span class="stat-val"><?= htmlspecialchars($destinations) ?></span>
                        <span class="stat-txt">Destinations</span>
                    </div>
                </div>
                <div class="gallery-stat-item">
                    <i class="fa-solid fa-face-smile"></i>
                    <div>
                        <span class="stat-val"><?= htmlspecialchars($clientsCount) ?></span>
                        <span class="stat-txt">Happy Travelers</span>
                    </div>
                </div>
                <div class="gallery-stat-item">
                    <i class="fa-solid fa-star"></i>
                    <div>
                        <span class="stat-val">4.9 / 5</span>
                        <span class="stat-txt">Average Rating</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GALLERY SECTION -->
    <section class="gallery-section section-pad" id="gallery">
        <div class="container">

            <!-- Filter Bar -->
            <div class="gallery-filter-bar" data-aos="fade-up">
                <button class="gallery-filter-btn active" data-filter="all">
                    <i class="fa-solid fa-globe"></i> All Photos
                </button>
<?php if ($useStatic): ?>
                <button class="gallery-filter-btn" data-filter="cultural">    <i class="fa-solid fa-landmark"></i> Cultural Sites</button>
                <button class="gallery-filter-btn" data-filter="beach">       <i class="fa-solid fa-umbrella-beach"></i> Beaches</button>
                <button class="gallery-filter-btn" data-filter="wildlife">    <i class="fa-solid fa-paw"></i> Wildlife</button>
                <button class="gallery-filter-btn" data-filter="hillcountry"> <i class="fa-solid fa-mountain"></i> Hill Country</button>
                <button class="gallery-filter-btn" data-filter="honeymoon">   <i class="fa-solid fa-heart"></i> Honeymoon</button>
<?php else: ?>
                <?php foreach ($catConfig as $key => $cat): if (!isset($dbCats[$key])) continue; ?>
                <button class="gallery-filter-btn" data-filter="<?= $key ?>">
                    <i class="fa-solid <?= $cat['icon'] ?>"></i> <?= $cat['label'] ?>
                </button>
                <?php endforeach; ?>
<?php endif; ?>
            </div>

            <!-- Masonry Gallery Grid -->
            <div class="gallery-masonry" id="galleryGrid" data-aos="fade-up" data-aos-delay="100">

<?php foreach ($displayImages as $i => $img):
    // Resolve image URL
    if ($useStatic) {
        $imgUrl  = $img['image_path'];
        $layout  = $img['layout'];
    } else {
        $imgUrl  = site_url($img['image_path']);
        $layout  = $layoutPattern[$i % count($layoutPattern)];
    }
    $cat      = $img['category'] ?? '';
    $title    = $img['title']    ?? 'Sri Lanka';
    $catLabel = $catConfig[$cat]['label'] ?? ucfirst($cat);
    // Extract location hint from title (text before comma if present)
    $location = strpos($title, ',') !== false ? substr($title, 0, strpos($title, ',')) : $title;
?>
                <div class="gallery-item <?= $layout ?>" data-category="<?= htmlspecialchars($cat) ?>"
                     data-src="<?= htmlspecialchars($imgUrl) ?>"
                     data-caption="<?= htmlspecialchars($title) ?>"
                     data-tag="<?= htmlspecialchars($catLabel) ?>">
                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($title) ?>">
                    <div class="gallery-item-overlay">
                        <span class="gallery-location"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($location) ?></span>
                        <span class="gallery-category-tag"><?= htmlspecialchars($catLabel) ?></span>
                    </div>
                    <div class="gallery-zoom-icon"><i class="fa-solid fa-magnifying-glass-plus"></i></div>
                </div>
<?php endforeach; ?>

            </div><!-- end gallery-masonry -->

        </div>
    </section>

    <!-- VIDEO SECTION — only shown when admin has added active videos -->
    <?php if (!empty($videos)): ?>
    <section class="gallery-video-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Watch & Explore</span>
                <h2 class="section-title">Sri Lanka in Motion</h2>
                <p class="section-subtitle">Let these videos inspire your next adventure to the Pearl of the Indian Ocean</p>
            </div>
            <div class="video-grid" data-aos="fade-up" data-aos-delay="100">
                <?php foreach ($videos as $v): ?>
                <div class="video-card">
                    <div class="video-embed">
                        <?php if ($v['video_type'] === 'upload' && $v['video_file']): ?>
                        <video controls preload="metadata" style="width:100%;height:100%;object-fit:cover;">
                            <source src="<?= htmlspecialchars(site_url($v['video_file'])) ?>"
                                    type="<?= str_ends_with($v['video_file'], '.webm') ? 'video/webm' : (str_ends_with($v['video_file'], '.ogg') ? 'video/ogg' : 'video/mp4') ?>">
                        </video>
                        <?php else: ?>
                        <iframe
                            src="<?= htmlspecialchars($v['embed_url']) ?>"
                            title="<?= htmlspecialchars($v['title']) ?>"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen loading="lazy">
                        </iframe>
                        <?php endif; ?>
                    </div>
                    <div class="video-card-body">
                        <h4><?= htmlspecialchars($v['title']) ?></h4>
                        <?php if ($v['description']): ?>
                        <p><?= htmlspecialchars($v['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- GALLERY CTA -->
    <section class="gallery-cta">
        <div class="container">
            <div data-aos="fade-up">
                <span class="section-tag light">Ready to Explore?</span>
                <h2 style="margin-top:12px;">Create Your Own <span>Memories</span></h2>
                <p>Every photo in this gallery was taken by a real traveler on a ASB Tours journey. Your story starts here.</p>
                <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
                    <a href="packages.php" class="btn btn-primary">
                        <i class="fa-solid fa-compass"></i> Browse Packages
                    </a>
                    <a href="../index.php#contact" class="btn btn-outline" style="border-color:rgba(255,255,255,0.35); color:white;">
                        <i class="fa-solid fa-envelope"></i> Contact Us
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- LIGHTBOX -->
    <div class="lightbox-overlay" id="lightboxOverlay">
        <button class="lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
        <button class="lightbox-prev" onclick="lightboxNav(-1)"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="lightbox-next" onclick="lightboxNav(1)"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="lightbox-inner">
            <div class="lightbox-img-wrap">
                <img src="" alt="" id="lightboxImg">
            </div>
            <div class="lightbox-caption">
                <i class="fa-solid fa-location-dot"></i>
                <span id="lightboxCaption"></span>
                <span class="lightbox-counter" id="lightboxCounter"></span>
            </div>
        </div>
    </div>

    <div id="footer-placeholder"></div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/components.js?v=8"></script>
    <script src="../assets/js/navbar.js"></script>
    <script src="../assets/js/animations.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        AOS.init({ duration: 700, once: true, offset: 60 });

        // Gallery Filter
        const filterBtns  = document.querySelectorAll('.gallery-filter-btn');
        const galleryItems = document.querySelectorAll('.gallery-item');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const filter = btn.dataset.filter;
                galleryItems.forEach(item => {
                    item.classList.toggle('hidden', filter !== 'all' && item.dataset.category !== filter);
                });
                // Rebuild lightbox list after filter change
                buildLightboxImages();
            });
        });

        // Lightbox
        let lightboxImages = [];
        let currentIndex   = 0;

        function buildLightboxImages() {
            lightboxImages = [];
            document.querySelectorAll('.gallery-item:not(.hidden)').forEach(item => {
                lightboxImages.push({ src: item.dataset.src, caption: item.dataset.caption });
            });
        }

        buildLightboxImages();

        galleryItems.forEach(item => {
            item.addEventListener('click', () => {
                buildLightboxImages();
                const src = item.dataset.src;
                currentIndex = lightboxImages.findIndex(img => img.src === src);
                if (currentIndex === -1) currentIndex = 0;
                openLightbox(currentIndex);
            });
        });

        function openLightbox(index) {
            const img = lightboxImages[index];
            document.getElementById('lightboxImg').src     = img.src;
            document.getElementById('lightboxImg').alt     = img.caption;
            document.getElementById('lightboxCaption').textContent = img.caption;
            document.getElementById('lightboxCounter').textContent = `${index + 1} / ${lightboxImages.length}`;
            document.getElementById('lightboxOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightboxOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }

        function lightboxNav(dir) {
            currentIndex = (currentIndex + dir + lightboxImages.length) % lightboxImages.length;
            openLightbox(currentIndex);
        }

        document.getElementById('lightboxOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeLightbox();
        });

        document.addEventListener('keydown', e => {
            if (!document.getElementById('lightboxOverlay').classList.contains('open')) return;
            if (e.key === 'Escape')      closeLightbox();
            if (e.key === 'ArrowLeft')   lightboxNav(-1);
            if (e.key === 'ArrowRight')  lightboxNav(1);
        });
    </script>
</body>
</html>




