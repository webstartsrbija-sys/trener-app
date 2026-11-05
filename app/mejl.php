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

/** Mejlovi klijentu idu na njegovom jeziku (korisnici.jezik). */
function je_engleski(array $k): bool
{
    return ($k['jezik'] ?? 'sr') === 'en';
}

function mejl_pozivnica(array $k, string $token): bool
{
    $sati = (int)pod('bezbednost.token_sati', 48);
    $tr = pod('sajt.trener_ime');
    $app = pod('sajt.naziv');
    $link = adresa('/lozinka?t=' . $token);
    $ime = explode(' ', $k['ime'])[0];
    if (je_engleski($k)) {
        return posalji_mejl($k['email'], "$tr has created your account — $app",
            "Hi $ime,\n\n$tr has created your account in the $app app.\n"
            . "Your training program, nutrition and progress are all there.\n\n"
            . "Set your password using this link (valid for $sati hours, can be used once):\n$link\n\n"
            . "After that, sign in with your email $k[email] and the password you choose.\n"
            . "On your phone, open the app in the browser and choose \"Add to Home Screen\".");
    }
    return posalji_mejl($k['email'], "$tr ti je napravio nalog — $app",
        "Zdravo $ime,\n\n$tr ti je napravio nalog u aplikaciji $app.\n"
        . "Tu su tvoj program treninga, ishrana i napredak.\n\n"
        . "Postavi lozinku na ovom linku (važi $sati sata i može da se iskoristi jednom):\n$link\n\n"
        . "Posle toga se prijavljuješ emailom $k[email] i lozinkom koju izabereš.\n"
        . "Na telefonu otvori aplikaciju u pregledaču i izaberi \"Dodaj na početni ekran\".");
}

function mejl_reset(array $k, string $token): bool
{
    $sati = (int)pod('bezbednost.token_sati', 48);
    $app = pod('sajt.naziv');
    $link = adresa('/lozinka?t=' . $token);
    if (je_engleski($k)) {
        return posalji_mejl($k['email'], "New password — $app",
            "Hi,\n\nSomeone (probably you) asked for a new password for $app.\n\n"
            . "Set a new password here (valid for $sati hours):\n$link\n\n"
            . "If you didn't ask for this, just ignore this email — your old password still works.");
    }
    return posalji_mejl($k['email'], "Nova lozinka — $app",
        "Zdravo,\n\nNeko (verovatno ti) je zatražio novu lozinku za $app.\n\n"
        . "Postavi novu lozinku ovde (važi $sati sata):\n$link\n\n"
        . "Ako nisi ti tražio, samo zanemari ovaj mejl — stara lozinka i dalje važi.");
}

function mejl_clanarina_istice(array $k, string $do): bool
{
    $n = razlika_dana(danas(), $do);
    $tr = pod('sajt.trener_ime');
    $ime = explode(' ', $k['ime'])[0];
    if (je_engleski($k)) {
        $kad = $n <= 0 ? 'today' : ($n === 1 ? 'tomorrow' : "in $n days");
        return posalji_mejl($k['email'], 'Your membership expires ' . $kad,
            "Hi $ime,\n\nYour membership expires $kad (" . na_jeziku('en', fn() => datum_lep($do, true)) . ").\n"
            . "Contact your coach ($tr) to renew it, so you don't lose access to your program.");
    }
    $kad = $n <= 0 ? 'danas' : ($n === 1 ? 'sutra' : "za $n dana");
    return posalji_mejl($k['email'], 'Članarina ističe ' . $kad,
        "Zdravo $ime,\n\nTvoja članarina ističe $kad (" . na_jeziku('sr', fn() => datum_lep($do, true)) . ").\n"
        . "Javi se treneru ($tr) i produži je, da ne izgubiš pristup programu.");
}

function mejl_clanarina_istekla(array $k, string $do): bool
{
    $tr = pod('sajt.trener_ime');
    $ime = explode(' ', $k['ime'])[0];
    $blok = pod('clanarina.blokira_program', true);
    if (je_engleski($k)) {
        return posalji_mejl($k['email'], 'Your membership has expired',
            "Hi $ime,\n\nYour membership expired on " . na_jeziku('en', fn() => datum_lep($do, true)) . ".\n"
            . ($blok ? "You can still see your history and progress, and your program unlocks as soon as $tr renews your membership." : "Get in touch to renew it."));
    }
    return posalji_mejl($k['email'], 'Članarina je istekla',
        "Zdravo $ime,\n\nTvoja članarina je istekla " . na_jeziku('sr', fn() => datum_lep($do, true)) . ".\n"
        . ($blok ? "Istoriju i napredak i dalje vidiš, a novi program se otvara čim $tr produži članarinu." : "Javi se da produžiš."));
}
