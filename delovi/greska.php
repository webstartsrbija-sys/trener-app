<?php
/** Strana greške. Očekuje $status, $naslov, $poruka. */
$nav = null;
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Greška <?= (int)$status ?></span><h1><?= e($naslov) ?></h1><span class="muted"><?= e($poruka) ?></span></section>
<a class="btn primary" href="/" style="align-self:flex-start">Na početnu</a>
<?php require KOREN . '/delovi/podnozje.php';
