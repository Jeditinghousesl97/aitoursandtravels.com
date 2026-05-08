<?php
require_once __DIR__ . '/theme-settings.php';

if (!isset($cfg) || !is_callable($cfg)) {
    return;
}

$themeSettings = theme_setting_definitions();
$themeValues = [];

foreach ($themeSettings as $key => $meta) {
    $themeValues[$meta['css_var']] = theme_normalize_hex_color($cfg($key, $meta['default']), $meta['default']);
}

[$primaryR, $primaryG, $primaryB] = theme_hex_to_rgb($themeValues['--primary']);
[$darkR, $darkG, $darkB] = theme_hex_to_rgb($themeValues['--dark']);
[$heroTopR, $heroTopG, $heroTopB] = theme_hex_to_rgb($themeValues['--hero-overlay-top-color']);
[$heroMidR, $heroMidG, $heroMidB] = theme_hex_to_rgb($themeValues['--hero-overlay-mid-color']);
[$heroEndR, $heroEndG, $heroEndB] = theme_hex_to_rgb($themeValues['--hero-overlay-end-color']);
?>
    <style id="site-theme-overrides">
        :root {
            <?php foreach ($themeValues as $cssVar => $value): ?>
            <?= htmlspecialchars($cssVar) ?>: <?= htmlspecialchars($value) ?>;
            <?php endforeach; ?>
            --grad-primary: linear-gradient(135deg, <?= htmlspecialchars($themeValues['--primary']) ?> 0%, <?= htmlspecialchars($themeValues['--primary-light']) ?> 100%);
            --grad-dark: linear-gradient(135deg, <?= htmlspecialchars($themeValues['--dark']) ?> 0%, <?= htmlspecialchars($themeValues['--dark-2']) ?> 100%);
            --grad-hero: linear-gradient(to bottom, rgba(<?= $darkR ?>,<?= $darkG ?>,<?= $darkB ?>,0.55) 0%, rgba(<?= $primaryR ?>,<?= $primaryG ?>,<?= $primaryB ?>,0.35) 100%);
            --hero-overlay-top: rgba(<?= $heroTopR ?>,<?= $heroTopG ?>,<?= $heroTopB ?>,0.70);
            --hero-overlay-main-start: rgba(<?= $heroTopR ?>,<?= $heroTopG ?>,<?= $heroTopB ?>,0.65);
            --hero-overlay-main-mid: rgba(<?= $heroMidR ?>,<?= $heroMidG ?>,<?= $heroMidB ?>,0.40);
            --hero-overlay-main-end: rgba(<?= $heroEndR ?>,<?= $heroEndG ?>,<?= $heroEndB ?>,0.25);
            --shadow-xs: 0 1px 4px rgba(<?= $primaryR ?>,<?= $primaryG ?>,<?= $primaryB ?>,0.08);
            --shadow-sm: 0 2px 12px rgba(<?= $primaryR ?>,<?= $primaryG ?>,<?= $primaryB ?>,0.10);
            --shadow-md: 0 6px 28px rgba(<?= $primaryR ?>,<?= $primaryG ?>,<?= $primaryB ?>,0.14);
            --shadow-lg: 0 12px 45px rgba(<?= $primaryR ?>,<?= $primaryG ?>,<?= $primaryB ?>,0.18);
        }
    </style>
