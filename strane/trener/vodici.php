<?php
require __DIR__ . '/_osnova.php';

$dir = KOREN . '/privatno/vodici';

/** Prima otpremljen PDF; vraća [ime_fajla, originalno_ime, velicina] ili poruku o grešci. */
function primi_pdf(string $dir): array|string|null
{
    $f = $_FILES['pdf'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $max = (int)pod('vodici.pdf_max_mb', 10) * 1024 * 1024;
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > $max) {
        return 'PDF je veći od ' . (int)pod('vodici.pdf_max_mb', 10) . ' MB.';
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return 'Otpremanje nije uspelo. Probaj ponovo.';
    }
    $tip = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $pocetak = (string)file_get_contents($f['tmp_name'], false, null, 0, 5);
    if ($tip !== 'application/pdf' || $pocetak !== '%PDF-') {
        return 'Fajl nije PDF.';
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $ime = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $ime)) {
        return 'Fajl ne može da se sačuva na serveru (prava na folder privatno/vodici).';
    }
    return [$ime, mb_substr(basename((string)$f['name']), 0, 190), (int)$f['size']];
}

if (post()) {
    $akcija = $_POST['akcija'] ?? '';
    $id = unos_int($_POST['id'] ?? 0);
    if ($akcija === 'obrisi') {
        $v = red('SELECT fajl FROM vodici WHERE id = ?', [$id]);
        if ($v && $v['fajl'] !== '' && preg_match('/^[a-f0-9]{32}\.pdf$/', $v['fajl'])) {
            @unlink($dir . '/' . $v['fajl']);
        }
        upit('DELETE FROM vodici WHERE id = ?', [$id]);
        poruka('Vodič je obrisan');
        preusmeri('/trener/vodici');
    }
    if ($akcija === 'dodaj' || $akcija === 'sacuvaj') {
        $staro = $akcija === 'sacuvaj' ? red('SELECT * FROM vodici WHERE id = ?', [$id]) : null;
        if ($akcija === 'sacuvaj' && !$staro) {
            greska(404);
        }
        $naslov_v = unos($_POST['naslov'] ?? '', 160);
        $tip = ($_POST['tip'] ?? '') === 'pdf' ? 'pdf' : 'link';
        $url = unos($_POST['url'] ?? '', 500);
        if ($naslov_v === '') {
            poruka('Upiši naslov.', 'greska');
            preusmeri('/trener/vodici');
        }
        $pdf = $tip === 'pdf' ? primi_pdf($dir) : null;
        if (is_string($pdf)) {
            poruka($pdf, 'greska');
            preusmeri('/trener/vodici');
        }
        if ($tip === 'link' && !preg_match('~^https?://~i', $url)) {
            poruka('Link mora da počinje sa https://', 'greska');
            preusmeri('/trener/vodici');
        }
        if ($tip === 'pdf' && !$pdf && (!$staro || $staro['fajl'] === '')) {
            poruka('Izaberi PDF fajl.', 'greska');
            preusmeri('/trener/vodici');
        }
        $fajl = $pdf[0] ?? ($staro['fajl'] ?? '');
        $fajl_ime = $pdf[1] ?? ($staro['fajl_ime'] ?? '');
        $vel = $pdf[2] ?? (int)($staro['velicina'] ?? 0);
        // Stari PDF se briše kad je zamenjen novim ili kad vodič postane link
        if ($staro && $staro['fajl'] !== '' && ($tip === 'link' || ($pdf && $staro['fajl'] !== $fajl))) {
            @unlink($dir . '/' . $staro['fajl']);
        }
        if ($tip === 'link') {
            $fajl = $fajl_ime = '';
            $vel = 0;
        }
        $vrednosti = [$naslov_v, unos($_POST['kategorija'] ?? '', 60), unos($_POST['opis'] ?? '', 500), $tip,
            $tip === 'link' ? $url : '', $fajl, $fajl_ime, $vel, !empty($_POST['objavljen']) ? 1 : 0];
        if ($akcija === 'dodaj') {
            $red = (int)vrednost('SELECT COALESCE(MAX(redosled), 0) + 1 FROM vodici');
            upit('INSERT INTO vodici (naslov, kategorija, opis, tip, url, fajl, fajl_ime, velicina, objavljen, redosled, kreirano)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$vrednosti, $red, sada()]);
        } else {
            upit('UPDATE vodici SET naslov = ?, kategorija = ?, opis = ?, tip = ?, url = ?, fajl = ?, fajl_ime = ?, velicina = ?, objavljen = ? WHERE id = ?',
                [...$vrednosti, $id]);
        }
        poruka('Vodič je sačuvan');
        preusmeri('/trener/vodici');
    }
    greska(400);
}

$lista = redovi('SELECT * FROM vodici ORDER BY redosled, kategorija, naslov');
$kategorije = array_values(array_unique(array_filter(array_column($lista, 'kategorija'))));
$naslov = 'Vodiči';
$aktivno = 'vodici';
$max_mb = (int)pod('vodici.pdf_max_mb', 10);

$forma = function (?array $v) use ($max_mb): void { ?>
  <form method="post" enctype="multipart/form-data" class="stack" style="padding-top:<?= $v ? '10px' : '0' ?>"><?= csrf_polje() ?>
    <?php if ($v): ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><?php endif; ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= $max_mb * 1024 * 1024 ?>">
    <div class="form">
      <label class="full">Naslov<input class="tin" name="naslov" value="<?= e($v['naslov'] ?? '') ?>" required maxlength="160"></label>
      <label>Kategorija<input class="tin" name="kategorija" value="<?= e($v['kategorija'] ?? '') ?>" list="kategorije" maxlength="60" placeholder="Ishrana, Navike…"></label>
      <label>Vrsta<select class="tin" name="tip" data-vrsta><option value="link"<?= ($v['tip'] ?? '') === 'link' ? ' selected' : '' ?>>Link</option><option value="pdf"<?= ($v['tip'] ?? '') === 'pdf' ? ' selected' : '' ?>>PDF</option></select></label>
      <label class="full">Kratak opis<input class="tin" name="opis" value="<?= e($v['opis'] ?? '') ?>" maxlength="500"></label>
      <label class="full" data-za="link">Link (YouTube, Google Drive, Zoom snimak…)<input class="tin" type="url" name="url" value="<?= e($v['url'] ?? '') ?>" placeholder="https://"></label>
      <label class="full" data-za="pdf">PDF do <?= $max_mb ?> MB<?= !empty($v['fajl']) ? ' — sada: ' . e($v['fajl_ime']) . ' (' . broj($v['velicina'] / 1048576) . ' MB)' : '' ?><input class="tin" type="file" name="pdf" accept="application/pdf,.pdf"></label>
      <label class="full potvrda" style="flex-direction:row"><input type="checkbox" name="objavljen" value="1"<?= ($v['objavljen'] ?? 1) ? ' checked' : '' ?>> Klijenti ga vide</label>
    </div>
    <div class="row"><button class="btn primary<?= $v ? ' small' : '' ?>" name="akcija" value="<?= $v ? 'sacuvaj' : 'dodaj' ?>"><?= $v ? 'Sačuvaj' : 'Dodaj vodič' ?></button>
    <?php if ($v): ?><button class="link" name="akcija" value="obrisi" formnovalidate data-potvrdi="Obrisati vodič „<?= e($v['naslov']) ?>“?" style="color:var(--bad)">Obriši</button><?php endif; ?></div>
  </form>
<?php };

require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Priručnici • vebinari • video</span><h1>Vodiči</h1><span class="muted">PDF do <?= $max_mb ?> MB se čuva na serveru i vide ga samo prijavljeni. Video i snimke dodaj kao link.</span></section>
<section class="card"><h3>Novi vodič</h3><?php $forma(null); ?></section>
<datalist id="kategorije"><?php foreach ($kategorije as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
<section class="card">
<?php if (!$lista): ?><p class="muted" style="margin:0">Još nema vodiča.</p><?php endif; ?>
<?php foreach ($lista as $v): ?>
  <details class="guide stavka"><summary><div><?php if ($v['kategorija'] !== ''): ?><span class="chip"><?= e($v['kategorija']) ?></span> <?php endif; ?><?= (int)$v['objavljen'] ? '' : '<span class="chip mute">skriven</span>' ?>
    <div style="font-weight:700;margin-top:4px"><?= e($v['naslov']) ?> <span class="muted" style="font-weight:500">• <?= $v['tip'] === 'pdf' ? 'PDF' : 'link' ?></span></div><div class="muted" style="font-size:14px"><?= e($v['opis']) ?></div></div><span class="link" style="margin-left:auto">Uredi</span></summary>
    <?php $forma($v); ?>
  </details>
<?php endforeach; ?>
</section>
<?php require KOREN . '/delovi/podnozje.php';
