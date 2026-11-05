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
    $tr = e(pod('sajt.trener_ime'));
    $d = e(datum_lep($k['clanarina_do']));
    if ($n < 0) {
        $dodatak = program_blokiran($k) ? 'Istorija i napredak su ti i dalje dostupni, a program se otvara čim ' . $tr . ' produži članarinu.' : 'Javi se ' . $tr . 'u i produži je.';
        return '<section class="card alert bad"><b>Tvoja članarina je istekla ' . $d . '.</b><span>' . $dodatak . '</span></section>';
    }
    return '<section class="card alert warn"><b>Tvoja članarina ističe ' . za_dana($n) . ' (' . $d . ').</b><span>Javi se ' . $tr . 'u i produži je, da ne izgubiš pristup programu.</span></section>';
}
