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

/** Primeri dana sa obrocima. */
function primeri(): array
{
    $p = [];
    foreach (redovi('SELECT * FROM primeri_ishrane ORDER BY redosled, id') as $r) {
        $r['obroci'] = [];
        $p[(int)$r['id']] = $r;
    }
    if ($p) {
        foreach (redovi('SELECT * FROM primeri_obroci WHERE primer_id IN (' . implode(',', array_keys($p)) . ') ORDER BY redosled, id') as $o) {
            $p[(int)$o['primer_id']]['obroci'][] = $o;
        }
    }
    return array_values($p);
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
