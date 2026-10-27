/* =============================================================
   Panel de administración: mejoras con JavaScript.
   Todo es opcional: sin JS los formularios funcionan igual
   (la vista previa se hace con el botón "Vista previa", en el servidor).
   No hay ninguna petición fetch/AJAX: el anti-bot de InfinityFree podría bloquearla.
   ============================================================= */
(function () {
  'use strict';

  /* ---------- QR de la verificación en dos pasos (se genera en el navegador, sin servicios externos) ---------- */
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    if (typeof window.qrcode !== 'function') return;
    try {
      var qr = window.qrcode(0, 'M');
      qr.addData(el.getAttribute('data-qr'));
      qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 3, scalable: true });
    } catch (e) { /* si falla, queda la clave manual */ }
  });

  /* ---------- Confirmación antes de borrar ---------- */
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  /* ---------- Seleccionar el código de una imagen al hacer clic ---------- */
  document.querySelectorAll('input[readonly]').forEach(function (i) {
    i.addEventListener('focus', function () { i.select(); });
  });

  /* ---------- Slug sugerido a partir del título ---------- */
  var titleIn = document.querySelector('[data-slug-source]');
  var slugIn = document.querySelector('[data-slug-target]');
  function slugify(s) {
    return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/ñ/g, 'n').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 200);
  }
  if (titleIn && slugIn) {
    var sync = function () { slugIn.placeholder = slugify(titleIn.value) || 'se-crea-a-partir-del-titulo'; };
    titleIn.addEventListener('input', sync);
    sync();
  }

  /* ---------- Markdown -> HTML (misma lógica que includes/markdown.php) ---------- */
  var BASE = '';
  function esc(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  function unesc(s) {
    return s.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>').replace(/&amp;/g, '&');
  }
  function safeUrl(escaped, isImage) {
    var raw = unesc(escaped).trim();
    if (!raw || /[\s\x00-\x1F\x7F]/.test(raw)) return null;
    var m = raw.match(/^([a-z][a-z0-9+.\-]*):/i);
    if (m) {
      var ok = isImage ? ['https', 'http'] : ['https', 'http', 'mailto'];
      if (ok.indexOf(m[1].toLowerCase()) === -1) return null;
    } else if (raw.indexOf('//') === 0) {
      return null;
    } else if (!/^[\/#?]/.test(raw)) {
      raw = BASE + '/' + raw;
    }
    return esc(raw);
  }
  function emphasis(t) {
    return t.replace(/\*\*(?=\S)([\s\S]+?)(?<=\S)\*\*/g, '<strong>$1</strong>')
      .replace(/(?<![*\w])\*(?=\S)([\s\S]+?)(?<=\S)\*(?![*\w])/g, '<em>$1</em>')
      .replace(/(?<![\p{L}\p{N}_])_(?=\S)([\s\S]+?)(?<=\S)_(?![\p{L}\p{N}_])/gu, '<em>$1</em>')
      .replace(/~~(?=\S)([\s\S]+?)(?<=\S)~~/g, '<del>$1</del>');
  }
  function inline(text) {
    var t = esc(text), store = [];
    function hold(h) { store.push(h); return '\x1A' + (store.length - 1) + '\x1A'; }
    t = t.replace(/`([^`\n]+)`/g, function (_, c) { return hold('<code>' + c + '</code>'); });
    t = t.replace(/!\[([^\]\n]*)\]\(([^)\s]+)\)/g, function (all, alt, u) {
      var s = safeUrl(u, true);
      return s === null ? all : hold('<img src="' + s + '" alt="' + alt + '">');
    });
    t = t.replace(/\[([^\]\n]+)\]\(([^)\s]+)\)/g, function (all, label, u) {
      var s = safeUrl(u, false);
      return s === null ? all : hold('<a href="' + s + '">' + emphasis(label) + '</a>');
    });
    t = emphasis(t).replace(/ {2,}\n/g, '<br>\n');
    for (var k = 0; k < 3 && t.indexOf('\x1A') > -1; k++) {
      t = t.replace(/\x1A(\d+)\x1A/g, function (_, i) { return store[+i] || ''; });
    }
    return t;
  }
  function cells(row) { return row.trim().replace(/^\||\|$/g, '').split('|').map(function (c) { return c.trim(); }); }
  var LIST = /^\s{0,3}([-*+]|\d{1,9}[.)])\s+(.*)$/;
  var SEP = /^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/;
  function blocks(lines) {
    var out = [], para = [], n = lines.length, i, m;
    function flush() { if (para.length) { out.push('<p>' + inline(para.join('\n')) + '</p>'); para = []; } }
    for (i = 0; i < n; i++) {
      var line = lines[i], tr = line.trim();
      if (tr === '') { flush(); continue; }
      if ((m = tr.match(/^```\s*([a-z0-9+#.\-]*)\s*$/i))) {
        flush();
        var code = [];
        for (i++; i < n && !/^```\s*$/.test(lines[i].trim()); i++) code.push(lines[i]);
        out.push('<pre><code>' + esc(code.join('\n')) + '</code></pre>');
        continue;
      }
      if ((m = tr.match(/^(#{1,6})\s+(.+?)\s*#*$/))) {
        flush();
        var lvl = Math.max(2, m[1].length);
        out.push('<h' + lvl + '>' + inline(m[2]) + '</h' + lvl + '>');
        continue;
      }
      if (/^([-*_])(\s*\1){2,}$/.test(tr)) { flush(); out.push('<hr>'); continue; }
      if (tr.charAt(0) === '>') {
        flush();
        var q = [];
        while (i < n && lines[i].trim().charAt(0) === '>') { q.push(lines[i].replace(/^\s*>\s?/, '')); i++; }
        i--;
        out.push('<blockquote>' + blocks(q) + '</blockquote>');
        continue;
      }
      if (tr.indexOf('|') > -1 && i + 1 < n && SEP.test(lines[i + 1].trim())) {
        flush();
        var head = cells(tr), h = '<div class="tabla"><table><thead><tr>';
        head.forEach(function (c) { h += '<th>' + inline(c) + '</th>'; });
        h += '</tr></thead><tbody>';
        for (i += 2; i < n && lines[i].indexOf('|') > -1 && lines[i].trim() !== ''; i++) {
          var r = cells(lines[i]);
          h += '<tr>';
          for (var c = 0; c < head.length; c++) h += '<td>' + inline(r[c] || '') + '</td>';
          h += '</tr>';
        }
        i--;
        out.push(h + '</tbody></table></div>');
        continue;
      }
      if ((m = line.match(LIST))) {
        flush();
        var ordered = /^\d/.test(m[1]), items = [], start = ordered ? parseInt(m[1], 10) : 1, mm;
        while (i < n) {
          var l = lines[i];
          if ((mm = l.match(LIST)) && /^\d/.test(mm[1]) === ordered) items.push(mm[2]);
          else if (items.length && l.trim() !== '' && /^\s{2,}\S/.test(l)) items[items.length - 1] += '\n' + l.trim();
          else break;
          i++;
        }
        i--;
        var tag = ordered ? 'ol' : 'ul';
        out.push('<' + tag + (ordered && start !== 1 ? ' start="' + start + '"' : '') + '>' +
          items.map(function (it) { return '<li>' + inline(it) + '</li>'; }).join('') + '</' + tag + '>');
        continue;
      }
      para.push(line);
    }
    flush();
    return out.join('\n');
  }
  function mdToHtml(src) {
    src = src.replace(/\r\n?/g, '\n').replace(/\t/g, '    ').replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
    return blocks(src.split('\n'));
  }

  /* ---------- Editor: barra de formato + pestañas Escribir / Vista previa ---------- */
  document.querySelectorAll('[data-md-editor]').forEach(function (box) {
    var ta = box.querySelector('textarea');
    if (!ta) return;
    BASE = ta.getAttribute('data-base') || '';

    function wrap(before, after, placeholder) {
      var s = ta.selectionStart, e = ta.selectionEnd;
      var sel = ta.value.slice(s, e) || placeholder;
      ta.setRangeText(before + sel + after, s, e, 'end');
      ta.setSelectionRange(s + before.length, s + before.length + sel.length);
      ta.focus();
    }
    function prefixLines(prefix) {
      var s = ta.selectionStart, e = ta.selectionEnd;
      var startLine = ta.value.lastIndexOf('\n', s - 1) + 1;
      var chunk = ta.value.slice(startLine, e) || 'texto';
      var res = chunk.split('\n').map(function (l, idx) {
        return (typeof prefix === 'function' ? prefix(idx) : prefix) + l;
      }).join('\n');
      ta.setRangeText(res, startLine, e, 'select');
      ta.focus();
    }

    var tools = [
      ['Negrita', 'N', function () { wrap('**', '**', 'texto'); }],
      ['Cursiva', 'C', function () { wrap('*', '*', 'texto'); }],
      ['Subtítulo', 'H', function () { prefixLines('## '); }],
      ['Lista', '•', function () { prefixLines('- '); }],
      ['Lista numerada', '1.', function () { prefixLines(function (i) { return (i + 1) + '. '; }); }],
      ['Cita', '❝', function () { prefixLines('> '); }],
      ['Enlace', '🔗', function () { wrap('[', '](https://)', 'texto del enlace'); }],
      ['Código', '</>', function () { wrap('`', '`', 'código'); }]
    ];

    var bar = document.createElement('div');
    bar.className = 'md-toolbar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Formato del texto');

    var tabs = document.createElement('div');
    tabs.className = 'md-tabs';
    var tWrite = document.createElement('button');
    var tPrev = document.createElement('button');
    [tWrite, tPrev].forEach(function (b) { b.type = 'button'; b.className = 'md-tab'; });
    tWrite.textContent = 'Escribir';
    tPrev.textContent = 'Vista previa';
    tWrite.setAttribute('aria-pressed', 'true');
    tPrev.setAttribute('aria-pressed', 'false');
    tabs.appendChild(tWrite);
    tabs.appendChild(tPrev);
    bar.appendChild(tabs);

    var group = document.createElement('div');
    group.className = 'md-tools';
    tools.forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'md-tool';
      b.textContent = t[1];
      b.title = t[0];
      b.setAttribute('aria-label', t[0]);
      b.addEventListener('click', t[2]);
      group.appendChild(b);
    });
    bar.appendChild(group);

    var prev = document.createElement('div');
    prev.className = 'md-preview prose';
    prev.hidden = true;
    prev.setAttribute('aria-live', 'polite');

    box.insertBefore(bar, ta);
    box.appendChild(prev);

    function show(preview) {
      if (preview) {
        prev.innerHTML = mdToHtml(ta.value) || '<p class="muted">Nada que previsualizar todavía.</p>';
        prev.style.minHeight = ta.offsetHeight + 'px';
      }
      prev.hidden = !preview;
      ta.hidden = preview;
      group.hidden = preview;
      tWrite.setAttribute('aria-pressed', preview ? 'false' : 'true');
      tPrev.setAttribute('aria-pressed', preview ? 'true' : 'false');
    }
    tWrite.addEventListener('click', function () { show(false); ta.focus(); });
    tPrev.addEventListener('click', function () { show(true); });

    // Atajos de teclado: Ctrl/Cmd + B (negrita) e I (cursiva)
    ta.addEventListener('keydown', function (ev) {
      if (!(ev.ctrlKey || ev.metaKey)) return;
      var k = ev.key.toLowerCase();
      if (k === 'b') { ev.preventDefault(); wrap('**', '**', 'texto'); }
      if (k === 'i') { ev.preventDefault(); wrap('*', '*', 'texto'); }
    });

    // Botones "Insertar" de la lista de imágenes
    document.querySelectorAll('[data-insert]').forEach(function (b) {
      b.hidden = false;
      b.addEventListener('click', function () {
        show(false);
        var s = ta.selectionStart;
        ta.setRangeText('\n' + b.getAttribute('data-insert') + '\n', s, ta.selectionEnd, 'end');
        ta.focus();
      });
    });
  });
})();
