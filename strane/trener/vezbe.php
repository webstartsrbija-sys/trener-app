<?php
require __DIR__ . '/_osnova.php';

if (post()) {
    $akcija = $_POST['akcija'] ?? '';
    $vid = unos_int($_POST['id'] ?? 0);
    $naziv = unos($_POST['naziv'] ?? '', 120);
    $yt = unos($_POST['youtube'] ?? '', 255);
    $teh = unos($_POST['tehnika'] ?? '', 2000);
    if ($yt !== '' && !preg_match('~^https://(www\.|m\.)?(youtube\.com|youtu\.be)/~i', $yt)) {
        poruka('YouTube link mora da počinje sa https://youtube.com ili https://youtu.be', 'greska');
        preusmeri('/trener/vezbe' . ($vid ? '#vz' . $vid : ''));
    }
    if (in_array($akcija, ['dodaj', 'sacuvaj'], true) && $naziv === '') {
        poruka('Upiši naziv vežbe.', 'greska');
        preusmeri('/trener/vezbe');
    }
    if (in_array($akcija, ['dodaj', 'sacuvaj'], true)
        && vrednost('SELECT 1 FROM vezbe WHERE naziv = ? AND id <> ?', [$naziv, $akcija === 'sacuvaj' ? $vid : 0])) {
        poruka('Vežba sa tim nazivom već postoji.', 'greska');
        preusmeri('/trener/vezbe');
    }
    switch ($akcija) {
        case 'dodaj':
            upit('INSERT INTO vezbe (naziv, youtube, tehnika, kreirano) VALUES (?, ?, ?, ?)', [$naziv, $yt, $teh, sada()]);
            poruka('Vežba je dodata u bazu');
            preusmeri('/trener/vezbe#vz' . poslednji_id());
        case 'sacuvaj':
            upit('UPDATE vezbe SET naziv = ?, youtube = ?, tehnika = ? WHERE id = ?', [$naziv, $yt, $teh, $vid]);
            poruka('Vežba je sačuvana');
            preusmeri('/trener/vezbe#vz' . $vid);
        case 'sakrij':
        case 'prikazi':
            upit('UPDATE vezbe SET aktivna = ? WHERE id = ?', [$akcija === 'prikazi' ? 1 : 0, $vid]);
            poruka($akcija === 'prikazi' ? 'Vežba je ponovo u izboru' : 'Vežba je sakrivena iz izbora (postojeći programi je zadržavaju)');
            preusmeri('/trener/vezbe#vz' . $vid);
        case 'obrisi':
            if (vrednost('SELECT 1 FROM trening_vezbe WHERE vezba_id = ? LIMIT 1', [$vid])) {
                poruka('Vežba se koristi u programu — možeš samo da je sakriješ.', 'greska');
            } else {
                upit('DELETE FROM vezbe WHERE id = ?', [$vid]);
                poruka('Vežba je obrisana');
            }
            preusmeri('/trener/vezbe');
    }
    greska(400);
}

$trazi = unos($_GET['q'] ?? '', 60);
$lista = $trazi !== ''
    ? redovi('SELECT * FROM vezbe WHERE naziv LIKE ? ORDER BY aktivna DESC, naziv', ['%' . addcslashes($trazi, '%_\\') . '%'])
    : redovi('SELECT * FROM vezbe ORDER BY aktivna DESC, naziv');
$naslov = 'Baza vežbi';
$aktivno = 'vezbe';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Samo trener</span><h1>Baza vežbi</h1><span class="muted">Dodaj YouTube link jednom, a dugme Video se pojavljuje kod svih klijenata. Video se ne otprema na hosting — samo link (može „unlisted“).</span></section>
<form class="card" method="post"><?= csrf_polje() ?><h3>Nova vežba</h3>
  <div class="form">
    <label class="full">Naziv<input class="tin" name="naziv" required maxlength="120" placeholder="npr. Iskorak u hodu"></label>
    <label class="full">YouTube link<input class="tin" name="youtube" type="url" placeholder="https://youtu.be/…"></label>
    <label class="full">Napomena o tehnici<textarea class="tin" name="tehnika" rows="2" placeholder="Kratko: na šta da pazi"></textarea></label>
  </div>
  <button class="btn primary" name="akcija" value="dodaj">Dodaj</button>
</form>
<form class="row" method="get" role="search"><input class="tin" type="search" name="q" value="<?= e($trazi) ?>" placeholder="Pretraga vežbi" style="flex:1" aria-label="Pretraga vežbi"><button class="btn">Traži</button></form>
<section class="card">
<?php foreach ($lista as $v): ?>
  <details class="guide stavka" id="vz<?= (int)$v['id'] ?>">
    <summary><b><?= e($v['naziv']) ?></b><?= (int)$v['aktivna'] ? '' : ' <span class="chip mute">sakrivena</span>' ?><?= $v['youtube'] === '' ? ' <span class="chip warn">bez videa</span>' : '' ?><span class="link" style="margin-left:auto">Uredi</span></summary>
    <form method="post" class="stack" style="padding-top:10px"><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
      <div class="form">
        <label class="full">Naziv<input class="tin" name="naziv" value="<?= e($v['naziv']) ?>" required maxlength="120"></label>
        <label class="full">YouTube link<input class="tin" name="youtube" type="url" value="<?= e($v['youtube']) ?>"></label>
        <label class="full">Napomena o tehnici<textarea class="tin" name="tehnika" rows="2"><?= e($v['tehnika']) ?></textarea></label>
      </div>
      <div class="row">
        <button class="btn primary small" name="akcija" value="sacuvaj">Sačuvaj</button>
        <a class="btn small" href="<?= e(youtube_link($v['youtube'], $v['naziv'])) ?>" target="_blank" rel="noopener">▶ Video</a>
        <button class="btn small" name="akcija" value="<?= (int)$v['aktivna'] ? 'sakrij' : 'prikazi' ?>" formnovalidate><?= (int)$v['aktivna'] ? 'Sakrij' : 'Vrati u izbor' ?></button>
        <button class="link" name="akcija" value="obrisi" formnovalidate data-potvrdi="Obrisati vežbu <?= e($v['naziv']) ?>?" style="color:var(--bad)">Obriši</button>
      </div>
    </form>
  </details>
<?php endforeach; ?>
<?php if (!$lista): ?><p class="muted" style="margin:0">Nema vežbi.</p><?php endif; ?>
</section>
<?php require KOREN . '/delovi/podnozje.php';
