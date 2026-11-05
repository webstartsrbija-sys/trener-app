<?php
/**
 * Pravi baza/podaci-primer.sql: 4 klijenta iz prototipa sa ~3 meseca istorije,
 * baza vežbi, namirnice, primeri ishrane i vodiči.
 *
 * Datumi u SQL-u su RELATIVNI (u odnosu na ponedeljak tekuće nedelje u trenutku
 * uvoza), pa primer uvek izgleda "sveže", ma kad da se uveze. Sve što bi palo u
 * budućnost briše se na kraju fajla.
 *
 *   php dev/napravi-primer.php > baza/podaci-primer.sql
 */

declare(strict_types=1);

mt_srand(20261103);
$out = [];
$q = fn($s) => $s === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string)$s) . "'";
$dan = fn(int $n) => $n === 0 ? '@pon' : '@pon + INTERVAL ' . $n . ' DAY';         // n = dana od ponedeljka ove nedelje
$danas = fn(int $n) => $n === 0 ? 'CURDATE()' : 'CURDATE() + INTERVAL ' . $n . ' DAY';
$ins = function (string $tabela, array $kolone, array $redovi) use (&$out) {
    foreach (array_chunk($redovi, 200) as $deo) {
        $out[] = "INSERT INTO $tabela (" . implode(', ', $kolone) . ") VALUES\n  " . implode(",\n  ", array_map(fn($r) => '(' . implode(', ', $r) . ')', $deo)) . ';';
    }
};

/* ---------------- Baza vežbi ---------------- */
$vezbe = ['Čučanj' => 'Grudi gore, kolena prate prste, spust do paralele.', 'Bench press' => 'Lopatice skupljene, stopala čvrsto na podu, šipka do donjeg dela grudi.',
    'Rumunsko mrtvo' => 'Kukovi idu nazad, leđa neutralna, šipka blizu nogu.', 'Lat mašina' => 'Vuci laktovima ka rebrima, bez zabacivanja trupa.',
    'Veslanje sajla' => 'Grudi gore, povuci do stomaka, kratka pauza.', 'Hip thrust' => 'Brada uvučena, pauza 1 s gore, bez uvijanja donjih leđa.',
    'Lateralno letenje' => 'Kontrolisano, bez zamaha, laktovi blago savijeni.', 'Bugarski čučanj' => 'Prednja noga radi, trup blago napred.',
    'Potisak iznad glave' => 'Stomak i gluteus stegnuti, glava prolazi kroz ruke na vrhu.', 'Pregib za biceps' => 'Laktovi uz telo, spuštanje sporo.',
    'Ekstenzija za triceps' => 'Laktovi mirni, puna ekstenzija.', 'Leg curl' => 'Kukovi pritisnuti uz klupu, polako nazad.'];
$vid = [];
$r = [];
$i = 0;
foreach ($vezbe as $n => $t) {
    $vid[$n] = ++$i;
    $r[] = [$i, $q($n), "''", $q($t), 1, 'NOW()'];
}
$out[] = "-- =============================================================================\n-- Tomislav Coaching — PRIMER PODATAKA (samo za probu i prikaz!)\n--\n-- Uvoziti POSLE shema.sql, u praznu bazu. Datumi su relativni: primer uvek\n-- izgleda kao da je trener počeo pre par meseci.\n--\n-- Klijenti iz primera se prijavljuju lozinkom: proba1234\n-- (ana@primer.rs, nikola@primer.rs, milica@primer.rs, stefan@primer.rs)\n-- U PRODUKCIJI: ne uvoziti, ili posle uvoza arhivirati ove klijente.\n-- Generisano skriptom dev/napravi-primer.php.\n-- =============================================================================\n\nSET NAMES utf8mb4;\nSET @pon := CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY;\nSET @lozinka := " . $q(password_hash('proba1234', PASSWORD_DEFAULT)) . ";\n";
$out[] = '-- Baza vežbi (YouTube link trener upisuje u aplikaciji; dok ga nema, dugme Video traži tehniku na YouTube-u)';
$ins('vezbe', ['id', 'naziv', 'youtube', 'tehnika', 'aktivna', 'kreirano'], $r);

