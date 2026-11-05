<?php
require __DIR__ . '/_osnova.php';

$pid = program_klijenta($k['id']);
$blokiran = program_blokiran($k);

if (post()) {
    if ($blokiran) {
        greska(403, 'Članarina je istekla — program je zaključan dok se ne produži.');
    }
    $r = sacuvaj_unos_klijenta($k['id'], $_POST, ($_POST['akcija'] ?? '') === 'zavrsi');
    poruka(($_POST['bio_zavrsen'] ?? '') === '1' ? 'Izmene su sačuvane' : 'Bravo! ' . pod('sajt.trener_ime') . ' vidi tvoj trening');
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
$izabran ??= $sledeci ?? ($program[0] ?? null);

$naslov = 'Trening';
$aktivno = 'trening';
require KOREN . '/delovi/glava.php';
echo clanarina_upozorenje($k);
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Nedelja <?= nedelja_programa($k) ?><?= $program ? ' • ' . $uradjeno . ' od ' . count($program) . ' treninga' : '' ?></span>
  <h1>Zdravo, <?= e(ime_kratko($k['ime'])) ?></h1>
  <span class="muted"><?= $blokiran ? 'Program je zaključan dok se članarina ne produži.' : ($program ? ($sledeci ? 'Danas je na redu ' . e(mb_strtolower($sledeci['naziv'])) . '.' : 'Svi treninzi ove nedelje su urađeni. Bravo!') : e(pod('sajt.trener_ime')) . ' još priprema tvoj program.') ?></span></section>
<?php if ($izabran):
    $tid = (int)$izabran['id'];
    $s = $sesije[$tid] ? sesija_puna((int)$sesije[$tid]['id']) : null;
    $gotov = $s && $s['status'] === 'zavrsena';
    // Ako sesija postoji, prikazuje se njen snimak plana; inače trenutni plan
    $vezbe = $s ? $s['vezbe'] : $izabran['vezbe'];
    $ci = $s['checkin'] ?? null;
?>
<div class="tabs" role="group" aria-label="Treninzi">
<?php foreach ($program as $t): ?>
  <a href="/trening?t=<?= (int)$t['id'] ?>" aria-pressed="<?= (int)$t['id'] === $tid ? 'true' : 'false' ?>"><?= $zavrsen((int)$t['id']) ? '✓ ' : '' ?><?= e($t['naziv']) ?></a>
<?php endforeach; ?>
</div>
<form method="post" action="/trening" class="stack" data-autosave="/api/trening" id="trening-forma">
<?= csrf_polje() ?>
<input type="hidden" name="trening_id" value="<?= $tid ?>">
<input type="hidden" name="bio_zavrsen" value="<?= $gotov ? '1' : '0' ?>">
<section class="card">
  <div class="row between"><div><h2><?= e($s['naziv'] ?? $izabran['naziv']) ?><?= ($s['fokus'] ?? $izabran['fokus']) !== '' ? ' • ' . e($s['fokus'] ?? $izabran['fokus']) : '' ?></h2>
  <?php $traj = $s['trajanje'] ?? $izabran['trajanje']; if ($traj !== ''): ?><span class="muted">Trajanje oko <?= e($traj) ?></span><?php endif; ?></div>
  <?= $gotov ? '<span class="chip ok">Završeno</span>' : '' ?></div>
  <div class="legend"><span><i class="sw"></i>Upisuje trener</span><span><i class="sw c"></i>Upisuješ ti</span></div>
  <?php $nap = $s ? $s['napomena_trenera'] : $izabran['napomena']; if (trim((string)$nap) !== ''): ?>
  <div class="note-t"><b><?= e(pod('sajt.trener_ime')) ?>:</b> <?= e_redovi($nap) ?></div>
  <?php endif; ?>
</section>
<section class="card">
<?php if (!$vezbe): ?><p class="muted" style="margin:0">U ovom treningu još nema vežbi.</p><?php endif; ?>
<?php foreach ($vezbe as $v): ?>
  <div class="ex">
    <div class="ex-head"><div><div class="ex-name"><?= e($v['naziv']) ?></div>
      <span class="muted" style="font-size:13px"><?= serije_rec(count($v['serije'])) ?><?= $v['zagrevanje'] !== '' ? ' • Zagrevanje: ' . e($v['zagrevanje']) : '' ?><?= $v['tempo'] !== '' ? ' • Tempo ' . e($v['tempo']) : '' ?><?= $v['pauza'] !== '' ? ' • Pauza ' . e($v['pauza']) : '' ?></span>
      <?php if (trim((string)($v['tehnika'] ?? '')) !== ''): ?><details class="tehnika"><summary>Tehnika</summary><?= e_redovi($v['tehnika']) ?></details><?php endif; ?>
    </div><a class="btn small" href="<?= e(youtube_link((string)($v['youtube'] ?? ''), $v['naziv'])) ?>" target="_blank" rel="noopener">▶ Video</a></div>
    <div class="sets"><div class="set hd"><span>#</span><span>Plan</span><span>Tvoja kg</span><span>Tvoja pon.</span><span></span></div>
    <?php foreach ($v['serije'] as $i => $sr):
        $kljuc = $s ? 's' . $sr['id'] : 'p' . $sr['id'];
        $pkg = $s ? $sr['plan_kg'] : $sr['kg']; $ppon = $s ? $sr['plan_pon'] : $sr['pon']; $prir = $s ? $sr['plan_rir'] : $sr['rir'];
        $ok = $s && (int)$sr['uradjeno'];
    ?>
      <div class="set" data-serija="<?= e($kljuc) ?>"><span class="n"><?= $i + 1 ?></span>
        <div class="plan"><b><?= e(kg_tekst($pkg)) ?></b> × <?= e($ppon !== '' ? $ppon : '—') ?><small><?= $prir !== '' ? 'RIR ' . e($prir) : '&nbsp;' ?></small></div>
        <input class="cin" name="serija[<?= e($kljuc) ?>][kg]" inputmode="decimal" placeholder="<?= e($pkg) ?>" value="<?= e($s ? $sr['kg'] : '') ?>" aria-label="Kilaža, serija <?= $i + 1 ?>" autocomplete="off">
        <input class="cin" name="serija[<?= e($kljuc) ?>][pon]" inputmode="numeric" placeholder="<?= e($ppon) ?>" value="<?= e($s ? $sr['pon'] : '') ?>" aria-label="Ponavljanja, serija <?= $i + 1 ?>" autocomplete="off">
        <input type="hidden" name="serija[<?= e($kljuc) ?>][ok]" value="<?= $ok ? '1' : '0' ?>">
        <button type="button" class="check" data-ok aria-pressed="<?= $ok ? 'true' : 'false' ?>" aria-label="Serija <?= $i + 1 ?> urađena"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></button>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</section>
<section class="card"><h2>Kako si danas?</h2><p class="muted" style="margin:0">1 je loše, 5 je odlično. Za stres 1 znači malo stresa.</p>
  <div class="ci">
  <?php foreach (CHECKIN_POLJA as $polje => $natpis): $v = $ci[$polje] ?? null; ?>
    <div><label id="l-<?= $polje ?>"><?= $natpis ?></label><div class="scale" role="group" aria-labelledby="l-<?= $polje ?>">
      <input type="hidden" name="ci[<?= $polje ?>]" value="<?= e($v) ?>">
      <?php for ($n = 1; $n <= 5; $n++): ?><button type="button" data-skala="<?= $n ?>" aria-pressed="<?= (int)$v === $n ? 'true' : 'false' ?>"><?= $n ?></button><?php endfor; ?>
    </div></div>
  <?php endforeach; ?>
    <div><label id="l-bol">Povreda / bol</label><div class="scale" role="group" aria-labelledby="l-bol">
      <input type="hidden" name="bol" value="<?= e($ci['bol'] ?? '') ?>">
      <?php foreach (BOL_OPCIJE as $o): ?><button type="button" data-skala="<?= $o ?>" aria-pressed="<?= ($ci['bol'] ?? '') === $o ? 'true' : 'false' ?>"><?= $o ?></button><?php endforeach; ?>
    </div></div>
  </div>
  <label class="eyebrow" for="napomena">Napomena za <?= e(pod('sajt.trener_ime')) ?>a</label>
  <textarea class="cin" id="napomena" name="napomena" placeholder="Kako je prošlo, šta je bilo teško, pitanja…"><?= e($s['napomena_klijenta'] ?? '') ?></textarea>
  <button class="btn primary block" name="akcija" value="zavrsi"><?= $gotov ? 'Sačuvaj izmene' : 'Završi trening' ?></button>
  <span class="muted autosave-status" data-autosave-status style="font-size:13px;text-align:center"><?= $s ? 'Sačuvano' : 'Upis se čuva automatski dok kucaš.' ?></span>
</section>
</form>
<?php endif; ?>
<?php require KOREN . '/delovi/podnozje.php';
