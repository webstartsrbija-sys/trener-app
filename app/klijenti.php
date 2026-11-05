<?php
/**
 * Klijenti: podaci, članarina, doslednost i "semafor" pažnje za trenera.
 */

declare(strict_types=1);

const CILJEVI = ['Gubitak masti', 'Povećanje snage', 'Rekompozicija', 'Povećanje mase', 'Opšta forma'];

/** Klijent sa podacima naloga i okvirom ishrane. Bez provere prava — to radi pozivalac. */
function klijent(int $id): ?array
{
    $k = red('SELECT kl.*, k.ime, k.email, k.slika, k.jezik, k.aktivan, k.poslednja_prijava,
                     (k.lozinka_hash IS NOT NULL) AS ima_lozinku,
                     o.kcal, o.p, o.uh, o.m
              FROM klijenti kl
              JOIN korisnici k ON k.id = kl.korisnik_id
              LEFT JOIN okvir_ishrane o ON o.klijent_id = kl.id
              WHERE kl.id = ?', [$id]);
    if ($k) {
        $k['id'] = (int)$k['id'];
        $k['korisnik_id'] = (int)$k['korisnik_id'];
    }
    return $k;
}

function ime_kratko(string $ime): string
{
    return explode(' ', trim($ime))[0];
}

function inicijali(string $ime): string
{
    $s = '';
    foreach (preg_split('/\s+/u', trim($ime)) ?: [] as $deo) {
        $s .= mb_strtoupper(mb_substr($deo, 0, 1));
    }
    return mb_substr($s, 0, 2);
}

/** Podrazumevani dani treninga za broj treninga nedeljno (kao u prototipu). */
function podrazumevani_dani(int $n): array
{
    return match (true) {
        $n <= 1 => [3],
        $n === 2 => [1, 4],
        $n === 3 => [1, 3, 5],
        $n === 4 => [1, 2, 4, 5],
        $n === 5 => [1, 2, 3, 4, 5],
        $n === 6 => [1, 2, 3, 4, 5, 6],
        default => [1, 2, 3, 4, 5, 6, 7],
    };
}

function dani_treninga(array $k): array
{
    $d = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$k['dani_treninga'])),
        fn($x) => $x >= 1 && $x <= 7)));
    sort($d);
    return $d ?: podrazumevani_dani((int)$k['treninga_nedeljno']);
}

function nedelja_programa(array $k): int
{
    return max(1, intdiv(max(0, razlika_dana(ponedeljak($k['datum_pocetka']), danas())), 7) + 1);
}

function oznake(array $k): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)$k['oznake']))));
}

/* -------------------------------------------------------------------------
   Članarina
   ------------------------------------------------------------------------- */

/** Dana do isteka (0 = ističe danas, negativno = istekla), null = nije postavljena. */
function clanarina_dana(array $k): ?int
{
    return $k['clanarina_do'] ? razlika_dana(danas(), $k['clanarina_do']) : null;
}

function clanarina_istekla(array $k): bool
{
    $n = clanarina_dana($k);
    return $n !== null && $n < 0;
}

function program_blokiran(array $k): bool
{
    return clanarina_istekla($k) && pod('clanarina.blokira_program', true) === true;
}

function za_dana(int $n): string
{
    return $n === 0 ? t('danas') : ($n === 1 ? t('za 1 dan') : t('za %s dana', $n));
}

function clanarina_chip(array $k): string
{
    $n = clanarina_dana($k);
    if ($n === null) {
        return '<span class="chip mute">Članarina nije postavljena</span>';
    }
    if ($n < 0) {
        return '<span class="chip bad">Članarina istekla ' . e(datum_lep($k['clanarina_do'])) . '</span>';
    }
    if ($n <= (int)pod('clanarina.upozorenje_dana', 3)) {
        return '<span class="chip warn">Članarina ističe ' . za_dana($n) . '</span>';
    }
    return '<span class="chip mute">Članarina do ' . e(datum_lep($k['clanarina_do'])) . '</span>';
}

