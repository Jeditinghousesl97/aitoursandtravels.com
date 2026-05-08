<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/seo.php';
$pdo = getPDO();
if (file_exists(__DIR__ . '/../maintenance.flag')) { include __DIR__ . '/../maintenance.php'; exit; }

// Settings 
$s   = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $key, string $default = '') => (isset($s[$key]) && $s[$key] !== '') ? $s[$key] : $default;
$whatsapp = $cfg('contact_whatsapp');

// Services 
$coreServices       = $pdo->query("SELECT * FROM services WHERE type='core'       AND is_active=1 ORDER BY sort_order, id")->fetchAll();
$additionalServices = $pdo->query("SELECT * FROM services WHERE type='additional' AND is_active=1 ORDER BY sort_order, id")->fetchAll();
$useStaticCore       = empty($coreServices);
$useStaticAdditional = empty($additionalServices);

// Process Steps 
$processSteps = tableExists($pdo, 'process_steps')
    ? $pdo->query("SELECT * FROM process_steps ORDER BY sort_order, step_number")->fetchAll()
    : [];
$useStaticSteps = empty($processSteps);

// Reviews 
$reviews = $pdo->query("SELECT * FROM reviews WHERE is_approved=1 ORDER BY id DESC LIMIT 3")->fetchAll();
$useStaticReviews = empty($reviews);

// Settings defaults 
$introHeading = $cfg('svc_intro_heading', "Sri Lanka's Most Trusted Travel Partner");
$introText1   = $cfg('svc_intro_text1',   "At ASB Tours, we believe every journey should be effortless and extraordinary. With over a decade of experience crafting travel experiences across Sri Lanka, we've built our reputation on personalised service, deep local knowledge, and an unwavering commitment to making your trip truly unforgettable.");
$introText2   = $cfg('svc_intro_text2',   "From the moment you contact us to the time you return home, our team is with you every step of the way, handling everything so you can simply focus on soaking in the beauty of Sri Lanka.");
$ctaHeading   = $cfg('svc_cta_heading',   'Ready to Explore Sri Lanka?');
$ctaText      = $cfg('svc_cta_text',      "Let our experts craft your perfect itinerary. Contact us today and get a free, no-obligation quote within 24 hours.");

// Trust badges
$badges = [];
for ($i = 1; $i <= 4; $i++) {
    $badges[] = [
        'icon'  => $cfg("svc_badge{$i}_icon",  ['fa-certificate','fa-headset','fa-tag','fa-shield-halved'][$i-1]),
        'title' => $cfg("svc_badge{$i}_title", ['Licensed & Certified Tour Operator','24 / 7 Customer Support','Best Price Guarantee','100% Secure Bookings'][$i-1]),
        'text'  => $cfg("svc_badge{$i}_text",  ['Registered with Sri Lanka Tourism Development Authority','Always reachable, before, during, and after your trip','We match or beat any comparable quoted price','Fully insured tours with transparent pricing, no hidden fees'][$i-1]),
    ];
}

// Stats
$stats = [];
$statDefaults = [
    ['icon'=>'fa-calendar-check','number'=>'10+',    'label'=>'Years of Experience'],
    ['icon'=>'fa-face-smile',    'number'=>'3,500+', 'label'=>'Happy Travelers'],
    ['icon'=>'fa-map-location-dot','number'=>'25+',  'label'=>'Destinations Covered'],
    ['icon'=>'fa-headset',       'number'=>'24 / 7', 'label'=>'Customer Support'],
];
$statIcons = ['fa-calendar-check','fa-face-smile','fa-map-location-dot','fa-headset'];
for ($i = 1; $i <= 4; $i++) {
    $stats[] = [
        'icon'   => $statIcons[$i-1],
        'number' => $cfg("svc_stat{$i}_number", $statDefaults[$i-1]['number']),
        'label'  => $cfg("svc_stat{$i}_label",  $statDefaults[$i-1]['label']),
    ];
}

