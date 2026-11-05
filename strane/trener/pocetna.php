<?php
require __DIR__ . '/_osnova.php';
$lista = lista_klijenata();
$paznja = array_filter($lista, fn($k) => $k['signal']['nivo'] < 2);
$istice = array_filter($lista, fn($k) => ($n = clanarina_dana($k)) !== null && $n <= (int)pod('clanarina.upozorenje_dana', 3));
$danas_treninzi = (int)vrednost("SELECT COUNT(*) FROM sesije s JOIN klijenti k ON k.id = s.klijent_id
                                 WHERE s.status = 'zavrsena' AND s.datum = ? AND k.arhiviran = 0", [danas()]);
$poslednji = redovi("SELECT s.id, s.naziv, s.datum, s.zavrseno_u, s.napomena_klijenta, s.klijent_id, ko.ime
                     FROM sesije s JOIN klijenti k ON k.id = s.klijent_id JOIN korisnici ko ON ko.id = k.korisnik_id
                     WHERE s.status = 'zavrsena' AND k.arhiviran = 0
                     ORDER BY s.zavrseno_u DESC LIMIT 10");
$naslov = 'Početna';
$aktivno = 'pocetna';
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= e(datum_naslov(danas())) ?></span><h1>Zdravo, <?= e(ime_kratko($ja['ime'])) ?></h1>
  <span class="muted"><?= $paznja ? count($paznja) . ' ' . (count($paznja) === 1 ? 'klijent traži' : 'klijenata traži') . ' tvoju pažnju danas.' : 'Svi klijenti su na planu.' ?></span></section>
<div class="stats">
  <a class="stat" href="/trener/klijenti"><b><?= count($lista) ?></b><span>klijenata</span></a>
  <a class="stat" href="/trener/klijenti"><b style="color:<?= $paznja ? 'var(--warn)' : 'inherit' ?>"><?= count($paznja) ?></b><span>traže pažnju</span></a>
  <div class="stat"><b><?= $danas_treninzi ?></b><span>treninga danas</span></div>
</div>
<?php if ($paznja): ?>
<section class="card"><h2>Traže pažnju</h2>
<?php foreach ($paznja as $k): ?>
  <a class="guide" href="/trener/klijent/<?= $k['id'] ?>"><div><b><?= e($k['ime']) ?></b><div class="row" style="margin-top:4px"><span class="chip <?= $k['signal']['t'] ?>"><?= e($k['signal']['m']) ?></span></div></div><span class="link">Otvori</span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($istice): ?>
<section class="card"><h2>Članarina</h2>
<?php foreach ($istice as $k): ?>
  <a class="guide" href="/trener/klijent/<?= $k['id'] ?>?tab=clanarina"><b><?= e($k['ime']) ?></b><?= clanarina_chip($k) ?></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<section class="card"><h2>Poslednji završeni treninzi</h2>
<?php if (!$poslednji): ?><p class="muted" style="margin:0">Još nema završenih treninga.</p><?php endif; ?>
<?php foreach ($poslednji as $s): $c = red('SELECT * FROM checkini WHERE sesija_id = ?', [$s['id']]); ?>
  <a class="guide" style="align-items:flex-start" href="/trener/klijent/<?= (int)$s['klijent_id'] ?>?tab=istorija&v=dan&d=<?= e($s['datum']) ?>">
    <div class="stack" style="gap:6px;min-width:0"><div><b><?= e($s['ime']) ?></b> <span class="muted">• <?= e($s['naziv']) ?></span></div>
    <?= checkin_chipovi($c) ?>
    <?php if (trim((string)$s['napomena_klijenta']) !== ''): ?><div class="done-view" style="font-size:13px"><?= e(mb_strimwidth($s['napomena_klijenta'], 0, 160, '…')) ?></div><?php endif; ?></div>
    <span class="muted" style="font-size:13px;white-space:nowrap"><?= $s['datum'] === danas() ? 'danas ' . e(vreme_kratko($s['zavrseno_u'])) : e(datum_lep($s['datum'])) ?></span></a>
<?php endforeach; ?>
</section>
<?php require KOREN . '/delovi/podnozje.php';
