<?php
require __DIR__ . '/_osnova.php';
$naslov = 'Istorija';
$aktivno = 'istorija';
$url = '/istorija?';
require KOREN . '/delovi/glava.php';
echo clanarina_upozorenje($k);
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= te('Treninzi i ishrana') ?></span><h1><?= te('Istorija') ?></h1></section>
<?php require KOREN . '/delovi/istorija.php'; ?>
<?php require KOREN . '/delovi/podnozje.php';
