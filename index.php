<?php
/**
 * Portada: últimas entradas, filtro por categoría o etiqueta, buscador y paginación.
 * Consultas por página: texto de portada, recuento, listado, etiquetas del listado,
 * categorías y etiquetas de la barra lateral (6 en total, todas con índices).
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$q = input_str($_GET, 'q', 100);
$catSlug = input_str($_GET, 'categoria', 100);
$tagSlug = input_str($_GET, 'etiqueta', 80);
$page = get_int('pagina');
$per = (int) cfg('posts_per_page');

$where = ["e.estado = 'publicada'"];
$params = [];
$cat = $tag = null;

if ($catSlug !== '') {
    $cat = q_one('SELECT id, nombre, slug, descripcion FROM categorias WHERE slug = ?', [$catSlug]);
    if (!$cat) {
        render_404();
    }
    $where[] = 'e.categoria_id = ?';
    $params[] = (int) $cat['id'];
}
if ($tagSlug !== '') {
    $tag = q_one('SELECT id, nombre, slug FROM etiquetas WHERE slug = ?', [$tagSlug]);
    if (!$tag) {
        render_404();
    }
    $where[] = 'EXISTS (SELECT 1 FROM entrada_etiqueta ee WHERE ee.entrada_id = e.id AND ee.etiqueta_id = ?)';
    $params[] = (int) $tag['id'];
}
if ($q !== '') {
    $like = like_escape($q);
    $where[] = '(e.titulo LIKE ? OR e.resumen LIKE ? OR e.contenido LIKE ?)';
    array_push($params, $like, $like, $like);
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int) q_val("SELECT COUNT(*) FROM entradas e $whereSql", $params);
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
}

// Las fijadas van primero solo en el listado general (en categorías, etiquetas y búsquedas manda la fecha).
$isGeneral = !$cat && !$tag && $q === '';
$order = $isGeneral ? 'e.fijada DESC, e.publicada_en DESC, e.id DESC' : 'e.publicada_en DESC, e.id DESC';

$posts = q_all("SELECT e.id, e.titulo, e.slug, e.resumen, e.imagen, e.imagen_alt, e.publicada_en, e.fijada,
                       u.nombre autor, c.nombre categoria, c.slug categoria_slug
                FROM entradas e
                JOIN usuarios u ON u.id = e.autor_id
                LEFT JOIN categorias c ON c.id = e.categoria_id
                $whereSql
                ORDER BY $order
                LIMIT ? OFFSET ?", array_merge($params, [$per, ($page - 1) * $per]));

// Etiquetas de las entradas del listado en UNA sola consulta
$tagsByPost = [];
if ($posts) {
    $ids = array_map('intval', array_column($posts, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q_all("SELECT ee.entrada_id, t.nombre, t.slug FROM entrada_etiqueta ee JOIN etiquetas t ON t.id = ee.etiqueta_id
                    WHERE ee.entrada_id IN ($in) ORDER BY t.nombre", $ids) as $t) {
        $tagsByPost[(int) $t['entrada_id']][] = $t;
    }
}

$sideCats = q_all("SELECT c.nombre, c.slug, COUNT(e.id) n FROM categorias c
                   JOIN entradas e ON e.categoria_id = c.id AND e.estado = 'publicada'
                   GROUP BY c.id, c.nombre, c.slug ORDER BY c.nombre");
$sideTags = q_all("SELECT t.nombre, t.slug, COUNT(*) n FROM etiquetas t
                   JOIN entrada_etiqueta ee ON ee.etiqueta_id = t.id
                   JOIN entradas e ON e.id = ee.entrada_id AND e.estado = 'publicada'
                   GROUP BY t.id, t.nombre, t.slug ORDER BY n DESC, t.nombre LIMIT 30");

$isHome = !$cat && !$tag && $q === '' && $page === 1;
$intro = $isHome ? get_pagina('portada') : null;

// Título y URL base de la vista actual (para la paginación)
if ($cat) {
    $viewTitle = 'Categoría: ' . $cat['nombre'];
    $baseUrl = url_cat($cat['slug']);
} elseif ($tag) {
    $viewTitle = 'Etiqueta: ' . $tag['nombre'];
    $baseUrl = url_tag($tag['slug']);
} else {
    $viewTitle = $q !== '' ? 'Resultados para «' . $q . '»' : 'Últimas entradas';
    $baseUrl = url();
}
$pageUrl = static fn(int $p) => with_query($baseUrl, ['q' => ($cat || $tag) ? '' : $q, 'pagina' => $p]);

// Fijadas aparte (bloque destacado) solo en la primera página del listado general
$featured = [];
$rest = $posts;
if ($isGeneral && $page === 1) {
    $featured = array_values(array_filter($posts, static fn($p) => (int) $p['fijada'] === 1));
    $rest = array_values(array_filter($posts, static fn($p) => (int) $p['fijada'] !== 1));
}
$hTag = $isHome ? 'h3' : 'h2';

/** Pinta una tarjeta de entrada. */
$card = static function (array $p, bool $big = false) use ($tagsByPost, $hTag): void {
    $date = $p['publicada_en'];
    $tags = $tagsByPost[(int) $p['id']] ?? [];
    ?>
    <article class="card<?= $big ? ' card-big' : '' ?> tone-<?= tone((int) $p['id']) ?> reveal" data-spot>
      <div class="card-media" aria-hidden="true">
        <?php if ($p['imagen'] && upload_exists($p['imagen'])): ?>
          <img src="<?= e(url_upload($p['imagen'])) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
          <span class="card-art"><span></span></span>
        <?php endif; ?>
        <?php if ($big): ?>
          <span class="pin-chip"><svg viewBox="0 0 24 24" focusable="false"><path d="M15 3l6 6-3 1-4 4 .5 4.5L13 20l-4-4-5 5-1-1 5-5-4-4 1.5-1.5L10 10l4-4z"/></svg>Fijada</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="card-top">
          <?php if ($p['categoria']): ?><a class="chip" href="<?= e(url_cat($p['categoria_slug'])) ?>"><?= e($p['categoria']) ?></a><?php endif; ?>
          <time datetime="<?= e(date('Y-m-d', strtotime($date))) ?>"><?= e(fecha_es($date)) ?></time>
        </div>
        <<?= $hTag ?> class="card-title"><a class="stretched" href="<?= e(url_post($p['slug'])) ?>"><?= e($p['titulo']) ?></a></<?= $hTag ?>>
        <?php if ($p['resumen']): ?><p class="card-summary"><?= e($p['resumen']) ?></p><?php endif; ?>
        <div class="card-foot">
          <span class="author"><span class="avatar" aria-hidden="true"><?= e(initials($p['autor'])) ?></span><?= e($p['autor']) ?></span>
          <?php if ($tags): ?>
            <ul class="tags" aria-label="Etiquetas">
              <?php foreach (array_slice($tags, 0, 3) as $t): ?><li><a href="<?= e(url_tag($t['slug'])) ?>">#<?= e($t['nombre']) ?></a></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
      <span class="card-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
    </article>
    <?php
};

