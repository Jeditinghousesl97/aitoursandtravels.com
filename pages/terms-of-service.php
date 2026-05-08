<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/seo.php';
$pdo = getPDO();
if (file_exists(__DIR__ . '/../maintenance.flag')) { include __DIR__ . '/../maintenance.php'; exit; }

$s   = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $key, string $default = '') => (isset($s[$key]) && $s[$key] !== '') ? $s[$key] : $default;
$seoTitle = 'Terms of Service | ASB Tours Sri Lanka';
$seoDesc = 'Terms of Service for ASB Tours Sri Lanka. Read our booking terms, cancellation policy, and conditions of travel.';
$seoCanonical = absolute_site_url('pages/terms-of-service.php');
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
    ]); ?>
    <title><?= htmlspecialchars($seoTitle) ?></title>    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <?php include __DIR__ . '/../assets/php/site-theme.php'; ?>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/services-page.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <style>
        .policy-body {
            max-width: 820px;
            margin: 60px auto;
            padding: 0 20px 80px;
        }
        .policy-body h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin: 2rem 0 .75rem;
        }
        .policy-body p, .policy-body li {
            font-size: .96rem;
            line-height: 1.85;
            color: #4a5568;
            margin-bottom: .6rem;
        }
        .policy-body ul {
            padding-left: 1.4rem;
            margin-bottom: 1rem;
        }
        .policy-body a { color: var(--primary); }
    </style>
</head>
<body>

    <div id="navbar-placeholder"></div>

    <section class="page-hero" style="background-image: url('../assets/images/hero/slide-2.jpg'); background-position: center 40%;">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <span>Terms of Service</span>
            </div>
            <h1>Terms of <span>Service</span></h1>
            <p>Last updated: <?= date('d F Y') ?></p>
        </div>
    </section>

    <div class="policy-body">

        <p>These Terms of Service govern your use of the ASB Tours website and the booking of our travel services. By accessing our website or making a booking, you agree to be bound by these terms. Please read them carefully before proceeding.</p>

        <h2>1. About Us</h2>
        <p>ASB Tours is a licensed travel company based in Sri Lanka, specialising in tailor-made tours, holiday packages, and travel services across the island. Our registered office is at <?= htmlspecialchars($cfg('contact_address', 'No. 15, Galle Road, Colombo 03, Sri Lanka')) ?>.</p>

        <h2>2. Bookings and Reservations</h2>
        <p>A booking is confirmed once you receive a written confirmation from us and have paid the required deposit. We reserve the right to decline any booking at our discretion. All bookings are subject to availability at the time of confirmation.</p>
        <ul>
            <li>A deposit of 30% of the total tour cost is required to confirm a booking.</li>
            <li>The remaining balance must be paid no later than 14 days before the tour departure date.</li>
            <li>For bookings made within 14 days of departure, full payment is required at the time of booking.</li>
        </ul>

        <h2>3. Pricing</h2>
        <p>All prices are quoted in US Dollars (USD) or Sri Lankan Rupees (LKR) as stated. Prices include the services specified in the tour itinerary. International airfares, visa fees, travel insurance, and items of a personal nature are not included unless explicitly stated.</p>

        <h2>4. Cancellation Policy</h2>
        <p>Cancellations must be made in writing. The following cancellation charges apply:</p>
        <ul>
            <li>More than 30 days before departure: Loss of deposit only</li>
            <li>15 to 30 days before departure: 50% of total tour cost</li>
            <li>7 to 14 days before departure: 75% of total tour cost</li>
            <li>Less than 7 days before departure: 100% of total tour cost (no refund)</li>
        </ul>

        <h2>5. Changes to Bookings</h2>
        <p>If you wish to amend your booking after confirmation, we will do our best to accommodate your request. Amendment fees may apply depending on the nature of the change and the suppliers involved.</p>

        <h2>6. Travel Insurance</h2>
        <p>We strongly recommend that all travellers obtain comprehensive travel insurance prior to departure, covering cancellation, medical emergencies, personal accident, and loss of baggage. ASB Tours is not liable for any costs arising from events covered by travel insurance.</p>

        <h2>7. Our Responsibilities</h2>
        <p>We will use reasonable care and skill in arranging and providing your tour services. However, we shall not be liable for any injury, loss, damage, or delay arising from circumstances beyond our control, including but not limited to natural disasters, civil unrest, strikes, or government actions.</p>

        <h2>8. Your Responsibilities</h2>
        <p>You are responsible for ensuring you hold a valid passport, relevant visas, and any required vaccinations for travel to Sri Lanka. You must comply with the laws and regulations of Sri Lanka throughout your trip.</p>

        <h2>9. Complaints</h2>
        <p>If you have a complaint during your tour, please inform your tour guide or our local representative immediately so we can attempt to resolve the issue. Complaints raised after the tour must be submitted in writing within 28 days of your return.</p>

        <h2>10. Governing Law</h2>
        <p>These Terms of Service are governed by the laws of the Democratic Socialist Republic of Sri Lanka. Any disputes shall be subject to the exclusive jurisdiction of the courts of Sri Lanka.</p>

        <h2>11. Contact Us</h2>
        <p>For any questions regarding these terms, please contact us:</p>
        <ul>
            <li><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($cfg('contact_email', 'info@asbtours.com')) ?>"><?= htmlspecialchars($cfg('contact_email', 'info@asbtours.com')) ?></a></li>
            <li><strong>Phone:</strong> <?= htmlspecialchars($cfg('contact_phone', '+94 11 234 5678')) ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($cfg('contact_address', 'No. 15, Galle Road, Colombo 03, Sri Lanka')) ?></li>
        </ul>

    </div>

    <div id="footer-placeholder"></div>

    <script src="../assets/js/components.js?v=8"></script>
    <script src="../assets/js/navbar.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>




