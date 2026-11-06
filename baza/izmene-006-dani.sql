-- =============================================================================
-- Izmena 006 (novembar 2026): dan u nedelji za svaki trening
--
-- Za POSTOJEĆU bazu: phpMyAdmin -> izabrati bazu -> SQL -> nalepiti -> Go.
-- Uvozi se JEDNOM, posle izmena 001–005. Nova instalacija ovo ne treba.
-- =============================================================================

-- 1 = ponedeljak … 7 = nedelja; NULL = trening nije vezan za dan (radi se redom)
ALTER TABLE treninzi ADD COLUMN dan TINYINT UNSIGNED NULL AFTER redosled;