$topics = ['Industria 4.0', 'IoT industrial', 'Automatización', 'Cloud', 'Big data', 'Ciberseguridad industrial', 'ERP y MES', 'Gemelos digitales', 'Robótica', 'Redes industriales'];

$docTitle = $isHome ? (string) cfg('site_name') : $viewTitle . ($page > 1 ? " (página $page)" : '');
public_header($docTitle, $intro ? md_plain($intro['contenido'], 160) : '', $isHome ? 'inicio' : '');
?>
<?php if ($isHome): ?>
<section class="hero" aria-labelledby="hero-title">
  <div class="hero-bg" aria-hidden="true">
    <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
    <span class="hero-mesh"></span>
  </div>
  <div class="wrap hero-grid">
    <div class="hero-text">
      <h1 id="hero-title" class="hero-title"><?php foreach (preg_split('/\s+/u', (string) cfg('site_name')) as $w): ?><span class="w"><span><?= e($w) ?></span></span> <?php endforeach; ?></h1>
      <?php if ($intro && trim($intro['contenido']) !== ''): ?>
        <div class="hero-intro"><?= md_to_html($intro['contenido']) ?></div>
      <?php endif; ?>
      <div class="hero-actions">
        <a class="btn btn-glow" href="#feed">Leer las entradas</a>
        <a class="btn btn-outline" href="<?= e(url_sobre()) ?>">La asignatura</a>
      </div>
      <dl class="hero-stats">
        <div><dt>Entradas</dt><dd data-count="<?= $total ?>"><?= $total ?></dd></div>
        <div><dt>Categorías</dt><dd data-count="<?= count($sideCats) ?>"><?= count($sideCats) ?></dd></div>
        <div><dt>Etiquetas</dt><dd data-count="<?= count($sideTags) ?>"><?= count($sideTags) ?></dd></div>
      </dl>
    </div>
    <figure class="hero-line" role="img" aria-label="Esquema animado de una planta conectada: sensores, PLC, sistema MES, ERP y nube intercambiando datos">
      <svg viewBox="0 0 420 300" aria-hidden="true" focusable="false">
        <defs>
          <linearGradient id="g-bus" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#2fc4d0"/><stop offset="1" stop-color="#7be3ea"/></linearGradient>
          <filter id="glow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
        </defs>
        <g class="grid-lines"><path d="M0 50H420M0 100H420M0 150H420M0 200H420M0 250H420M70 0V300M140 0V300M210 0V300M280 0V300M350 0V300"/></g>
        <path class="bus" d="M40 240 H120 V170 H210 V100 H300 V50 H380"/>
        <path class="bus-pulse p1" filter="url(#glow)" d="M40 240 H120 V170 H210 V100 H300 V50 H380"/>
        <path class="bus-pulse p2" filter="url(#glow)" d="M40 240 H120 V170 H210 V100 H300 V50 H380"/>
        <path class="bus-pulse p3" filter="url(#glow)" d="M40 240 H120 V170 H210 V100 H300 V50 H380"/>
        <g class="node n1"><circle class="ring" cx="40" cy="240" r="30"/><rect x="16" y="222" width="48" height="36" rx="6"/><text x="40" y="245">Sensor</text></g>
        <g class="node n2"><circle class="ring" cx="120" cy="170" r="30"/><rect x="96" y="152" width="48" height="36" rx="6"/><text x="120" y="175">PLC</text></g>
        <g class="node n3"><circle class="ring" cx="210" cy="100" r="30"/><rect x="186" y="82" width="48" height="36" rx="6"/><text x="210" y="105">MES</text></g>
        <g class="node n4"><circle class="ring" cx="300" cy="50" r="30"/><rect x="276" y="32" width="48" height="36" rx="6"/><text x="300" y="55">ERP</text></g>
        <g class="node node-cloud n5"><path d="M358 62a12 12 0 0 1 4-23 15 15 0 0 1 28-2 11 11 0 0 1 4 25z"/><text x="378" y="82">Nube</text></g>
        <g class="gear-mark"><circle cx="72" cy="120" r="22"/><circle cx="72" cy="120" r="8"/></g>
        <g class="levels"><text x="404" y="252">Campo</text><text x="404" y="182">Control</text><text x="404" y="128">Operaciones</text><text x="404" y="18">Empresa</text></g>
      </svg>
    </figure>
  </div>
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track">
      <?php for ($k = 0; $k < 2; $k++): foreach ($topics as $t): ?><span><?= e($t) ?></span><?php endforeach; endfor; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="wrap layout" id="feed">
  <section class="feed" aria-labelledby="feed-title">
    <header class="feed-head reveal">
      <<?= $isHome ? 'h2' : 'h1' ?> id="feed-title"><?= e($viewTitle) ?></<?= $isHome ? 'h2' : 'h1' ?>>
      <?php if ($cat && $cat['descripcion']): ?><p class="feed-desc"><?= e($cat['descripcion']) ?></p><?php endif; ?>
      <?php if (!$isHome && $total > 0): ?><p class="feed-count"><?= $total ?> entrada<?= $total === 1 ? '' : 's' ?></p><?php endif; ?>
      <?php if ($cat || $tag || $q !== ''): ?><p><a class="link-arrow" href="<?= e(url()) ?>">Ver todas las entradas</a></p><?php endif; ?>
    </header>

    <?php if (!$posts): ?>
      <div class="empty-state reveal">
        <div class="empty-icon" aria-hidden="true">
          <svg viewBox="0 0 64 64" focusable="false"><circle cx="32" cy="32" r="13"/><path d="M32 6v10M32 48v10M6 32h10M48 32h10M13.6 13.6l7 7M43.4 43.4l7 7M13.6 50.4l7-7M43.4 20.6l7-7"/></svg>
        </div>
        <?php if ($q !== ''): ?>
          <h2>Nada coincide con «<?= e($q) ?>»</h2>
          <p>Prueba con otra palabra, por ejemplo IoT, ERP o ciberseguridad.</p>
        <?php elseif ($cat || $tag): ?>
          <h2>Todavía no hay entradas aquí</h2>
          <p>Vuelve más adelante o mira <a href="<?= e(url()) ?>">todas las entradas</a>.</p>
        <?php else: ?>
          <h2>Aún no hay publicaciones</h2>
          <p>La línea está montada y esperando datos. Las primeras entradas del módulo aparecerán aquí muy pronto.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php if ($featured): ?>
        <div class="featured" aria-label="Entradas fijadas">
          <?php foreach ($featured as $p) { $card($p, true); } ?>
        </div>
      <?php endif; ?>
      <?php if ($rest): ?>
        <div class="card-grid">
          <?php foreach ($rest as $p) { $card($p); } ?>
        </div>
      <?php endif; ?>
      <?= pagination($page, $pages, $pageUrl) ?>
    <?php endif; ?>
  </section>

  <aside class="sidebar" aria-label="Explorar el blog">
    <form class="side-card search reveal" role="search" method="get" action="<?= e(url()) ?>">
      <label for="q">Buscar en el blog</label>
      <div class="search-row">
        <svg class="search-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
        <input id="q" name="q" type="search" maxlength="100" value="<?= e($q) ?>" placeholder="IoT, PLC, gemelo digital…">
        <button type="submit" class="btn btn-primary">Buscar</button>
      </div>
    </form>

    <?php if ($sideCats): ?>
    <nav class="side-card reveal" aria-labelledby="side-cats">
      <h2 id="side-cats">Categorías</h2>
      <ul class="cat-list">
        <?php foreach ($sideCats as $c): ?>
          <li><a href="<?= e(url_cat($c['slug'])) ?>"<?= $cat && $cat['slug'] === $c['slug'] ? ' aria-current="page"' : '' ?>><span><?= e($c['nombre']) ?></span><span class="n" aria-label="<?= (int) $c['n'] ?> entradas"><?= (int) $c['n'] ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>

    <?php if ($sideTags): ?>
    <nav class="side-card reveal" aria-labelledby="side-tags">
      <h2 id="side-tags">Etiquetas</h2>
      <ul class="tags">
        <?php foreach ($sideTags as $t): ?>
          <li><a href="<?= e(url_tag($t['slug'])) ?>"<?= $tag && $tag['slug'] === $t['slug'] ? ' aria-current="page"' : '' ?>>#<?= e($t['nombre']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </aside>
</div>
<?php
public_footer();
