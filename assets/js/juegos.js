/* =============================================================
   Juegos para repasar las entradas del blog.
   Los juegos se crean en Panel > Juegos; juegos.php los pasa aquí
   como JSON en el atributo data-datos. Este archivo no lleva ningún
   contenido: solo los motores de cada tipo de juego.
   Sin dependencias. Compatible con la CSP del blog (sin estilos ni scripts en línea).
   ============================================================= */
(function () {
  'use strict';

  var root = document.querySelector('[data-juegos]');
  if (!root) return;

  var DATA = { juegos: [], entradas: {} };
  try { DATA = JSON.parse(root.getAttribute('data-datos') || '{}'); } catch (e) {}
  var ENT = DATA.entradas && !Array.isArray(DATA.entradas) ? DATA.entradas : {};
  var cleanups = [];

  /* ================= UTILIDADES ================= */

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }
  function fuente(key) {
    var art = key ? ENT[key] : null;
    if (!art) return null;
    var p = el('p', 'jg-src');
    p.appendChild(document.createTextNode('Repasa: '));
    var a = el('a', null, art.t);
    a.href = art.u;
    p.appendChild(a);
    return p;
  }
  var STORE = 'juegos-blog-v2';
  function loadBest() {
    try { return JSON.parse(localStorage.getItem(STORE) || '{}') || {}; } catch (e) { return {}; }
  }
  function saveBest(id, pct) {
    var b = loadBest();
    if (b[id] == null || pct > b[id]) {
      b[id] = pct;
      try { localStorage.setItem(STORE, JSON.stringify(b)); } catch (e) {}
      return true;
    }
    return false;
  }
  function norm(s) {
    return String(s).toUpperCase()
      .replace(/[ÁÀÄ]/g, 'A').replace(/[ÉÈË]/g, 'E').replace(/[ÍÌÏ]/g, 'I')
      .replace(/[ÓÒÖÔ]/g, 'O').replace(/[ÚÙÜÛ]/g, 'U').replace(/[ÂÊÎ]/g, function (c) { return { 'Â': 'A', 'Ê': 'E', 'Î': 'I' }[c]; });
  }
  function artsOf(list) {
    var seen = {}, out = [];
    list.forEach(function (x) { if (x && !seen[x]) { seen[x] = 1; out.push(x); } });
    return out;
  }

  /* ================= MOTOR DE PREGUNTAS (test, V/F, clasificar) ================= */
  // rounds: [{ kicker, text, options:[...], ok:index, why, a }]
  // cfg: { fixed: bool (no barajar opciones), grid: 'two'|'many' }
  function runRounds(box, game, rounds, cfg) {
    cfg = cfg || {};
    var i = 0, score = 0, fallos = [];
    var head = el('div', 'jg-status');
    var count = el('span', 'jg-count');
    var pts = el('span', 'jg-points');
    head.appendChild(count); head.appendChild(pts);
    var bar = el('div', 'jg-bar'); var fill = el('span'); bar.appendChild(fill);
    var stage = el('div', 'jg-stage');
    box.appendChild(head); box.appendChild(bar); box.appendChild(stage);

    function show() {
      var r = rounds[i];
      count.textContent = (cfg.unit || 'Pregunta') + ' ' + (i + 1) + ' de ' + rounds.length;
      pts.textContent = score + ' acierto' + (score === 1 ? '' : 's');
      fill.style.width = (i / rounds.length * 100) + '%';
      stage.textContent = '';
      var card = el('div', 'jg-qcard');
      if (r.kicker) card.appendChild(el('p', 'jg-kicker', r.kicker));
      card.appendChild(el('h3', 'jg-q', r.text));
      stage.appendChild(card);

      var opts = r.options.map(function (t, k) { return { t: t, k: k }; });
      if (!cfg.fixed) opts = shuffle(opts);
      var list = el('div', 'jg-opts' + (cfg.grid ? ' jg-opts-' + cfg.grid : ''));
      var buttons = [];
      opts.forEach(function (o) {
        var b = el('button', 'jg-opt', o.t);
        b.type = 'button';
        b.addEventListener('click', function () { answer(o.k, b); });
        buttons.push({ b: b, k: o.k });
        list.appendChild(b);
      });
      stage.appendChild(list);
      if (buttons[0]) buttons[0].b.focus({ preventScroll: true });

      function answer(k, btn) {
        var ok = k === r.ok;
        if (ok) score++; else fallos.push(r.e);
        buttons.forEach(function (x) {
          x.b.disabled = true;
          if (x.k === r.ok) x.b.classList.add('is-ok');
        });
        if (!ok) btn.classList.add('is-ko');
        pts.textContent = score + ' acierto' + (score === 1 ? '' : 's');
        var fb = el('div', 'jg-feedback ' + (ok ? 'ok' : 'ko'));
        fb.setAttribute('role', 'status');
        fb.appendChild(el('strong', null, ok ? '¡Correcto!' : 'No es así.'));
        if (!ok && cfg.showAnswer !== false) fb.appendChild(el('span', 'jg-answer', ' Respuesta: ' + r.options[r.ok] + '.'));
        if (r.why) fb.appendChild(el('p', null, r.why));
        var src = fuente(r.e); if (src) fb.appendChild(src);
        var next = el('button', 'btn btn-primary jg-next', i + 1 < rounds.length ? 'Siguiente' : 'Ver resultado');
        next.type = 'button';
        next.addEventListener('click', function () {
          i++;
          if (i < rounds.length) show(); else finish(game, score, rounds.length, artsOf(fallos));
        });
        fb.appendChild(next);
        stage.appendChild(fb);
        next.focus({ preventScroll: true });
      }
    }
    show();
  }

  /* ================= JUEGO: MEMORIA ================= */
  function runMemory(box, game, pairs) {
    var cards = [];
    pairs.forEach(function (p, idx) {
      cards.push({ id: idx, t: p.a, kind: 'n' });
      cards.push({ id: idx, t: p.b, kind: 'd' });
    });
    cards = shuffle(cards);
    var moves = 0, found = 0, open = [], lock = false;
    var head = el('div', 'jg-status');
    var mv = el('span', 'jg-count', 'Intentos: 0');
    var fd = el('span', 'jg-points', 'Parejas: 0 de ' + pairs.length);
    head.appendChild(mv); head.appendChild(fd);
    box.appendChild(head);
    box.appendChild(el('p', 'jg-help', 'Da la vuelta a dos cartas: une cada concepto con su pareja.'));
    var grid = el('div', 'jg-memory');
    box.appendChild(grid);
    cards.forEach(function (c) {
      var b = el('button', 'jg-mcard');
      b.type = 'button';
      b.setAttribute('aria-label', 'Carta oculta');
      var inner = el('span', 'jg-mcard-inner');
      inner.appendChild(el('span', 'jg-mcard-back'));
      var front = el('span', 'jg-mcard-front ' + (c.kind === 'n' ? 'is-name' : 'is-desc'), c.t);
      inner.appendChild(front);
      b.appendChild(inner);
      c.b = b;
      b.addEventListener('click', function () {
        if (lock || b.classList.contains('is-up')) return;
        b.classList.add('is-up');
        b.setAttribute('aria-label', c.t);
        open.push(c);
        if (open.length === 2) {
          moves++; mv.textContent = 'Intentos: ' + moves;
          var x = open[0], y = open[1];
          if (x.id === y.id && x.kind !== y.kind) {
            x.b.classList.add('is-done'); y.b.classList.add('is-done');
            x.b.disabled = true; y.b.disabled = true;
            open = []; found++;
            fd.textContent = 'Parejas: ' + found + ' de ' + pairs.length;
            if (found === pairs.length) {
              // 8 intentos = perfecto (10). Cuantos más intentos, menos nota.
              var nota = Math.max(1, Math.min(10, Math.round(10 * pairs.length / moves)));
              setTimeout(function () { finish(game, nota, 10, artsOf(pairs.map(function (p) { return p.e; })), 'Lo has completado en ' + moves + ' intentos.'); }, 600);
            }
          } else {
            lock = true;
            setTimeout(function () {
              x.b.classList.remove('is-up'); y.b.classList.remove('is-up');
              x.b.setAttribute('aria-label', 'Carta oculta'); y.b.setAttribute('aria-label', 'Carta oculta');
              open = []; lock = false;
            }, 1100);
          }
        }
      });
      grid.appendChild(b);
    });
  }

  /* ================= JUEGO: AHORCADO ================= */
  function runHangman(box, game, list, count) {
    var words = shuffle(list).slice(0, count || 6);
    var idx = 0, score = 0, fallos = [];
    var head = el('div', 'jg-status');
    var count = el('span', 'jg-count'); var pts = el('span', 'jg-points');
    head.appendChild(count); head.appendChild(pts);
    var stage = el('div', 'jg-stage');
    box.appendChild(head); box.appendChild(stage);
    var keyHandler = null;

    function round() {
      var item = words[idx];
      var target = norm(item.w);
      var guessed = {}, lives = 6, over = false;
      count.textContent = 'Palabra ' + (idx + 1) + ' de ' + words.length;
      pts.textContent = score + ' acertada' + (score === 1 ? '' : 's');
      stage.textContent = '';
      var wrap = el('div', 'jg-hang');
      var gallows = el('div', 'jg-lives');
      var livesTxt = el('p', 'jg-lives-txt');
      wrap.appendChild(gallows);
      var right = el('div', 'jg-hang-main');
      right.appendChild(el('p', 'jg-kicker', 'Pista'));
      right.appendChild(el('p', 'jg-clue', item.p));
      var word = el('div', 'jg-word');
      word.setAttribute('aria-live', 'polite');
      right.appendChild(word);
      right.appendChild(livesTxt);
      wrap.appendChild(right);
      stage.appendChild(wrap);
      var kb = el('div', 'jg-keys');
      stage.appendChild(kb);
      var fbSlot = el('div');
      stage.appendChild(fbSlot);

      var slots = [];
      for (var k = 0; k < item.w.length; k++) {
        var ch = item.w[k];
        var s = el('span', ch === ' ' ? 'jg-gap' : 'jg-letter');
        slots.push({ s: s, ch: ch, n: norm(ch) });
        word.appendChild(s);
      }
      var dots = [];
      for (var d = 0; d < 6; d++) { var dot = el('span', 'jg-life'); dots.push(dot); gallows.appendChild(dot); }

      function paint() {
        slots.forEach(function (x) { if (x.ch !== ' ') x.s.textContent = guessed[x.n] || over ? x.ch : ''; if (over && !guessed[x.n] && x.ch !== ' ') x.s.classList.add('is-missed'); });
        dots.forEach(function (dt, n) { dt.classList.toggle('is-lost', n >= lives); });
        livesTxt.textContent = 'Te quedan ' + lives + ' vida' + (lives === 1 ? '' : 's');
      }
      var keys = {};
      'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ'.split('').forEach(function (L) {
        var b = el('button', 'jg-key', L); b.type = 'button';
        b.setAttribute('aria-label', 'Letra ' + L);
        b.addEventListener('click', function () { press(L); });
        keys[L] = b; kb.appendChild(b);
      });
      function press(L) {
        if (over || guessed[L] || !keys[L]) return;
        guessed[L] = true;
        keys[L].disabled = true;
        if (target.indexOf(L) >= 0) keys[L].classList.add('is-ok');
        else { keys[L].classList.add('is-ko'); lives--; }
        var done = slots.every(function (x) { return x.ch === ' ' || guessed[x.n]; });
        if (done || lives <= 0) {
          over = true;
          if (done) score++; else fallos.push(item.e);
          Object.keys(keys).forEach(function (q) { keys[q].disabled = true; });
          pts.textContent = score + ' acertada' + (score === 1 ? '' : 's');
          var fb = el('div', 'jg-feedback ' + (done ? 'ok' : 'ko'));
          fb.setAttribute('role', 'status');
          fb.appendChild(el('strong', null, done ? '¡La tienes!' : 'Se acabaron las vidas.'));
          fb.appendChild(el('span', 'jg-answer', ' La palabra era: ' + item.w + '.'));
          var src = fuente(item.e); if (src) fb.appendChild(src);
          var next = el('button', 'btn btn-primary jg-next', idx + 1 < words.length ? 'Siguiente palabra' : 'Ver resultado');
          next.type = 'button';
          next.addEventListener('click', function () {
            idx++;
            if (idx < words.length) round();
            else { document.removeEventListener('keydown', keyHandler); finish(game, score, words.length, artsOf(fallos)); }
          });
          fb.appendChild(next);
          fbSlot.appendChild(fb);
          next.focus({ preventScroll: true });
        }
        paint();
      }
      if (keyHandler) document.removeEventListener('keydown', keyHandler);
      keyHandler = function (ev) {
        if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
        var L = norm(ev.key || '');
        if (L.length === 1 && keys[L]) { ev.preventDefault(); press(L); }
      };
      document.addEventListener('keydown', keyHandler);
      cleanups.push(function () { document.removeEventListener('keydown', keyHandler); });
      paint();
    }
    round();
  }

  /* ================= JUEGO: SOPA DE LETRAS ================= */
  function runWordSearch(box, game, list) {
    var longest = list.reduce(function (m, x) { return Math.max(m, x.w.length); }, 0);
    var N = Math.min(14, Math.max(10, longest + 1, Math.ceil(Math.sqrt(list.reduce(function (s, x) { return s + x.w.length; }, 0) * 2.2))));
    var DIRS = [[0, 1], [1, 0], [1, 1], [-1, 1]];
    var grid, placed, okAll = false;
    for (var attempt = 0; attempt < 50; attempt++) {
      grid = []; placed = [];
      for (var r = 0; r < N; r++) { grid.push([]); for (var c = 0; c < N; c++) grid[r].push(''); }
      okAll = shuffle(list).every(function (item) {
        var w = item.w;
        for (var t = 0; t < 200; t++) {
          var d = DIRS[Math.floor(Math.random() * DIRS.length)];
          var r0 = Math.floor(Math.random() * N), c0 = Math.floor(Math.random() * N);
          var rEnd = r0 + d[0] * (w.length - 1), cEnd = c0 + d[1] * (w.length - 1);
          if (rEnd < 0 || rEnd >= N || cEnd < 0 || cEnd >= N) continue;
          var fits = true;
          for (var k = 0; k < w.length; k++) {
            var g = grid[r0 + d[0] * k][c0 + d[1] * k];
            if (g !== '' && g !== w[k]) { fits = false; break; }
          }
          if (!fits) continue;
          var cells = [];
          for (k = 0; k < w.length; k++) { grid[r0 + d[0] * k][c0 + d[1] * k] = w[k]; cells.push((r0 + d[0] * k) * N + (c0 + d[1] * k)); }
          placed.push({ w: w, p: item.p, e: item.e, cells: cells });
          return true;
        }
        return false;
      });
      if (okAll) break;
    }
    var ABC = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' + (list.some(function (x) { return x.w.indexOf('Ñ') >= 0; }) ? 'Ñ' : '');
    for (r = 0; r < N; r++) for (c = 0; c < N; c++) if (grid[r][c] === '') grid[r][c] = ABC[Math.floor(Math.random() * ABC.length)];

    var head = el('div', 'jg-status');
    var cnt = el('span', 'jg-count', 'Encontradas: 0 de ' + placed.length);
    head.appendChild(cnt);
    var giveUp = el('button', 'btn btn-small btn-ghost', 'Me rindo');
    giveUp.type = 'button';
    head.appendChild(giveUp);
    box.appendChild(head);
    box.appendChild(el('p', 'jg-help', 'Arrastra de la primera a la última letra (o toca la primera y luego la última). Las palabras van en horizontal, vertical o diagonal.'));
    var wrap = el('div', 'jg-ws');
    var board = el('div', 'jg-ws-grid');
    board.style.setProperty('--n', String(N));
        board.setAttribute('aria-label', 'Sopa de letras');
    var cellsEl = [];
    for (r = 0; r < N; r++) {
      for (c = 0; c < N; c++) {
        var ce = el('span', 'jg-ws-cell', grid[r][c]);
        ce.setAttribute('data-i', String(r * N + c));
        cellsEl.push(ce);
        board.appendChild(ce);
      }
    }
    wrap.appendChild(board);
    var listBox = el('ul', 'jg-ws-list');
    placed.forEach(function (pl) {
      var li = el('li');
      li.appendChild(el('strong', null, pl.w));
      li.appendChild(el('span', null, pl.p));
      pl.li = li;
      listBox.appendChild(li);
    });
    wrap.appendChild(listBox);
    box.appendChild(wrap);
    var fbSlot = el('div'); box.appendChild(fbSlot);

    var found = 0, start = null, tapStart = null, ended = false, moved = false;
    function cellFromPoint(x, y) {
      var t = document.elementFromPoint(x, y);
      return t && t.classList && t.classList.contains('jg-ws-cell') ? parseInt(t.getAttribute('data-i'), 10) : null;
    }
    function line(a, b) {
      var r1 = Math.floor(a / N), c1 = a % N, r2 = Math.floor(b / N), c2 = b % N;
      var dr = Math.sign(r2 - r1), dc = Math.sign(c2 - c1);
      var len = Math.max(Math.abs(r2 - r1), Math.abs(c2 - c1));
      if (!(r1 === r2 || c1 === c2 || Math.abs(r2 - r1) === Math.abs(c2 - c1))) return null;
      var out = [];
      for (var k = 0; k <= len; k++) out.push((r1 + dr * k) * N + (c1 + dc * k));
      return out;
    }
    function clearSel() { cellsEl.forEach(function (x) { x.classList.remove('is-sel'); }); }
    function paintSel(arr) { clearSel(); (arr || []).forEach(function (i) { cellsEl[i].classList.add('is-sel'); }); }
    function check(arr) {
      if (!arr || arr.length < 2) return false;
      var key = arr.join(','), rev = arr.slice().reverse().join(',');
      var hit = null;
      placed.forEach(function (pl) { if (!pl.done && (pl.cells.join(',') === key || pl.cells.join(',') === rev)) hit = pl; });
      if (hit) {
        hit.done = true; found++;
        hit.cells.forEach(function (i) { cellsEl[i].classList.add('is-found'); });
        hit.li.classList.add('is-found');
        cnt.textContent = 'Encontradas: ' + found + ' de ' + placed.length;
        if (found === placed.length) end(false);
        return true;
      }
      return false;
    }
    function end(surrender) {
      if (ended) return; ended = true;
      giveUp.disabled = true;
      placed.forEach(function (pl) { if (!pl.done) pl.cells.forEach(function (i) { cellsEl[i].classList.add('is-reveal'); }); });
      var fb = el('div', 'jg-feedback ' + (surrender ? 'ko' : 'ok'));
      fb.appendChild(el('strong', null, surrender ? 'Te marcamos las que faltaban.' : '¡Sopa completada!'));
      var next = el('button', 'btn btn-primary jg-next', 'Ver resultado');
      next.type = 'button';
      next.addEventListener('click', function () { finish(game, found, placed.length, artsOf(placed.filter(function (pl) { return !pl.done; }).map(function (pl) { return pl.e; }))); });
      fb.appendChild(next);
      fbSlot.appendChild(fb);
    }
    giveUp.addEventListener('click', function () { end(true); });

    board.addEventListener('pointerdown', function (ev) {
      if (ended) return;
      var i = cellFromPoint(ev.clientX, ev.clientY);
      if (i == null) return;
      ev.preventDefault();
      try { board.setPointerCapture(ev.pointerId); } catch (e) {}
      start = i; moved = false;
      paintSel([i]);
    });
    board.addEventListener('pointermove', function (ev) {
      if (start == null) return;
      var i = cellFromPoint(ev.clientX, ev.clientY);
      if (i == null) return;
      if (i !== start) moved = true;
      paintSel(line(start, i) || [start]);
    });
    function up(ev) {
      if (start == null) return;
      var i = cellFromPoint(ev.clientX, ev.clientY);
      var s = start; start = null;
      if (i == null) { clearSel(); return; }
      if (!moved && i === s) {
        // modo toque: primera y última letra
        if (tapStart == null) { tapStart = s; paintSel([s]); cellsEl[s].classList.add('is-anchor'); return; }
        var arr = line(tapStart, s);
        cellsEl[tapStart].classList.remove('is-anchor');
        tapStart = null;
        check(arr); clearSel();
        return;
      }
      if (tapStart != null) { cellsEl[tapStart].classList.remove('is-anchor'); tapStart = null; }
      check(line(s, i)); clearSel();
    }
    board.addEventListener('pointerup', up);
    board.addEventListener('pointercancel', function () { start = null; clearSel(); });
  }

  /* ================= JUEGO: COMPLETA LA FRASE ================= */
  function runCloze(box, game, all, count) {
    var items = shuffle(all).slice(0, count || all.length);
    box.appendChild(el('p', 'jg-help', 'Elige la palabra que falta en cada hueco y pulsa «Comprobar».'));
    var list = el('ol', 'jg-cloze');
    var selects = [];
    items.forEach(function (it) {
      var li = el('li');
      var p = el('p');
      var choices = shuffle(it.r.filter(function (x, i) { return it.r.indexOf(x) === i; }).concat(extra(it)));
      it.s.forEach(function (part) {
        if (typeof part === 'string') { p.appendChild(document.createTextNode(part)); return; }
        var sel = el('select', 'jg-select');
        sel.setAttribute('aria-label', 'Hueco ' + (part + 1));
        sel.appendChild(new Option('…', ''));
        choices.forEach(function (ch) { sel.appendChild(new Option(ch, ch)); });
        selects.push({ sel: sel, ok: it.r[part], a: it.e, li: li });
        p.appendChild(sel);
      });
      li.appendChild(p);
      list.appendChild(li);
    });
    box.appendChild(list);
    function extra(it) {
      // distractores: respuestas de otras frases (todas del blog)
      var pool = [];
      all.forEach(function (h) { if (h !== it) pool = pool.concat(h.r); });
      pool = pool.filter(function (x, i) { return it.r.indexOf(x) < 0 && pool.indexOf(x) === i; });
      return shuffle(pool).slice(0, 2);
    }
    var btn = el('button', 'btn btn-primary', 'Comprobar respuestas');
    btn.type = 'button';
    var fbSlot = el('div');
    box.appendChild(btn); box.appendChild(fbSlot);
    btn.addEventListener('click', function () {
      var score = 0, fallos = [];
      selects.forEach(function (x) {
        var ok = x.sel.value === x.ok;
        if (ok) score++; else fallos.push(x.a);
        x.sel.classList.toggle('is-ok', ok);
        x.sel.classList.toggle('is-ko', !ok);
        x.sel.disabled = true;
        if (!ok) {
          var hint = el('span', 'jg-fix', x.ok);
          x.sel.insertAdjacentElement('afterend', hint);
        }
      });
      btn.disabled = true;
      var fb = el('div', 'jg-feedback ' + (score === selects.length ? 'ok' : 'ko'));
      fb.appendChild(el('strong', null, score + ' de ' + selects.length + ' huecos bien.'));
      var next = el('button', 'btn btn-primary jg-next', 'Ver resultado');
      next.type = 'button';
      next.addEventListener('click', function () { finish(game, score, selects.length, artsOf(fallos)); });
      fb.appendChild(next);
      fbSlot.appendChild(fb);
    });
  }


  /* ================= CATÁLOGO: convierte los datos del panel en juegos ================= */
  function limit(list, n) { return n > 0 ? list.slice(0, n) : list; }

  var BUILDERS = {
    test: function (box, g, d) {
      runRounds(box, g, limit(shuffle(d.items), g.n).map(function (x) {
        return { text: x.q, options: x.o, ok: 0, why: x.why, e: x.e };
      }), { grid: x2(d.items) ? 'two' : '' });
    },
    vf: function (box, g, d) {
      runRounds(box, g, limit(shuffle(d.items), g.n).map(function (x) {
        return { text: x.q, options: ['Verdadero', 'Falso'], ok: x.v ? 0 : 1, why: x.why, e: x.e };
      }), { fixed: true, grid: 'two', unit: 'Afirmación', showAnswer: false });
    },
    clasificar: function (box, g, d) {
      var cats = d.cats || [];
      runRounds(box, g, limit(shuffle(d.items), g.n).map(function (x) {
        return { kicker: '¿A qué grupo pertenece?', text: x.t, options: cats, ok: x.c, why: x.why, e: x.e };
      }), { fixed: true, grid: cats.length === 2 ? 'two' : 'many', unit: 'Elemento' });
    },
    memoria: function (box, g, d) { runMemory(box, g, d.items); },
    ahorcado: function (box, g, d) { runHangman(box, g, d.items, g.n); },
    sopa: function (box, g, d) { runWordSearch(box, g, d.items); },
    completar: function (box, g, d) { runCloze(box, g, d.items, g.n); },
    origen: function (box, g, d) {
      var keys = [];
      d.items.forEach(function (x) { if (ENT[x.e] && keys.indexOf(x.e) < 0) keys.push(x.e); });
      var titles = keys.map(function (k) { return ENT[k].t; });
      runRounds(box, g, limit(shuffle(d.items.filter(function (x) { return ENT[x.e]; })), g.n).map(function (x) {
        return { kicker: '¿En qué entrada aparece?', text: '«' + x.t + '»', options: titles, ok: keys.indexOf(x.e), why: x.why, e: x.e };
      }), { fixed: true, grid: 'many', unit: 'Pista' });
    }
  };
  // test con respuestas cortas: en dos columnas
  function x2(items) {
    return items.every(function (x) { return x.o.every(function (o) { return o.length <= 28; }); });
  }
  var UNITS = { test: 'preguntas', vf: 'afirmaciones', clasificar: 'elementos', memoria: 'parejas', ahorcado: 'palabras', sopa: 'palabras', completar: 'frases', origen: 'pistas' };

  var GAMES = (DATA.juegos || []).filter(function (g) {
    return BUILDERS[g.tipo] && g.data && g.data.items && g.data.items.length;
  }).map(function (g) {
    g.arts = artsOf(g.data.items.map(function (x) { return x.e; })).filter(function (k) { return ENT[k]; });
    g.count = g.data.items.length;
    g.unit = UNITS[g.tipo] || 'elementos';
    g.run = function (box) { BUILDERS[g.tipo](box, g, g.data); };
    return g;
  });
  if (!GAMES.length) return;

  /* ================= INTERFAZ: PORTADA DE JUEGOS Y PANTALLA DE JUEGO ================= */
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var index = el('section', 'jg-index');
  var play = el('section', 'jg-play');
  play.hidden = true;
  root.appendChild(index);
  root.appendChild(play);

  function stars(pct) {
    return pct >= 90 ? 3 : pct >= 60 ? 2 : pct > 0 ? 1 : 0;
  }

  function renderIndex() {
    index.textContent = '';
    var best = loadBest();
    var played = GAMES.filter(function (g) { return best[g.id] != null; }).length;
    var totalStars = GAMES.reduce(function (s, g) { return s + (best[g.id] != null ? stars(best[g.id]) : 0); }, 0);

    var head = el('header', 'feed-head jg-head');
    head.appendChild(el('h2', null, GAMES.length === 1 ? '1 juego para repasar' : GAMES.length + ' juegos para repasar'));
    var summary = el('div', 'jg-summary');
    var s1 = el('p'); s1.appendChild(el('strong', null, played + '/' + GAMES.length)); s1.appendChild(document.createTextNode(' juegos jugados'));
    var s2 = el('p'); s2.appendChild(el('strong', null, totalStars + '/' + GAMES.length * 3)); s2.appendChild(document.createTextNode(' estrellas'));
    summary.appendChild(s1); summary.appendChild(s2);
    head.appendChild(summary);
    index.appendChild(head);
    index.appendChild(el('p', 'jg-note', 'Las preguntas salen de las entradas del blog. Tu mejor resultado se guarda solo en este navegador.'));

    var grid = el('ul', 'jg-grid');
    GAMES.forEach(function (g, n) {
      var li = el('li', 'jg-card tone-' + (n % 6));
      var art = el('span', 'jg-card-icon', g.icono);
      art.setAttribute('aria-hidden', 'true');
      li.appendChild(art);
      var body = el('div', 'jg-card-body');
      body.appendChild(el('span', 'jg-type', g.tipoNombre));
      var h = el('h3');
      var a = el('a', 'stretched', g.titulo);
      a.href = '#' + g.id;
      h.appendChild(a);
      body.appendChild(h);
      if (g.desc) body.appendChild(el('p', null, g.desc));
      var foot = el('div', 'jg-card-foot');
      var arts = g.arts.length;
      foot.appendChild(el('span', 'jg-from', arts ? 'Sale de ' + arts + ' entrada' + (arts === 1 ? '' : 's') : g.count + ' ' + g.unit));
      var b = best[g.id];
      var st = el('span', 'jg-stars' + (b == null ? ' is-new' : ''), b == null ? 'Sin jugar' : '★★★'.slice(0, stars(b)) + '☆☆☆'.slice(0, 3 - stars(b)) + '  ' + b + '%');
      foot.appendChild(st);
      body.appendChild(foot);
      li.appendChild(body);
      grid.appendChild(li);
    });
    index.appendChild(grid);
  }

  function openGame(g) {
    cleanups.forEach(function (f) { f(); }); cleanups = [];
    index.hidden = true;
    play.hidden = false;
    play.textContent = '';
    var bar = el('div', 'jg-play-head');
    var back = el('a', 'link-back', 'Todos los juegos');
    back.href = '#';
    bar.appendChild(back);
    play.appendChild(bar);
    var title = el('div', 'jg-play-title');
    title.appendChild(el('span', 'jg-type', g.tipoNombre));
    var h = el('h2', null, g.titulo);
    h.tabIndex = -1;
    title.appendChild(h);
    if (g.desc) title.appendChild(el('p', null, g.desc));
    play.appendChild(title);
    var box = el('div', 'jg-box');
    play.appendChild(box);
    g.run(box, g);
    play.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    h.focus({ preventScroll: true });
  }

  function finish(g, score, max, repaso, extraTxt) {
    cleanups.forEach(function (f) { f(); }); cleanups = [];
    var pct = max ? Math.round(score / max * 100) : 0;
    var record = saveBest(g.id, pct);
    var box = play.querySelector('.jg-box');
    box.textContent = '';
    var res = el('div', 'jg-result');
    var ring = el('div', 'jg-ring');
    ring.style.setProperty('--p', String(pct));
    ring.appendChild(el('span', null, pct + '%'));
    res.appendChild(ring);
    var txt = el('div', 'jg-result-txt');
    var n = stars(pct);
    txt.appendChild(el('p', 'jg-result-stars', '★★★'.slice(0, n) + '☆☆☆'.slice(0, 3 - n)));
    txt.appendChild(el('h3', null, pct >= 90 ? '¡Eres un experto del blog!' : pct >= 60 ? '¡Muy bien!' : pct >= 30 ? 'Vas por buen camino' : 'Toca repasar'));
    txt.appendChild(el('p', null, 'Has conseguido ' + score + ' de ' + max + (g.tipo === 'memoria' ? ' puntos.' : '.') + (extraTxt ? ' ' + extraTxt : '')));
    if (record) txt.appendChild(el('p', 'jg-record', '¡Nuevo récord en este navegador!'));
    var reps = (repaso || []).filter(function (k) { return k && ENT[k]; });
    if (reps.length && pct < 100) {
      txt.appendChild(el('p', 'jg-kicker', 'Para repasar'));
      var ul = el('ul', 'jg-review');
      reps.forEach(function (k) { var li = el('li'); var a = el('a', null, ENT[k].t); a.href = ENT[k].u; li.appendChild(a); ul.appendChild(li); });
      txt.appendChild(ul);
    }
    var actions = el('div', 'jg-actions');
    var again = el('button', 'btn btn-primary', 'Jugar otra vez');
    again.type = 'button';
    again.addEventListener('click', function () { openGame(g); });
    var other = el('a', 'btn', 'Elegir otro juego');
    other.href = '#';
    actions.appendChild(again); actions.appendChild(other);
    if (GAMES.length > 1) {
      var nextG = GAMES[(GAMES.indexOf(g) + 1) % GAMES.length];
      var nx = el('a', 'btn btn-ghost', 'Siguiente: ' + nextG.titulo + ' →');
      nx.href = '#' + nextG.id;
      actions.appendChild(nx);
    }
    txt.appendChild(actions);
    res.appendChild(txt);
    box.appendChild(res);
    again.focus({ preventScroll: true });
  }

  function route() {
    var id = (location.hash || '').replace('#', '');
    var g = null;
    GAMES.forEach(function (x) { if (x.id === id) g = x; });
    if (g) { openGame(g); return; }
    cleanups.forEach(function (f) { f(); }); cleanups = [];
    play.hidden = true; play.textContent = '';
    index.hidden = false;
    renderIndex();
  }
  window.addEventListener('hashchange', route);
  route();
})();

