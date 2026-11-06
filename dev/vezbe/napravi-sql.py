"""
Pravi baza/vezbe-osnovne.sql (nova instalacija) i baza/izmene-005-vezbe.sql (postojeća baza)
iz dev/vezbe/lista.py.   python3 -I dev/vezbe/napravi-sql.py dev/vezbe/lista.py baza
"""
import sys, os, importlib.util
spec = importlib.util.spec_from_file_location('lista', sys.argv[1]); m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
def q(s): return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"
redovi = [f"({q(sr)}, {q(en)}, {q(tez)}, {q(op)}, {q(gl)}, {q(po)}, {q(tsr)}, {q(ten)}, NOW())" for sr, en, tez, op, gl, po, tsr, ten in m.L]
insert = ("INSERT INTO vezbe (naziv, naziv_en, tezina, oprema, misici, misici_pomocni, tehnika, tehnika_en, kreirano) VALUES\n  " + ",\n  ".join(redovi) +
          "\n-- Vežba koja već postoji (isti naziv) zadržava sve što je trener upisao; popunjava se samo ono što je prazno\n"
          "ON DUPLICATE KEY UPDATE naziv_en = IF(naziv_en = '', VALUES(naziv_en), naziv_en), tezina = IF(tezina = '', VALUES(tezina), tezina),\n"
          "  oprema = IF(oprema = '', VALUES(oprema), oprema), misici = IF(misici = '', VALUES(misici), misici),\n"
          "  misici_pomocni = IF(misici_pomocni = '', VALUES(misici_pomocni), misici_pomocni),\n"
          "  tehnika = IF(tehnika IS NULL OR tehnika = '', VALUES(tehnika), tehnika), tehnika_en = IF(tehnika_en IS NULL OR tehnika_en = '', VALUES(tehnika_en), tehnika_en);\n")
glava = (f"-- {len(redovi)} najčešćih vežbi u teretani: težina izvođenja, oprema, glavni i pomoćni mišići, kratka tehnika (sr/en).\n"
         "-- Ključevi opreme i mišića: app/vezbe.php. Spisak: dev/vezbe/lista.py, SQL: dev/vezbe/napravi-sql.py.\n"
         "-- YouTube linkove upisuje trener (Baza vežbi). Sve vežbe može da menja ili sakrije.\n\nSET NAMES utf8mb4;\n\n")
alter = ("ALTER TABLE vezbe\n"
         "    ADD COLUMN tezina VARCHAR(10) NOT NULL DEFAULT '' AFTER naziv_en,\n"
         "    ADD COLUMN oprema VARCHAR(160) NOT NULL DEFAULT '' AFTER tezina,\n"
         "    ADD COLUMN misici VARCHAR(160) NOT NULL DEFAULT '' AFTER oprema,\n"
         "    ADD COLUMN misici_pomocni VARCHAR(160) NOT NULL DEFAULT '' AFTER misici,\n"
         "    ADD COLUMN tehnika_en TEXT NULL AFTER tehnika;\n\n")
open(os.path.join(sys.argv[2], 'vezbe-osnovne.sql'), 'w', encoding='utf-8').write(
    "-- =============================================================================\n-- Baza vežbi za NOVU instalaciju (uvesti posle shema.sql i, po želji, podaci-primer.sql).\n"
    "-- =============================================================================\n" + glava + insert)
open(os.path.join(sys.argv[2], 'izmene-005-vezbe.sql'), 'w', encoding='utf-8').write(
    "-- =============================================================================\n-- Izmena 005 (novembar 2026): baza vežbi sa težinom, opremom i mišićima\n--\n"
    "-- Za POSTOJEĆU bazu: phpMyAdmin -> izabrati bazu -> SQL -> nalepiti -> Go. Uvozi se JEDNOM,\n"
    "-- posle izmena 001–004. Nova instalacija umesto ovoga uvozi baza/vezbe-osnovne.sql.\n"
    "-- =============================================================================\n" + glava + alter + insert)
print(len(redovi), 'vežbi')
