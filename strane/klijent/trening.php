<?php
require __DIR__ . '/_osnova.php';

$pid = program_klijenta($k['id']);
$blokiran = program_blokiran($k);

if (post()) {
    if ($blokiran) {
        greska(403, 'Članarina je istekla — program je zaključan dok se ne produži.');
    }
    $r = sacuvaj_unos_klijenta($k['id'], $_POST, ($_POST['akcija'] ?? '') === 'zavrsi');
    poruka(($_POST['bio_zavrsen'] ?? '') === '1' ? t('Izmene su sačuvane') : t('Bravo! %s vidi tvoj trening', pod('sajt.trener_ime')));
    preusmeri('/trening?t=' . unos_int($_POST['trening_id'] ?? 0));
}

$program = $blokiran ? [] : program_stablo($pid);
$sesije = [];
foreach ($program as $t) {
    $sesije[(int)$t['id']] = sesija_ove_nedelje($k['id'], (int)$t['id']);
}
$zavrsen = fn(int $tid) => ($sesije[$tid]['status'] ?? '') === 'zavrsena';
$uradjeno = count(array_filter(array_keys($sesije), $zavrsen));
$sledeci = null;
foreach ($program as $t) {
    if (!$zavrsen((int)$t['id'])) {
        $sledeci = $t;
        break;
    }
}
$izabran = null;
$trazen = unos_int($_GET['t'] ?? 0);
foreach ($program as $t) {
    if ((int)$t['id'] === $trazen) {
        $izabran = $t;
    }
}
// Treninzi vezani za dane u nedelji (trener ih podesi u programu)
$danas_dan = dan_u_nedelji(danas());
$ima_dane = (bool)array_filter($program, fn($t) => $t['dan'] !== null);
$danasnji = array_values(array_filter($program, fn($t) => (int)$t['dan'] === $danas_dan));
$naredni = null;   // prvi sledeći trening ove nedelje posle danas
foreach ($program as $t) {
    if ($t['dan'] !== null && (int)$t['dan'] > $danas_dan && !$zavrsen((int)$t['id'])) {
        $naredni = $t;
        break;
    }
}
if (!$izabran && $ima_dane) {
    foreach ($danasnji as $t) {
        if (!$zavrsen((int)$t['id'])) {
            $izabran = $t;
            break;
        }
    }
    $izabran ??= $danasnji[0] ?? $naredni;
}
$izabran ??= $sledeci ?? ($program[0] ?? null);
$malo = fn(string $n) => jezik() === 'en' ? $n : mb_strtolower($n);
if ($blokiran) {
    $poruka_dana = t('Program je zaključan dok se članarina ne produži.');
} elseif (!$program) {
    $poruka_dana = t('%s još priprema tvoj program.', pod('sajt.trener_ime'));
} elseif ($ima_dane) {
    $poruka_dana = $danasnji
        ? t('Danas: %s.', implode(', ', array_map(fn($t) => $t['naziv'], $danasnji)))
        : ($naredni ? t('Danas je dan za odmor. Sledeći trening: %s — %s.', dan_dugo((int)$naredni['dan']), $naredni['naziv']) : t('Danas je dan za odmor.'));
} else {
    $poruka_dana = $sledeci ? t('Danas je na redu %s.', $malo($sledeci['naziv'])) : t('Svi treninzi ove nedelje su urađeni. Bravo!');
}

$naslov = 'Trening';
$aktivno = 'trening';
require KOREN . '/delovi/glava.php';
echo clanarina_upozorenje($k);
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= te('Nedelja %s', nedelja_programa($k)) ?><?= $program ? ' • ' . te('%s od %s treninga', $uradjeno, count($program)) : '' ?></span>
  <h1><?= te('Zdravo, %s', ime_kratko($k['ime'])) ?></h1>
  <span class="muted"><?= e($poruka_dana) ?></span></section>
