# Tomislav Coaching — aplikacija za trenera

Web aplikacija za personalnog trenera i njegove klijente: program treninga, check-in, ishrana, napredak, istorija, vodiči i članarina. Napravljena je po prototipu „Tomislav Coaching“, koji je izvor istine za ekrane, tekstove i dizajn.

- **Tehnologija:** PHP 8.1+ i MariaDB 10.11, bez frameworka i bez Composera. Stil je isti kao u webstart-cms-core: PDO, pripremljeni upiti, `config.php` van gita.
- **Hosting:** cPanel (Mint), subdomen `trener.webstartsrbija.rs`. Adresa se čita samo iz `app/config.php`.
- **Mejl:** SMTP klijent sa sajta (`app/smtp.php`), sekcija `smtp` u configu.
- **Izgled:** mobile-first, jedna kolona do 720 px, donja navigacija, svetla i tamna tema, fontovi Barlow i Barlow Condensed (lokalno, licenca OFL). PWA manifest i ikonice omogućavaju „Dodaj na početni ekran“, bez offline režima.

Postavljanje na server: **[ISPORUKA.md](ISPORUKA.md)** · Uputstvo za trenera: **[UPUTSTVO-TRENER.md](UPUTSTVO-TRENER.md)**

## Struktura

```
index.php              jedina ulazna tačka (ruter); .htaccess šalje sve ovde, osim assets/
app/                   jezgro: osnova (baza, datumi, ispis), auth (prijava, tokeni, CSRF),
                       klijenti, program, sesije, napredak, ishrana, mejl, smtp
strane/javno/          prijava, zaboravljena lozinka, postavljanje lozinke, manifest
strane/klijent/        trening (+ api-trening za autosave), ishrana, istorija, napredak, vodiči
strane/trener/         početna, klijenti, detalj klijenta (+ _program), vežbe, ishrana, vodiči
delovi/                zaglavlje, podnožje (donja navigacija), istorija i napredak (zajedničko)
assets/                css, js, fontovi, ikonice
cron/dnevno.php        dnevni rezime treneru i mejlovi o članarini (samo iz komandne linije)
alati/                 novi-trener.php (nalog trenera), napravi-zip.sh (isporuka)
baza/                  shema.sql, podaci-primer.sql
privatno/vodici/       otpremljeni PDF-ovi; servira ih PHP samo prijavljenima
dev/, testovi/         lokalni razvoj i Playwright test; ne idu u ZIP
```

## Kako radi „živ“ program

Trener menja program kad god hoće (tabele `programi`, `treninzi`, `trening_vezbe`, `plan_serije`). Kad klijent prvi put nešto upiše u trening, pravi se **sesija** sa snimkom tadašnjeg plana (`sesije`, `sesija_vezbe`, `sesija_serije`). Istorija zato uvek prikazuje šta je tada bilo planirano. Brisanje treninga ili vežbe ne briše istoriju, jer su veze `ON DELETE SET NULL`.

- Sesija važi za tekuću nedelju. Sledeće nedelje isti trening počinje iz početka.
- Autosave šalje unos 0,8 s posle kucanja, kao i kad se telefon zaključa (`sendBeacon`). Zahtevi idu jedan po jedan, a red klijenta se zaključava u bazi (`FOR UPDATE`), pa ne mogu da nastanu duple sesije.
- **Šablon** je program bez klijenta (`programi.klijent_id IS NULL`).

## Baza namirnica

