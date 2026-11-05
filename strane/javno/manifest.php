<?php
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo json_encode([
    'name' => pod('sajt.naziv'),
    'short_name' => pod('sajt.naziv'),
    'description' => 'Trening, ishrana i napredak',
    'lang' => 'sr-Latn',
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#f3f5f2',
    'theme_color' => '#15201c',
    'icons' => [
        ['src' => '/assets/img/ikona-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/assets/img/ikona-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/assets/img/ikona-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
