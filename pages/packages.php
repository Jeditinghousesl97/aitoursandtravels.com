<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/turnstile.php';
require_once __DIR__ . '/../assets/php/seo.php';

if (file_exists(__DIR__ . '/../maintenance.flag')) {
    include __DIR__ . '/../maintenance.php'; exit;
}

$pdo = getPDO();

// All active packages ordered by featured first
$packages = $pdo->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY is_featured DESC, id DESC")->fetchAll();

// Settings (for WhatsApp link etc)
$s   = $pdo->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $k, string $d = '') => $s[$k] ?? $d;
$turnstileSiteKey = nt_turnstile_site_key($s);
$turnstileEnabled = nt_turnstile_enabled($s);

// Maps
$catIcon  = [
    'cultural'=>'fa-landmark',
    'beach'=>'fa-umbrella-beach',
    'wildlife'=>'fa-paw',
    'hill'=>'fa-mountain',
    'honeymoon'=>'fa-heart',
    'adventure'=>'fa-person-hiking',
    'sightseeing'=>'fa-camera-retro',
    'leisure'=>'fa-couch',
    'round-tours'=>'fa-route',
    'most-popular'=>'fa-fire',
    'escape-to-wild'=>'fa-tree',
];
$catLabel = [
    'cultural'=>'Cultural',
    'beach'=>'Beach',
    'wildlife'=>'Wildlife',
    'hill'=>'Hill Country',
    'honeymoon'=>'Honeymoon',
    'adventure'=>'Adventure',
    'sightseeing'=>'Sightseeing',
    'leisure'=>'Leisure',
    'round-tours'=>'Round Tours',
    'most-popular'=>'Most Popular',
    'escape-to-wild'=>'Escape to Wild',
];
// DB stores 'hill', filter bar uses 'hillcountry'
$catFilter= [
    'cultural'=>'cultural',
    'beach'=>'beach',
    'wildlife'=>'wildlife',
    'hill'=>'hillcountry',
    'honeymoon'=>'honeymoon',
    'adventure'=>'adventure',
    'sightseeing'=>'sightseeing',
    'leisure'=>'leisure',
    'round-tours'=>'round-tours',
    'most-popular'=>'most-popular',
    'escape-to-wild'=>'escape-to-wild',
];

function normalizePackageCategory(string $category): string
{
    $key = strtolower(trim($category));
    $key = str_replace(['_', ' '], '-', $key);
    if ($key === 'hillcountry' || $key === 'hill-country') {
        return 'hill';
    }
    if ($key === 'roundtour' || $key === 'round-tours') {
        return 'round-tours';
    }
    if ($key === 'mostpopular' || $key === 'most-popular') {
        return 'most-popular';
    }
    if ($key === 'escapetowild' || $key === 'escape-to-wild') {
        return 'escape-to-wild';
    }
    return $key;
}

$badgeClass = ['popular'=>'badge-popular','bestseller'=>'badge-bestseller','new'=>'badge-new',
               'limited'=>'badge-featured','hotdeal'=>'badge-popular'];
$badgeLabel = ['popular'=>'Popular','bestseller'=>'Best Seller','new'=>'New',
               'limited'=>'Limited','hotdeal'=>'Hot Deal'];

