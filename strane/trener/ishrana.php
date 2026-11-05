<?php
require __DIR__ . '/_osnova.php';

if (post()) {
    [$akcija, $arg] = array_pad(explode(':', (string)($_POST['akcija'] ?? ''), 2), 2, '');
    $id = unos_int($_POST['id'] ?? 0);
    $br = fn($x) => max(0, min(9999, (float)(unos_broj($_POST[$x] ?? '') ?? 0)));
    switch ($akcija) {
        case 'nam_dodaj':
        case 'nam_sacuvaj':
            $naziv = unos($_POST['naziv'] ?? '', 120);
            if ($naziv === '' || vrednost('SELECT 1 FROM namirnice WHERE naziv = ? AND id <> ?', [$naziv, $akcija === 'nam_sacuvaj' ? $id : 0])) {
                poruka($naziv === '' ? 'Upiši naziv namirnice.' : 'Namirnica sa tim nazivom već postoji.', 'greska');
                preusmeri('/trener/ishrana?tab=namirnice');
            }
            if ($akcija === 'nam_dodaj') {
                upit('INSERT INTO namirnice (naziv, kcal, p, uh, m) VALUES (?, ?, ?, ?, ?)', [$naziv, $br('kcal'), $br('p'), $br('uh'), $br('m')]);
            } else {
                upit('UPDATE namirnice SET naziv = ?, kcal = ?, p = ?, uh = ?, m = ? WHERE id = ?', [$naziv, $br('kcal'), $br('p'), $br('uh'), $br('m'), $id]);
            }
            poruka('Namirnica je sačuvana');
            preusmeri('/trener/ishrana?tab=namirnice');
        case 'nam_obrisi':
            upit('DELETE FROM namirnice WHERE id = ?', [$id]);
            poruka('Namirnica je obrisana (upisani dani klijenata ostaju)');
            preusmeri('/trener/ishrana?tab=namirnice');
        case 'primer_dodaj':
            $red = (int)vrednost('SELECT COALESCE(MAX(redosled), 0) + 1 FROM primeri_ishrane');
            upit('INSERT INTO primeri_ishrane (naziv, redosled) VALUES (?, ?)', [unos($_POST['naziv'] ?? '', 120) ?: 'Primer dana ' . $red, $red]);
            $pid = poslednji_id();
            upit('INSERT INTO primeri_obroci (primer_id, redosled, naziv) VALUES (?, 1, ?)', [$pid, 'Obrok 1']);
            poruka('Primer je dodat');
            preusmeri('/trener/ishrana?p=' . $pid);
        case 'primer_sacuvaj':
        case 'obrok_dodaj':
        case 'obrok_ukloni':
            if (!vrednost('SELECT 1 FROM primeri_ishrane WHERE id = ?', [$id])) {
                greska(404);
            }
            upit('UPDATE primeri_ishrane SET naziv = ? WHERE id = ?', [unos($_POST['naziv'] ?? '', 120) ?: 'Primer dana', $id]);
            foreach ((array)($_POST['obrok'] ?? []) as $oid => $o) {
                if (!is_array($o)) { continue; }
                upit('UPDATE primeri_obroci SET naziv = ?, opis = ?, kcal = ?, p = ?, uh = ?, m = ? WHERE id = ? AND primer_id = ?', [
                    unos($o['naziv'] ?? '', 80), unos($o['opis'] ?? '', 500),
                    max(0, unos_int($o['kcal'] ?? 0)), max(0, unos_int($o['p'] ?? 0)), max(0, unos_int($o['uh'] ?? 0)), max(0, unos_int($o['m'] ?? 0)),
                    (int)$oid, $id,
                ]);
            }
            if ($akcija === 'obrok_dodaj') {
                $n = (int)vrednost('SELECT COUNT(*) FROM primeri_obroci WHERE primer_id = ?', [$id]);
                upit('INSERT INTO primeri_obroci (primer_id, redosled, naziv) VALUES (?, ?, ?)', [$id, $n + 1, 'Obrok ' . ($n + 1)]);
            } elseif ($akcija === 'obrok_ukloni') {
                upit('DELETE FROM primeri_obroci WHERE id = ? AND primer_id = ?', [(int)$arg, $id]);
            }
            poruka('Primer je sačuvan');
            preusmeri('/trener/ishrana?p=' . $id);
        case 'primer_obrisi':
            upit('DELETE FROM primeri_ishrane WHERE id = ?', [$id]);
            poruka('Primer je obrisan');
            preusmeri('/trener/ishrana');
    }
    greska(400);
}

$tab = ($_GET['tab'] ?? '') === 'namirnice' ? 'namirnice' : 'primeri';
$primeri = primeri();
$izabran = null;
foreach ($primeri as $p) {
    if ((int)$p['id'] === unos_int($_GET['p'] ?? 0)) {
        $izabran = $p;
    }
}
$izabran ??= $primeri[0] ?? null;
$naslov = 'Ishrana';
$aktivno = 'ishrana';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Isto za sve klijente</span><h1>Ishrana</h1><span class="muted">Primere dana i namirnice pišeš jednom. Dnevni okvir (kcal i makroi) postavljaš svakom klijentu u njegovom profilu.</span></section>
<div class="tabs" role="group" aria-label="Ishrana">
  <a href="/trener/ishrana" aria-pressed="<?= $tab === 'primeri' ? 'true' : 'false' ?>">Primeri dana</a>
  <a href="/trener/ishrana?tab=namirnice" aria-pressed="<?= $tab === 'namirnice' ? 'true' : 'false' ?>">Baza namirnica</a>
