<?php
/**
 * Zaglavlje svake strane. Očekuje: $naslov (string), $nav ('klijent'|'trener'|null), $aktivno (string).
 */
$nav ??= null;
$aktivno ??= '';
$ja = $nav ? korisnik() : null;
$en = jezik() === 'en';
// Dugme za jezik: za klijente i neprijavljene (trenerov deo je samo na srpskom)
$jezik_dugme = !$ja || $ja['uloga'] === 'klijent';
$ovde_put = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
// Tekstovi koje koristi app.js
$js_tekst = [];
foreach (['Čuvam…', 'Sačuvano u %s', 'Nije sačuvano: %s Pokušaću ponovo.', 'Ima nesačuvanih izmena…', 'Link je kopiran', 'Greška'] as $x) {
    $js_tekst[$x] = t($x);
}
?><!doctype html>
<html lang="<?= $en ? 'en' : 'sr-Latn' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,viewport-fit=cover">
<title><?= e(($naslov ?? '') !== '' ? t($naslov) . ' — ' . pod('sajt.naziv') : pod('sajt.naziv')) ?></title>
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
<body<?= $nav ? ' class="ima-nav"' : '' ?> data-csrf="<?= e(csrf_token()) ?>" data-jezik="<?= $en ? 'en' : 'sr' ?>">
<script type="application/json" id="js-tekst"><?= json_encode($js_tekst, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<header class="top"><div class="wrap">
  <a class="brand" href="/"><?= e(pod('sajt.naziv')) ?><small><?= $en ? 'TRAINING • NUTRITION • PROGRESS' : e(pod('sajt.podnaslov')) ?></small></a>
  <div class="row" style="gap:10px;flex-wrap:nowrap">
  <?php if ($jezik_dugme): ?>
    <?php if ($ja): ?>
    <form method="post" action="/jezik" style="margin:0"><?= csrf_polje() ?><input type="hidden" name="j" value="<?= $en ? 'sr' : 'en' ?>"><input type="hidden" name="posle" value="<?= e($ovde_put) ?>"><button class="jezik" aria-label="<?= $en ? 'Prebaci na srpski' : 'Switch to English' ?>"><?= $en ? 'SR' : 'EN' ?></button></form>
    <?php else: ?>
    <a class="jezik" href="/jezik?j=<?= $en ? 'sr' : 'en' ?>&amp;posle=<?= e(rawurlencode($ovde_put)) ?>" aria-label="<?= $en ? 'Prebaci na srpski' : 'Switch to English' ?>"><?= $en ? 'SR' : 'EN' ?></a>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($ja): ?>
  <details class="meni">
    <summary aria-label="<?= te('Nalog') ?>"><?= avatar($ja['id'], $ja['ime'], $ja['slika'] ?? null, 'sm') ?></summary>
    <div class="meni-lista">
      <div class="muted" style="font-size:13px;padding:4px 10px 8px"><?= e($ja['ime']) ?><br><?= e($ja['email']) ?></div>
      <a class="meni-stavka" href="/nalog"><?= te('Moj nalog (slika, lozinka)') ?></a>
      <button type="button" class="meni-stavka" data-tema><?= te('Tamna / svetla tema') ?></button>
      <form method="post" action="/odjava"><?= csrf_polje() ?><button class="meni-stavka"><?= te('Odjavi se') ?></button></form>
    </div>
  </details>
  <?php endif; ?>
  </div>
</div></header>
<main class="wrap" id="app">
