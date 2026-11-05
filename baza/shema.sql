-- =============================================================================
-- Tomislav Coaching — šema baze (MariaDB 10.11, utf8mb4)
--
-- Uvoz: cPanel -> phpMyAdmin -> izabrati bazu -> Uvoz -> ovaj fajl.
-- Ili iz komandne linije:  mariadb -u KORISNIK -p IME_BAZE < shema.sql
--
-- Princip "živog programa": trener menja programi/treninzi/trening_vezbe/
-- plan_serije kad god hoće. Kad klijent počne trening, pravi se SESIJA koja
-- kopira (snimak) tadašnji plan u sesija_vezbe/sesija_serije. Zato izmena ili
-- brisanje programa nikad ne menja istoriju: sesije ne zavise od programa,
-- veze ka programu su samo "ON DELETE SET NULL".
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Nalozi i prijava
-- -----------------------------------------------------------------------------

CREATE TABLE korisnici (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uloga             ENUM('trener','klijent') NOT NULL,
    email             VARCHAR(190) NOT NULL,
    ime               VARCHAR(120) NOT NULL,
    lozinka_hash      VARCHAR(255) NULL,              -- NULL dok klijent ne postavi lozinku
    aktivan           TINYINT(1) NOT NULL DEFAULT 1,  -- 0 = arhiviran, ne može da se prijavi
    poslednja_prijava DATETIME NULL,
    kreirano          DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_korisnici_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jednokratni linkovi: pozivnica i zaboravljena lozinka. U bazi je samo
-- SHA-256 heš tokena — ko ukrade bazu, ne može da iskoristi link.
CREATE TABLE tokeni (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    korisnik_id  INT UNSIGNED NOT NULL,
    svrha        ENUM('poziv','reset') NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    istice       DATETIME NOT NULL,
    iskoriscen   DATETIME NULL,
    kreirano     DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tokeni_hash (token_hash),
    KEY ix_tokeni_korisnik (korisnik_id),
    CONSTRAINT fk_tokeni_korisnik FOREIGN KEY (korisnik_id) REFERENCES korisnici(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Zapamti me" 30 dana: kolačić nosi selektor + validator, u bazi je heš
-- validatora. Ne oslanjamo se na PHP sesiju, jer deljeni hosting briše
-- stare sesije posle ~24 minuta.
CREATE TABLE zapamcene_prijave (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    korisnik_id     INT UNSIGNED NOT NULL,
    selektor        CHAR(24) NOT NULL,
    validator_hash  CHAR(64) NOT NULL,
    istice          DATETIME NOT NULL,
    kreirano        DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_zapamcene_selektor (selektor),
    KEY ix_zapamcene_korisnik (korisnik_id),
    CONSTRAINT fk_zapamcene_korisnik FOREIGN KEY (korisnik_id) REFERENCES korisnici(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ograničenje pokušaja prijave (isto kao u CMS-u: po IP adresi)
CREATE TABLE pokusaji_prijave (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip    VARBINARY(16) NOT NULL,
    kada  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_pokusaji_ip_kada (ip, kada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Klijenti i članarina
-- -----------------------------------------------------------------------------

CREATE TABLE klijenti (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    korisnik_id         INT UNSIGNED NOT NULL,
    cilj                VARCHAR(80) NOT NULL DEFAULT '',
    datum_pocetka       DATE NOT NULL,
    treninga_nedeljno   TINYINT UNSIGNED NOT NULL DEFAULT 3,
    dani_treninga       VARCHAR(20) NOT NULL DEFAULT '1,3,5',  -- ISO dani: 1 = ponedeljak … 7 = nedelja
    clanarina_do        DATE NULL,                              -- važi zaključno sa ovim datumom
    oznake              VARCHAR(255) NOT NULL DEFAULT '',       -- slobodne oznake, odvojene zarezom
    napomena            TEXT NULL,                              -- interna beleška trenera
    arhiviran           TINYINT(1) NOT NULL DEFAULT 0,
    -- Da se isti mejl ne pošalje dvaput: pamti se za koji datum isteka je poslat
    mejl_istice_za      DATE NULL,
    mejl_istekla_za     DATE NULL,
    kreirano            DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_klijenti_korisnik (korisnik_id),
    KEY ix_klijenti_arhiviran (arhiviran),
    CONSTRAINT fk_klijenti_korisnik FOREIGN KEY (korisnik_id) REFERENCES korisnici(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Istorija produženja (samo evidencija, bez online plaćanja)
CREATE TABLE produzenja (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    klijent_id   INT UNSIGNED NOT NULL,
    staro_do     DATE NULL,
    novo_do      DATE NOT NULL,
    iznos        DECIMAL(10,2) NULL,
    napomena     VARCHAR(255) NOT NULL DEFAULT '',
    -- Mesto za kasnije online plaćanje: ovde ide veza ka tabeli "placanja"
    placanje_id  INT UNSIGNED NULL,
    kreirano     DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_produzenja_klijent (klijent_id, kreirano),
    CONSTRAINT fk_produzenja_klijent FOREIGN KEY (klijent_id) REFERENCES klijenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Baza vežbi
-- -----------------------------------------------------------------------------

CREATE TABLE vezbe (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naziv     VARCHAR(120) NOT NULL,
    youtube   VARCHAR(255) NOT NULL DEFAULT '',   -- samo link (unlisted video), ništa se ne otprema
    tehnika   TEXT NULL,                          -- kratka napomena o tehnici
    aktivna   TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = sakrivena iz izbora, stari programi je i dalje prikazuju
    kreirano  DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vezbe_naziv (naziv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Program (živ, menja ga trener). Šablon = program bez klijenta.
-- -----------------------------------------------------------------------------

CREATE TABLE programi (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    klijent_id  INT UNSIGNED NULL,          -- NULL = šablon
    naziv       VARCHAR(120) NOT NULL DEFAULT '',
    kreirano    DATETIME NOT NULL,
    izmenjeno   DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_programi_klijent (klijent_id),   -- jedan aktivan program po klijentu (NULL se ne broji)
    CONSTRAINT fk_programi_klijent FOREIGN KEY (klijent_id) REFERENCES klijenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE treninzi (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id  INT UNSIGNED NOT NULL,
    redosled    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    naziv       VARCHAR(120) NOT NULL,
    fokus       VARCHAR(120) NOT NULL DEFAULT '',
    trajanje    VARCHAR(40)  NOT NULL DEFAULT '',
    napomena    TEXT NULL,                           -- napomena trenera
    PRIMARY KEY (id),
    KEY ix_treninzi_program (program_id, redosled),
    CONSTRAINT fk_treninzi_program FOREIGN KEY (program_id) REFERENCES programi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trening_vezbe (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trening_id  INT UNSIGNED NOT NULL,
    vezba_id    INT UNSIGNED NOT NULL,
    redosled    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    zagrevanje  VARCHAR(40) NOT NULL DEFAULT '',
    tempo       VARCHAR(40) NOT NULL DEFAULT '',
    pauza       VARCHAR(40) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY ix_tv_trening (trening_id, redosled),
    CONSTRAINT fk_tv_trening FOREIGN KEY (trening_id) REFERENCES treninzi(id) ON DELETE CASCADE,
    CONSTRAINT fk_tv_vezba   FOREIGN KEY (vezba_id)   REFERENCES vezbe(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plan serije: tekst, jer trener piše i "2×10", "do otkaza", "12 / noga"
CREATE TABLE plan_serije (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trening_vezba_id  INT UNSIGNED NOT NULL,
    redosled          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    kg                VARCHAR(20) NOT NULL DEFAULT '',
    pon               VARCHAR(20) NOT NULL DEFAULT '',
    rir               VARCHAR(10) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY ix_ps_tv (trening_vezba_id, redosled),
    CONSTRAINT fk_ps_tv FOREIGN KEY (trening_vezba_id) REFERENCES trening_vezbe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Izvršenja (istorija) — snimak plana + šta je klijent upisao
-- -----------------------------------------------------------------------------

CREATE TABLE sesije (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    klijent_id         INT UNSIGNED NOT NULL,
    trening_id         INT UNSIGNED NULL,               -- NULL kad trener obriše trening; istorija ostaje
    datum              DATE NOT NULL,
    status             ENUM('u_toku','zavrsena') NOT NULL DEFAULT 'u_toku',
    naziv              VARCHAR(120) NOT NULL,           -- snimak
    fokus              VARCHAR(120) NOT NULL DEFAULT '',
    trajanje           VARCHAR(40)  NOT NULL DEFAULT '',
    napomena_trenera   TEXT NULL,                       -- snimak napomene u trenutku treninga
    napomena_klijenta  TEXT NULL,
    zavrseno_u         DATETIME NULL,
    kreirano           DATETIME NOT NULL,
    izmenjeno          DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_sesije_klijent_datum (klijent_id, datum),
    KEY ix_sesije_trening (trening_id, datum),
    KEY ix_sesije_zavrseno (status, zavrseno_u),
    CONSTRAINT fk_sesije_klijent FOREIGN KEY (klijent_id) REFERENCES klijenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_sesije_trening FOREIGN KEY (trening_id) REFERENCES treninzi(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesija_vezbe (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sesija_id         INT UNSIGNED NOT NULL,
    trening_vezba_id  INT UNSIGNED NULL,
    vezba_id          INT UNSIGNED NULL,
    redosled          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    naziv             VARCHAR(120) NOT NULL,
    zagrevanje        VARCHAR(40) NOT NULL DEFAULT '',
    tempo             VARCHAR(40) NOT NULL DEFAULT '',
    pauza             VARCHAR(40) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY ix_sv_sesija (sesija_id, redosled),
    KEY ix_sv_vezba (vezba_id),
    CONSTRAINT fk_sv_sesija FOREIGN KEY (sesija_id) REFERENCES sesije(id) ON DELETE CASCADE,
    CONSTRAINT fk_sv_tv     FOREIGN KEY (trening_vezba_id) REFERENCES trening_vezbe(id) ON DELETE SET NULL,
    CONSTRAINT fk_sv_vezba  FOREIGN KEY (vezba_id) REFERENCES vezbe(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesija_serije (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sesija_vezba_id  INT UNSIGNED NOT NULL,
    plan_serija_id   INT UNSIGNED NULL,
    redosled         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    plan_kg          VARCHAR(20) NOT NULL DEFAULT '',   -- snimak plana
    plan_pon         VARCHAR(20) NOT NULL DEFAULT '',
    plan_rir         VARCHAR(10) NOT NULL DEFAULT '',
    kg               VARCHAR(20) NOT NULL DEFAULT '',   -- upisao klijent
    pon              VARCHAR(20) NOT NULL DEFAULT '',
    uradjeno         TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_ss_sv (sesija_vezba_id, redosled),
    CONSTRAINT fk_ss_sv FOREIGN KEY (sesija_vezba_id) REFERENCES sesija_vezbe(id) ON DELETE CASCADE,
    CONSTRAINT fk_ss_ps FOREIGN KEY (plan_serija_id) REFERENCES plan_serije(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Kako si danas?" — jedan check-in po sesiji
CREATE TABLE checkini (
    sesija_id    INT UNSIGNED NOT NULL,
    spavanje     TINYINT UNSIGNED NULL,
    hidratacija  TINYINT UNSIGNED NULL,
    hrana        TINYINT UNSIGNED NULL,
    stres        TINYINT UNSIGNED NULL,
    motivacija   TINYINT UNSIGNED NULL,
    bol          ENUM('Nema','Blago','Jako') NULL,
    izmenjeno    DATETIME NOT NULL,
    PRIMARY KEY (sesija_id),
    CONSTRAINT fk_checkini_sesija FOREIGN KEY (sesija_id) REFERENCES sesije(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Napredak
-- -----------------------------------------------------------------------------

CREATE TABLE tezine (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    klijent_id  INT UNSIGNED NOT NULL,
    datum       DATE NOT NULL,
    kg          DECIMAL(5,1) NOT NULL,
    izmenjeno   DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tezine_dan (klijent_id, datum),   -- jedan unos po danu
    CONSTRAINT fk_tezine_klijent FOREIGN KEY (klijent_id) REFERENCES klijenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Ishrana
-- -----------------------------------------------------------------------------

-- Vrednosti na 100 g
CREATE TABLE namirnice (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naziv  VARCHAR(120) NOT NULL,
    kcal   DECIMAL(6,1) NOT NULL DEFAULT 0,
    p      DECIMAL(5,1) NOT NULL DEFAULT 0,
    uh     DECIMAL(5,1) NOT NULL DEFAULT 0,
    m      DECIMAL(5,1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_namirnice_naziv (naziv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE primeri_ishrane (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naziv     VARCHAR(120) NOT NULL,
    redosled  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE primeri_obroci (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    primer_id  INT UNSIGNED NOT NULL,
    redosled   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    naziv      VARCHAR(80) NOT NULL,
    opis       VARCHAR(500) NOT NULL DEFAULT '',
    kcal       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    p          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    uh         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    m          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_po_primer (primer_id, redosled),
    CONSTRAINT fk_po_primer FOREIGN KEY (primer_id) REFERENCES primeri_ishrane(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dnevni okvir koji trener postavlja svakom klijentu
CREATE TABLE okvir_ishrane (
    klijent_id  INT UNSIGNED NOT NULL,
    kcal        SMALLINT UNSIGNED NOT NULL,
    p           SMALLINT UNSIGNED NOT NULL,
    uh          SMALLINT UNSIGNED NOT NULL,
    m           SMALLINT UNSIGNED NOT NULL,
    izmenjeno   DATETIME NOT NULL,
    PRIMARY KEY (klijent_id),
    CONSTRAINT fk_okvir_klijent FOREIGN KEY (klijent_id) REFERENCES klijenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Sastavi svoj dan": šta je klijent pojeo po danu. Vrednosti se čuvaju kao
-- snimak, da izmena namirnice u bazi ne promeni prošle dane.
CREATE TABLE ishrana_dnevnik (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    klijent_id  INT UNSIGNED NOT NULL,
    datum       DATE NOT NULL,
    naziv       VARCHAR(120) NOT NULL,
    grami       SMALLINT UNSIGNED NOT NULL,
    kcal        DECIMAL(7,1) NOT NULL,
    p           DECIMAL(6,1) NOT NULL,
    uh          DECIMAL(6,1) NOT NULL,
    m           DECIMAL(6,1) NOT NULL,
    kreirano    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_dnevnik_klijent_datum (klijent_id, datum),
    CONSTRAINT fk_dnevnik_klijent FOREIGN KEY (klijent_id) REFERENCES klijenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Vodiči (materijali)
-- -----------------------------------------------------------------------------

CREATE TABLE vodici (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naslov      VARCHAR(160) NOT NULL,
    kategorija  VARCHAR(60) NOT NULL DEFAULT '',
    opis        VARCHAR(500) NOT NULL DEFAULT '',
    tip         ENUM('link','pdf') NOT NULL DEFAULT 'link',
    url         VARCHAR(500) NOT NULL DEFAULT '',
    fajl        VARCHAR(120) NOT NULL DEFAULT '',   -- ime fajla u privatno/vodici/ (nasumično)
    fajl_ime    VARCHAR(190) NOT NULL DEFAULT '',   -- originalno ime, za preuzimanje
    velicina    INT UNSIGNED NOT NULL DEFAULT 0,
    redosled    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    objavljen   TINYINT(1) NOT NULL DEFAULT 1,
    kreirano    DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Pomoćno
-- -----------------------------------------------------------------------------

-- Da dnevni rezime ode tačno jednom dnevno, i kad se cron greškom pokrene dvaput
CREATE TABLE cron_dnevnik (
    datum     DATE NOT NULL,
    zadatak   VARCHAR(40) NOT NULL,
    izvrseno  DATETIME NOT NULL,
    izvestaj  TEXT NULL,
    PRIMARY KEY (datum, zadatak)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- MESTO ZA KASNIJE (ne pravi se u prvoj verziji, samo dogovorena struktura)
-- =============================================================================
--
-- Online plaćanje članarine:
--   CREATE TABLE placanja (id, klijent_id, iznos, valuta, provajder,
--     provajder_id, status ENUM('ceka','uspelo','odbijeno','vraceno'),
--     kreirano, potvrdjeno);
--   produzenja.placanje_id već postoji i pokazuje na ovu tabelu.
--
-- Predavanja uživo (samo link ka Zoom/Meet/YouTube Live + raspored):
--   CREATE TABLE predavanja (id, naslov, opis, pocinje DATETIME,
--     trajanje_min, link VARCHAR(500), snimak_vodic_id NULL -> vodici.id);
--
-- Fotografije napretka i obimi:
--   CREATE TABLE obimi (id, klijent_id, datum, struk, kukovi, grudi,
--     butina, nadlaktica, UNIQUE(klijent_id, datum));
--   CREATE TABLE fotografije (id, klijent_id, datum, ugao ENUM('napred',
--     'bok','leđa'), fajl) — fajlovi u privatno/foto/, servira ih PHP
--     uz proveru vlasništva, kao PDF vodiče.
--
-- Push notifikacije:
--   CREATE TABLE push_pretplate (id, korisnik_id, endpoint, p256dh, auth,
--     kreirano). Service worker se dodaje uz manifest.json.
