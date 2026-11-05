<?php
/** Zajedničko za klijentove strane: ko je prijavljen i koji je NJEGOV klijent. */
$ja = zahtevaj_ulogu('klijent');
$k = klijent((int)$ja['klijent_id']);
if (!$k || (int)$k['arhiviran']) {
    odjavi();
    preusmeri('/prijava');
}
$nav = 'klijent';

/** Upozorenje o članarini (3 dana pre i posle isteka), kao u prototipu. */
function clanarina_upozorenje(array $k): string
{
    $n = clanarina_dana($k);
    if ($n === null || $n > (int)pod('clanarina.upozorenje_dana', 3)) {
        return '';
    }
    $tr = (string)pod('sajt.trener_ime');
    $d = datum_lep($k['clanarina_do']);
    if ($n < 0) {
        $dodatak = program_blokiran($k)
            ? t('Istorija i napredak su ti i dalje dostupni, a program se otvara čim %s produži članarinu.', $tr)
            : t('Javi se %su i produži je.', $tr);
        return '<section class="card alert bad"><b>' . te('Tvoja članarina je istekla %s.', $d) . '</b><span>' . e($dodatak) . '</span></section>';
    }
    return '<section class="card alert warn"><b>' . te('Tvoja članarina ističe %s (%s).', za_dana($n), $d) . '</b><span>'
        . te('Javi se %su i produži je, da ne izgubiš pristup programu.', $tr) . '</span></section>';
}
