<?php
/** Panel: biblioteca de imágenes. */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/upload.php';
$user = require_login();

if (is_post()) {
    csrf_check();
    $accion = (string) ($_POST['accion'] ?? '');
    if ($accion === 'subir') {
        $r = handle_image_upload('imagen', (int) $user['id']);
        if ($r === null) {
            flash('error', 'Elige una imagen antes de pulsar «Subir imagen».');
        } elseif (!$r['ok']) {
            flash('error', $r['error']);
        } else {
            flash('ok', 'Imagen subida. Copia su código para usarla en una entrada.');
        }
    } elseif ($accion === 'borrar') {
        $img = q_one('SELECT id, archivo FROM imagenes WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        if ($img) {
            // No se borra si alguna entrada o página la usa (evita imágenes rotas en la web).
            $uses = q_all('SELECT titulo FROM entradas WHERE imagen = ? OR contenido LIKE ? LIMIT 5', [$img['archivo'], like_escape($img['archivo'])]);
            $usesPage = (int) q_val('SELECT COUNT(*) FROM paginas WHERE contenido LIKE ?', [like_escape($img['archivo'])]);
            if ($uses || $usesPage) {
                $where = array_map(static fn($r) => '«' . $r['titulo'] . '»', $uses);
                if ($usesPage) {
                    $where[] = 'una página';
                }
                flash('error', 'No se ha borrado porque se usa en: ' . implode(', ', $where) . '. Quítala de ahí primero.');
            } else {
                $path = APP_ROOT . '/uploads/' . basename($img['archivo']);
                if (is_file($path)) {
                    @unlink($path);
                }
                q('DELETE FROM imagenes WHERE id = ?', [(int) $img['id']]);
                flash('ok', 'Imagen borrada.');
            }
        }
    }
    redirect('admin/imagenes.php');
}

$images = q_all('SELECT i.*, u.nombre autor FROM imagenes i LEFT JOIN usuarios u ON u.id = i.subida_por ORDER BY i.id DESC LIMIT 200');
$gd = function_exists('imagecreatetruecolor');

admin_header('Imágenes', 'imagenes');
?>
<div class="page-head"><h1>Imágenes</h1></div>

<section class="panel-card">
  <h2>Subir imagen</h2>
  <form method="post" enctype="multipart/form-data" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="subir">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) cfg('upload_max_bytes') ?>">
    <div class="grow">
      <label for="imagen">Archivo</label>
      <input id="imagen" name="imagen" type="file" required accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="up-help">
    </div>
    <button class="btn btn-primary">Subir imagen</button>
  </form>
  <p class="hint" id="up-help">JPG, PNG o WebP, hasta <?= e(human_bytes((int) cfg('upload_max_bytes'))) ?> y <?= (int) cfg('upload_max_px') ?> px de lado.
    <?= $gd ? 'Se reducen a ' . (int) cfg('image_max_width') . ' px de ancho y se les quitan los metadatos (como la ubicación GPS).' : 'El servidor no tiene GD: las imágenes se guardan tal cual, así que quítales los metadatos antes de subirlas.' ?></p>
</section>

<?php if (!$images): ?>
  <p class="panel-card empty">No hay imágenes. Las que subas aparecerán aquí y en el editor de entradas.</p>
<?php else: ?>
<ul class="media-grid">
  <?php foreach ($images as $im): $snippet = '![Descripción](uploads/' . $im['archivo'] . ')'; ?>
  <li class="panel-card<?= upload_exists($im['archivo']) ? '' : ' is-missing' ?>">
    <?php if (!upload_exists($im['archivo'])): ?>
      <p class="flash flash-error">Este archivo no está en el servidor (carpeta uploads). Bórrala y vuelve a subirla.</p>
    <?php endif; ?>
    <a href="<?= e(url_upload($im['archivo'])) ?>" target="_blank" rel="noopener">
      <img src="<?= e(url_upload($im['archivo'])) ?>" alt="<?= e($im['nombre_original']) ?>" loading="lazy" width="<?= (int) $im['ancho'] ?>" height="<?= (int) $im['alto'] ?>">
    </a>
    <p class="media-name"><?= e($im['nombre_original']) ?></p>
    <p class="muted"><?= (int) $im['ancho'] ?> × <?= (int) $im['alto'] ?> px, <?= e(human_bytes((int) $im['tamano'])) ?><?= $im['autor'] ? ', subida por ' . e($im['autor']) : '' ?></p>
    <label class="sr-only" for="s<?= (int) $im['id'] ?>">Código Markdown</label>
    <input id="s<?= (int) $im['id'] ?>" type="text" readonly value="<?= e($snippet) ?>" data-copy>
    <form method="post" data-confirm="¿Borrar esta imagen?">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $im['id'] ?>">
      <button class="btn btn-small btn-danger" name="accion" value="borrar">Borrar</button>
    </form>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php
admin_footer();
