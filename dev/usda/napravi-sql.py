"""
Pravi baza/namirnice-usda.sql i baza/izmene-004-namirnice.sql iz USDA FoodData Central (SR Legacy).

Izvor: U.S. Department of Agriculture, Agricultural Research Service. FoodData Central, SR Legacy (2018).
https://fdc.nal.usda.gov — podaci su u javnom vlasništvu (CC0 1.0), slobodni za upotrebu.

Upotreba (podatke prvo preuzeti i raspakovati u zaseban folder):
  curl -O https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_sr_legacy_food_csv_2018-04.zip
  unzip FoodData_Central_sr_legacy_food_csv_2018-04.zip -d /tmp/usda
  python3 -I dev/usda/napravi-sql.py /tmp/usda/FoodData_Central_sr_legacy_food_csv_2018-04 dev/usda/lista.py baza

Spisak namirnica i srpski/engleski nazivi su u dev/usda/lista.py (regex na USDA opis).
"""
import csv, sys, re, importlib.util, os

D, LISTA, IZLAZ = sys.argv[1], sys.argv[2], sys.argv[3]
spec = importlib.util.spec_from_file_location('lista', LISTA)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
foods = {r['fdc_id']: r['description'] for r in csv.DictReader(open(D + '/food.csv', encoding='utf-8'))}
nut = {}
for r in csv.DictReader(open(D + '/food_nutrient.csv', encoding='utf-8')):
    if r['nutrient_id'] in ('1008', '1003', '1004', '1005'):   # kcal, protein, masti, UH
        nut.setdefault(r['fdc_id'], {})[r['nutrient_id']] = float(r['amount'])

def q(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"

redovi = []
for sr, en, rx in m.L:
    r = re.compile(rx)
    hits = sorted((len(d), i) for i, d in foods.items() if r.search(d))
    if not hits:
        sys.exit('Nema u USDA bazi: ' + sr)
    i = hits[0][1]
    n = nut[i]
    redovi.append(f"({q(sr)}, {q(en)}, {round(n.get('1008', 0), 1)}, {round(n.get('1003', 0), 1)}, {round(n.get('1005', 0), 1)}, {round(n.get('1004', 0), 1)}, {q('USDA FDC ' + i)})")

insert = ("INSERT INTO namirnice (naziv, naziv_en, kcal, p, uh, m, izvor) VALUES\n  " + ",\n  ".join(redovi) + "\n"
          "-- Namirnica koja već postoji (isti srpski naziv) se NE menja — trenerove izmene ostaju;\n"
          "-- dobija samo engleski naziv i izvor ako ih nema.\n"
          "ON DUPLICATE KEY UPDATE naziv_en = IF(naziv_en = '', VALUES(naziv_en), naziv_en), izvor = IF(izvor = '', VALUES(izvor), izvor);\n")
zaglavlje = ("-- " + str(len(redovi)) + " namirnica, vrednosti na 100 g (kcal, protein, ugljeni hidrati, masti).\n"
             "-- Izvor: USDA FoodData Central, SR Legacy (2018), https://fdc.nal.usda.gov — javno vlasništvo (CC0 1.0).\n"
             "-- Srpski i engleski nazivi: dev/usda/lista.py. Generisano: dev/usda/napravi-sql.py\n"
             "-- Trener sve namirnice može da menja ili briše u aplikaciji (Ishrana -> Baza namirnica).\n\n"
             "SET NAMES utf8mb4;\n\n")
open(os.path.join(IZLAZ, 'namirnice-usda.sql'), 'w', encoding='utf-8').write(
    "-- =============================================================================\n-- Baza namirnica za NOVU instalaciju (uvesti posle shema.sql).\n"
    "-- =============================================================================\n" + zaglavlje + insert)
open(os.path.join(IZLAZ, 'izmene-004-namirnice.sql'), 'w', encoding='utf-8').write(
    "-- =============================================================================\n-- Izmena 004 (novembar 2026): baza namirnica sa kalorijama + izvor podatka\n--\n"
    "-- Za POSTOJEĆU bazu: phpMyAdmin -> izabrati bazu -> SQL -> nalepiti -> Go. Uvozi se JEDNOM,\n"
    "-- posle izmena 001–003. Nova instalacija umesto ovoga uvozi baza/namirnice-usda.sql.\n"
    "-- =============================================================================\n" + zaglavlje +
    "-- Odakle je namirnica (prazno = upisao trener)\nALTER TABLE namirnice ADD COLUMN izvor VARCHAR(40) NOT NULL DEFAULT '' AFTER m;\n\n" + insert)
print(len(redovi), 'namirnica')
