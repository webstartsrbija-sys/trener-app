<?php
/** PDF vodič: servira ga PHP, samo prijavljenima (fajlovi su van dometa pregledača). */
$ja = korisnik();
if (!$ja) {
    preusmeri('/prijava');
}
$v = red("SELECT * FROM vodici WHERE id = ? AND tip = 'pdf'", [(int)$param[0]]);
if (!$v || (!$v['objavljen'] && $ja['uloga'] !== 'trener') || !preg_match('/^[a-f0-9]{32}\.pdf$/', $v['fajl'])) {
    greska(404);
}
$fajl = KOREN . '/privatno/vodici/' . $v['fajl'];
if (!is_file($fajl)) {
    greska(404);
}
$ime = $v['fajl_ime'] !== '' ? $v['fajl_ime'] : 'vodic.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($fajl));
header("Content-Disposition: inline; filename=\"" . preg_replace('/[^A-Za-z0-9._-]/', '_', $ime) . "\"; filename*=UTF-8''" . rawurlencode($ime));
header('Cache-Control: private, max-age=3600');
readfile($fajl);
