<?php
/**
 * Jedina ulazna tačka. .htaccess šalje ovde sve što nije postojeći fajl.
 * Svaka ruta sama proverava ulogu; klijentove rute ID klijenta uzimaju
 * isključivo iz sesije.
 */

declare(strict_types=1);

require __DIR__ . '/app/osnova.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/klijenti.php';
require __DIR__ . '/app/program.php';
require __DIR__ . '/app/sesije.php';
require __DIR__ . '/app/napredak.php';
require __DIR__ . '/app/ishrana.php';

podesavanja();

// Osnovna zaštitna zaglavlja
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

$put = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$put = '/' . trim($put, '/');

$rute = [
    '#^/$#'                          => 'strane/pocetna.php',
    '#^/prijava$#'                   => 'strane/javno/prijava.php',
    '#^/odjava$#'                    => 'strane/javno/odjava.php',
    '#^/zaboravljena-lozinka$#'      => 'strane/javno/zaboravljena.php',
    '#^/lozinka$#'                   => 'strane/javno/lozinka.php',
    '#^/manifest\.webmanifest$#'     => 'strane/javno/manifest.php',
    '#^/vodic/(\d+)$#'               => 'strane/vodic-fajl.php',

    '#^/trening$#'                   => 'strane/klijent/trening.php',
    '#^/ishrana$#'                   => 'strane/klijent/ishrana.php',
    '#^/istorija$#'                  => 'strane/klijent/istorija.php',
    '#^/napredak$#'                  => 'strane/klijent/napredak.php',
    '#^/vodici$#'                    => 'strane/klijent/vodici.php',
    '#^/api/trening$#'               => 'strane/klijent/api-trening.php',

    '#^/trener$#'                    => 'strane/trener/pocetna.php',
    '#^/trener/klijenti$#'           => 'strane/trener/klijenti.php',
    '#^/trener/klijent/(\d+)$#'      => 'strane/trener/klijent.php',
    '#^/trener/vezbe$#'              => 'strane/trener/vezbe.php',
    '#^/trener/ishrana$#'            => 'strane/trener/ishrana.php',
    '#^/trener/vodici$#'             => 'strane/trener/vodici.php',
];

foreach ($rute as $obrazac => $fajl) {
    if (preg_match($obrazac, $put, $m)) {
        $param = array_slice($m, 1);
        require __DIR__ . '/' . $fajl;
        exit;
    }
}

greska(404);
