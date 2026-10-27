<?php
/** Página de una entrada publicada. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$slug = input_str($_GET, 'slug', 220);
if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    render_404();
}

$p = q_one("SELECT e.id, e.titulo, e.slug, e.resumen, e.contenido, e.imagen, e.imagen_alt, e.publicada_en, e.actualizada_en,
                   u.nombre autor, c.nombre categoria, c.slug categoria_slug
            FROM entradas e
            JOIN usuarios u ON u.id = e.autor_id
            LEFT JOIN categorias c ON c.id = e.categoria_id
            WHERE e.slug = ? AND e.estado = 'publicada'", [$slug]);
if (!$p) {
    render_404();
}
$tags = q_all('SELECT t.nombre, t.slug FROM etiquetas t JOIN entrada_etiqueta ee ON ee.etiqueta_id = t.id
               WHERE ee.entrada_id = ? ORDER BY t.nombre', [(int) $p['id']]);

// Se considera "actualizada" si se editó más de un día después de publicarse
$updated = strtotime($p['actualizada_en']) - strtotime($p['publicada_en']) > 86400;

public_header($p['titulo'], (string) $p['resumen']);
?>
<header class="post-hero tone-<?= tone((int) $p['id']) ?>">
  <div class="hero-bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="hero-mesh"></span></div>
  <div class="wrap narrow post-head">
    <a class="link-back" href="<?= e(url()) ?>">Todas las entradas</a>
    <?php if ($p['categoria']): ?><a class="chip chip-light" href="<?= e(url_cat($p['categoria_slug'])) ?>"><?= e($p['categoria']) ?></a><?php endif; ?>
    <h1 class="post-title"><?= e($p['titulo']) ?></h1>
    <div class="post-meta">
      <span class="author"><span class="avatar" aria-hidden="true"><?= e(initials($p['autor'])) ?></span><?= e($p['autor']) ?></span>
      <span><time datetime="<?= e(date('c', strtotime($p['publicada_en']))) ?>"><?= e(fecha_es($p['publicada_en'])) ?></time></span>
      <span><?= reading_minutes($p['contenido']) ?> min de lectura</span>
      <?php if ($updated): ?><span>Actualizado el <time datetime="<?= e(date('c', strtotime($p['actualizada_en']))) ?>"><?= e(fecha_es($p['actualizada_en'])) ?></time></span><?php endif; ?>
    </div>
  </div>
</header>

<article class="wrap narrow post" data-reading>
  <?php if ($p['imagen'] && upload_exists($p['imagen'])): ?>
    <figure class="post-figure reveal">
      <img src="<?= e(url_upload($p['imagen'])) ?>" alt="<?= e((string) $p['imagen_alt']) ?>" decoding="async">
    </figure>
  <?php endif; ?>

  <div class="prose">
    <?= md_to_html($p['contenido']) ?>
  </div>

  <footer class="post-foot">
    <?php if ($tags): ?>
      <h2 class="sr-only">Etiquetas</h2>
      <ul class="tags">
        <?php foreach ($tags as $t): ?><li><a href="<?= e(url_tag($t['slug'])) ?>"><?= e($t['nombre']) ?></a></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p><a class="btn btn-outline-dark" href="<?= e(url()) ?>">Volver a todas las entradas</a></p>
  </footer>
</article>
<?php
public_footer();