/* ---------------- Namirnice (na 100 g) ---------------- */
$hrana = ['Pileća prsa (sirova)' => [120, 23, 0, 2.6], 'Junetina 5% (sirova)' => [137, 21, 0, 5], 'Losos (sirov)' => [208, 20, 0, 13], 'Tunjevina u vodi' => [116, 26, 0, 1],
    'Jaja' => [143, 12.6, 0.7, 9.5], 'Grčki jogurt 2%' => [73, 10, 4, 2], 'Posni sir' => [98, 11, 3.4, 4.3], 'Ovsene pahuljice' => [370, 13, 60, 7],
    'Pirinač (sirov)' => [360, 7, 79, 0.6], 'Krompir (sirov)' => [77, 2, 17, 0.1], 'Integralni hleb' => [250, 10, 43, 3.5], 'Banana' => [89, 1.1, 23, 0.3],
    'Jabuka' => [52, 0.3, 14, 0.2], 'Maslinovo ulje' => [884, 0, 0, 100], 'Kikiriki puter' => [588, 25, 20, 50]];
$r = [];
$i = 0;
foreach ($hrana as $n => $v) {
    $r[] = [++$i, $q($n), ...$v];
}
$out[] = '-- Namirnice';
$ins('namirnice', ['id', 'naziv', 'kcal', 'p', 'uh', 'm'], $r);

/* ---------------- Primeri ishrane ---------------- */
$primeri = [
    'Primer dana 1' => [['Obrok 1', 'Ovsene pahuljice 70 g, grčki jogurt 200 g, borovnice 100 g', 480, 32, 62, 10], ['Obrok 2', 'Pileća prsa 180 g, pirinač 80 g, salata, maslinovo ulje 10 g', 640, 49, 64, 16], ['Obrok 3', 'Integralni hleb 80 g, posni sir 150 g, paradajz', 380, 27, 40, 10], ['Obrok 4', 'Losos 150 g, krompir 250 g, brokoli 200 g', 570, 36, 48, 22]],
    'Primer dana 2' => [['Obrok 1', '3 jaja, integralni hleb 60 g, paprika', 420, 25, 28, 21], ['Obrok 2', 'Junetina 5% 170 g, krompir 300 g, salata', 560, 40, 55, 10], ['Obrok 3', 'Grčki jogurt 250 g, banana, kikiriki puter 15 g', 380, 28, 46, 9], ['Obrok 4', 'Tunjevina 120 g, pirinač 70 g, povrće', 470, 36, 58, 4]],
    'Primer dana 3' => [['Obrok 1', 'Proteinski palačinci: ovsene 60 g, 2 jaja, belanca 100 g', 450, 34, 38, 15], ['Obrok 2', 'Pileća prsa 180 g, testenina 80 g, paradajz sos', 650, 52, 72, 9], ['Obrok 3', 'Jabuka, posni sir 200 g', 300, 23, 30, 9], ['Obrok 4', 'Ćuretina 150 g, krompir u ljusci 250 g, salata, ulje 10 g', 520, 36, 44, 18]],
];
$rp = $ro = [];
$i = $j = 0;
foreach ($primeri as $n => $obroci) {
    $rp[] = [++$i, $q($n), $i];
    foreach ($obroci as $k => $o) {
        $ro[] = [++$j, $i, $k + 1, $q($o[0]), $q($o[1]), $o[2], $o[3], $o[4], $o[5]];
    }
}
$out[] = '-- Primeri ishrane';
$ins('primeri_ishrane', ['id', 'naziv', 'redosled'], $rp);
$ins('primeri_obroci', ['id', 'primer_id', 'redosled', 'naziv', 'opis', 'kcal', 'p', 'uh', 'm'], $ro);

/* ---------------- Vodiči ---------------- */
$vodici = [['Ishrana', 'Kako sastaviti obrok', 'Osnovni princip za samostalno kombinovanje obroka'], ['Ishrana', 'Proteini u praksi', 'Izvori proteina, porcije i dnevni cilj'],
    ['Ishrana', 'Ishrana van kuće', 'Praktičan izbor kada nemaš pripremljen obrok'], ['Navike', 'Hidratacija', 'Dnevni unos, trening i praktično praćenje'],
    ['Navike', 'San i oporavak', 'Osnove sna, umora i spremnosti za trening'], ['Trening', 'Kako čitati program', 'Serije, ponavljanja, RIR, tempo i pauze'],
    ['Vebinar', 'Snimci konsultacija', 'Snimci edukativnih predavanja']];