`baza/namirnice-usda.sql` ima 223 namirnice (kcal, P, UH, M na 100 g) iz USDA FoodData Central, SR Legacy (https://fdc.nal.usda.gov). Ti podaci su javno vlasništvo (CC0), sa srpskim i engleskim nazivom. Spisak i nazivi su u `dev/usda/lista.py`, a SQL se ponovo pravi skriptom `dev/usda/napravi-sql.py` (uputstvo je u zaglavlju skripte). Kolona `namirnice.izvor` čuva USDA ID. Trener sve vrednosti može da menja u aplikaciji.

Podaci sa tablicakalorija.com namerno nisu preuzeti, jer su to tuđi uslovi korišćenja i tuđa baza.

## Jezik (srpski / engleski)

Klijentski deo je preveden na engleski za strane klijente. Tekst u kodu je na srpskom i prolazi kroz `t('…')`, a prevod je u `app/jezik/en.php`. Jezik klijenta se čuva na nalogu (`korisnici.jezik`): bira ga trener ili sam klijent dugmetom EN/SR. Mejlovi klijentu idu na njegovom jeziku. Trenerov deo je samo na srpskom. Posle dodavanja novog teksta pokreni `php dev/proveri-prevode.php`, koji ispisuje tekstove bez prevoda.

## Bezbednost

- **Uloge:** postoje dve, `trener` i `klijent`, i zavise samo od naloga. Prekidača iz prototipa nema.
- **Vlasništvo:** klijentove rute ID klijenta uzimaju **isključivo iz sesije**. Svaki ID iz forme (trening, serija, stavka ishrane) proverava se na serveru da pripada baš tom klijentu, i podmetnut ID se ignoriše. Trenerove rute traže ulogu `trener` (u suprotnom 403).
- **CSRF:** token stoji na svakoj formi i u svakom `fetch`-u, a jedan proverač u `post()` pokriva sve POST zahteve.
- **Izlaz i lozinke:** sav izlaz ide kroz `e()`. Lozinke se čuvaju sa `password_hash`.
- **Tokeni:** jednokratni su i važe 48 h. U bazi stoji samo SHA-256 heš tokena.
- **Prijava:** pokušaji su ograničeni po IP adresi, kao u CMS-u. „Zapamti me“ traje 30 dana i koristi selektor i validator, a validator se menja pri svakom korišćenju.
- **Zaglavlja:** CSP (`script-src 'self'`, bez inline skripti), `X-Frame-Options`, `nosniff`.
- **Direktan pristup:** iz pregledača se može dobiti samo `assets/`. Sve ostalo prolazi kroz ruter, koji za nepoznate adrese vraća 404.

## Lokalni razvoj

Potrebni su MariaDB 10.11 (baza `trener_test`, korisnik `trener`/`trener`), PHP 8.1+ i Node sa Playwrightom.

```bash
sh dev/pokreni.sh --nova
```

Ova komanda puni bazu iz `shema.sql` i `podaci-primer.sql`, pravi trenera `trener@primer.rs` / `trener-lozinka-1` i pokreće aplikaciju na http://127.0.0.1:8080.

Klijenti iz primera: `ana@`, `nikola@`, `milica@` i `stefan@primer.rs`, sa lozinkom `proba1234`. Mejlovi se u razvoju ne šalju, nego se upisuju u `kes/mejlovi/`.

Test, dok server radi u drugom terminalu:

```bash
TC_LOG=server.log node testovi/e2e.js
```

Test prolazi ceo tok na 390 px i 1440 px: trener pravi klijenta, klijent postavlja lozinku iz mejla, popunjava trening (autosave) i check-in, a trener vidi upis. Proverava i izolaciju (klijent A ne može da otvori ni da menja podatke klijenta B, ni direktnim URL-om ni podmetnutim ID-jem), CSRF, odsustvo PHP i JS grešaka i horizontalnog skrola. Snimci ekrana se čuvaju u `testovi/rezultati/`.

Primer podataka se pravi iznova sa `php dev/napravi-primer.php > baza/podaci-primer.sql`. Datumi u njemu su relativni, pa primer uvek izgleda sveže.

## Izvan prve verzije (mesto je ostavljeno)

- **Online plaćanje:** `produzenja.placanje_id` i tabela `placanja`, opisana u `shema.sql`.
- **Predavanja uživo:** tabela `predavanja` (link ka Zoom/Meet/YouTube Live i raspored). Snimci mogu već sada da idu kao vodič sa linkom.
- **Fotografije napretka i obimi:** tabele `obimi` i `fotografije`. Fajlovi bi išli u `privatno/foto/` i servirali bi se kao PDF vodiči, uz proveru vlasništva.
- **Push i offline režim:** tabela `push_pretplate` i service worker uz postojeći manifest.
