<?php
if (korisnik()) {
    preusmeri('/');
}
$greska_prijave = null;
$email = '';
if (post()) {
    $email = unos($_POST['email'] ?? '', 190);
    $greska_prijave = prijavi($email, (string)($_POST['lozinka'] ?? ''), !empty($_POST['zapamti']));
    if ($greska_prijave === null) {
        $posle = (string)($_GET['posle'] ?? '/');
        // Samo putanja unutar aplikacije — nikad tuđ sajt
        preusmeri(preg_match('~^/(?!/)[A-Za-z0-9/_\-?=&%.]*$~', $posle) ? $posle : '/');
    }
}
$naslov = 'Prijava';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= te('Dobro došao nazad') ?></span><h1><?= te('Prijava') ?></h1></section>
<form class="card" method="post">
  <?= csrf_polje() ?>
  <?php if ($greska_prijave): ?><div class="alert bad card" role="alert"><?= e($greska_prijave) ?></div><?php endif; ?>
  <div class="form">
    <label class="full"><?= te('Email') ?><input class="tin" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus></label>
    <label class="full"><?= te('Lozinka') ?><input class="tin" type="password" name="lozinka" autocomplete="current-password" required></label>
  </div>
  <label class="row potvrda"><input type="checkbox" name="zapamti" value="1" checked> <?= te('Zapamti me na ovom uređaju (%s dana)', (int)pod('bezbednost.zapamti_dana', 30)) ?></label>
  <button class="btn primary block"><?= te('Prijavi se') ?></button>
  <a class="link" href="/zaboravljena-lozinka" style="align-self:center"><?= te('Zaboravljena lozinka?') ?></a>
</form>
<p class="muted" style="text-align:center;font-size:13px;margin:0"><?= te('Nalog pravi trener. Ako ga još nemaš, javi se %su.', pod('sajt.trener_ime')) ?></p>
<?php require KOREN . '/delovi/podnozje.php';