$total = count($packages);
$categoryCounts = [];
foreach ($packages as $p) {
    $rawCat = (string)($p['category'] ?? '');
    if ($rawCat === '') {
        continue;
    }
    $normalized = normalizePackageCategory($rawCat);
    if (!isset($catLabel[$normalized])) {
        continue;
    }
    $categoryCounts[$normalized] = ($categoryCounts[$normalized] ?? 0) + 1;
}
$seoTitle = 'Tour Packages | ASB Tours Sri Lanka';
$seoDesc = 'ASB Tours Sri Lanka Packages, Cultural, Beach, Wildlife, Hill Country & Honeymoon packages. Book your perfect Sri Lanka tour today.';
$seoCanonical = absolute_site_url('pages/packages.php');
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
            '@type' => 'CollectionPage',
            'name' => 'Sri Lanka Tour Packages',
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
    <link rel="stylesheet" href="../assets/css/packages.css">
    <link rel="stylesheet" href="../assets/css/services-page.css">
    <link rel="stylesheet" href="../assets/css/packages-page.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <?php if ($turnstileEnabled): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>

    <div id="navbar-placeholder"></div>

    <!-- PAGE HERO -->
    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <span>Packages</span>
            </div>
            <h1>Sri Lanka Tour <span>Packages</span></h1>
            <p>Handpicked itineraries for every type of traveler, beaches, safaris, culture, and romance.</p>
        </div>
    </section>

    <!-- FILTER BAR -->
    <div class="filter-bar" id="filterBar">
        <div class="container">
            <div class="filter-inner">
                <span class="filter-label"><i class="fa-solid fa-sliders"></i> Filter:</span>
                <button class="filter-btn active" data-filter="all">
                    <i class="fa-solid fa-globe"></i> All Packages
                </button>
                <?php
                $filterMap = ['cultural','beach','wildlife','hill','honeymoon','adventure',
                              'sightseeing','leisure','round-tours','most-popular','escape-to-wild'];
                $filterIcons = [
                    'cultural'=>'fa-landmark',
                    'beach'=>'fa-umbrella-beach',
                    'wildlife'=>'fa-paw',
                    'hill'=>'fa-mountain',
                    'honeymoon'=>'fa-heart',
                    'adventure'=>'fa-person-hiking',
                    'sightseeing'=>'fa-camera-retro',
                    'leisure'=>'fa-couch',
                    'round-tours'=>'fa-route',
                    'most-popular'=>'fa-fire',
                    'escape-to-wild'=>'fa-tree',
                ];
                $filterLabels = [
                    'cultural'=>'Cultural',
                    'beach'=>'Beach',
                    'wildlife'=>'Wildlife',
                    'hill'=>'Hill Country',
                    'honeymoon'=>'Honeymoon',
                    'adventure'=>'Adventure',
                    'sightseeing'=>'Sightseeing',
                    'leisure'=>'Leisure',
                    'round-tours'=>'Round Tours',
                    'most-popular'=>'Most Popular',
                    'escape-to-wild'=>'Escape to Wild',
                ];
                foreach ($filterMap as $cat):
                    if (($categoryCounts[$cat] ?? 0) < 1) {
                        continue;
                    }
                    $fc = $catFilter[$cat] ?? $cat;
                ?>
                <button class="filter-btn" data-filter="<?= $fc ?>">
                    <i class="fa-solid <?= $filterIcons[$cat] ?>"></i> <?= $filterLabels[$cat] ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- PACKAGES GRID -->
    <section class="packages-page-section section-pad">
        <div class="container">

            <p class="packages-count" data-aos="fade-up">
                Showing <strong id="pkgCount"><?= $total ?></strong> package<?= $total !== 1 ? 's' : '' ?> for Sri Lanka
            </p>

            <div class="packages-page-grid" id="packagesGrid">

            <?php if (!empty($packages)):
                foreach ($packages as $i => $p):
                    $img     = $p['cover_image'] ? site_url($p['cover_image']) : '../assets/images/packages/cultural-triangle.jpg';
                    $hasPrice = $p['price'] !== null && $p['price'] !== '';
                    $price   = $hasPrice ? number_format((float)$p['price'], 0) : null;
                    $oldPrice= ($p['old_price'] !== null && $p['old_price'] !== '') ? number_format((float)$p['old_price'], 0) : null;
                    $cat     = normalizePackageCategory((string)$p['category']);
                    $fc      = $catFilter[$cat] ?? $cat;
                    $badge   = $p['badge'] ?? '';
                    $rating  = $p['rating']  ? number_format((float)$p['rating'],  1) : null;
                    $reviews = (int)($p['review_count'] ?? 0);
                    $delay   = ($i % 3 + 1) * 50;
            ?>
            <div class="pkg-card-wrap" data-category="<?= htmlspecialchars($fc) ?>" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                <div class="pkg-full-card">
                    <div class="pkg-image">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                        <?php if ($badge && isset($badgeLabel[$badge])): ?>
                        <span class="pkg-badge <?= $badgeClass[$badge] ?>"><?= $badgeLabel[$badge] ?></span>
                        <?php endif; ?>
                        <span class="pkg-category-tag">
                            <i class="fa-solid <?= $catIcon[$cat] ?? 'fa-globe' ?>"></i>
                            <?= $catLabel[$cat] ?? ucfirst($cat) ?>
                        </span>
                    </div>
                    <div class="pkg-body">
                        <div class="pkg-meta">
                            <?php if ($p['duration']): ?>
                            <span><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($p['duration']) ?></span>
                            <?php endif; ?>
                            <?php if ($p['group_size']): ?>
                            <span><i class="fa-solid fa-users"></i> <?= htmlspecialchars($p['group_size']) ?></span>
                            <?php endif; ?>
                            <?php if ($rating): ?>
                            <span class="pkg-rating">
                                <i class="fa-solid fa-star"></i> <?= $rating ?><?= $reviews ? " ($reviews)" : '' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="pkg-price-row">
                            <div class="pkg-price-main">
                                <?php if ($price): ?>
                                <span class="pkg-price-from">From</span>
                                <span class="pkg-price-amount">$<?= $price ?></span>
                                <span class="pkg-price-pp">/ person</span>
                                <?php else: ?>
                                <span class="pkg-price-amount">Contact Us</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($oldPrice): ?>
                            <div class="pkg-price-old">Was $<?= $oldPrice ?></div>
                            <?php endif; ?>
                        </div>
                        <h3><?= htmlspecialchars($p['title']) ?></h3>
                        <p class="pkg-desc"><?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', strip_tags((string)($p['description'] ?? ''))) ?? '')) ?></p>

                        <div class="pkg-cta-row">
                            <button class="pkg-book-btn"
                                onclick="openBookingModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>', '<?= $price ? '$' . $price : 'Contact us for pricing' ?>')">
                                <i class="fa-solid fa-calendar-check"></i> Book Now
                            </button>
                            <a href="package-detail.php?slug=<?= urlencode($p['slug']) ?>" class="pkg-enquire-btn">
                                <i class="fa-solid fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach;
            else: ?>
            <div style="text-align:center;padding:80px 20px;color:var(--text-light);grid-column:1/-1;">
                <i class="fa-solid fa-suitcase-rolling" style="font-size:3rem;opacity:.3;display:block;margin-bottom:1rem;"></i>
                <p>No packages available yet. Check back soon!</p>
            </div>
            <?php endif; ?>

            </div>

            <!-- No results (shown by JS filter) -->
            <div id="noResults" style="display:none;text-align:center;padding:60px 20px;color:var(--text-light);">
                <i class="fa-solid fa-magnifying-glass" style="font-size:2.5rem;color:var(--border);margin-bottom:16px;display:block;"></i>
                <p>No packages found for this category.
                    <a href="#" onclick="filterPackages('all');return false;" style="color:var(--primary);font-weight:600;">Show all packages</a>
                </p>
            </div>

        </div>
    </section>

    <!-- WHY BOOK WITH US -->
    <section class="why-book-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-label">PEACE OF MIND</span>
                <h2>Why Book With Us</h2>
                <p>We make your Sri Lanka journey seamless from the first click to the final farewell.</p>
            </div>
            <div class="why-book-grid">
                <div class="why-book-card" data-aos="fade-up" data-aos-delay="50">
                    <div class="why-book-icon"><i class="fa-solid fa-bolt"></i></div>
                    <h4>Instant Confirmation</h4>
                    <p>Receive your booking confirmation and detailed itinerary within 2 hours of your request.</p>
                </div>
                <div class="why-book-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="why-book-icon"><i class="fa-solid fa-tag"></i></div>
                    <h4>Best Price Guarantee</h4>
                    <p>Find the same itinerary cheaper elsewhere and we'll match it, guaranteed, no questions asked.</p>
                </div>
                <div class="why-book-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="why-book-icon"><i class="fa-solid fa-rotate-left"></i></div>
                    <h4>Free Cancellation</h4>
                    <p>Cancel up to 14 days before your departure for a full refund. Flexible rebooking always available.</p>
                </div>
                <div class="why-book-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="why-book-icon"><i class="fa-solid fa-headset"></i></div>
                    <h4>24 / 7 Support</h4>
                    <p>Our Sri Lanka team is reachable round the clock, before, during, and after your trip.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- POPULAR ADD-ONS -->
    <section class="addons-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-label">ENHANCE YOUR TRIP</span>
                <h2>Popular Add-Ons</h2>
                <p>Upgrade your experience with our hand-picked add-on activities and services.</p>
            </div>
            <div class="addons-grid">
                <div class="addon-card" data-aos="fade-up" data-aos-delay="50"><div class="addon-icon"><i class="fa-solid fa-van-shuttle"></i></div><div class="addon-text"><h5>Private Airport Transfer</h5><span>Door-to-door A/C vehicle, 24/7</span></div></div>
                <div class="addon-card" data-aos="fade-up" data-aos-delay="100"><div class="addon-icon"><i class="fa-solid fa-camera"></i></div><div class="addon-text"><h5>Photography Experience</h5><span>Sunrise shoot at Sigiriya or Ella</span></div></div>
                <div class="addon-card" data-aos="fade-up" data-aos-delay="150"><div class="addon-icon"><i class="fa-solid fa-spa"></i></div><div class="addon-text"><h5>Ayurveda Spa Session</h5><span>Traditional 90-min treatment</span></div></div>
                <div class="addon-card" data-aos="fade-up" data-aos-delay="200"><div class="addon-icon"><i class="fa-solid fa-water"></i></div><div class="addon-text"><h5>Whale Watching Cruise</h5><span>Blue whales off Mirissa coast</span></div></div>
                <div class="addon-card" data-aos="fade-up" data-aos-delay="250"><div class="addon-icon"><i class="fa-solid fa-utensils"></i></div><div class="addon-text"><h5>Sri Lankan Cooking Class</h5><span>Learn to cook authentic local dishes</span></div></div>
                <div class="addon-card" data-aos="fade-up" data-aos-delay="300"><div class="addon-icon"><i class="fa-solid fa-elephant"></i></div><div class="addon-text"><h5>Elephant Sanctuary Visit</h5><span>Ethical elephant experience, Pinnawala</span></div></div>
            </div>
        </div>
    </section>

    <!-- CTA BANNER -->
    <section class="packages-cta">
        <div class="container">
            <h2 data-aos="fade-up">Can't Find What You're <span>Looking For?</span></h2>
            <p data-aos="fade-up" data-aos-delay="100">We design fully custom Sri Lanka itineraries tailored to your dates, budget, and interests. Let's plan your dream trip together.</p>
            <div class="cta-btns" data-aos="fade-up" data-aos-delay="200">
                <a href="../index.php#custom-tour" class="btn btn-primary">
                    <i class="fa-solid fa-pencil"></i> Plan Custom Tour
                </a>
                <a href="../index.php#contact" class="btn btn-outline">
                    <i class="fa-solid fa-phone"></i> Talk to an Expert
                </a>
            </div>
        </div>
    </section>

    <div id="footer-placeholder"></div>

    <!-- BOOKING MODAL -->
    <div class="booking-modal-overlay" id="bookingModalOverlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="booking-modal" id="bookingModal">
            <div class="modal-header">
                <div class="modal-header-top">
                    <div>
                        <span class="modal-pkg-label">BOOKING REQUEST</span>
                        <h3 id="modalTitle">Package Name</h3>
                        <p id="modalSubtitle">Fill in your details and we'll confirm within 2 hours.</p>
                    </div>
                    <button class="modal-close" onclick="closeBookingModal()" aria-label="Close modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <form id="bookingForm" novalidate>
                <input type="hidden" id="bkgPackageId" value="">
                <div class="modal-body">

                    <div class="modal-section-title"><i class="fa-solid fa-user"></i> Personal Details</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span class="req">*</span></label>
                            <input type="text" id="bkgName" placeholder="e.g. John Smith" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address <span class="req">*</span></label>
                            <input type="email" id="bkgEmail" placeholder="john@example.com" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone / WhatsApp <span class="req">*</span></label>
                            <input type="tel" id="bkgPhone" placeholder="+1 234 567 8900" required>
                        </div>
                        <div class="form-group">
                            <label>Country of Residence</label>
                            <select id="bkgCountry">
                                <option value="">Select country…</option>
                                <option>United Kingdom</option><option>United States</option>
                                <option>Australia</option><option>Germany</option>
                                <option>France</option><option>Netherlands</option>
                                <option>Canada</option><option>India</option>
                                <option>Singapore</option><option>China</option>
                                <option>Japan</option><option>South Korea</option>
                                <option>UAE</option><option>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-section-title"><i class="fa-solid fa-plane"></i> Travel Details</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Arrival Date <span class="req">*</span></label>
                            <input type="date" id="bkgDate" required>
                        </div>
                        <div class="form-group">
                            <label>Adults</label>
                            <select id="bkgAdults">
                                <option value="1">1</option><option value="2" selected>2</option>
                                <option value="3">3</option><option value="4">4</option>
                                <option value="5">5</option><option value="6">6+</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Children (under 12)</label>
                            <select id="bkgChildren">
                                <option value="0" selected>0</option><option value="1">1</option>
                                <option value="2">2</option><option value="3">3</option><option value="4">4+</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-section-title"><i class="fa-solid fa-sliders"></i> Preferences &amp; Notes</div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1">
                            <label>Special Requests or Questions</label>
                            <textarea id="bkgNotes" placeholder="Dietary requirements, accessibility needs, special occasions..." rows="3"></textarea>
                        </div>
                    </div>

                    <div id="bkgMsg" style="display:none;padding:.75rem;border-radius:8px;text-align:center;margin-bottom:1rem;"></div>
                    <?php if ($turnstileEnabled): ?>
                    <div class="form-row">
                        <div class="form-group" style="flex:1">
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="modal-submit-btn" id="bkgSubmitBtn">
                        <i class="fa-solid fa-paper-plane"></i> Send Booking Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/components.js?v=8"></script>
    <script src="../assets/js/navbar.js"></script>
    <script src="../assets/js/animations.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
    AOS.init({ duration: 700, once: true });

    // ── Category filter ──
    function filterPackages(cat) {
        const cards = document.querySelectorAll('.pkg-card-wrap');
        let visible = 0;
        cards.forEach(c => {
            const show = cat === 'all' || c.dataset.category === cat;
            c.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('pkgCount').textContent = visible;
        document.getElementById('noResults').style.display = visible === 0 ? '' : 'none';
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.filter === cat);
        });
    }
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => filterPackages(btn.dataset.filter));
    });

    // Sticky filter bar
    const filterBar = document.getElementById('filterBar');
    const fbTop = filterBar.offsetTop;
    window.addEventListener('scroll', () => {
        filterBar.classList.toggle('sticky', window.scrollY >= fbTop);
    });

    // Booking modal 
    function openBookingModal(pkgId, name, price) {
        document.getElementById('bkgPackageId').value = pkgId;
        document.getElementById('modalTitle').textContent = name;
        document.getElementById('modalSubtitle').textContent = price.startsWith('$')
            ? ('Starting from ' + price + ' per person, confirm within 2 hours.')
            : (price + '. Confirm within 2 hours.');
        document.getElementById('bkgMsg').style.display = 'none';
        document.getElementById('bookingForm').reset();
        resetTurnstile(document.getElementById('bookingForm'));
        document.getElementById('bookingModalOverlay').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeBookingModal() {
        document.getElementById('bookingModalOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }
    document.getElementById('bookingModalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeBookingModal();
    });

    function getTurnstileToken(form) {
        const field = form.querySelector('[name="cf-turnstile-response"]');
        return field ? field.value : '';
    }

    function resetTurnstile(form) {
        if (!window.turnstile) return;
        const widget = form.querySelector('.cf-turnstile');
        if (widget) window.turnstile.reset(widget);
    }

    // Booking form submit 
    document.getElementById('bookingForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('bkgSubmitBtn');
        const msgEl = document.getElementById('bkgMsg');
        const turnstileToken = getTurnstileToken(this);
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';

        const res = await fetch('<?= htmlspecialchars(site_url('submit-booking.php')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                package_id:      document.getElementById('bkgPackageId').value,
                full_name:       document.getElementById('bkgName').value,
                email:           document.getElementById('bkgEmail').value,
                phone:           document.getElementById('bkgPhone').value,
                nationality:     document.getElementById('bkgCountry').value,
                adults:          document.getElementById('bkgAdults').value,
                children:        document.getElementById('bkgChildren').value,
                travel_date:     document.getElementById('bkgDate').value,
                special_request: document.getElementById('bkgNotes').value,
                'cf-turnstile-response': turnstileToken,
            })
        });
        const data = await res.json();

        msgEl.style.display = 'block';
        if (data.success) {
            msgEl.style.background = '#d1fae5';
            msgEl.style.color = '#065f46';
            msgEl.textContent = '✓ Booking request sent! Our team will contact you within 2 hours.';
            this.reset();
            resetTurnstile(this);
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Request Sent!';
        } else {
            msgEl.style.background = '#fee2e2';
            msgEl.style.color = '#991b1b';
            msgEl.textContent = data.error || 'Something went wrong. Please try again.';
            resetTurnstile(this);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Booking Request';
        }
    });
    </script>

</body>
</html>



