<?php
/**
 * Profilne slike. Slika se smanjuje na 320×320 (kvadrat, sredina), okreće
 * po EXIF-u (telefoni) i čuva kao JPEG u privatno/slike/ pod nasumičnim imenom.
 * Original se ne čuva. Servira je PHP, samo prijavljenima (strane/slika.php).
 */

declare(strict_types=1);

const SLIKA_DIR = KOREN . '/privatno/slike';
const SLIKA_MAX_MB = 8;
const SLIKA_STRANICA = 320;

/** Prima otpremljenu sliku iz $_FILES[$polje]; vraća ime novog fajla ili poruku o grešci. */
function primi_profilnu(string $polje = 'slika'): array
{
    $f = $_FILES[$polje] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['greska' => 'Izaberi sliku.'];
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > SLIKA_MAX_MB * 1048576) {
        return ['greska' => 'Slika je veća od ' . SLIKA_MAX_MB . ' MB.'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return ['greska' => 'Otpremanje nije uspelo. Probaj ponovo.'];
    }
    if (!function_exists('imagecreatetruecolor')) {
        return ['greska' => 'Na serveru nije uključen PHP dodatak GD (cPanel → Select PHP Version → gd).'];
    }
    $tip = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $izvor = match ($tip) {
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'image/png'  => @imagecreatefrompng($f['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        default      => false,
    };
    if (!$izvor) {
        return ['greska' => 'Slika mora biti JPG, PNG ili WebP.'];
    }
    // Telefoni snimaju "naopako" i upisuju ugao u EXIF
    if ($tip === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($f['tmp_name'])['Orientation'] ?? 1);
        $ugao = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($ugao) {
            $izvor = imagerotate($izvor, $ugao, 0);
        }
    }
    $w = imagesx($izvor);
    $h = imagesy($izvor);
    $strana = min($w, $h);
    $cilj = imagecreatetruecolor(SLIKA_STRANICA, SLIKA_STRANICA);
    imagefill($cilj, 0, 0, imagecolorallocate($cilj, 255, 255, 255));   // providan PNG dobija belu pozadinu
    imagecopyresampled($cilj, $izvor, 0, 0, intdiv($w - $strana, 2), intdiv($h - $strana, 2), SLIKA_STRANICA, SLIKA_STRANICA, $strana, $strana);
    if (!is_dir(SLIKA_DIR)) {
        mkdir(SLIKA_DIR, 0750, true);
    }
    $ime = bin2hex(random_bytes(16)) . '.jpg';
    $ok = imagejpeg($cilj, SLIKA_DIR . '/' . $ime, 85);
    imagedestroy($izvor);
    imagedestroy($cilj);
    return $ok ? ['ime' => $ime] : ['greska' => 'Slika ne može da se sačuva (prava na folder privatno/slike).'];
}

/** Postavlja (ili briše, kad je $ime null) sliku korisnika; stari fajl se briše. */
function postavi_profilnu(int $korisnik_id, ?string $ime): void
{
    $staro = vrednost('SELECT slika FROM korisnici WHERE id = ?', [$korisnik_id]);
    upit('UPDATE korisnici SET slika = ? WHERE id = ?', [$ime, $korisnik_id]);
    if (is_string($staro) && preg_match('/^[a-f0-9]{32}\.jpg$/', $staro)) {
        @unlink(SLIKA_DIR . '/' . $staro);
    }
}

/** Krug sa slikom ili inicijalima. $klasa: '' (44 px), 'sm' (zaglavlje), 'lg' (profil). */
function avatar(int $korisnik_id, string $ime, ?string $slika, string $klasa = ''): string
{
    $k = trim('av ' . $klasa);
    if ($slika) {
        return '<span class="' . $k . '"><img src="/slika/' . $korisnik_id . '?v=' . e(substr($slika, 0, 8))
            . '" alt="" width="88" height="88" loading="lazy" decoding="async"></span>';
    }
    return '<span class="' . $k . '">' . e(inicijali($ime)) . '</span>';
}
