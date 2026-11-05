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
                [$k['id'], $datum, naziv_jezik($n), $g, $n['kcal'] * $x, $n['p'] * $x, $n['uh'] * $x, $n['m'] * $x, sada()]);
        } else {
            poruka(t('Izaberi namirnicu i upiši gramažu.'), 'greska');
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
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= te('Dnevni okvir od %sa', pod('sajt.trener_ime')) ?></span><h1><?= te('Ishrana') ?></h1></section>
<section class="card">
<?php if ($okv): ?><?= makro_kutija($okv) ?>
<?php else: ?><p class="muted" style="margin:0"><?= te('%s još nije postavio tvoj dnevni okvir. Do tada možeš da koristiš kalkulator.', pod('sajt.trener_ime')) ?></p><?php endif; ?>
  <p class="muted" style="margin:0;font-size:13px"><?= te('Gramaže su sirove, osim ako piše drugačije. Obroke iz različitih primera možeš da kombinuješ dok god ostaješ u okviru kalorija.') ?></p>
</section>
<div class="tabs" role="group" aria-label="<?= te('Ishrana') ?>">
<?php foreach ($primeri as $p): ?><a href="/ishrana?tab=p<?= (int)$p['id'] ?>" aria-pressed="<?= $tab === 'p' . $p['id'] ? 'true' : 'false' ?>"><?= e($p['naziv']) ?></a><?php endforeach; ?>
  <a href="/ishrana?tab=dan" aria-pressed="<?= $tab === 'dan' ? 'true' : 'false' ?>"><?= te('Sastavi svoj dan') ?></a>
  <a href="/ishrana?tab=kalk" aria-pressed="<?= $tab === 'kalk' ? 'true' : 'false' ?>"><?= te('Kalkulator') ?></a>
</div>
<?php
$prikazan = false;
foreach ($primeri as $p):
    if ($tab !== 'p' . $p['id']) { continue; }
    $prikazan = true;
    $z = zbir_obroka($p['obroci']);
?>
<section class="card"><h2><?= e($p['naziv']) ?></h2><?= $p['klijent_id'] ? '<span class="chip" style="align-self:flex-start">' . te('Samo za tebe') . '</span>' : '' ?>
<?php foreach ($p['obroci'] as $o): ?>
  <div class="meal"><div><b><?= e($o['naziv']) ?></b><?php if ($o['stavke']): ?><div><?= e(stavke_tekst($o['stavke'])) ?></div><?php endif; ?><?php if (trim($o['opis']) !== ''): ?><div<?= $o['stavke'] ? ' class="muted" style="font-size:14px"' : '' ?>><?= e($o['opis']) ?></div><?php endif; ?></div><div class="k"><?= (int)$o['kcal'] ?> kcal<br><?= pum($o['p'], $o['uh'], $o['m']) ?></div></div>
<?php endforeach; ?>
  <div class="meal"><b><?= te('Ukupno') ?></b><div class="k"><b style="color:var(--ink)"><?= (int)$z['kcal'] ?> kcal</b><br><?= pum($z['p'], $z['uh'], $z['m']) ?></div></div>
</section>
<?php endforeach;

if ($tab === 'dan'):
    $prikazan = true;
    $stavke = dnevnik($k['id'], $datum);
    $zbir = zbir_obroka($stavke);
    $nam = namirnice();
?>
<section class="card"><div class="row between"><h2><?= te('Sastavi svoj dan') ?></h2>
  <div class="row" style="gap:4px">
    <a class="btn small" href="/ishrana?tab=dan&d=<?= e(dan_plus($datum, -1)) ?>" aria-label="<?= te('Prethodni dan') ?>">‹</a>
    <b style="font-size:14px"><?= $datum === danas() ? te('Danas') : e(dan_kratko(dan_u_nedelji($datum)) . ' ' . date(jezik() === 'en' ? 'j/n' : 'j.n.', strtotime($datum))) ?></b>
    <?php if ($datum < danas()): ?><a class="btn small" href="/ishrana?tab=dan&d=<?= e(dan_plus($datum, 1)) ?>" aria-label="<?= te('Sledeći dan') ?>">›</a><?php else: ?><span class="btn small" aria-disabled="true">›</span><?php endif; ?>
  </div></div>
  <p class="muted" style="margin:0"><?= te('Izaberi namirnicu i upiši gramažu. Sve se sabira samo i čuva se u tvojoj istoriji.') ?></p>
  <form method="post" class="row"><?= csrf_polje() ?><input type="hidden" name="datum" value="<?= e($datum) ?>">
    <select class="cin" name="namirnica_id" style="flex:2 1 180px" aria-label="<?= te('Namirnica') ?>" required>
      <?php if (jezik() === 'en') { usort($nam, fn($a, $b) => strcasecmp(naziv_jezik($a), naziv_jezik($b))); } ?>
      <?php foreach ($nam as $n): ?><option value="<?= (int)$n['id'] ?>"><?= e(naziv_jezik($n)) ?></option><?php endforeach; ?>
    </select>
    <input class="cin" name="grami" type="number" inputmode="numeric" min="1" max="3000" value="100" style="flex:1 1 80px" aria-label="<?= te('Grama') ?>" required>
    <button class="btn primary" name="akcija" value="dodaj"><?= te('Dodaj') ?></button>
  </form>
<?php foreach ($stavke as $s): ?>
  <div class="meal"><div><b><?= e($s['naziv']) ?></b> <span class="muted"><?= (int)$s['grami'] ?> g</span></div>
    <div class="k"><?= (int)round($s['kcal']) ?> kcal
      <form method="post" style="display:inline"><?= csrf_polje() ?><input type="hidden" name="datum" value="<?= e($datum) ?>"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="link" name="akcija" value="ukloni" aria-label="<?= te('Ukloni %s', $s['naziv']) ?>">✕</button></form>
      <br><?= pum($s['p'], $s['uh'], $s['m']) ?></div></div>
<?php endforeach; ?>
<?php if (!$stavke): ?><p class="muted" style="margin:0"><?= te('Još nema namirnica za ovaj dan.') ?></p><?php endif; ?>
</section>
<section class="card"><h2><?= te('Koliko ti je ostalo') ?></h2>
  <?= $okv ? trake_ostalo($zbir, $okv) : makro_kutija($zbir) ?>
</section>
<?php endif;

if ($tab === 'kalk' || !$prikazan):
    $tez = (float)(vrednost('SELECT kg FROM tezine WHERE klijent_id = ? ORDER BY datum DESC LIMIT 1', [$k['id']]) ?: 70);
?>
<section class="card"><h2><?= te('Kalkulator kalorija') ?></h2><p class="muted" style="margin:0"><?= te('Upiši svoje podatke i odmah dobijaš dnevne kalorije i makroe.') ?></p>
  <form class="form" data-kalkulator>
    <label><?= te('Pol') ?><select class="cin" name="pol"><option value="z"><?= te('Žensko') ?></option><option value="m"><?= te('Muško') ?></option></select></label>
    <label><?= te('Godine') ?><input class="cin" name="god" type="number" inputmode="numeric" value="30" min="14" max="99"></label>
    <label><?= te('Visina (cm)') ?><input class="cin" name="vis" type="number" inputmode="numeric" value="170" min="120" max="230"></label>
    <label><?= te('Težina (kg)') ?><input class="cin" name="tez" type="number" inputmode="decimal" step="0.1" value="<?= e(round($tez, 1)) ?>"></label>
    <label class="full"><?= te('Aktivnost') ?><select class="cin" name="akt">
      <?php foreach ([['1.2', 'Sedeći posao, bez treninga'], ['1.375', '1-2 treninga nedeljno'], ['1.55', '3-4 treninga nedeljno'], ['1.725', '5-6 treninga ili fizički posao'], ['1.9', 'Svakodnevno teško']] as [$v, $l]): ?>
      <option value="<?= $v ?>"<?= $v === '1.55' ? ' selected' : '' ?>><?= te($l) ?></option><?php endforeach; ?></select></label>
    <label class="full"><?= te('Cilj') ?><select class="cin" name="cilj">
      <?php foreach ([['-0.2', 'Brži gubitak masti (−20%)'], ['-0.15', 'Gubitak masti (−15%)'], ['0', 'Održavanje'], ['0.1', 'Povećanje mase (+10%)']] as [$v, $l]): ?>
      <option value="<?= $v ?>"<?= $v === '-0.15' ? ' selected' : '' ?>><?= te($l) ?></option><?php endforeach; ?></select></label>
  </form>
</section>
<section class="card" data-kalk-rezultat><span class="eyebrow"><?= te('Tvoj dnevni unos') ?></span><div class="big"><span data-out="kc">—</span> kcal</div>
  <div class="macros"><div class="macro"><b data-out="kc2">—</b><span>kcal</span></div><div class="macro"><b data-out="p">—</b><span><?= te('protein g') ?></span></div><div class="macro"><b data-out="uh">—</b><span><?= te('UH g') ?></span></div><div class="macro"><b data-out="m">—</b><span><?= te('masti g') ?></span></div></div>
  <div class="result"><span class="muted"><?= te('Bazalni metabolizam') ?></span><b><span data-out="bmr">—</span> kcal</b><span class="muted"><?= te('Potrošnja sa aktivnošću') ?></span><b><span data-out="tdee">—</span> kcal</b></div>
  <p class="muted" style="margin:0;font-size:13px"><?= te('Formula Mifflin-St Jeor. Protein 2 g po kg, masti 0,9 g po kg, ostatak su ugljeni hidrati. %s potvrđuje konačan plan.', pod('sajt.trener_ime')) ?></p>
</section>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
