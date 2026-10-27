<?php
/** Panel: listado de entradas, publicar/despublicar y borrar. */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();

// ---- Acciones (POST) ----
if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['accion'] ?? '');
    $post = $id > 0 ? q_one('SELECT id, titulo, estado, publicada_en FROM entradas WHERE id = ?', [$id]) : null;
    if (!$post) {
        flash('error', 'La entrada no existe.');
    } elseif ($action === 'publicar') {
        q('UPDATE entradas SET estado = ?, publicada_en = COALESCE(publicada_en, ?), editada_por = ?, actualizada_en = ? WHERE id = ?',
            ['publicada', now(), $user['id'], now(), $id]);
        flash('ok', 'Publicada: «' . $post['titulo'] . '».');
    } elseif ($action === 'despublicar') {
        q('UPDATE entradas SET estado = ?, editada_por = ?, actualizada_en = ? WHERE id = ?', ['borrador', $user['id'], now(), $id]);
        flash('ok', 'Pasada a borrador: «' . $post['titulo'] . '». Ya no se ve en la web.');
    } elseif ($action === 'fijar') {
        $maxPin = (int) cfg('max_pinned', 3);
        if (pinned_count($id) >= $maxPin) {
            flash('error', "Ya hay {$maxPin} entradas fijadas, que es el máximo. Desfija alguna antes.");
        } else {
            q('UPDATE entradas SET fijada = 1 WHERE id = ?', [$id]);
            flash('ok', 'Fijada en la portada: «' . $post['titulo'] . '».' . ($post['estado'] === 'borrador' ? ' Se verá cuando la publiques.' : ''));
        }
    } elseif ($action === 'desfijar') {
        q('UPDATE entradas SET fijada = 0 WHERE id = ?', [$id]);
        flash('ok', 'Desfijada: «' . $post['titulo'] . '». Vuelve a su sitio por fecha.');
    } elseif ($action === 'borrar') {
        q('DELETE FROM entradas WHERE id = ?', [$id]);   // las etiquetas asociadas se borran en cascada
        flash('ok', 'Borrada: «' . $post['titulo'] . '». Las imágenes siguen en Imágenes por si las reutilizas.');
    }
    $back = 'admin/index.php';
    $qs = array_intersect_key($_GET, ['estado' => 1, 'pagina' => 1]);
    redirect($qs ? $back . '?' . http_build_query($qs) : $back);
}

// ---- Listado ----
$estado = (string) ($_GET['estado'] ?? '');
$estado = in_array($estado, ['publicada', 'borrador'], true) ? $estado : '';
$page = get_int('pagina');
$per = (int) cfg('admin_posts_per_page');

$where = $estado ? 'WHERE e.estado = ?' : '';
$params = $estado ? [$estado] : [];

$counts = q_one("SELECT COUNT(*) total, SUM(estado = 'publicada') pub, SUM(estado = 'borrador') bor FROM entradas");
$total = $estado ? (int) ($estado === 'publicada' ? $counts['pub'] : $counts['bor']) : (int) $counts['total'];
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);

