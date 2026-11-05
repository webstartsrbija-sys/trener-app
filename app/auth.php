<?php
/**
 * Prijava, sesija, "zapamti me", tokeni za lozinku i CSRF.
 *
 * Uloga zavisi isključivo od naloga u bazi (korisnici.uloga). Klijentove rute
 * nikad ne primaju ID klijenta iz zahteva — uzimaju ga iz sesije.
 */

declare(strict_types=1);

const KOLACIC_SESIJE   = 'tc_sesija';
const KOLACIC_ZAPAMTI  = 'tc_zapamti';

function https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function sesija_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }
    if (headers_sent($fajl, $linija)) {
        // Glasno, da se greška vidi u logu umesto kao "sesija je istekla"
        error_log("sesija_start(): ispis je već počeo u $fajl:$linija — kolačić sesije ne može da se pošalje");
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        // Lax (ne Strict): klik na link iz mejla mora da stigne sa kolačićem
        'samesite' => 'Lax',
        'secure'   => https(),
    ]);
    session_name(KOLACIC_SESIJE);
    session_start();
}

/** Trenutno prijavljen korisnik (sa klijent_id za klijente), ili null. */
function korisnik(): ?array
{
    static $k = false;
    if ($k !== false) {
        return $k;
    }
    sesija_start();

    $id = (int)($_SESSION['korisnik_id'] ?? 0);
    if ($id > 0) {
        $minuta = (int)pod('bezbednost.sesija_minuta', 120);
        $zapamcen = !empty($_SESSION['zapamcen']);
        if (!$zapamcen && isset($_SESSION['aktivnost']) && time() - $_SESSION['aktivnost'] > $minuta * 60) {
            $id = 0;
            $_SESSION = [];
        }
    }
    if ($id === 0) {
        $id = prijava_iz_kolacica();
    }
    if ($id === 0) {
        return $k = null;
    }

    $r = red('SELECT k.id, k.uloga, k.email, k.ime, k.aktivan, kl.id AS klijent_id
              FROM korisnici k LEFT JOIN klijenti kl ON kl.korisnik_id = k.id
              WHERE k.id = ?', [$id]);
    if (!$r || !(int)$r['aktivan'] || ($r['uloga'] === 'klijent' && !$r['klijent_id'])) {
        odjavi();
        return $k = null;
    }
    $_SESSION['aktivnost'] = time();
    $r['id'] = (int)$r['id'];
    $r['klijent_id'] = $r['klijent_id'] !== null ? (int)$r['klijent_id'] : null;
    return $k = $r;
}

function zahtevaj_ulogu(string $uloga): array
{
    $k = korisnik();
    if (!$k) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            greska(401, 'Prijava je istekla. Osveži stranu i prijavi se ponovo.');
        }
        preusmeri('/prijava?posle=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    if ($k['uloga'] !== $uloga) {
        greska(403);
    }
    return $k;
}

/**
 * Pokušaj prijave. Vraća poruku o grešci ili null.
 * Ista poruka za nepostojeći nalog i pogrešnu lozinku.
 */
function prijavi(string $email, string $lozinka, bool $zapamti): ?string
{
    sesija_start();
    if (previse_pokusaja()) {
        return 'Previše neuspelih pokušaja. Sačekaj ' . (int)pod('bezbednost.period_minuta', 15) . ' minuta pa probaj ponovo.';
    }
    $r = red('SELECT id, lozinka_hash, aktivan FROM korisnici WHERE email = ?', [mb_strtolower(trim($email))]);
    $hash = $r['lozinka_hash'] ?? null;
    // password_verify i kad nalog ne postoji, da vreme odgovora ne oda ništa
    $ok = password_verify($lozinka, $hash ?: '$2y$10$' . str_repeat('x', 53));
    if (!$r || !$hash || !$ok || !(int)$r['aktivan']) {
        zabelezi_pokusaj();
        return 'Pogrešan email ili lozinka.';
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        upit('UPDATE korisnici SET lozinka_hash = ? WHERE id = ?', [password_hash($lozinka, PASSWORD_DEFAULT), $r['id']]);
    }
    uspostavi_sesiju((int)$r['id'], $zapamti);
    return null;
}

function uspostavi_sesiju(int $korisnik_id, bool $zapamti): void
{
    sesija_start();
    session_regenerate_id(true);
    $_SESSION['korisnik_id'] = $korisnik_id;
    $_SESSION['aktivnost'] = time();
    $_SESSION['zapamcen'] = $zapamti;
    upit('UPDATE korisnici SET poslednja_prijava = ? WHERE id = ?', [sada(), $korisnik_id]);
    if ($zapamti) {
        napravi_zapamti_kolacic($korisnik_id);
    }
}

function odjavi(): void
{
    sesija_start();
    $kol = $_COOKIE[KOLACIC_ZAPAMTI] ?? '';
    if (is_string($kol) && str_contains($kol, ':')) {
        upit('DELETE FROM zapamcene_prijave WHERE selektor = ?', [explode(':', $kol, 2)[0]]);
    }
    postavi_kolacic(KOLACIC_ZAPAMTI, '', time() - 3600);
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        postavi_kolacic(session_name(), '', time() - 3600);
        session_destroy();
    }
}

function postavi_kolacic(string $ime, string $vrednost, int $istice): void
{
    if (headers_sent()) {
        return;
    }
    setcookie($ime, $vrednost, [
        'expires' => $istice, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => https(),
    ]);
}

