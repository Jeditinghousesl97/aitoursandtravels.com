<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/seo.php';
$pdo = getPDO();
if (file_exists(__DIR__ . '/../maintenance.flag')) { include __DIR__ . '/../maintenance.php'; exit; }

$s   = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $key, string $default = '') => (isset($s[$key]) && $s[$key] !== '') ? $s[$key] : $default;
$seoTitle = 'Privacy Policy | ASB Tours Sri Lanka';
$seoDesc = 'Privacy Policy for ASB Tours Sri Lanka. Learn how we collect, use, and protect your personal information.';
$seoCanonical = absolute_site_url('pages/privacy-policy.php');
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

    <section class="page-hero" style="background-image: url('../assets/images/hero/slide-1.jpg'); background-position: center 40%;">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <span>Privacy Policy</span>
            </div>
            <h1>Privacy <span>Policy</span></h1>
            <p>Last updated: <?= date('d F Y') ?></p>
        </div>
    </section>

    <div class="policy-body">

        <p>Welcome to <strong>ASB Tours</strong>. We are committed to protecting your personal information and your right to privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or use our services.</p>

        <h2>1. Information We Collect</h2>
        <p>We may collect personal information that you voluntarily provide to us when you:</p>
        <ul>
            <li>Fill out a booking or inquiry form</li>
            <li>Subscribe to our newsletter</li>
            <li>Contact us by email, phone, or WhatsApp</li>
            <li>Leave a review on our website</li>
        </ul>
        <p>The types of personal information we collect include your name, email address, phone number, nationality, travel dates, and any special requests you provide.</p>

        <h2>2. How We Use Your Information</h2>
        <p>We use the information we collect to:</p>
        <ul>
            <li>Process and manage your tour bookings and inquiries</li>
            <li>Communicate with you about your travel arrangements</li>
            <li>Send you relevant travel offers and updates (with your consent)</li>
            <li>Improve our website and services</li>
            <li>Comply with legal obligations</li>
        </ul>

        <h2>3. Sharing Your Information</h2>
        <p>We do not sell, trade, or rent your personal information to third parties. We may share your information only with trusted service providers (such as hotels and transport partners) who assist in delivering your tour, and only to the extent necessary to fulfil your booking.</p>

        <h2>4. Data Security</h2>
        <p>We implement appropriate technical and organisational measures to protect your personal data against unauthorised access, alteration, disclosure, or destruction. However, no method of transmission over the internet is 100% secure, and we cannot guarantee absolute security.</p>

        <h2>5. Cookies</h2>
        <p>Our website uses cookies to enhance your browsing experience. For full details, please see our <a href="cookie-policy.php">Cookie Policy</a>.</p>

        <h2>6. Your Rights</h2>
        <p>You have the right to access, correct, or delete the personal information we hold about you. To exercise any of these rights, please contact us at <a href="mailto:<?= htmlspecialchars($cfg('contact_email', 'info@asbtours.com')) ?>"><?= htmlspecialchars($cfg('contact_email', 'info@asbtours.com')) ?></a>.</p>

        <h2>7. Third-Party Links</h2>
        <p>Our website may contain links to third-party websites. We are not responsible for the privacy practices of those sites and encourage you to review their privacy policies.</p>

        <h2>8. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. Any changes will be posted on this page with an updated date. Continued use of our website after changes constitutes acceptance of the revised policy.</p>

        <h2>9. Contact Us</h2>
        <p>If you have any questions about this Privacy Policy, please contact us:</p>
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




