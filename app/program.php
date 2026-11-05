<?php
/**
 * Program treninga ("živ" — menja ga trener) i šabloni.
 */

declare(strict_types=1);

/** ID programa klijenta; pravi prazan ako ga nema. */
function program_klijenta(int $klijent_id): int
{
    $id = vrednost('SELECT id FROM programi WHERE klijent_id = ?', [$klijent_id]);
    if ($id) {
        return (int)$id;
    }
    upit('INSERT INTO programi (klijent_id, naziv, kreirano, izmenjeno) VALUES (?, ?, ?, ?)',
        [$klijent_id, '', sada(), sada()]);
    return poslednji_id();
}

/**
 * Ceo program kao stablo: treninzi -> vezbe -> serije.
 * Tri upita, bez obzira na veličinu programa.
 */
function program_stablo(int $program_id): array
{
    $treninzi = [];
    foreach (redovi('SELECT * FROM treninzi WHERE program_id = ? ORDER BY redosled, id', [$program_id]) as $t) {
        $t['vezbe'] = [];
        $treninzi[(int)$t['id']] = $t;
    }
    if (!$treninzi) {
        return [];
    }
    $ids = implode(',', array_keys($treninzi));
    $vezbe = [];
    foreach (redovi("SELECT tv.*, v.naziv, v.youtube, v.tehnika FROM trening_vezbe tv
                     JOIN vezbe v ON v.id = tv.vezba_id
                     WHERE tv.trening_id IN ($ids) ORDER BY tv.redosled, tv.id") as $v) {
        $v['serije'] = [];
        $vezbe[(int)$v['id']] = $v;
    }
    if ($vezbe) {
        $vids = implode(',', array_keys($vezbe));
        foreach (redovi("SELECT * FROM plan_serije WHERE trening_vezba_id IN ($vids) ORDER BY redosled, id") as $s) {
            $vezbe[(int)$s['trening_vezba_id']]['serije'][] = $s;
        }
        foreach ($vezbe as $v) {
            $treninzi[(int)$v['trening_id']]['vezbe'][] = $v;
        }
    }
    return array_values($treninzi);
}

/** Da li trening pripada programu — provera vlasništva pre svake izmene. */
function trening_u_programu(int $trening_id, int $program_id): bool
{
    return (bool)vrednost('SELECT 1 FROM treninzi WHERE id = ? AND program_id = ?', [$trening_id, $program_id]);
}

function dodaj_trening(int $program_id): int
{
    $n = (int)vrednost('SELECT COUNT(*) FROM treninzi WHERE program_id = ?', [$program_id]);
    $red = (int)vrednost('SELECT COALESCE(MAX(redosled), 0) + 1 FROM treninzi WHERE program_id = ?', [$program_id]);
    upit('INSERT INTO treninzi (program_id, redosled, naziv, fokus, trajanje, napomena) VALUES (?, ?, ?, ?, ?, ?)',
        [$program_id, $red, 'Trening ' . ($n + 1), '', '60 min', '']);
    $tid = poslednji_id();
    dodaj_vezbu($tid);
    return $tid;
}

function dodaj_vezbu(int $trening_id, ?int $vezba_id = null): int
{
    $vezba_id ??= (int)vrednost('SELECT id FROM vezbe WHERE aktivna = 1 ORDER BY naziv LIMIT 1');
    if (!$vezba_id) {
        throw new RuntimeException('Baza vežbi je prazna — prvo dodaj vežbu u Bazu vežbi.');
    }
    $red = (int)vrednost('SELECT COALESCE(MAX(redosled), 0) + 1 FROM trening_vezbe WHERE trening_id = ?', [$trening_id]);
    upit('INSERT INTO trening_vezbe (trening_id, vezba_id, redosled, zagrevanje, tempo, pauza) VALUES (?, ?, ?, ?, ?, ?)',
        [$trening_id, $vezba_id, $red, '—', '2-0-1', '90 s']);
    $tvid = poslednji_id();
    for ($i = 1; $i <= 3; $i++) {
        upit('INSERT INTO plan_serije (trening_vezba_id, redosled, kg, pon, rir) VALUES (?, ?, ?, ?, ?)', [$tvid, $i, '', '10', '2']);
    }
    return $tvid;
}

function dodaj_seriju(int $tv_id): void
{
    $posl = red('SELECT kg, pon, rir, redosled FROM plan_serije WHERE trening_vezba_id = ? ORDER BY redosled DESC, id DESC LIMIT 1', [$tv_id]);
    upit('INSERT INTO plan_serije (trening_vezba_id, redosled, kg, pon, rir) VALUES (?, ?, ?, ?, ?)',
        [$tv_id, (int)($posl['redosled'] ?? 0) + 1, $posl['kg'] ?? '', $posl['pon'] ?? '10', $posl['rir'] ?? '2']);
}

/** Briše sve treninge odredišnog programa i kopira treninge izvora. Istorija ostaje (SET NULL). */
function kopiraj_program(int $izvor_id, int $odrediste_id): void
{
    if ($izvor_id === $odrediste_id) {
        return;
    }
    $stablo = program_stablo($izvor_id);
    upit('DELETE FROM treninzi WHERE program_id = ?', [$odrediste_id]);
    foreach ($stablo as $t) {
        upit('INSERT INTO treninzi (program_id, redosled, naziv, fokus, trajanje, napomena) VALUES (?, ?, ?, ?, ?, ?)',
            [$odrediste_id, $t['redosled'], $t['naziv'], $t['fokus'], $t['trajanje'], $t['napomena']]);
        $tid = poslednji_id();
        foreach ($t['vezbe'] as $v) {
            upit('INSERT INTO trening_vezbe (trening_id, vezba_id, redosled, zagrevanje, tempo, pauza) VALUES (?, ?, ?, ?, ?, ?)',
                [$tid, $v['vezba_id'], $v['redosled'], $v['zagrevanje'], $v['tempo'], $v['pauza']]);
            $tvid = poslednji_id();
            foreach ($v['serije'] as $s) {
                upit('INSERT INTO plan_serije (trening_vezba_id, redosled, kg, pon, rir) VALUES (?, ?, ?, ?, ?)',
                    [$tvid, $s['redosled'], $s['kg'], $s['pon'], $s['rir']]);
            }
        }
    }
    upit('UPDATE programi SET izmenjeno = ? WHERE id = ?', [sada(), $odrediste_id]);
}

function sabloni(): array
{
    return redovi('SELECT p.id, p.naziv, (SELECT COUNT(*) FROM treninzi t WHERE t.program_id = p.id) AS treninga
                   FROM programi p WHERE p.klijent_id IS NULL ORDER BY p.naziv');
}

function sacuvaj_kao_sablon(int $program_id, string $naziv): int
{
    upit('INSERT INTO programi (klijent_id, naziv, kreirano, izmenjeno) VALUES (NULL, ?, ?, ?)', [$naziv, sada(), sada()]);
    $sid = poslednji_id();
    kopiraj_program($program_id, $sid);
    return $sid;
}

/**
 * Snima izmene jednog treninga iz trenerove forme. Svaki ID iz forme se
 * proverava da pripada baš ovom treningu — podmetnut ID se tiho preskače.
 */
function sacuvaj_trening_forma(int $trening_id, array $post): void
{
    upit('UPDATE treninzi SET naziv = ?, fokus = ?, trajanje = ?, napomena = ? WHERE id = ?', [
        unos($post['naziv'] ?? '', 120) ?: 'Trening', unos($post['fokus'] ?? '', 120),
        unos($post['trajanje'] ?? '', 40), unos($post['napomena'] ?? '', 5000), $trening_id,
    ]);
    $moje_tv = array_map('intval', array_column(redovi('SELECT id FROM trening_vezbe WHERE trening_id = ?', [$trening_id]), 'id'));
    $aktivne = array_map('intval', array_column(redovi('SELECT id FROM vezbe'), 'id'));
    foreach ((array)($post['vezba'] ?? []) as $tvid => $v) {
        $tvid = (int)$tvid;
        if (!in_array($tvid, $moje_tv, true) || !is_array($v)) {
            continue;
        }
        $vid = (int)($v['vezba_id'] ?? 0);
        upit('UPDATE trening_vezbe SET vezba_id = COALESCE(?, vezba_id), zagrevanje = ?, tempo = ?, pauza = ? WHERE id = ?', [
            in_array($vid, $aktivne, true) ? $vid : null,
            unos($v['zagrevanje'] ?? '', 40), unos($v['tempo'] ?? '', 40), unos($v['pauza'] ?? '', 40), $tvid,
        ]);
    }
    foreach ((array)($post['serija'] ?? []) as $sid => $s) {
        if (!is_array($s)) {
            continue;
        }
        upit('UPDATE plan_serije ps JOIN trening_vezbe tv ON tv.id = ps.trening_vezba_id
              SET ps.kg = ?, ps.pon = ?, ps.rir = ?
              WHERE ps.id = ? AND tv.trening_id = ?', [
            unos($s['kg'] ?? '', 20), unos($s['pon'] ?? '', 20), unos($s['rir'] ?? '', 10), (int)$sid, $trening_id,
        ]);
    }
    upit('UPDATE programi p JOIN treninzi t ON t.program_id = p.id SET p.izmenjeno = ? WHERE t.id = ?', [sada(), $trening_id]);
}

/** Da li je plan kilaže broj — onda se uz njega piše "kg". */
function kg_tekst(string $kg): string
{
    $kg = trim($kg);
    if ($kg === '') {
        return '—';
    }
    return is_numeric(str_replace(',', '.', $kg)) ? str_replace('.', ',', $kg) . ' kg' : $kg;
}

function youtube_link(string $url, string $naziv): string
{
    if ($url !== '' && preg_match('~^https://(www\.|m\.)?(youtube\.com|youtu\.be)/~i', $url)) {
        return $url;
    }
    return 'https://www.youtube.com/results?search_query=' . rawurlencode($naziv . ' tehnika');
}

function serije_rec(int $n): string
{
    $d = $n % 10;
    $s = $n % 100;
    return $n . ' ' . (($d >= 2 && $d <= 4 && ($s < 12 || $s > 14)) ? 'serije' : ($d === 1 && $s !== 11 ? 'serija' : 'serija'));
}
