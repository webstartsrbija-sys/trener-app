<?php
/**
 * Pravi (ili menja) nalog trenera iz komandne linije.
 *
 *   php alati/novi-trener.php email@domen.rs "Tomislav Prezime"
 *
 * Lozinka se kuca u terminalu (ne vidi se i ne ostaje u istoriji komandi).
 * Ako nalog sa tim emailom već postoji, menja mu se lozinka.
 * Na cPanel-u: Terminal (ako je uključen) ili privremeni Cron Job — vidi ISPORUKA.md.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Samo iz komandne linije.\n");
}

require __DIR__ . '/../app/osnova.php';
require __DIR__ . '/../app/auth.php';

$email = mb_strtolower(trim($argv[1] ?? ''));
$ime = trim($argv[2] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Upotreba: php alati/novi-trener.php email@domen.rs \"Ime Prezime\" [lozinka]\n");
    exit(1);
}

function procitaj_lozinku(string $poruka): string
{
    echo $poruka;
    $tty = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($tty) {
        shell_exec('stty -echo');
    }
    $l = rtrim((string)fgets(STDIN), "\r\n");
    if ($tty) {
        shell_exec('stty echo');
        echo "\n";
    }
    return $l;
}

// Treći argument samo za automatizaciju (npr. jednokratni cron); inače se kuca
$lozinka = $argv[3] ?? '';
if ($lozinka === '') {
    $lozinka = procitaj_lozinku('Lozinka (bar 10 znakova): ');
    if ($lozinka !== procitaj_lozinku('Ponovi lozinku: ')) {
        fwrite(STDERR, "Lozinke se ne poklapaju.\n");
        exit(1);
    }
}
if (mb_strlen($lozinka) < 10) {
    fwrite(STDERR, "Lozinka mora imati bar 10 znakova.\n");
    exit(1);
}

$postoji = red('SELECT id, uloga FROM korisnici WHERE email = ?', [$email]);
if ($postoji && $postoji['uloga'] !== 'trener') {
    fwrite(STDERR, "Taj email već koristi klijent. Izaberi drugi email za trenera.\n");
    exit(1);
}
$hash = password_hash($lozinka, PASSWORD_DEFAULT);
if ($postoji) {
    upit('UPDATE korisnici SET lozinka_hash = ?, aktivan = 1' . ($ime !== '' ? ', ime = ?' : '') . ' WHERE id = ?',
        $ime !== '' ? [$hash, $ime, $postoji['id']] : [$hash, $postoji['id']]);
    upit('DELETE FROM zapamcene_prijave WHERE korisnik_id = ?', [$postoji['id']]);
    echo "Lozinka trenera $email je promenjena.\n";
} else {
    upit("INSERT INTO korisnici (uloga, email, ime, lozinka_hash, aktivan, kreirano) VALUES ('trener', ?, ?, ?, 1, ?)",
        [$email, $ime !== '' ? $ime : (string)pod('sajt.trener_ime', 'Trener'), $hash, sada()]);
    echo "Nalog trenera $email je napravljen. Prijava: " . adresa('/prijava') . "\n";
}
