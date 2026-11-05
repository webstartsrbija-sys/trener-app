<?php
/** Lokalna podešavanja za razvoj i testove. Kopira se u app/config.php skriptom dev/pokreni.sh. */
$p = require __DIR__ . '/../app/config.primer.php';
$p['baza'] = ['host' => '127.0.0.1', 'ime' => getenv('TC_BAZA') ?: 'trener_test', 'korisnik' => 'trener', 'lozinka' => 'trener', 'port' => 3306];
$p['sajt']['adresa'] = getenv('TC_ADRESA') ?: 'http://127.0.0.1:8080';
$p['mejl']['u_fajl'] = true;           // mejlovi idu u kes/mejlovi/
$p['mejl']['posiljalac'] = 'trener@primer.rs';
$p['smtp']['host'] = '';
$p['bezbednost']['max_pokusaja'] = 50;
// Testovi "pomeraju" današnji dan, da podaci-primer.sql uvek izgledaju isto
$p['razvoj']['danas'] = getenv('TC_DANAS') ?: '';
return $p;