<?php if ($izabran):
    $tid = (int)$izabran['id'];
    // Ako je trener izmenio program posle početka treninga, nezavršen trening dobija novi plan
    if ($sesije[$tid]) {
        osvezi_nezavrsenu_sesiju($sesije[$tid], $izabran);
    }
    $s = $sesije[$tid] ? sesija_puna((int)$sesije[$tid]['id']) : null;
    $gotov = $s && $s['status'] === 'zavrsena';
    // Ako sesija postoji, prikazuje se njen snimak plana; inače trenutni plan
    $vezbe = $s ? $s['vezbe'] : $izabran['vezbe'];
    $ci = $s['checkin'] ?? null;
?>
<div class="tabs" role="group" aria-label="<?= te('Treninzi') ?>">
<?php foreach ($program as $t): ?>
  <a href="/trening?t=<?= (int)$t['id'] ?>" aria-pressed="<?= (int)$t['id'] === $tid ? 'true' : 'false' ?>"><?= $zavrsen((int)$t['id']) ? '✓ ' : '' ?><?= $t['dan'] !== null ? '<small class="dan-oznaka">' . e(dan_kratko((int)$t['dan'])) . '</small> ' : '' ?><?= e($t['naziv']) ?></a>
<?php endforeach; ?>
</div>
<form method="post" action="/trening" class="stack" data-autosave="/api/trening" id="trening-forma">
<?= csrf_polje() ?>
<input type="hidden" name="trening_id" value="<?= $tid ?>">
<input type="hidden" name="bio_zavrsen" value="<?= $gotov ? '1' : '0' ?>">
<section class="card">
  <div class="row between"><div><h2><?= e($s['naziv'] ?? $izabran['naziv']) ?><?= ($s['fokus'] ?? $izabran['fokus']) !== '' ? ' • ' . e($s['fokus'] ?? $izabran['fokus']) : '' ?></h2>
  <?php $traj = $s['trajanje'] ?? $izabran['trajanje']; if ($traj !== ''): ?><span class="muted"><?= te('Trajanje oko %s', $traj) ?></span><?php endif; ?></div>
  <?= $gotov ? '<span class="chip ok">' . te('Završeno') . '</span>' : '' ?></div>
  <div class="legend"><span><i class="sw"></i><?= te('Upisuje trener') ?></span><span><i class="sw c"></i><?= te('Upisuješ ti') ?></span></div>
  <?php $nap = $s ? $s['napomena_trenera'] : $izabran['napomena']; if (trim((string)$nap) !== ''): ?>
  <div class="note-t"><b><?= e(pod('sajt.trener_ime')) ?>:</b> <?= e_redovi($nap) ?></div>
  <?php endif; ?>