$r = [];
foreach ($vodici as $i => $v) {
    $r[] = [$i + 1, $q($v[1]), $q($v[0]), $q($v[2]), "'link'", $q('https://www.youtube.com/results?search_query=' . rawurlencode($v[1])), "''", "''", 0, $i + 1, 1, 'NOW()'];
}
$out[] = '-- Vodiči (linkovi su privremeni — trener ih menja u aplikaciji)';
$ins('vodici', ['id', 'naslov', 'kategorija', 'opis', 'tip', 'url', 'fajl', 'fajl_ime', 'velicina', 'redosled', 'objavljen', 'kreirano'], $r);

/* ---------------- Program iz prototipa ---------------- */
$program = [
    ['Trening 1', 'Donji deo tela', '60 min', 'Na čučnju drži grudi gore i spusti se do paralele. Ako sve 3 serije odradiš sa RIR 2, dodaj 2,5 kg.', [
        ['Čučanj', '2 serije', '3-1-1', '2-3 min', [[60, 10, 3], [70, 8, 2], [75, 6, 1]]], ['Rumunsko mrtvo', '1 serija', '3-0-1', '2 min', [[55, 10, 3], [60, 8, 2], [60, 8, 2]]],
        ['Bugarski čučanj', '—', '2-0-1', '90 s', [['2×10', '12 / noga', 3], ['2×12', '10 / noga', 2], ['2×12', '10 / noga', 2]]], ['Leg curl', '—', '2-0-1', '60 s', [[30, 15, 2], [35, 12, 1], [25, 'do otkaza', 0]]]]],
    ['Trening 2', 'Gornji deo tela', '55 min', 'Bench: lopatice skupljene, stopala čvrsto na podu. Lateralno letenje radi kontrolisano, bez zamaha.', [
        ['Bench press', '2 serije', '3-1-1', '2-3 min', [[50, 10, 3], [55, 8, 2], [57.5, 6, 1]]], ['Lat mašina', '1 serija', '2-1-1', '2 min', [[45, 12, 3], [50, 10, 2], [50, 10, 2]]],
        ['Veslanje sajla', '—', '2-1-1', '90 s', [[40, 12, 2], [45, 10, 2], [45, 10, 1]]], ['Lateralno letenje', '—', '2-0-1', '60 s', [[6, 15, 2], [6, 15, 1], [4, '20+', 0]]]]],
    ['Trening 3', 'Celo telo', '60 min', 'Hip thrust: pauza 1 s gore u svakom ponavljanju.', [
        ['Hip thrust', '2 serije', '2-1-1', '2 min', [[70, 10, 3], [80, 8, 2], [90, 6, 1]]], ['Potisak iznad glave', '1 serija', '2-0-1', '2 min', [[25, 10, 3], [30, 8, 2], [30, 8, 1]]],
        ['Pregib za biceps', '—', '2-0-1', '60 s', [[10, 12, 2], [10, 10, 1]]], ['Ekstenzija za triceps', '—', '2-0-1', '60 s', [[20, 15, 2], [20, 12, 1]]]]],
];

