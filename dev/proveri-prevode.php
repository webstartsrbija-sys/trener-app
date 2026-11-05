<?php
/**
 * Proverava da li svaki tekst iz t('…') / te('…') ima engleski prevod u app/jezik/en.php.
 *   php dev/proveri-prevode.php          -> spisak tekstova bez prevoda (izlaz 1 ako ih ima)
 */
$koren = dirname(__DIR__);
$fajlovi = array_merge(glob("$koren/*.php"), glob("$koren/app/*.php"), glob("$koren/delovi/*.php"), glob("$koren/strane/*.php"), glob("$koren/strane/*/*.php"));
$kljucevi = [];
foreach ($fajlovi as $f) {
    $kod = file_get_contents($f);
    preg_match_all("/\\bte?\\('((?:[^'\\\\]|\\\\.)*)'/u", $kod, $m);
    foreach ($m[1] as $k) {
        $kljucevi[stripcslashes(str_replace("\\'", "'", $k))] = basename(dirname($f)) . '/' . basename($f);
    }
}
// Tekstovi koji u t() ulaze kroz promenljive (natpisi menija, check-in, opcije, naslovi strana, greške)
require "$koren/app/osnova.php";
foreach (array_merge(
    ['Trening', 'Ishrana', 'Istorija', 'Napredak', 'Vodiči', 'Prijava', 'Zaboravljena lozinka', 'Lozinka', 'Moj nalog', 'Dan', 'Nedelja', 'Mesec', 'kg prosek', 'kg u mesecu',
     'Spavanje', 'Hidratacija', 'Hrana', 'Stres', 'Motivacija', 'Nema', 'Blago', 'Jako',
     'Sedeći posao, bez treninga', '1-2 treninga nedeljno', '3-4 treninga nedeljno', '5-6 treninga ili fizički posao', 'Svakodnevno teško',
     'Brži gubitak masti (−20%)', 'Gubitak masti (−15%)', 'Održavanje', 'Povećanje mase (+10%)',
     'Nemaš pristup', 'Stranica ne postoji', 'Nedozvoljen zahtev', 'Sesija je istekla', 'Greška',
     'Sesija je istekla ili je zahtev stigao sa druge strane. Osveži stranu i probaj ponovo.',
     'Članarina je istekla — program je zaključan dok se ne produži.', 'Članarina je istekla — program je zaključan.', 'Trening ne postoji.',
     'Prijava je istekla. Osveži stranu i prijavi se ponovo.',
     'Izaberi sliku.', 'Slika je veća od 8 MB.', 'Otpremanje nije uspelo. Probaj ponovo.', 'Slika mora biti JPG, PNG ili WebP.',
     'Na serveru nije uključen PHP dodatak GD (cPanel → Select PHP Version → gd).', 'Slika ne može da se sačuva (prava na folder privatno/slike).',
     'Čuvam…', 'Sačuvano u %s', 'Nije sačuvano: %s Pokušaću ponovo.', 'Ima nesačuvanih izmena…', 'Link je kopiran', 'Glavni meni'],
    []) as $x) {
    $kljucevi[$x] ??= '(promenljiva)';
}
$en = require "$koren/app/jezik/en.php";
$fali = array_diff_key($kljucevi, $en);
foreach ($fali as $k => $f) {
    echo str_pad($f, 28) . ' ' . var_export($k, true) . "\n";
}
echo count($kljucevi) . ' tekstova, bez prevoda: ' . count($fali) . "\n";
exit($fali ? 1 : 0);