/* -------------------------------------------------------------------------
   Zapamti me: selektor (javan) + validator (tajan, u bazi samo heš).
   Validator se menja pri svakom korišćenju.
   ------------------------------------------------------------------------- */

function napravi_zapamti_kolacic(int $korisnik_id): void
{
    $dana = (int)pod('bezbednost.zapamti_dana', 30);
    $selektor = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    upit('DELETE FROM zapamcene_prijave WHERE istice < ?', [sada()]);
    upit('INSERT INTO zapamcene_prijave (korisnik_id, selektor, validator_hash, istice, kreirano) VALUES (?, ?, ?, ?, ?)',
        [$korisnik_id, $selektor, hash('sha256', $validator), date('Y-m-d H:i:s', time() + $dana * 86400), sada()]);
    postavi_kolacic(KOLACIC_ZAPAMTI, $selektor . ':' . $validator, time() + $dana * 86400);
}

function prijava_iz_kolacica(): int
{
    $kol = $_COOKIE[KOLACIC_ZAPAMTI] ?? '';
    if (!is_string($kol) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $kol, $m)) {
        return 0;
    }
    $r = red('SELECT id, korisnik_id, validator_hash, istice FROM zapamcene_prijave WHERE selektor = ?', [$m[1]]);
    if (!$r || $r['istice'] < sada() || !hash_equals($r['validator_hash'], hash('sha256', $m[2]))) {
        if ($r) {
            // Pogrešan validator uz ispravan selektor = verovatno ukraden kolačić
            upit('DELETE FROM zapamcene_prijave WHERE id = ?', [$r['id']]);
        }
        postavi_kolacic(KOLACIC_ZAPAMTI, '', time() - 3600);
        return 0;
    }
    upit('DELETE FROM zapamcene_prijave WHERE id = ?', [$r['id']]);
    $id = (int)$r['korisnik_id'];
    uspostavi_sesiju($id, true);
    return $id;
}

/* -------------------------------------------------------------------------
   Ograničenje pokušaja (po IP adresi, kao u CMS-u)
   ------------------------------------------------------------------------- */

function ip_binarno(): ?string
{
    $bin = @inet_pton($_SERVER['REMOTE_ADDR'] ?? '');
    return $bin === false ? null : $bin;
}

function zabelezi_pokusaj(): void
{
    $ip = ip_binarno();
    if ($ip !== null) {
        upit('INSERT INTO pokusaji_prijave (ip, kada) VALUES (?, ?)', [$ip, sada()]);
    }
}

function previse_pokusaja(): bool
{
    $ip = ip_binarno();
    if ($ip === null) {
        return false;
    }
    upit('DELETE FROM pokusaji_prijave WHERE kada < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $od = date('Y-m-d H:i:s', time() - (int)pod('bezbednost.period_minuta', 15) * 60);
    $broj = (int)vrednost('SELECT COUNT(*) FROM pokusaji_prijave WHERE ip = ? AND kada > ?', [$ip, $od]);
    return $broj >= (int)pod('bezbednost.max_pokusaja', 5);
}

/* -------------------------------------------------------------------------
   Tokeni: pozivnica i zaboravljena lozinka (jednokratni, 48 h)
   ------------------------------------------------------------------------- */

/** Pravi novi token (stari iste svrhe se poništavaju) i vraća ga u čitljivom obliku. */
function napravi_token(int $korisnik_id, string $svrha): string
{
    $token = bin2hex(random_bytes(32));
    $sati = (int)pod('bezbednost.token_sati', 48);
    upit('UPDATE tokeni SET iskoriscen = ? WHERE korisnik_id = ? AND iskoriscen IS NULL', [sada(), $korisnik_id]);
    upit('INSERT INTO tokeni (korisnik_id, svrha, token_hash, istice, kreirano) VALUES (?, ?, ?, ?, ?)',
        [$korisnik_id, $svrha, hash('sha256', $token), date('Y-m-d H:i:s', time() + $sati * 3600), sada()]);
    return $token;
}

/** Važeći (neiskorišćen, neistekao) token -> red sa korisnikom, ili null. */
function nadji_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return red('SELECT t.id, t.svrha, t.korisnik_id, k.ime, k.email
                FROM tokeni t JOIN korisnici k ON k.id = t.korisnik_id
                WHERE t.token_hash = ? AND t.iskoriscen IS NULL AND t.istice > ? AND k.aktivan = 1',
        [hash('sha256', $token), sada()]);
}

function iskoristi_token(int $token_id): void
{
    upit('UPDATE tokeni SET iskoriscen = ? WHERE id = ?', [sada(), $token_id]);
}

/* -------------------------------------------------------------------------
   CSRF
   ------------------------------------------------------------------------- */

function csrf_token(): string
{
    sesija_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_polje(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function proveri_csrf(): void
{
    sesija_start();
    $poslat = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    $ocekivan = $_SESSION['csrf'] ?? '';
    if ($ocekivan === '' || !is_string($poslat) || !hash_equals($ocekivan, $poslat)) {
        greska(419, 'Sesija je istekla ili je zahtev stigao sa druge strane. Osveži stranu i probaj ponovo.');
    }
}

/** Svaki POST mora imati ispravan CSRF token — poziva se jednom, u ruteru. */
function post(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return false;
    }
    proveri_csrf();
    return true;
}
