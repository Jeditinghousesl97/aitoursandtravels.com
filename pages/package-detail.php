<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/package-itinerary.php';
require_once __DIR__ . '/../assets/php/turnstile.php';
require_once __DIR__ . '/../assets/php/seo.php';
$pdo  = getPDO();
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') { header('Location: packages.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM packages WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$pkg = $stmt->fetch();
if (!$pkg) { header('Location: packages.php'); exit; }
$itineraryItems = nt_fetch_package_itinerary($pdo, (int)$pkg['id']);

// Related packages, same category, exclude self, max 3
$rel = $pdo->prepare('SELECT * FROM packages WHERE category = ? AND id != ? AND is_active = 1 ORDER BY is_featured DESC, id DESC LIMIT 3');
$rel->execute([$pkg['category'], $pkg['id']]);
$related = $rel->fetchAll();
// If fewer than 3, fill with other packages
if (count($related) < 3) {
    $needed = 3 - count($related);
    $ids    = array_merge([$pkg['id']], array_column($related, 'id'));
    $ph     = implode(',', array_fill(0, count($ids), '?'));
    $extra  = $pdo->prepare("SELECT * FROM packages WHERE id NOT IN ($ph) AND is_active = 1 ORDER BY is_featured DESC LIMIT $needed");
    $extra->execute($ids);
    $related = array_merge($related, $extra->fetchAll());
}

// Settings
$s   = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $k, string $d = '') => (isset($s[$k]) && $s[$k] !== '') ? $s[$k] : $d;
$turnstileSiteKey = nt_turnstile_site_key($s);
$turnstileEnabled = nt_turnstile_enabled($s);

// Helpers
$title       = $pkg['title'];
$hasPrice    = $pkg['price'] !== null && $pkg['price'] !== '';
$price       = $hasPrice ? number_format((float)$pkg['price'], 0) : null;
$oldPrice    = ($pkg['old_price'] !== null && $pkg['old_price'] !== '') ? number_format((float)$pkg['old_price'], 0) : null;
$coverImage  = $pkg['cover_image'] ? site_url($pkg['cover_image']) : 'assets/images/packages/cultural-triangle.jpg';
$badge       = $pkg['badge'] ?? '';
$badgeLabels = ['popular' => 'Popular', 'bestseller' => 'Best Seller', 'new' => 'New', 'limited' => 'Limited Spots', 'hotdeal' => 'Hot Deal'];
$badgeClass  = ['popular' => 'badge-popular', 'bestseller' => 'badge-bestseller', 'new' => 'badge-new', 'limited' => 'badge-limited', 'hotdeal' => 'badge-hot'];
$difficulty  = ucfirst($pkg['difficulty'] ?? 'Moderate');
$rating      = $pkg['rating'] ? number_format((float)$pkg['rating'], 1) : null;
$reviewCount = (int)($pkg['review_count'] ?? 0);
$seoTitle = $title . ' | ASB Tours Sri Lanka';
$plainDescription = trim(preg_replace('/\s+/', ' ', strip_tags((string)($pkg['description'] ?? ''))) ?? '');
$seoDesc = $title . ', ' . ($pkg['duration'] ? $pkg['duration'] . ' Sri Lanka tour.' : 'Sri Lanka tour package.') . ($price ? ' From $' . $price . ' per person.' : '');
$seoCanonical = absolute_site_url('pages/package-detail.php?slug=' . rawurlencode($slug));
$seoImage = $cfg('seo_image', '') ?: ($cfg('site_logo', '') ?: 'assets/images/logo.png');

// Parse highlights (one per line)
$highlights = $pkg['highlights'] ? array_filter(array_map('trim', explode("\n", $pkg['highlights']))) : [];

// Parse destinations
$destinations = $pkg['destinations'] ? array_filter(array_map('trim', explode("\n", $pkg['destinations']))) : [];

// Parse inclusions / exclusions
$inclusions = $pkg['inclusions'] ? array_filter(array_map('trim', explode("\n", $pkg['inclusions']))) : [];
$exclusions = $pkg['exclusions'] ? array_filter(array_map('trim', explode("\n", $pkg['exclusions']))) : [];

