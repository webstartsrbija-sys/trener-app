<?php
/**
 * Baza vežbi: težina izvođenja, oprema i mišići.
 * U bazi su ključevi (npr. 'sipka,klupa'), a ovde su nazivi na srpskom i engleskom.
 */

declare(strict_types=1);

const TEZINE = [
    'pocetna'  => ['Početni', 'Beginner', 'ok'],
    'srednja'  => ['Srednji', 'Intermediate', 'warn'],
    'napredna' => ['Napredni', 'Advanced', 'bad'],
];

const OPREMA = [
    'bez'           => ['Bez opreme', 'Bodyweight'],
    'sipka'         => ['Šipka', 'Barbell'],
    'bucice'        => ['Bučice', 'Dumbbells'],
    'kettlebell'    => ['Kettlebell', 'Kettlebell'],
    'masina'        => ['Mašina', 'Machine'],
    'sajla'         => ['Sajla', 'Cable'],
    'smit'          => ['Smit mašina', 'Smith machine'],
    'klupa'         => ['Klupa', 'Bench'],
    'vratilo'       => ['Vratilo', 'Pull-up bar'],
    'razboj'        => ['Razboj', 'Dip bars'],
    'elastic'       => ['Elastična traka', 'Resistance band'],
    'trx'           => ['TRX trake', 'Suspension trainer'],
    'kutija'        => ['Kutija', 'Plyo box'],
    'vijaca'        => ['Vijača', 'Jump rope'],
    'tocak'         => ['Točak za trbušnjake', 'Ab wheel'],
    'kardio_sprava' => ['Kardio sprava', 'Cardio machine'],
];

/** Mišić => [srpski, engleski, grupa] */
const MISICI = [
    'grudi'         => ['Grudi', 'Chest', 'grudi'],
    'siroki_ledjni' => ['Široki leđni', 'Lats', 'ledja'],
    'gornja_ledja'  => ['Gornja leđa', 'Upper back', 'ledja'],
    'donja_ledja'   => ['Donja leđa', 'Lower back', 'ledja'],
    'ramena'        => ['Ramena', 'Shoulders', 'ramena'],
    'zadnje_rame'   => ['Zadnje rame', 'Rear delts', 'ramena'],
    'biceps'        => ['Biceps', 'Biceps', 'ruke'],
    'triceps'       => ['Triceps', 'Triceps', 'ruke'],
    'podlaktice'    => ['Podlaktice', 'Forearms', 'ruke'],
    'trbuh'         => ['Trbušnjaci', 'Abs', 'trbuh'],
    'kosi_trbusni'  => ['Kosi trbušni', 'Obliques', 'trbuh'],
    'gluteus'       => ['Gluteus', 'Glutes', 'noge'],
    'kvadriceps'    => ['Prednja loža (kvadriceps)', 'Quads', 'noge'],
    'zadnja_loza'   => ['Zadnja loža', 'Hamstrings', 'noge'],
    'aduktori'      => ['Unutrašnja strana butine', 'Adductors', 'noge'],
    'listovi'       => ['Listovi', 'Calves', 'noge'],
    'kardio'        => ['Kardio', 'Cardio', 'kardio'],
];

const GRUPE_MISICA = [
    'noge'   => ['Noge i gluteus', 'Legs & glutes'],
    'grudi'  => ['Grudi', 'Chest'],
    'ledja'  => ['Leđa', 'Back'],
    'ramena' => ['Ramena', 'Shoulders'],
    'ruke'   => ['Ruke', 'Arms'],
    'trbuh'  => ['Trbuh', 'Core'],
    'kardio' => ['Kardio', 'Cardio'],
];

/** Ključevi iz kolone ('sipka,klupa') -> ['sipka', 'klupa'], samo poznati. */
function kljucevi(?string $polje, array $mapa): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)$polje)), fn($k) => isset($mapa[$k])));
}

/** Naziv iz mape na jeziku korisnika. */
function oznaka(array $mapa, string $k): string
{
    return $mapa[$k][jezik() === 'en' ? 1 : 0] ?? $k;
}

function oznake_liste(?string $polje, array $mapa): string
{
    return implode(', ', array_map(fn($k) => oznaka($mapa, $k), kljucevi($polje, $mapa)));
}

/** Grupe mišića vežbe (za filter): ['noge', 'trbuh'] */
function grupe_vezbe(array $v): array
{
    return array_values(array_unique(array_map(fn($k) => MISICI[$k][2], kljucevi($v['misici'] ?? '', MISICI))));
}

/**
 * Kratak opis vežbe u jednom redu: "Grudi, triceps • Šipka, klupa • Srednji".
 * Za klijenta (ispod naziva) i u pretrazi vežbi.
 */
function vezba_opis(array $v): string
{
    $delovi = array_filter([
        oznake_liste($v['misici'] ?? '', MISICI),
        oznake_liste($v['oprema'] ?? '', OPREMA),
        isset(TEZINE[$v['tezina'] ?? '']) ? oznaka(TEZINE, $v['tezina']) : '',
    ]);
    return implode(' • ', $delovi);
}

/** Čipovi za karticu vežbe (trener): težina u boji, oprema, glavni i pomoćni mišići. */
function vezba_cipovi(array $v): string
{
    $h = '';
    if (isset(TEZINE[$v['tezina'] ?? ''])) {
        $h .= '<span class="chip ' . TEZINE[$v['tezina']][2] . '">' . e(oznaka(TEZINE, $v['tezina'])) . '</span>';
    }
    foreach (kljucevi($v['misici'] ?? '', MISICI) as $k) {
        $h .= '<span class="chip">' . e(oznaka(MISICI, $k)) . '</span>';
    }
    foreach (kljucevi($v['misici_pomocni'] ?? '', MISICI) as $k) {
        $h .= '<span class="chip mute">' . e(oznaka(MISICI, $k)) . '</span>';
    }
    foreach (kljucevi($v['oprema'] ?? '', OPREMA) as $k) {
        $h .= '<span class="chip mute">⚙ ' . e(oznaka(OPREMA, $k)) . '</span>';
    }
    return $h !== '' ? '<span class="row" style="gap:4px">' . $h . '</span>' : '';
}

/** Ključevi iz forme (niz checkbox-ova) -> 'sipka,klupa' */
function kljucevi_iz_forme(mixed $niz, array $mapa): string
{
    return implode(',', array_values(array_filter((array)$niz, fn($k) => is_string($k) && isset($mapa[$k]))));
}

/** <option> za izbor vežbe sa pretragom (data-alt = drugi jezik, data-info = mišići • oprema • težina). */
function opcije_vezbi(array $vezbe, int $izabrana = 0): string
{
    $h = '';
    foreach ($vezbe as $b) {
        if (!(int)$b['aktivna'] && (int)$b['id'] !== $izabrana) {
            continue;
        }
        $ime = naziv_jezik($b);
        $alt = $ime === $b['naziv'] ? (string)($b['naziv_en'] ?? '') : (string)$b['naziv'];
        $trazi = $alt . ' ' . oznake_liste($b['misici'] ?? '', MISICI) . ' ' . oznake_liste($b['misici_pomocni'] ?? '', MISICI) . ' ' . oznake_liste($b['oprema'] ?? '', OPREMA);
        $h .= '<option value="' . (int)$b['id'] . '"' . ((int)$b['id'] === $izabrana ? ' selected' : '')
            . ' data-alt="' . e($trazi) . '" data-info="' . e(vezba_opis($b)) . '">' . e($ime) . '</option>';
    }
    return $h;
}
