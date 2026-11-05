#!/bin/sh
# Lokalno pokretanje: MariaDB 10.11 mora da radi, baza trener_test i korisnik trener/trener.
#   sh dev/pokreni.sh            -> http://127.0.0.1:8080
#   sh dev/pokreni.sh --nova     -> prvo obriše i napuni bazu iz shema.sql + podaci-primer.sql
set -e
cd "$(dirname "$0")/.."
[ -f app/config.php ] || printf "<?php\nreturn require __DIR__ . '/../dev/config.php';\n" > app/config.php
if [ "$1" = "--nova" ]; then
  mariadb -u trener -ptrener -e "DROP DATABASE IF EXISTS ${TC_BAZA:-trener_test}; CREATE DATABASE ${TC_BAZA:-trener_test} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mariadb -u trener -ptrener "${TC_BAZA:-trener_test}" < baza/shema.sql
  mariadb -u trener -ptrener "${TC_BAZA:-trener_test}" < baza/podaci-primer.sql
  php alati/novi-trener.php trener@primer.rs "Tomislav" "trener-lozinka-1"
  rm -rf kes/mejlovi
fi
# output_buffering=0 kao na cPanel-u, da se greške sa sesijom vide i lokalno
exec php -d output_buffering=0 -d display_errors=stderr -d error_reporting=-1 -S 127.0.0.1:${PORT:-8080} dev/ruter.php
