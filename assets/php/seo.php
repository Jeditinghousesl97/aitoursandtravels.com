<?php
if (!function_exists('nt_render_seo_tags')) {
    function nt_render_seo_tags(array $data = []): void
    {
        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $canonical = trim((string)($data['canonical'] ?? current_url()));
        $image = trim((string)($data['image'] ?? ''));
        $type = trim((string)($data['type'] ?? 'website'));
        $robots = trim((string)($data['robots'] ?? 'index,follow'));
        $siteName = trim((string)($data['site_name'] ?? 'ASB Tours'));
        $twitterCard = trim((string)($data['twitter_card'] ?? 'summary_large_image'));
        $structuredData = $data['structured_data'] ?? [];

        if ($image !== '') {
            $image = absolute_site_url($image);
        }
        $canonical = $canonical !== '' ? $canonical : current_url();
        ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <meta name="robots" content="<?= htmlspecialchars($robots) ?>">
    <meta property="og:locale" content="en_US">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($type) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <?php if ($image !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($image) ?>">
    <meta name="twitter:card" content="<?= htmlspecialchars($twitterCard) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($image) ?>">
    <?php else: ?>
    <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
        <?php

        foreach ((array)$structuredData as $schema) {
            if (!is_array($schema) || $schema === []) {
                continue;
            }
            ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
            <?php
        }
    }
}
