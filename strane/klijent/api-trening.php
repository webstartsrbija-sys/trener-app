<?php
/** Autosave treninga (JSON). Klijent iz sesije; trening/serije se proveravaju u sacuvaj_unos_klijenta(). */
$ja = zahtevaj_ulogu('klijent');
if (!post()) {
    greska(405);
}
$k = klijent((int)$ja['klijent_id']);
if (!$k || program_blokiran($k)) {
    greska(403, 'Članarina je istekla — program je zaključan.');
}
// Sesija više ne treba (provera prijave i CSRF su gotovi) — otpusti je odmah
session_write_close();
$r = sacuvaj_unos_klijenta($k['id'], $_POST, false);
json(['ok' => true, 'sesija_id' => $r['sesija_id'], 'mapa' => $r['mapa'], 'status' => $r['status'], 'vreme' => date('H:i')]);
