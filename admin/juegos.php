<?php
/** Panel: listado de juegos, publicar/despublicar y borrar. */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
ensure_juegos_schema();

$tipos = juego_tipos();

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $accion = (string) ($_POST['accion'] ?? '');
    $j = $id > 0 ? q_one('SELECT id, titulo, tipo, contenido FROM juegos WHERE id = ?', [$id]) : null;
    if (!$j) {
        flash('error', 'El juego no existe.');
    } elseif ($accion === 'publicar') {
        if (juego_parse($j['tipo'], $j['contenido'])['errores']) {
            flash('error', '«' . $j['titulo'] . '» tiene errores en su contenido. Ábrelo y pulsa «Comprobar» para verlos.');
        } else {
            q("UPDATE juegos SET estado = 'publicado', actualizado_en = ?, actualizado_por = ? WHERE id = ?", [now(), $user['id'], $id]);
            flash('ok', 'Publicado: «' . $j['titulo'] . '».');
        }
    } elseif ($accion === 'despublicar') {
        q("UPDATE juegos SET estado = 'borrador', actualizado_en = ?, actualizado_por = ? WHERE id = ?", [now(), $user['id'], $id]);
        flash('ok', 'Pasado a borrador: «' . $j['titulo'] . '». Ya no se ve en la web.');
    } elseif ($accion === 'borrar') {
        q('DELETE FROM juegos WHERE id = ?', [$id]);
        flash('ok', 'Borrado: «' . $j['titulo'] . '».');
    }
    redirect('admin/juegos.php');
}

$rows = q_all('SELECT j.id, j.titulo, j.tipo, j.contenido, j.estado, j.orden, j.actualizado_en, u.nombre editor
               FROM juegos j LEFT JOIN usuarios u ON u.id = j.actualizado_por
               ORDER BY j.orden, j.id');

admin_header('Juegos', 'juegos');
?>
<div class="page-head">
  <h1>Juegos</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/juego.php')) ?>">Nuevo juego</a>
</div>
<p class="hint">Juegos para repasar las entradas: test, verdadero o falso, clasificar, memoria, ahorcado, sopa de letras, completar la frase y «¿de qué entrada es?». La sección <strong>Juegos</strong> aparece en el menú de la web cuando hay al menos uno publicado.</p>

<?php if (!$rows): ?>
  <section class="panel-card empty">
    <h2>Todavía no hay juegos</h2>
    <p>Crea juegos con preguntas sobre lo que habéis publicado. Mientras no haya ninguno publicado, la web no enseña la sección.</p>
    <p><a class="btn btn-primary" href="<?= e(url('admin/juego.php')) ?>">Crear el primer juego</a></p>
  </section>
<?php else: ?>
<div class="table-wrap">
<table class="data">
  <caption class="sr-only">Listado de juegos</caption>
  <thead><tr><th scope="col">Título</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Contenido</th><th scope="col">Editado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $p = juego_parse($r['tipo'], $r['contenido']);
      $t = $tipos[$r['tipo']] ?? ['nombre' => $r['tipo'], 'unidad' => 'elemento'];
      $n = count($p['items']); ?>
    <tr>
      <td class="col-title"><a href="<?= e(url('admin/juego.php?id=' . $r['id'])) ?>"><?= e($r['titulo']) ?></a></td>
      <td><?= e($t['nombre']) ?></td>
      <td><?= $r['estado'] === 'publicado' ? '<span class="badge badge-pub">Publicado</span>' : '<span class="badge badge-draft">Borrador</span>' ?></td>
      <td><?= $n ?> <?= e($t['unidad']) ?><?= $n === 1 ? '' : 's' ?><?php if ($p['errores']): ?> <span class="badge badge-draft">Con errores</span><?php endif; ?></td>
      <td class="dates"><span class="muted"><?= e(fecha_es($r['actualizado_en'], true)) ?><?= $r['editor'] ? ' por ' . e($r['editor']) : '' ?></span></td>
      <td class="actions">
        <a class="btn btn-small" href="<?= e(url('admin/juego.php?id=' . $r['id'])) ?>">Editar</a>
        <?php if ($r['estado'] === 'publicado'): ?>
          <a class="btn btn-small btn-ghost" href="<?= e(url_juegos()) ?>#j<?= (int) $r['id'] ?>" target="_blank" rel="noopener">Ver</a>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <?php if ($r['estado'] === 'publicado'): ?>
            <button class="btn btn-small btn-ghost" name="accion" value="despublicar">Pasar a borrador</button>
          <?php else: ?>
            <button class="btn btn-small btn-ghost" name="accion" value="publicar">Publicar</button>
          <?php endif; ?>
        </form>
        <form method="post" data-confirm="¿Borrar el juego «<?= e($r['titulo']) ?>»? No se puede deshacer.">
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
<?php endif; ?>
<?php
admin_footer();
