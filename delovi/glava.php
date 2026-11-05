<?php
/**
 * Zaglavlje svake strane. Očekuje: $naslov (string), $nav ('klijent'|'trener'|null), $aktivno (string).
 */
$nav ??= null;
$aktivno ??= '';
$ja = $nav ? korisnik() : null;
?><!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e(($naslov ?? '') !== '' ? $naslov . ' — ' . pod('sajt.naziv') : pod('sajt.naziv')) ?></title>
<meta name="theme-color" content="#15201c">
<meta name="robots" content="noindex,nofollow">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="<?= e(statika('/assets/img/ikona.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(pod('sajt.naziv')) ?>">
<link rel="preload" href="/assets/fonts/barlow-400-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/barlow-condensed-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(statika('/assets/css/app.css')) ?>">
<script src="<?= e(statika('/assets/js/app.js')) ?>" defer></script>
</head>
<body<?= $nav ? ' class="ima-nav"' : '' ?> data-csrf="<?= e(csrf_token()) ?>">
<header class="top"><div class="wrap">
  <a class="brand" href="/"><?= e(pod('sajt.naziv')) ?><small><?= e(pod('sajt.podnaslov')) ?></small></a>
  <?php if ($ja): ?>
  <details class="meni">
    <summary aria-label="Nalog"><span class="av sm"><?= e(inicijali($ja['ime'])) ?></span></summary>
    <div class="meni-lista">
      <div class="muted" style="font-size:13px;padding:4px 10px 8px"><?= e($ja['ime']) ?><br><?= e($ja['email']) ?></div>
      <a class="meni-stavka" href="/nalog">Promeni lozinku</a>
      <button type="button" class="meni-stavka" data-tema>Tamna / svetla tema</button>
      <form method="post" action="/odjava"><?= csrf_polje() ?><button class="meni-stavka">Odjavi se</button></form>
    </div>
  </details>
  <?php endif; ?>
</div></header>
<main class="wrap" id="app">
