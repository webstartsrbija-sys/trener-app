<?php
/**
 * Istorija (kalendar, nedelja, dan) — isto vide klijent i trener.
 * Očekuje: $k (klijent), $url (npr. '/istorija?' ili '/trener/klijent/5?tab=istorija&').
 */

$danas = danas();
$pocetak = $k['datum_pocetka'];
$kraj = dan_plus(ponedeljak($danas), 6);
$okv = okvir($k);
$planirani_dani = dani_treninga($k);

$v = in_array($_GET['v'] ?? '', ['dan', 'nedelja', 'mesec'], true) ? $_GET['v'] : 'nedelja';
$d = ispravan_datum($_GET['d'] ?? null) ? $_GET['d'] : $danas;
$d = max($pocetak > $kraj ? $kraj : $pocetak, min($kraj, $d));

if ($v === 'mesec') {
    $od = date('Y-m-01', strtotime($d));
    $do = date('Y-m-t', strtotime($d));
} elseif ($v === 'nedelja') {
    $od = ponedeljak($d);
    $do = dan_plus($od, 6);
} else {
    $od = $do = $d;
}
$od_upit = min($od, ponedeljak($od));
$do_upit = max($do, dan_plus(ponedeljak($do), 6));
$sesije = sesije_po_danu($k['id'], $od_upit, $do_upit);
$hrana = ishrana_po_danu($k['id'], $od_upit, $do_upit);
$tez = [];
foreach (redovi('SELECT datum, kg FROM tezine WHERE klijent_id = ? AND datum BETWEEN ? AND ?', [$k['id'], $od_upit, $do_upit]) as $t) {
    $tez[$t['datum']] = (float)$t['kg'];
}
$zavrseni = array_map('count', $sesije);

$link = fn(string $vv, string $dd) => e($url . 'v=' . $vv . '&d=' . $dd);
$u_opsegu = fn(string $x) => $x >= $pocetak && $x <= $kraj;

/** Status dana: done | miss | today | plan | odmor | van */
$status = function (string $x) use ($sesije, $planirani_dani, $danas, $u_opsegu): string {
    if (!$u_opsegu($x)) {
        return 'van';
    }
    if (!empty($sesije[$x])) {
        return 'done';
    }
    if (!in_array(dan_u_nedelji($x), $planirani_dani, true)) {
        return 'odmor';
    }
    return $x < $danas ? 'miss' : ($x === $danas ? 'today' : 'plan');
};
$po_danu = treninzi_po_danu((int)$k['id']);
// Nazivi treninga zakazanih za taj dan u nedelji (ako ih je trener vezao za dane)
$zakazano = fn(string $x) => implode(', ', array_map(fn($t) => $t['naziv'], $po_danu[dan_u_nedelji($x)] ?? []));
$chip = function (string $x) use ($status, $sesije, $zakazano): string {
    $z = $zakazano($x);
    return match ($status($x)) {
        'done'  => implode(' ', array_map(fn($s) => '<span class="chip ok">✓ ' . e($s['naziv']) . '</span>', $sesije[$x])),
        'miss'  => '<span class="chip bad">' . te('Propušten') . ($z !== '' ? ' • ' . e($z) : '') . '</span>',
        'today' => '<span class="chip warn">' . te('Danas') . ($z !== '' ? ' • ' . e($z) : '') . '</span>',
        'plan'  => '<span class="chip">' . ($z !== '' ? te('%s • planiran', $z) : te('Trening • planiran')) . '</span>',
        default => '<span class="chip mute">' . te('Odmor') . '</span>',
    };
};
$zbir = function (string $a, string $b) use ($k, $hrana, $tez, $zavrseni): array {
    $t = treninzi_u_periodu($k, $a, $b, $zavrseni);
    $kc = array_filter($hrana, fn($x, $dd) => $dd >= $a && $dd <= $b, ARRAY_FILTER_USE_BOTH);
    $w = array_filter($tez, fn($x, $dd) => $dd >= $a && $dd <= $b, ARRAY_FILTER_USE_BOTH);
    ksort($w);
    return $t + [
        'kc' => $kc ? (int)round(array_sum(array_column($kc, 'kcal')) / count($kc)) : 0,
        'w' => $w ? array_sum($w) / count($w) : null,
        'wf' => $w ? reset($w) : null, 'wl' => $w ? end($w) : null,
    ];
};
$statistika = function (array $a, string $treca = 'kg prosek', ?string $treca_vrednost = null): string {
    return '<div class="stats"><div class="stat"><b>' . $a['dn'] . '/' . $a['pl'] . '</b><span>' . te('treninga') . '</span></div>'
        . '<div class="stat"><b>' . ($a['kc'] ?: '—') . '</b><span>' . te('prosek kcal') . '</span></div>'
        . '<div class="stat"><b>' . ($treca_vrednost ?? ($a['w'] !== null ? broj($a['w']) : '—')) . '</b><span>' . te($treca) . '</span></div></div>';
};
$traka = function (string $x) use ($hrana, $okv): string {
    if (empty($hrana[$x]) || !$okv) {
        return '';
    }
    $pc = $hrana[$x]['kcal'] / max(1, $okv['kcal']) * 100;
    return '<span class="bar"><i class="' . (kcal_u_okviru($hrana[$x]['kcal'], $okv) ? '' : 'over') . '" style="width:' . min(100, round($pc)) . '%"></i></span>';
};

