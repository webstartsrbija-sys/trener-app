<?php
require __DIR__ . '/_osnova.php';

$greske = [];
$staro = [];
if (post()) {
    $staro = $_POST;
    $ime = unos($_POST['ime'] ?? '', 120);
    $email = mb_strtolower(unos($_POST['email'] ?? '', 190));
    $cilj = unos($_POST['cilj'] ?? '', 80);
    $pocetak = ispravan_datum($_POST['datum_pocetka'] ?? null) ? $_POST['datum_pocetka'] : danas();
    $tn = max(1, min(7, unos_int($_POST['treninga_nedeljno'] ?? 3)));
    $dana = max(0, min(400, unos_int($_POST['clanarina_dana'] ?? 30)));
    $sablon = unos_int($_POST['sablon_id'] ?? 0);
    if ($ime === '') {
        $greske[] = 'Upiši ime i prezime.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $greske[] = 'Email nije ispravan.';
    } elseif (vrednost('SELECT 1 FROM korisnici WHERE email = ?', [$email])) {
        $greske[] = 'Nalog sa tim emailom već postoji.';
    }
    if ($sablon && !vrednost('SELECT 1 FROM programi WHERE id = ? AND klijent_id IS NULL', [$sablon])) {
        $sablon = 0;
    }
    if (!$greske) {
        $r = napravi_klijenta([
            'ime' => $ime, 'email' => $email, 'cilj' => $cilj, 'datum_pocetka' => $pocetak, 'treninga_nedeljno' => $tn,
            'clanarina_do' => $dana > 0 ? dan_plus(danas(), $dana) : null, 'sablon_id' => $sablon,
            'jezik' => ($_POST['jezik'] ?? '') === 'en' ? 'en' : 'sr',
        ]);
        require_once KOREN . '/app/mejl.php';
        $poslat = mejl_pozivnica($r['korisnik'], $r['token']);
        $_SESSION['poziv_link'] = ['klijent' => $r['id'], 'link' => adresa('/lozinka?t=' . $r['token']), 'poslat' => $poslat];
        poruka($poslat ? 'Nalog za ' . ime_kratko($ime) . ' je napravljen, pozivnica je poslata' : 'Nalog je napravljen, ali mejl nije otišao — pošalji link ručno', $poslat ? 'ok' : 'greska');
        preusmeri('/trener/klijent/' . $r['id']);
    }
}

$trazi = unos($_GET['q'] ?? '', 60);
$arhiva = ($_GET['arhiva'] ?? '') === '1';
$lista = lista_klijenata($arhiva, $trazi);
$naslov = 'Klijenti';
$aktivno = 'klijenti';
require KOREN . '/delovi/glava.php';
$need = count(array_filter($lista, fn($k) => $k['signal']['nivo'] < 2));
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= e(datum_naslov(danas())) ?></span><h1><?= count($lista) ?> <?= $arhiva ? 'u arhivi' : (count($lista) === 1 ? 'klijent' : 'klijenata') ?></h1>
<?php if (!$arhiva): ?><span class="muted"><?= $need ? $need . ' traže tvoju pažnju danas.' : 'Svi su na planu.' ?></span><?php endif; ?></section>
<div class="row between"><h2>Klijenti</h2><?php if (!$arhiva): ?><button type="button" class="btn primary small" data-otvori="novi-klijent">+ Novi klijent</button><?php endif; ?></div>
<form class="row" method="get" role="search">
  <?php if ($arhiva): ?><input type="hidden" name="arhiva" value="1"><?php endif; ?>
  <input class="tin" type="search" name="q" value="<?= e($trazi) ?>" placeholder="Pretraga po imenu" style="flex:1" aria-label="Pretraga po imenu">
  <button class="btn">Traži</button>
