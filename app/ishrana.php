<?php
/**
 * Ishrana: okvir po klijentu, primeri dana, baza namirnica, "Sastavi svoj dan".
 */

declare(strict_types=1);

function okvir(array $k): ?array
{
    return $k['kcal'] !== null ? ['kcal' => (int)$k['kcal'], 'p' => (int)$k['p'], 'uh' => (int)$k['uh'], 'm' => (int)$k['m']] : null;
}

function namirnice(): array
{
    return redovi('SELECT * FROM namirnice ORDER BY naziv');
}

/**
 * Primeri dana sa obrocima i stavkama (namirnicama).
 * $za_klijenta: null = svi primeri (trener), broj = zajednički + primeri tog klijenta.
 */
function primeri(?int $za_klijenta = null, bool $samo_licni = false): array
{
    if ($samo_licni) {
        $sql = 'SELECT * FROM primeri_ishrane WHERE klijent_id = ? ORDER BY redosled, id';
        $par = [$za_klijenta];
    } elseif ($za_klijenta !== null) {
        // Lični primeri klijenta idu prvi
        $sql = 'SELECT * FROM primeri_ishrane WHERE klijent_id IS NULL OR klijent_id = ? ORDER BY klijent_id IS NULL, redosled, id';
        $par = [$za_klijenta];
    } else {
        $sql = 'SELECT * FROM primeri_ishrane ORDER BY klijent_id IS NOT NULL, redosled, id';
        $par = [];
    }
    $p = [];
    foreach (redovi($sql, $par) as $r) {
        $r['obroci'] = [];
        $p[(int)$r['id']] = $r;
    }
    if (!$p) {
        return [];
    }
    $obroci = [];
    foreach (redovi('SELECT * FROM primeri_obroci WHERE primer_id IN (' . implode(',', array_keys($p)) . ') ORDER BY redosled, id') as $o) {
        $o['stavke'] = [];
        $obroci[(int)$o['id']] = $o;
    }
    if ($obroci) {
        foreach (redovi('SELECT * FROM primeri_stavke WHERE obrok_id IN (' . implode(',', array_keys($obroci)) . ') ORDER BY redosled, id') as $st) {
            $obroci[(int)$st['obrok_id']]['stavke'][] = $st;
        }
        foreach ($obroci as $o) {
            $p[(int)$o['primer_id']]['obroci'][] = $o;
        }
    }
    return array_values($p);
}

/** Obrok sa stavkama dobija kcal/P/UH/M kao zbir stavki. */
function preracunaj_obrok(int $obrok_id): void
{
    $z = red('SELECT COUNT(*) n, SUM(kcal) kcal, SUM(p) p, SUM(uh) uh, SUM(m) m FROM primeri_stavke WHERE obrok_id = ?', [$obrok_id]);
    if ((int)$z['n'] > 0) {
        upit('UPDATE primeri_obroci SET kcal = ?, p = ?, uh = ?, m = ? WHERE id = ?',
            [(int)round((float)$z['kcal']), (int)round((float)$z['p']), (int)round((float)$z['uh']), (int)round((float)$z['m']), $obrok_id]);
    }
}

/** Vrednosti namirnice za zadatu gramažu. */
function vrednosti_namirnice(array $n, int $grami): array
{
    $x = $grami / 100;
    return ['kcal' => round($n['kcal'] * $x, 1), 'p' => round($n['p'] * $x, 1), 'uh' => round($n['uh'] * $x, 1), 'm' => round($n['m'] * $x, 1)];
}

/** "Pileća prsa (sirova) 180 g, Pirinač (sirov) 80 g" */
function stavke_tekst(array $stavke): string
{
    return implode(', ', array_map(fn($s) => $s['naziv'] . ' ' . (int)$s['grami'] . ' g', $stavke));
}

