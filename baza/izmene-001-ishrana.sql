-- =============================================================================
-- Izmena 001 (novembar 2026): obroci iz baze namirnica + primer za jednog klijenta
--
-- Za POSTOJEĆU bazu na serveru: phpMyAdmin -> izabrati bazu -> SQL -> nalepiti
-- ovaj fajl -> Go. Uvozi se JEDNOM. Nova instalacija ovo ne treba (već je u shema.sql).
-- =============================================================================

SET NAMES utf8mb4;

-- Primer dana može biti za sve klijente (NULL) ili samo za jednog
ALTER TABLE primeri_ishrane
    ADD COLUMN klijent_id INT UNSIGNED NULL AFTER id,
    ADD KEY ix_primeri_klijent (klijent_id),
    ADD CONSTRAINT fk_primeri_klijent FOREIGN KEY (klijent_id) REFERENCES klijenti(id) ON DELETE CASCADE;

-- Namirnice u obroku. Vrednosti su snimak (izmena namirnice u bazi se
-- primenjuje kad trener sledeći put sačuva primer).
CREATE TABLE primeri_stavke (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    obrok_id      INT UNSIGNED NOT NULL,
    namirnica_id  INT UNSIGNED NULL,
    redosled      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    naziv         VARCHAR(120) NOT NULL,
    grami         SMALLINT UNSIGNED NOT NULL,
    kcal          DECIMAL(7,1) NOT NULL DEFAULT 0,
    p             DECIMAL(6,1) NOT NULL DEFAULT 0,
    uh            DECIMAL(6,1) NOT NULL DEFAULT 0,
    m             DECIMAL(6,1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_ps_obrok (obrok_id, redosled),
    CONSTRAINT fk_pst_obrok FOREIGN KEY (obrok_id) REFERENCES primeri_obroci(id) ON DELETE CASCADE,
    CONSTRAINT fk_pst_namirnica FOREIGN KEY (namirnica_id) REFERENCES namirnice(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
