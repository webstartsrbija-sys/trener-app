#!/bin/sh
# Pravi isporuka/trener-app-DATUM.zip sa produkcionim fajlovima
# (bez config.php, dev/, testovi/, dokumentacije) + isporuka/shema.sql.
#   sh alati/napravi-zip.sh
set -e
cd "$(dirname "$0")/.."
D=$(date +%Y-%m-%d)
rm -rf isporuka && mkdir -p isporuka/trener-app
cp -r index.php .htaccess .user.ini assets strane delovi cron isporuka/trener-app/
mkdir -p isporuka/trener-app/app isporuka/trener-app/alati isporuka/trener-app/privatno/vodici isporuka/trener-app/kes isporuka/trener-app/baza
cp app/*.php app/.htaccess isporuka/trener-app/app/
rm -f isporuka/trener-app/app/config.php
cp alati/novi-trener.php alati/.htaccess isporuka/trener-app/alati/
cp privatno/.htaccess isporuka/trener-app/privatno/
cp kes/.htaccess isporuka/trener-app/kes/
cp baza/.htaccess baza/shema.sql isporuka/trener-app/baza/
cp baza/shema.sql isporuka/shema.sql
cp baza/podaci-primer.sql isporuka/podaci-primer.sql
cp baza/namirnice-usda.sql isporuka/namirnice-usda.sql
cp baza/vezbe-osnovne.sql isporuka/vezbe-osnovne.sql
(cd isporuka/trener-app && zip -qr "../trener-app-$D.zip" . -x '*.DS_Store')
rm -rf isporuka/trener-app
echo "Gotovo: isporuka/trener-app-$D.zip, isporuka/shema.sql, isporuka/podaci-primer.sql"
unzip -l "isporuka/trener-app-$D.zip" | tail -1
