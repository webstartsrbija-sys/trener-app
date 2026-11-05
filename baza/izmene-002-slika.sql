-- =============================================================================
-- Izmena 002 (novembar 2026): profilna slika
--
-- Za POSTOJEĆU bazu: phpMyAdmin -> izabrati bazu -> SQL -> nalepiti -> Go.
-- Uvozi se JEDNOM. Nova instalacija ovo ne treba (već je u shema.sql).
-- =============================================================================

ALTER TABLE korisnici
    ADD COLUMN slika VARCHAR(60) NULL AFTER ime;   -- ime fajla u privatno/slike/ (nasumično), NULL = inicijali