</section>
<section class="card">
<?php if (!$vezbe): ?><p class="muted" style="margin:0"><?= te('U ovom treningu još nema vežbi.') ?></p><?php endif; ?>
<?php foreach ($vezbe as $v): ?>
  <div class="ex">
    <div class="ex-head"><div><div class="ex-name"><?= e(naziv_jezik($v)) ?></div>
      <span class="muted" style="font-size:13px"><?= serije_rec(count($v['serije'])) ?><?= $v['zagrevanje'] !== '' ? ' • ' . te('Zagrevanje: %s', $v['zagrevanje']) : '' ?><?= $v['tempo'] !== '' ? ' • ' . te('Tempo %s', $v['tempo']) : '' ?><?= $v['pauza'] !== '' ? ' • ' . te('Pauza %s', $v['pauza']) : '' ?></span>
      <?php $opis = vezba_opis($v); if ($opis !== ''): ?><div class="vezba-opis"><?= e($opis) ?></div><?php endif; ?>
      <?php $teh = naziv_jezik(['tehnika' => (string)($v['tehnika'] ?? ''), 'tehnika_en' => (string)($v['tehnika_en'] ?? '')], 'tehnika'); if (trim($teh) !== ''): ?><details class="tehnika"><summary><?= te('Tehnika') ?></summary><?= e_redovi($teh) ?></details><?php endif; ?>
    </div><a class="btn small" href="<?= e(youtube_link((string)($v['youtube'] ?? ''), naziv_jezik($v))) ?>" target="_blank" rel="noopener">▶ Video</a></div>
    <div class="sets"><div class="set hd"><span>#</span><span><?= te('Plan') ?></span><span><?= te('Tvoja kg') ?></span><span><?= te('Tvoja pon.') ?></span><span></span></div>
    <?php foreach ($v['serije'] as $i => $sr):
        $kljuc = $s ? 's' . $sr['id'] : 'p' . $sr['id'];
        $pkg = $s ? $sr['plan_kg'] : $sr['kg']; $ppon = $s ? $sr['plan_pon'] : $sr['pon']; $prir = $s ? $sr['plan_rir'] : $sr['rir'];
        $ok = $s && (int)$sr['uradjeno'];
    ?>
      <div class="set" data-serija="<?= e($kljuc) ?>"><span class="n"><?= $i + 1 ?></span>
        <div class="plan"><b><?= e(kg_tekst($pkg)) ?></b> × <?= e($ppon !== '' ? $ppon : '—') ?><small><?= $prir !== '' ? 'RIR ' . e($prir) : '&nbsp;' ?></small></div>
        <input class="cin" name="serija[<?= e($kljuc) ?>][kg]" inputmode="decimal" placeholder="<?= e($pkg) ?>" value="<?= e($s ? $sr['kg'] : '') ?>" aria-label="<?= te('Kilaža, serija %s', $i + 1) ?>" autocomplete="off">
        <input class="cin" name="serija[<?= e($kljuc) ?>][pon]" inputmode="numeric" placeholder="<?= e($ppon) ?>" value="<?= e($s ? $sr['pon'] : '') ?>" aria-label="<?= te('Ponavljanja, serija %s', $i + 1) ?>" autocomplete="off">
        <input type="hidden" name="serija[<?= e($kljuc) ?>][ok]" value="<?= $ok ? '1' : '0' ?>">
        <button type="button" class="check" data-ok aria-pressed="<?= $ok ? 'true' : 'false' ?>" aria-label="<?= te('Serija %s urađena', $i + 1) ?>"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></button>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</section>
<section class="card"><h2><?= te('Kako si danas?') ?></h2><p class="muted" style="margin:0"><?= te('1 je loše, 5 je odlično. Za stres 1 znači malo stresa.') ?></p>
  <div class="ci">
  <?php foreach (CHECKIN_POLJA as $polje => $natpis): $v = $ci[$polje] ?? null; ?>
    <div><label id="l-<?= $polje ?>"><?= te($natpis) ?></label><div class="scale" role="group" aria-labelledby="l-<?= $polje ?>">
      <input type="hidden" name="ci[<?= $polje ?>]" value="<?= e($v) ?>">
      <?php for ($n = 1; $n <= 5; $n++): ?><button type="button" data-skala="<?= $n ?>" aria-pressed="<?= (int)$v === $n ? 'true' : 'false' ?>"><?= $n ?></button><?php endfor; ?>
    </div></div>
  <?php endforeach; ?>
    <div><label id="l-bol"><?= te('Povreda / bol') ?></label><div class="scale" role="group" aria-labelledby="l-bol">
      <input type="hidden" name="bol" value="<?= e($ci['bol'] ?? '') ?>">
      <?php foreach (BOL_OPCIJE as $o): ?><button type="button" data-skala="<?= $o ?>" aria-pressed="<?= ($ci['bol'] ?? '') === $o ? 'true' : 'false' ?>"><?= te($o) ?></button><?php endforeach; ?>
    </div></div>
  </div>
  <label class="eyebrow" for="napomena"><?= te('Napomena za %sa', pod('sajt.trener_ime')) ?></label>
  <textarea class="cin" id="napomena" name="napomena" placeholder="<?= te('Kako je prošlo, šta je bilo teško, pitanja…') ?>"><?= e($s['napomena_klijenta'] ?? '') ?></textarea>
  <button class="btn primary block" name="akcija" value="zavrsi"><?= $gotov ? te('Sačuvaj izmene') : te('Završi trening') ?></button>
  <span class="muted autosave-status" data-autosave-status style="font-size:13px;text-align:center"><?= $s ? te('Sačuvano') : te('Upis se čuva automatski dok kucaš.') ?></span>
</section>
</form>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
