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
$r = sacuvaj_unos_klijenta($k['id'], $_POST, false);
json(['ok' => true, 'sesija_id' => $r['sesija_id'], 'mapa' => $r['mapa'], 'status' => $r['status'], 'vreme' => date('H:i')]);
