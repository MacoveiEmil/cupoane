/* Singurul JS de pe site (vanilla, defer). Totul funcționează și fără el. */
(function () {
  'use strict';
  var d = document;

  /* Meniul mobil: se închide la click în afară sau cu Esc. */
  var menu = d.querySelector('[data-menu]');
  if (menu) {
    d.addEventListener('click', function (e) { if (menu.open && !menu.contains(e.target)) menu.open = false; });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); } });
  }

  /* Codul: dezvăluire inline + copiere; magazinul se deschide în tab nou prin /go/ (linkul nu e blocat). */
  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (ok, fail) {
      var t = d.createElement('textarea');
      t.value = text; t.setAttribute('readonly', ''); t.style.cssText = 'position:fixed;top:0;opacity:0';
      d.body.appendChild(t); t.select();
      try { d.execCommand('copy') ? ok() : fail(); } catch (e) { fail(e); }
      t.remove();
    });
  }
  function say(status, msg, href) {
    status.textContent = msg;
    if (!href) return;
    var a = d.createElement('a');
    a.href = href; a.target = '_blank'; a.rel = 'sponsored nofollow noopener';
    a.textContent = 'Mergi la magazin ↗';
    status.appendChild(d.createTextNode(' · ')); status.appendChild(a);
  }
  d.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn--code');
    if (!btn) return;
    var code = btn.getAttribute('data-code') || '';
    var status = btn.closest('.card').querySelector('.card__status');
    var again = btn.classList.contains('is-open');
    // Primul click: dezvăluie, copiază și deschide magazinul. Următoarele: doar copiază (fără tab-uri noi).
    if (again) e.preventDefault();
    var box = btn.querySelector('.btn__code');
    box.textContent = code; box.removeAttribute('aria-hidden');
    btn.classList.add('is-open');
    copy(code).then(function () { say(status, again ? 'Copiat din nou!' : 'Copiat! 🎉', btn.href); }, function () { say(status, 'Selectează codul și copiază-l', btn.href); });
  });

  /* Voturi „A funcționat?” */
  function voted() { var m = d.cookie.match(/(?:^|;\s*)cv=([^;]*)/); return m ? decodeURIComponent(m[1]).split('.') : []; }
  function done(el, msg) { el.classList.add('is-done'); el.querySelector('.vote__q').textContent = msg; }
  var mine = voted();
  d.querySelectorAll('[data-vote]').forEach(function (el) { if (mine.indexOf(el.getAttribute('data-vote')) !== -1) done(el, 'Mulțumim pentru vot!'); });
  d.addEventListener('click', function (e) {
    var b = e.target.closest('.vote__btn');
    if (!b) return;
    var el = b.closest('[data-vote]');
    el.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
    fetch('/api/vote', {
      method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ id: +el.getAttribute('data-vote'), v: +b.getAttribute('data-v') })
    }).then(function (r) { return r.json(); }).then(function (r) {
      done(el, r && r.ok ? 'Mulțumim pentru vot!' : 'Ai votat deja.');
      var rt = el.querySelector('.vote__rate');
      if (r && r.rate !== null && r.rate !== undefined) rt.textContent = r.rate + '% succes · ' + r.total + ' voturi';
    }).catch(function () { el.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
  });

  /* Căutare cu sugestii: JSON mic, încărcat la primul focus, filtrat local (fără diacritice). */
  var index = null, loading = null;
  function norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim(); }
  function load(url) {
    if (index) return Promise.resolve(index);
    return loading || (loading = fetch(url).then(function (r) { return r.json(); }).then(function (rows) {
      return (index = rows.map(function (r) { return { n: r[0], u: r[1], c: r[2], k: norm(r[0]) }; }));
    }).catch(function () { loading = null; return []; }));
  }
  d.querySelectorAll('[data-search]').forEach(function (form) {
    var input = form.querySelector('input[name=q]'), list = form.querySelector('.search__list'), url = form.getAttribute('data-index'), active = -1, items = [];
    function close() { list.hidden = true; list.innerHTML = ''; input.setAttribute('aria-expanded', 'false'); active = -1; items = []; }
    function select(i) { items.forEach(function (li, j) { li.setAttribute('aria-selected', j === i ? 'true' : 'false'); }); active = i; }
    function render(q) {
      var nq = norm(q);
      if (!nq || !index) return close();
      var res = index.filter(function (s) { return s.k.indexOf(nq) === 0; }).concat(index.filter(function (s) { return s.k.indexOf(nq) > 0; })).slice(0, 8);
      if (!res.length) return close();
      list.innerHTML = '';
      items = res.map(function (s, i) {
        var li = d.createElement('li'), a = d.createElement('a'), sm = d.createElement('small');
        li.id = input.id + '-o' + i; li.setAttribute('role', 'option');
        a.href = s.u; a.tabIndex = -1; a.textContent = s.n;
        sm.textContent = s.c === 1 ? '1 ofertă' : s.c + (s.c % 100 < 20 ? ' oferte' : ' de oferte');
        a.appendChild(sm); li.appendChild(a); list.appendChild(li);
        return li;
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true'); select(-1);
    }
    input.addEventListener('focus', function () { load(url); }, { once: true });
    input.addEventListener('input', function () { load(url).then(function () { render(input.value); }); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); select((active + 1) % items.length); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); select(active <= 0 ? items.length - 1 : active - 1); }
      else if (e.key === 'Escape') close();
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); location.href = items[active].querySelector('a').href; }
    });
    d.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
  });
})();
