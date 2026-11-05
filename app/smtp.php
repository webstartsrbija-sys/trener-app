<?php
/**
 * Slanje mejla preko SMTP-a — jedan fajl, bez Composera i biblioteka.
 *
 * Zašto ne mail(): na deljenom hostingu PHP mail() ume da „uspe" (vrati true)
 * a da poruka nikad ne izađe sa servera, ili da je primalac odbaci jer nije
 * potpisana kao pošta sa našeg domena. Preko SMTP-a se prijavljujemo na pravi
 * mejl nalog (sajt@webstartsrbija.rs), pa poruka ide istim putem kao da je
 * poslata iz Outlook-a — i svaka greška stiže kao odgovor servera.
 *
 * Podržano: SSL na portu 465, STARTTLS na 587, bez šifrovanja (samo za
 * lokalnu probu), prijava AUTH LOGIN / AUTH PLAIN. Lozinka se nikad ne upisuje
 * u log.
 */

declare(strict_types=1);

/**
 * Šalje jednu tekstualnu poruku.
 *
 * @param array $smtp  sekcija 'smtp' iz config.php
 * @param array $poruka ['od' => adresa, 'od_ime' => ime, 'za' => adresa,
 *                       'naslov' => tekst, 'telo' => tekst, 'odgovor_na' => adresa|'' ]
 * @return array{uspeh: bool, greska: string}
 */
