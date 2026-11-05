<?php
/** Postavljanje lozinke iz pozivnice ili linka za reset (jednokratni token). */
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$t = nadji_token($token);
$greska_forme = null;
if ($t && post()) {
    $l1 = (string)($_POST['lozinka'] ?? '');
    $l2 = (string)($_POST['lozinka2'] ?? '');
    if (mb_strlen($l1) < 8) {
        $greska_forme = 'Lozinka mora imati bar 8 znakova.';
    } elseif ($l1 !== $l2) {
        $greska_forme = 'Lozinke se ne poklapaju.';
    } else {
        upit('UPDATE korisnici SET lozinka_hash = ? WHERE id = ?', [password_hash($l1, PASSWORD_DEFAULT), $t['korisnik_id']]);
        iskoristi_token((int)$t['id']);
        // Stare "zapamti me" prijave više ne važe posle promene lozinke
        upit('DELETE FROM zapamcene_prijave WHERE korisnik_id = ?', [$t['korisnik_id']]);
        uspostavi_sesiju((int)$t['korisnik_id'], true);
        poruka($t['svrha'] === 'poziv' ? 'Dobro došao! Lozinka je postavljena.' : 'Nova lozinka je postavljena.');
        preusmeri('/');
    }
}
$naslov = 'Lozinka';
require KOREN . '/delovi/glava.php';
?>
<?php if (!$t): ?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Link ne važi</span><h1>Link je istekao</h1><span class="muted">Link je već iskorišćen ili je prošlo više od <?= (int)pod('bezbednost.token_sati', 48) ?> sata.</span></section>
<section class="card"><span>Zatraži novi link ili se javi <?= e(pod('sajt.trener_ime')) ?>u da ti ponovo pošalje pozivnicu.</span><a class="btn primary" href="/zaboravljena-lozinka">Zatraži novi link</a></section>
<?php else: ?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= $t['svrha'] === 'poziv' ? 'Dobro došao' : 'Nova lozinka' ?></span><h1>Zdravo, <?= e(ime_kratko($t['ime'])) ?></h1><span class="muted">Izaberi lozinku za nalog <?= e($t['email']) ?>.</span></section>
<form class="card" method="post" action="/lozinka">
  <?= csrf_polje() ?><input type="hidden" name="t" value="<?= e($token) ?>">
  <?php if ($greska_forme): ?><div class="alert bad card" role="alert"><?= e($greska_forme) ?></div><?php endif; ?>
  <input type="email" name="email" value="<?= e($t['email']) ?>" autocomplete="username" hidden>
  <div class="form">
    <label class="full">Nova lozinka (bar 8 znakova)<input class="tin" type="password" name="lozinka" minlength="8" autocomplete="new-password" required autofocus></label>
    <label class="full">Ponovi lozinku<input class="tin" type="password" name="lozinka2" minlength="8" autocomplete="new-password" required></label>
  </div>
  <button class="btn primary block">Sačuvaj lozinku</button>
</form>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