// Static fallbacks
$staticCoreServices = [
    ['icon_class'=>'fa-plane-departure','title'=>'Flight Booking',         'description'=>'Getting to Sri Lanka is the first step of your adventure, and we make it effortless. We search across hundreds of airlines and routes to find you the best fares to Bandaranaike International Airport (CMB) in Colombo. Whether you\'re flying from Europe, Asia, the Middle East, or Australia, our team handles every detail, including layovers, seat selection, and baggage allowances, so your journey begins perfectly.','features'=>"International & connecting flight search from 200+ airlines\nBest-fare guarantee with flexible date comparison\nEconomy, business, and first-class bookings\nSeat selection, meal preferences & special assistance\nE-ticket delivery and booking confirmation\nFlight change & cancellation support"],
    ['icon_class'=>'fa-hotel',          'title'=>'Hotel & Resort Booking',  'description'=>'Sri Lanka offers an extraordinary range of accommodation, from centuries-old colonial boutique hotels in Galle Fort to breathtaking cliff-top infinity pool resorts in Mirissa, lush tea-plantation bungalows in Ella, and intimate jungle lodges near Yala. We personally vet every property we recommend, ensuring quality, location, and value align perfectly with your expectations and budget.','features'=>"Curated selection from budget guesthouses to 5-star luxury\nBoutique colonial villas, beach resorts & eco-lodges\nBreakfast-included, half-board & all-inclusive options\nHoneymoon & suite upgrades available on request\nBest available rate, no booking fees\nPartner hotels: Cinnamon, Jetwing & Aitken Spence properties"],
    ['icon_class'=>'fa-user-tie',       'title'=>'Expert Local Guides',    'description'=>'Our certified Sri Lankan guides are the heart of the ASB Tours experience. Trained in history, culture, wildlife, and geology, they bring Sigiriya\'s ancient frescoes, Kandy\'s sacred temples, Galle\'s colonial streets, and Yala\'s wildlife to vivid life. They don\'t just take you to the highlights, they reveal the hidden stories, the local legends, and the off-the-beaten-path gems.','features'=>"Certified by Sri Lanka Tourism & National Guide Association\nMultilingual guides, English, German, French, Mandarin & more\nSpecialist wildlife naturalists for safari & bird-watching\nCultural & heritage tour experts for UNESCO sites\nPrivate, small-group & large-group guide options\nLocal food, craft & culinary experience guided tours"],
    ['icon_class'=>'fa-passport',       'title'=>'Visa Assistance',        'description'=>'Most visitors to Sri Lanka require an Electronic Travel Authorisation (ETA) obtained online before arrival. The process, while straightforward, can be confusing, especially around document requirements, photo specifications, and processing times. Our team guides you through every step, reviews your application before submission, and helps resolve any issues quickly.','features'=>"Step-by-step ETA application guidance\nDocument checklist & pre-submission review\nVisa-on-arrival assistance at Colombo airport\nVisa extension support for long-stay travelers\nMultiple-entry & business visa advisory\nEmergency visa support available 24/7"],
    ['icon_class'=>'fa-car-side',       'title'=>'Island-Wide Transfers',  'description'=>'Sri Lanka is a relatively small island but its diverse landscapes, from the central highlands to the southern coast, mean comfortable, well-timed transport is essential. We provide fully air-conditioned, modern vehicles with experienced, English-speaking drivers who know every road, shortcut, and scenic detour.','features'=>"Airport pickups & drop-offs, Colombo BIA, 24/7\nAir-conditioned cars, vans, minibuses & coaches\nColombo → Kandy → Ella → Mirissa & all routes\nEnglish-speaking, experienced professional drivers\nFlexible multi-day chauffeur-drive packages\nFlight & train connection monitoring for on-time pickup"],
    ['icon_class'=>'fa-people-group',   'title'=>'Group Tours',            'description'=>'Whether you\'re travelling with family, friends, colleagues, or a school group, our tailored group tour packages bring people together through shared Sri Lankan experiences. We manage all logistics, accommodation, transport, meals, activities, across groups of any size, ensuring everyone travels comfortably, safely, and with maximum enjoyment.','features'=>"Custom itineraries for families, corporates & schools\nGroups from 5 to 500+, all sizes welcome\nCultural, wildlife, beach & adventure group packages\nGroup accommodation & meal coordination\nDedicated group tour manager throughout\nSpecial group discounts from 10 travelers"],
];

