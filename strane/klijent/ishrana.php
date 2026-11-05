<?php
require __DIR__ . '/_osnova.php';

$datum = ispravan_datum($_GET['d'] ?? null) ? $_GET['d'] : danas();
$datum = max(dan_plus(danas(), -60), min(danas(), $datum));

if (post()) {
    $akcija = $_POST['akcija'] ?? '';
    $datum = ispravan_datum($_POST['datum'] ?? null) ? $_POST['datum'] : danas();
    $datum = max(dan_plus(danas(), -60), min(danas(), $datum));
    if ($akcija === 'dodaj') {
        $n = red('SELECT * FROM namirnice WHERE id = ?', [unos_int($_POST['namirnica_id'] ?? 0)]);
        $g = unos_int($_POST['grami'] ?? 0);
        if ($n && $g > 0 && $g <= 3000) {
            $x = $g / 100;
            upit('INSERT INTO ishrana_dnevnik (klijent_id, datum, naziv, grami, kcal, p, uh, m, kreirano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$k['id'], $datum, $n['naziv'], $g, $n['kcal'] * $x, $n['p'] * $x, $n['uh'] * $x, $n['m'] * $x, sada()]);
        } else {
            poruka('Izaberi namirnicu i upiši gramažu.', 'greska');
        }
    } elseif ($akcija === 'ukloni') {
        // klijent_id u WHERE: tuđ unos se ne može obrisati podmetanjem ID-ja
        upit('DELETE FROM ishrana_dnevnik WHERE id = ? AND klijent_id = ?', [unos_int($_POST['id'] ?? 0), $k['id']]);
    }
    preusmeri('/ishrana?tab=dan&d=' . $datum);
}

$okv = okvir($k);
$primeri = primeri($k['id']);
$tab = (string)($_GET['tab'] ?? ($primeri ? 'p' . $primeri[0]['id'] : 'dan'));
$naslov = 'Ishrana';
$aktivno = 'ishrana';
require KOREN . '/delovi/glava.php';
echo clanarina_upozorenje($k);
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Dnevni okvir od <?= e(pod('sajt.trener_ime')) ?>a</span><h1>Ishrana</h1></section>
<section class="card">
<?php if ($okv): ?><?= makro_kutija($okv) ?>
<?php else: ?><p class="muted" style="margin:0"><?= e(pod('sajt.trener_ime')) ?> još nije postavio tvoj dnevni okvir. Do tada možeš da koristiš kalkulator.</p><?php endif; ?>
  <p class="muted" style="margin:0;font-size:13px">Gramaže su sirove, osim ako piše drugačije. Obroke iz različitih primera možeš da kombinuješ dok god ostaješ u okviru kalorija.</p>
</section>
<div class="tabs" role="group" aria-label="Ishrana">
<?php foreach ($primeri as $p): ?><a href="/ishrana?tab=p<?= (int)$p['id'] ?>" aria-pressed="<?= $tab === 'p' . $p['id'] ? 'true' : 'false' ?>"><?= e($p['naziv']) ?></a><?php endforeach; ?>
  <a href="/ishrana?tab=dan" aria-pressed="<?= $tab === 'dan' ? 'true' : 'false' ?>">Sastavi svoj dan</a>
  <a href="/ishrana?tab=kalk" aria-pressed="<?= $tab === 'kalk' ? 'true' : 'false' ?>">Kalkulator</a>
</div>
<?php
$prikazan = false;
foreach ($primeri as $p):
    if ($tab !== 'p' . $p['id']) { continue; }
    $prikazan = true;
    $z = zbir_obroka($p['obroci']);
?>
<section class="card"><h2><?= e($p['naziv']) ?></h2><?= $p['klijent_id'] ? '<span class="chip" style="align-self:flex-start">Samo za tebe</span>' : '' ?>
<?php foreach ($p['obroci'] as $o): ?>
  <div class="meal"><div><b><?= e($o['naziv']) ?></b><?php if ($o['stavke']): ?><div><?= e(stavke_tekst($o['stavke'])) ?></div><?php endif; ?><?php if (trim($o['opis']) !== ''): ?><div<?= $o['stavke'] ? ' class="muted" style="font-size:14px"' : '' ?>><?= e($o['opis']) ?></div><?php endif; ?></div><div class="k"><?= (int)$o['kcal'] ?> kcal<br>P <?= (int)$o['p'] ?> • UH <?= (int)$o['uh'] ?> • M <?= (int)$o['m'] ?></div></div>
<?php endforeach; ?>
  <div class="meal"><b>Ukupno</b><div class="k"><b style="color:var(--ink)"><?= (int)$z['kcal'] ?> kcal</b><br>P <?= (int)$z['p'] ?> • UH <?= (int)$z['uh'] ?> • M <?= (int)$z['m'] ?></div></div>
</section>
<?php endforeach;

if ($tab === 'dan'):
    $prikazan = true;
    $stavke = dnevnik($k['id'], $datum);
    $zbir = zbir_obroka($stavke);
    $nam = namirnice();
?>
<section class="card"><div class="row between"><h2>Sastavi svoj dan</h2>
  <div class="row" style="gap:4px">
    <a class="btn small" href="/ishrana?tab=dan&d=<?= e(dan_plus($datum, -1)) ?>" aria-label="Prethodni dan">‹</a>
    <b style="font-size:14px"><?= $datum === danas() ? 'Danas' : e(DANI_KRATKO[dan_u_nedelji($datum)] . ' ' . date('j.n.', strtotime($datum))) ?></b>
    <?php if ($datum < danas()): ?><a class="btn small" href="/ishrana?tab=dan&d=<?= e(dan_plus($datum, 1)) ?>" aria-label="Sledeći dan">›</a><?php else: ?><span class="btn small" aria-disabled="true">›</span><?php endif; ?>
  </div></div>
  <p class="muted" style="margin:0">Izaberi namirnicu i upiši gramažu. Sve se sabira samo i čuva se u tvojoj istoriji.</p>
  <form method="post" class="row"><?= csrf_polje() ?><input type="hidden" name="datum" value="<?= e($datum) ?>">
    <select class="cin" name="namirnica_id" style="flex:2 1 180px" aria-label="Namirnica" required>
      <?php foreach ($nam as $n): ?><option value="<?= (int)$n['id'] ?>"><?= e($n['naziv']) ?></option><?php endforeach; ?>
    </select>
    <input class="cin" name="grami" type="number" inputmode="numeric" min="1" max="3000" value="100" style="flex:1 1 80px" aria-label="Grama" required>
    <button class="btn primary" name="akcija" value="dodaj">Dodaj</button>
  </form>
<?php foreach ($stavke as $s): ?>
  <div class="meal"><div><b><?= e($s['naziv']) ?></b> <span class="muted"><?= (int)$s['grami'] ?> g</span></div>
    <div class="k"><?= (int)round($s['kcal']) ?> kcal
      <form method="post" style="display:inline"><?= csrf_polje() ?><input type="hidden" name="datum" value="<?= e($datum) ?>"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="link" name="akcija" value="ukloni" aria-label="Ukloni <?= e($s['naziv']) ?>">✕</button></form>
      <br>P <?= broj($s['p'], 0) ?> • UH <?= broj($s['uh'], 0) ?> • M <?= broj($s['m'], 0) ?></div></div>
<?php endforeach; ?>
<?php if (!$stavke): ?><p class="muted" style="margin:0">Još nema namirnica za ovaj dan.</p><?php endif; ?>
</section>
<section class="card"><h2>Koliko ti je ostalo</h2>
  <?= $okv ? trake_ostalo($zbir, $okv) : makro_kutija($zbir) ?>
</section>
<?php endif;

if ($tab === 'kalk' || !$prikazan):
    $tez = (float)(vrednost('SELECT kg FROM tezine WHERE klijent_id = ? ORDER BY datum DESC LIMIT 1', [$k['id']]) ?: 70);
?>
<section class="card"><h2>Kalkulator kalorija</h2><p class="muted" style="margin:0">Upiši svoje podatke i odmah dobijaš dnevne kalorije i makroe.</p>
  <form class="form" data-kalkulator>
    <label>Pol<select class="cin" name="pol"><option value="z">Žensko</option><option value="m">Muško</option></select></label>
    <label>Godine<input class="cin" name="god" type="number" inputmode="numeric" value="30" min="14" max="99"></label>
    <label>Visina (cm)<input class="cin" name="vis" type="number" inputmode="numeric" value="170" min="120" max="230"></label>
    <label>Težina (kg)<input class="cin" name="tez" type="number" inputmode="decimal" step="0.1" value="<?= e(round($tez, 1)) ?>"></label>
    <label class="full">Aktivnost<select class="cin" name="akt">
      <?php foreach ([['1.2', 'Sedeći posao, bez treninga'], ['1.375', '1-2 treninga nedeljno'], ['1.55', '3-4 treninga nedeljno'], ['1.725', '5-6 treninga ili fizički posao'], ['1.9', 'Svakodnevno teško']] as [$v, $l]): ?>
      <option value="<?= $v ?>"<?= $v === '1.55' ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <label class="full">Cilj<select class="cin" name="cilj">
      <?php foreach ([['-0.2', 'Brži gubitak masti (−20%)'], ['-0.15', 'Gubitak masti (−15%)'], ['0', 'Održavanje'], ['0.1', 'Povećanje mase (+10%)']] as [$v, $l]): ?>
      <option value="<?= $v ?>"<?= $v === '-0.15' ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  </form>
</section>
<section class="card" data-kalk-rezultat><span class="eyebrow">Tvoj dnevni unos</span><div class="big"><span data-out="kc">—</span> kcal</div>
  <div class="macros"><div class="macro"><b data-out="kc2">—</b><span>kcal</span></div><div class="macro"><b data-out="p">—</b><span>protein g</span></div><div class="macro"><b data-out="uh">—</b><span>UH g</span></div><div class="macro"><b data-out="m">—</b><span>masti g</span></div></div>
  <div class="result"><span class="muted">Bazalni metabolizam</span><b><span data-out="bmr">—</span> kcal</b><span class="muted">Potrošnja sa aktivnošću</span><b><span data-out="tdee">—</span> kcal</b></div>
  <p class="muted" style="margin:0;font-size:13px">Formula Mifflin-St Jeor. Protein 2 g po kg, masti 0,9 g po kg, ostatak su ugljeni hidrati. <?= e(pod('sajt.trener_ime')) ?> potvrđuje konačan plan.</p>
</section>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
