<?php
/**
 * Napredak: težina (nedeljni proseci, grafikon) i procenjeni 1RM.
 */

declare(strict_types=1);

/** Nedeljni proseci težine: [['n' => broj nedelje od početka, 'kg' => prosek, 'od' => ponedeljak], ...] */
function nedeljni_proseci(array $k): array
{
    $pocetak = ponedeljak($k['datum_pocetka']);
    $nedelje = [];
    foreach (redovi('SELECT datum, kg FROM tezine WHERE klijent_id = ? ORDER BY datum', [$k['id']]) as $t) {
        $n = intdiv(razlika_dana($pocetak, ponedeljak($t['datum'])), 7) + 1;
        $nedelje[$n][] = (float)$t['kg'];
    }
    $r = [];
    foreach ($nedelje as $n => $v) {
        $r[] = ['n' => $n, 'kg' => array_sum($v) / count($v), 'od' => dan_plus($pocetak, ($n - 1) * 7)];
    }
    return $r;
}

function promena_tezine_ukupno(int $klijent_id): ?float
{
    $k = klijent($klijent_id);
    $p = nedeljni_proseci($k);
    return count($p) >= 2 ? end($p)['kg'] - $p[0]['kg'] : null;
}

function upisi_tezinu(int $klijent_id, string $datum, float $kg): void
{
    upit('INSERT INTO tezine (klijent_id, datum, kg, izmenjeno) VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE kg = VALUES(kg), izmenjeno = VALUES(izmenjeno)',
        [$klijent_id, $datum, round($kg, 1), sada()]);
}

/** SVG grafikon nedeljnih proseka (isti izgled kao u prototipu). */
function grafikon_tezine(array $proseci): string
{
    $proseci = array_slice($proseci, -12);
    if (!$proseci) {
        return '<p class="muted" style="margin:0">Grafikon se pojavljuje posle prvog upisa težine.</p>';
    }
    $w = array_column($proseci, 'kg');
    $W = 320; $H = 150; $l = 34; $r = 10; $t = 12; $b = 26;
    $mn = floor(min($w) - 0.5);
    $mx = ceil(max($w) + 0.5);
    $n = count($w);
    $x = fn(int $i) => $l + ($n === 1 ? ($W - $l - $r) / 2 : $i * ($W - $l - $r) / ($n - 1));
    $y = fn(float $v) => $t + ($mx - $v) * ($H - $t - $b) / ($mx - $mn);
    $tacke = [];
    foreach ($w as $i => $v) {
        $tacke[] = sprintf('%.1f,%.1f', $x($i), $y($v));
    }
    $s = '<div class="chart"><svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Nedeljni prosek težine">';
    foreach ([$mn, ($mn + $mx) / 2, $mx] as $v) {
        $s .= sprintf('<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" stroke="var(--line)" stroke-width="1"/>', $l, $W - $r, $y($v), $y($v));
        $s .= sprintf('<text x="%d" y="%.1f" text-anchor="end" font-size="10" fill="var(--muted)">%s</text>', $l - 6, $y($v) + 4, broj($v));
    }
    if ($n > 1) {
        $s .= sprintf('<path d="M%.1f,%d L%s L%.1f,%d Z" fill="var(--brand)" fill-opacity=".12"/>', $x(0), $H - $b, implode(' L', $tacke), $x($n - 1), $H - $b);
        $s .= '<polyline points="' . implode(' ', $tacke) . '" fill="none" stroke="var(--brand)" stroke-width="2.5" stroke-linejoin="round"/>';
    }
    foreach ($w as $i => $v) {
        $posl = $i === $n - 1;
        $s .= sprintf('<circle cx="%.1f" cy="%.1f" r="%s" fill="%s" stroke="var(--brand)" stroke-width="2"><title>N%d: %s kg</title></circle>',
            $x($i), $y($v), $posl ? '4.5' : '2.5', $posl ? 'var(--brand)' : 'var(--surface)', $proseci[$i]['n'], broj($v));
        $s .= sprintf('<text x="%.1f" y="%d" text-anchor="middle" font-size="10" fill="var(--muted)">N%d</text>', $x($i), $H - 8, $proseci[$i]['n']);
    }
    return $s . '</svg></div>';
}

/** Procenjeni 1RM (Epley): kg × (1 + pon / 30). */
function e1rm(float $kg, int $pon): float
{
    return $kg * (1 + $pon / 30);
}

/**
 * Za svaku vežbu: najbolja serija u PRVOJ i u POSLEDNJOJ sesiji gde se pojavila.
 * Računaju se samo serije gde su i kilaža i ponavljanja brojevi.
 */
function tabela_1rm(int $klijent_id): array
{
    $rez = [];
    $sql = "SELECT s.id AS sid, s.datum, COALESCE(sv.vezba_id, 0) AS vid, sv.naziv, ss.kg, ss.pon
            FROM sesija_serije ss
            JOIN sesija_vezbe sv ON sv.id = ss.sesija_vezba_id
            JOIN sesije s ON s.id = sv.sesija_id
            WHERE s.klijent_id = ? AND s.status = 'zavrsena' AND ss.kg <> '' AND ss.pon <> ''
            ORDER BY s.datum, s.id";
    foreach (redovi($sql, [$klijent_id]) as $r) {
        $kg = unos_broj($r['kg']);
        $pon = ctype_digit(trim($r['pon'])) ? (int)$r['pon'] : 0;
        if ($kg === null || $kg <= 0 || $pon < 1 || $pon > 30) {
            continue;
        }
        $kljuc = $r['vid'] ?: $r['naziv'];
        $e = e1rm($kg, $pon);
        $rez[$kljuc] ??= ['vezba' => $r['naziv'], 'prva' => $r['sid'], 'pocetni' => 0.0, 'poslednja' => $r['sid'], 'trenutni' => 0.0];
        $x = &$rez[$kljuc];
        if ($r['sid'] === $x['prva']) {
            $x['pocetni'] = max($x['pocetni'], $e);
        }
        if ($r['sid'] !== $x['poslednja']) {
            $x['poslednja'] = $r['sid'];
            $x['trenutni'] = 0.0;
        }
        $x['trenutni'] = max($x['trenutni'], $e);
        unset($x);
    }
    return array_values($rez);
}