// Navigacija napred/nazad
if ($v === 'mesec') {
    $naslov_perioda = mb_convert_case(mesec((int)date('n', strtotime($d))), MB_CASE_TITLE) . ' ' . date('Y', strtotime($d));
    $preth = date('Y-m-t', strtotime($od . ' -1 day'));
    $sled = date('Y-m-01', strtotime($do . ' +1 day'));
    $na_pocetku = $od <= $pocetak;
    $na_kraju = $do >= $kraj;
    $danas_vidljiv = $danas >= $od && $danas <= $do;
} elseif ($v === 'nedelja') {
    $tacka = jezik() === 'en' ? '' : '.';
    $naslov_perioda = (int)date('j', strtotime($od)) . $tacka . ' ' . mesec_kratko((int)date('n', strtotime($od))) . ' – '
        . (int)date('j', strtotime($do)) . $tacka . ' ' . mesec_kratko((int)date('n', strtotime($do)));
    $preth = dan_plus($od, -7);
    $sled = dan_plus($od, 7);
    $na_pocetku = $od <= $pocetak;
    $na_kraju = $do >= $kraj;
    $danas_vidljiv = $danas >= $od && $danas <= $do;
} else {
    $naslov_perioda = datum_naslov($d);
    $preth = dan_plus($d, -1);
    $sled = dan_plus($d, 1);
    $na_pocetku = $d <= $pocetak;
    $na_kraju = $d >= $kraj;
    $danas_vidljiv = $d === $danas;
}
?>
<div class="tabs" role="group" aria-label="<?= te('Prikaz istorije') ?>">
<?php foreach (['dan' => 'Dan', 'nedelja' => 'Nedelja', 'mesec' => 'Mesec'] as $kk => $l): ?>
  <a href="<?= $link($kk, $d) ?>" aria-pressed="<?= $v === $kk ? 'true' : 'false' ?>"><?= te($l) ?></a>
<?php endforeach; ?>
</div>
<div class="hnav">
  <?php if ($na_pocetku): ?><span class="btn small" aria-disabled="true">‹</span><?php else: ?><a class="btn small" href="<?= $link($v, max($pocetak, $preth)) ?>" aria-label="<?= te('Prethodno') ?>">‹</a><?php endif; ?>
  <b><?= e($naslov_perioda) ?></b>
  <?php if ($na_kraju): ?><span class="btn small" aria-disabled="true">›</span><?php else: ?><a class="btn small" href="<?= $link($v, min($kraj, $sled)) ?>" aria-label="<?= te('Sledeće') ?>">›</a><?php endif; ?>
</div>
<?php if (!$danas_vidljiv): ?><a class="link" href="<?= $link($v, $danas) ?>" style="align-self:center"><?= te('Vrati na danas') ?></a><?php endif; ?>

<?php if ($v === 'mesec'):
    $a = $zbir($od, $do);
    $promena = $a['wf'] !== null && $a['wl'] !== null && $a['wf'] !== $a['wl'] ? broj_znak($a['wl'] - $a['wf']) : null;