</div>
<?php if ($tab === 'primeri'): ?>
<div class="tabs" role="group" aria-label="Primeri">
<?php foreach ($primeri as $p): ?><a href="/trener/ishrana?p=<?= (int)$p['id'] ?>" aria-pressed="<?= $izabran && (int)$p['id'] === (int)$izabran['id'] ? 'true' : 'false' ?>"><?= e($p['naziv']) ?></a><?php endforeach; ?>
  <form method="post" style="display:contents"><?= csrf_polje() ?><button class="dodaj" name="akcija" value="primer_dodaj">+ Novi primer</button></form>
</div>
<?php if ($izabran): $z = zbir_obroka($izabran['obroci']); ?>
<form class="card" method="post" data-cuvaj-upozorenje><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int)$izabran['id'] ?>">
  <div class="form"><label class="full">Naziv primera<input class="tin" name="naziv" value="<?= e($izabran['naziv']) ?>" style="font-weight:600" maxlength="120"></label></div>
  <?php foreach ($izabran['obroci'] as $o): $oid = (int)$o['id']; ?>
  <div class="ex">
    <div class="ex-head"><input class="tin" name="obrok[<?= $oid ?>][naziv]" value="<?= e($o['naziv']) ?>" style="font-weight:700;max-width:200px" aria-label="Naziv obroka" maxlength="80">
      <button class="link" name="akcija" value="obrok_ukloni:<?= $oid ?>" data-potvrdi="Ukloniti obrok?">Ukloni</button></div>
    <label class="eyebrow">Šta jede<textarea class="tin" name="obrok[<?= $oid ?>][opis]" rows="2" maxlength="500" style="text-transform:none;letter-spacing:0"><?= e($o['opis']) ?></textarea></label>
    <div class="edit">
      <?php foreach (['kcal' => 'kcal', 'p' => 'P (g)', 'uh' => 'UH (g)', 'm' => 'M (g)'] as $kk => $l): ?><label><?= $l ?><input class="tin" type="number" min="0" inputmode="numeric" name="obrok[<?= $oid ?>][<?= $kk ?>]" value="<?= (int)$o[$kk] ?>"></label><?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="meal"><b>Ukupno</b><div class="k"><b style="color:var(--ink)"><?= (int)$z['kcal'] ?> kcal</b><br>P <?= (int)$z['p'] ?> • UH <?= (int)$z['uh'] ?> • M <?= (int)$z['m'] ?></div></div>
  <button class="btn block" name="akcija" value="obrok_dodaj">+ Dodaj obrok</button>
  <button class="btn primary block" name="akcija" value="primer_sacuvaj">Sačuvaj primer</button>
  <button class="link" name="akcija" value="primer_obrisi" data-potvrdi="Obrisati ceo primer?" style="color:var(--bad);align-self:center">Obriši primer</button>
</form>
<?php else: ?><section class="card"><p class="muted" style="margin:0">Još nema primera. Dodaj prvi.</p></section><?php endif; ?>
<?php else: $nam = namirnice(); ?>
<form class="card" method="post"><?= csrf_polje() ?><h3>Nova namirnica (vrednosti na 100 g)</h3>
  <div class="form">
    <label class="full">Naziv<input class="tin" name="naziv" required maxlength="120" placeholder="npr. Ćuretina (sirova)"></label>
    <?php foreach (['kcal' => 'kcal', 'p' => 'Protein g', 'uh' => 'UH g', 'm' => 'Masti g'] as $kk => $l): ?><label><?= $l ?><input class="tin" name="<?= $kk ?>" inputmode="decimal" required></label><?php endforeach; ?>
  </div>
  <button class="btn primary" name="akcija" value="nam_dodaj">Dodaj</button>
</form>
<section class="card">
<?php foreach ($nam as $n): ?>
  <details class="guide stavka"><summary><b><?= e($n['naziv']) ?></b><span class="muted" style="margin-left:auto;font-size:13px;text-align:right"><?= broj($n['kcal'], 0) ?> kcal • P <?= broj($n['p']) ?> • UH <?= broj($n['uh']) ?> • M <?= broj($n['m']) ?></span></summary>
    <form method="post" class="stack" style="padding-top:10px"><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
      <div class="form"><label class="full">Naziv<input class="tin" name="naziv" value="<?= e($n['naziv']) ?>" required maxlength="120"></label>
      <?php foreach (['kcal' => 'kcal', 'p' => 'Protein g', 'uh' => 'UH g', 'm' => 'Masti g'] as $kk => $l): ?><label><?= $l ?><input class="tin" name="<?= $kk ?>" inputmode="decimal" value="<?= e(broj_polje($n[$kk])) ?>"></label><?php endforeach; ?></div>
      <div class="row"><button class="btn primary small" name="akcija" value="nam_sacuvaj">Sačuvaj</button><button class="link" name="akcija" value="nam_obrisi" formnovalidate data-potvrdi="Obrisati namirnicu?" style="color:var(--bad)">Obriši</button></div>
    </form>
  </details>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