/* ---------------- Klijenti ---------------- */
$klijenti = [
    // ime, email, cilj, dana od početka (u odnosu na ponedeljak), treninga, dani, članarina (dana od danas), doslednost, nedeljni proseci, okvir, oznake
    ['Ana Petrović', 'ana@primer.rs', 'Gubitak masti', -62, 3, '1,3,5', 2, 0.92, [72.4, 72.0, 71.8, 71.1, 70.9, 70.6, 70.1, 69.8, 69.6], [1850, 135, 190, 60], 'online'],
    ['Nikola Jovanović', 'nikola@primer.rs', 'Povećanje snage', -111, 4, '1,2,4,5', 27, 0.81, [84.0, 84.3, 84.2, 84.8, 85.1, 85.0, 85.4, 85.6], [2700, 170, 320, 80], 'uživo, rame'],
    ['Milica Stojanović', 'milica@primer.rs', 'Rekompozicija', -28, 3, '1,3,5', -2, 0.58, [64.2, 64.0, 63.9, 63.9, 63.8], [1900, 120, 210, 60], ''],
    ['Stefan Ilić', 'stefan@primer.rs', 'Gubitak masti', -75, 3, '1,3,5', 3, 0.88, [96.5, 95.8, 95.1, 94.7, 94.0, 93.6, 93.1, 92.4, 92.0], [2300, 185, 220, 70], ''],
];
$korisnici = $kl = $okviri = $produzenja = $programi = $treninzi = $tvez = $pser = [];
$sesije = $svez = $sser = $checkini = $tezine = $dnevnik = [];
$tid = $tvid = $psid = $sid = $svid = $ssid = $did = $tzid = 0;
$napomene = ['Sve je išlo lako.', 'Malo umorna posle posla.', 'Poslednja serija je bila teška.', 'Odličan trening!', 'Kasnije sam stigla, skratila pauze.', ''];
$korpa = [['Ovsene pahuljice', 60], ['Grčki jogurt 2%', 200], ['Banana', 120], ['Pileća prsa (sirova)', 180], ['Pirinač (sirov)', 80], ['Maslinovo ulje', 10], ['Losos (sirov)', 150], ['Krompir (sirov)', 250], ['Integralni hleb', 80], ['Posni sir', 150]];

