<?php
/**
 * Sesije = izvršeni (ili započeti) treninzi. Kad klijent prvi put nešto upiše,
 * pravi se sesija sa SNIMKOM tadašnjeg plana. Kasnije izmene programa je ne diraju.
 */

declare(strict_types=1);

const CHECKIN_POLJA = ['spavanje' => 'Spavanje', 'hidratacija' => 'Hidratacija', 'hrana' => 'Hrana', 'stres' => 'Stres', 'motivacija' => 'Motivacija'];
const BOL_OPCIJE = ['Nema', 'Blago', 'Jako'];

/** Sesija za trening u tekućoj nedelji (započeta ili završena), ili null. */
function sesija_ove_nedelje(int $klijent_id, int $trening_id): ?array
{
    return red('SELECT * FROM sesije WHERE klijent_id = ? AND trening_id = ? AND datum >= ?
                ORDER BY id DESC LIMIT 1', [$klijent_id, $trening_id, ponedeljak(danas())]);
}

/** Poslednja sesija za trening (bilo koje nedelje) — trener vidi šta je klijent upisao. */
function poslednja_sesija_treninga(int $klijent_id, int $trening_id): ?array
{
    return red('SELECT * FROM sesije WHERE klijent_id = ? AND trening_id = ? ORDER BY datum DESC, id DESC LIMIT 1',
        [$klijent_id, $trening_id]);
}

/** Snimak plana jednog treninga u novu sesiju. */
function napravi_sesiju(int $klijent_id, array $trening): int
{
    upit('INSERT INTO sesije (klijent_id, trening_id, datum, status, naziv, fokus, trajanje, napomena_trenera, kreirano, izmenjeno)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $klijent_id, $trening['id'], danas(), 'u_toku', $trening['naziv'], $trening['fokus'],
        $trening['trajanje'], $trening['napomena'], sada(), sada(),
    ]);
    $sid = poslednji_id();
    snimi_plan_u_sesiju($sid, $trening, []);
    return $sid;
}

/**
 * Vežbe i serije plana u sesiju. $upisano: [plan_serija_id => ['kg','pon','uradjeno']] —
 * vrednosti koje je klijent već upisao, da se ne izgube pri osvežavanju.
 */