/** Produžava članarinu i upisuje u istoriju. */
function produzi_clanarinu(int $klijent_id, string $novo_do, ?float $iznos, string $napomena): void
{
    transakcija(function () use ($klijent_id, $novo_do, $iznos, $napomena) {
        $staro = vrednost('SELECT clanarina_do FROM klijenti WHERE id = ? FOR UPDATE', [$klijent_id]);
        upit('UPDATE klijenti SET clanarina_do = ? WHERE id = ?', [$novo_do, $klijent_id]);
        upit('INSERT INTO produzenja (klijent_id, staro_do, novo_do, iznos, napomena, kreirano) VALUES (?, ?, ?, ?, ?, ?)',
            [$klijent_id, $staro ?: null, $novo_do, $iznos, mb_substr($napomena, 0, 255), sada()]);
    });
}

/* -------------------------------------------------------------------------
   Doslednost i statistika treninga
   ------------------------------------------------------------------------- */

/** Datumi završenih treninga u periodu: ['2026-11-03' => 1, ...] */
function zavrseni_po_danu(int $klijent_id, string $od, string $do): array
{
    $r = [];
    foreach (redovi("SELECT datum, COUNT(*) AS n FROM sesije
                     WHERE klijent_id = ? AND status = 'zavrsena' AND datum BETWEEN ? AND ?
                     GROUP BY datum", [$klijent_id, $od, $do]) as $x) {
        $r[$x['datum']] = (int)$x['n'];
    }
    return $r;
}

/**
 * Urađeno / planirano u periodu. Planirani su dani treninga pre danas; danas
 * i trening van plana ulaze u planirane tek kad su urađeni.
 *
 * @return array{dn:int, pl:int}
 */
function treninzi_u_periodu(array $k, string $od, string $do, ?array $zavrseni = null): array
{
    $od = max($od, $k['datum_pocetka']);
    if ($od > $do) {
        return ['dn' => 0, 'pl' => 0];
    }
    $zavrseni ??= zavrseni_po_danu($k['id'], $od, $do);
    $dani = dani_treninga($k);
    $danas = danas();
    $dn = 0;
    $pl = 0;
    for ($d = $od; $d <= $do; $d = dan_plus($d, 1)) {
        $uradjeno = $zavrseni[$d] ?? 0;
        $dn += $uradjeno;
        if (in_array(dan_u_nedelji($d), $dani, true) && $d < $danas) {
            $pl += max(1, $uradjeno);
        } else {
            $pl += $uradjeno;
        }
    }
    return ['dn' => $dn, 'pl' => $pl];
}

/** Doslednost u poslednje 4 nedelje, u procentima, ili null ako još nema planiranih treninga. */
function doslednost(array $k): ?int
{
    $do = dan_plus(danas(), -1);
    $s = treninzi_u_periodu($k, dan_plus(danas(), -28), $do);
    $danas = zavrseni_po_danu($k['id'], danas(), danas());
    $s['dn'] += $danas[danas()] ?? 0;
    $s['pl'] += $danas[danas()] ?? 0;
    return $s['pl'] > 0 ? min(100, (int)round($s['dn'] / $s['pl'] * 100)) : null;
}

/* -------------------------------------------------------------------------
   Semafor pažnje: crveno / narandžasto / zeleno
   ------------------------------------------------------------------------- */

/**
 * @return array{t:string, m:string, nivo:int}  nivo: 0 crveno, 1 narandžasto, 2 zeleno
 */
function signal(array $k): array
{
    $danas = danas();
    $poslednja = red("SELECT s.id, s.naziv, s.datum, c.bol FROM sesije s
                      LEFT JOIN checkini c ON c.sesija_id = s.id
                      WHERE s.klijent_id = ? AND s.status = 'zavrsena'
                      ORDER BY s.datum DESC, s.zavrseno_u DESC LIMIT 1", [$k['id']]);
    $bol = red("SELECT s.datum, c.bol FROM sesije s JOIN checkini c ON c.sesija_id = s.id
                WHERE s.klijent_id = ? AND s.status = 'zavrsena' AND c.bol IS NOT NULL AND s.datum >= ?
                ORDER BY s.datum DESC, s.zavrseno_u DESC LIMIT 1",
        [$k['id'], dan_plus($danas, -(int)pod('paznja.dana_bol', 7))]);
    $n = clanarina_dana($k);
    $prag = (int)pod('paznja.dana_bez_treninga', 4);

    // Nivo uzima u obzir i članarinu, ali poruka govori o aktivnosti —
    // članarina ima svoj čip pored, pa se ne ponavlja.
    $clan_nivo = $n === null ? 2 : ($n < 0 ? 0 : ($n <= (int)pod('clanarina.upozorenje_dana', 3) ? 1 : 2));
    $sa_clanarinom = function (array $s) use ($clan_nivo): array {
        $nivo = min($s['nivo'], $clan_nivo);
        return ['t' => ['bad', 'warn', 'ok'][$nivo], 'nivo' => $nivo, 'm' => $s['m']];
    };

    if ($bol && $bol['bol'] !== 'Nema') {
        return $sa_clanarinom(['nivo' => 0, 'm' => 'Bol: ' . mb_strtolower($bol['bol']) . ' (check-in ' . datum_lep($bol['datum']) . ')']);
    }
    if (!(int)$k['ima_lozinku']) {
        return $sa_clanarinom(['nivo' => 1, 'm' => 'Čeka postavljanje lozinke']);
    }
    if (!$poslednja) {
        $od_pocetka = razlika_dana($k['datum_pocetka'], $danas);
        return $sa_clanarinom($od_pocetka >= $prag
            ? ['nivo' => 1, 'm' => "Bez treninga od početka ($od_pocetka dana)"]
            : ['nivo' => 1, 'm' => 'Novi nalog, čeka prvi trening']);
    }
    $bez = razlika_dana($poslednja['datum'], $danas);
    if ($bez >= $prag) {
        return $sa_clanarinom(['nivo' => 1, 'm' => "Bez treninga $bez dana"]);
    }
    $kad = $bez === 0 ? 'danas' : ($bez === 1 ? 'juče' : datum_lep($poslednja['datum']));
    return $sa_clanarinom(['nivo' => 2, 'm' => 'Završen ' . $poslednja['naziv'] . ' ' . $kad]);
}

/** Lista za trenera, sortirana po pažnji. */
function lista_klijenata(bool $arhivirani = false, string $trazi = ''): array
{
    $sql = 'SELECT kl.id FROM klijenti kl JOIN korisnici k ON k.id = kl.korisnik_id WHERE kl.arhiviran = ?';
    $par = [$arhivirani ? 1 : 0];
    if ($trazi !== '') {
        $sql .= ' AND k.ime LIKE ?';
        $par[] = '%' . addcslashes($trazi, '%_\\') . '%';
    }
    $lista = [];
    foreach (redovi($sql, $par) as $r) {
        $k = klijent((int)$r['id']);
        $k['signal'] = signal($k);
        $k['doslednost'] = doslednost($k);
        $k['promena_kg'] = promena_tezine_ukupno($k['id']);
        $lista[] = $k;
    }
    usort($lista, fn($a, $b) => [$a['signal']['nivo'], $a['ime']] <=> [$b['signal']['nivo'], $b['ime']]);
    return $lista;
}

/* -------------------------------------------------------------------------
   Pravljenje klijenta
   ------------------------------------------------------------------------- */

/**
 * @return array{id:int, korisnik:array, token:string}
 */
function napravi_klijenta(array $p): array
{
    return transakcija(function () use ($p) {
        $jezik = ($p['jezik'] ?? 'sr') === 'en' ? 'en' : 'sr';
        upit("INSERT INTO korisnici (uloga, email, ime, jezik, kreirano) VALUES ('klijent', ?, ?, ?, ?)",
            [$p['email'], $p['ime'], $jezik, sada()]);
        $kid = poslednji_id();
        $dani = implode(',', podrazumevani_dani($p['treninga_nedeljno']));
        upit('INSERT INTO klijenti (korisnik_id, cilj, datum_pocetka, treninga_nedeljno, dani_treninga, clanarina_do, kreirano)
              VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$kid, $p['cilj'], $p['datum_pocetka'], $p['treninga_nedeljno'], $dani, $p['clanarina_do'], sada()]);
        $id = poslednji_id();
        if ($p['clanarina_do']) {
            upit('INSERT INTO produzenja (klijent_id, staro_do, novo_do, napomena, kreirano) VALUES (?, NULL, ?, ?, ?)',
                [$id, $p['clanarina_do'], 'Prva članarina', sada()]);
        }
        $pid = program_klijenta($id);
        if (!empty($p['sablon_id'])) {
            kopiraj_program((int)$p['sablon_id'], $pid);
        }
        $token = napravi_token($kid, 'poziv');
        return ['id' => $id, 'korisnik' => ['ime' => $p['ime'], 'email' => $p['email'], 'jezik' => $jezik], 'token' => $token];
    });
}
