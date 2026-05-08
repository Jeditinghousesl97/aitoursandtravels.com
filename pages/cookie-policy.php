<?php
require_once __DIR__ . '/../admin/config/db.php';
require_once __DIR__ . '/../assets/php/seo.php';
$pdo = getPDO();
if (file_exists(__DIR__ . '/../maintenance.flag')) { include __DIR__ . '/../maintenance.php'; exit; }

$s   = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cfg = fn(string $key, string $default = '') => (isset($s[$key]) && $s[$key] !== '') ? $s[$key] : $default;
$seoTitle = 'Cookie Policy | ASB Tours Sri Lanka';
$seoDesc = 'Cookie Policy for ASB Tours Sri Lanka. Learn about the cookies we use and how to manage your preferences.';
$seoCanonical = absolute_site_url('pages/cookie-policy.php');
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
        .policy-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0 1.5rem;
            font-size: .92rem;
        }
        .policy-body table th {
            background: var(--primary);
            color: #fff;
            padding: .6rem 1rem;
            text-align: left;
        }
        .policy-body table td {
            padding: .6rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
            vertical-align: top;
        }
        .policy-body table tr:nth-child(even) td { background: #f7fafc; }
        .policy-body a { color: var(--primary); }
    </style>
</head>
<body>

    <div id="navbar-placeholder"></div>

    <section class="page-hero" style="background-image: url('../assets/images/hero/slide-3.jpg'); background-position: center 40%;">
        <div class="container">
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <i class="fa fa-chevron-right"></i>
                <span>Cookie Policy</span>
            </div>
            <h1>Cookie <span>Policy</span></h1>
            <p>Last updated: <?= date('d F Y') ?></p>
        </div>
    </section>

    <div class="policy-body">

        <p>This Cookie Policy explains what cookies are, how ASB Tours uses them on our website, and what choices you have regarding their use. By continuing to use our website, you consent to our use of cookies as described in this policy.</p>

        <h2>1. What Are Cookies?</h2>
        <p>Cookies are small text files that are stored on your device (computer, tablet, or mobile) when you visit a website. They help the website remember your actions and preferences over time, so you don't have to re-enter them each time you visit.</p>

        <h2>2. How We Use Cookies</h2>
        <p>We use cookies to:</p>
        <ul>
            <li>Keep our website functioning correctly (essential cookies)</li>
            <li>Remember your preferences and settings</li>
            <li>Understand how visitors use our website (analytics)</li>
            <li>Improve our website based on usage patterns</li>
        </ul>

        <h2>3. Types of Cookies We Use</h2>
        <table>
            <thead>
                <tr>
                    <th>Cookie Type</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Essential</strong></td>
                    <td>Required for the website to function. These include session management and security cookies.</td>
                    <td>Session</td>
                </tr>
                <tr>
                    <td><strong>Functional</strong></td>
                    <td>Remember your preferences such as language and region to provide a more personalised experience.</td>
                    <td>1 year</td>
                </tr>
                <tr>
                    <td><strong>Analytics</strong></td>
                    <td>Help us understand how visitors interact with our website by collecting and reporting anonymous information.</td>
                    <td>2 years</td>
                </tr>
                <tr>
                    <td><strong>Third-party</strong></td>
                    <td>Set by embedded content such as Google Maps. These cookies are governed by the respective third parties' policies.</td>
                    <td>Varies</td>
                </tr>
            </tbody>
        </table>

        <h2>4. Third-Party Cookies</h2>
        <p>Our website may include content from third-party services such as Google Maps and social media platforms. These services may set their own cookies on your device. We do not control these cookies and recommend reviewing the cookie policies of those third parties:</p>
        <ul>
            <li><a href="https://policies.google.com/technologies/cookies" target="_blank" rel="noopener">Google Cookie Policy</a></li>
            <li><a href="https://www.facebook.com/policies/cookies/" target="_blank" rel="noopener">Facebook Cookie Policy</a></li>
        </ul>

        <h2>5. Managing Cookies</h2>
        <p>Most web browsers allow you to manage your cookie preferences through the browser settings. You can set your browser to refuse cookies, delete existing cookies, or notify you when cookies are being set. Please note that disabling certain cookies may affect the functionality of our website.</p>
        <p>How to manage cookies in popular browsers:</p>
        <ul>
            <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Google Chrome</a></li>
            <li><a href="https://support.mozilla.org/en-US/kb/enable-and-disable-cookies-website-preferences" target="_blank" rel="noopener">Mozilla Firefox</a></li>
            <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" rel="noopener">Apple Safari</a></li>
            <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener">Microsoft Edge</a></li>
        </ul>

        <h2>6. Changes to This Policy</h2>
        <p>We may update this Cookie Policy from time to time to reflect changes in technology, legislation, or our data practices. Any changes will be posted on this page with an updated date.</p>

        <h2>7. Contact Us</h2>
        <p>If you have any questions about our use of cookies, please contact us:</p>
        <ul>
            <li><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($cfg('contact_email', 'info@asbtours.com')) ?>"><?= htmlspecialchars($cfg('contact_email', 'info@asbtours.com')) ?></a></li>
            <li><strong>Phone:</strong> <?= htmlspecialchars($cfg('contact_phone', '+94 11 234 5678')) ?></li>
        </ul>

    </div>

    <div id="footer-placeholder"></div>

    <script src="../assets/js/components.js?v=8"></script>
    <script src="../assets/js/navbar.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>




