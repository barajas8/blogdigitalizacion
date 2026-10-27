<?php
/** Panel: etiquetas (crear, renombrar, borrar, limpiar las que no se usan). */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

if (is_post()) {
    csrf_check();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $nombre = input_str($_POST, 'nombre', 60);

    if ($accion === 'crear' || $accion === 'editar') {
        $slug = slugify($nombre, 80);
        $clash = $slug !== '' ? q_val('SELECT id FROM etiquetas WHERE slug = ?', [$slug]) : null;
        if ($slug === '') {
            flash('error', 'La etiqueta necesita un nombre con letras o números.');
        } elseif ($clash !== null && (int) $clash !== $id) {
            flash('error', "Ya existe una etiqueta equivalente a «{$nombre}».");
        } elseif ($accion === 'crear') {
            q('INSERT INTO etiquetas (nombre, slug) VALUES (?, ?)', [$nombre, $slug]);
            flash('ok', "Etiqueta «{$nombre}» creada.");
        } else {
            q('UPDATE etiquetas SET nombre = ?, slug = ? WHERE id = ?', [$nombre, $slug, $id]);
            flash('ok', "Etiqueta «{$nombre}» guardada.");
        }
    } elseif ($accion === 'borrar' && $id > 0) {
        q('DELETE FROM etiquetas WHERE id = ?', [$id]);
        flash('ok', 'Etiqueta borrada y quitada de sus entradas.');
    } elseif ($accion === 'limpiar') {
        $n = q('DELETE t FROM etiquetas t LEFT JOIN entrada_etiqueta ee ON ee.etiqueta_id = t.id WHERE ee.etiqueta_id IS NULL')->rowCount();
        flash('ok', $n ? "Borradas {$n} etiqueta(s) sin uso." : 'No había etiquetas sin uso.');
    }
    redirect('admin/etiquetas.php');
}

$tags = q_all('SELECT t.id, t.nombre, COUNT(ee.entrada_id) total
               FROM etiquetas t LEFT JOIN entrada_etiqueta ee ON ee.etiqueta_id = t.id
               GROUP BY t.id, t.nombre ORDER BY t.nombre');

admin_header('Etiquetas', 'etiquetas');
?>
<div class="page-head">
  <h1>Etiquetas</h1>
  <?php if ($tags): ?>
  <form method="post" data-confirm="¿Borrar todas las etiquetas que no tienen ninguna entrada?">
    <?= csrf_field() ?>
    <button class="btn btn-ghost" name="accion" value="limpiar">Borrar etiquetas sin uso</button>
  </form>
  <?php endif; ?>
</div>

<section class="panel-card">
  <h2>Nueva etiqueta</h2>
  <p class="hint">También se crean solas al escribirlas en una entrada.</p>
  <form method="post" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="crear">
    <div class="grow"><label for="new-tag">Nombre</label><input id="new-tag" name="nombre" required maxlength="60" placeholder="Por ejemplo: PLC"></div>
    <button class="btn btn-primary">Crear etiqueta</button>
  </form>
</section>

<?php if (!$tags): ?>
  <p class="panel-card empty">No hay etiquetas todavía.</p>
<?php else: ?>
<ul class="edit-list compact">
  <?php foreach ($tags as $t): ?>
  <li class="panel-card">
    <form method="post" class="form-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
      <div class="grow"><label class="sr-only" for="t<?= (int) $t['id'] ?>">Nombre</label><input id="t<?= (int) $t['id'] ?>" name="nombre" required maxlength="60" value="<?= e($t['nombre']) ?>"></div>
      <p class="count"><?= (int) $t['total'] ?> entrada<?= (int) $t['total'] === 1 ? '' : 's' ?></p>
      <button class="btn btn-small" name="accion" value="editar">Guardar</button>
    </form>
    <form method="post" data-confirm="¿Borrar la etiqueta «<?= e($t['nombre']) ?>»?">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
      <button class="btn btn-small btn-danger" name="accion" value="borrar">Borrar</button>
    </form>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php
admin_footer();
