<?php
/** Panel: categorías (crear, renombrar, borrar). */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

if (is_post()) {
    csrf_check();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $nombre = input_str($_POST, 'nombre', 80);
    $desc = input_str($_POST, 'descripcion', 255);

    if ($accion === 'crear' || $accion === 'editar') {
        if ($nombre === '' || slugify($nombre) === '') {
            flash('error', 'La categoría necesita un nombre con letras o números.');
        } elseif ($accion === 'crear') {
            q('INSERT INTO categorias (nombre, slug, descripcion) VALUES (?, ?, ?)',
                [$nombre, unique_slug('categorias', slugify($nombre, 100)), $desc !== '' ? $desc : null]);
            flash('ok', "Categoría «{$nombre}» creada.");
        } elseif (q_val('SELECT id FROM categorias WHERE id = ?', [$id]) !== null) {
            q('UPDATE categorias SET nombre = ?, slug = ?, descripcion = ? WHERE id = ?',
                [$nombre, unique_slug('categorias', slugify($nombre, 100), $id), $desc !== '' ? $desc : null, $id]);
            flash('ok', "Categoría «{$nombre}» guardada.");
        }
    } elseif ($accion === 'borrar' && $id > 0) {
        $n = (int) q_val('SELECT COUNT(*) FROM entradas WHERE categoria_id = ?', [$id]);
        q('DELETE FROM categorias WHERE id = ?', [$id]);   // las entradas pasan a "Sin categoría" (ON DELETE SET NULL)
        flash('ok', $n ? "Categoría borrada. {$n} entrada(s) han quedado sin categoría." : 'Categoría borrada.');
    }
    redirect('admin/categorias.php');
}

$cats = q_all('SELECT c.id, c.nombre, c.slug, c.descripcion, COUNT(e.id) total
               FROM categorias c LEFT JOIN entradas e ON e.categoria_id = c.id
               GROUP BY c.id, c.nombre, c.slug, c.descripcion ORDER BY c.nombre');

admin_header('Categorías', 'categorias');
?>
<div class="page-head"><h1>Categorías</h1></div>

<section class="panel-card">
  <h2>Nueva categoría</h2>
  <form method="post" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="crear">
    <div><label for="new-nombre">Nombre</label><input id="new-nombre" name="nombre" required maxlength="80" placeholder="Por ejemplo: Industria 4.0"></div>
    <div class="grow"><label for="new-desc">Descripción (opcional)</label><input id="new-desc" name="descripcion" maxlength="255"></div>
    <button class="btn btn-primary">Crear categoría</button>
  </form>
</section>

<?php if (!$cats): ?>
  <p class="panel-card empty">No hay categorías todavía. Ideas para empezar: Industria 4.0, IoT industrial, Ciberseguridad industrial, Cloud y big data, ERP y MES.</p>
<?php else: ?>
<ul class="edit-list">
  <?php foreach ($cats as $c): ?>
  <li class="panel-card">
    <form method="post" class="form-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <div><label for="n<?= (int) $c['id'] ?>">Nombre</label><input id="n<?= (int) $c['id'] ?>" name="nombre" required maxlength="80" value="<?= e($c['nombre']) ?>"></div>
      <div class="grow"><label for="d<?= (int) $c['id'] ?>">Descripción</label><input id="d<?= (int) $c['id'] ?>" name="descripcion" maxlength="255" value="<?= e((string) $c['descripcion']) ?>"></div>
      <p class="count"><?= (int) $c['total'] ?> entrada<?= (int) $c['total'] === 1 ? '' : 's' ?></p>
      <button class="btn" name="accion" value="editar">Guardar</button>
    </form>
    <form method="post" data-confirm="¿Borrar la categoría «<?= e($c['nombre']) ?>»? Sus entradas quedarán sin categoría.">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <button class="btn btn-small btn-danger" name="accion" value="borrar">Borrar</button>
    </form>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php
admin_footer();