</form>
<div class="stack">
<?php if (!$lista): ?><p class="muted"><?= $trazi !== '' ? 'Nema klijenata za tu pretragu.' : ($arhiva ? 'Arhiva je prazna.' : 'Još nema klijenata. Napravi prvog dugmetom „+ Novi klijent“.') ?></p><?php endif; ?>
<?php foreach ($lista as $k): ?>
  <a class="card client" href="/trener/klijent/<?= $k['id'] ?>">
    <?= avatar($k['korisnik_id'], $k['ime'], $k['slika']) ?>
    <div class="info"><div class="row between"><span class="name"><?= e($k['ime']) ?></span><span class="muted" style="font-size:13px">Ned. <?= nedelja_programa($k) ?></span></div>
      <div class="muted" style="font-size:14px"><?= e($k['cilj'] ?: '—') ?> • doslednost <?= $k['doslednost'] !== null ? $k['doslednost'] . '%' : '—' ?><?= $k['promena_kg'] !== null ? ' • ' . broj_znak($k['promena_kg']) . ' kg' : '' ?></div>
      <div style="margin-top:6px" class="row"><span class="chip <?= $k['signal']['t'] ?>"><?= e($k['signal']['m']) ?></span><?= clanarina_chip($k) ?><?= $k['jezik'] === 'en' ? '<span class="chip mute">EN</span>' : '' ?>
      <?php foreach (oznake($k) as $o): ?><span class="chip mute"><?= e($o) ?></span><?php endforeach; ?></div></div>
  </a>
<?php endforeach; ?>
</div>
<a class="link" href="/trener/klijenti<?= $arhiva ? '' : '?arhiva=1' ?>" style="align-self:center"><?= $arhiva ? '← Aktivni klijenti' : 'Arhivirani klijenti' ?></a>

<div class="sheet" id="novi-klijent"<?= $greske ? '' : ' hidden' ?> data-sheet>
<form class="card" method="post" action="/trener/klijenti"><h2>Novi klijent</h2>
  <?= csrf_polje() ?>
  <?php foreach ($greske as $g): ?><div class="alert bad card" role="alert"><?= e($g) ?></div><?php endforeach; ?>
  <div class="form">
    <label class="full">Ime i prezime<input class="tin" name="ime" required maxlength="120" value="<?= e($staro['ime'] ?? '') ?>" placeholder="npr. Jovana Marković"></label>
    <label class="full">Email<input class="tin" name="email" type="email" required value="<?= e($staro['email'] ?? '') ?>" placeholder="jovana@primer.rs"></label>
    <label>Cilj<select class="tin" name="cilj"><?php foreach (CILJEVI as $c): ?><option<?= ($staro['cilj'] ?? '') === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
    <label>Treninga nedeljno<input class="tin" name="treninga_nedeljno" type="number" min="1" max="7" value="<?= e($staro['treninga_nedeljno'] ?? 3) ?>"></label>
    <label>Datum početka<input class="tin" name="datum_pocetka" type="date" value="<?= e($staro['datum_pocetka'] ?? danas()) ?>"></label>
    <label>Članarina (dana)<input class="tin" name="clanarina_dana" type="number" min="0" max="400" value="<?= e($staro['clanarina_dana'] ?? pod('clanarina.produzenje_dana', 30)) ?>"></label>
    <label class="full">Jezik aplikacije i mejlova<select class="tin" name="jezik"><option value="sr">Srpski</option><option value="en"<?= ($staro['jezik'] ?? '') === 'en' ? ' selected' : '' ?>>English (strani klijent)</option></select></label>
    <label class="full">Početni program<select class="tin" name="sablon_id"><option value="0">Prazan program</option>
      <?php foreach (sabloni() as $s): ?><option value="<?= (int)$s['id'] ?>">Šablon: <?= e($s['naziv']) ?> (<?= (int)$s['treninga'] ?> tr.)</option><?php endforeach; ?></select></label>
  </div>
  <p class="muted" style="margin:0;font-size:13px">Klijent dobija mejl sa linkom da postavi lozinku (važi <?= (int)pod('bezbednost.token_sati', 48) ?> sata).</p>
  <div class="row"><button type="button" class="btn" data-zatvori style="flex:1">Otkaži</button><button class="btn primary" style="flex:2">Napravi nalog</button></div>
</form></div>
<?php require KOREN . '/delovi/podnozje.php';
