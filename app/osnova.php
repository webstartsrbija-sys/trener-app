<?php
/**
 * Osnova: podešavanja, baza, ispis, datumi, poruke. Učitava se na svakom zahtevu.
 * Isti stil kao webstart-cms-core: PDO, pripremljeni upiti, bez frameworka.
 */

declare(strict_types=1);

const KOREN = __DIR__ . '/..';

function podesavanja(): array
{
    static $p = null;
    if ($p === null) {
        $put = __DIR__ . '/config.php';
        if (!is_file($put)) {
            http_response_code(500);
            exit('Nedostaje app/config.php — kopirati app/config.primer.php i popuniti.');
        }
        $p = require $put;
        date_default_timezone_set($p['sajt']['vremenska_zona'] ?? 'Europe/Belgrade');
    }
    return $p;
}

/** Jedna vrednost iz podešavanja, npr. pod('clanarina.blokira_program', true). */
function pod(string $kljuc, mixed $podrazumevano = null): mixed
{
    $v = podesavanja();
    foreach (explode('.', $kljuc) as $deo) {
        if (!is_array($v) || !array_key_exists($deo, $v)) {
            return $podrazumevano;
        }
        $v = $v[$deo];
    }
    return $v;
}

function baza(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $k = podesavanja()['baza'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $k['host'], $k['port'], $k['ime']);
    try {
        $pdo = new PDO($dsn, $k['korisnik'], $k['lozinka'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $g) {
        error_log('Greška veze sa bazom: ' . $g->getMessage());
        http_response_code(503);
        exit('Aplikacija trenutno nije dostupna. Pokušaj ponovo za koji minut.');
    }
    return $pdo;
}

function upit(string $sql, array $parametri = []): PDOStatement
{
    $s = baza()->prepare($sql);
    $s->execute($parametri);
    return $s;
}

function red(string $sql, array $parametri = []): ?array
{
    $r = upit($sql, $parametri)->fetch();
    return $r === false ? null : $r;
}

function redovi(string $sql, array $parametri = []): array
{
    return upit($sql, $parametri)->fetchAll();
}

function vrednost(string $sql, array $parametri = []): mixed
{
    $v = upit($sql, $parametri)->fetchColumn();
    return $v === false ? null : $v;
}

function poslednji_id(): int
{
    return (int)baza()->lastInsertId();
}

/** Izvršava funkciju u transakciji; greška vraća sve unazad. */
function transakcija(callable $f): mixed
{
    $pdo = baza();
    $pdo->beginTransaction();
    try {
        $r = $f();
        $pdo->commit();
        return $r;
    } catch (Throwable $g) {
        $pdo->rollBack();
        throw $g;
    }
}

/* -------------------------------------------------------------------------
   Ispis
   ------------------------------------------------------------------------- */

function e(mixed $tekst): string
{
    return htmlspecialchars((string)($tekst ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function e_redovi(?string $tekst): string
{
    return nl2br(e($tekst), false);
}

/** Broj na srpski način: 69,8 */
function broj(float|int|string|null $n, int $decimale = 1): string
{
    return number_format((float)$n, $decimale, ',', '.');
}

/** Broj za polje forme: 884 / 2,6 (bez razdvajanja hiljada, bez suvišnih nula) */
function broj_polje(float|int|string|null $n): string
{
    return rtrim(rtrim(number_format((float)$n, 1, ',', ''), '0'), ',');
}

/** Broj sa predznakom: +1,2 / −0,4 */
function broj_znak(float $n, int $decimale = 1): string
{
    $r = round($n, $decimale);
    return ($r > 0 ? '+' : ($r < 0 ? '−' : '')) . broj(abs($r), $decimale);
}

/** Tekst iz forme, normalizovan i skraćen. */
function unos(mixed $v, int $max = 0): string
{
    $t = str_replace(["\r\n", "\r"], "\n", trim(is_scalar($v) ? (string)$v : ''));
    return $max > 0 ? mb_substr($t, 0, $max) : $t;
}

/** Decimalni broj iz forme, prihvata i zarez. */
function unos_broj(mixed $v): ?float
{
    $t = str_replace([',', ' '], ['.', ''], trim(is_scalar($v) ? (string)$v : ''));
    return is_numeric($t) ? (float)$t : null;
}

function unos_int(mixed $v): int
{
    return is_numeric($v) ? (int)$v : 0;
}

function adresa(string $put = '/'): string
{
    return rtrim((string)pod('sajt.adresa', ''), '/') . $put;
}

/** Statička datoteka sa otiskom verzije, da posle izmene ne ostane stari keš. */
function statika(string $put): string
{
    $puna = KOREN . $put;
    return is_file($puna) ? $put . '?v=' . filemtime($puna) : $put;
}

function preusmeri(string $kuda): never
{
    header('Location: ' . $kuda, true, 303);
    exit;
}

function json(array $podaci, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($podaci, JSON_UNESCAPED_UNICODE);
    exit;
}

function greska(int $status, string $poruka = ''): never
{
    http_response_code($status);
    $naslovi = [403 => 'Nemaš pristup', 404 => 'Stranica ne postoji', 405 => 'Nedozvoljen zahtev', 419 => 'Sesija je istekla'];
    $naslov = $naslovi[$status] ?? 'Greška';
    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        json(['ok' => false, 'greska' => $poruka ?: $naslov], $status);
    }
    $poruka = $poruka ?: 'Vrati se na početnu i probaj ponovo.';
    require KOREN . '/delovi/greska.php';
    exit;
}

/* -------------------------------------------------------------------------
   Poruka posle preusmeravanja (toast)
   ------------------------------------------------------------------------- */

function poruka(string $tekst, string $vrsta = 'ok'): void
{
    sesija_start();
    $_SESSION['poruka'] = ['tekst' => $tekst, 'vrsta' => $vrsta];
}

function uzmi_poruku(): ?array
{
    $p = $_SESSION['poruka'] ?? null;
    unset($_SESSION['poruka']);
    return $p;
}

/* -------------------------------------------------------------------------
   Datumi. Svi datumi su 'Y-m-d' stringovi; vreme dolazi iz PHP-a (ne iz
   NOW() baze), da vremenska zona bude jedna i ista.
   ------------------------------------------------------------------------- */

const MESECI = ['januar','februar','mart','april','maj','jun','jul','avgust','septembar','oktobar','novembar','decembar'];
const MESECI_GEN = ['januara','februara','marta','aprila','maja','juna','jula','avgusta','septembra','oktobra','novembra','decembra'];
const DANI_KRATKO = [1 => 'Pon', 'Uto', 'Sre', 'Čet', 'Pet', 'Sub', 'Ned'];
const DANI_DUGO = [1 => 'Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak', 'Subota', 'Nedelja'];

function danas(): string
{
    // Testovi mogu da "pomere" današnji dan (samo kad je razvoj uključen)
    $t = pod('razvoj.danas');
    return is_string($t) && $t !== '' ? $t : date('Y-m-d');
}

function sada(): string
{
    return date('Y-m-d H:i:s');
}

function dan_plus(string $d, int $n): string
{
    return date('Y-m-d', strtotime($d . ' ' . ($n >= 0 ? '+' : '') . $n . ' days'));
}

function razlika_dana(string $od, string $do): int
{
    return (int)round((strtotime($do . ' 12:00') - strtotime($od . ' 12:00')) / 86400);
}

function ponedeljak(string $d): string
{
    return dan_plus($d, -((int)date('N', strtotime($d)) - 1));
}

function dan_u_nedelji(string $d): int
{
    return (int)date('N', strtotime($d));
}

function ispravan_datum(mixed $d): bool
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    [$g, $m, $dd] = array_map('intval', explode('-', $d));
    return checkdate($m, $dd, $g);
}

/** "3. novembra" */
function datum_lep(string $d, bool $godina = false): string
{
    $t = strtotime($d);
    return (int)date('j', $t) . '. ' . MESECI_GEN[(int)date('n', $t) - 1] . ($godina ? ' ' . date('Y', $t) . '.' : '');
}

/** "03.11.2026." */
function datum_kratko(?string $d): string
{
    return $d ? date('d.m.Y.', strtotime($d)) : '—';
}

/** "Utorak, 3. novembar" */
function datum_naslov(string $d): string
{
    $t = strtotime($d);
    return DANI_DUGO[(int)date('N', $t)] . ', ' . (int)date('j', $t) . '. ' . MESECI[(int)date('n', $t) - 1];
}

function vreme_kratko(?string $dt): string
{
    return $dt ? date('H:i', strtotime($dt)) : '';
}
