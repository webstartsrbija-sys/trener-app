<?php
require __DIR__ . '/_osnova.php';
if (post()) {
    $kg = unos_broj($_POST['kg'] ?? '');
    $datum = ispravan_datum($_POST['datum'] ?? null) ? $_POST['datum'] : danas();
    if ($kg === null || $kg <= 30 || $kg >= 300) {
        poruka('Upiši težinu u kilogramima, npr. 69,6', 'greska');
    } elseif ($datum > danas() || $datum < dan_plus(danas(), -60)) {
        poruka('Datum može biti danas ili do 60 dana unazad.', 'greska');
    } else {
        upisi_tezinu($k['id'], $datum, $kg);
        poruka('Težina je upisana');
    }
    preusmeri('/napredak');
}
$naslov = 'Napredak';
$aktivno = 'napredak';
$moze_upis = true;
require KOREN . '/delovi/glava.php';
echo clanarina_upozorenje($k);
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Nedelja <?= nedelja_programa($k) ?></span><h1>Napredak</h1></section>
<?php require KOREN . '/delovi/napredak.php'; ?>
<?php require KOREN . '/delovi/podnozje.php';
