<?php
/**
 * Plantillas HTML compartidas.
 */
declare(strict_types=1);

/** Logotipo: engranaje con nodo de red (SVG propio, sin copyright). */
function logo_svg(): string
{
    return '<svg class="logo-svg" viewBox="0 0 40 40" aria-hidden="true" focusable="false">'
        . '<path class="logo-gear" d="M20 5l2.6 3.3 4.1-.9 1 4.1 4 1.4-1 4.1 2.8 3-2.8 3 1 4.1-4 1.4-1 4.1-4.1-.9L20 35l-2.6-3.3-4.1.9-1-4.1-4-1.4 1-4.1L6.5 20l2.8-3-1-4.1 4-1.4 1-4.1 4.1.9z"/>'
        . '<circle class="logo-node" cx="20" cy="20" r="4.2"/>'
        . '<path class="logo-link" d="M20 20h11M31 20v-7M31 13h5"/>'
        . '<circle class="logo-tip" cx="36" cy="13" r="2"/>'
        . '</svg>';
}

/** Cabecera de la parte pública. */
function public_header(string $title, string $description = '', string $active = ''): void
{
    $site = (string) cfg('site_name');
    $full = $title === $site ? $site : $title . ' | ' . $site;
    $desc = $description !== '' ? $description : (string) cfg('site_tagline');
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($full) ?></title>
<meta name="description" content="<?= e(str_limit($desc, 160)) ?>">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('assets/fonts/barlow-semi-condensed-latin-700-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
</head>
<body>
<div class="progress" data-progress aria-hidden="true"><span></span></div>
<header class="site-header" data-header>
  <div class="wrap header-row">
    <a class="brand" href="<?= e(url()) ?>">
      <?= logo_svg() ?>
      <span class="brand-text"><span class="brand-name"><?= e($site) ?></span><span class="brand-sub"><?= e((string) cfg('site_tagline')) ?></span></span>
    </a>
    <nav class="main-nav" aria-label="Principal">
      <a href="<?= e(url()) ?>"<?= $active === 'inicio' ? ' aria-current="page"' : '' ?>>Inicio</a>
      <a href="<?= e(url_categorias()) ?>"<?= $active === 'categorias' ? ' aria-current="page"' : '' ?>>Categorías</a>
      <a href="<?= e(url_entradas()) ?>"<?= $active === 'entradas' ? ' aria-current="page"' : '' ?>>Entradas</a>
      <?php if (juegos_publicados_count() > 0 || $active === 'juegos'): ?><a href="<?= e(url_juegos()) ?>"<?= $active === 'juegos' ? ' aria-current="page"' : '' ?>>Juegos</a><?php endif; ?>
      <a href="<?= e(url_sobre()) ?>"<?= $active === 'sobre' ? ' aria-current="page"' : '' ?>>Sobre nosotros</a>
      <button type="button" class="theme-toggle" data-theme-toggle hidden aria-label="Cambiar a modo oscuro">
        <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 9 9 7 7 0 0 1-9-9z"/></svg>
        <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1"/></svg>
      </button>
    </nav>
  </div>
</header>
<main id="contenido" tabindex="-1">
<?php
}

function public_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="brand" href="<?= e(url()) ?>"><?= logo_svg() ?><span class="brand-name"><?= e((string) cfg('site_name')) ?></span></a>
      <p><?= e((string) cfg('footer_text')) ?></p>
    </div>
    <nav class="footer-links" aria-label="Pie de página">
      <a href="<?= e(url()) ?>">Inicio</a>
      <a href="<?= e(url_categorias()) ?>">Categorías</a>
      <a href="<?= e(url_entradas()) ?>">Entradas</a>
      <?php if (juegos_publicados_count() > 0): ?><a href="<?= e(url_juegos()) ?>">Juegos</a><?php endif; ?>
      <a href="<?= e(url_sobre()) ?>">Sobre nosotros</a>
    </nav>
  </div>
</footer>
<button type="button" class="to-top" data-to-top hidden aria-label="Volver arriba">
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
</body>
</html>
<?php
}

/**
 * Tarjeta de una entrada (misma maqueta que la portada).
 * $p: fila de entrada con autor, categoria y categoria_slug. $tags: etiquetas de la entrada.
 */
function render_post_card(array $p, array $tags = [], string $hTag = 'h2'): void
{
    $date = $p['publicada_en'];
    ?>
    <article class="card tone-<?= tone((int) $p['id']) ?> reveal" data-spot>
      <div class="card-media" aria-hidden="true">
        <?php if ($p['imagen'] && upload_exists($p['imagen'])): ?>
          <img src="<?= e(url_upload($p['imagen'])) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
          <span class="card-art"><span></span></span>
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
}

