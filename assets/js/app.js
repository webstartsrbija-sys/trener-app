/* Tomislav Coaching — sitna interakcija. Sve radi i bez JS-a osim autosave-a,
   check-in dugmadi i kalkulatora; podaci se uvek čuvaju na serveru. */
(function () {
  'use strict';
  var d = document;
  var csrf = d.body ? d.body.getAttribute('data-csrf') : '';
  var loc = d.body && d.body.getAttribute('data-jezik') === 'en' ? 'en-GB' : 'sr-RS';
  var recnik = {};
  try { recnik = JSON.parse(d.getElementById('js-tekst').textContent); } catch (e) {}
  // Prevod iz glava.php; %s se menja argumentom
  function T(k, a) { var s = recnik[k] || k; return a === undefined ? s : s.replace('%s', a); }

  /* ---------- Tema ---------- */
  function tema(t) {
    if (t) d.documentElement.setAttribute('data-theme', t); else d.documentElement.removeAttribute('data-theme');
  }
  try { tema(localStorage.getItem('tema')); } catch (e) {}
  d.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-tema]')) return;
    var tamna = matchMedia('(prefers-color-scheme: dark)').matches;
    var sada = d.documentElement.getAttribute('data-theme') || (tamna ? 'dark' : 'light');
    var nova = sada === 'dark' ? 'light' : 'dark';
    tema(nova);
    try { localStorage.setItem('tema', nova); } catch (e) {}
  });

  /* ---------- Toast ---------- */
  var tt;
  function toast(tekst, greska) {
    var t = d.getElementById('toast');
    if (!t) return;
    if (tekst) { t.textContent = tekst; t.classList.toggle('bad', !!greska); }
    t.hidden = false;
    clearTimeout(tt);
    tt = setTimeout(function () { t.hidden = true; }, greska ? 5000 : 2600);
  }
  var t0 = d.getElementById('toast');
  if (t0 && !t0.hidden) toast();

  /* ---------- Meni naloga: zatvori na klik van ---------- */
  d.addEventListener('click', function (ev) {
    d.querySelectorAll('details.meni[open]').forEach(function (m) { if (!m.contains(ev.target)) m.open = false; });
  });

  /* ---------- Donji list (sheet) ---------- */
  d.addEventListener('click', function (ev) {
    var o = ev.target.closest('[data-otvori]');
    if (o) {
      var s = d.getElementById(o.getAttribute('data-otvori'));
      if (s) { s.hidden = false; var f = s.querySelector('input:not([type=hidden])'); if (f) f.focus(); }
      return;
    }
    var z = ev.target.closest('[data-zatvori]');
    if (z) { z.closest('[data-sheet]').hidden = true; return; }
    if (ev.target.matches('[data-sheet]')) ev.target.hidden = true;
  });
  d.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') d.querySelectorAll('[data-sheet]').forEach(function (s) { s.hidden = true; });
  });

  /* ---------- Potvrda pre brisanja ---------- */
  d.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-potvrdi]');
    if (b && !confirm(b.getAttribute('data-potvrdi'))) { ev.preventDefault(); ev.stopPropagation(); }
  }, true);

  /* ---------- Kopiraj link ---------- */
  d.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-kopiraj]');
    if (!b) return;
    var i = d.getElementById(b.getAttribute('data-kopiraj'));
    i.select();
    (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.reject()).then(
      function () { toast(T('Link je kopiran')); },
      function () { try { d.execCommand('copy'); toast(T('Link je kopiran')); } catch (e) {} });
  });

  /* ---------- Upozorenje na nesačuvane izmene (trenerov program) ---------- */
  d.querySelectorAll('form[data-cuvaj-upozorenje]').forEach(function (f) {
    var prljavo = false;
    f.addEventListener('input', function () { prljavo = true; });
    f.addEventListener('change', function () { prljavo = true; });
    f.addEventListener('submit', function () { prljavo = false; });
    d.addEventListener('submit', function (ev) { if (ev.target !== f) prljavo = false; });
    window.addEventListener('beforeunload', function (ev) { if (prljavo) { ev.preventDefault(); ev.returnValue = ''; } });
  });

  /* ---------- Vodiči: polje za link ili PDF ---------- */
  function vrsta(sel) {
    var f = sel.closest('form');
    f.querySelectorAll('[data-za]').forEach(function (el) { el.hidden = el.getAttribute('data-za') !== sel.value; });
  }
  d.querySelectorAll('[data-vrsta]').forEach(function (s) { vrsta(s); s.addEventListener('change', function () { vrsta(s); }); });

  /* ---------- Trening: štikla, skala, autosave ---------- */
  var forma = d.querySelector('form[data-autosave]');
  if (forma) {
    var status = forma.querySelector('[data-autosave-status]');
    var tajmer = null, uToku = false, ponovo = false, prljavo = false;

    function javi(t, greska) { if (status) { status.textContent = t; status.classList.toggle('greska', !!greska); } }

    function podaci() { var fd = new FormData(forma); fd.delete('akcija'); return fd; }

    function sacuvaj() {
      clearTimeout(tajmer);
      if (!prljavo) return;
      if (uToku) { ponovo = true; return; }   // jedan zahtev u isto vreme — bez duplih sesija
      uToku = true; prljavo = false;
      javi(T('Čuvam…'));
      fetch(forma.getAttribute('data-autosave'), { method: 'POST', body: podaci(), credentials: 'same-origin', headers: { 'X-CSRF': csrf } })
        .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw new Error(j.greska || T('Greška')); return j; }); })
        .then(function (j) {
          // Serije su do sada imale ključ plana (p12); sada dobijaju ključ sesije (s34)
          Object.keys(j.mapa || {}).forEach(function (stari) {
            var novi = j.mapa[stari];
            forma.querySelectorAll('[name^="serija[' + stari + ']"]').forEach(function (el) {
              el.name = el.name.replace('serija[' + stari + ']', 'serija[' + novi + ']');
            });
            var red = forma.querySelector('[data-serija="' + stari + '"]');
            if (red) red.setAttribute('data-serija', novi);
          });
          javi(T('Sačuvano u %s', j.vreme));
        })
        .catch(function (e) { prljavo = true; javi(T('Nije sačuvano: %s Pokušaću ponovo.', e.message), true); tajmer = setTimeout(sacuvaj, 5000); })
        .then(function () { uToku = false; if (ponovo) { ponovo = false; sacuvaj(); } });
    }
    function zakazi() { prljavo = true; javi(T('Ima nesačuvanih izmena…')); clearTimeout(tajmer); tajmer = setTimeout(sacuvaj, 800); }

    forma.addEventListener('input', zakazi);
    forma.addEventListener('click', function (ev) {
      var ok = ev.target.closest('[data-ok]');
      if (ok) {
        var red = ok.closest('.set');
        var h = red.querySelector('input[type=hidden]');
        var novo = ok.getAttribute('aria-pressed') !== 'true';
        ok.setAttribute('aria-pressed', novo ? 'true' : 'false');
        h.value = novo ? '1' : '0';
        // Kao u prototipu: štikla popunjava prazna polja planom
        if (novo) red.querySelectorAll('input.cin').forEach(function (i) { if (!i.value && i.placeholder) i.value = i.placeholder; });
        zakazi();
        return;
      }
      var sk = ev.target.closest('[data-skala]');
      if (sk) {
        var grupa = sk.closest('.scale');
        var skriveno = grupa.querySelector('input[type=hidden]');
        var v = sk.getAttribute('data-skala');
        var vec = sk.getAttribute('aria-pressed') === 'true';
        grupa.querySelectorAll('[data-skala]').forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });
        if (!vec) sk.setAttribute('aria-pressed', 'true');
        skriveno.value = vec ? '' : v;
        zakazi();
      }
    });
    forma.addEventListener('submit', function () { clearTimeout(tajmer); prljavo = false; });
    // Telefon se zaključa / aplikacija ode u pozadinu: pošalji odmah
    d.addEventListener('visibilitychange', function () {
      if (d.visibilityState === 'hidden' && prljavo && navigator.sendBeacon) {
        var fd = podaci(); fd.append('csrf_beacon', '1');
        if (navigator.sendBeacon(forma.getAttribute('data-autosave'), fd)) prljavo = false;
      }
    });
    window.addEventListener('pagehide', function () { if (prljavo) sacuvaj(); });
  }

  /* ---------- Primeri ishrane: vrednosti se računaju dok trener kuca grame ---------- */
  var obrociForma = d.querySelector('form[data-obroci]');
  if (obrociForma) {
    var baza = {};
    try { JSON.parse(d.getElementById('namirnice-podaci').textContent).forEach(function (n) { baza[n[0]] = n.slice(1); }); } catch (e) {}
    var cele = function (x) { return Math.round(x).toLocaleString('sr-RS'); };
    var opis = function (v) { return cele(v[0]) + ' kcal<br>P ' + cele(v[1]) + ' • UH ' + cele(v[2]) + ' • M ' + cele(v[3]); };
    var obracun = function (obrok) {
      var zbir = [0, 0, 0, 0];
      obrok.querySelectorAll('[data-stavka]').forEach(function (r) {
        var n = r.getAttribute('data-na100').split(',').map(Number), g = parseFloat(r.querySelector('input').value) || 0;
        var v = n.map(function (x) { return x * g / 100; });
        r.querySelector('[data-vrednost]').innerHTML = opis(v);
        v.forEach(function (x, i) { zbir[i] += x; });
      });
      var nova = obrok.querySelector('[data-nova]');
      if (nova) {
        var g = parseFloat(nova.querySelector('input').value) || 0, n = baza[nova.querySelector('select').value];
        var out = nova.querySelector('[data-vrednost]');
        if (g > 0 && n) { var v = n.map(function (x) { return x * g / 100; }); out.innerHTML = '+ ' + opis(v); v.forEach(function (x, i) { zbir[i] += x; }); } else out.innerHTML = '';
      }
      var zk = obrok.querySelector('[data-zbir-kcal]'), zm = obrok.querySelector('[data-zbir-makro]');
      if (zk) { zk.textContent = cele(zbir[0]) + ' kcal'; zm.textContent = 'P ' + cele(zbir[1]) + ' • UH ' + cele(zbir[2]) + ' • M ' + cele(zbir[3]); }
    };
    obrociForma.addEventListener('input', function (ev) { var o = ev.target.closest('[data-obrok]'); if (o) obracun(o); });
    obrociForma.addEventListener('change', function (ev) { var o = ev.target.closest('[data-obrok]'); if (o) obracun(o); });
  }

  /* ---------- Kalkulator kalorija (Mifflin-St Jeor, iste formule kao u prototipu) ---------- */
  var kal = d.querySelector('form[data-kalkulator]');
  if (kal) {
    var izlaz = d.querySelector('[data-kalk-rezultat]');
    try {
      var sacuvano = JSON.parse(localStorage.getItem('kalkulator') || '{}');
      Object.keys(sacuvano).forEach(function (k) { if (kal.elements[k] && k !== 'tez') kal.elements[k].value = sacuvano[k]; });
    } catch (e) {}
    var fmt = function (n) { return Math.round(n).toLocaleString(loc); };
    var racunaj = function () {
      var v = function (n) { return parseFloat(String(kal.elements[n].value).replace(',', '.')) || 0; };
      var pol = kal.elements.pol.value, god = v('god'), vis = v('vis'), tez = v('tez'), akt = v('akt'), cilj = parseFloat(kal.elements.cilj.value) || 0;
      var bmr = 10 * tez + 6.25 * vis - 5 * god + (pol === 'm' ? 5 : -161);
      var tdee = bmr * akt;
      var kc = Math.round(tdee * (1 + cilj) / 10) * 10;
      var p = Math.round(tez * 2), m = Math.round(tez * 0.9), uh = Math.max(0, Math.round((kc - p * 4 - m * 9) / 4));
      var o = { kc: fmt(kc), kc2: fmt(kc), p: p, uh: uh, m: m, bmr: fmt(bmr), tdee: fmt(tdee) };
      Object.keys(o).forEach(function (k) { var el = izlaz.querySelector('[data-out="' + k + '"]'); if (el) el.textContent = o[k]; });
      try {
        var z = {}; ['pol', 'god', 'vis', 'akt', 'cilj'].forEach(function (k) { z[k] = kal.elements[k].value; });
        localStorage.setItem('kalkulator', JSON.stringify(z));
      } catch (e) {}
    };
    kal.addEventListener('input', racunaj);
    kal.addEventListener('change', racunaj);
    kal.addEventListener('submit', function (ev) { ev.preventDefault(); });
    racunaj();
  }
})();
