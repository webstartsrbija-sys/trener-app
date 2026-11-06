/**
 * Playwright test celog toka, na 390 px (telefon) i 1440 px (računar).
 *
 *   sh dev/pokreni.sh --nova      (u drugom terminalu)
 *   node testovi/e2e.js
 *
 * Proverava:
 *  1. trener pravi klijenta -> pozivni mejl (kes/mejlovi/) -> klijent postavlja lozinku
 *  2. klijent popunjava trening (autosave) i check-in, završava trening
 *  3. trener vidi upis i check-in
 *  4. klijent A NE MOŽE da vidi/menja podatke klijenta B: direktan URL i podmetnut ID u formi
 *  5. nema PHP grešaka, JS grešaka ni horizontalnog skrola ni na jednoj strani
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const BAZA = process.env.TC_ADRESA || 'http://127.0.0.1:8080';
const KOREN = path.join(__dirname, '..');
const MEJLOVI = path.join(KOREN, 'kes', 'mejlovi');
const SLIKE = path.join(__dirname, 'rezultati');
fs.mkdirSync(SLIKE, { recursive: true });

const TRENER = { email: 'trener@primer.rs', lozinka: 'trener-lozinka-1' };
const ANA = { email: 'ana@primer.rs', lozinka: 'proba1234' };   // klijent B iz primera
let greske = [];
let provera = 0;
function ok(uslov, opis) {
  provera++;
  if (!uslov) { greske.push(opis); console.log('  ✗ ' + opis); } else console.log('  ✓ ' + opis);
}

const PHP_GRESKA = /(Fatal error|Warning:|Notice:|Deprecated:|Parse error|Uncaught|Stack trace)/;

async function novaStrana(browser, sirina, locale = 'sr-RS') {
  const ctx = await browser.newContext({ viewport: { width: sirina, height: sirina < 500 ? 844 : 900 }, deviceScaleFactor: 1, locale });
  const p = await ctx.newPage();
  p.on('pageerror', (e) => greske.push(`JS greška (${sirina}px) ${p.url()}: ${e.message}`));
  // Namerni 403/404/419 u testu izolacije nisu greške strane
  p.on('console', (m) => { if (m.type() === 'error' && !p.ocekujeOdbijanje) greske.push(`Konzola (${sirina}px) ${p.url()}: ${m.text()}`); });
  p.on('dialog', (d) => d.accept());
  return { ctx, p };
}

/** Posle svake strane: PHP greške u HTML-u, horizontalni skrol. */
async function proveriStranu(p, sirina, ime) {
  const html = await p.content();
  ok(!PHP_GRESKA.test(html), `${ime} (${sirina}px): bez PHP grešaka`);
  const skrol = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  ok(skrol <= 0, `${ime} (${sirina}px): bez horizontalnog skrola (višak ${skrol}px)`);
  await p.screenshot({ path: path.join(SLIKE, `${sirina}-${ime}.png`), fullPage: true });
}

async function prijava(p, k) {
  await p.goto(BAZA + '/prijava');
  await p.fill('input[name=email]', k.email);
  await p.fill('input[name=lozinka]', k.lozinka);
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Prijavi se")')]);
}

