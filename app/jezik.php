<?php
/**
 * Jezik interfejsa: srpski (podrazumevano) ili engleski — za strane klijente.
 *
 * Tekst u kodu je na srpskom i prolazi kroz t('...'); prevod je u
 * app/jezik/en.php (srpski tekst => engleski). Ako prevoda nema, ostaje srpski.
 *
 * Ko vidi engleski:
 *  - klijent kome je jezik 'en' (bira sam dugmetom EN, ili trener u tabu Nalog)
 *  - neprijavljen posetilac koji je izabrao EN (kolačić), ili mu pregledač nije na našem jeziku
 * Trener uvek vidi srpski (i zajedničke delove, npr. istoriju klijenta).
 */

declare(strict_types=1);

const JEZICI = ['sr' => 'Srpski', 'en' => 'English'];
const KOLACIC_JEZIK = 'tc_jezik';

/** Privremeno zadat jezik (npr. mejl klijentu na njegovom jeziku). */
function jezik_stek(?string $j = null, bool $skini = false): ?string
{
    static $stek = [];
    if ($skini) {
        array_pop($stek);
    } elseif ($j !== null) {
        $stek[] = $j;
    }
    return $stek ? end($stek) : null;
}

function jezik(): string
{
    $privremen = jezik_stek();
    if ($privremen !== null) {
        return $privremen;
    }
    static $j = null;
    if ($j !== null) {
        return $j;
    }
    if (PHP_SAPI === 'cli') {
        return 'sr';
    }
    $k = korisnik();
    if ($k) {
        return $j = ($k['uloga'] === 'klijent' && ($k['jezik'] ?? 'sr') === 'en') ? 'en' : 'sr';
    }
    $kol = $_COOKIE[KOLACIC_JEZIK] ?? '';
    if ($kol === 'en' || $kol === 'sr') {
        return $j = $kol;
    }
    // Prvi dolazak: pregledač na jeziku koji nije naš -> engleski
    $al = strtolower(substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2));
    return $j = ($al === '' || in_array($al, ['sr', 'hr', 'bs', 'sh', 'me', 'cn'], true)) ? 'sr' : 'en';
}

/** Izvršava $f na zadatom jeziku (mejlovi klijentu). */
function na_jeziku(string $j, callable $f): mixed
{
    jezik_stek(in_array($j, ['sr', 'en'], true) ? $j : 'sr');
    try {
        return $f();
    } finally {
        jezik_stek(null, true);
    }
}

function prevodi(): array
{
    static $p = null;
    return $p ??= require __DIR__ . '/jezik/en.php';
}

/** Prevod. Dodatni argumenti idu u %s mesta: t('Nedelja %s', 9). */
function t(string $sr, mixed ...$arg): string
{
    $tekst = jezik() === 'en' ? (prevodi()[$sr] ?? $sr) : $sr;
    return $arg ? vsprintf($tekst, $arg) : $tekst;
}

/** Prevod spreman za HTML. */
function te(string $sr, mixed ...$arg): string
{
    return e(t($sr, ...$arg));
}

/** Naziv vežbe ili namirnice na jeziku korisnika (engleski ako ga trener upiše). */
function naziv_jezik(array $r, string $polje = 'naziv'): string
{
    $en = trim((string)($r[$polje . '_en'] ?? ''));
    return jezik() === 'en' && $en !== '' ? $en : (string)$r[$polje];
}

/* ---- Datumi i brojevi na jeziku korisnika ---- */

function dan_kratko(int $iso): string
{
    return jezik() === 'en' ? [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$iso] : DANI_KRATKO[$iso];
}

function dan_dugo(int $iso): string
{
    return jezik() === 'en' ? [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$iso] : DANI_DUGO[$iso];
}

/** Ime meseca (1–12); $padez: srpski genitiv ("novembra"). */
function mesec(int $m, bool $padez = false): string
{
    if (jezik() === 'en') {
        return ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][$m - 1];
    }
    return $padez ? MESECI_GEN[$m - 1] : MESECI[$m - 1];
}

function mesec_kratko(int $m): string
{
    return mb_substr(mesec($m), 0, 3);
}
