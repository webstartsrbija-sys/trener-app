<?php
/**
 * Dnevni zadatak (cPanel Cron Jobs, svaki dan u 20:00):
 *   1. klijentima: "članarina ističe za 3 dana" i "članarina je istekla" (svaki mejl jednom)
 *   2. treneru: JEDAN rezime dana — ko je završio trening, ko je prijavio bol, kome ističe članarina
 *
 * Komanda za cPanel (zameniti KORISNIK i putanju):
 *   /usr/local/bin/php /home/KORISNIK/trener.webstartsrbija.rs/cron/dnevno.php >> /home/KORISNIK/trener-cron.log 2>&1
 *
 * Ručna proba bez slanja:  php cron/dnevno.php --proba
 * Ponovno slanje rezimea:   php cron/dnevno.php --ponovo
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ovaj zadatak se pokreće samo iz komandne linije (cron).');
}

require __DIR__ . '/../app/osnova.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/klijenti.php';
require __DIR__ . '/../app/program.php';
require __DIR__ . '/../app/sesije.php';
require __DIR__ . '/../app/mejl.php';

$proba = in_array('--proba', $argv, true);
$ponovo = in_array('--ponovo', $argv, true);
$danas = danas();
$upoz = (int)pod('clanarina.upozorenje_dana', 3);
$log = fn(string $t) => print('[' . date('Y-m-d H:i:s') . '] ' . $t . "\n");

// Ključ: da se zadatak ne izvrši dvaput istog dana (npr. dupli cron)
if (!$proba && !$ponovo && vrednost('SELECT 1 FROM cron_dnevnik WHERE datum = ? AND zadatak = ?', [$danas, 'dnevno'])) {
    $log('Već izvršeno danas — preskačem. (--ponovo za ponovno slanje)');
    exit(0);
}

/* ---- 1. Članarine ---- */
$klijenti = redovi('SELECT kl.id FROM klijenti kl JOIN korisnici k ON k.id = kl.korisnik_id
                    WHERE kl.arhiviran = 0 AND k.aktivan = 1 AND kl.clanarina_do IS NOT NULL');
$istice = $istekla = [];
foreach ($klijenti as $r) {
    $k = klijent((int)$r['id']);
    $n = clanarina_dana($k);
    if ($n >= 0 && $n <= $upoz) {
        $istice[] = $k;
        if ($k['mejl_istice_za'] !== $k['clanarina_do'] && (int)$k['ima_lozinku']) {
            $ok = $proba || mejl_clanarina_istice($k, $k['clanarina_do']);
            if ($ok && !$proba) {
                upit('UPDATE klijenti SET mejl_istice_za = ? WHERE id = ?', [$k['clanarina_do'], $k['id']]);
            }
            $log(($proba ? '[proba] ' : '') . "Članarina ističe: {$k['email']} " . ($ok ? 'poslato' : 'NIJE POSLATO'));
        }
    } elseif ($n < 0) {
        if ($n >= -7) {
            $istekla[] = $k;
        }
        if ($k['mejl_istekla_za'] !== $k['clanarina_do'] && (int)$k['ima_lozinku']) {
            $ok = $proba || mejl_clanarina_istekla($k, $k['clanarina_do']);
            if ($ok && !$proba) {
                upit('UPDATE klijenti SET mejl_istekla_za = ? WHERE id = ?', [$k['clanarina_do'], $k['id']]);
            }
            $log(($proba ? '[proba] ' : '') . "Članarina istekla: {$k['email']} " . ($ok ? 'poslato' : 'NIJE POSLATO'));
        }
    }
}

/* ---- 2. Rezime za trenera ---- */
// Od prethodnog rezimea (da trening završen posle 20 h uđe u sutrašnji), a prvi put od ponoći
$od = (string)(vrednost("SELECT MAX(izvrseno) FROM cron_dnevnik WHERE zadatak = 'dnevno' AND datum < ?", [$danas]) ?: $danas . ' 00:00:00');
$zavrseni = redovi("SELECT s.id, s.naziv, s.napomena_klijenta, ko.ime, c.bol, c.spavanje, c.motivacija
                    FROM sesije s JOIN klijenti kl ON kl.id = s.klijent_id JOIN korisnici ko ON ko.id = kl.korisnik_id
                    LEFT JOIN checkini c ON c.sesija_id = s.id
                    WHERE s.status = 'zavrsena' AND s.zavrseno_u > ? AND kl.arhiviran = 0 ORDER BY s.zavrseno_u", [$od]);
$bol = array_filter($zavrseni, fn($s) => $s['bol'] !== null && $s['bol'] !== 'Nema');

$t = "Rezime za " . datum_naslov($danas) . "\n\n";
$t .= 'ZAVRŠENI TRENINZI od ' . date('j.n. \u H:i', strtotime($od)) . ' (' . count($zavrseni) . ")\n";
foreach ($zavrseni as $s) {
    $t .= "• {$s['ime']} — {$s['naziv']}" . ($s['bol'] && $s['bol'] !== 'Nema' ? " — BOL: {$s['bol']}" : '') . "\n";
    if (trim((string)$s['napomena_klijenta']) !== '') {
        $t .= '    „' . trim(str_replace("\n", ' ', $s['napomena_klijenta'])) . "“\n";
    }
}
if (!$zavrseni) {
    $t .= "Danas niko nije završio trening.\n";
}
$t .= "\nPRIJAVILI BOL / POVREDU (" . count($bol) . ")\n";
$t .= $bol ? implode('', array_map(fn($s) => "• {$s['ime']}: {$s['bol']}\n", $bol)) : "Niko.\n";
$t .= "\nČLANARINA\n";
foreach ($istice as $k) {
    $t .= "• {$k['ime']} — ističe " . za_dana(clanarina_dana($k)) . ' (' . datum_lep($k['clanarina_do']) . ")\n";
}
foreach ($istekla as $k) {
    $t .= "• {$k['ime']} — ISTEKLA " . datum_lep($k['clanarina_do']) . "\n";
}
if (!$istice && !$istekla) {
    $t .= "Nikome ne ističe u naredna $upoz dana.\n";
}
$t .= "\nSvi klijenti: " . adresa('/trener/klijenti') . "\n";

$prima = (string)pod('mejl.rezime_prima', '');
if ($prima === '') {
    $prima = (string)vrednost("SELECT email FROM korisnici WHERE uloga = 'trener' AND aktivan = 1 ORDER BY id LIMIT 1");
}
if ($proba) {
    echo "\n--- [proba] rezime za $prima ---\n$t\n";
    exit(0);
}
if ($prima === '') {
    $log('Nema trenerovog naloga — rezime nije poslat. Napravi ga: php alati/novi-trener.php');
    exit(1);
}
$naslov = 'Rezime dana: ' . count($zavrseni) . ' treninga' . ($bol ? ', ' . count($bol) . ' bol' : '') . (($istice || $istekla) ? ', članarine' : '');
$ok = posalji_mejl($prima, $naslov, $t);
upit('INSERT INTO cron_dnevnik (datum, zadatak, izvrseno, izvestaj) VALUES (?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE izvrseno = VALUES(izvrseno), izvestaj = VALUES(izvestaj)', [$danas, 'dnevno', sada(), ($ok ? 'poslato' : 'GREŠKA') . "\n" . $t]);
$log('Rezime za ' . $prima . ': ' . ($ok ? 'poslat' : 'NIJE POSLAT (vidi kes/mejl-greske.log)'));
exit($ok ? 0 : 1);