function snimi_plan_u_sesiju(int $sid, array $trening, array $upisano): void
{
    foreach ($trening['vezbe'] as $i => $v) {
        upit('INSERT INTO sesija_vezbe (sesija_id, trening_vezba_id, vezba_id, redosled, naziv, zagrevanje, tempo, pauza)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$sid, $v['id'], $v['vezba_id'], $i + 1, $v['naziv'], $v['zagrevanje'], $v['tempo'], $v['pauza']]);
        $svid = poslednji_id();
        foreach ($v['serije'] as $j => $s) {
            $u = $upisano[(int)$s['id']] ?? null;
            upit('INSERT INTO sesija_serije (sesija_vezba_id, plan_serija_id, redosled, plan_kg, plan_pon, plan_rir, kg, pon, uradjeno)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$svid, $s['id'], $j + 1, $s['kg'], $s['pon'], $s['rir'], $u['kg'] ?? '', $u['pon'] ?? '', (int)($u['uradjeno'] ?? 0)]);
        }
    }
}

/**
 * Trening koji je klijent započeo, a nije završio, prati "živ" program: ako je
 * trener posle početka izmenio program, plan u sesiji se osvežava, a ono što je
 * klijent već upisao ostaje (po seriji plana). Završeni treninzi se ne diraju —
 * istorija čuva ono što je tada bilo planirano.
 *
 * @return bool da li je sesija osvežena
 */
function osvezi_nezavrsenu_sesiju(array $sesija, array $trening): bool
{
    if ($sesija['status'] !== 'u_toku') {
        return false;
    }
    // Poredi se sadržaj plana, ne vreme izmene (dve izmene u istoj sekundi bi promakle)
    $sada = [$trening['naziv'], $trening['fokus'], $trening['trajanje'], (string)$trening['napomena']];
    foreach ($trening['vezbe'] as $v) {
        $sada[] = [(int)$v['id'], (int)$v['vezba_id'], $v['zagrevanje'], $v['tempo'], $v['pauza'],
            array_map(fn($x) => [(int)$x['id'], $x['kg'], $x['pon'], $x['rir']], $v['serije'])];
    }
    $snimak = [$sesija['naziv'], $sesija['fokus'], $sesija['trajanje'], (string)$sesija['napomena_trenera']];
    $sv = redovi('SELECT * FROM sesija_vezbe WHERE sesija_id = ? ORDER BY redosled, id', [$sesija['id']]);
    $ser = [];
    if ($sv) {
        foreach (redovi('SELECT * FROM sesija_serije WHERE sesija_vezba_id IN (' . implode(',', array_map('intval', array_column($sv, 'id'))) . ') ORDER BY redosled, id') as $r) {
            $ser[(int)$r['sesija_vezba_id']][] = [(int)$r['plan_serija_id'], $r['plan_kg'], $r['plan_pon'], $r['plan_rir']];
        }
    }
    foreach ($sv as $v) {
        $snimak[] = [(int)$v['trening_vezba_id'], (int)$v['vezba_id'], $v['zagrevanje'], $v['tempo'], $v['pauza'], $ser[(int)$v['id']] ?? []];
    }
    if ($sada === $snimak) {
        return false;
    }
    transakcija(function () use ($sesija, $trening) {
        $upisano = [];
        foreach (redovi('SELECT ss.plan_serija_id, ss.kg, ss.pon, ss.uradjeno FROM sesija_serije ss
                         JOIN sesija_vezbe sv ON sv.id = ss.sesija_vezba_id WHERE sv.sesija_id = ? AND ss.plan_serija_id IS NOT NULL',
            [$sesija['id']]) as $r) {
            $upisano[(int)$r['plan_serija_id']] = $r;
        }
        upit('DELETE FROM sesija_vezbe WHERE sesija_id = ?', [$sesija['id']]);
        snimi_plan_u_sesiju((int)$sesija['id'], $trening, $upisano);
        // kreirano = vreme snimka plana
        upit('UPDATE sesije SET naziv = ?, fokus = ?, trajanje = ?, napomena_trenera = ?, kreirano = ? WHERE id = ?',
            [$trening['naziv'], $trening['fokus'], $trening['trajanje'], $trening['napomena'], sada(), $sesija['id']]);
    });
    return true;
}

/** Sesija sa vežbama, serijama i check-inom. */
function sesija_puna(int $sesija_id): ?array
{
    $s = red('SELECT * FROM sesije WHERE id = ?', [$sesija_id]);
    if (!$s) {
        return null;
    }
    $s['checkin'] = red('SELECT * FROM checkini WHERE sesija_id = ?', [$sesija_id]);
    $vezbe = [];
    foreach (redovi('SELECT sv.*, v.youtube, v.tehnika, v.tehnika_en, v.naziv_en, v.tezina, v.oprema, v.misici, v.misici_pomocni FROM sesija_vezbe sv LEFT JOIN vezbe v ON v.id = sv.vezba_id
                     WHERE sv.sesija_id = ? ORDER BY sv.redosled, sv.id', [$sesija_id]) as $v) {
        $v['serije'] = [];
        $vezbe[(int)$v['id']] = $v;
    }
    if ($vezbe) {
        foreach (redovi('SELECT * FROM sesija_serije WHERE sesija_vezba_id IN (' . implode(',', array_keys($vezbe)) . ')
                         ORDER BY redosled, id') as $r) {
            $vezbe[(int)$r['sesija_vezba_id']]['serije'][] = $r;
        }
    }
    $s['vezbe'] = array_values($vezbe);
    return $s;
}

/**
 * Upis klijenta (autosave i "Završi trening"). Klijent se uzima iz sesije,
 * trening i serije se proveravaju da pripadaju baš njemu.
 *
 * @return array{sesija_id:int, mapa:array<string,string>, status:string}
 */
function sacuvaj_unos_klijenta(int $klijent_id, array $post, bool $zavrsi): array
{
    return transakcija(function () use ($klijent_id, $post, $zavrsi) {
        // Zaključavanje reda klijenta: dva istovremena autosave-a ne prave dve sesije
        upit('SELECT id FROM klijenti WHERE id = ? FOR UPDATE', [$klijent_id]);

        $trening_id = unos_int($post['trening_id'] ?? 0);
        $pid = program_klijenta($klijent_id);
        if (!trening_u_programu($trening_id, $pid)) {
            greska(404, 'Trening ne postoji.');
        }
        $sesija = sesija_ove_nedelje($klijent_id, $trening_id);
        if (!$sesija) {
            $trening = null;
            foreach (program_stablo($pid) as $t) {
                if ((int)$t['id'] === $trening_id) {
                    $trening = $t;
                }
            }
            $sesija_id = napravi_sesiju($klijent_id, $trening);
            $sesija = red('SELECT * FROM sesije WHERE id = ?', [$sesija_id]);
        }
        $sesija_id = (int)$sesija['id'];

        // Serije: ključ "s123" (već u sesiji) ili "p45" (plan, pre nego što je sesija postojala)
        $mapa = [];
        $plan_u_sesiju = [];
        foreach (redovi('SELECT ss.id, ss.plan_serija_id FROM sesija_serije ss
                         JOIN sesija_vezbe sv ON sv.id = ss.sesija_vezba_id WHERE sv.sesija_id = ?', [$sesija_id]) as $r) {
            $plan_u_sesiju['s' . $r['id']] = (int)$r['id'];
            if ($r['plan_serija_id']) {
                $plan_u_sesiju['p' . $r['plan_serija_id']] = (int)$r['id'];
            }
        }
        foreach ((array)($post['serija'] ?? []) as $kljuc => $v) {
            $kljuc = (string)$kljuc;
            if (!isset($plan_u_sesiju[$kljuc]) || !is_array($v)) {
                continue;   // tuđa ili nepostojeća serija — ne dira se
            }
            $ssid = $plan_u_sesiju[$kljuc];
            if ($kljuc[0] === 'p') {
                $mapa[$kljuc] = 's' . $ssid;
            }
            upit('UPDATE sesija_serije SET kg = ?, pon = ?, uradjeno = ? WHERE id = ?',
                [unos($v['kg'] ?? '', 20), unos($v['pon'] ?? '', 20), !empty($v['ok']) ? 1 : 0, $ssid]);
        }

        $ci = [];
        foreach (array_keys(CHECKIN_POLJA) as $p) {
            $n = unos_int($post['ci'][$p] ?? 0);
            $ci[$p] = $n >= 1 && $n <= 5 ? $n : null;
        }
        $bol = in_array($post['bol'] ?? '', BOL_OPCIJE, true) ? $post['bol'] : null;
        upit('INSERT INTO checkini (sesija_id, spavanje, hidratacija, hrana, stres, motivacija, bol, izmenjeno)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE spavanje = VALUES(spavanje), hidratacija = VALUES(hidratacija), hrana = VALUES(hrana),
                stres = VALUES(stres), motivacija = VALUES(motivacija), bol = VALUES(bol), izmenjeno = VALUES(izmenjeno)',
            [$sesija_id, $ci['spavanje'], $ci['hidratacija'], $ci['hrana'], $ci['stres'], $ci['motivacija'], $bol, sada()]);

        $status = $sesija['status'];
        if ($zavrsi && $status !== 'zavrsena') {
            $status = 'zavrsena';
            upit('UPDATE sesije SET status = ?, datum = ?, zavrseno_u = ? WHERE id = ?', [$status, danas(), sada(), $sesija_id]);
        }
        upit('UPDATE sesije SET napomena_klijenta = ?, izmenjeno = ? WHERE id = ?',
            [unos($post['napomena'] ?? '', 3000), sada(), $sesija_id]);

        return ['sesija_id' => $sesija_id, 'mapa' => $mapa, 'status' => $status];
    });
}

/** Završene sesije u periodu, po danu: ['2026-11-03' => [sesija, ...]] */
function sesije_po_danu(int $klijent_id, string $od, string $do): array
{
    $r = [];
    foreach (redovi("SELECT s.*, c.bol FROM sesije s LEFT JOIN checkini c ON c.sesija_id = s.id
                     WHERE s.klijent_id = ? AND s.status = 'zavrsena' AND s.datum BETWEEN ? AND ?
                     ORDER BY s.zavrseno_u", [$klijent_id, $od, $do]) as $s) {
        $r[$s['datum']][] = $s;
    }
    return $r;
}

/** Kratak opis check-ina kao čipovi. */
function checkin_chipovi(?array $c): string
{
    if (!$c) {
        return '';
    }
    $h = '';
    foreach (CHECKIN_POLJA as $k => $natpis) {
        if ($c[$k] === null) {
            continue;
        }
        $v = (int)$c[$k];
        $klasa = $k === 'stres' ? ($v >= 4 ? 'bad' : 'ok') : ($v <= 2 ? 'warn' : 'ok');
        $h .= '<span class="chip ' . $klasa . '">' . te($natpis) . ' ' . $v . '/5</span>';
    }
    if ($c['bol'] !== null) {
        $h .= '<span class="chip ' . ($c['bol'] === 'Nema' ? 'ok' : 'bad') . '">' . te('Bol: %s', t($c['bol'])) . '</span>';
    }
    return $h !== '' ? '<div class="row">' . $h . '</div>' : '';
}

/** Kratak zapis urađenih serija: "60×10 • 70×8" */
function serije_kratko(array $serije): string
{
    $d = [];
    foreach ($serije as $s) {
        if ($s['kg'] !== '' || $s['pon'] !== '') {
            $d[] = ($s['kg'] !== '' ? $s['kg'] : '?') . '×' . ($s['pon'] !== '' ? $s['pon'] : '?');
        }
    }
    return $d ? implode(' • ', $d) : '—';
}