?>
<section class="card"><?= $statistika($a, $promena !== null ? 'kg u mesecu' : 'kg prosek', $promena) ?>
  <div class="cal">
    <?php for ($i = 1; $i <= 7; $i++): ?><span class="h"><?= e(dan_kratko($i)) ?></span><?php endfor; ?>
    <?php for ($i = 1; $i < dan_u_nedelji($od); $i++): ?><span></span><?php endfor; ?>
    <?php for ($x = $od; $x <= $do; $x = dan_plus($x, 1)):
        $st = $status($x);
        $dot = ['done' => 'done', 'miss' => 'miss', 'today' => 'zakazan', 'plan' => 'zakazan'][$st] ?? 'none';
        $kb = !empty($hrana[$x]) ? (kcal_u_okviru($hrana[$x]['kcal'], $okv) ? 'ok' : 'off') : '';
        $oznaka = datum_lep($x);
    ?>
      <?php if ($st === 'van'): ?>
      <span class="day" aria-disabled="true"><span><?= (int)date('j', strtotime($x)) ?></span><i class="dot none"></i><i class="kb"></i></span>
      <?php else: ?>
      <a class="day<?= $x === $danas ? ' today' : '' ?>" href="<?= $link('dan', $x) ?>" aria-label="<?= e($oznaka) ?>"><span><?= (int)date('j', strtotime($x)) ?></span><i class="dot <?= $dot ?>"></i><i class="kb <?= $kb ?>"></i></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <div class="legend"><span><i class="dot done"></i><?= te('Trening urađen') ?></span><span><i class="dot miss"></i><?= te('Propušten') ?></span><span><i class="dot zakazan"></i><?= te('Planiran') ?></span><span><i class="kb ok" style="width:16px"></i><?= te('Kalorije u okviru') ?></span><span><i class="kb off" style="width:16px"></i><?= te('Van okvira') ?></span></div>
  <p class="muted" style="margin:0;font-size:13px"><?= te('Dodirni dan za detalje.') ?></p>
</section>
<section class="card"><h3><?= te('Po nedeljama') ?></h3>
<?php for ($w = ponedeljak($od); $w <= $do; $w = dan_plus($w, 7)):
    $we = dan_plus($w, 6);
    if ($w > $danas || $we < $pocetak) { continue; }
    $b = $zbir($w, $we);
?>
  <a class="wd" href="<?= $link('nedelja', $w) ?>"><span class="muted" style="font-size:13px"><?= (int)date('j', strtotime($w)) ?><?= jezik() === 'en' ? '' : '.' ?>–<?= (int)date('j', strtotime($we)) ?><?= jezik() === 'en' ? '' : '.' ?> <?= e(mesec_kratko((int)date('n', strtotime($we)))) ?></span><span><b><?= $b['dn'] ?>/<?= $b['pl'] ?></b> <?= te('treninga') ?> • <?= $b['kc'] ?: '—' ?> kcal</span><span class="muted"><?= $b['w'] !== null ? broj($b['w']) . ' kg' : '' ?></span></a>
<?php endfor; ?>
</section>

<?php elseif ($v === 'nedelja'): $a = $zbir($od, $do); ?>
<section class="card"><?= $statistika($a) ?><div>
<?php for ($x = $od; $x <= $do; $x = dan_plus($x, 1)): $st = $status($x); ?>
  <<?= $st === 'van' ? 'span aria-disabled="true"' : 'a href="' . $link('dan', $x) . '"' ?> class="wd"><span><b><?= e(dan_kratko(dan_u_nedelji($x))) ?></b><br><span class="muted" style="font-size:13px"><?= date(jezik() === 'en' ? 'j/n' : 'j.n.', strtotime($x)) ?></span></span>
  <span class="stack" style="gap:4px"><?= $st === 'van' ? '<span class="muted">—</span>' : $chip($x) ?><?= $traka($x) ?></span>
  <span class="muted" style="font-size:13px;text-align:right"><?= !empty($hrana[$x]) ? (int)round($hrana[$x]['kcal']) . ' kcal' : ($st !== 'van' && $x <= $danas ? te('nije upisano') : '') ?><?= isset($tez[$x]) ? '<br>' . broj($tez[$x]) . ' kg' : '' ?></span></<?= $st === 'van' ? 'span' : 'a' ?>>
<?php endfor; ?>
</div></section>