/** Página de error genérica (pública o del panel) y fin de la ejecución. */
function render_error(int $code, string $title, string $text = '', bool $admin = false): never
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    if ($admin) {
        admin_header($title, '', false);
        echo '<section class="panel-card error-box"><h1>' . e($title) . '</h1><p>' . e($text) . '</p>'
            . '<p><a class="btn" href="' . e(url('admin/')) . '">Volver al panel</a></p></section>';
        admin_footer();
    } else {
        public_header($title);
        echo '<div class="wrap narrow error-page"><p class="error-code" aria-hidden="true">' . $code . '</p><h1>' . e($title) . '</h1>'
            . ($text !== '' ? '<p>' . e($text) . '</p>' : '')
            . '<p><a class="btn" href="' . e(url()) . '">Ir al inicio</a></p></div>';
        public_footer();
    }
    exit;
}

function render_404(): never
{
    render_error(404, 'No encontramos esta página', 'Puede que la entrada se haya movido o que ya no esté publicada. Prueba con el buscador de la portada.');
}

/**
 * Paginación accesible.
 * $makeUrl recibe el número de página y devuelve la URL.
 */
function pagination(int $page, int $pages, callable $makeUrl): string
{
    if ($pages <= 1) {
        return '';
    }
    $h = '<nav class="pager" aria-label="Paginación"><ul>';
    if ($page > 1) {
        $h .= '<li><a href="' . e($makeUrl($page - 1)) . '" rel="prev">Anterior</a></li>';
    }
    $from = max(1, $page - 2);
    $to = min($pages, $page + 2);
    if ($from > 1) {
        $h .= '<li><a href="' . e($makeUrl(1)) . '">1</a></li>' . ($from > 2 ? '<li class="gap" aria-hidden="true">…</li>' : '');
    }
    for ($p = $from; $p <= $to; $p++) {
        $h .= $p === $page
            ? '<li><a aria-current="page" href="' . e($makeUrl($p)) . '">' . $p . '</a></li>'
            : '<li><a href="' . e($makeUrl($p)) . '" aria-label="Página ' . $p . '">' . $p . '</a></li>';
    }
    if ($to < $pages) {
        $h .= ($to < $pages - 1 ? '<li class="gap" aria-hidden="true">…</li>' : '') . '<li><a href="' . e($makeUrl($pages)) . '">' . $pages . '</a></li>';
    }
    if ($page < $pages) {
        $h .= '<li><a href="' . e($makeUrl($page + 1)) . '" rel="next">Siguiente</a></li>';
    }
    return $h . '</ul></nav>';
}

/* ================= PANEL DE ADMINISTRACIÓN ================= */

function admin_header(string $title, string $active = '', bool $withNav = true): void
{
    $u = $withNav ? current_user() : null;
    header('X-Robots-Tag: noindex, nofollow');
    $items = [
        'entradas'   => ['admin/index.php', 'Entradas'],
        'nueva'      => ['admin/entrada.php', 'Nueva entrada'],
        'categorias' => ['admin/categorias.php', 'Categorías'],
        'etiquetas'  => ['admin/etiquetas.php', 'Etiquetas'],
        'imagenes'   => ['admin/imagenes.php', 'Imágenes'],
        'paginas'    => ['admin/paginas.php', 'Páginas'],
        'juegos'     => ['admin/juegos.php', 'Juegos'],
        'cuenta'     => ['admin/cuenta.php', 'Mi cuenta'],
        'seguridad'  => ['admin/seguridad.php', 'Seguridad'],
    ];
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | Panel</title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<script src="<?= e(asset('js/qrcode.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin<?= $u ? '' : ' admin-guest' ?>">
<?php if ($u): ?>
<header class="admin-bar">
  <a class="brand" href="<?= e(url('admin/')) ?>"><?= logo_svg() ?><span class="brand-name">Panel del blog</span></a>
  <nav class="admin-nav" aria-label="Panel">
    <?php foreach ($items as $key => [$path, $label]): ?>
      <a href="<?= e(url($path)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-user">
    <span>Sesión de <strong><?= e($u['nombre']) ?></strong></span>
    <a href="<?= e(url()) ?>">Ver la web</a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="linkish">Cerrar sesión</button>
    </form>
    <button type="button" class="theme-toggle" data-theme-toggle hidden aria-label="Cambiar a modo oscuro">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 9 9 7 7 0 0 1-9-9z"/></svg>
    </button>
  </div>
</header>
<?php endif; ?>
<main id="panel-main" class="admin-main" tabindex="-1">
<?php
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach (take_flashes() as $f) {
            $role = $f['type'] === 'error' ? 'alert' : 'status';
            echo '<div class="flash flash-' . e($f['type']) . '" role="' . $role . '">' . e($f['msg']) . '</div>';
        }
    }
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}

/** Insignia de estado de una entrada. */
function estado_badge(string $estado): string
{
    return $estado === 'publicada'
        ? '<span class="badge badge-pub">Publicada</span>'
        : '<span class="badge badge-draft">Borrador</span>';
}
