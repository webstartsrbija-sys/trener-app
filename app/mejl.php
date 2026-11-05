<?php
/**
 * Mejlovi aplikacije. Šalje se preko SMTP klijenta sa sajta (app/smtp.php).
 *
 * Za lokalni razvoj (mejl.u_fajl = true) poruka se ne šalje nego upisuje u
 * kes/mejlovi/, da testovi mogu da pročitaju link iz pozivnice.
 */

declare(strict_types=1);

require_once __DIR__ . '/smtp.php';

/** @return bool da li je poruka otišla */
function posalji_mejl(string $za, string $naslov, string $telo): bool
{
    $od = (string)pod('mejl.posiljalac', '');
    $od_ime = (string)pod('mejl.ime_posiljaoca', pod('sajt.naziv', ''));
    $telo = rtrim($telo) . "\n\n— \n" . pod('sajt.naziv') . "\n" . adresa('/') . "\n";

    if (pod('mejl.u_fajl') === true) {
        $dir = KOREN . '/kes/mejlovi';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ime = sprintf('%s/%s-%s.txt', $dir, date('Ymd-His'), bin2hex(random_bytes(3)));
        return file_put_contents($ime, "Za: $za\nNaslov: $naslov\n\n$telo") !== false;
    }

    $smtp = pod('smtp', []);
    if (trim((string)($smtp['host'] ?? '')) === '') {
        mejl_zabelezi_gresku("Mejl za $za nije poslat: SMTP nije podešen u config.php");
        return false;
    }
    $ishod = smtp_posalji($smtp, [
        'od' => $od ?: (string)($smtp['korisnik'] ?? ''), 'od_ime' => $od_ime,
        'za' => $za, 'naslov' => $naslov, 'telo' => $telo,
    ]);
    if (!$ishod['uspeh']) {
        mejl_zabelezi_gresku("Mejl za $za ($naslov): " . $ishod['greska']);
    }
    return $ishod['uspeh'];
}

function mejl_pozivnica(array $k, string $token): bool
{
    $sati = (int)pod('bezbednost.token_sati', 48);
    $tr = pod('sajt.trener_ime');
    $link = adresa('/lozinka?t=' . $token);
    $ime = explode(' ', $k['ime'])[0];
    return posalji_mejl($k['email'], "$tr ti je napravio nalog — " . pod('sajt.naziv'),
        "Zdravo $ime,\n\n$tr ti je napravio nalog u aplikaciji " . pod('sajt.naziv') . ".\n"
        . "Tu su tvoj program treninga, ishrana i napredak.\n\n"
        . "Postavi lozinku na ovom linku (važi $sati sata i može da se iskoristi jednom):\n$link\n\n"
        . "Posle toga se prijavljuješ emailom $k[email] i lozinkom koju izabereš.\n"
        . "Na telefonu otvori aplikaciju u pregledaču i izaberi \"Dodaj na početni ekran\".");
}

function mejl_reset(array $k, string $token): bool
{
    $sati = (int)pod('bezbednost.token_sati', 48);
    $link = adresa('/lozinka?t=' . $token);
    return posalji_mejl($k['email'], 'Nova lozinka — ' . pod('sajt.naziv'),
        "Zdravo,\n\nNeko (verovatno ti) je zatražio novu lozinku za " . pod('sajt.naziv') . ".\n\n"
        . "Postavi novu lozinku ovde (važi $sati sata):\n$link\n\n"
        . "Ako nisi ti tražio, samo zanemari ovaj mejl — stara lozinka i dalje važi.");
}

function mejl_clanarina_istice(array $k, string $do): bool
{
    $n = razlika_dana(danas(), $do);
    $kad = $n <= 0 ? 'danas' : ($n === 1 ? 'sutra' : "za $n dana");
    $tr = pod('sajt.trener_ime');
    return posalji_mejl($k['email'], 'Članarina ističe ' . $kad,
        'Zdravo ' . explode(' ', $k['ime'])[0] . ",\n\nTvoja članarina ističe $kad (" . datum_lep($do, true) . ").\n"
        . "Javi se treneru ($tr) i produži je, da ne izgubiš pristup programu.");
}

function mejl_clanarina_istekla(array $k, string $do): bool
{
    $tr = pod('sajt.trener_ime');
    $blok = pod('clanarina.blokira_program', true)
        ? "Istoriju i napredak i dalje vidiš, a novi program se otvara čim $tr produži članarinu."
        : "Javi se da produžiš.";
    return posalji_mejl($k['email'], 'Članarina je istekla',
        'Zdravo ' . explode(' ', $k['ime'])[0] . ",\n\nTvoja članarina je istekla " . datum_lep($do, true) . ".\n$blok");
}