$staticAdditionalServices = [
    ['icon_class'=>'fa-shield-heart',  'title'=>'Travel Insurance',              'description'=>'Comprehensive travel insurance plans covering medical emergencies, trip cancellations, lost luggage, and travel delays, for full peace of mind.'],
    ['icon_class'=>'fa-camera',        'title'=>'Photography Tours',             'description'=>'Guided photography expeditions at Sigiriya, Ella, Yala, and Colombo, sunrise and golden-hour sessions with expert local photographers.'],
    ['icon_class'=>'fa-spa',           'title'=>'Ayurveda & Wellness',           'description'=>'Traditional Sri Lankan Ayurveda retreat bookings, herbal spa packages, and yoga retreat coordination across certified wellness centres.'],
    ['icon_class'=>'fa-heart',         'title'=>'Wedding & Honeymoon Planning',  'description'=>'Romantic beach ceremonies, luxury resort honeymoon packages, and bespoke couple\'s itineraries crafted to make love stories unforgettable.'],
    ['icon_class'=>'fa-graduation-cap','title'=>'School & Educational Tours',    'description'=>'Safe, educational, and engaging group tours for schools and universities, covering cultural heritage sites, wildlife reserves, and conservation projects.'],
    ['icon_class'=>'fa-briefcase',     'title'=>'Corporate & MICE Travel',       'description'=>'End-to-end corporate travel management, business travel bookings, incentive trips, conferences, team-building retreats, and event logistics in Sri Lanka.'],
];

$staticProcessSteps = [
    ['icon_class'=>'fa-comment-dots',  'step_number'=>1, 'title'=>'Tell Us Your Dream',  'description'=>'Share your travel dates, budget, interests and any special requirements. The more you tell us, the better we can plan.'],
    ['icon_class'=>'fa-pencil-ruler',  'step_number'=>2, 'title'=>'We Plan Your Trip',   'description'=>'Our travel experts craft a personalised itinerary with flights, hotels, guides, and activities tailored just for you.'],
    ['icon_class'=>'fa-circle-check',  'step_number'=>3, 'title'=>'Book & Confirm',      'description'=>'Review your itinerary, request any tweaks, and confirm your booking. We handle all reservations and send you full confirmation.'],
    ['icon_class'=>'fa-sun',           'step_number'=>4, 'title'=>'Enjoy Sri Lanka',     'description'=>'Arrive, relax, and explore. Your driver meets you at the airport and our team is just a message away throughout your trip.'],
];

