<?php

if (!function_exists('theme_setting_definitions')) {
    function theme_setting_definitions(): array
    {
        return [
            'theme_primary' => [
                'label' => 'Primary',
                'css_var' => '--primary',
                'default' => '#0077B6',
                'description' => 'Main brand color for buttons and highlights.',
            ],
            'theme_primary_dark' => [
                'label' => 'Primary Dark',
                'css_var' => '--primary-dark',
                'default' => '#005F92',
                'description' => 'Hover and darker button state.',
            ],
            'theme_primary_light' => [
                'label' => 'Primary Light',
                'css_var' => '--primary-light',
                'default' => '#00B4D8',
                'description' => 'Secondary highlight tone for gradients.',
            ],
            'theme_secondary' => [
                'label' => 'Secondary',
                'css_var' => '--secondary',
                'default' => '#00B4D8',
                'description' => 'Used across badges and supporting accents.',
            ],
            'theme_accent' => [
                'label' => 'Accent',
                'css_var' => '--accent',
                'default' => '#ADE8F4',
                'description' => 'Soft accent backgrounds and subtle emphasis.',
            ],
            'theme_dark' => [
                'label' => 'Dark',
                'css_var' => '--dark',
                'default' => '#03045E',
                'description' => 'Primary dark brand shade.',
            ],
            'theme_dark_2' => [
                'label' => 'Dark 2',
                'css_var' => '--dark-2',
                'default' => '#023E8A',
                'description' => 'Supporting dark gradient tone.',
            ],
            'theme_dark_3' => [
                'label' => 'Dark 3',
                'css_var' => '--dark-3',
                'default' => '#0A1628',
                'description' => 'Deepest UI dark shade.',
            ],
            'theme_light' => [
                'label' => 'Light',
                'css_var' => '--light',
                'default' => '#F0F9FF',
                'description' => 'Light section background.',
            ],
            'theme_light_2' => [
                'label' => 'Light 2',
                'css_var' => '--light-2',
                'default' => '#E8F4FD',
                'description' => 'Alternative soft background color.',
            ],
            'theme_text' => [
                'label' => 'Text',
                'css_var' => '--text',
                'default' => '#2D3748',
                'description' => 'Main text color.',
            ],
            'theme_text_light' => [
                'label' => 'Text Light',
                'css_var' => '--text-light',
                'default' => '#718096',
                'description' => 'Muted body text and helper copy.',
            ],
            'theme_text_muted' => [
                'label' => 'Text Muted',
                'css_var' => '--text-muted',
                'default' => '#A0AEC0',
                'description' => 'Less prominent labels and metadata.',
            ],
            'theme_border' => [
                'label' => 'Border',
                'css_var' => '--border',
                'default' => '#E2E8F0',
                'description' => 'Borders and separators.',
            ],
            'theme_hero_overlay_top' => [
                'label' => 'Hero Overlay Top',
                'css_var' => '--hero-overlay-top-color',
                'default' => '#03045E',
                'description' => 'Top fade color used in homepage hero overlay.',
            ],
            'theme_hero_overlay_mid' => [
                'label' => 'Hero Overlay Middle',
                'css_var' => '--hero-overlay-mid-color',
                'default' => '#0077B6',
                'description' => 'Middle gradient color used in homepage hero overlay.',
            ],
            'theme_hero_overlay_end' => [
                'label' => 'Hero Overlay End',
                'css_var' => '--hero-overlay-end-color',
                'default' => '#00B4D8',
                'description' => 'End gradient color used in homepage hero overlay.',
            ],
        ];
    }
}

if (!function_exists('theme_normalize_hex_color')) {
    function theme_normalize_hex_color(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        $fallback = strtoupper($fallback);

        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^#([A-Fa-f0-9]{3})$/', $value, $matches)) {
            $chars = strtoupper($matches[1]);
            return '#' . $chars[0] . $chars[0] . $chars[1] . $chars[1] . $chars[2] . $chars[2];
        }

        if (preg_match('/^#([A-Fa-f0-9]{6})$/', $value)) {
            return strtoupper($value);
        }

        return $fallback;
    }
}

if (!function_exists('theme_hex_to_rgb')) {
    function theme_hex_to_rgb(string $hex): array
    {
        $hex = ltrim(theme_normalize_hex_color($hex, '#000000'), '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
