<?php
/** Postavljanje lozinke iz pozivnice ili linka za reset (jednokratni token). */
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$tok = nadji_token($token);
// Strana je na jeziku klijenta kome je link poslat (osim ako je sam izabrao drugi)
if ($tok && !isset($_COOKIE[KOLACIC_JEZIK]) && ($tok['jezik'] ?? 'sr') === 'en') {
    jezik_stek('en');
}
$greska_forme = null;
if ($tok && post()) {
    $l1 = (string)($_POST['lozinka'] ?? '');
    $l2 = (string)($_POST['lozinka2'] ?? '');
    $greska_forme = greska_nove_lozinke($l1, $l2);
    if ($greska_forme === null) {
        // Postavlja lozinku, poništava token i stare "zapamti me" prijave
        postavi_lozinku((int)$tok['korisnik_id'], $l1);
        uspostavi_sesiju((int)$tok['korisnik_id'], true);
        poruka(na_jeziku((string)($tok['jezik'] ?? 'sr'), fn() => $tok['svrha'] === 'poziv' ? t('Dobro došao! Lozinka je postavljena.') : t('Nova lozinka je postavljena.')));
        preusmeri('/');
    }
}
$naslov = 'Lozinka';
require KOREN . '/delovi/glava.php';
?>
<?php if (!$tok): ?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= te('Link ne važi') ?></span><h1><?= te('Link je istekao') ?></h1><span class="muted"><?= te('Link je već iskorišćen ili je prošlo više od %s sata.', (int)pod('bezbednost.token_sati', 48)) ?></span></section>
<section class="card"><span><?= te('Zatraži novi link ili se javi %su da ti ponovo pošalje pozivnicu.', pod('sajt.trener_ime')) ?></span><a class="btn primary" href="/zaboravljena-lozinka"><?= te('Zatraži novi link') ?></a></section>
<?php else: ?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= $tok['svrha'] === 'poziv' ? te('Dobro došao') : te('Nova lozinka') ?></span><h1><?= te('Zdravo, %s', ime_kratko($tok['ime'])) ?></h1><span class="muted"><?= te('Izaberi lozinku za nalog %s.', $tok['email']) ?></span></section>
<form class="card" method="post" action="/lozinka">
  <?= csrf_polje() ?><input type="hidden" name="t" value="<?= e($token) ?>">
  <?php if ($greska_forme): ?><div class="alert bad card" role="alert"><?= e($greska_forme) ?></div><?php endif; ?>
  <input type="email" name="email" value="<?= e($tok['email']) ?>" autocomplete="username" hidden>
  <div class="form">
    <label class="full"><?= te('Nova lozinka (bar 8 znakova)') ?><input class="tin" type="password" name="lozinka" minlength="8" autocomplete="new-password" required autofocus></label>
    <label class="full"><?= te('Ponovi lozinku') ?><input class="tin" type="password" name="lozinka2" minlength="8" autocomplete="new-password" required></label>
  </div>
  <button class="btn primary block"><?= te('Sačuvaj lozinku') ?></button>
</form>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
