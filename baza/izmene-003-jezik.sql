-- =============================================================================
-- Izmena 003 (novembar 2026): engleski jezik za strane klijente
--
-- Za POSTOJEĆU bazu: phpMyAdmin -> izabrati bazu -> SQL -> nalepiti -> Go.
-- Uvozi se JEDNOM. Nova instalacija ovo ne treba (već je u shema.sql).
-- =============================================================================

SET NAMES utf8mb4;

-- Jezik aplikacije i mejlova za klijenta: 'sr' ili 'en'
ALTER TABLE korisnici ADD COLUMN jezik CHAR(2) NOT NULL DEFAULT 'sr' AFTER slika;

-- Vodič za sve klijente ('') ili samo za one na srpskom / engleskom
ALTER TABLE vodici ADD COLUMN jezik CHAR(2) NOT NULL DEFAULT '' AFTER objavljen;

-- Engleski nazivi (prazno = koristi se srpski)
ALTER TABLE vezbe ADD COLUMN naziv_en VARCHAR(120) NOT NULL DEFAULT '' AFTER naziv;
ALTER TABLE namirnice ADD COLUMN naziv_en VARCHAR(120) NOT NULL DEFAULT '' AFTER naziv;

-- Engleski nazivi za vežbe i namirnice iz početne baze (ako postoje)
UPDATE vezbe SET naziv_en = CASE naziv
    WHEN 'Čučanj' THEN 'Squat'
    WHEN 'Bench press' THEN 'Bench press'
    WHEN 'Rumunsko mrtvo' THEN 'Romanian deadlift'
    WHEN 'Lat mašina' THEN 'Lat pulldown'
    WHEN 'Veslanje sajla' THEN 'Seated cable row'
    WHEN 'Hip thrust' THEN 'Hip thrust'
    WHEN 'Lateralno letenje' THEN 'Lateral raise'
    WHEN 'Bugarski čučanj' THEN 'Bulgarian split squat'
    WHEN 'Potisak iznad glave' THEN 'Overhead press'
    WHEN 'Pregib za biceps' THEN 'Biceps curl'
    WHEN 'Ekstenzija za triceps' THEN 'Triceps extension'
    WHEN 'Leg curl' THEN 'Leg curl'
    ELSE naziv_en END
WHERE naziv_en = '';

UPDATE namirnice SET naziv_en = CASE naziv
    WHEN 'Pileća prsa (sirova)' THEN 'Chicken breast (raw)'
    WHEN 'Junetina 5% (sirova)' THEN 'Lean beef 5% (raw)'
    WHEN 'Losos (sirov)' THEN 'Salmon (raw)'
    WHEN 'Tunjevina u vodi' THEN 'Tuna in water'
    WHEN 'Jaja' THEN 'Eggs'
    WHEN 'Grčki jogurt 2%' THEN 'Greek yogurt 2%'
    WHEN 'Posni sir' THEN 'Cottage cheese'
    WHEN 'Ovsene pahuljice' THEN 'Rolled oats'
    WHEN 'Pirinač (sirov)' THEN 'Rice (raw)'
    WHEN 'Krompir (sirov)' THEN 'Potatoes (raw)'
    WHEN 'Integralni hleb' THEN 'Wholegrain bread'
    WHEN 'Banana' THEN 'Banana'
    WHEN 'Jabuka' THEN 'Apple'
    WHEN 'Maslinovo ulje' THEN 'Olive oil'
    WHEN 'Kikiriki puter' THEN 'Peanut butter'
    ELSE naziv_en END
WHERE naziv_en = '';