<?php else: $st = $status($d); $fo = $hrana[$d] ?? null; ?>
<section class="card"><div class="row between"><h2><?= te('Trening') ?></h2><?= $chip($d) ?></div>
<?php if ($st === 'done'):
    foreach ($sesije[$d] as $ss): $pun = sesija_puna((int)$ss['id']); ?>
  <span class="muted"><?= e($pun['naziv']) ?><?= $pun['fokus'] !== '' ? ' • ' . e($pun['fokus']) : '' ?> • <?= te('završen u %s', vreme_kratko($pun['zavrseno_u'])) ?></span>
  <?php foreach ($pun['vezbe'] as $vz): ?>
  <div class="meal"><b><?= e(naziv_jezik($vz)) ?></b><div class="k" style="color:var(--ink)"><?= e(serije_kratko($vz['serije'])) ?></div></div>
  <?php endforeach; ?>
  <?= checkin_chipovi($pun['checkin']) ?>
  <?php if (trim((string)$pun['napomena_klijenta']) !== ''): ?><div class="done-view"><b><?= te('Napomena klijenta:') ?></b> <?= e_redovi($pun['napomena_klijenta']) ?></div><?php endif; ?>
<?php endforeach;
elseif ($st === 'today' || $st === 'plan'):
    $zakazani = $po_danu[dan_u_nedelji($d)] ?? [];
    if (!$zakazani): ?>
  <p class="muted" style="margin:0"><?= te('Zakazan je trening za ovaj dan.') ?></p>
<?php else:
        $stablo = [];
        foreach (program_stablo(program_klijenta((int)$k['id'])) as $pt) {
            $stablo[(int)$pt['id']] = $pt;
        }
        foreach ($zakazani as $z): $pt = $stablo[(int)$z['id']] ?? null; if (!$pt) { continue; } ?>
  <span class="eyebrow"><?= te('Plan za ovaj dan') ?></span>
  <div class="row between"><b><?= e($pt['naziv']) ?><?= $pt['fokus'] !== '' ? ' • ' . e($pt['fokus']) : '' ?></b>
    <?php if (str_starts_with($url, '/istorija')): ?><a class="btn small" href="/trening?t=<?= (int)$pt['id'] ?>"><?= te('Otvori trening') ?></a>
    <?php else: ?><a class="link" href="/trener/klijent/<?= (int)$k['id'] ?>?tab=program&amp;t=<?= (int)$pt['id'] ?>">Program →</a><?php endif; ?></div>
  <?php foreach ($pt['vezbe'] as $vz): ?>
  <div class="meal"><div><b><?= e(naziv_jezik($vz)) ?></b><?php $op = vezba_opis($vz); if ($op !== ''): ?><div class="vezba-opis"><?= e($op) ?></div><?php endif; ?></div>
    <div class="k" style="color:var(--ink)"><?= e(serije_rec(count($vz['serije']))) ?><br><span class="muted"><?= e(implode(' • ', array_map(fn($sr) => ($sr['kg'] !== '' ? $sr['kg'] . '×' : '') . $sr['pon'], $vz['serije']))) ?></span></div></div>
  <?php endforeach; ?>
<?php endforeach; endif; ?>
<?php elseif ($st === 'miss'): ?>
  <p class="muted" style="margin:0"><?= te('Trening nije upisan.') ?></p>
<?php else: ?>
  <p class="muted" style="margin:0"><?= te('Dan za odmor.') ?></p>
<?php endif; ?>
</section>
<section class="card"><div class="row between"><h2><?= te('Ishrana') ?></h2><?= $fo && $okv ? '<span class="chip ' . (kcal_u_okviru($fo['kcal'], $okv) ? 'ok' : 'warn') . '">' . (int)round($fo['kcal']) . ' / ' . $okv['kcal'] . ' kcal</span>' : '' ?></div>
<?php if ($fo && $okv): ?><?= trake_ostalo($fo, $okv, false) ?>
<?php elseif ($fo): ?><?= makro_kutija($fo) ?>
<?php endif; ?>
<?php if ($fo): ?><div class="stack" style="gap:10px;border-top:1px solid var(--line);padding-top:10px"><span class="eyebrow"><?= te('Šta je pojedeno') ?></span><?= spisak_hrane(dnevnik($k['id'], $d)) ?></div>
<?php else: ?><p class="muted" style="margin:0"><?= te('Ishrana za ovaj dan nije upisana.') ?></p><?php endif; ?>
</section>
<section class="card"><div class="row between"><h2><?= te('Težina') ?></h2><b><?= isset($tez[$d]) ? broj($tez[$d]) . ' kg' : '<span class="muted">' . te('nije upisana') . '</span>' ?></b></div></section>
<?php endif;
