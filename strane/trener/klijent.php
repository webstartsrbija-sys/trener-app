<?php
require __DIR__ . '/_osnova.php';

$k = klijent((int)$param[0]);
if (!$k) {
    greska(404);
}
$id = $k['id'];
$pid = program_klijenta($id);
$tab = in_array($_GET['tab'] ?? '', ['program', 'istorija', 'napredak', 'ishrana', 'clanarina', 'nalog'], true) ? $_GET['tab'] : 'program';
$ovde = '/trener/klijent/' . $id;

/* -------------------------------------------------------------------------
   Izmene (svaka akcija proverava da objekat pripada OVOM klijentu)
   ------------------------------------------------------------------------- */
if (post()) {
    $akcija = (string)($_POST['akcija'] ?? '');
    [$akcija, $arg] = array_pad(explode(':', $akcija, 2), 2, '');
    $arg = (int)$arg;
    $tid = unos_int($_POST['trening_id'] ?? 0);
    $nazad = $ovde . '?tab=program&t=' . $tid;

    $tv_moja = fn(int $tv) => (bool)vrednost('SELECT 1 FROM trening_vezbe tv JOIN treninzi t ON t.id = tv.trening_id
                                                WHERE tv.id = ? AND t.program_id = ?', [$tv, $pid]);

    try {
        switch ($akcija) {
            case 'sacuvaj': case 'dodaj_vezbu': case 'ukloni_vezbu': case 'dodaj_seriju': case 'ukloni_seriju':
            case 'ukloni_trening': case 'vezba_gore': case 'vezba_dole':
                if (!trening_u_programu($tid, $pid)) {
                    greska(404);
                }
                transakcija(function () use ($akcija, $arg, $tid, $pid, $tv_moja, &$nazad, $ovde) {
                    sacuvaj_trening_forma($tid, $_POST);
                    if ($akcija === 'dodaj_vezbu') {
                        $nazad .= '#v' . dodaj_vezbu($tid);
                    } elseif ($akcija === 'ukloni_vezbu' && $tv_moja($arg)) {
                        upit('DELETE FROM trening_vezbe WHERE id = ?', [$arg]);
                    } elseif ($akcija === 'dodaj_seriju' && $tv_moja($arg)) {
                        dodaj_seriju($arg);
                        $nazad .= '#v' . $arg;
                    } elseif ($akcija === 'ukloni_seriju') {
                        $tv = (int)vrednost('SELECT trening_vezba_id FROM plan_serije WHERE id = ?', [$arg]);
                        if ($tv && $tv_moja($tv) && (int)vrednost('SELECT COUNT(*) FROM plan_serije WHERE trening_vezba_id = ?', [$tv]) > 1) {
                            upit('DELETE FROM plan_serije WHERE id = ?', [$arg]);
                        }
                        $nazad .= '#v' . $tv;
                    } elseif (($akcija === 'vezba_gore' || $akcija === 'vezba_dole') && $tv_moja($arg)) {
                        $ids = array_map('intval', array_column(redovi('SELECT id FROM trening_vezbe WHERE trening_id = ? ORDER BY redosled, id', [$tid]), 'id'));
                        $i = array_search($arg, $ids, true);
                        $j = $akcija === 'vezba_gore' ? $i - 1 : $i + 1;
                        if ($i !== false && isset($ids[$j])) {
                            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                        }
                        foreach ($ids as $r => $x) {
                            upit('UPDATE trening_vezbe SET redosled = ? WHERE id = ?', [$r + 1, $x]);
                        }
                        $nazad .= '#v' . $arg;
                    } elseif ($akcija === 'ukloni_trening') {
                        upit('DELETE FROM treninzi WHERE id = ?', [$tid]);
                        $nazad = $ovde . '?tab=program';
                    }
                });
                poruka(match ($akcija) {
                    'sacuvaj' => 'Program je sačuvan i klijent ga odmah vidi',
                    'ukloni_trening' => 'Trening je uklonjen (istorija ostaje)',
                    default => 'Sačuvano',
                });
                preusmeri($nazad);

            case 'dodaj_trening':
                if (trening_u_programu($tid, $pid)) {
                    sacuvaj_trening_forma($tid, $_POST);
                }
                $novi = dodaj_trening($pid);
                poruka('Dodat je novi trening');
                preusmeri($ovde . '?tab=program&t=' . $novi);

            case 'kopiraj_od':
                $izvor = unos_int($_POST['izvor'] ?? 0);
                $ipid = (int)vrednost('SELECT id FROM programi WHERE klijent_id = ?', [$izvor]);
                if ($ipid && $izvor !== $id) {
                    transakcija(fn() => kopiraj_program($ipid, $pid));
                    poruka('Program je kopiran');
                }
                preusmeri($ovde . '?tab=program');

            case 'sablon_sacuvaj':
                $naziv = unos($_POST['naziv'] ?? '', 120);
                if ($naziv === '') {
                    poruka('Upiši naziv šablona.', 'greska');
                } else {
                    transakcija(fn() => sacuvaj_kao_sablon($pid, $naziv));
                    poruka('Šablon „' . $naziv . '“ je sačuvan');
                }
                preusmeri($ovde . '?tab=program');

            case 'sablon_ucitaj':
                $sid = unos_int($_POST['sablon_id'] ?? 0);
                if (vrednost('SELECT 1 FROM programi WHERE id = ? AND klijent_id IS NULL', [$sid])) {
                    transakcija(fn() => kopiraj_program($sid, $pid));
                    poruka('Šablon je učitan');
                }
                preusmeri($ovde . '?tab=program');

            case 'sablon_obrisi':
                upit('DELETE FROM programi WHERE id = ? AND klijent_id IS NULL', [unos_int($_POST['sablon_id'] ?? 0)]);
                poruka('Šablon je obrisan');
                preusmeri($ovde . '?tab=program');

            case 'okvir':
                $v = array_map(fn($x) => max(0, min(20000, unos_int($_POST[$x] ?? 0))), ['kcal' => 'kcal', 'p' => 'p', 'uh' => 'uh', 'm' => 'm']);
                upit('INSERT INTO okvir_ishrane (klijent_id, kcal, p, uh, m, izmenjeno) VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE kcal = VALUES(kcal), p = VALUES(p), uh = VALUES(uh), m = VALUES(m), izmenjeno = VALUES(izmenjeno)',
                    [$id, $v['kcal'], $v['p'], $v['uh'], $v['m'], sada()]);
                poruka('Okvir ishrane je sačuvan');
                preusmeri($ovde . '?tab=ishrana');

            case 'produzi':
                $osnova = max((string)$k['clanarina_do'], danas());
                $novo = dan_plus($osnova, (int)pod('clanarina.produzenje_dana', 30));
                produzi_clanarinu($id, $novo, unos_broj($_POST['iznos'] ?? ''), unos($_POST['napomena'] ?? '', 255));
                poruka('Članarina važi do ' . datum_lep($novo));
                preusmeri($ovde . '?tab=clanarina');

            case 'produzi_datum':
                $novo = $_POST['datum'] ?? '';
                if (!ispravan_datum($novo)) {
                    poruka('Izaberi datum.', 'greska');
                } else {
                    produzi_clanarinu($id, $novo, unos_broj($_POST['iznos'] ?? ''), unos($_POST['napomena'] ?? '', 255));
                    poruka('Članarina važi do ' . datum_lep($novo));
                }
                preusmeri($ovde . '?tab=clanarina');

            case 'nalog':
                $ime = unos($_POST['ime'] ?? '', 120);
                $email = mb_strtolower(unos($_POST['email'] ?? '', 190));
                if ($ime === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    poruka('Ime i ispravan email su obavezni.', 'greska');
                } elseif (vrednost('SELECT 1 FROM korisnici WHERE email = ? AND id <> ?', [$email, $k['korisnik_id']])) {
                    poruka('Drugi nalog već koristi taj email.', 'greska');
                } else {
                    $dani = array_values(array_filter(array_map('intval', (array)($_POST['dani'] ?? [])), fn($x) => $x >= 1 && $x <= 7));
                    $tn = max(1, min(7, unos_int($_POST['treninga_nedeljno'] ?? 3)));
                    upit('UPDATE korisnici SET ime = ?, email = ? WHERE id = ?', [$ime, $email, $k['korisnik_id']]);
                    upit('UPDATE klijenti SET cilj = ?, datum_pocetka = ?, treninga_nedeljno = ?, dani_treninga = ?, oznake = ?, napomena = ? WHERE id = ?', [
                        unos($_POST['cilj'] ?? '', 80),
                        ispravan_datum($_POST['datum_pocetka'] ?? null) ? $_POST['datum_pocetka'] : $k['datum_pocetka'],
                        $tn, implode(',', $dani ?: podrazumevani_dani($tn)),
                        implode(', ', array_filter(array_map('trim', explode(',', unos($_POST['oznake'] ?? '', 255))))),
                        unos($_POST['napomena'] ?? '', 5000), $id,
                    ]);
                    poruka('Podaci su sačuvani');
                }
                preusmeri($ovde . '?tab=nalog');

            case 'slika':
                $r = primi_profilnu();
                if (isset($r['greska'])) {
                    poruka($r['greska'], 'greska');
                } else {
                    postavi_profilnu($k['korisnik_id'], $r['ime']);
                    poruka('Profilna slika je postavljena');
                }
                preusmeri($ovde . '?tab=nalog');

            case 'slika_ukloni':
                postavi_profilnu($k['korisnik_id'], null);
                poruka('Profilna slika je uklonjena');
                preusmeri($ovde . '?tab=nalog');

            case 'lozinka':
                $l1 = (string)($_POST['lozinka'] ?? '');
                $g = greska_nove_lozinke($l1, (string)($_POST['lozinka2'] ?? ''));
                if ($g) {
                    poruka($g, 'greska');
                } else {
                    postavi_lozinku($k['korisnik_id'], $l1);
                    poruka('Nova lozinka za ' . ime_kratko($k['ime']) . ' je postavljena. Javi je klijentu.');
                }
                preusmeri($ovde . '?tab=nalog');

            case 'pozivnica':
                require_once KOREN . '/app/mejl.php';
                $kor = ['ime' => $k['ime'], 'email' => $k['email']];
                $tok = napravi_token($k['korisnik_id'], (int)$k['ima_lozinku'] ? 'reset' : 'poziv');
                $ok = (int)$k['ima_lozinku'] ? mejl_reset($kor, $tok) : mejl_pozivnica($kor, $tok);
                $_SESSION['poziv_link'] = ['klijent' => $id, 'link' => adresa('/lozinka?t=' . $tok), 'poslat' => $ok];
                poruka($ok ? 'Mejl sa linkom je poslat' : 'Mejl nije otišao — pošalji link ručno', $ok ? 'ok' : 'greska');
                preusmeri($ovde . '?tab=nalog');

            case 'arhiviraj':
            case 'vrati':
                $a = $akcija === 'arhiviraj' ? 1 : 0;
                upit('UPDATE klijenti SET arhiviran = ? WHERE id = ?', [$a, $id]);
                upit('UPDATE korisnici SET aktivan = ? WHERE id = ?', [1 - $a, $k['korisnik_id']]);
                if ($a) {
                    upit('DELETE FROM zapamcene_prijave WHERE korisnik_id = ?', [$k['korisnik_id']]);
                }
                poruka($a ? 'Klijent je arhiviran i više ne može da se prijavi' : 'Klijent je vraćen iz arhive');
                preusmeri($a ? '/trener/klijenti' : $ovde . '?tab=nalog');
        }
    } catch (RuntimeException $g) {
        poruka($g->getMessage(), 'greska');
        preusmeri($nazad);
    }
    greska(400);
}

/* -------------------------------------------------------------------------
   Prikaz
   ------------------------------------------------------------------------- */
$program = program_stablo($pid);
$uradjeno_nedelja = 0;
foreach ($program as $t) {
    if ((sesija_ove_nedelje($id, (int)$t['id'])['status'] ?? '') === 'zavrsena') {
        $uradjeno_nedelja++;
    }
}
$dosl = doslednost($k);
$proseci = nedeljni_proseci($k);
$poziv = ($_SESSION['poziv_link']['klijent'] ?? 0) === $id ? $_SESSION['poziv_link'] : null;
unset($_SESSION['poziv_link']);

$naslov = $k['ime'];
$aktivno = 'klijenti';
require KOREN . '/delovi/glava.php';
$n = clanarina_dana($k);
?>
<a class="link" href="/trener/klijenti<?= (int)$k['arhiviran'] ? '?arhiva=1' : '' ?>">← Svi klijenti</a>
<section class="band band-profil"><?= avatar($k['korisnik_id'], $k['ime'], $k['slika'], 'lg') ?><div class="stack" style="gap:6px;min-width:0"><span class="eyebrow" style="color:inherit;opacity:.7"><?= e($k['cilj'] ?: 'Klijent') ?> • od <?= e(datum_kratko($k['datum_pocetka'])) ?></span><h1><?= e($k['ime']) ?></h1>
  <span class="muted">Nedelja <?= nedelja_programa($k) ?> • <?= (int)$k['treninga_nedeljno'] ?> treninga nedeljno<?= (int)$k['arhiviran'] ? ' • ARHIVIRAN' : '' ?></span></div></section>
<?php if ($poziv): ?>
<section class="card alert <?= $poziv['poslat'] ? 'warn' : 'bad' ?>"><b><?= $poziv['poslat'] ? 'Mejl je poslat.' : 'Mejl nije otišao.' ?></b>
  <span>Ako ne stigne, pošalji klijentu ovaj link (npr. porukom). Važi <?= (int)pod('bezbednost.token_sati', 48) ?> sata i može da se iskoristi jednom:</span>
  <div class="row"><input class="tin" value="<?= e($poziv['link']) ?>" readonly style="flex:1" id="poziv-link" aria-label="Link za lozinku"><button type="button" class="btn small" data-kopiraj="poziv-link">Kopiraj</button></div></section>
<?php endif; ?>
<?php if ($n === null || $n <= (int)pod('clanarina.upozorenje_dana', 3)): ?>
<section class="card"><div class="row between"><div><span class="eyebrow">Članarina</span><div style="font-weight:700;font-size:17px"><?= $n === null ? 'Nije postavljena' : ($n < 0 ? 'Istekla ' . e(datum_lep($k['clanarina_do'])) : 'Važi do ' . e(datum_lep($k['clanarina_do']))) ?></div></div><?= clanarina_chip($k) ?></div>
  <form method="post" class="row between"><?= csrf_polje() ?><span class="muted" style="font-size:13px"><?= $n !== null && $n < 0 ? 'Klijent je dobio upozorenje 3 dana pre isteka.' : 'Klijent dobija upozorenje 3 dana pre isteka.' ?></span><button class="btn small primary" name="akcija" value="produzi">Produži <?= (int)pod('clanarina.produzenje_dana', 30) ?> dana</button></form></section>
<?php endif; ?>
<div class="stats">
  <div class="stat"><b><?= $dosl !== null ? $dosl . '%' : '—' ?></b><span>doslednost</span></div>
  <div class="stat"><b><?= $proseci ? broj(end($proseci)['kg']) : '—' ?></b><span>kg prosek</span></div>
  <div class="stat"><b><?= $uradjeno_nedelja ?>/<?= count($program) ?></b><span>ove nedelje</span></div>
</div>
<div class="tabs" role="group" aria-label="Klijent">
<?php foreach (['program' => 'Program', 'istorija' => 'Istorija', 'napredak' => 'Napredak', 'ishrana' => 'Ishrana', 'clanarina' => 'Članarina', 'nalog' => 'Nalog'] as $kk => $l): ?>
  <a href="<?= $ovde ?>?tab=<?= $kk ?>" aria-pressed="<?= $tab === $kk ? 'true' : 'false' ?>"><?= $l ?></a>
<?php endforeach; ?>
</div>
<?php
if ($tab === 'program') {
    require __DIR__ . '/_program.php';
} elseif ($tab === 'istorija') {
    $url = $ovde . '?tab=istorija&';
    require KOREN . '/delovi/istorija.php';
} elseif ($tab === 'napredak') {
    $moze_upis = false;
    require KOREN . '/delovi/napredak.php';
} elseif ($tab === 'ishrana') {
    $okv = okvir($k) ?? ['kcal' => '', 'p' => '', 'uh' => '', 'm' => ''];
?>
<form class="card" method="post"><?= csrf_polje() ?><h2>Dnevni okvir</h2>
  <div class="form">
  <?php foreach ([['kcal', 'Kalorije'], ['p', 'Protein (g)'], ['uh', 'UH (g)'], ['m', 'Masti (g)']] as [$kk, $l]): ?>
    <label><?= $l ?><input class="tin" type="number" inputmode="numeric" min="0" name="<?= $kk ?>" value="<?= e($okv[$kk]) ?>" required></label>
  <?php endforeach; ?>
  </div>
  <p class="muted" style="margin:0;font-size:13px">Primere dana pišeš jednom (meni Ishrana), a klijent ih vidi u svom delu Ishrana.</p>
  <button class="btn primary block" name="akcija" value="okvir">Sačuvaj</button>
</form>
<section class="card"><h2>Primeri ishrane samo za klijenta</h2>
<?php $licni = primeri($id, true); if (!$licni): ?><p class="muted" style="margin:0;font-size:14px">Klijent vidi zajedničke primere dana. Ovde možeš da mu napraviš lične — iz početka ili kao kopiju zajedničkog primera.</p><?php endif; ?>
<?php foreach ($licni as $pr): $zz = zbir_obroka($pr['obroci']); ?>
  <a class="guide" href="/trener/ishrana?p=<?= (int)$pr['id'] ?>"><b><?= e($pr['naziv']) ?></b><span class="muted" style="font-size:13px"><?= (int)$zz['kcal'] ?> kcal • P <?= (int)$zz['p'] ?> <span class="link">Uredi</span></span></a>
<?php endforeach; ?>
  <form method="post" action="/trener/ishrana" class="row"><?= csrf_polje() ?><input type="hidden" name="klijent_id" value="<?= $id ?>">
    <input class="tin" name="naziv" placeholder="Naziv, npr. Plan za <?= e(ime_kratko($k['ime'])) ?>" maxlength="120" style="flex:1 1 180px" aria-label="Naziv primera">
    <button class="btn" name="akcija" value="primer_dodaj">+ Novi primer za klijenta</button>
  </form>
  <?php $zajednicki = primeri(null); $zajednicki = array_filter($zajednicki, fn($x) => $x['klijent_id'] === null); if ($zajednicki): ?>
  <form method="post" action="/trener/ishrana" class="row"><?= csrf_polje() ?><input type="hidden" name="kopija_za" value="<?= $id ?>">
    <select class="tin" name="id" style="flex:1 1 180px" aria-label="Zajednički primer"><?php foreach ($zajednicki as $zp): ?><option value="<?= (int)$zp['id'] ?>"><?= e($zp['naziv']) ?></option><?php endforeach; ?></select>
    <button class="btn" name="akcija" value="primer_kopiraj">Kopiraj za klijenta</button>
  </form>
  <?php endif; ?>
</section>
<?php
    // 7 dana koji se završavaju datumom ?do= (podrazumevano danas)
    $do_d = ispravan_datum($_GET['do'] ?? null) ? min(danas(), $_GET['do']) : danas();
    $od_d = dan_plus($do_d, -6);
    $po_danu = dnevnik_po_danu($id, $od_d, $do_d);
    $okv_k = okvir($k);
    $otvoren = null;   // najnoviji dan sa upisom je otvoren
    for ($d = $do_d; $d >= $od_d && $otvoren === null; $d = dan_plus($d, -1)) {
        $otvoren = isset($po_danu[$d]) ? $d : null;
    }
?>
<section class="card"><div class="row between"><h2>Šta je jeo</h2>
  <div class="row" style="gap:4px">
    <a class="btn small" href="<?= $ovde ?>?tab=ishrana&do=<?= e(dan_plus($od_d, -1)) ?>#jelo" aria-label="Prethodnih 7 dana">‹</a>
    <b style="font-size:14px" id="jelo"><?= date('j.n.', strtotime($od_d)) ?>–<?= date('j.n.', strtotime($do_d)) ?></b>
    <?php if ($do_d < danas()): ?><a class="btn small" href="<?= $ovde ?>?tab=ishrana&do=<?= e(min(danas(), dan_plus($do_d, 7))) ?>#jelo" aria-label="Sledećih 7 dana">›</a><?php else: ?><span class="btn small" aria-disabled="true">›</span><?php endif; ?>
  </div></div>
  <p class="muted" style="margin:0;font-size:13px">Ono što klijent upiše u „Sastavi svoj dan“. Dodirni dan za spisak namirnica.</p>
<?php for ($d = $do_d; $d >= $od_d; $d = dan_plus($d, -1)):
    $st = $po_danu[$d] ?? [];
    $zb = zbir_obroka($st);
?>
  <details class="dan-hrana"<?= $d === $otvoren ? ' open' : '' ?>>
    <summary><span><b><?= DANI_KRATKO[dan_u_nedelji($d)] ?></b> <span class="muted"><?= date('j.n.', strtotime($d)) ?></span></span>
      <span class="k"><?php if ($st): ?><span class="chip <?= kcal_u_okviru($zb['kcal'], $okv_k) ? 'ok' : ($okv_k ? 'warn' : '') ?>"><?= (int)round($zb['kcal']) ?><?= $okv_k ? ' / ' . $okv_k['kcal'] : '' ?> kcal</span><br>P <?= (int)round($zb['p']) ?> • UH <?= (int)round($zb['uh']) ?> • M <?= (int)round($zb['m']) ?> • <?= count($st) ?> nam.<?php else: ?>nije upisano<?php endif; ?></span></summary>
    <?php if ($st): ?>
    <div class="stack" style="gap:10px;padding-top:10px">
      <?= spisak_hrane($st) ?>
      <?= $okv_k ? trake_ostalo($zb, $okv_k, false) : '' ?>
      <a class="link" href="<?= $ovde ?>?tab=istorija&v=dan&d=<?= e($d) ?>" style="font-size:14px">Ceo dan u istoriji (trening, težina) →</a>
    </div>
    <?php endif; ?>
  </details>
<?php endfor; ?>
</section>
<?php
} elseif ($tab === 'clanarina') {
    $istorija = redovi('SELECT * FROM produzenja WHERE klijent_id = ? ORDER BY kreirano DESC, id DESC', [$id]);
?>
<section class="card"><div class="row between"><div><span class="eyebrow">Članarina</span><div style="font-weight:700;font-size:17px"><?= $n === null ? 'Nije postavljena' : ($n < 0 ? 'Istekla ' : 'Važi do ') . e(datum_lep((string)$k['clanarina_do'], true)) ?></div></div><?= clanarina_chip($k) ?></div>
  <form method="post" class="stack"><?= csrf_polje() ?>
    <div class="form">
      <label>Iznos (opciono)<input class="tin" name="iznos" inputmode="decimal" placeholder="npr. 5000"></label>
      <label>Napomena<input class="tin" name="napomena" maxlength="255" placeholder="npr. gotovina"></label>
    </div>
    <button class="btn primary block" name="akcija" value="produzi">Produži <?= (int)pod('clanarina.produzenje_dana', 30) ?> dana (do <?= e(datum_lep(dan_plus(max((string)$k['clanarina_do'], danas()), (int)pod('clanarina.produzenje_dana', 30)), true)) ?>)</button>
  </form>
</section>
<form class="card" method="post"><?= csrf_polje() ?><h3>Ručno izaberi datum</h3>
  <div class="form">
    <label class="full">Važi zaključno sa<input class="tin" type="date" name="datum" value="<?= e($k['clanarina_do'] ?: dan_plus(danas(), 30)) ?>" required></label>
    <label>Iznos (opciono)<input class="tin" name="iznos" inputmode="decimal"></label>
    <label>Napomena<input class="tin" name="napomena" maxlength="255"></label>
  </div>
  <button class="btn block" name="akcija" value="produzi_datum">Postavi datum</button>
  <p class="muted" style="margin:0;font-size:13px">Samo evidencija — online plaćanje nije uključeno. Klijent dobija mejl <?= (int)pod('clanarina.upozorenje_dana', 3) ?> dana pre isteka i na dan posle isteka.</p>
</form>
<section class="card"><h3>Istorija produženja</h3>
<?php if (!$istorija): ?><p class="muted" style="margin:0">Još nema produženja.</p><?php else: ?>
  <div style="overflow-x:auto"><table><thead><tr><th>Datum</th><th>Važi do</th><th class="num">Iznos</th></tr></thead><tbody>
  <?php foreach ($istorija as $p): ?><tr><td><?= e(datum_kratko($p['kreirano'])) ?><?= $p['napomena'] !== '' ? '<br><span class="muted" style="font-size:13px">' . e($p['napomena']) . '</span>' : '' ?></td><td><?= e(datum_kratko($p['novo_do'])) ?></td><td class="num"><?= $p['iznos'] !== null ? e(broj($p['iznos'], 0)) : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</section>
<?php
} else {
    $dani = dani_treninga($k);
?>
<form class="card" method="post"><?= csrf_polje() ?><h2>Podaci klijenta</h2>
  <div class="form">
    <label class="full">Ime i prezime<input class="tin" name="ime" value="<?= e($k['ime']) ?>" required maxlength="120"></label>
    <label class="full">Email (prijava)<input class="tin" type="email" name="email" value="<?= e($k['email']) ?>" required></label>
    <label>Cilj<input class="tin" name="cilj" value="<?= e($k['cilj']) ?>" list="ciljevi" maxlength="80"></label>
    <label>Datum početka<input class="tin" type="date" name="datum_pocetka" value="<?= e($k['datum_pocetka']) ?>"></label>
    <label>Treninga nedeljno<input class="tin" type="number" min="1" max="7" name="treninga_nedeljno" value="<?= (int)$k['treninga_nedeljno'] ?>"></label>
    <label>Oznake (zarez)<input class="tin" name="oznake" value="<?= e($k['oznake']) ?>" placeholder="npr. online, početnik"></label>
    <fieldset class="full dani"><legend>Dani treninga (za kalendar)</legend>
      <?php foreach (DANI_KRATKO as $b => $dn): ?><label class="potvrda"><input type="checkbox" name="dani[]" value="<?= $b ?>"<?= in_array($b, $dani, true) ? ' checked' : '' ?>> <?= $dn ?></label><?php endforeach; ?>
    </fieldset>
    <label class="full">Interna beleška (klijent ne vidi)<textarea class="tin" name="napomena" rows="3"><?= e($k['napomena']) ?></textarea></label>
  </div>
  <datalist id="ciljevi"><?php foreach (CILJEVI as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
  <button class="btn primary block" name="akcija" value="nalog">Sačuvaj podatke</button>
</form>
<form class="card" method="post" enctype="multipart/form-data"><?= csrf_polje() ?><h3>Profilna slika</h3>
  <div class="row" style="gap:16px;flex-wrap:nowrap">
    <?= avatar($k['korisnik_id'], $k['ime'], $k['slika'], 'lg') ?>
    <div class="stack" style="gap:8px;min-width:0;flex:1">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= SLIKA_MAX_MB * 1048576 ?>">
      <input class="tin" type="file" name="slika" accept="image/jpeg,image/png,image/webp" required aria-label="Izaberi sliku">
      <div class="row"><button class="btn small" name="akcija" value="slika">Sačuvaj sliku</button>
      <?php if ($k['slika']): ?><button class="link" name="akcija" value="slika_ukloni" formnovalidate data-potvrdi="Ukloniti sliku klijenta?" style="color:var(--bad)">Ukloni</button><?php endif; ?></div>
    </div>
  </div>
  <span class="muted" style="font-size:13px">Klijent može i sam da postavi sliku u meniju „Moj nalog“.</span>
</form>
<section class="card"><h3>Prijava</h3>
  <span class="muted"><?= (int)$k['ima_lozinku'] ? 'Lozinka je postavljena' . ($k['poslednja_prijava'] ? ' • poslednja prijava ' . e(datum_kratko($k['poslednja_prijava'])) : '') . '.' : 'Klijent još nije postavio lozinku.' ?></span>
  <form method="post"><?= csrf_polje() ?><button class="btn block" name="akcija" value="pozivnica"><?= (int)$k['ima_lozinku'] ? 'Pošalji link za novu lozinku' : 'Pošalji pozivnicu ponovo' ?></button></form>
</section>
<form class="card" method="post" autocomplete="off"><?= csrf_polje() ?><h3>Postavi novu lozinku</h3>
  <span class="muted" style="font-size:14px">Kad klijent zaboravi lozinku, a mejl mu ne stiže. Upiši novu lozinku i javi mu je — posle prijave može sam da je promeni u meniju „Promeni lozinku“. Klijent se odjavljuje sa svih uređaja.</span>
  <div class="form">
    <label>Nova lozinka<input class="tin" type="text" name="lozinka" minlength="8" autocomplete="off" required></label>
    <label>Ponovi lozinku<input class="tin" type="text" name="lozinka2" minlength="8" autocomplete="off" required></label>
  </div>
  <button class="btn block" name="akcija" value="lozinka" data-potvrdi="Postaviti novu lozinku za <?= e($k['ime']) ?>?">Postavi lozinku</button>
</form>
<section class="card"><h3>Arhiva</h3>
  <span class="muted">Arhiviran klijent ne može da se prijavi i ne prikazuje se u listi. Ništa se ne briše — istorija ostaje.</span>
  <form method="post"><?= csrf_polje() ?>
  <?php if ((int)$k['arhiviran']): ?><button class="btn block" name="akcija" value="vrati">Vrati iz arhive</button>
  <?php else: ?><button class="btn block" name="akcija" value="arhiviraj" data-potvrdi="Arhivirati <?= e($k['ime']) ?>? Klijent više neće moći da se prijavi.">Arhiviraj klijenta</button><?php endif; ?>
  </form>
</section>
<?php
}
require KOREN . '/delovi/podnozje.php';