/** Kopija primera (obroci + stavke), npr. zajednički primer -> lični primer klijenta. */
function kopiraj_primer(int $primer_id, ?int $klijent_id, string $naziv): int
{
    $red = (int)vrednost('SELECT COALESCE(MAX(redosled), 0) + 1 FROM primeri_ishrane');
    upit('INSERT INTO primeri_ishrane (klijent_id, naziv, redosled) VALUES (?, ?, ?)', [$klijent_id, $naziv, $red]);
    $novi = poslednji_id();
    foreach (redovi('SELECT * FROM primeri_obroci WHERE primer_id = ? ORDER BY redosled, id', [$primer_id]) as $o) {
        upit('INSERT INTO primeri_obroci (primer_id, redosled, naziv, opis, kcal, p, uh, m) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$novi, $o['redosled'], $o['naziv'], $o['opis'], $o['kcal'], $o['p'], $o['uh'], $o['m']]);
        $oid = poslednji_id();
        upit('INSERT INTO primeri_stavke (obrok_id, namirnica_id, redosled, naziv, grami, kcal, p, uh, m)
              SELECT ?, namirnica_id, redosled, naziv, grami, kcal, p, uh, m FROM primeri_stavke WHERE obrok_id = ?', [$oid, $o['id']]);
    }
    return $novi;
}

function zbir_obroka(array $obroci): array
{
    $z = ['kcal' => 0, 'p' => 0, 'uh' => 0, 'm' => 0];
    foreach ($obroci as $o) {
        foreach ($z as $k => $_) {
            $z[$k] += (float)$o[$k];
        }
    }
    return $z;
}

function dnevnik(int $klijent_id, string $datum): array
{
    return redovi('SELECT * FROM ishrana_dnevnik WHERE klijent_id = ? AND datum = ? ORDER BY id', [$klijent_id, $datum]);
}

/** Zbir kalorija i makroa po danu: ['2026-11-03' => ['kcal'=>..., ...]] */
function ishrana_po_danu(int $klijent_id, string $od, string $do): array
{
    $r = [];
    foreach (redovi('SELECT datum, SUM(kcal) kcal, SUM(p) p, SUM(uh) uh, SUM(m) m FROM ishrana_dnevnik
                     WHERE klijent_id = ? AND datum BETWEEN ? AND ? GROUP BY datum', [$klijent_id, $od, $do]) as $x) {
        $r[$x['datum']] = ['kcal' => (float)$x['kcal'], 'p' => (float)$x['p'], 'uh' => (float)$x['uh'], 'm' => (float)$x['m']];
    }
    return $r;
}

/** Kalorije u okviru: ±10% od cilja. */
function kcal_u_okviru(float $kcal, ?array $okvir): bool
{
    return $okvir && $okvir['kcal'] > 0 && abs($kcal / $okvir['kcal'] - 1) <= 0.1;
}

function makro_kutija(array $t): string
{
    return '<div class="macros"><div class="macro"><b>' . (int)round($t['kcal']) . '</b><span>kcal</span></div>'
        . '<div class="macro"><b>' . (int)round($t['p']) . '</b><span>protein g</span></div>'
        . '<div class="macro"><b>' . (int)round($t['uh']) . '</b><span>UH g</span></div>'
        . '<div class="macro"><b>' . (int)round($t['m']) . '</b><span>masti g</span></div></div>';
}

/** Trake "koliko ti je ostalo" za zadati zbir i okvir. */
function trake_ostalo(array $zbir, array $okvir, bool $ostalo = true): string
{
    $h = '';
    foreach ([['kcal', 'Kalorije', 'kcal'], ['p', 'Protein', 'g'], ['uh', 'Ugljeni hidrati', 'g'], ['m', 'Masti', 'g']] as [$k, $n, $j]) {
        $v = (float)$zbir[$k];
        $g = max(1, (int)$okvir[$k]);
        $pc = $v / $g * 100;
        $h .= '<div class="stack" style="gap:4px"><div class="row between"><span>' . $n . '</span><span><b>' . (int)round($v) . '</b> / ' . $g . ' ' . $j
            . ($ostalo ? ' <span class="muted">• ostalo ' . max(0, (int)round($g - $v)) . '</span>' : '') . '</span></div>'
            . '<div class="bar"><i class="' . ($pc > 105 ? 'over' : '') . '" style="width:' . min(100, round($pc, 1)) . '%"></i></div></div>';
    }
    return $h;
}
