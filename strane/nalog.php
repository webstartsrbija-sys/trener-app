<?php
/** Promena sopstvene lozinke — za klijenta i za trenera. */
$ja = korisnik();
if (!$ja) {
    preusmeri('/prijava?posle=/nalog');
}
$nav = $ja['uloga'];
$greska_forme = null;
if (post()) {
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
$naslov = 'Promeni lozinku';
$aktivno = '';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Moj nalog</span><h1>Promeni lozinku</h1><span class="muted"><?= e($ja['email']) ?></span></section>
<form class="card" method="post">
  <?= csrf_polje() ?>
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