$displayCore       = $useStaticCore       ? $staticCoreServices       : $coreServices;
$displayAdditional = $useStaticAdditional ? $staticAdditionalServices : $additionalServices;
$displaySteps      = $useStaticSteps      ? $staticProcessSteps       : $processSteps;
$delays            = [50, 100, 150, 200, 250, 300];
$seoTitle = 'Our Services | ASB Tours Sri Lanka';
$seoDesc = 'ASB Tours Services, Flight booking, hotel reservations, expert local guides, visa assistance, island-wide transfers and group tours across Sri Lanka.';
$seoCanonical = absolute_site_url('pages/services.php');
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
            '@type' => 'Service',
            'serviceType' => 'Sri Lanka travel planning and tour services',
            'provider' => [
                '@type' => 'TravelAgency',
                'name' => $cfg('site_name', 'ASB Tours'),
                'url' => absolute_site_url('/'),
            ],
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
    <link rel="stylesheet" href="../assets/css/services.css">
    <link rel="stylesheet" href="../assets/css/services-page.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

    <div id="navbar-placeholder"></div>

    <!-- PAGE HERO -->
    <section class="page-hero">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <span>Services</span>
            </div>
            <h1>Our <span>Services</span></h1>
            <p>Everything you need for a seamless, memorable Sri Lanka travel experience, expertly handled from start to finish.</p>
        </div>
    </section>

    <!-- SERVICES INTRO -->
    <section class="services-intro section-pad">
        <div class="container">
            <div class="services-intro-grid">

                <!-- Left: Text + Trust Badges -->
                <div class="services-intro-text" data-aos="fade-right">
                    <span class="section-label">WHO WE ARE</span>
                    <h2><?= htmlspecialchars($introHeading) ?></h2>
                    <p><?= nl2br(htmlspecialchars($introText1)) ?></p>
                    <p><?= nl2br(htmlspecialchars($introText2)) ?></p>

                    <div class="trust-badges">
                        <?php foreach ($badges as $badge): ?>
                        <div class="trust-badge">
                            <div class="trust-badge-icon"><i class="fa-solid <?= htmlspecialchars($badge['icon']) ?>"></i></div>
                            <div class="trust-badge-text">
                                <strong><?= htmlspecialchars($badge['title']) ?></strong>
                                <span><?= htmlspecialchars($badge['text']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Image with floating badge -->
                <div class="services-intro-image" data-aos="fade-left">
                    <img src="../assets/images/destinations/sigiriya.jpg" alt="ASB Tours team in Sri Lanka">
                    <div class="intro-badge-float">
                        <div class="ibf-icon"><i class="fa-solid fa-trophy"></i></div>
                        <div class="ibf-text">
                            <strong><?= htmlspecialchars($stats[0]['number']) ?></strong>
                            <span>Years of Excellence</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- CORE SERVICES -->
    <section class="services-detail-section section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-label">WHAT WE OFFER</span>
                <h2>Our Core Services</h2>
                <p>The pillars of excellence that make every ASB Tours experience seamless and stress-free.</p>
            </div>

            <div class="services-detail-grid">
                <?php foreach ($displayCore as $i => $svc):
                    $features = array_filter(array_map('trim', explode("\n", $svc['features'] ?? '')));
                    $delay    = $delays[min($i, 5)];
                ?>
                <div class="service-detail-card" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                    <div class="sdc-header">
                        <div class="sdc-icon"><i class="fa-solid <?= htmlspecialchars($svc['icon_class']) ?>"></i></div>
                        <div class="sdc-title-group">
                            <div class="sdc-number">SERVICE <?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
                            <h3><?= htmlspecialchars($svc['title']) ?></h3>
                        </div>
                    </div>
                    <?php if ($svc['description']): ?>
                    <p class="sdc-description"><?= nl2br(htmlspecialchars($svc['description'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($features)): ?>
                    <ul class="sdc-features">
                        <?php foreach ($features as $feat): ?>
                        <li><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($feat) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <a href="../index.php#contact" class="sdc-cta">
                        <i class="fa-solid fa-paper-plane"></i> Get a Quote
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- STATS STRIP -->
    <section class="services-stats">
        <div class="container">
            <div class="services-stats-grid">
                <?php foreach ($stats as $i => $stat): ?>
                <div class="stat-item" data-aos="fade-up" data-aos-delay="<?= 50 + $i * 50 ?>">
                    <span class="stat-icon"><i class="fa-solid <?= $stat['icon'] ?>"></i></span>
                    <strong><?= htmlspecialchars($stat['number']) ?></strong>
                    <span><?= htmlspecialchars($stat['label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="how-it-works section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-label">SIMPLE PROCESS</span>
                <h2>How It Works</h2>
                <p>Booking your dream Sri Lanka trip with us is easy, just four simple steps.</p>
            </div>

            <div class="process-steps">
                <?php foreach ($displaySteps as $i => $step): ?>
                <div class="process-step" data-aos="fade-up" data-aos-delay="<?= 50 + $i * 50 ?>">
                    <div class="step-circle">
                        <i class="fa-solid <?= htmlspecialchars($step['icon_class']) ?>"></i>
                        <span class="step-num"><?= str_pad($step['step_number'], 2, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <h4><?= htmlspecialchars($step['title']) ?></h4>
                    <p><?= nl2br(htmlspecialchars($step['description'] ?? '')) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ADDITIONAL SERVICES -->
    <?php if (!empty($displayAdditional)): ?>
    <section class="additional-services section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-label">AND MORE</span>
                <h2>Additional Services</h2>
                <p>Beyond our core offerings, we provide a full suite of specialist travel services for every need.</p>
            </div>

            <div class="additional-grid">
                <?php foreach ($displayAdditional as $i => $svc): ?>
                <div class="additional-card" data-aos="fade-up" data-aos-delay="<?= $delays[min($i, 5)] ?>">
                    <div class="additional-card-icon"><i class="fa-solid <?= htmlspecialchars($svc['icon_class']) ?>"></i></div>
                    <div class="additional-card-text">
                        <h4><?= htmlspecialchars($svc['title']) ?></h4>
                        <p><?= nl2br(htmlspecialchars($svc['description'] ?? '')) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- REVIEWS -->
    <section class="services-reviews section-pad">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-label">WHAT THEY SAY</span>
                <h2>Travelers Love Our Service</h2>
                <p>Don't take our word for it, here's what some of our guests have to say.</p>
            </div>

            <div class="mini-reviews-grid">
<?php if ($useStaticReviews): ?>
                <div class="mini-review-card" data-aos="fade-up" data-aos-delay="50">
                    <div class="mini-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p>"Our guide Pradeep was outstanding, his knowledge of Sigiriya's history was incredible. The transfer service was punctual and the hotels were exactly as described. Truly a flawless trip."</p>
                    <div class="mini-reviewer">
                        <div class="mini-avatar av-1">SM</div>
                        <div class="mini-reviewer-info">
                            <h5>Sarah Mitchell</h5>
                            <span>London, UK, Cultural Triangle Tour</span>
                        </div>
                    </div>
                </div>
                <div class="mini-review-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="mini-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p>"The team handled every detail, from our visa guidance to resort bookings in Mirissa. I just had to pack my bags. We spotted a leopard on our very first Yala safari morning!"</p>
                    <div class="mini-reviewer">
                        <div class="mini-avatar av-2">JO</div>
                        <div class="mini-reviewer-info">
                            <h5>James O'Brien</h5>
                            <span>Sydney, Australia, Yala Safari Package</span>
                        </div>
                    </div>
                </div>
                <div class="mini-review-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="mini-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p>"Our honeymoon was absolutely perfect. Every detail was thoughtfully planned, the candlelit beach dinner, the tea estate bungalow, the whale watching. ASB Tours are magicians!"</p>
                    <div class="mini-reviewer">
                        <div class="mini-avatar av-3">PS</div>
                        <div class="mini-reviewer-info">
                            <h5>Priya &amp; Rohan Sharma</h5>
                            <span>Mumbai, India, Honeymoon Package</span>
                        </div>
                    </div>
                </div>
<?php else: ?>
                <?php $avatarColors = ['av-1','av-2','av-3']; foreach ($reviews as $i => $rev):
                    $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', $rev['name'] ?? '')))));
                    $initials = substr($initials, 0, 2);
                    $stars    = (int)($rev['rating'] ?? 5);
                ?>
                <div class="mini-review-card" data-aos="fade-up" data-aos-delay="<?= 50 + $i * 50 ?>">
                    <div class="mini-stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="fa-<?= $s <= $stars ? 'solid' : 'regular' ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <p>"<?= htmlspecialchars($rev['review_text'] ?? $rev['comment'] ?? '') ?>"</p>
                    <div class="mini-reviewer">
                        <div class="mini-avatar <?= $avatarColors[$i % 3] ?>"><?= $initials ?></div>
                        <div class="mini-reviewer-info">
                            <h5><?= htmlspecialchars($rev['name'] ?? '') ?></h5>
                            <?php if (!empty($rev['country'])): ?>
                            <span><?= htmlspecialchars($rev['country']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
<?php endif; ?>
            </div>
        </div>
    </section>

    <!-- CTA BANNER -->
    <section class="services-cta">
        <div class="container">
            <h2 data-aos="fade-up"><?= htmlspecialchars($ctaHeading) ?></h2>
            <p data-aos="fade-up" data-aos-delay="100"><?= nl2br(htmlspecialchars($ctaText)) ?></p>
            <div class="cta-btns" data-aos="fade-up" data-aos-delay="200">
                <a href="../index.php#contact" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Get a Free Quote
                </a>
                <a href="packages.php" class="btn btn-outline">
                    <i class="fa-solid fa-compass"></i> View Packages
                </a>
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
    </script>
</body>
</html>




