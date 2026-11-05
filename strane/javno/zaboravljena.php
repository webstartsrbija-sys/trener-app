<?php
$poslato = false;
if (post()) {
    $email = mb_strtolower(unos($_POST['email'] ?? '', 190));
    // Isto ograničenje kao kod prijave, da se forma ne koristi za slanje gomile mejlova
    if (previse_pokusaja()) {
        poruka('Previše pokušaja. Sačekaj malo pa probaj ponovo.', 'greska');
        preusmeri('/zaboravljena-lozinka');
    }
    zabelezi_pokusaj();
    $k = red('SELECT id, ime, email FROM korisnici WHERE email = ? AND aktivan = 1', [$email]);
    if ($k) {
        require_once KOREN . '/app/mejl.php';
        mejl_reset($k, napravi_token((int)$k['id'], 'reset'));
    }
    // Isti odgovor i kad nalog ne postoji — ne odajemo ko ima nalog
    $poslato = true;
}
$naslov = 'Zaboravljena lozinka';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Nova lozinka</span><h1>Zaboravljena lozinka</h1></section>
<?php if ($poslato): ?>
<section class="card"><b>Proveri mejl.</b><span class="muted">Ako nalog sa tom adresom postoji, poslali smo link za novu lozinku. Link važi <?= (int)pod('bezbednost.token_sati', 48) ?> sata. Proveri i nepoželjnu poštu (spam).</span><a class="btn" href="/prijava">Nazad na prijavu</a></section>
<?php else: ?>
<form class="card" method="post">
  <?= csrf_polje() ?>
  <p class="muted" style="margin:0">Upiši email kojim se prijavljuješ. Poslaćemo ti link za postavljanje nove lozinke.</p>
  <div class="form"><label class="full">Email<input class="tin" type="email" name="email" autocomplete="username" required autofocus></label></div>
  <button class="btn primary block">Pošalji link</button>
  <a class="link" href="/prijava" style="align-self:center">Nazad na prijavu</a>
</form>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