// Parse itinerary into days
$days = [];
if (!empty($itineraryItems)) {
    foreach ($itineraryItems as $item) {
        $days[] = [
            'num' => (int)$item['day_number'],
            'title' => (string)$item['title'],
            'description' => (string)($item['description'] ?? ''),
            'images' => array_values(array_filter([
                !empty($item['image_1']) ? site_url($item['image_1']) : '',
                !empty($item['image_2']) ? site_url($item['image_2']) : '',
            ])),
            'activities' => [],
        ];
    }
} elseif ($pkg['itinerary']) {
    $lines   = explode("\n", $pkg['itinerary']);
    $current = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^Day\s+(\d+)[:\-\.]?\s*(.*)/i', $line, $m)) {
            if ($current !== null) $days[] = $current;
            $current = ['num' => (int)$m[1], 'title' => trim($m[2]), 'description' => '', 'images' => [], 'activities' => []];
        } elseif ($current !== null && $line !== '') {
            $current['activities'][] = $line;
        }
    }
    if ($current !== null) $days[] = $current;
}
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
        'type' => 'product',
        'site_name' => $cfg('site_name', 'ASB Tours'),
        'structured_data' => [[
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => $title,
            'description' => $seoDesc,
            'url' => $seoCanonical,
            'image' => absolute_site_url($seoImage),
            'touristType' => $pkg['group_size'] ?: 'Travelers',
            'itinerary' => array_values(array_map(fn($day) => $day['title'], $days)),
            'offers' => $price ? [
                '@type' => 'Offer',
                'priceCurrency' => 'USD',
                'price' => (float) str_replace(',', '', $price),
                'availability' => 'https://schema.org/InStock',
                'url' => $seoCanonical,
            ] : null,
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
    <link rel="stylesheet" href="../assets/css/packages-page.css">
    <link rel="stylesheet" href="../assets/css/package-detail.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <?php if ($turnstileEnabled): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>

    <div id="navbar-placeholder"></div>

    <!-- PAGE HERO -->
    <section class="page-hero" style="background-image: url('<?= $coverImage ?>'); background-position: center 45%;">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <a href="packages.php">Packages</a>
                <i class="fa fa-chevron-right"></i>
                <span><?= htmlspecialchars($title) ?></span>
            </div>
            <?php if ($badge && isset($badgeLabels[$badge])): ?>
            <span class="pkg-badge <?= $badgeClass[$badge] ?>" style="position:static;display:inline-flex;margin-bottom:14px;">
                <?= $badgeLabels[$badge] ?>
            </span>
            <?php endif; ?>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p><?= htmlspecialchars($plainDescription !== '' ? mb_substr($plainDescription, 0, 160) : '') ?></p>
            <div style="display:flex;gap:22px;flex-wrap:wrap;margin-top:22px;">
                <?php if ($pkg['duration']): ?>
                <span style="display:flex;align-items:center;gap:7px;color:rgba(255,255,255,0.9);font-size:0.88rem;font-weight:500;">
                    <i class="fa-solid fa-clock" style="color:var(--accent);"></i> <?= htmlspecialchars($pkg['duration']) ?>
                </span>
                <?php endif; ?>
                <?php if ($pkg['group_size']): ?>
                <span style="display:flex;align-items:center;gap:7px;color:rgba(255,255,255,0.9);font-size:0.88rem;font-weight:500;">
                    <i class="fa-solid fa-users" style="color:var(--accent);"></i> <?= htmlspecialchars($pkg['group_size']) ?>
                </span>
                <?php endif; ?>
                <?php if ($rating): ?>
                <span style="display:flex;align-items:center;gap:7px;color:rgba(255,255,255,0.9);font-size:0.88rem;font-weight:500;">
                    <i class="fa-solid fa-star" style="color:#F7C948;"></i>
                    <?= $rating ?><?= $reviewCount ? ' (' . $reviewCount . ' reviews)' : '' ?>
                </span>
                <?php endif; ?>
                <?php if ($pkg['best_season']): ?>
                <span style="display:flex;align-items:center;gap:7px;color:rgba(255,255,255,0.9);font-size:0.88rem;font-weight:500;">
                    <i class="fa-solid fa-calendar-days" style="color:var(--accent);"></i> Best: <?= htmlspecialchars($pkg['best_season']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- MAIN DETAIL SECTION -->
    <section class="pkg-detail-section section-pad">
        <div class="container">
            <div class="pkg-detail-layout">

                <!-- LEFT CONTENT -->
                <div class="pkg-detail-content">

                    <!-- Overview Bar -->
                    <div class="detail-overview-bar" data-aos="fade-up">
                        <?php if ($pkg['duration']): ?>
                        <div class="overview-stat">
                            <i class="fa-solid fa-clock"></i>
                            <span class="stat-value"><?= htmlspecialchars($pkg['duration']) ?></span>
                            <span class="stat-label">Duration</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($pkg['group_size']): ?>
                        <div class="overview-stat">
                            <i class="fa-solid fa-users"></i>
                            <span class="stat-value"><?= htmlspecialchars($pkg['group_size']) ?></span>
                            <span class="stat-label">Group Size</span>
                        </div>
                        <?php endif; ?>
                        <div class="overview-stat">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span class="stat-value"><?= $difficulty ?></span>
                            <span class="stat-label">Difficulty</span>
                        </div>
                        <?php if ($pkg['best_season']): ?>
                        <div class="overview-stat">
                            <i class="fa-solid fa-calendar-days"></i>
                            <span class="stat-value"><?= htmlspecialchars($pkg['best_season']) ?></span>
                            <span class="stat-label">Best Season</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($pkg['description'])): ?>
                    <div class="detail-block" data-aos="fade-up">
                        <h2 class="detail-block-title">
                            <span class="dbt-icon"><i class="fa-solid fa-file-lines"></i></span>
                            Tour Description
                        </h2>
                        <div class="detail-rich-text">
                            <?= $pkg['description'] ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Highlights -->
                    <?php if (!empty($highlights)): ?>
                    <div class="detail-block" data-aos="fade-up">
                        <h2 class="detail-block-title">
                            <span class="dbt-icon"><i class="fa-solid fa-star"></i></span>
                            Tour Highlights
                        </h2>
                        <div class="highlights-grid">
                            <?php foreach ($highlights as $hl): ?>
                            <div class="highlight-item">
                                <div class="highlight-icon"><i class="fa-solid fa-circle-check"></i></div>
                                <div>
                                    <h5><?= htmlspecialchars($hl) ?></h5>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Itinerary -->
                    <?php if (!empty($days)): ?>
                    <div class="detail-block" data-aos="fade-up">
                        <h2 class="detail-block-title">
                            <span class="dbt-icon"><i class="fa-solid fa-map-location-dot"></i></span>
                            Day-by-Day Itinerary
                        </h2>
                        <div class="itinerary-timeline">
                            <?php foreach ($days as $i => $day): ?>
                            <div class="itinerary-day itinerary-day-static">
                                <div class="itinerary-day-rail">
                                    <div class="day-num"><?= str_pad($day['num'], 2, '0', STR_PAD_LEFT) ?></div>
                                </div>
                                <div class="itinerary-day-content">
                                    <div class="day-header-text">
                                        <strong><?= htmlspecialchars($day['title']) ?></strong>
                                    </div>
                                    <?php if (!empty($day['description'])): ?>
                                    <div class="itinerary-day-description"><?= nl2br(htmlspecialchars($day['description'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($day['activities'])): ?>
                                    <ul class="day-activity-list">
                                        <?php foreach ($day['activities'] as $act): ?>
                                        <li><i class="fa-solid fa-circle-dot"></i> <?= htmlspecialchars($act) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                    <?php if (!empty($day['images'])): ?>
                                    <div class="itinerary-photo-grid">
                                        <?php foreach ($day['images'] as $imageUrl): ?>
                                        <div class="itinerary-photo-card">
                                            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($day['title']) ?>">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Inclusions / Exclusions -->
                    <?php if (!empty($inclusions) || !empty($exclusions)): ?>
                    <div class="detail-block" data-aos="fade-up">
                        <h2 class="detail-block-title">
                            <span class="dbt-icon"><i class="fa-solid fa-list-check"></i></span>
                            What's Included &amp; Excluded
                        </h2>
                        <div class="inc-exc-grid">
                            <?php if (!empty($inclusions)): ?>
                            <div class="inc-exc-col">
                                <h5><i class="fa-solid fa-circle-check icon-inc"></i> Included</h5>
                                <ul class="inc-exc-list">
                                    <?php foreach ($inclusions as $item): ?>
                                    <li><i class="fa-solid fa-circle-check icon-inc"></i> <?= htmlspecialchars($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($exclusions)): ?>
                            <div class="inc-exc-col">
                                <h5><i class="fa-solid fa-circle-xmark icon-exc"></i> Excluded</h5>
                                <ul class="inc-exc-list">
                                    <?php foreach ($exclusions as $item): ?>
                                    <li><i class="fa-solid fa-circle-xmark icon-exc"></i> <?= htmlspecialchars($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end .pkg-detail-content -->

                <!-- RIGHT SIDEBAR -->
                <aside class="pkg-detail-sidebar">
                    <div class="sidebar-price-card">
                        <div class="sidebar-price-header">
                            <div class="sidebar-pkg-name"><?= htmlspecialchars($title) ?></div>
                            <div class="sidebar-price-block">
                                <?php if ($oldPrice): ?>
                                <div class="sidebar-price-was">Was $<?= $oldPrice ?></div>
                                <?php endif; ?>
                                <?php if ($price): ?>
                                <div class="sidebar-price-now">
                                    <span class="price-num">$<?= $price ?></span>
                                    <span class="price-pp">/ person</span>
                                </div>
                                <?php else: ?>
                                <div class="sidebar-price-now">
                                    <span class="price-num">Contact Us</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="sidebar-price-note">Starting price · Subject to availability</div>
                        </div>
                        <div class="sidebar-price-body">
                            <ul class="sidebar-meta-list">
                                <?php if ($pkg['duration']): ?>
                                <li>
                                    <span class="meta-key"><i class="fa-solid fa-clock"></i> Duration</span>
                                    <span class="meta-val"><?= htmlspecialchars($pkg['duration']) ?></span>
                                </li>
                                <?php endif; ?>
                                <?php if ($pkg['group_size']): ?>
                                <li>
                                    <span class="meta-key"><i class="fa-solid fa-users"></i> Group Size</span>
                                    <span class="meta-val"><?= htmlspecialchars($pkg['group_size']) ?></span>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <span class="meta-key"><i class="fa-solid fa-gauge-high"></i> Difficulty</span>
                                    <span class="meta-val"><?= $difficulty ?></span>
                                </li>
                                <?php if ($pkg['best_season']): ?>
                                <li>
                                    <span class="meta-key"><i class="fa-solid fa-calendar-days"></i> Best Season</span>
                                    <span class="meta-val"><?= htmlspecialchars($pkg['best_season']) ?></span>
                                </li>
                                <?php endif; ?>
                                <?php if ($rating): ?>
                                <li>
                                    <span class="meta-key"><i class="fa-solid fa-star"></i> Rating</span>
                                    <span class="meta-val">
                                        <?= $rating ?>
                                        <span class="stars">
                                            <?php
                                            $full = floor((float)$rating);
                                            for ($s = 0; $s < $full; $s++) echo '<i class="fa-solid fa-star"></i>';
                                            if ((float)$rating - $full >= 0.5) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                            ?>
                                        </span>
                                        <?= $reviewCount ? "($reviewCount)" : '' ?>
                                    </span>
                                </li>
                                <?php endif; ?>
                                <?php if (!empty($destinations)): ?>
                                <li>
                                    <span class="meta-key"><i class="fa-solid fa-location-dot"></i> Destinations</span>
                                    <span class="meta-val"><?= htmlspecialchars(implode(', ', array_slice($destinations, 0, 3))) ?><?= count($destinations) > 3 ? ' & more' : '' ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                            <button class="sidebar-book-btn"
                                onclick="openBookingModal('<?= htmlspecialchars(addslashes($title)) ?>', '<?= $price ? '$' . $price : 'Contact us for pricing' ?>')">
                                <i class="fa-solid fa-calendar-check"></i> Book This Package
                            </button>
                            <a href="../index.php#contact" class="sidebar-enquire-btn">
                                <i class="fa-solid fa-envelope"></i> Send Enquiry
                            </a>
                            <div class="sidebar-trust">
                                <div class="trust-item"><i class="fa-solid fa-shield-halved"></i> Free cancellation up to 30 days prior</div>
                                <div class="trust-item"><i class="fa-solid fa-circle-check"></i> No hidden fees, all taxes included</div>
                                <div class="trust-item"><i class="fa-solid fa-headset"></i> 24/7 in-destination support</div>
                                <div class="trust-item"><i class="fa-solid fa-lock"></i> Secure online booking</div>
                            </div>
                        </div>
                    </div>
                </aside>

            </div><!-- end .pkg-detail-layout -->
        </div>
    </section>

    <!-- RELATED PACKAGES -->
    <?php if (!empty($related)): ?>
    <section class="related-pkg-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">Explore More</span>
                <h2 class="section-title">You Might Also Like</h2>
                <p class="section-subtitle">Discover more hand-crafted Sri Lanka experiences</p>
            </div>
            <div class="related-pkg-grid" data-aos="fade-up" data-aos-delay="100">
                <?php foreach ($related as $r):
                    $rImg   = $r['cover_image'] ? site_url($r['cover_image']) : '../assets/images/packages/cultural-triangle.jpg';
                    $rHasPrice = $r['price'] !== null && $r['price'] !== '';
                    $rPrice = $rHasPrice ? number_format((float)$r['price'], 0) : null;
                    $rRat   = $r['rating'] ? number_format((float)$r['rating'], 1) : null;
                ?>
                <div class="related-pkg-card">
                    <div class="related-pkg-img">
                        <img src="<?= $rImg ?>" alt="<?= htmlspecialchars($r['title']) ?>">
                        <span class="related-pkg-price"><?= $rPrice ? 'From $' . $rPrice : 'Contact Us' ?></span>
                    </div>
                    <div class="related-pkg-body">
                        <h4><?= htmlspecialchars($r['title']) ?></h4>
                        <div class="related-pkg-meta">
                            <?php if ($r['duration']): ?>
                            <span><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($r['duration']) ?></span>
                            <?php endif; ?>
                            <?php if ($rRat): ?>
                            <span><i class="fa-solid fa-star"></i> <?= $rRat ?><?= $r['review_count'] ? ' (' . $r['review_count'] . ')' : '' ?></span>
                            <?php endif; ?>
                        </div>
                        <p><?= htmlspecialchars(mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)($r['description'] ?? ''))) ?? ''), 0, 120)) ?>…</p>
                        <a href="package-detail.php?slug=<?= urlencode($r['slug']) ?>" class="related-pkg-link">
                            <i class="fa-solid fa-arrow-right"></i> View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- BOOKING MODAL -->
    <div class="booking-modal-overlay" id="bookingOverlay" onclick="handleOverlayClick(event)">
        <div class="booking-modal" id="bookingModal">
            <div class="modal-header">
                <div class="modal-header-top">
                    <div>
                        <span class="modal-pkg-label">Book Your Tour</span>
                        <h3 id="modalPkgTitle"><?= htmlspecialchars($title) ?></h3>
                        <p id="modalPkgPrice"><?= $price ? 'Starting from $' . $price . ' per person' : 'Contact us for pricing' ?></p>
                    </div>
                    <button class="modal-close" onclick="closeBookingModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="modal-body" id="bookingFormWrap">
                <form onsubmit="submitBooking(event)">
                    <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                    <p class="modal-section-title"><i class="fa-solid fa-user"></i> Personal Details</p>
                    <div class="form-row">
                        <div class="form-group"><label>First Name <span class="req">*</span></label><input type="text" name="first_name" placeholder="John" required></div>
                        <div class="form-group"><label>Last Name <span class="req">*</span></label><input type="text" name="last_name" placeholder="Smith" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Email Address <span class="req">*</span></label><input type="email" name="email" placeholder="john@example.com" required></div>
                        <div class="form-group"><label>Phone / WhatsApp <span class="req">*</span></label><input type="tel" name="phone" placeholder="+1 234 567 8900" required></div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group"><label>Country of Residence</label><input type="text" name="nationality" placeholder="e.g. United Kingdom"></div>
                    </div>
                    <div class="form-divider"></div>
                    <p class="modal-section-title"><i class="fa-solid fa-plane"></i> Travel Details</p>
                    <div class="form-row">
                        <div class="form-group"><label>Arrival Date <span class="req">*</span></label><input type="date" name="travel_date" required></div>
                        <div class="form-group"><label>Number of Travelers <span class="req">*</span></label>
                            <select name="adults" required>
                                <option value="">Select</option>
                                <option value="1">1 Person</option><option value="2">2 People</option><option value="3">3 People</option>
                                <option value="4">4 People</option><option value="6">5–8 People</option><option value="12">9–15 People</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Accommodation Preference</label>
                            <select name="accommodation">
                                <option>Standard (3-Star)</option>
                                <option>Comfort (4-Star)</option>
                                <option>Luxury (5-Star)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>How Did You Hear About Us?</label>
                            <select name="source">
                                <option>Google Search</option><option>Social Media</option>
                                <option>Friend / Family</option><option>Travel Blog</option><option>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-divider"></div>
                    <p class="modal-section-title"><i class="fa-solid fa-sliders"></i> Preferences &amp; Notes</p>
                    <div class="form-row single">
                        <div class="form-group"><label>Special Requests or Questions</label>
                            <textarea name="special_request" placeholder="Dietary requirements, accessibility needs, special occasions, custom itinerary requests..."></textarea>
                        </div>
                    </div>
                    <?php if ($turnstileEnabled): ?>
                    <div class="form-row single">
                        <div class="form-group">
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="modal-submit-btn">
                        <i class="fa-solid fa-paper-plane"></i> Send Booking Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div id="footer-placeholder"></div>

    <!-- AOS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/components.js?v=8"></script>
    <script src="../assets/js/navbar.js"></script>
    <script src="../assets/js/animations.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>AOS.init({ duration: 700, once: true });</script>

    <!-- Booking Modal -->
    <script>
    function openBookingModal(name, price) {
        document.getElementById('modalPkgTitle').textContent = name;
        document.getElementById('modalPkgPrice').textContent = price.startsWith('$') ? ('Starting from ' + price + ' per person') : price;
        document.getElementById('bookingOverlay').classList.add('active');
        const bookingForm = document.querySelector('#bookingFormWrap form');
        if (bookingForm) resetTurnstile(bookingForm);
        document.body.style.overflow = 'hidden';
    }
    function closeBookingModal() {
        document.getElementById('bookingOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }
    function handleOverlayClick(e) {
        if (e.target === document.getElementById('bookingOverlay')) closeBookingModal();
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
    async function submitBooking(e) {
        e.preventDefault();
        const form = e.target;
        const btn  = form.querySelector('.modal-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
        const turnstileToken = getTurnstileToken(form);

        const first = form.querySelector('[name="first_name"]').value.trim();
        const last  = form.querySelector('[name="last_name"]').value.trim();
        const accom = form.querySelector('[name="accommodation"]').value;
        const src   = form.querySelector('[name="source"]').value;
        const notes = form.querySelector('[name="special_request"]').value.trim();
        const extra = [accom ? 'Accommodation: ' + accom : '', src ? 'Source: ' + src : '', notes].filter(Boolean).join(' | ');

        const data = {
            package_id:      form.querySelector('[name="package_id"]').value,
            full_name:       (first + ' ' + last).trim(),
            email:           form.querySelector('[name="email"]').value.trim(),
            phone:           form.querySelector('[name="phone"]').value.trim(),
            nationality:     form.querySelector('[name="nationality"]').value.trim(),
            travel_date:     form.querySelector('[name="travel_date"]').value,
            adults:          parseInt(form.querySelector('[name="adults"]').value) || 1,
            children:        0,
            special_request: extra,
            'cf-turnstile-response': turnstileToken,
        };

        try {
            const res  = await fetch('../submit-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            const json = await res.json();
            if (json.success) {
                document.getElementById('bookingFormWrap').innerHTML =
                    '<div style="text-align:center;padding:3rem 1rem;">' +
                    '<i class="fa-solid fa-circle-check" style="font-size:3rem;color:#22c55e;margin-bottom:1rem;display:block;"></i>' +
                    '<h3 style="color:#03045E;margin-bottom:.5rem;">Booking Request Sent!</h3>' +
                    '<p style="color:#718096;">Thank you! Our team will contact you within 24 hours to confirm your booking.</p>' +
                    '</div>';
            } else {
                resetTurnstile(form);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Booking Request';
                alert(json.error || 'Something went wrong. Please try again.');
            }
        } catch (err) {
            resetTurnstile(form);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Booking Request';
            alert('Connection error. Please try again.');
        }
    }
    </script>

</body>
</html>




