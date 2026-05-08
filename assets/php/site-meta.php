<?php
// Outputs all settings meta tags read by components.js
// Requires $cfg callable to be defined before inclusion

$footerServices = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("
            SELECT title
            FROM services
            WHERE is_active = 1
            ORDER BY CASE WHEN type = 'core' THEN 0 ELSE 1 END, sort_order, id
            LIMIT 6
        ");
        $footerServices = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $e) {
        $footerServices = [];
    }
}
?>
<?php
$siteLogo = public_asset_url($cfg('site_logo', ''));
$siteFavicon = public_asset_url($cfg('site_favicon', ''));
$cacheVersion = trim((string) $cfg('cache_busted_at', ''));
if ($siteLogo !== '') {
    if ($cacheVersion !== '') {
        $siteLogo .= (str_contains($siteLogo, '?') ? '&' : '?') . 'v=' . rawurlencode($cacheVersion);
    }
}
if ($siteFavicon !== '' && $cacheVersion !== '') {
    $siteFavicon .= (str_contains($siteFavicon, '?') ? '&' : '?') . 'v=' . rawurlencode($cacheVersion);
}
$faviconPath = parse_url($siteFavicon, PHP_URL_PATH) ?: '';
$faviconType = 'image/png';
if (preg_match('/\.svg$/i', $faviconPath)) {
    $faviconType = 'image/svg+xml';
} elseif (preg_match('/\.ico$/i', $faviconPath)) {
    $faviconType = 'image/x-icon';
} elseif (preg_match('/\.webp$/i', $faviconPath)) {
    $faviconType = 'image/webp';
}
?>
    <?php if ($siteFavicon !== ''): ?>
    <link rel="icon" type="<?= htmlspecialchars($faviconType) ?>" href="<?= htmlspecialchars($siteFavicon) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFavicon) ?>">
    <meta name="msapplication-TileImage" content="<?= htmlspecialchars(absolute_site_url($siteFavicon)) ?>">
    <?php endif; ?>
    <meta name="site-logo" content="<?= htmlspecialchars($siteLogo) ?>">
    <meta name="site-name" content="<?= htmlspecialchars($cfg('site_name', 'ASB Tours')) ?>">
    <meta name="site-tagline" content="<?= htmlspecialchars($cfg('site_tagline', 'Come as a guest - Leave as a friend.')) ?>">
    <meta name="wa-number" content="<?= preg_replace('/\D/', '', $cfg('contact_whatsapp', '')) ?>">
    <meta name="site-phone" content="<?= htmlspecialchars($cfg('contact_phone', '')) ?>">
    <meta name="site-email" content="<?= htmlspecialchars($cfg('contact_email', '')) ?>">
    <meta name="site-address" content="<?= htmlspecialchars($cfg('contact_address', '')) ?>">
    <meta name="social-facebook" content="<?= htmlspecialchars($cfg('social_facebook', '')) ?>">
    <meta name="social-instagram" content="<?= htmlspecialchars($cfg('social_instagram', '')) ?>">
    <meta name="social-twitter" content="<?= htmlspecialchars($cfg('social_twitter', '')) ?>">
    <meta name="social-youtube" content="<?= htmlspecialchars($cfg('social_youtube', '')) ?>">
    <meta name="social-tripadvisor" content="<?= htmlspecialchars($cfg('social_tripadvisor', '')) ?>">
    <meta name="footer-services" content="<?= htmlspecialchars(json_encode($footerServices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">
