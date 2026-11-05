<?php
/** Napredak (težina + 1RM) — isto vide klijent i trener. Očekuje: $k, $moze_upis (bool). */
$proseci = nedeljni_proseci($k);
$posl = $proseci ? end($proseci) : null;
$preth = count($proseci) >= 2 ? $proseci[count($proseci) - 2] : null;
$ukupno = count($proseci) >= 2 ? $posl['kg'] - $proseci[0]['kg'] : null;
$dosl = doslednost($k);
$jutros = red('SELECT kg FROM tezine WHERE klijent_id = ? AND datum = ?', [$k['id'], danas()]);
$poslednji = redovi('SELECT datum, kg FROM tezine WHERE klijent_id = ? ORDER BY datum DESC LIMIT 7', [$k['id']]);
$rm = tabela_1rm($k['id']);
?>
<section class="card"><div class="row between"><h2><?= te('Težina') ?></h2><?= $ukupno !== null ? '<span class="chip">' . te('%s kg ukupno', broj_znak($ukupno)) . '</span>' : '' ?></div>
  <div class="stats">
    <div class="stat"><b><?= $posl ? broj($posl['kg']) : '—' ?></b><span><?= $posl && $posl['od'] === ponedeljak(danas()) ? te('prosek ove ned.') : te('poslednji prosek') ?></span></div>
    <div class="stat"><b><?= $preth ? broj_znak($posl['kg'] - $preth['kg']) : '—' ?></b><span><?= te('od prošle ned.') ?></span></div>
    <div class="stat"><b><?= $dosl !== null ? $dosl . '%' : '—' ?></b><span><?= te('doslednost') ?></span></div>
  </div>
  <?= grafikon_tezine($proseci) ?>
<?php if ($moze_upis): ?>
  <form method="post" class="stack" style="gap:8px">
    <?= csrf_polje() ?>
    <div class="row">
      <input class="cin" name="kg" type="text" inputmode="decimal" placeholder="<?= te('Jutarnja težina (kg)') ?>" value="<?= $jutros ? e(broj_polje($jutros['kg'])) : '' ?>" style="flex:1 1 140px" aria-label="<?= te('Jutarnja težina u kilogramima') ?>" required>
      <input class="cin" name="datum" type="date" value="<?= e(danas()) ?>" max="<?= e(danas()) ?>" min="<?= e($k['datum_pocetka'] < danas() ? dan_plus(danas(), -60) : danas()) ?>" style="flex:0 1 160px" aria-label="<?= te('Datum') ?>">
      <button class="btn primary" name="akcija" value="tezina"><?= $jutros ? te('Izmeni') : te('Upiši') ?></button>
    </div>
  </form>
  <p class="muted" style="margin:0;font-size:13px"><?= te('Upisuj svako jutro posle toaleta. Jedan unos po danu — ponovni upis za isti dan ga menja. Prazan dan ne remeti prosek.') ?></p>
<?php endif; ?>
<?php if ($poslednji): ?>
  <details><summary class="link"><?= te('Poslednji upisi') ?></summary>
  <table><tbody><?php foreach ($poslednji as $t): ?><tr><td><?= e(dan_kratko(dan_u_nedelji($t['datum'])) . ', ' . datum_kratko($t['datum'])) ?></td><td class="num"><?= broj($t['kg']) ?> kg</td></tr><?php endforeach; ?></tbody></table>
  </details>
<?php endif; ?>
</section>
<section class="card"><h2><?= te('Snaga • procenjeni 1RM') ?></h2>
<?php if ($rm): ?>
  <div style="overflow-x:auto"><table><thead><tr><th><?= te('Vežba') ?></th><th class="num"><?= te('Početni') ?></th><th class="num"><?= te('Trenutni') ?></th><th class="num"><?= te('Promena') ?></th></tr></thead><tbody>
  <?php foreach ($rm as $x): $pr = $x['pocetni'] > 0 ? ($x['trenutni'] / $x['pocetni'] - 1) * 100 : 0; ?>
    <tr><td><?= e($x['vezba']) ?></td><td class="num"><?= broj($x['pocetni']) ?></td><td class="num"><?= broj($x['trenutni']) ?></td>
    <td class="num" style="color:<?= $pr >= 0 ? 'var(--good)' : 'var(--bad)' ?>;font-weight:600"><?= broj_znak($pr, 0) ?>%</td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php else: ?>
  <p class="muted" style="margin:0"><?= te('Tabela se popunjava sama kad završiš prve treninge.') ?></p>
<?php endif; ?>
  <p class="muted" style="margin:0;font-size:13px"><?= te('Računa se automatski iz najbolje serije: kg × (1 + ponavljanja / 30).') ?></p>
</section>
