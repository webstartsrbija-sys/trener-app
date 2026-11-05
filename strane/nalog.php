<?php
/** Moj nalog: profilna slika i promena lozinke — za klijenta i za trenera. */
$ja = korisnik();
if (!$ja) {
    preusmeri('/prijava?posle=/nalog');
}
$nav = $ja['uloga'];
$greska_forme = null;
if (post()) {
    $akcija = $_POST['akcija'] ?? 'lozinka';
    if ($akcija === 'slika') {
        $r = primi_profilnu();
        if (isset($r['greska'])) {
            poruka($r['greska'], 'greska');
        } else {
            postavi_profilnu($ja['id'], $r['ime']);
            poruka('Profilna slika je postavljena');
        }
        preusmeri('/nalog');
    }
    if ($akcija === 'slika_ukloni') {
        postavi_profilnu($ja['id'], null);
        poruka('Profilna slika je uklonjena');
        preusmeri('/nalog');
    }
    $trenutna = (string)($_POST['trenutna'] ?? '');
    $l1 = (string)($_POST['lozinka'] ?? '');
    $hash = (string)vrednost('SELECT lozinka_hash FROM korisnici WHERE id = ?', [$ja['id']]);
    if (previse_pokusaja()) {
        $greska_forme = 'Previše pogrešnih pokušaja. Sačekaj ' . (int)pod('bezbednost.period_minuta', 15) . ' minuta pa probaj ponovo.';
    } elseif (!password_verify($trenutna, $hash)) {
        zabelezi_pokusaj();
        $greska_forme = 'Trenutna lozinka nije tačna.';
    } elseif ($trenutna === $l1) {
        $greska_forme = 'Nova lozinka mora biti drugačija od trenutne.';
    } else {
        $greska_forme = greska_nove_lozinke($l1, (string)($_POST['lozinka2'] ?? ''));
    }
    if ($greska_forme === null) {
        postavi_lozinku($ja['id'], $l1, true);
        poruka('Lozinka je promenjena. Na drugim uređajima se prijavi novom lozinkom.');
        preusmeri('/nalog');
    }
}
$naslov = 'Moj nalog';
$aktivno = '';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Moj nalog</span><h1><?= e($ja['ime']) ?></h1><span class="muted"><?= e($ja['email']) ?></span></section>
<form class="card" method="post" enctype="multipart/form-data"><?= csrf_polje() ?><h2>Profilna slika</h2>
  <div class="row" style="gap:16px;flex-wrap:nowrap">
    <?= avatar($ja['id'], $ja['ime'], $ja['slika'], 'lg') ?>
    <div class="stack" style="gap:8px;min-width:0;flex:1">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= SLIKA_MAX_MB * 1048576 ?>">
      <input class="tin" type="file" name="slika" accept="image/jpeg,image/png,image/webp" required aria-label="Izaberi sliku">
      <div class="row">
        <button class="btn primary small" name="akcija" value="slika">Sačuvaj sliku</button>
        <?php if ($ja['slika']): ?><button class="link" name="akcija" value="slika_ukloni" formnovalidate data-potvrdi="Ukloniti profilnu sliku?" style="color:var(--bad)">Ukloni</button><?php endif; ?>
      </div>
    </div>
  </div>
  <p class="muted" style="margin:0;font-size:13px">JPG, PNG ili WebP do <?= SLIKA_MAX_MB ?> MB. Slika se iseče na kvadrat (sredina) i smanji. <?= $ja['uloga'] === 'klijent' ? 'Vidiš je ti i ' . e(pod('sajt.trener_ime')) . ' — drugi klijenti je ne vide.' : 'Klijenti vide tvoju sliku.' ?></p>
</form>
<form class="card" method="post" id="lozinka"><h2>Promeni lozinku</h2>
  <?= csrf_polje() ?><input type="hidden" name="akcija" value="lozinka">
  <?php if ($greska_forme): ?><div class="alert bad card" role="alert"><?= e($greska_forme) ?></div><?php endif; ?>
  <input type="email" name="email" value="<?= e($ja['email']) ?>" autocomplete="username" hidden>
  <div class="form">
    <label class="full">Trenutna lozinka<input class="tin" type="password" name="trenutna" autocomplete="current-password" required></label>
    <label class="full">Nova lozinka (bar 8 znakova)<input class="tin" type="password" name="lozinka" minlength="8" autocomplete="new-password" required></label>
    <label class="full">Ponovi novu lozinku<input class="tin" type="password" name="lozinka2" minlength="8" autocomplete="new-password" required></label>
  </div>
  <button class="btn primary block">Sačuvaj novu lozinku</button>
  <p class="muted" style="margin:0;font-size:13px">Posle promene ostaješ prijavljen na ovom uređaju, a na svim ostalim se odjavljuješ. Ne sećaš se trenutne lozinke? Odjavi se i izaberi „Zaboravljena lozinka?“<?= $ja['uloga'] === 'klijent' ? ' ili se javi ' . e(pod('sajt.trener_ime')) . 'u.' : '.' ?></p>
</form>
<?php require KOREN . '/delovi/podnozje.php';