$rows = q_all("SELECT e.id, e.titulo, e.slug, e.estado, e.fijada, e.creada_en, e.actualizada_en, e.publicada_en,
                      u.nombre autor, c.nombre categoria, ed.nombre editor
               FROM entradas e
               JOIN usuarios u ON u.id = e.autor_id
               LEFT JOIN categorias c ON c.id = e.categoria_id
               LEFT JOIN usuarios ed ON ed.id = e.editada_por
               $where
               ORDER BY e.fijada DESC, e.actualizada_en DESC
               LIMIT ? OFFSET ?", array_merge($params, [$per, ($page - 1) * $per]));

admin_header('Entradas', 'entradas');
$selfQs = static fn(int $p) => url('admin/index.php') . (($q = http_build_query(array_filter(['estado' => $estado, 'pagina' => $p > 1 ? $p : null]))) ? '?' . $q : '');
?>
<div class="page-head">
  <h1>Entradas</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/entrada.php')) ?>">Nueva entrada</a>
</div>

<nav class="filter-tabs" aria-label="Filtrar entradas">
  <a href="<?= e(url('admin/index.php')) ?>"<?= $estado === '' ? ' aria-current="page"' : '' ?>>Todas (<?= (int) $counts['total'] ?>)</a>
  <a href="<?= e(url('admin/index.php?estado=publicada')) ?>"<?= $estado === 'publicada' ? ' aria-current="page"' : '' ?>>Publicadas (<?= (int) $counts['pub'] ?>)</a>
  <a href="<?= e(url('admin/index.php?estado=borrador')) ?>"<?= $estado === 'borrador' ? ' aria-current="page"' : '' ?>>Borradores (<?= (int) $counts['bor'] ?>)</a>
</nav>

<?php if (!$rows): ?>
  <section class="panel-card empty">
    <h2><?= $estado ? 'No hay entradas en este estado' : 'Todavía no hay entradas' ?></h2>
    <p>La web pública muestra «Aún no hay publicaciones» hasta que publiquéis la primera.</p>
    <p><a class="btn btn-primary" href="<?= e(url('admin/entrada.php')) ?>">Escribir la primera entrada</a></p>
  </section>
<?php else: ?>
<div class="table-wrap">
<table class="data">
  <caption class="sr-only">Listado de entradas</caption>
  <thead><tr><th scope="col">Título</th><th scope="col">Estado</th><th scope="col">Categoría</th><th scope="col">Autor</th><th scope="col">Fechas</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="col-title"><a href="<?= e(url('admin/entrada.php?id=' . $r['id'])) ?>"><?= e($r['titulo']) ?></a></td>
      <td><?= estado_badge($r['estado']) ?><?= $r['fijada'] ? ' <span class="badge badge-pin">Fijada</span>' : '' ?></td>
      <td><?= $r['categoria'] ? e($r['categoria']) : '<span class="muted">Sin categoría</span>' ?></td>
      <td><?= e($r['autor']) ?></td>
      <td class="dates">
        <span>Creada el <?= e(fecha_es($r['creada_en'])) ?></span>
        <?php if ($r['publicada_en']): ?><span>Publicada el <?= e(fecha_es($r['publicada_en'])) ?></span><?php endif; ?>
        <span class="muted">Editada el <?= e(fecha_es($r['actualizada_en'], true)) ?><?= $r['editor'] ? ' por ' . e($r['editor']) : '' ?></span>
      </td>
      <td class="actions">
        <a class="btn btn-small" href="<?= e(url('admin/entrada.php?id=' . $r['id'])) ?>">Editar</a>
        <?php if ($r['estado'] === 'publicada'): ?>
          <a class="btn btn-small btn-ghost" href="<?= e(url_post($r['slug'])) ?>" target="_blank" rel="noopener">Ver</a>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <?php if ($r['estado'] === 'publicada'): ?>
            <button class="btn btn-small btn-ghost" name="accion" value="despublicar">Pasar a borrador</button>
          <?php else: ?>
            <button class="btn btn-small btn-ghost" name="accion" value="publicar">Publicar</button>
          <?php endif; ?>
          <?php if ($r['fijada']): ?>
            <button class="btn btn-small btn-ghost" name="accion" value="desfijar">Desfijar</button>
          <?php else: ?>
            <button class="btn btn-small btn-ghost" name="accion" value="fijar">Fijar</button>
          <?php endif; ?>
        </form>
        <form method="post" data-confirm="¿Borrar «<?= e($r['titulo']) ?>»? No se puede deshacer.">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-small btn-danger" name="accion" value="borrar">Borrar</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= pagination($page, $pages, $selfQs) ?>
<?php endif; ?>
<?php
admin_footer();
