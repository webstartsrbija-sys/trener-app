<?php
$k = korisnik();
if (!$k) {
    preusmeri('/prijava');
}
preusmeri($k['uloga'] === 'trener' ? '/trener' : '/trening');
