<?php
/**
 * Profilna slika. Vidi je: sam korisnik, trener (sve slike), a klijenti i
 * trenerovu sliku. Klijent nikad ne vidi sliku drugog klijenta.
 */
$ja = korisnik();
if (!$ja) {
    greska(404);
}
$id = (int)$param[0];
$r = red('SELECT id, uloga, slika FROM korisnici WHERE id = ?', [$id]);
$sme = $r && ($ja['id'] === $id || $ja['uloga'] === 'trener' || $r['uloga'] === 'trener');
if (!$sme || !$r['slika'] || !preg_match('/^[a-f0-9]{32}\.jpg$/', $r['slika']) || !is_file(SLIKA_DIR . '/' . $r['slika'])) {
    greska(404);
}
$fajl = SLIKA_DIR . '/' . $r['slika'];
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($fajl));
// Adresa nosi ?v= (deo imena fajla), pa nova slika dobija novu adresu
header('Cache-Control: private, max-age=2592000');
readfile($fajl);
