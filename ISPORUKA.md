# Isporuka na server (Mint, cPanel)

## Šta se isporučuje

Napravi se komandom `sh alati/napravi-zip.sh`:

- **`isporuka/trener-app-DATUM.zip`:** produkcioni fajlovi, bez `config.php`, `dev/`, `testovi/` i dokumentacije.
- **`isporuka/shema.sql`:** prazna šema baze.
- **`isporuka/podaci-primer.sql`:** 4 klijenta iz prototipa, baza vežbi, namirnice, primeri ishrane i vodiči. Ovo je **samo za probu i prikaz.**

## Redosled koraka u cPanelu

1. **Subdomen.** Otvori *Domains → Domains → Create A New Domain* (u starijem cPanelu *Subdomains*). Upiši `trener.webstartsrbija.rs`. Za Document Root ostavi predlog, npr. `/home/KORISNIK/trener.webstartsrbija.rs`. Bolje je da bude van `public_html`.
2. **HTTPS.** Otvori *SSL/TLS Status*, označi `trener.webstartsrbija.rs` i klikni *Run AutoSSL*. Aplikacija sama preusmerava na https.
3. **PHP verzija.** U *MultiPHP Manager* za taj domen izaberi **PHP 8.1 ili noviji** (preporuka 8.3). U *Select PHP Version → Extensions* proveri da su uključeni `pdo_mysql`, `mbstring`, `fileinfo` i `openssl` (na Mintu su obično već uključeni).
4. **Baza.** U *MySQL Databases*:
   - napravi bazu `trener` (cPanel je imenuje `KORISNIK_trener`);
   - napravi korisnika `trener` sa jakom lozinkom (postaje `KORISNIK_trener`);
   - u delu *Add User To Database* daj tom korisniku **ALL PRIVILEGES** nad bazom.
5. **Šema.** U *phpMyAdmin* izaberi bazu, pa *Import → `shema.sql` → Go*. Za prikaz treneru možeš posle toga da uvezeš i `podaci-primer.sql`. Pre pravog rada te primer klijente arhiviraj ili bazu isprazni i ponovo uvezi samo šemu.
6. **Mejl nalog.** U *Email Accounts* napravi `trener@webstartsrbija.rs` (ili koristi postojeći `sajt@`). Iz *Connect Devices* prepiši SMTP server i port (obično `mail.webstartsrbija.rs`, 465, SSL).
7. **Upload.** U *File Manager* otvori Document Root iz koraka 1, uradi *Upload* ZIP-a, pa desni klik → *Extract*. U *Settings* uključi *Show Hidden Files*: moraju da se vide `.htaccess` i `.user.ini`.
8. **config.php.** U folderu `app/` kopiraj `config.primer.php` u `config.php` (*Copy*), pa ga otvori sa *Edit* i popuni (spisak je ispod).
9. **Nalog trenera.** Napravi ga na jedan od dva načina:
   - **Terminal** (ako je u cPanelu uključen):
     ```bash
     cd ~/trener.webstartsrbija.rs
     ```
     ```bash
     php alati/novi-trener.php tomislav@domen.rs "Tomislav Prezime"
     ```
     Lozinka se kuca dvaput i ne vidi se.
   - **Bez Terminala:** u *Cron Jobs* dodaj jednokratni zadatak za „sledeći minut“ sa komandom ispod, pa ga **odmah posle obriši**. Trener posle prve prijave neka promeni lozinku preko „Zaboravljena lozinka“, jer je lozinka bila upisana u cron.
     ```bash
     /usr/local/bin/php /home/KORISNIK/trener.webstartsrbija.rs/alati/novi-trener.php tomislav@domen.rs "Tomislav" PRIVREMENA-LOZINKA-123
     ```
10. **Cron za dnevni rezime i mejlove o članarini.** U *Cron Jobs → Add New Cron Job* upiši Minute `0`, Hour `20`, Day `*`, Month `*`, Weekday `*`. Komanda:
    ```bash
    /usr/local/bin/php /home/KORISNIK/trener.webstartsrbija.rs/cron/dnevno.php >> /home/KORISNIK/trener-cron.log 2>&1
    ```
    - `KORISNIK` zameni imenom cPanel naloga (vidi se gore desno u cPanelu ili u putanji u File Manageru).
    - Ako `/usr/local/bin/php` ne postoji ili je stara verzija, koristi tačnu verziju, npr. `/opt/cpanel/ea-php83/root/usr/bin/php`.
    - Cron radi po **vremenu servera**. Ako server nije na beogradskom vremenu (proveri u *Server Information*), pomeri sat, npr. `19` za UTC zimi i `18` za UTC leti.
    - Ručna proba bez slanja: `php cron/dnevno.php --proba`. Zadatak se iz pregledača ne može pokrenuti.
11. **Provera.**
    - Otvori `https://trener.webstartsrbija.rs` i prijavi se kao trener.
    - Napravi probnog klijenta sa svojom drugom adresom i proveri da je pozivnica stigla (i u nepoželjnoj pošti).
    - Ako mejl ne stigne, razlog je upisan u `kes/mejl-greske.log`.

## Šta se upisuje u `app/config.php`

| Ključ | Šta upisati |
|---|---|
| `baza.ime` / `baza.korisnik` | `KORISNIK_trener` / `KORISNIK_trener` (iz koraka 4) |
| `baza.lozinka` | lozinka korisnika baze iz koraka 4 |
| `baza.host` | `localhost` |
| `sajt.adresa` | `https://trener.webstartsrbija.rs`, bez `/` na kraju. Kad pređe na trenerov domen, menja se **samo ovo**. |
| `sajt.naziv`, `sajt.trener_ime` | `Tomislav Coaching`, `Tomislav` |
| `mejl.posiljalac` | `trener@webstartsrbija.rs`, ista adresa kao `smtp.korisnik` |
| `mejl.rezime_prima` | prazno, ako rezime ide na email trenerovog naloga; inače druga adresa |
| `smtp.host` / `port` / `sifrovanje` | `mail.webstartsrbija.rs` / `465` / `ssl` (ili `587` / `tls`) |
| `smtp.korisnik` / `smtp.lozinka` | mejl nalog iz koraka 6 i njegova lozinka |
| `clanarina.blokira_program` | `true`: posle isteka klijent vidi istoriju, napredak i vodiče, ali ne i program. `false`: vidi sve. |
| `clanarina.upozorenje_dana` / `produzenje_dana` | `3` / `30` |
| `paznja.dana_bez_treninga` | posle koliko dana bez treninga klijent postaje narandžast (podrazumevano `4`) |
| `bezbednost.*` | može da ostane kako jeste (5 pokušaja / 15 min, „zapamti me“ 30 dana, link 48 h) |

## Prelazak na trenerov domen (kasnije)

1. U cPanelu dodaj domen i usmeri ga na isti folder (ili premesti fajlove).
2. Pokreni AutoSSL.
3. U `config.php` promeni `sajt.adresa`, a po potrebi i `mejl.posiljalac` i `smtp`.

Ništa drugo nije vezano za adresu. Klijenti treba ponovo da se prijave na novoj adresi i ponovo da je dodaju na početni ekran.

## Rezervna kopija

U *Backup* (ili *JetBackup*) treba da budu baza `KORISNIK_trener` i folder `privatno/vodici/` (PDF-ovi). Ostalo je u gitu.
