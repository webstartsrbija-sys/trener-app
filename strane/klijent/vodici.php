<?php
require __DIR__ . '/_osnova.php';
$naslov = 'Vodiči';
$aktivno = 'vodici';
$lista = redovi('SELECT * FROM vodici WHERE objavljen = 1 ORDER BY redosled, kategorija, naslov');
require KOREN . '/delovi/glava.php';
echo clanarina_upozorenje($k);
?>
<section class="band"><span class="eyebrow" style="color:inherit;opacity:.7">Priručnici • vebinari • video</span><h1>Vodiči</h1></section>
<section class="card">
<?php if (!$lista): ?><p class="muted" style="margin:0"><?= e(pod('sajt.trener_ime')) ?> još nije dodao materijale.</p><?php endif; ?>
<?php foreach ($lista as $v): ?>
  <div class="guide"><div><?php if ($v['kategorija'] !== ''): ?><span class="chip"><?= e($v['kategorija']) ?></span><?php endif; ?>
    <div style="font-weight:700;margin-top:4px"><?= e($v['naslov']) ?></div><div class="muted" style="font-size:14px"><?= e($v['opis']) ?></div></div>
    <a class="btn small" href="<?= e($v['tip'] === 'pdf' ? '/vodic/' . $v['id'] : $v['url']) ?>" target="_blank" rel="noopener"><?= $v['tip'] === 'pdf' ? 'PDF' : 'Otvori' ?></a></div>
<?php endforeach; ?>
</section>
<?php require KOREN . '/delovi/podnozje.php';
