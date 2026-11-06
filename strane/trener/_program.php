<?php
/** Tab "Program" u detalju klijenta. Koristi $program, $pid, $id, $k, $ovde. */
$tid = unos_int($_GET['t'] ?? 0);
$t = null;
foreach ($program as $x) {
    if ((int)$x['id'] === $tid) {
        $t = $x;
    }
}
$t ??= $program[0] ?? null;
$sve_vezbe = redovi('SELECT id, naziv, naziv_en, aktivna, tezina, oprema, misici, misici_pomocni FROM vezbe ORDER BY naziv');
$drugi = redovi('SELECT kl.id, k.ime FROM klijenti kl JOIN korisnici k ON k.id = kl.korisnik_id
                 WHERE kl.id <> ? AND kl.arhiviran = 0 ORDER BY k.ime', [$id]);
$sabloni = sabloni();
?>
<div class="tabs" role="group" aria-label="Treninzi">
<?php foreach ($program as $x): $zs = sesija_ove_nedelje($id, (int)$x['id']); ?>
  <a href="<?= $ovde ?>?tab=program&t=<?= (int)$x['id'] ?>" aria-pressed="<?= $t && (int)$x['id'] === (int)$t['id'] ? 'true' : 'false' ?>"><?= ($zs['status'] ?? '') === 'zavrsena' ? '✓ ' : '' ?><?= $x['dan'] ? '<small class="dan-oznaka">' . DANI_KRATKO[(int)$x['dan']] . '</small> ' : '' ?><?= e($x['naziv']) ?></a>
<?php endforeach; ?>
<?php if (!$t): ?>
  <form method="post" style="display:contents"><?= csrf_polje() ?><button class="dodaj" name="akcija" value="dodaj_trening">+ Dodaj trening</button></form>
<?php else: ?>
  <button class="dodaj" form="program-forma" name="akcija" value="dodaj_trening">+ Dodaj trening</button>
<?php endif; ?>
</div>

<?php if (!$t): ?>
<section class="card"><p class="muted" style="margin:0">Program je prazan. Dodaj prvi trening, učitaj šablon ili kopiraj program od drugog klijenta (dole).</p></section>
<?php else:
    $tid = (int)$t['id'];
    $ses = poslednja_sesija_treninga($id, $tid);
    $sp = $ses ? sesija_puna((int)$ses['id']) : null;
    // Šta je klijent upisao, po ID-ju plan serije
    $upis = [];
    foreach ($sp['vezbe'] ?? [] as $sv) {
        foreach ($sv['serije'] as $ss) {
            if ($ss['plan_serija_id']) {
                $upis[(int)$ss['plan_serija_id']] = $ss;
            }
        }
    }
?>
<form method="post" id="program-forma" class="stack" data-cuvaj-upozorenje>
<?= csrf_polje() ?>
<input type="hidden" name="trening_id" value="<?= $tid ?>">
<section class="card"><div class="row between"><h2><?= e($t['naziv']) ?></h2>
  <div class="row"><?= $ses ? ($ses['status'] === 'zavrsena' ? '<span class="chip ok">Klijent završio ' . e(datum_lep($ses['datum'])) . '</span>' : '<span class="chip warn">U toku ' . e(datum_lep($ses['datum'])) . '</span>') : '<span class="chip">Čeka klijenta</span>' ?>
  <button class="link" name="akcija" value="ukloni_trening" data-potvrdi="Ukloniti ceo trening „<?= e($t['naziv']) ?>“? Istorija klijenta ostaje.">Ukloni trening</button></div></div>
  <div class="form">
    <label class="full">Naziv treninga<input class="tin" name="naziv" value="<?= e($t['naziv']) ?>" placeholder="npr. Noge A, Push dan" style="font-weight:600" maxlength="120"></label>
    <label>Dan u nedelji<select class="tin" name="dan"><option value="0">— bez dana (redom) —</option>
      <?php foreach (DANI_DUGO as $bd => $ime_dana): ?><option value="<?= $bd ?>"<?= (int)$t['dan'] === $bd ? ' selected' : '' ?>><?= $ime_dana ?></option><?php endforeach; ?></select></label>
    <label>Trajanje<input class="tin" name="trajanje" value="<?= e($t['trajanje']) ?>" maxlength="40"></label>
    <label class="full">Fokus<input class="tin" name="fokus" value="<?= e($t['fokus']) ?>" maxlength="120"></label>
  </div>
  <div class="legend"><span><i class="sw"></i>Ti upisuješ</span><span><i class="sw c"></i>Klijent upisuje</span></div>
</section>
<section class="card">
<?php foreach ($t['vezbe'] as $vi => $v): $tv = (int)$v['id']; ?>
  <div class="ex" id="v<?= $tv ?>">
    <div class="ex-head">
      <div style="flex:1 1 auto;min-width:0;max-width:340px">
      <select class="tin" name="vezba[<?= $tv ?>][vezba_id]" style="font-weight:700;font-size:16px" aria-label="Vežba" data-pretraga="Traži vežbu: naziv, mišić ili oprema…">
        <?= opcije_vezbi($sve_vezbe, (int)$v['vezba_id']) ?>
      </select>
      <?php $opis = vezba_opis($v); if ($opis !== ''): ?><div class="vezba-opis"><?= e($opis) ?></div><?php endif; ?>
      </div>
      <div class="row" style="gap:10px;flex-wrap:nowrap">
        <?php if ($vi > 0): ?><button class="link" name="akcija" value="vezba_gore:<?= $tv ?>" aria-label="Pomeri gore">↑</button><?php endif; ?>
        <?php if ($vi < count($t['vezbe']) - 1): ?><button class="link" name="akcija" value="vezba_dole:<?= $tv ?>" aria-label="Pomeri dole">↓</button><?php endif; ?>
        <button class="link" name="akcija" value="ukloni_vezbu:<?= $tv ?>" data-potvrdi="Ukloniti vežbu <?= e($v['naziv']) ?>?">Ukloni</button>
      </div>
    </div>
    <div class="edit">
      <?php foreach (['zagrevanje' => 'Zagrevanje', 'tempo' => 'Tempo', 'pauza' => 'Pauza'] as $kk => $l): ?>
      <label><?= $l ?><input class="tin" name="vezba[<?= $tv ?>][<?= $kk ?>]" value="<?= e($v[$kk]) ?>" maxlength="40"></label>
      <?php endforeach; ?>
    </div>
    <div class="tsets"><div class="tset hd"><span>#</span><span>Kilaža</span><span>Pon.</span><span>RIR</span><span>Urađeno</span><span></span></div>
    <?php foreach ($v['serije'] as $si => $s): $u = $upis[(int)$s['id']] ?? null; ?>
      <div class="tset"><span class="n"><?= $si + 1 ?></span>
        <input class="tin" name="serija[<?= (int)$s['id'] ?>][kg]" value="<?= e($s['kg']) ?>" aria-label="Kilaža, serija <?= $si + 1 ?>" maxlength="20">
        <input class="tin" name="serija[<?= (int)$s['id'] ?>][pon]" value="<?= e($s['pon']) ?>" aria-label="Ponavljanja, serija <?= $si + 1 ?>" maxlength="20">
        <input class="tin" name="serija[<?= (int)$s['id'] ?>][rir]" value="<?= e($s['rir']) ?>" aria-label="RIR, serija <?= $si + 1 ?>" maxlength="10">
        <span class="done-view sm" title="<?= $u && (int)$u['uradjeno'] ? 'Serija označena kao urađena' : '' ?>"><?= $u && ($u['kg'] !== '' || $u['pon'] !== '') ? e(($u['kg'] ?: '?') . ' × ' . ($u['pon'] ?: '?')) . ((int)$u['uradjeno'] ? ' ✓' : '') : '—' ?></span>
        <button class="link" name="akcija" value="ukloni_seriju:<?= (int)$s['id'] ?>" aria-label="Ukloni seriju <?= $si + 1 ?>"<?= count($v['serije']) < 2 ? ' disabled' : '' ?>>✕</button>
      </div>
    <?php endforeach; ?>
      <button class="btn small" name="akcija" value="dodaj_seriju:<?= $tv ?>" style="align-self:flex-start">+ Dodaj seriju</button>
    </div>
  </div>
<?php endforeach; ?>
  <button class="btn block" name="akcija" value="dodaj_vezbu">+ Dodaj vežbu</button>
</section>
<section class="card">
  <label class="eyebrow" for="tn">Napomena trenera</label>
  <textarea class="tin" id="tn" name="napomena" style="min-height:70px"><?= e($t['napomena']) ?></textarea>
  <span class="eyebrow">Napomena klijenta<?= $sp ? ' • ' . e(datum_lep($sp['datum'])) : '' ?></span>
  <div class="done-view"><?= $sp && trim((string)$sp['napomena_klijenta']) !== '' ? e_redovi($sp['napomena_klijenta']) : '<span class="muted">Još nema napomene.</span>' ?></div>
  <?= checkin_chipovi($sp['checkin'] ?? null) ?>
  <?php if ($sp && $sp['napomena_trenera'] !== $t['napomena']): ?><p class="muted" style="margin:0;font-size:13px">Klijent je radio po ranijoj verziji ovog treninga — istorija čuva tadašnji plan.</p><?php endif; ?>
  <button class="btn primary block" name="akcija" value="sacuvaj">Sačuvaj program</button>
</section>
</form>
<?php endif; ?>

<section class="card"><h3>Šabloni i kopiranje</h3>
  <p class="muted" style="margin:0;font-size:13px">Kopiranje i učitavanje šablona ZAMENJUJE trenutni program ovog klijenta. Istorija ostaje netaknuta.</p>
  <?php if ($drugi): ?>
  <form method="post" class="row"><?= csrf_polje() ?>
    <select class="tin" name="izvor" style="flex:1 1 180px" aria-label="Klijent od koga se kopira"><?php foreach ($drugi as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['ime']) ?></option><?php endforeach; ?></select>
    <button class="btn" name="akcija" value="kopiraj_od" data-potvrdi="Zameniti program ovog klijenta kopijom izabranog?">Kopiraj program od klijenta</button>
  </form>
  <?php endif; ?>
  <?php if ($program): ?>
  <form method="post" class="row"><?= csrf_polje() ?>
    <input class="tin" name="naziv" placeholder="Naziv šablona, npr. Početnik 3×" style="flex:1 1 180px" maxlength="120" aria-label="Naziv šablona" required>
    <button class="btn" name="akcija" value="sablon_sacuvaj">Sačuvaj kao šablon</button>
  </form>
  <?php endif; ?>
  <?php if ($sabloni): ?>
  <form method="post" class="row"><?= csrf_polje() ?>
    <select class="tin" name="sablon_id" style="flex:1 1 180px" aria-label="Šablon"><?php foreach ($sabloni as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['naziv']) ?> (<?= (int)$s['treninga'] ?> tr.)</option><?php endforeach; ?></select>
    <button class="btn" name="akcija" value="sablon_ucitaj" data-potvrdi="Zameniti program ovog klijenta izabranim šablonom?">Učitaj šablon</button>
    <button class="link" name="akcija" value="sablon_obrisi" data-potvrdi="Obrisati izabrani šablon? Programi klijenata se ne menjaju." style="color:var(--bad)">Obriši</button>
  </form>
  <?php endif; ?>
</section>
