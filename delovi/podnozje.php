<?php
/** Kraj strane: donja navigacija i poruka (toast). */
$ikone = [
    'hi' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8 14h2M14 14h2M8 17h2"/></svg>',
    'tr' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 7v10M18 7v10M3 10v4M21 10v4M6 12h12"/></svg>',
    'is' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 3v8a3 3 0 0 0 3 3v7M7 3v5M10 3v5M17 21V3c-2 1-3 4-3 8h3"/></svg>',
    'na' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg>',
    'vo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 5h6a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H4zM20 5h-6a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h6z"/></svg>',
    'kl' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/></svg>',
    'bz' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3z"/></svg>',
    'po' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></svg>',
];
$stavke = match ($nav ?? null) {
    'klijent' => [['/trening', 'trening', 'Trening', 'tr'], ['/ishrana', 'ishrana', 'Ishrana', 'is'], ['/istorija', 'istorija', 'Istorija', 'hi'], ['/napredak', 'napredak', 'Napredak', 'na'], ['/vodici', 'vodici', 'Vodiči', 'vo']],
    'trener'  => [['/trener', 'pocetna', 'Početna', 'po'], ['/trener/klijenti', 'klijenti', 'Klijenti', 'kl'], ['/trener/vezbe', 'vezbe', 'Vežbe', 'bz'], ['/trener/ishrana', 'ishrana', 'Ishrana', 'is'], ['/trener/vodici', 'vodici', 'Vodiči', 'vo']],
    default   => [],
};
$p = uzmi_poruku();
?>
</main>
<?php if ($stavke): ?>
<nav class="bottom" aria-label="<?= te('Glavni meni') ?>"><div class="wrap">
<?php foreach ($stavke as [$href, $kljuc, $natpis, $ik]): ?>
  <a href="<?= $href ?>"<?= $aktivno === $kljuc ? ' aria-current="page"' : '' ?>><?= $ikone[$ik] ?><?= te($natpis) ?></a>
<?php endforeach; ?>
</div></nav>
<?php endif; ?>
<div id="toast" class="toast<?= $p && $p['vrsta'] === 'greska' ? ' bad' : '' ?>" role="status" aria-live="polite"<?= $p ? '' : ' hidden' ?>><?= $p ? e($p['tekst']) : '' ?></div>
</body>
</html>