foreach ($klijenti as $ki => [$ime, $email, $cilj, $start, $tn, $dani, $clan, $cons, $w, $okv, $ozn]) {
    $id = $ki + 1;
    $korisnici[] = [$id + 1, "'klijent'", $q($email), $q($ime), '@lozinka', 1, 'NOW() - INTERVAL ' . (2 + $ki) . ' HOUR', $dan($start)];
    $kl[] = [$id, $id + 1, $q($cilj), $dan($start), $tn, $q($dani), $danas($clan), $q($ozn), 'NULL', 0, $dan($start)];
    $okviri[] = [$id, ...$okv, 'NOW()'];
    for ($p = $clan; $p > $start; $p -= 30) {
        $produzenja[] = [$id, $p - 30 > $start ? $danas($p - 30) : 'NULL', $danas($p), $p - 30 > $start ? 5000 : 'NULL', $q($p - 30 > $start ? 'gotovina' : 'Prva članarina'), 'NULL', $danas($p - 30) . ' + INTERVAL 10 HOUR'];
    }
    $programi[] = [$id, $id, "''", $dan($start), 'NOW()'];

    // Program (živ) — isti kao u prototipu
    $plan = [];
    foreach ($program as $ti => [$tnaziv, $fokus, $traj, $nap, $vez]) {
        $treninzi[] = [++$tid, $id, $ti + 1, $q($tnaziv), $q($fokus), $q($traj), $q($nap)];
        $plan[$ti] = ['id' => $tid, 'naziv' => $tnaziv, 'fokus' => $fokus, 'traj' => $traj, 'nap' => $nap, 'vezbe' => []];
        foreach ($vez as $vi => [$vn, $zag, $tempo, $pauza, $ser]) {
            $tvez[] = [++$tvid, $tid, $vid[$vn], $vi + 1, $q($zag), $q($tempo), $q($pauza)];
            $pv = ['id' => $tvid, 'naziv' => $vn, 'zag' => $zag, 'tempo' => $tempo, 'pauza' => $pauza, 'serije' => []];
            foreach ($ser as $si => [$kg, $pon, $rir]) {
                $pser[] = [++$psid, $tvid, $si + 1, $q((string)$kg), $q((string)$pon), $q((string)$rir)];
                $pv['serije'][] = ['id' => $psid, 'kg' => $kg, 'pon' => $pon, 'rir' => $rir];
            }
            $plan[$ti]['vezbe'][] = $pv;
        }
    }

    // Istorija: od početka do nedelje tekuće nedelje (budući dani se brišu na kraju)
    $planirani = array_map('intval', explode(',', $dani));
    $ukupno = -$start;
    $u_nedelji = 0;
    for ($n = $start; $n <= 6; $n++) {
        $dow = (($n % 7) + 7) % 7 + 1;   // @pon je ponedeljak -> n=0 je dan 1
        if ($dow === 1) {
            $u_nedelji = 0;
        }
        $pr = min(1, ($n - $start) / max(1, $ukupno));
        $nedelja = intdiv($n - $start, 7);

        if (in_array($dow, $planirani, true)) {
            $t = $plan[$u_nedelji % count($plan)];
            $u_nedelji++;
            $uradio = mt_rand() / mt_getrandmax() < $cons;
            if ($ki === 2 && $n > -4) { $uradio = false; }                 // Milica: bez treninga poslednjih dana
            if ($ki === 1 && $n === -3) { $uradio = true; }                // Nikola: trening sa bolom u petak
            if ($uradio) {
                $bol = $ki === 1 && $n >= -3 ? 'Blago' : (mt_rand(0, 9) < 7 ? 'Nema' : null);
                $nap_k = $ki === 1 && $n >= -3 ? 'Bolelo me desno rame na benču, spustio sam kilažu.' : $napomene[mt_rand(0, count($napomene) - 1)];
                $sat = 17 + mt_rand(0, 3);
                $sesije[] = [++$sid, $id, $t['id'], $dan($n), "'zavrsena'", $q($t['naziv']), $q($t['fokus']), $q($t['traj']), $q($t['nap']), $q($nap_k),
                    $dan($n) . " + INTERVAL $sat HOUR", $dan($n) . ' + INTERVAL ' . ($sat - 1) . ' HOUR', $dan($n) . " + INTERVAL $sat HOUR"];
                foreach ($t['vezbe'] as $vi => $v) {
                    $svez[] = [++$svid, $sid, $v['id'], $vid[$v['naziv']], $vi + 1, $q($v['naziv']), $q($v['zag']), $q($v['tempo']), $q($v['pauza'])];
                    foreach ($v['serije'] as $si => $s) {
                        $kg = is_numeric($s['kg']) ? (string)(round($s['kg'] * (0.85 + $pr * 0.15) / 2.5) * 2.5) : $s['kg'];
                        $pon = (string)(((int)$s['pon'] ?: 12) - (mt_rand(0, 1)));
                        $sser[] = [++$ssid, $svid, $s['id'], $si + 1, $q((string)$s['kg']), $q((string)$s['pon']), $q((string)$s['rir']), $q($kg), $q($pon), 1];
                    }
                }
                $checkini[] = [$sid, mt_rand(2, 5), mt_rand(2, 5), mt_rand(2, 5), mt_rand(1, 4), mt_rand(3, 5), $q($bol), 'NOW()'];
            }
        }
        // Težina (75% dana) i ishrana (80% dana)
        if (mt_rand(0, 99) < 75 || ($ki === 3 && $n >= -1)) {
            $avg = $w[min($nedelja, count($w) - 1)];
            $tezine[] = [$id, $dan($n), round($avg + (mt_rand(-35, 35) / 100), 1), 'NOW()'];
        }
        if (mt_rand(0, 99) < 80) {
            $f = 0.86 + mt_rand(0, 24) / 100;
            $kcal_korpe = 0;
            foreach ($korpa as [$nn, $g]) { $kcal_korpe += $hrana[$nn][0] * $g / 100; }
            $faktor = $okv[0] * $f / $kcal_korpe;
            foreach ($korpa as [$nn, $g]) {
                $gg = max(5, (int)round($g * $faktor / 5) * 5);
                $x = $gg / 100;
                $v = $hrana[$nn];
                $dnevnik[] = [$id, $dan($n), $q($nn), $gg, round($v[0] * $x, 1), round($v[1] * $x, 1), round($v[2] * $x, 1), round($v[3] * $x, 1), 'NOW()'];
            }
        }
    }
}
$out[] = '-- Klijenti (lozinka svih: proba1234)';
$ins('korisnici', ['id', 'uloga', 'email', 'ime', 'lozinka_hash', 'aktivan', 'poslednja_prijava', 'kreirano'], $korisnici);
$ins('klijenti', ['id', 'korisnik_id', 'cilj', 'datum_pocetka', 'treninga_nedeljno', 'dani_treninga', 'clanarina_do', 'oznake', 'napomena', 'arhiviran', 'kreirano'], $kl);
$ins('okvir_ishrane', ['klijent_id', 'kcal', 'p', 'uh', 'm', 'izmenjeno'], $okviri);
$ins('produzenja', ['klijent_id', 'staro_do', 'novo_do', 'iznos', 'napomena', 'placanje_id', 'kreirano'], $produzenja);
$out[] = '-- Programi';
$ins('programi', ['id', 'klijent_id', 'naziv', 'kreirano', 'izmenjeno'], $programi);
$ins('treninzi', ['id', 'program_id', 'redosled', 'naziv', 'fokus', 'trajanje', 'napomena'], $treninzi);
$ins('trening_vezbe', ['id', 'trening_id', 'vezba_id', 'redosled', 'zagrevanje', 'tempo', 'pauza'], $tvez);
$ins('plan_serije', ['id', 'trening_vezba_id', 'redosled', 'kg', 'pon', 'rir'], $pser);
$out[] = "-- Šablon (program bez klijenta)\nINSERT INTO programi (id, klijent_id, naziv, kreirano, izmenjeno) VALUES (100, NULL, 'Početnik 3× nedeljno', NOW(), NOW());";
$sab = [];
foreach (array_slice($treninzi, 0, 3) as $t) { $sab[] = [1000 + $t[0], 100, $t[2], $t[3], $t[4], $t[5], $t[6]]; }
$ins('treninzi', ['id', 'program_id', 'redosled', 'naziv', 'fokus', 'trajanje', 'napomena'], $sab);
$sabv = $sabs = [];
foreach ($tvez as $v) { if ($v[1] <= 3) { $sabv[] = [1000 + $v[0], 1000 + $v[1], ...array_slice($v, 2)]; } }
foreach ($pser as $s) { if ($s[1] <= 12) { $sabs[] = [1000 + $s[0], 1000 + $s[1], ...array_slice($s, 2)]; } }
$ins('trening_vezbe', ['id', 'trening_id', 'vezba_id', 'redosled', 'zagrevanje', 'tempo', 'pauza'], $sabv);
$ins('plan_serije', ['id', 'trening_vezba_id', 'redosled', 'kg', 'pon', 'rir'], $sabs);
$out[] = '-- Istorija treninga';
$ins('sesije', ['id', 'klijent_id', 'trening_id', 'datum', 'status', 'naziv', 'fokus', 'trajanje', 'napomena_trenera', 'napomena_klijenta', 'zavrseno_u', 'kreirano', 'izmenjeno'], $sesije);
$ins('sesija_vezbe', ['id', 'sesija_id', 'trening_vezba_id', 'vezba_id', 'redosled', 'naziv', 'zagrevanje', 'tempo', 'pauza'], $svez);
$ins('sesija_serije', ['id', 'sesija_vezba_id', 'plan_serija_id', 'redosled', 'plan_kg', 'plan_pon', 'plan_rir', 'kg', 'pon', 'uradjeno'], $sser);
$ins('checkini', ['sesija_id', 'spavanje', 'hidratacija', 'hrana', 'stres', 'motivacija', 'bol', 'izmenjeno'], $checkini);
$out[] = '-- Težina i ishrana';
$ins('tezine', ['klijent_id', 'datum', 'kg', 'izmenjeno'], $tezine);
$ins('ishrana_dnevnik', ['klijent_id', 'datum', 'naziv', 'grami', 'kcal', 'p', 'uh', 'm', 'kreirano'], $dnevnik);
$out[] = "-- Ono što je palo u budućnost (zavisi od dana uvoza) se briše\nDELETE FROM sesije WHERE datum > CURDATE() OR zavrseno_u > NOW();\nDELETE FROM tezine WHERE datum > CURDATE();\nDELETE FROM ishrana_dnevnik WHERE datum > CURDATE();";

echo implode("\n\n", $out), "\n";
