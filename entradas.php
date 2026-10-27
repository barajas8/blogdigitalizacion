<?php
/** Página con todas las entradas publicadas (con paginación). */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$per = max(1, (int) cfg('posts_per_page'));
$page = get_int('pagina');

$total = (int) q_val("SELECT COUNT(*) FROM entradas WHERE estado = 'publicada'");
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
}

$posts = q_all("SELECT e.id, e.titulo, e.slug, e.resumen, e.imagen, e.imagen_alt, e.publicada_en,
                       u.nombre autor, c.nombre categoria, c.slug categoria_slug
                FROM entradas e
                JOIN usuarios u ON u.id = e.autor_id
                LEFT JOIN categorias c ON c.id = e.categoria_id
                WHERE e.estado = 'publicada'
                ORDER BY e.publicada_en DESC, e.id DESC
                LIMIT ? OFFSET ?", [$per, ($page - 1) * $per]);

// Etiquetas de las entradas de esta página en UNA sola consulta
$tagsByPost = [];
if ($posts) {
    $ids = array_map('intval', array_column($posts, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q_all("SELECT ee.entrada_id, t.nombre, t.slug FROM entrada_etiqueta ee JOIN etiquetas t ON t.id = ee.etiqueta_id
                    WHERE ee.entrada_id IN ($in) ORDER BY t.nombre", $ids) as $t) {
        $tagsByPost[(int) $t['entrada_id']][] = $t;
    }
}

$pageUrl = static fn(int $p) => with_query(url_entradas(), ['pagina' => $p]);

public_header('Todas las entradas' . ($page > 1 ? " (página $page)" : ''), 'Listado completo de las entradas del blog.', 'entradas');
?>
<div class="wrap list-page">
  <header class="feed-head reveal">
    <h1>Todas las entradas</h1>
    <?php if ($total > 0): ?><p class="feed-count"><?= $total ?> entrada<?= $total === 1 ? '' : 's' ?></p><?php endif; ?>
  </header>

  <?php if (!$posts): ?>
    <div class="empty-state reveal">
      <h2>Aún no hay publicaciones</h2>
      <p>Las primeras entradas del módulo aparecerán aquí muy pronto.</p>
    </div>
  <?php else: ?>
    <div class="card-grid">
      <?php foreach ($posts as $p) { render_post_card($p, $tagsByPost[(int) $p['id']] ?? [], 'h2'); } ?>
    </div>
    <?= pagination($page, $pages, $pageUrl) ?>
  <?php endif; ?>
</div>
<?php
public_footer();
