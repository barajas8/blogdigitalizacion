<?php
/** Página con todas las categorías del blog. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$cats = q_all("SELECT c.id, c.nombre, c.slug, c.descripcion, COUNT(e.id) n
               FROM categorias c
               LEFT JOIN entradas e ON e.categoria_id = c.id AND e.estado = 'publicada'
               GROUP BY c.id, c.nombre, c.slug, c.descripcion
               ORDER BY c.nombre");

public_header('Categorías', 'Todas las categorías del blog.', 'categorias');
?>
<div class="wrap list-page">
  <header class="feed-head reveal">
    <h1>Categorías</h1>
    <p class="feed-count"><?= count($cats) ?> categoría<?= count($cats) === 1 ? '' : 's' ?></p>
  </header>

  <?php if (!$cats): ?>
    <div class="empty-state reveal">
      <h2>Aún no hay categorías</h2>
      <p>Cuando el equipo cree categorías aparecerán aquí.</p>
    </div>
  <?php else: ?>
    <ul class="cat-grid">
      <?php foreach ($cats as $c): ?>
        <li class="cat-card tone-<?= tone((int) $c['id']) ?> reveal">
          <span class="cat-card-art" aria-hidden="true"></span>
          <div class="cat-card-body">
            <h2><a href="<?= e(url_cat($c['slug'])) ?>"><?= e($c['nombre']) ?></a></h2>
            <?php if ($c['descripcion']): ?><p><?= e($c['descripcion']) ?></p><?php endif; ?>
            <span class="cat-card-n"><?= (int) $c['n'] ?> entrada<?= (int) $c['n'] === 1 ? '' : 's' ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php
public_footer();
