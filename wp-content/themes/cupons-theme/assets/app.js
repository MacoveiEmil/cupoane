/* Cupons: singurul JS de pe frontend (vanilla, defer). Site-ul funcționează și fără el. */
(function () {
  'use strict';

  var d = document;

  /* ---------- Meniu mobil: se închide la click în afară sau cu Esc (fără JS rămâne un <details> simplu) ---------- */
  var menu = d.querySelector('[data-menu]');
  if (menu) {
    d.addEventListener('click', function (e) { if (menu.open && !menu.contains(e.target)) menu.open = false; });
    d.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
    });
  }

  /* ---------- Cod: dezvăluire inline + copiere; magazinul se deschide în tab nou prin /go/ ---------- */
  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (ok, fail) {
      var t = d.createElement('textarea');
      t.value = text;
      t.setAttribute('readonly', '');
      t.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
      d.body.appendChild(t);
      t.select();
      try { d.execCommand('copy') ? ok() : fail(); } catch (e) { fail(e); }
      t.remove();
    });
  }

  d.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn--code');
    if (!btn) return;
    // Nu blocăm linkul: tab-ul nou cu magazinul se deschide normal (target=_blank).
    var code = btn.getAttribute('data-code') || '';
    var box = btn.querySelector('.btn__code');
    var card = btn.closest('.card');
    var status = card && card.querySelector('.card__status');
    box.textContent = code;
    box.removeAttribute('aria-hidden');
    btn.classList.add('is-open');
    copy(code).then(function () {
      if (status) status.textContent = 'Copiat!';
    }, function () {
      if (status) status.textContent = 'Selectează codul și copiază-l';
    });
  });

  /* ---------- Voturi „A funcționat?” ---------- */
  var api = d.body.getAttribute('data-api');

  function voted() {
    var m = d.cookie.match(/(?:^|;\s*)cupons_v=([^;]*)/);
    return m ? decodeURIComponent(m[1]).split('.') : [];
  }

  function done(el, msg) {
    el.classList.add('is-done');
    var q = el.querySelector('.vote__q');
    if (q) q.textContent = msg;
  }

  function rateText(r) {
    return r && r.rate !== null && r.rate !== undefined ? r.rate + '% succes · ' + r.total + ' voturi' : '';
  }

  var mine = voted();
  d.querySelectorAll('[data-vote]').forEach(function (el) {
    if (mine.indexOf(el.getAttribute('data-vote')) !== -1) done(el, 'Mulțumim pentru vot!');
  });

  d.addEventListener('click', function (e) {
    var b = e.target.closest('.vote__btn');
    if (!b || !api) return;
    var el = b.closest('[data-vote]');
    var id = parseInt(el.getAttribute('data-vote'), 10);
    el.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
    fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, v: parseInt(b.getAttribute('data-v'), 10) })
    }).then(function (r) { return r.json(); }).then(function (r) {
      done(el, r && r.ok ? 'Mulțumim pentru vot!' : 'Ai votat deja.');
      var rt = el.querySelector('.vote__rate');
      if (rt && r && r.total !== undefined) rt.textContent = rateText(r);
    }).catch(function () {
      el.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
    });
  });

  /* ---------- Căutare cu sugestii (JSON static, încărcat la primul focus) ---------- */
  var index = null, loading = null;

  function norm(s) {
    return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
  }

  function load(url) {
    if (index) return Promise.resolve(index);
    if (!loading) {
      loading = fetch(url, { credentials: 'omit' }).then(function (r) { return r.json(); }).then(function (rows) {
        index = rows.map(function (r) { return { n: r[0], u: r[1], c: r[2], k: norm(r[0]) }; });
        return index;
      }).catch(function () { loading = null; return []; });
    }
    return loading;
  }

  function label(c) {
    return c === 1 ? '1 ofertă' : c + (c % 100 < 20 ? ' oferte' : ' de oferte');
  }

  d.querySelectorAll('[data-search]').forEach(function (form) {
    var input = form.querySelector('input[name=q]');
    var list = form.querySelector('.search__list');
    var url = form.getAttribute('data-index');
    var active = -1, items = [];
    if (!input || !list || !url) return;

    function close() {
      list.hidden = true;
      list.innerHTML = '';
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
      items = [];
    }

    function select(i) {
      items.forEach(function (li, j) { li.setAttribute('aria-selected', j === i ? 'true' : 'false'); });
      active = i;
      if (i >= 0) input.setAttribute('aria-activedescendant', items[i].id);
    }

    function render(q) {
      var nq = norm(q);
      if (nq.length < 1 || !index) return close();
      var starts = [], contains = [];
      index.forEach(function (s) {
        var p = s.k.indexOf(nq);
        if (p === 0) starts.push(s); else if (p > 0) contains.push(s);
      });
      var res = starts.concat(contains).slice(0, 8);
      if (!res.length) return close();
      list.innerHTML = '';
      items = res.map(function (s, i) {
        var li = d.createElement('li');
        li.id = input.id + '-o' + i;
        li.setAttribute('role', 'option');
        var a = d.createElement('a');
        a.href = s.u;
        a.tabIndex = -1;
        a.textContent = s.n;
        var sm = d.createElement('small');
        sm.textContent = label(s.c);
        a.appendChild(sm);
        li.appendChild(a);
        list.appendChild(li);
        return li;
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      select(-1);
    }

    input.addEventListener('focus', function () { load(url).then(function () { if (input.value) render(input.value); }); }, { once: true });
    input.addEventListener('input', function () { load(url).then(function () { render(input.value); }); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); select((active + 1) % items.length); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); select(active <= 0 ? items.length - 1 : active - 1); }
      else if (e.key === 'Escape') { close(); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); location.href = items[active].querySelector('a').href; }
    });
    d.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
  });
})();