function smtp_posalji(array $smtp, array $poruka): array
{
    $host    = trim((string)($smtp['host'] ?? ''));
    $port    = (int)($smtp['port'] ?? 465);
    $sifra   = strtolower((string)($smtp['sifrovanje'] ?? 'ssl'));   // ssl | tls | nista
    $timeout = (int)($smtp['timeout'] ?? 15);
    $provera = (bool)($smtp['proveri_sertifikat'] ?? true);

    if ($host === '') {
        return ['uspeh' => false, 'greska' => 'SMTP host nije podešen u config.php'];
    }

    $kontekst = stream_context_create(['ssl' => [
        'verify_peer'       => $provera,
        'verify_peer_name'  => $provera,
        'allow_self_signed' => !$provera,
        'peer_name'         => $host,
        'SNI_enabled'       => true,
    ]]);

    $adresa = ($sifra === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $veza = @stream_socket_client($adresa, $br, $tekst, $timeout, STREAM_CLIENT_CONNECT, $kontekst);
    if (!$veza) {
        // Kod SSL greške $tekst je prazan; pravi razlog (npr. sertifikat) je u error_get_last()
        $razlog = trim($tekst) !== '' ? "$br: $tekst" : (error_get_last()['message'] ?? 'nepoznat razlog');
        $savet = $sifra === 'ssl' ? ' — proverite port (465 za ssl, 587 za tls) i sertifikat servera' : '';
        return ['uspeh' => false, 'greska' => "Nema veze sa $adresa ($razlog)$savet"];
    }
    stream_set_timeout($veza, $timeout);

    try {
        smtp_ocekuj($veza, 220);
        $ime_servera = smtp_ime_servera();
        $mogucnosti = smtp_ehlo($veza, $ime_servera);

        if ($sifra === 'tls') {
            smtp_komanda($veza, 'STARTTLS', 220);
            $metod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                   | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            if (!@stream_socket_enable_crypto($veza, true, $metod)) {
                throw new RuntimeException('STARTTLS nije uspeo (sertifikat ili TLS verzija)');
            }
            $mogucnosti = smtp_ehlo($veza, $ime_servera);   // posle TLS-a se pita ponovo
        }

        $korisnik = (string)($smtp['korisnik'] ?? '');
        if ($korisnik !== '') {
            $lozinka = (string)($smtp['lozinka'] ?? '');
            if (preg_match('/AUTH[ =][^\r\n]*\bLOGIN\b/i', $mogucnosti)) {
                smtp_komanda($veza, 'AUTH LOGIN', 334);
                smtp_komanda($veza, base64_encode($korisnik), 334, 'korisničko ime');
                smtp_komanda($veza, base64_encode($lozinka), 235, 'lozinka');
            } else {
                smtp_komanda($veza, 'AUTH PLAIN ' . base64_encode("\0$korisnik\0$lozinka"), 235, 'prijava');
            }
        }

        $od = smtp_cista_adresa((string)$poruka['od']);
        $za = smtp_cista_adresa((string)$poruka['za']);
        if ($od === '' || $za === '') {
            throw new RuntimeException('Neispravna adresa pošiljaoca ili primaoca');
        }
        smtp_komanda($veza, "MAIL FROM:<$od>", 250);
        smtp_komanda($veza, "RCPT TO:<$za>", [250, 251]);
        smtp_komanda($veza, 'DATA', 354);

        $tekst_poruke = smtp_sastavi($poruka, $od, $za, $ime_servera);
        // Red koji počinje tačkom bi server shvatio kao kraj poruke — udvaja se
        $tekst_poruke = preg_replace('/^\./m', '..', $tekst_poruke);
        fwrite($veza, $tekst_poruke . "\r\n.\r\n");
        smtp_ocekuj($veza, 250);

        @fwrite($veza, "QUIT\r\n");
        fclose($veza);
        return ['uspeh' => true, 'greska' => ''];
    } catch (RuntimeException $g) {
        @fwrite($veza, "QUIT\r\n");
        @fclose($veza);
        return ['uspeh' => false, 'greska' => $g->getMessage()];
    }
}

/** Šalje komandu i proverava kod odgovora. $opis zamenjuje komandu u grešci (lozinka!). */
function smtp_komanda($veza, string $komanda, int|array $kod, string $opis = ''): string
{
    fwrite($veza, $komanda . "\r\n");
    return smtp_ocekuj($veza, $kod, $opis !== '' ? $opis : $komanda);
}

/** Čita odgovor servera (i višeredni „250-…") i baca grešku ako kod nije očekivan. */
function smtp_ocekuj($veza, int|array $kod, string $posle = ''): string
{
    $odgovor = '';
    while (($red = fgets($veza, 4096)) !== false) {
        $odgovor .= $red;
        if (strlen($red) < 4 || $red[3] === ' ') {
            break;
        }
    }
    if ($odgovor === '') {
        $meta = stream_get_meta_data($veza);
        throw new RuntimeException('Server nije odgovorio' . ($posle !== '' ? " posle: $posle" : '')
                                   . (!empty($meta['timed_out']) ? ' (isteklo vreme)' : ''));
    }
    $dobio = (int)substr($odgovor, 0, 3);
    if (!in_array($dobio, (array)$kod, true)) {
        throw new RuntimeException('SMTP ' . ($posle !== '' ? "($posle) " : '') . 'odgovor: ' . trim($odgovor));
    }
    return $odgovor;
}

function smtp_ehlo($veza, string $ime): string
{
    fwrite($veza, "EHLO $ime\r\n");
    return smtp_ocekuj($veza, 250, 'EHLO');
}

/** Ime koje se predstavlja serveru: domen sajta, ne „localhost". */
function smtp_ime_servera(): string
{
    $h = strtolower((string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));
    $h = preg_replace('/[^a-z0-9.\-]/', '', $h) ?? '';
    return $h !== '' ? $h : 'localhost';
}

/** Samo adresa, bez razmaka i preloma redova (sprečava ubacivanje SMTP komandi). */
function smtp_cista_adresa(string $a): string
{
    $a = trim(str_replace(["\r", "\n", '<', '>'], '', $a));
    return filter_var($a, FILTER_VALIDATE_EMAIL) ? $a : '';
}

/**
 * Zaglavlja + telo. Telo ide kao base64: radi na svakom serveru, bez obzira
 * na naša slova i dužinu reda.
 */
function smtp_sastavi(array $p, string $od, string $za, string $ime_servera): string
{
    $kod = static fn(string $t): string =>
        preg_match('/[\x80-\xFF]/', $t) ? '=?UTF-8?B?' . base64_encode($t) . '?=' : $t;
    $jedan_red = static fn(string $t): string => trim(str_replace(["\r", "\n"], ' ', $t));

    $domen = substr(strrchr($od, '@') ?: '@' . $ime_servera, 1);
    $od_ime = $jedan_red((string)($p['od_ime'] ?? ''));
    $z = [
        'Date: ' . date('r'),
        'From: ' . ($od_ime !== '' ? $kod($od_ime) . ' ' : '') . "<$od>",
        "To: <$za>",
        'Subject: ' . $kod($jedan_red((string)$p['naslov'])),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domen . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: WebStart CMS',
    ];
    $odgovor = smtp_cista_adresa((string)($p['odgovor_na'] ?? ''));
    if ($odgovor !== '') {
        $z[] = "Reply-To: <$odgovor>";
    }
    $telo = str_replace(["\r\n", "\r"], "\n", (string)$p['telo']);
    $telo = str_replace("\n", "\r\n", $telo);
    return implode("\r\n", $z) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($telo), 76, "\r\n"));
}

/**
 * Upisuje grešku slanja u kes/mejl-greske.log (folder je zaključan za
 * pregledač), a ako to ne može — u PHP error log. Lozinka nikad ne ulazi
 * u poruku: smtp_komanda() za nju piše samo „lozinka".
 */
function mejl_zabelezi_gresku(string $poruka): void
{
    $red = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $poruka) . "\n";
    $fajl = dirname(__DIR__) . '/kes/mejl-greske.log';
    if (@file_put_contents($fajl, $red, FILE_APPEND | LOCK_EX) === false) {
        error_log('WebStart mejl: ' . trim($red));
    }
}
