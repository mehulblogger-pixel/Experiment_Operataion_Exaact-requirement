<?php
// PWA manifest — served as PHP so the app name and theme colour follow Settings.
// May be reached directly (Apache serves the real file) or included by index.php,
// which has already loaded these — require_once keeps both paths working.
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$name = function_exists('app_name') ? app_name() : 'Inspection Ops';
$brand = function_exists('setting_get') ? (setting_get('c_primary', '') ?: '#1e40af') : '#1e40af';
// A simple inline SVG icon keeps the install prompt working with no binary assets.
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192"><rect width="192" height="192" rx="34" fill="' . htmlspecialchars($brand, ENT_QUOTES) . '"/><text x="96" y="128" font-family="Helvetica,Arial,sans-serif" font-size="96" font-weight="bold" fill="#fff" text-anchor="middle">IR</text></svg>';
$icon = 'data:image/svg+xml;base64,' . base64_encode($svg);
echo json_encode([
    'name' => $name,
    'short_name' => mb_substr($name, 0, 14),
    'description' => 'Inspection documentation, reporting and endorsement — works in the field.',
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#f4f6f9',
    'theme_color' => $brand,
    'icons' => [
        ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
        ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
