<?php
require_once __DIR__ . '/admin/config/db.php';

http_response_code(404);

$pdo = getPDO();
$settings = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$whatsAppNumber = preg_replace('/\D/', '', (string)($settings['contact_whatsapp'] ?? ''));

$title = 'Page Not Found | ASB Tours Sri Lanka';
$description = 'The page you are looking for could not be found. Explore ASB Tours Sri Lanka packages, services, blog, and gallery instead.';
$canonical = absolute_site_url('404.php');
$image = 'assets/images/logo.png';

require_once __DIR__ . '/assets/php/seo.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <title><?= htmlspecialchars($title) ?></title>
    <?php nt_render_seo_tags([
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'image' => $image,
        'type' => 'website',
        'robots' => 'noindex,follow',
        'structured_data' => [[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => '404 Page',
            'url' => $canonical,
            'description' => $description,
            'isPartOf' => absolute_site_url('/'),
        ]],
    ]); ?>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <style>
        body { margin: 0; font-family: 'Poppins', sans-serif; color: #1f2937; background: linear-gradient(180deg, #f8fafc 0%, #eef7ff 100%); }
        .not-found { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
        .card { max-width: 720px; text-align: center; background: rgba(255,255,255,0.92); border: 1px solid rgba(3, 4, 94, 0.08); border-radius: 24px; padding: 3rem 2rem; box-shadow: 0 25px 80px rgba(15, 23, 42, 0.08); }
        .eyebrow { display: inline-block; padding: 0.45rem 0.9rem; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
        h1 { margin: 1rem 0 0.75rem; font-family: 'Playfair Display', serif; font-size: clamp(2.2rem, 6vw, 4rem); color: #03045e; }
        p { margin: 0 auto 1.5rem; max-width: 42rem; color: #475569; line-height: 1.7; }
        .actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.9rem 1.3rem; border-radius: 999px; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #fff; color: #0f172a; border: 1px solid rgba(15, 23, 42, 0.12); }
        .float-wa {
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 9999;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #25D366;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            font-size: 1.6rem;
            line-height: 1;
        }
        .float-wa:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <main class="not-found">
        <section class="card">
            <span class="eyebrow">404 Error</span>
            <h1>That page has moved or no longer exists.</h1>
            <p>The URL you opened does not match a live page on ASB Tours. You can head back to the homepage or jump straight into our Sri Lanka packages.</p>
            <div class="actions">
                <a class="btn btn-primary" href="<?= htmlspecialchars(site_url()) ?>">Back to Home</a>
                <a class="btn btn-secondary" href="<?= htmlspecialchars(site_url('pages/packages.php')) ?>">View Packages</a>
            </div>
        </section>
    </main>
    <?php if ($whatsAppNumber !== ''): ?>
    <a class="float-wa" href="https://wa.me/<?= htmlspecialchars($whatsAppNumber) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp" title="Chat on WhatsApp">
        <span>W</span>
    </a>
    <?php endif; ?>
</body>
</html>
