<?php
/**
 * Primer podešavanja. KOPIRATI kao `app/config.php` i popuniti pravim podacima.
 *
 * `config.php` NE ide u git ni u ZIP (sadrži lozinke). Na hostingu se
 * popunjava jednom, ručno, kroz cPanel File Manager.
 */

declare(strict_types=1);

return [
    // --- Baza ---------------------------------------------------------------
    // cPanel dodaje prefiks naloga: baza "trener" postaje npr. "webstart_trener".
    'baza' => [
        'host'     => 'localhost',
        'ime'      => 'webstart_trener',
        'korisnik' => 'webstart_trener',
        'lozinka'  => 'OVDE_UPISATI_LOZINKU',
        'port'     => 3306,
    ],

    // --- Aplikacija ---------------------------------------------------------
    // Adresa se uzima SAMO odavde (linkovi u mejlovima, manifest). Kad aplikacija
    // pređe na trenerov domen, menja se samo ovaj red.
    'sajt' => [
        'adresa'         => 'https://trener.webstartsrbija.rs',   // bez kose crte na kraju
        'naziv'          => 'Tomislav Coaching',
        'podnaslov'      => 'TRENING • ISHRANA • NAPREDAK',
        'trener_ime'     => 'Tomislav',          // kako ga klijent vidi ("Javi se Tomislavu")
        'vremenska_zona' => 'Europe/Belgrade',
    ],

    // --- Mejl ---------------------------------------------------------------
    // `posiljalac` mora biti adresa na domenu sa kog se šalje (ista kao smtp.korisnik).
    'mejl' => [
        'posiljalac'     => 'trener@webstartsrbija.rs',
        'ime_posiljaoca' => 'Tomislav Coaching',
        // Kome ide dnevni rezime. Prazno = na email trenerovog naloga.
        'rezime_prima'   => '',
        // Samo za lokalni razvoj: true = mejl se ne šalje nego upisuje u kes/mejlovi/
        'u_fajl'         => false,
    ],

    // --- SMTP (isti klijent kao na sajtu, app/smtp.php) -----------------------
    'smtp' => [
        'host'       => 'mail.webstartsrbija.rs',
        'port'       => 465,              // 465 uz 'ssl', 587 uz 'tls'
        'sifrovanje' => 'ssl',            // ssl | tls | nista
        'korisnik'   => 'trener@webstartsrbija.rs',
        'lozinka'    => 'OVDE_UPISATI_LOZINKU_MEJL_NALOGA',
        'timeout'    => 15,
        'proveri_sertifikat' => true,
    ],

    // --- Bezbednost ---------------------------------------------------------
    'bezbednost' => [
        'max_pokusaja'  => 5,     // neuspelih prijava sa jedne IP adrese
        'period_minuta' => 15,    // u kom periodu
        'sesija_minuta' => 120,   // prijava BEZ "zapamti me" ističe posle ovoliko neaktivnosti
        'zapamti_dana'  => 30,    // "zapamti me"
        'token_sati'    => 48,    // koliko važi link za postavljanje lozinke
    ],

    // --- Članarina ----------------------------------------------------------
    'clanarina' => [
        // true: posle isteka klijent vidi istoriju, napredak i vodiče, ali NE
        // vidi program (Trening) dok trener ne produži. false: vidi sve.
        'blokira_program' => true,
        'upozorenje_dana' => 3,    // upozorenje (i mejl) ovoliko dana pre isteka
        'produzenje_dana' => 30,   // dugme "Produži 30 dana"
    ],

    // --- Lista klijenata ----------------------------------------------------
    'paznja' => [
        'dana_bez_treninga' => 4,  // narandžasto: nije trenirao ovoliko dana
        'dana_bol'          => 7,  // crveno: bol u check-inu u poslednjih N dana
    ],

    'vodici' => [
        'pdf_max_mb' => 10,
    ],
];
