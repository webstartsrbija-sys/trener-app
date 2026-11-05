<?php
/** Strana greške. Očekuje $status, $naslov, $poruka (već prevedene). */
$nav = null;
require KOREN . '/delovi/glava.php';
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7"><?= te('Greška') ?> <?= (int)$status ?></span><h1><?= e($naslov) ?></h1><span class="muted"><?= e($poruka) ?></span></section>
<a class="btn primary" href="/" style="align-self:flex-start"><?= te('Na početnu') ?></a>
<?php require KOREN . '/delovi/podnozje.php';
