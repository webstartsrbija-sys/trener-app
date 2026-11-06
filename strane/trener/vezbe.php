<?php
require __DIR__ . '/_osnova.php';

if (post()) {
    $akcija = $_POST['akcija'] ?? '';
    $vid = unos_int($_POST['id'] ?? 0);
    $naziv = unos($_POST['naziv'] ?? '', 120);
    $yt = unos($_POST['youtube'] ?? '', 255);
    $nazad = $vid ? '/trener/vezbe?uredi=' . $vid . '#uredi' : '/trener/vezbe';
    if (in_array($akcija, ['dodaj', 'sacuvaj'], true)) {
        if ($yt !== '' && !preg_match('~^https://(www\.|m\.)?(youtube\.com|youtu\.be)/~i', $yt)) {
            poruka('YouTube link mora da počinje sa https://youtube.com ili https://youtu.be', 'greska');
            preusmeri($nazad);
        }
        if ($naziv === '') {
            poruka('Upiši naziv vežbe.', 'greska');
            preusmeri($nazad);
        }
        if (vrednost('SELECT 1 FROM vezbe WHERE naziv = ? AND id <> ?', [$naziv, $akcija === 'sacuvaj' ? $vid : 0])) {
            poruka('Vežba sa tim nazivom već postoji.', 'greska');
            preusmeri($nazad);
        }
        $polja = [
            $naziv, unos($_POST['naziv_en'] ?? '', 120),
            isset(TEZINE[$_POST['tezina'] ?? '']) ? $_POST['tezina'] : '',
            kljucevi_iz_forme($_POST['oprema'] ?? [], OPREMA),
            kljucevi_iz_forme($_POST['misici'] ?? [], MISICI),
            kljucevi_iz_forme($_POST['misici_pomocni'] ?? [], MISICI),
            $yt, unos($_POST['tehnika'] ?? '', 2000), unos($_POST['tehnika_en'] ?? '', 2000),
        ];
    }
    switch ($akcija) {
        case 'dodaj':
            upit('INSERT INTO vezbe (naziv, naziv_en, tezina, oprema, misici, misici_pomocni, youtube, tehnika, tehnika_en, kreirano)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$polja, sada()]);
            poruka('Vežba je dodata u bazu');
            preusmeri('/trener/vezbe?uredi=' . poslednji_id() . '#uredi');
        case 'sacuvaj':
            upit('UPDATE vezbe SET naziv = ?, naziv_en = ?, tezina = ?, oprema = ?, misici = ?, misici_pomocni = ?, youtube = ?, tehnika = ?, tehnika_en = ?
                  WHERE id = ?', [...$polja, $vid]);
            poruka('Vežba je sačuvana');
            preusmeri('/trener/vezbe?uredi=' . $vid . '#uredi');
        case 'sakrij':
        case 'prikazi':
            upit('UPDATE vezbe SET aktivna = ? WHERE id = ?', [$akcija === 'prikazi' ? 1 : 0, $vid]);
            poruka($akcija === 'prikazi' ? 'Vežba je ponovo u izboru' : 'Vežba je sakrivena iz izbora (postojeći programi je zadržavaju)');
            preusmeri($nazad);
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

// Filteri (rade i bez JS-a); pretraga radi odmah dok se kuca
$f_grupa = isset(GRUPE_MISICA[$_GET['grupa'] ?? '']) ? $_GET['grupa'] : '';
$f_oprema = isset(OPREMA[$_GET['oprema'] ?? '']) ? $_GET['oprema'] : '';
$f_tezina = isset(TEZINE[$_GET['tezina'] ?? '']) ? $_GET['tezina'] : '';
$sve = redovi('SELECT * FROM vezbe ORDER BY aktivna DESC, naziv');
$lista = array_values(array_filter($sve, fn($v) =>
    ($f_grupa === '' || in_array($f_grupa, grupe_vezbe($v), true))
    && ($f_oprema === '' || in_array($f_oprema, kljucevi($v['oprema'], OPREMA), true))
    && ($f_tezina === '' || $v['tezina'] === $f_tezina)));
$filter_link = function (string $k, string $v) use ($f_grupa, $f_oprema, $f_tezina): string {
    $p = array_filter(['grupa' => $f_grupa, 'oprema' => $f_oprema, 'tezina' => $f_tezina]);
    if (($p[$k] ?? '') === $v) { unset($p[$k]); } else { $p[$k] = $v; }
    return '/trener/vezbe' . ($p ? '?' . http_build_query($p) : '');
};

/** Polja forme za vežbu (nova i izmena). */
$forma = function (?array $v): void {
    $op = kljucevi($v['oprema'] ?? '', OPREMA);
    $gl = kljucevi($v['misici'] ?? '', MISICI);
    $po = kljucevi($v['misici_pomocni'] ?? '', MISICI);
    $cek = function (string $ime, array $mapa, array $izabrano): string {
        $h = '';
        foreach ($mapa as $k => $l) {
            $h .= '<label class="potvrda"><input type="checkbox" name="' . $ime . '[]" value="' . $k . '"' . (in_array($k, $izabrano, true) ? ' checked' : '') . '> ' . e($l[0]) . '</label>';
        }
        return $h;
    };
?>
    <div class="form">
      <label class="full">Naziv<input class="tin" name="naziv" value="<?= e($v['naziv'] ?? '') ?>" required maxlength="120" placeholder="npr. Iskorak u hodu"></label>
      <label class="full">Naziv na engleskom (za strane klijente)<input class="tin" name="naziv_en" value="<?= e($v['naziv_en'] ?? '') ?>" maxlength="120" placeholder="npr. Walking lunge"></label>
      <label>Težina izvođenja<select class="tin" name="tezina"><option value="">—</option>
        <?php foreach (TEZINE as $k => $t): ?><option value="<?= $k ?>"<?= ($v['tezina'] ?? '') === $k ? ' selected' : '' ?>><?= $t[0] ?></option><?php endforeach; ?></select></label>
      <label>YouTube link<input class="tin" name="youtube" type="url" value="<?= e($v['youtube'] ?? '') ?>" placeholder="https://youtu.be/…"></label>
      <fieldset class="full dani"><legend>Oprema</legend><?= $cek('oprema', OPREMA, $op) ?></fieldset>
      <fieldset class="full dani"><legend>Glavni mišići</legend><?= $cek('misici', MISICI, $gl) ?></fieldset>
      <fieldset class="full dani"><legend>Pomoćni mišići</legend><?= $cek('misici_pomocni', MISICI, $po) ?></fieldset>
      <label class="full">Napomena o tehnici<textarea class="tin" name="tehnika" rows="2" placeholder="Kratko: na šta da pazi"><?= e($v['tehnika'] ?? '') ?></textarea></label>
      <label class="full">Tehnika na engleskom (za strane klijente)<textarea class="tin" name="tehnika_en" rows="2"><?= e($v['tehnika_en'] ?? '') ?></textarea></label>
    </div>
<?php };

$naslov = 'Baza vežbi';
$aktivno = 'vezbe';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Samo trener</span><h1>Baza vežbi</h1><span class="muted"><?= count($sve) ?> vežbi — za svaku težina izvođenja, oprema i mišići. YouTube link dodaš jednom i dugme Video se pojavljuje kod svih klijenata (samo link, može „unlisted“).</span></section>
<?php $ured = unos_int($_GET['uredi'] ?? 0) ? red('SELECT * FROM vezbe WHERE id = ?', [unos_int($_GET['uredi'])]) : null; if ($ured): $v = $ured; ?>
<section class="card" id="uredi"><div class="row between"><h2><?= e($v['naziv']) ?></h2><a class="link" href="/trener/vezbe#vz<?= (int)$v['id'] ?>">← Nazad na listu</a></div>
  <form method="post" class="stack"><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
    <?php $forma($v); ?>
    <div class="row">
      <button class="btn primary" name="akcija" value="sacuvaj">Sačuvaj</button>
      <a class="btn small" href="<?= e(youtube_link($v['youtube'], $v['naziv'])) ?>" target="_blank" rel="noopener">▶ Video</a>
      <button class="btn small" name="akcija" value="<?= (int)$v['aktivna'] ? 'sakrij' : 'prikazi' ?>" formnovalidate><?= (int)$v['aktivna'] ? 'Sakrij iz izbora' : 'Vrati u izbor' ?></button>
      <button class="link" name="akcija" value="obrisi" formnovalidate data-potvrdi="Obrisati vežbu <?= e($v['naziv']) ?>?" style="color:var(--bad)">Obriši</button>
    </div>
  </form>
</section>
<?php endif; ?>
<details class="card"><summary class="btn primary" style="align-self:flex-start;list-style:none;cursor:pointer">+ Nova vežba</summary>
  <form method="post" class="stack" style="padding-top:10px"><?= csrf_polje() ?><h3>Nova vežba</h3>
    <?php $forma(null); ?>
    <button class="btn primary" name="akcija" value="dodaj">Dodaj</button>
  </form>
</details>
<section class="card">
  <input class="tin" type="search" placeholder="Pretraži: zgib, sklek, čučanj, grudi, bučice…" aria-label="Pretraga vežbi" data-filter="[data-vezba]" data-filter-broj="#vz-broj">
  <div class="filteri">
    <span class="eyebrow">Deo tela</span>
    <div class="tabs"><?php foreach (GRUPE_MISICA as $k => $g): ?><a href="<?= e($filter_link('grupa', $k)) ?>" aria-pressed="<?= $f_grupa === $k ? 'true' : 'false' ?>"><?= e($g[0]) ?></a><?php endforeach; ?></div>
    <span class="eyebrow">Težina</span>
    <div class="tabs"><?php foreach (TEZINE as $k => $t): ?><a href="<?= e($filter_link('tezina', $k)) ?>" aria-pressed="<?= $f_tezina === $k ? 'true' : 'false' ?>"><?= e($t[0]) ?></a><?php endforeach; ?></div>
    <span class="eyebrow">Oprema</span>
    <div class="tabs"><?php foreach (OPREMA as $k => $o): ?><a href="<?= e($filter_link('oprema', $k)) ?>" aria-pressed="<?= $f_oprema === $k ? 'true' : 'false' ?>"><?= e($o[0]) ?></a><?php endforeach; ?></div>
  </div>
  <span class="muted" style="font-size:13px"><span id="vz-broj"><?= count($lista) ?> od <?= count($sve) ?> vežbi</span><?= ($f_grupa || $f_oprema || $f_tezina) ? ' • <a class="link" href="/trener/vezbe">Poništi filtere</a>' : '' ?></span>
<?php foreach ($lista as $v): ?>
  <div class="guide stavka-vezba" id="vz<?= (int)$v['id'] ?>" data-vezba data-tekst="<?= e($v['naziv'] . ' ' . $v['naziv_en'] . ' ' . oznake_liste($v['misici'], MISICI) . ' ' . oznake_liste($v['misici_pomocni'], MISICI) . ' ' . oznake_liste($v['oprema'], OPREMA) . ' ' . (TEZINE[$v['tezina']][0] ?? '')) ?>">
    <span class="stack" style="gap:6px;min-width:0"><span><b><?= e($v['naziv']) ?></b><?= $v['naziv_en'] !== '' ? ' <span class="muted" style="font-size:13px">' . e($v['naziv_en']) . '</span>' : '' ?>
      <?= (int)$v['aktivna'] ? '' : ' <span class="chip mute">sakrivena</span>' ?><?= $v['youtube'] === '' ? ' <span class="chip mute">bez videa</span>' : '' ?></span>
      <?= vezba_cipovi($v) ?></span>
    <a class="link" style="margin-left:auto;white-space:nowrap" href="/trener/vezbe?uredi=<?= (int)$v['id'] ?>">Uredi</a>
  </div>
<?php endforeach; ?>
<?php if (!$lista): ?><p class="muted" style="margin:0">Nema vežbi za ovaj izbor.</p><?php endif; ?>
</section>
<?php require KOREN . '/delovi/podnozje.php';
