/* =============================================================
   Animaciones e interacción de la parte pública.
   Todo es mejora progresiva: sin JS la web se ve y funciona entera.
   Respeta "reducir movimiento" del sistema operativo.
   ============================================================= */
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  /* ---------- Modo claro / oscuro ---------- */
  function current() {
    return root.getAttribute('data-theme') || (media && media.matches ? 'dark' : 'light');
  }
  function label(btn) {
    var dark = current() === 'dark';
    btn.setAttribute('aria-label', dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
  }
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.hidden = false;
    label(btn);
    btn.addEventListener('click', function () {
      var next = current() === 'dark' ? 'light' : 'dark';
      root.classList.add('theme-anim');
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('tema', next); } catch (e) {}
      document.querySelectorAll('[data-theme-toggle]').forEach(label);
      setTimeout(function () { root.classList.remove('theme-anim'); }, 450);
    });
  });

  /* ---------- Cabecera compacta, barra de lectura y botón "arriba" ---------- */
  var header = document.querySelector('[data-header]');
  var toTop = document.querySelector('[data-to-top]');
  var progress = document.querySelector('[data-progress]');
  var reading = document.querySelector('[data-reading]');
  var bar = progress ? progress.querySelector('span') : null;
  if (progress && reading) progress.classList.add('on');

  var ticking = false;
  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    if (header) header.classList.toggle('scrolled', y > 12);
    if (toTop) toTop.classList.toggle('show', y > 700);
    if (bar && reading) {
      var start = reading.offsetTop - window.innerHeight * 0.3;
      var total = reading.offsetHeight - window.innerHeight * 0.5;
      var p = Math.min(1, Math.max(0, (y - start) / Math.max(1, total)));
      bar.style.transform = 'scaleX(' + p.toFixed(4) + ')';
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; window.requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();

  if (toTop) {
    toTop.hidden = false;
    toTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
      var main = document.getElementById('contenido');
      if (main) main.focus({ preventScroll: true });
    });
  }

  /* ---------- Aparición al hacer scroll ---------- */
  var items = document.querySelectorAll('.reveal');
  if (reduce || !('IntersectionObserver' in window)) {
    items.forEach(function (el) { el.classList.add('in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach(function (el, i) {
      // pequeño escalonado entre tarjetas de la misma fila
      el.style.setProperty('--d', (i % 3) * 80 + 'ms');
      io.observe(el);
    });
  }

  /* ---------- Contadores del hero ---------- */
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    if (reduce || target === 0) return;
    var t0 = null, dur = 1100;
    el.textContent = '0';
    function step(ts) {
      if (!t0) t0 = ts;
      var k = Math.min(1, (ts - t0) / dur);
      el.textContent = String(Math.round(target * (1 - Math.pow(1 - k, 3))));
      if (k < 1) window.requestAnimationFrame(step);
    }
    setTimeout(function () { window.requestAnimationFrame(step); }, 500);
  });

  /* ---------- Luz que sigue al ratón en las tarjetas ---------- */
  if (!reduce && window.matchMedia('(hover: hover)').matches) {
    document.querySelectorAll('[data-spot]').forEach(function (card) {
      card.addEventListener('pointermove', function (ev) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
        card.style.setProperty('--my', (ev.clientY - r.top) + 'px');
      });
    });
  }
})();
