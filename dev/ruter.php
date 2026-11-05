<?php
/**
 * Ruter za PHP ugrađeni server (samo lokalno):
 *   php -S 127.0.0.1:8080 dev/ruter.php
 * Pušta samo assets/ kao statiku, sve ostalo ide u index.php — isto kao .htaccess.
 */
$put = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($put, '/assets/') && is_file(__DIR__ . '/..' . $put)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../index.php';
