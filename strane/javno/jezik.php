<?php
/**
 * Promena jezika. Neprijavljen: GET, samo kolačić. Prijavljen klijent: POST
 * (CSRF), čuva se i na nalogu — pa i mejlovi stižu na tom jeziku.
 */
$j = ($_POST['j'] ?? $_GET['j'] ?? '') === 'en' ? 'en' : 'sr';
$posle = (string)($_POST['posle'] ?? $_GET['posle'] ?? '/');
if (!preg_match('~^/(?!/)[A-Za-z0-9/_\-?=&%.]*$~', $posle)) {
    $posle = '/';
}
$k = korisnik();
if ($k) {
    if (!post()) {
        preusmeri($posle);
    }
    if ($k['uloga'] === 'klijent') {
        upit('UPDATE korisnici SET jezik = ? WHERE id = ?', [$j, $k['id']]);
    }
}
postavi_kolacic(KOLACIC_JEZIK, $j, time() + 365 * 86400);
preusmeri($posle);
