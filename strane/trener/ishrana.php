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
            $kl = unos_int($_POST['klijent_id'] ?? 0) ?: null;
            if ($kl && !vrednost('SELECT 1 FROM klijenti WHERE id = ?', [$kl])) {
                $kl = null;
            }
            $red = (int)vrednost('SELECT COALESCE(MAX(redosled), 0) + 1 FROM primeri_ishrane');
            upit('INSERT INTO primeri_ishrane (klijent_id, naziv, redosled) VALUES (?, ?, ?)', [$kl, unos($_POST['naziv'] ?? '', 120) ?: 'Primer dana ' . $red, $red]);
            $pid = poslednji_id();
            upit('INSERT INTO primeri_obroci (primer_id, redosled, naziv) VALUES (?, 1, ?)', [$pid, 'Obrok 1']);
            poruka('Primer je dodat');
            preusmeri('/trener/ishrana?p=' . $pid);
        case 'primer_kopiraj':
            $izvor = red('SELECT * FROM primeri_ishrane WHERE id = ?', [$id]);
            $kl = unos_int($_POST['kopija_za'] ?? 0) ?: null;
            if (!$izvor || ($kl && !vrednost('SELECT 1 FROM klijenti WHERE id = ?', [$kl]))) {
                greska(404);
            }
            $ime_kl = $kl ? (string)vrednost('SELECT k.ime FROM klijenti kl JOIN korisnici k ON k.id = kl.korisnik_id WHERE kl.id = ?', [$kl]) : '';
            $novi = transakcija(fn() => kopiraj_primer($id, $kl, mb_substr($izvor['naziv'] . ($kl ? ' — ' . ime_kratko($ime_kl) : ' (kopija)'), 0, 120)));
            poruka($kl ? 'Napravljena je kopija za ' . ime_kratko($ime_kl) . ' — sada je prilagodi' : 'Napravljena je kopija');
            preusmeri('/trener/ishrana?p=' . $novi);
        case 'primer_sacuvaj':
        case 'obrok_dodaj':
        case 'obrok_ukloni':
        case 'stavka_dodaj':
        case 'stavka_ukloni':
            if (!vrednost('SELECT 1 FROM primeri_ishrane WHERE id = ?', [$id])) {
                greska(404);
            }
            transakcija(function () use ($id, $akcija, $arg) {
                $kl = unos_int($_POST['klijent_id'] ?? 0) ?: null;
                if ($kl && !vrednost('SELECT 1 FROM klijenti WHERE id = ?', [$kl])) {
                    $kl = null;
                }
                upit('UPDATE primeri_ishrane SET naziv = ?, klijent_id = ? WHERE id = ?', [unos($_POST['naziv'] ?? '', 120) ?: 'Primer dana', $kl, $id]);
                $moji = array_map('intval', array_column(redovi('SELECT id FROM primeri_obroci WHERE primer_id = ?', [$id]), 'id'));
                foreach ((array)($_POST['obrok'] ?? []) as $oid => $o) {
                    if (!is_array($o) || !in_array((int)$oid, $moji, true)) { continue; }
                    upit('UPDATE primeri_obroci SET naziv = ?, opis = ?, kcal = ?, p = ?, uh = ?, m = ? WHERE id = ?', [
                        unos($o['naziv'] ?? '', 80), unos($o['opis'] ?? '', 500),
                        max(0, unos_int($o['kcal'] ?? 0)), max(0, unos_int($o['p'] ?? 0)), max(0, unos_int($o['uh'] ?? 0)), max(0, unos_int($o['m'] ?? 0)),
                        (int)$oid,
                    ]);
                }
                // Gramaže postojećih stavki: vrednosti se računaju iznova iz baze namirnica
                foreach ((array)($_POST['stavka'] ?? []) as $sid => $st) {
                    $staro = red('SELECT ps.*, n.kcal nkcal, n.p np, n.uh nuh, n.m nm FROM primeri_stavke ps
                                  JOIN primeri_obroci o ON o.id = ps.obrok_id LEFT JOIN namirnice n ON n.id = ps.namirnica_id
                                  WHERE ps.id = ? AND o.primer_id = ?', [(int)$sid, $id]);
                    $g = max(0, min(3000, unos_int($st['grami'] ?? 0)));
                    if (!$staro || $g < 1) { continue; }
                    $v = $staro['nkcal'] !== null
                        ? vrednosti_namirnice(['kcal' => $staro['nkcal'], 'p' => $staro['np'], 'uh' => $staro['nuh'], 'm' => $staro['nm']], $g)
                        : array_map(fn($x) => round((float)$x * $g / max(1, (int)$staro['grami']), 1), ['kcal' => $staro['kcal'], 'p' => $staro['p'], 'uh' => $staro['uh'], 'm' => $staro['m']]);
                    upit('UPDATE primeri_stavke SET grami = ?, kcal = ?, p = ?, uh = ?, m = ? WHERE id = ?', [$g, $v['kcal'], $v['p'], $v['uh'], $v['m'], (int)$sid]);
                }
                // Nova namirnica u obroku: dodaje se svuda gde su upisani grami
                foreach ((array)($_POST['nova'] ?? []) as $oid => $nv) {
                    if (!is_array($nv) || !in_array((int)$oid, $moji, true)) { continue; }
                    $g = max(0, min(3000, unos_int($nv['grami'] ?? 0)));
                    $n = red('SELECT * FROM namirnice WHERE id = ?', [unos_int($nv['namirnica_id'] ?? 0)]);
                    if (!$n || $g < 1) { continue; }
                    $v = vrednosti_namirnice($n, $g);
                    $red = (int)vrednost('SELECT COALESCE(MAX(redosled), 0) + 1 FROM primeri_stavke WHERE obrok_id = ?', [(int)$oid]);
                    upit('INSERT INTO primeri_stavke (obrok_id, namirnica_id, redosled, naziv, grami, kcal, p, uh, m) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [(int)$oid, $n['id'], $red, $n['naziv'], $g, $v['kcal'], $v['p'], $v['uh'], $v['m']]);
                }
                if ($akcija === 'stavka_ukloni') {
                    upit('DELETE ps FROM primeri_stavke ps JOIN primeri_obroci o ON o.id = ps.obrok_id WHERE ps.id = ? AND o.primer_id = ?', [(int)$arg, $id]);
                }
                if ($akcija === 'obrok_dodaj') {
                    $n = (int)vrednost('SELECT COUNT(*) FROM primeri_obroci WHERE primer_id = ?', [$id]);
                    upit('INSERT INTO primeri_obroci (primer_id, redosled, naziv) VALUES (?, ?, ?)', [$id, $n + 1, 'Obrok ' . ($n + 1)]);
                } elseif ($akcija === 'obrok_ukloni') {
                    upit('DELETE FROM primeri_obroci WHERE id = ? AND primer_id = ?', [(int)$arg, $id]);
                }
                foreach ($moji as $oid) {
                    preracunaj_obrok($oid);
                }
            });
            poruka('Primer je sačuvan');
            preusmeri('/trener/ishrana?p=' . $id . ($akcija === 'stavka_dodaj' || $akcija === 'stavka_ukloni' ? '#o' . (int)$arg : ''));
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
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Primeri dana • baza namirnica</span><h1>Ishrana</h1><span class="muted">Obrok sastavljaš iz baze namirnica — kalorije i makroi se računaju sami. Primer može biti za sve klijente ili samo za jednog (👤). Dnevni okvir postavljaš svakom klijentu u njegovom profilu.</span></section>
<div class="tabs" role="group" aria-label="Ishrana">
  <a href="/trener/ishrana" aria-pressed="<?= $tab === 'primeri' ? 'true' : 'false' ?>">Primeri dana</a>
  <a href="/trener/ishrana?tab=namirnice" aria-pressed="<?= $tab === 'namirnice' ? 'true' : 'false' ?>">Baza namirnica</a>
</div>
<?php if ($tab === 'primeri'):
    $nam = namirnice();
    $klijenti_lista = redovi('SELECT kl.id, k.ime FROM klijenti kl JOIN korisnici k ON k.id = kl.korisnik_id WHERE kl.arhiviran = 0 ORDER BY k.ime');
    $imena = array_column($klijenti_lista, 'ime', 'id');
?>
<div class="tabs" role="group" aria-label="Primeri">
<?php foreach ($primeri as $p): ?><a href="/trener/ishrana?p=<?= (int)$p['id'] ?>" aria-pressed="<?= $izabran && (int)$p['id'] === (int)$izabran['id'] ? 'true' : 'false' ?>"><?= $p['klijent_id'] ? '👤 ' : '' ?><?= e($p['naziv']) ?></a><?php endforeach; ?>
  <form method="post" style="display:contents"><?= csrf_polje() ?><button class="dodaj" name="akcija" value="primer_dodaj">+ Novi primer</button></form>
</div>
<?php if ($izabran): $z = zbir_obroka($izabran['obroci']); ?>
<script type="application/json" id="namirnice-podaci"><?= json_encode(array_map(fn($n) => [(int)$n['id'], (float)$n['kcal'], (float)$n['p'], (float)$n['uh'], (float)$n['m']], $nam), JSON_HEX_TAG) ?></script>
<form class="card" method="post" data-cuvaj-upozorenje data-obroci><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int)$izabran['id'] ?>">
  <div class="form">
    <label class="full">Naziv primera<input class="tin" name="naziv" value="<?= e($izabran['naziv']) ?>" style="font-weight:600" maxlength="120"></label>
    <label class="full">Za koga<select class="tin" name="klijent_id">
      <option value="0">Svi klijenti</option>
      <?php foreach ($klijenti_lista as $kl): ?><option value="<?= (int)$kl['id'] ?>"<?= (int)$izabran['klijent_id'] === (int)$kl['id'] ? ' selected' : '' ?>>Samo: <?= e($kl['ime']) ?></option><?php endforeach; ?>
    </select></label>
  </div>
  <?php foreach ($izabran['obroci'] as $o): $oid = (int)$o['id']; $ima = (bool)$o['stavke']; ?>
  <div class="ex" id="o<?= $oid ?>" data-obrok>
    <div class="ex-head"><input class="tin" name="obrok[<?= $oid ?>][naziv]" value="<?= e($o['naziv']) ?>" style="font-weight:700;max-width:200px" aria-label="Naziv obroka" maxlength="80">
      <button class="link" name="akcija" value="obrok_ukloni:<?= $oid ?>" data-potvrdi="Ukloniti obrok?">Ukloni</button></div>
    <div class="stavke">
      <?php foreach ($o['stavke'] as $st): $g = max(1, (int)$st['grami']); ?>
      <div class="stavka-red" data-stavka data-na100="<?= e(implode(',', [round($st['kcal'] / $g * 100, 2), round($st['p'] / $g * 100, 2), round($st['uh'] / $g * 100, 2), round($st['m'] / $g * 100, 2)])) ?>">
        <span class="naziv"><?= e($st['naziv']) ?></span>
        <label class="grami"><input class="tin" type="number" min="1" max="3000" inputmode="numeric" name="stavka[<?= (int)$st['id'] ?>][grami]" value="<?= (int)$st['grami'] ?>" aria-label="Grama: <?= e($st['naziv']) ?>"> g</label>
        <span class="k" data-vrednost><?= (int)round($st['kcal']) ?> kcal<br>P <?= broj($st['p'], 0) ?> • UH <?= broj($st['uh'], 0) ?> • M <?= broj($st['m'], 0) ?></span>
        <button class="link" name="akcija" value="stavka_ukloni:<?= (int)$st['id'] ?>" aria-label="Ukloni <?= e($st['naziv']) ?>">✕</button>
      </div>
      <?php endforeach; ?>
      <div class="stavka-red nova" data-nova>
        <select class="tin naziv" name="nova[<?= $oid ?>][namirnica_id]" aria-label="Namirnica iz baze">
          <?php foreach ($nam as $n): ?><option value="<?= (int)$n['id'] ?>"><?= e($n['naziv']) ?></option><?php endforeach; ?>
        </select>
        <label class="grami"><input class="tin" type="number" min="1" max="3000" inputmode="numeric" name="nova[<?= $oid ?>][grami]" placeholder="g" aria-label="Grama"> g</label>
        <span class="k" data-vrednost></span>
        <button class="btn small primary" name="akcija" value="stavka_dodaj:<?= $oid ?>">+ Dodaj</button>
      </div>
    </div>
    <label class="eyebrow">Napomena (opciono)<textarea class="tin" name="obrok[<?= $oid ?>][opis]" rows="2" maxlength="500" style="text-transform:none;letter-spacing:0" placeholder="<?= $ima ? 'npr. salata po želji, začini' : 'Šta jede — ili izaberi namirnice iznad' ?>"><?= e($o['opis']) ?></textarea></label>
    <?php if ($ima): ?>
    <div class="meal" data-zbir-obroka><b>Obrok ukupno</b><div class="k"><b style="color:var(--ink)" data-zbir-kcal><?= (int)$o['kcal'] ?> kcal</b><br><span data-zbir-makro>P <?= (int)$o['p'] ?> • UH <?= (int)$o['uh'] ?> • M <?= (int)$o['m'] ?></span></div></div>
    <?php foreach (['kcal', 'p', 'uh', 'm'] as $kk): ?><input type="hidden" name="obrok[<?= $oid ?>][<?= $kk ?>]" value="<?= (int)$o[$kk] ?>"><?php endforeach; ?>
    <?php else: ?>
    <p class="muted" style="margin:0;font-size:13px">Bez namirnica iz baze — vrednosti upisuješ ručno:</p>
    <div class="edit">
      <?php foreach (['kcal' => 'kcal', 'p' => 'P (g)', 'uh' => 'UH (g)', 'm' => 'M (g)'] as $kk => $l): ?><label><?= $l ?><input class="tin" type="number" min="0" inputmode="numeric" name="obrok[<?= $oid ?>][<?= $kk ?>]" value="<?= (int)$o[$kk] ?>"></label><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="meal"><b>Ukupno za dan</b><div class="k"><b style="color:var(--ink)"><?= (int)$z['kcal'] ?> kcal</b><br>P <?= (int)$z['p'] ?> • UH <?= (int)$z['uh'] ?> • M <?= (int)$z['m'] ?></div></div>
  <button class="btn block" name="akcija" value="obrok_dodaj">+ Dodaj obrok</button>
  <button class="btn primary block" name="akcija" value="primer_sacuvaj">Sačuvaj primer</button>
  <div class="row" style="border-top:1px solid var(--line);padding-top:12px">
    <select class="tin" name="kopija_za" style="flex:1 1 180px" aria-label="Kopija za"><option value="0">Kopija za sve klijente</option><?php foreach ($klijenti_lista as $kl): ?><option value="<?= (int)$kl['id'] ?>">Kopija za: <?= e($kl['ime']) ?></option><?php endforeach; ?></select>
    <button class="btn" name="akcija" value="primer_kopiraj" formnovalidate>Napravi kopiju</button>
  </div>
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