/** Izbor namirnice kroz pretragu (select[data-pretraga] -> polje + lista). */
async function izaberiNamirnicu(okvir, upit, tekst) {
  const polje = okvir.locator('.birac input[type=search]').first();
  await polje.fill(upit);
  const tacno = new RegExp('^' + tekst.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$');
  await okvir.locator('.birac-opcija > span').filter({ hasText: tacno }).first().click();
}

function poslednjiMejl(za) {
  if (!fs.existsSync(MEJLOVI)) return null;
  const f = fs.readdirSync(MEJLOVI).sort().reverse().map((x) => fs.readFileSync(path.join(MEJLOVI, x), 'utf8')).find((t) => t.includes('Za: ' + za));
  return f || null;
}

async function tok(browser, sirina) {
  console.log(`\n=== ${sirina}px ===`);
  const oznaka = Date.now().toString(36) + sirina;
  const novi = { ime: 'Jovana Test ' + sirina, email: `jovana.${oznaka}@primer.rs`, lozinka: 'jovana-lozinka-1' };

  /* ---- 1. Trener pravi klijenta ---- */
  const T = await novaStrana(browser, sirina);
  await prijava(T.p, TRENER);
  ok(T.p.url().endsWith('/trener'), 'trener posle prijave stiže na /trener');
  await proveriStranu(T.p, sirina, 'trener-pocetna');
  await T.p.goto(BAZA + '/trener/klijenti');
  await proveriStranu(T.p, sirina, 'trener-klijenti');
  const nivoi = await T.p.$$eval('a.client', (a) => a.map((x) => ({ bad: 0, warn: 1, ok: 2 })[x.querySelector('.chip').classList[1]]));
  ok(nivoi.every((n, i) => i === 0 || nivoi[i - 1] <= n) && nivoi[0] === 0, 'lista je sortirana po pažnji (crveno, narandžasto, zeleno): ' + nivoi.join(','));
  await T.p.click('button:has-text("+ Novi klijent")');
  await T.p.fill('#novi-klijent input[name=ime]', novi.ime);
  await T.p.fill('#novi-klijent input[name=email]', novi.email);
  await T.p.selectOption('#novi-klijent select[name=sablon_id]', { label: /Šablon/ }).catch(async () => {
    const o = await T.p.$$eval('#novi-klijent select[name=sablon_id] option', (x) => x.map((y) => y.value));
    await T.p.selectOption('#novi-klijent select[name=sablon_id]', o[1]);
  });
  await Promise.all([T.p.waitForNavigation(), T.p.click('#novi-klijent button:has-text("Napravi nalog")')]);
  const novaAdresa = T.p.url();
  const noviId = (novaAdresa.match(/klijent\/(\d+)/) || [])[1];
  ok(!!noviId, 'klijent je napravljen: ' + novaAdresa);
  ok(await T.p.isVisible('text=Mejl je poslat'), 'trener vidi potvrdu i link pozivnice');
  await proveriStranu(T.p, sirina, 'trener-klijent-program');

  /* ---- 2. Klijent postavlja lozinku iz mejla ---- */
  const mejl = poslednjiMejl(novi.email);
  ok(!!mejl, 'pozivni mejl je "poslat" (kes/mejlovi)');
  const link = (mejl.match(/https?:\/\/\S+\/lozinka\?t=[a-f0-9]{64}/) || [])[0];
  ok(!!link, 'mejl sadrži link za lozinku');
  const K = await novaStrana(browser, sirina);
  await K.p.goto(link);
  await proveriStranu(K.p, sirina, 'klijent-lozinka');
  await K.p.fill('input[name=lozinka]', novi.lozinka);
  await K.p.fill('input[name=lozinka2]', novi.lozinka);
  await Promise.all([K.p.waitForNavigation(), K.p.click('button:has-text("Sačuvaj lozinku")')]);
  ok(K.p.url().endsWith('/trening'), 'posle lozinke klijent je prijavljen i na /trening');
  // Link je jednokratan
  const K2 = await novaStrana(browser, sirina);
  await K2.p.goto(link);
  ok(await K2.p.isVisible('text=Link je istekao'), 'link za lozinku ne radi drugi put');
  await K2.ctx.close();

  /* ---- 3. Klijent popunjava trening (autosave) i check-in ---- */
  await proveriStranu(K.p, sirina, 'klijent-trening');
  const kg = K.p.locator('input[name$="[kg]"]').first();
  await kg.fill('62,5');
  await K.p.locator('input[name$="[pon]"]').first().fill('9');
  await K.p.waitForFunction(() => /Sačuvano u/.test(document.querySelector('[data-autosave-status]').textContent), null, { timeout: 8000 });
  ok(true, 'autosave je sačuvao unos');
  ok(/^serija\[s\d+\]/.test(await kg.getAttribute('name')), 'posle autosave-a polja nose ID sesije');
  // Osveži: unos mora da ostane (kao da se telefon zaključao)
  await K.p.reload();
  ok(await K.p.locator('input[name$="[kg]"]').first().inputValue() === '62,5', 'unos ostaje posle osvežavanja strane');
  await K.p.locator('.check').nth(1).click();   // štikla popunjava plan
  for (const [polje, v] of [['Spavanje', '4'], ['Hidratacija', '3'], ['Hrana', '4'], ['Stres', '2'], ['Motivacija', '5']]) {
    await K.p.locator(`.scale[aria-labelledby="l-${polje.toLowerCase()}"] button[data-skala="${v}"]`).click();
  }
  await K.p.locator('.scale[aria-labelledby="l-bol"] button[data-skala="Jako"]').click();
  await K.p.fill('textarea[name=napomena]', 'Koleno me bolelo na čučnju <script>alert(1)</script>');
  await Promise.all([K.p.waitForNavigation(), K.p.click('button:has-text("Završi trening")')]);
  ok(await K.p.isVisible('text=Sačuvaj izmene'), 'posle završetka dugme je "Sačuvaj izmene"');
  ok(await K.p.locator('.tabs a', { hasText: '✓' }).count() === 1, 'završen trening ima ✓ u tabu');
  await proveriStranu(K.p, sirina, 'klijent-trening-zavrsen');

  // Ostali klijentovi ekrani
  for (const [u, ime] of [['/ishrana', 'klijent-ishrana'], ['/ishrana?tab=dan', 'klijent-sastavi-dan'], ['/ishrana?tab=kalk', 'klijent-kalkulator'],
    ['/istorija', 'klijent-istorija-nedelja'], ['/istorija?v=mesec', 'klijent-istorija-mesec'], ['/istorija?v=dan', 'klijent-istorija-dan'], ['/napredak', 'klijent-napredak'], ['/vodici', 'klijent-vodici']]) {
    await K.p.goto(BAZA + u);
    await proveriStranu(K.p, sirina, ime);
  }
  // Težina i ishrana se čuvaju
  await K.p.goto(BAZA + '/napredak');
  await K.p.fill('input[name=kg]', '68,4');
  await Promise.all([K.p.waitForNavigation(), K.p.click('button[value=tezina]')]);
  ok(await K.p.isVisible('text=Težina je upisana'), 'težina je upisana');
  await K.p.goto(BAZA + '/ishrana?tab=dan');
  // Pretraga: bez kvačica i po korenu reči ("cokolad" -> Čokolada, "piletina" -> Pileća)
  await K.p.locator('.birac input[type=search]').fill('cokolad');
  ok(await K.p.locator('.birac-opcija', { hasText: 'Crna čokolada 70%' }).count() === 1, 'pretraga bez kvačica nalazi „Crna čokolada 70%“');
  await K.p.locator('.birac input[type=search]').fill('piletina prsa');
  ok((await K.p.locator('.birac-opcija').first().textContent()).includes('Pileća prsa'), 'pretraga po korenu: „piletina prsa“ nalazi „Pileća prsa“');
  await K.p.locator('.birac input[type=search]').fill('chicken');
  ok(await K.p.locator('.birac-opcija', { hasText: 'Pileća prsa' }).count() >= 1, 'pretraga radi i na engleskom („chicken“)');
  ok(await K.p.locator('select[name=namirnica_id] option').count() > 200, 'baza ima više od 200 namirnica');
  await izaberiNamirnicu(K.p, 'banan', 'Banana');
  await K.p.fill('input[name=grami]', '150');
  await Promise.all([K.p.waitForNavigation(), K.p.click('button[value=dodaj]')]);
  ok(await K.p.locator('.meal').count() >= 1, 'namirnica je dodata u "Sastavi svoj dan"');
  await K.p.goto(BAZA + '/ishrana?tab=kalk');
  ok(/\d/.test(await K.p.textContent('[data-out="kc"]')), 'kalkulator računa kalorije');

  /* ---- 4. Trener vidi upis ---- */
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=program`);
  const t = await T.p.textContent('main');
  ok(t.includes('62,5 × 9'), 'trener vidi klijentovu kilažu i ponavljanja');
  ok(t.includes('Bol: Jako') && t.includes('Spavanje 4/5'), 'trener vidi check-in');
  ok(t.includes('<script>alert(1)</script>') && !(await T.p.$('main script')), 'napomena klijenta je escapovana');
  await T.p.goto(BAZA + '/trener/klijenti');
  ok((await T.p.locator('a.client', { hasText: novi.ime }).locator('.chip.bad').count()) >= 1, 'novi klijent je crven u listi (prijavio bol)');
  await T.p.goto(BAZA + '/trener');
  ok((await T.p.textContent('main')).includes(novi.ime), 'početna trenera prikazuje poslednji trening');
  for (const tab of ['istorija', 'napredak', 'ishrana', 'clanarina', 'nalog']) {
    await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=${tab}`);
    await proveriStranu(T.p, sirina, 'trener-klijent-' + tab);
  }
  // Trener vidi konkretno šta je klijent jeo (Sastavi svoj dan)
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=ishrana`);
  const jelo = await T.p.locator('details.dan-hrana[open]').first().textContent().catch(() => '');
  ok(/Banana\s*150 g/.test(jelo) && jelo.includes('134 kcal'), 'trener vidi namirnice koje je klijent pojeo (Banana 150 g, 134 kcal)');
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=istorija&v=dan`);
  ok((await T.p.textContent('main')).includes('Šta je pojedeno'), 'istorija dana prikazuje spisak namirnica');
  await proveriStranu(T.p, sirina, 'trener-klijent-istorija-dan');

  // Trener menja program: istorija ostaje netaknuta
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=program`);
  await T.p.locator('input[name^="serija"][name$="[kg]"]').first().fill('999');
  await Promise.all([T.p.waitForNavigation(), T.p.click('button:has-text("Sačuvaj program")')]);
  ok(await T.p.locator('input[name^="serija"][name$="[kg]"]').first().inputValue() === '999', 'trener je sačuvao izmenu programa');
  await K.p.goto(BAZA + '/istorija?v=dan');
  ok(!(await K.p.textContent('main')).includes('999'), 'istorija klijenta čuva stari plan (izmena programa je ne menja)');
  await T.p.click('text=Produži 30 dana').catch(() => {});
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=clanarina`);
  await Promise.all([T.p.waitForNavigation(), T.p.click('button[value=produzi]')]);
  ok(await T.p.isVisible('text=Članarina važi do'), 'članarina je produžena');

  // Ishrana iz baze namirnica: lični primer za klijenta, vrednosti se računaju same
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=ishrana`);
  await T.p.fill('input[name=naziv][placeholder^="Naziv"]', 'Plan ' + sirina);
  await Promise.all([T.p.waitForNavigation(), T.p.click('button:has-text("+ Novi primer za klijenta")')]);
  ok(/\/trener\/ishrana\?p=\d+/.test(T.p.url()), 'napravljen je primer za klijenta');
  const obrok = T.p.locator('[data-obrok]').first();
  await izaberiNamirnicu(obrok.locator('[data-nova]'), 'pileca prsa sirov', 'Pileća prsa (sirova)');
  await obrok.locator('[data-nova] input[type=number]').fill('150');
  ok((await obrok.locator('[data-nova] [data-vrednost]').textContent()).includes('180 kcal'), 'kalorije se računaju dok trener kuca (150 g piletine = 180 kcal)');
  await Promise.all([T.p.waitForNavigation(), obrok.locator('button:has-text("+ Dodaj")').click()]);
  await izaberiNamirnicu(T.p.locator('[data-obrok]').first().locator('[data-nova]'), 'pirinac', 'Pirinač (sirov)');
  await T.p.locator('[data-obrok]').first().locator('[data-nova] input[type=number]').fill('80');
  await Promise.all([T.p.waitForNavigation(), T.p.click('button:has-text("Sačuvaj primer")')]);
  const zbirObroka = await T.p.locator('[data-zbir-kcal]').first().textContent();
  ok(zbirObroka.includes('468 kcal'), 'obrok je zbir namirnica (180 + 288 = 468 kcal): ' + zbirObroka.trim());
  await T.p.locator('[data-stavka] input').first().fill('200');
  ok((await T.p.locator('[data-zbir-kcal]').first().textContent()).includes('528'), 'promena grama odmah menja zbir (200 g piletine)');
  await Promise.all([T.p.waitForNavigation(), T.p.click('button:has-text("Sačuvaj primer")')]);
  await proveriStranu(T.p, sirina, 'trener-primer-namirnice');
  await K.p.goto(BAZA + '/ishrana');
  const kIsh = await K.p.textContent('main');
  ok(kIsh.includes('Plan ' + sirina) && kIsh.includes('Samo za tebe') && kIsh.includes('Pileća prsa (sirova) 200 g'), 'klijent vidi svoj primer sa namirnicama i gramima');
  ok(kIsh.includes('528 kcal'), 'klijent vidi izračunate kalorije obroka');
  await T.p.goto(BAZA + '/trener/ishrana?tab=namirnice');
  await T.p.fill('input[data-filter]', 'jogurt');
  const vidljivo = await T.p.locator('details[data-nam]:visible').count();
  ok(vidljivo >= 3 && vidljivo <= 8, 'pretraga baze namirnica kod trenera („jogurt“ -> ' + vidljivo + ' rezultata)');
  for (const [u, ime] of [['/trener/vezbe', 'trener-vezbe'], ['/trener/ishrana', 'trener-ishrana'], ['/trener/ishrana?tab=namirnice', 'trener-namirnice'], ['/trener/vodici', 'trener-vodici']]) {
    await T.p.goto(BAZA + u);
    await proveriStranu(T.p, sirina, ime);
  }

  /* ---- 6a. Profilna slika ---- */
  const probnaSlika = path.join(SLIKE, 'proba-slika.jpg');
  // Pravougaona slika (600×400) — aplikacija mora da je iseče na kvadrat 320×320
  execSync(`php -r '$i=imagecreatetruecolor(600,400);imagefill($i,0,0,imagecolorallocate($i,29,107,87));imagejpeg($i,"${probnaSlika}");'`);
  await K.p.goto(BAZA + '/nalog');
  await K.p.setInputFiles('input[name=slika]', probnaSlika);
  await Promise.all([K.p.waitForNavigation(), K.p.click('button:has-text("Sačuvaj sliku")')]);
  ok(await K.p.isVisible('text=Profilna slika je postavljena'), 'klijent je postavio profilnu sliku');
  const dim = await K.p.$eval('header .av img', (i) => i.complete ? [i.naturalWidth, i.naturalHeight] : [0, 0]).catch(() => [0, 0]);
  ok(dim[0] === 320 && dim[1] === 320, 'slika u zaglavlju je isečena na 320×320: ' + dim.join('×'));
  await proveriStranu(K.p, sirina, 'klijent-moj-nalog');
  await T.p.goto(BAZA + '/trener/klijenti');
  const src = await T.p.locator('a.client', { hasText: novi.ime }).locator('.av img').getAttribute('src');
  ok(/^\/slika\/\d+/.test(src || ''), 'trener vidi sliku klijenta u listi');
  const tSlika = await T.p.evaluate(async (u) => (await fetch(u)).status, src);
  ok(tSlika === 200, 'trener može da otvori sliku klijenta');
  // Trener postavlja sliku sebi i klijentu
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=nalog`);
  await T.p.setInputFiles('input[name=slika]', probnaSlika);
  await Promise.all([T.p.waitForNavigation(), T.p.click('button:has-text("Sačuvaj sliku")')]);
  ok(await T.p.isVisible('text=Profilna slika je postavljena'), 'trener je postavio sliku klijentu');
  // Pogrešan fajl se odbija
  const lazna = path.join(SLIKE, 'nije-slika.jpg');
  fs.writeFileSync(lazna, '<?php echo 1; ?>');
  await K.p.goto(BAZA + '/nalog');
  await K.p.setInputFiles('input[name=slika]', lazna);
  await Promise.all([K.p.waitForNavigation(), K.p.click('button:has-text("Sačuvaj sliku")')]);
  ok(await K.p.isVisible('text=JPG, PNG ili WebP'), 'fajl koji nije slika je odbijen');
  novi.slika = (await T.p.locator('.band .av img').getAttribute('src')).split('?')[0];

  /* ---- 5. Izolacija: novi klijent (A) protiv Ane (B) ---- */
  K.p.ocekujeOdbijanje = true;
  // Direktan URL trenerovih strana
  for (const u of ['/trener', '/trener/klijenti', '/trener/klijent/1', '/trener/vezbe']) {
    const r = await K.p.goto(BAZA + u);
    ok(r.status() === 403, `klijent dobija 403 na ${u}`);
  }
  // Anini podaci kroz podmetnut ID
  const anaTrening = await T.p.evaluate(async () => 1);   // trening 1 iz primera pripada Ani (klijent 1)
  const csrf = await K.p.goto(BAZA + '/trening').then(() => K.p.getAttribute('body', 'data-csrf'));
  const odg = await K.p.evaluate(async ({ csrf, anaTrening }) => {
    const fd = new FormData();
    fd.append('csrf', csrf); fd.append('trening_id', String(anaTrening)); fd.append('napomena', 'HAK');
    fd.append('serija[s1][kg]', '1'); fd.append('serija[p1][kg]', '1');
    const r = await fetch('/api/trening', { method: 'POST', body: fd });
    return r.status;
  }, { csrf, anaTrening });
  ok(odg === 404, 'autosave sa Aninim trening_id je odbijen (404)');
  // Podmetnut ID serije iz Anine sesije u sopstvenu formu
  const mojTrening = await K.p.getAttribute('input[name=trening_id]', 'value');
  await K.p.evaluate(async ({ csrf, mojTrening }) => {
    const fd = new FormData();
    fd.append('csrf', csrf); fd.append('trening_id', mojTrening);
    for (let i = 1; i <= 60; i++) fd.append(`serija[s${i}][kg]`, '777');
    await fetch('/api/trening', { method: 'POST', body: fd });
  }, { csrf, mojTrening });
  // Brisanje Anine stavke ishrane podmetanjem ID-ja
  await K.p.evaluate(async ({ csrf }) => {
    for (let id = 1; id <= 40; id++) {
      const fd = new FormData(); fd.append('csrf', csrf); fd.append('akcija', 'ukloni'); fd.append('id', String(id)); fd.append('datum', new Date().toISOString().slice(0, 10));
      await fetch('/ishrana', { method: 'POST', body: fd });
    }
  }, { csrf });
  // CSRF: bez tokena -> 419
  const bezTokena = await K.p.evaluate(async () => (await fetch('/napredak', { method: 'POST', body: new URLSearchParams({ kg: '50' }) })).status);
  ok(bezTokena === 419, 'POST bez CSRF tokena je odbijen (419)');
  K.p.ocekujeOdbijanje = false;

  const A = await novaStrana(browser, sirina);
  await prijava(A.p, ANA);
  await A.p.goto(BAZA + '/istorija?v=mesec');
  const anaTekst = await A.p.textContent('main');
  ok(!anaTekst.includes('777') && !anaTekst.includes('HAK'), 'Anini treninzi nisu promenjeni podmetnutim ID-jem');
  await A.p.goto(BAZA + '/trening');
  ok(!(await A.p.textContent('main')).includes('HAK'), 'Anina napomena nije promenjena');
  K.p.ocekujeOdbijanje = true; A.p.ocekujeOdbijanje = true;
  const tudjaSlika = await A.p.evaluate(async (u) => (await fetch(u)).status, novi.slika);
  ok(tudjaSlika === 404, 'Ana ne može da otvori sliku drugog klijenta (' + novi.slika + ' -> ' + tudjaSlika + ')');
  A.p.ocekujeOdbijanje = false;
  await A.p.goto(BAZA + '/ishrana');
  ok(!(await A.p.textContent('main')).includes('Plan ' + sirina), 'Ana ne vidi lični primer ishrane drugog klijenta');
  await A.ctx.close();
  const db = process.env.TC_BAZA || 'trener_test';
  const n = execSync(`mariadb -u trener -ptrener ${db} -N -e "SELECT COUNT(*) FROM ishrana_dnevnik WHERE id <= 40 AND klijent_id = 1"`).toString().trim();
  ok(n === '40', 'Anine stavke ishrane nisu obrisane podmetnutim ID-jem (' + n + '/40)');
  const s = execSync(`mariadb -u trener -ptrener ${db} -N -e "SELECT COUNT(*) FROM sesija_serije WHERE kg = '777'"`).toString().trim();
  ok(s === '0', 'nijedna tuđa serija nije dobila podmetnutu kilažu');

  /* ---- 6. Promena lozinke ---- */
  // Klijent sam menja lozinku
  await K.p.goto(BAZA + '/nalog');
  await proveriStranu(K.p, sirina, 'klijent-nalog');
  await K.p.fill('input[name=trenutna]', 'pogresna-lozinka');
  await K.p.fill('input[name=lozinka]', 'nova-lozinka-22');
  await K.p.fill('input[name=lozinka2]', 'nova-lozinka-22');
  await Promise.all([K.p.waitForNavigation(), K.p.click('button:has-text("Sačuvaj novu lozinku")')]);
  ok(await K.p.isVisible('text=Trenutna lozinka nije tačna'), 'pogrešna trenutna lozinka je odbijena');
  await K.p.fill('input[name=trenutna]', novi.lozinka);
  await K.p.fill('input[name=lozinka]', 'nova-lozinka-22');
  await K.p.fill('input[name=lozinka2]', 'nova-lozinka-22');
  await Promise.all([K.p.waitForNavigation(), K.p.click('button:has-text("Sačuvaj novu lozinku")')]);
  ok(await K.p.isVisible('text=Lozinka je promenjena'), 'klijent je promenio lozinku');
  await K.p.goto(BAZA + '/trening');
  ok(K.p.url().endsWith('/trening'), 'klijent ostaje prijavljen posle promene lozinke');
  const K3 = await novaStrana(browser, sirina);
  await prijava(K3.p, { email: novi.email, lozinka: novi.lozinka });
  ok(K3.p.url().includes('/prijava'), 'stara lozinka više ne radi');
  await prijava(K3.p, { email: novi.email, lozinka: 'nova-lozinka-22' });
  ok(K3.p.url().endsWith('/trening'), 'nova lozinka radi');

  // Trener postavlja klijentu novu lozinku -> klijent odjavljen svuda
  await T.p.goto(`${BAZA}/trener/klijent/${noviId}?tab=nalog`);
  await T.p.fill('input[name=lozinka]', 'od-trenera-33');
  await T.p.fill('input[name=lozinka2]', 'od-trenera-33');
  await Promise.all([T.p.waitForNavigation(), T.p.click('button:has-text("Postavi lozinku")')]);
  ok(await T.p.isVisible('text=je postavljena'), 'trener je postavio klijentu novu lozinku');
  await K3.p.goto(BAZA + '/trening');
  ok(K3.p.url().includes('/prijava'), 'posle trenerove promene klijent je odjavljen sa drugog uređaja');
  await prijava(K3.p, { email: novi.email, lozinka: 'od-trenera-33' });
  ok(K3.p.url().endsWith('/trening'), 'klijent se prijavljuje lozinkom koju je postavio trener');
  await K3.ctx.close();

  // Trener menja svoju lozinku (pa je vraća, da drugi prolaz testa radi)
  for (const [stara, nova] of [[TRENER.lozinka, 'privremena-trener-9'], ['privremena-trener-9', TRENER.lozinka]]) {
    await T.p.goto(BAZA + '/nalog');
    await T.p.fill('input[name=trenutna]', stara);
    await T.p.fill('input[name=lozinka]', nova);
    await T.p.fill('input[name=lozinka2]', nova);
    await Promise.all([T.p.waitForNavigation(), T.p.click('button:has-text("Sačuvaj novu lozinku")')]);
    ok(await T.p.isVisible('text=Lozinka je promenjena'), 'trener je promenio svoju lozinku');
  }
  await proveriStranu(T.p, sirina, 'trener-nalog');
  ok(await T.p.isVisible('a.meni-stavka:has-text("Promeni lozinku")') || (await T.p.$('a.meni-stavka[href="/nalog"]')) !== null, 'meni naloga ima "Promeni lozinku"');

  /* ---- 7. Engleski za strane klijente ---- */
  const john = { ime: 'John Test ' + sirina, email: `john.${oznaka}@primer.rs`, lozinka: 'john-password-1' };
  await T.p.goto(BAZA + '/trener/klijenti');
  await T.p.click('button:has-text("+ Novi klijent")');
  await T.p.fill('#novi-klijent input[name=ime]', john.ime);
  await T.p.fill('#novi-klijent input[name=email]', john.email);
  await T.p.selectOption('#novi-klijent select[name=jezik]', 'en');
  const sab = await T.p.$$eval('#novi-klijent select[name=sablon_id] option', (x) => x.map((y) => y.value));
  await T.p.selectOption('#novi-klijent select[name=sablon_id]', sab[1]);
  await Promise.all([T.p.waitForNavigation(), T.p.click('#novi-klijent button:has-text("Napravi nalog")')]);
  ok(await T.p.isVisible('text=Mejl je poslat'), 'trener je napravio klijenta na engleskom (i dalje vidi srpski)');
  const jm = poslednjiMejl(john.email);
  ok(jm && jm.includes('has created your account') && jm.includes('Set your password'), 'pozivnica je na engleskom');
  const jLink = (jm.match(/https?:\/\/\S+\/lozinka\?t=[a-f0-9]{64}/) || [])[0];
  // Strani klijent: pregledač na engleskom (en-US)
  const J = await novaStrana(browser, sirina, 'en-US');
  await J.p.goto(jLink);
  ok((await J.p.textContent('main')).includes('Choose a password for the account'), 'strana za lozinku je na engleskom');
  await J.p.fill('input[name=lozinka]', john.lozinka);
  await J.p.fill('input[name=lozinka2]', john.lozinka);
  await Promise.all([J.p.waitForNavigation(), J.p.click('button:has-text("Save password")')]);
  ok(await J.p.isVisible('text=Welcome! Your password has been set.'), 'poruka posle lozinke je na engleskom');
  const strane = [['/trening', ['Finish workout', 'How are you today?', 'Squat', 'Warm-up:']], ['/ishrana?tab=dan', ['Build your day', 'What you have left', 'Chicken breast (raw)']],
    ['/ishrana?tab=kalk', ['Calorie calculator', 'Basal metabolic rate']], ['/istorija?v=mesec', ['Workout done', 'By week', 'Mon']], ['/istorija?v=dan', ['Nutrition', 'Weight']],
    ['/napredak', ['Weigh in every morning', 'Strength • estimated 1RM']], ['/vodici', ['Guides']], ['/nalog', ['Profile photo', 'Change password']]];
  for (const [u, reci] of strane) {
    await J.p.goto(BAZA + u);
    // Bez sadržaja koji upisuje trener (kategorije vodiča, nazivi treninga, napomene)
    const tx = await J.p.evaluate(() => { const b = document.body.cloneNode(true); b.querySelectorAll('.guide > div, .note-t, script').forEach((x) => x.remove()); return b.textContent; });
    const fali = reci.filter((r) => !tx.includes(r));
    ok(fali.length === 0, `${u} je na engleskom` + (fali.length ? ' (fali: ' + fali.join(', ') + ')' : ''));
    // Interfejs ne sme da ima srpske reči (sadržaj koji piše trener — nazivi treninga, napomene — može)
    const srpski = ['Sačuvaj', 'Završi', 'Dodaj', 'Ishrana', 'Istorija', 'Napredak', 'Vodiči', 'Odjavi', 'nedelja', 'Kalorije', 'Težina'].filter((r) => tx.includes(r));
    ok(srpski.length === 0, `${u}: bez srpskih reči u interfejsu` + (srpski.length ? ' (' + srpski.join(', ') + ')' : ''));
    await proveriStranu(J.p, sirina, 'en' + u.replace(/[/?=&]/g, '-'));
  }
  // Autosave poruka na engleskom
  await J.p.goto(BAZA + '/trening');
  await J.p.locator('input[name$="[kg]"]').first().fill('40');
  await J.p.waitForFunction(() => /Saved at/.test(document.querySelector('[data-autosave-status]').textContent), null, { timeout: 8000 });
  ok(true, 'autosave poruka je na engleskom ("Saved at")');
  // Dugme SR prebacuje na srpski i pamti izbor
  await Promise.all([J.p.waitForNavigation(), J.p.click('button.jezik')]);
  ok((await J.p.textContent('main')).includes('Završi trening'), 'dugme SR prebacuje na srpski');
  await Promise.all([J.p.waitForNavigation(), J.p.click('button.jezik')]);
  ok((await J.p.textContent('main')).includes('Finish workout'), 'dugme EN vraća engleski');
  await J.ctx.close();
  // Trener vidi istog klijenta na srpskom
  await T.p.goto(BAZA + '/trener/klijenti');
  ok((await T.p.locator('a.client', { hasText: john.ime }).textContent()).includes('EN'), 'trener vidi oznaku EN kod klijenta');
  // Prijava za goste: dugme EN radi i bez naloga
  const G = await novaStrana(browser, sirina);
  await G.p.goto(BAZA + '/prijava');
  ok(await G.p.isVisible('button:has-text("Prijavi se")'), 'prijava je na srpskom za srpski pregledač');
  await Promise.all([G.p.waitForNavigation(), G.p.click('a.jezik')]);
  ok(await G.p.isVisible('button:has-text("Sign in")'), 'dugme EN na prijavi prebacuje na engleski');
  await G.ctx.close();

  await T.ctx.close();
  await K.ctx.close();
}

(async () => {
  const browser = await chromium.launch();
  try {
    for (const s of [390, 1440]) await tok(browser, s);
  } finally {
    await browser.close();
  }
  const log = process.env.TC_LOG && fs.existsSync(process.env.TC_LOG) ? fs.readFileSync(process.env.TC_LOG, 'utf8') : '';
  const phpLog = log.split('\n').filter((r) => PHP_GRESKA.test(r) || /PHP (Warning|Notice|Fatal|Deprecated)/.test(r));
  ok(phpLog.length === 0, 'PHP log servera je čist' + (phpLog.length ? ':\n    ' + phpLog.slice(0, 10).join('\n    ') : ''));
  console.log(`\n${provera - greske.length}/${provera} provera prošlo.`);
  if (greske.length) {
    console.log('\nGREŠKE:\n- ' + [...new Set(greske)].join('\n- '));
    process.exit(1);
  }
  console.log('Snimci ekrana: testovi/rezultati/');
})();
