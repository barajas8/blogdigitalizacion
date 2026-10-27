<?php
/** Panel: crear y editar una entrada. */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/upload.php';
$user = require_login();

$id = get_int('id', 0);
$existing = null;
if ($id > 0) {
    $existing = q_one('SELECT e.*, u.nombre autor FROM entradas e JOIN usuarios u ON u.id = e.autor_id WHERE e.id = ?', [$id]);
    if (!$existing) {
        render_error(404, 'Esa entrada no existe', 'Puede que la haya borrado tu compañero.', true);
    }
}

/** Etiquetas de una entrada como texto "a, b, c". */
function tags_string(int $postId): string
{
    $t = q_all('SELECT t.nombre FROM etiquetas t JOIN entrada_etiqueta ee ON ee.etiqueta_id = t.id WHERE ee.entrada_id = ? ORDER BY t.nombre', [$postId]);
    return implode(', ', array_column($t, 'nombre'));
}

$form = [
    'titulo'       => $existing['titulo'] ?? '',
    'slug'         => $existing['slug'] ?? '',
    'resumen'      => $existing['resumen'] ?? '',
    'contenido'    => $existing['contenido'] ?? '',
    'categoria_id' => (int) ($existing['categoria_id'] ?? 0),
    'etiquetas'    => $existing ? tags_string($id) : '',
    'imagen'       => $existing['imagen'] ?? '',
    'imagen_alt'   => $existing['imagen_alt'] ?? '',
    'fijada'       => (int) ($existing['fijada'] ?? 0),
];
$errors = [];
$preview = null;

if (is_post()) {
    csrf_check();
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    if (!in_array($accion, ['guardar', 'borrador', 'publicar', 'previsualizar'], true)) {
        $accion = 'guardar';
    }

    $form['titulo'] = input_str($_POST, 'titulo', 200);
    $form['slug'] = slugify(input_str($_POST, 'slug', 220));
    $form['resumen'] = input_str($_POST, 'resumen', 400);
    $form['contenido'] = is_string($_POST['contenido'] ?? null) ? str_replace("\r\n", "\n", (string) $_POST['contenido']) : '';
    $form['categoria_id'] = (int) ($_POST['categoria_id'] ?? 0);
    $form['etiquetas'] = input_str($_POST, 'etiquetas', 600);
    $form['imagen'] = input_str($_POST, 'imagen', 100);
    $form['imagen_alt'] = input_str($_POST, 'imagen_alt', 200);
    $form['fijada'] = !empty($_POST['fijada']) ? 1 : 0;
    $maxPin = (int) cfg('max_pinned', 3);
    if ($form['fijada'] && pinned_count($existing['id'] ?? null) >= $maxPin) {
        $form['fijada'] = 0;
        $errors[] = "Ya hay {$maxPin} entradas fijadas, que es el máximo. Desfija alguna desde el listado de Entradas.";
    }

    if ($form['titulo'] === '') {
        $errors[] = 'Escribe un título.';
    }
    if (trim($form['contenido']) === '') {
        $errors[] = 'El contenido está vacío.';
    } elseif (mb_strlen($form['contenido']) > 200000) {
        $errors[] = 'El contenido es demasiado largo (máximo 200.000 caracteres). Divídelo en varias entradas.';
    }
    if ($form['categoria_id'] > 0 && q_val('SELECT id FROM categorias WHERE id = ?', [$form['categoria_id']]) === null) {
        $form['categoria_id'] = 0;
        $errors[] = 'La categoría elegida ya no existe. Elige otra.';
    }

    // Imagen destacada: subida nueva > elegida de la lista > quitar
    $up = handle_image_upload('imagen_nueva', (int) $user['id']);
    if ($up && !$up['ok']) {
        $errors[] = $up['error'];
    } elseif ($up) {
        $form['imagen'] = $up['archivo'];
    }
    if (!empty($_POST['quitar_imagen'])) {
        $form['imagen'] = '';
    }
    if ($form['imagen'] !== '' && q_val('SELECT id FROM imagenes WHERE archivo = ?', [$form['imagen']]) === null) {
        $form['imagen'] = '';
        $errors[] = 'La imagen destacada elegida no existe.';
    }
    if ($form['imagen'] !== '' && $form['imagen_alt'] === '') {
        $errors[] = 'Describe la imagen destacada en «Texto alternativo» (lo leen los lectores de pantalla).';
    }

    // Etiquetas: separadas por comas, máximo 15
    $tagNames = [];
    foreach (explode(',', $form['etiquetas']) as $t) {
        $t = mb_substr(trim($t), 0, 60);
        $s = slugify($t, 80);
        if ($t !== '' && $s !== '') {
            $tagNames[$s] ??= $t; // se conserva la primera forma escrita
        }
    }
    if (count($tagNames) > 15) {
        $errors[] = 'Pon como máximo 15 etiquetas.';
    }

    if ($accion === 'previsualizar') {
        $preview = md_to_html($form['contenido']);
    } elseif (!$errors) {
        $slug = unique_slug('entradas', $form['slug'] !== '' ? $form['slug'] : slugify($form['titulo']), $existing['id'] ?? null);
        $resumen = $form['resumen'] !== '' ? $form['resumen'] : md_plain($form['contenido'], 300);
        $estadoActual = $existing['estado'] ?? 'borrador';
        $estado = match ($accion) {
            'publicar' => 'publicada',
            'borrador' => 'borrador',
            default    => $estadoActual,
        };
        $publicadaEn = $existing['publicada_en'] ?? null;
        if ($estado === 'publicada' && !$publicadaEn) {
            $publicadaEn = now();
        }
        $cat = $form['categoria_id'] > 0 ? $form['categoria_id'] : null;
        $img = $form['imagen'] !== '' ? $form['imagen'] : null;
        $alt = $img ? $form['imagen_alt'] : null;

        try {
            db()->beginTransaction();
            if ($existing) {
                q('UPDATE entradas SET titulo = ?, slug = ?, resumen = ?, contenido = ?, imagen = ?, imagen_alt = ?, categoria_id = ?,
                       fijada = ?, estado = ?, publicada_en = ?, editada_por = ?, actualizada_en = ? WHERE id = ?',
                    [$form['titulo'], $slug, $resumen, $form['contenido'], $img, $alt, $cat, $form['fijada'], $estado, $publicadaEn, (int) $user['id'], now(), $id]);
                $postId = $id;
            } else {
                q('INSERT INTO entradas (titulo, slug, resumen, contenido, imagen, imagen_alt, categoria_id, fijada, autor_id, editada_por, estado, creada_en, actualizada_en, publicada_en)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$form['titulo'], $slug, $resumen, $form['contenido'], $img, $alt, $cat, $form['fijada'], (int) $user['id'], (int) $user['id'], $estado, now(), now(), $publicadaEn]);
                $postId = (int) db()->lastInsertId();
            }

            // Etiquetas: se crean las que no existan y se vuelven a enlazar
            q('DELETE FROM entrada_etiqueta WHERE entrada_id = ?', [$postId]);
            foreach ($tagNames as $tSlug => $tName) {
                $tid = q_val('SELECT id FROM etiquetas WHERE slug = ?', [$tSlug]);
                if ($tid === null) {
                    q('INSERT INTO etiquetas (nombre, slug) VALUES (?, ?)', [$tName, $tSlug]);
                    $tid = db()->lastInsertId();
                }
                q('INSERT IGNORE INTO entrada_etiqueta (entrada_id, etiqueta_id) VALUES (?, ?)', [$postId, (int) $tid]);
            }
            db()->commit();
        } catch (Throwable $ex) {
            db()->rollBack();
            error_log('Guardar entrada: ' . $ex->getMessage());
            render_error(500, 'No se pudo guardar la entrada', 'Ha fallado la base de datos. Copia tu texto en un lugar seguro antes de volver atrás.', true);
        }

        $msg = match (true) {
            $estado === 'publicada' && $estadoActual !== 'publicada' => 'Entrada publicada. Ya se ve en la web.',
            $estado === 'borrador' && $estadoActual === 'publicada'  => 'Entrada pasada a borrador. Ya no se ve en la web.',
            $estado === 'publicada'                                    => 'Cambios guardados y visibles en la web.',
            default                                                    => 'Borrador guardado.',
        };
        flash('ok', $msg);
        redirect('admin/entrada.php?id=' . $postId);
    }
}

$cats = q_all('SELECT id, nombre FROM categorias ORDER BY nombre');
$allTags = q_all('SELECT nombre FROM etiquetas ORDER BY nombre LIMIT 60');
$images = list_images();
$isPub = ($existing['estado'] ?? '') === 'publicada';
$title = $existing ? 'Editar entrada' : 'Nueva entrada';

admin_header($title, $existing ? 'entradas' : 'nueva');
?>
<div class="page-head">
  <h1><?= e($title) ?></h1>
  <?php if ($existing): ?>
    <p class="muted">
      <?= estado_badge($existing['estado']) ?>
      Creada por <?= e($existing['autor']) ?> el <?= e(fecha_es($existing['creada_en'])) ?>.
      <?php if ($isPub): ?><a href="<?= e(url_post($existing['slug'])) ?>" target="_blank" rel="noopener">Ver en la web</a><?php endif; ?>
    </p>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="flash flash-error" role="alert"><p>Revisa estos puntos:</p><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($preview !== null): ?>
  <section class="panel-card preview-box" aria-labelledby="pv-title">
    <h2 id="pv-title" class="preview-label">Vista previa (aún no guardada)</h2>
    <article class="prose">
      <h1><?= e($form['titulo']) ?></h1>
      <?= $preview ?>
    </article>
  </section>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="editor-form" novalidate>
  <?= csrf_field() ?>
  <div class="editor-main">
    <label for="titulo">Título</label>
    <input id="titulo" name="titulo" required maxlength="200" value="<?= e($form['titulo']) ?>" class="input-title" data-slug-source>

    <label for="contenido">Contenido</label>
    <p class="hint" id="md-help">Se escribe en Markdown: <code>## Título</code>, <code>**negrita**</code>, <code>*cursiva*</code>, <code>- lista</code>, <code>[enlace](https://...)</code>, <code>![descripción](uploads/foto.jpg)</code>, <code>&gt; cita</code>, <code>```</code> para código y tablas con <code>|</code>.</p>
    <div class="md-editor" data-md-editor>
      <textarea id="contenido" name="contenido" rows="22" required aria-describedby="md-help" data-base="<?= e(base_path()) ?>"><?= e($form['contenido']) ?></textarea>
    </div>

    <details class="panel-card image-picker"<?= $images ? '' : ' hidden' ?>>
      <summary>Insertar una imagen ya subida en el texto</summary>
      <p class="hint">Copia el código en el contenido (o pulsa «Insertar» si tienes JavaScript activado) y cambia «Descripción» por lo que se ve en la imagen.</p>
      <ul class="thumb-grid">
        <?php foreach ($images as $im): $snippet = '![Descripción](uploads/' . $im['archivo'] . ')'; ?>
          <li>
            <img src="<?= e(url_upload($im['archivo'])) ?>" alt="" loading="lazy" width="<?= (int) $im['ancho'] ?>" height="<?= (int) $im['alto'] ?>">
            <input type="text" readonly value="<?= e($snippet) ?>" aria-label="Código Markdown de <?= e($im['nombre_original']) ?>">
            <button type="button" class="btn btn-small" data-insert="<?= e($snippet) ?>" hidden>Insertar</button>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  </div>

  <aside class="editor-side">
    <section class="panel-card">
      <h2>Publicación</h2>
      <label class="check pin-check"><input type="checkbox" name="fijada" value="1"<?= $form['fijada'] ? ' checked' : '' ?> aria-describedby="pin-help"> Fijar en la portada</label>
      <p class="hint" id="pin-help">Sale la primera del listado de inicio, por delante de las más recientes. Máximo <?= (int) cfg('max_pinned', 3) ?> a la vez. Solo se ve si está publicada.</p>
      <div class="btn-col">
        <?php if ($isPub): ?>
          <button class="btn btn-primary" name="accion" value="guardar">Guardar cambios</button>
          <button class="btn btn-ghost" name="accion" value="borrador">Pasar a borrador</button>
        <?php else: ?>
          <button class="btn btn-primary" name="accion" value="publicar">Publicar</button>
          <button class="btn" name="accion" value="borrador">Guardar borrador</button>
        <?php endif; ?>
        <button class="btn btn-ghost" name="accion" value="previsualizar">Vista previa</button>
      </div>
    </section>

    <section class="panel-card">
      <h2>Organización</h2>
      <label for="categoria_id">Categoría</label>
      <select id="categoria_id" name="categoria_id">
        <option value="0">Sin categoría</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>"<?= $form['categoria_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$cats): ?><p class="hint">Aún no hay categorías. <a href="<?= e(url('admin/categorias.php')) ?>">Crea alguna</a>.</p><?php endif; ?>

      <label for="etiquetas">Etiquetas</label>
      <input id="etiquetas" name="etiquetas" maxlength="600" value="<?= e($form['etiquetas']) ?>" aria-describedby="tags-help">
      <p class="hint" id="tags-help">Separadas por comas. Las nuevas se crean solas.<?php if ($allTags): ?> Existentes: <?= e(implode(', ', array_column($allTags, 'nombre'))) ?>.<?php endif; ?></p>
    </section>

    <section class="panel-card">
      <h2>Imagen destacada</h2>
      <?php if ($form['imagen'] !== '' && !upload_exists($form['imagen'])): ?>
        <p class="flash flash-error">La imagen destacada no está en el servidor. Sube otra o elige una de la lista.</p>
      <?php endif; ?>
      <?php if ($form['imagen'] !== ''): ?>
        <img class="featured-thumb" src="<?= e(url_upload($form['imagen'])) ?>" alt="">
        <label class="check"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la imagen destacada</label>
      <?php endif; ?>
      <label for="imagen">Elegir una ya subida</label>
      <select id="imagen" name="imagen">
        <option value="">Ninguna</option>
        <?php foreach ($images as $im): ?>
          <option value="<?= e($im['archivo']) ?>"<?= $form['imagen'] === $im['archivo'] ? ' selected' : '' ?>><?= e($im['nombre_original']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="imagen_nueva">O subir una nueva</label>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) cfg('upload_max_bytes') ?>">
      <input id="imagen_nueva" name="imagen_nueva" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="img-help">
      <p class="hint" id="img-help">JPG, PNG o WebP, hasta <?= e(human_bytes((int) cfg('upload_max_bytes'))) ?>.</p>
      <label for="imagen_alt">Texto alternativo</label>
      <input id="imagen_alt" name="imagen_alt" maxlength="200" value="<?= e($form['imagen_alt']) ?>" aria-describedby="alt-help">
      <p class="hint" id="alt-help">Qué se ve en la imagen, en una frase.</p>
    </section>

    <section class="panel-card">
      <h2>Detalles</h2>
      <label for="resumen">Resumen</label>
      <textarea id="resumen" name="resumen" rows="4" maxlength="400" aria-describedby="sum-help"><?= e($form['resumen']) ?></textarea>
      <p class="hint" id="sum-help">Aparece en el listado. Si lo dejas vacío se genera con el principio del texto.</p>
      <label for="slug">Dirección (slug)</label>
      <input id="slug" name="slug" maxlength="220" value="<?= e($form['slug']) ?>" aria-describedby="slug-help" data-slug-target>
      <p class="hint" id="slug-help">Parte final de la URL. Vacío = se crea a partir del título.</p>
    </section>
  </aside>
</form>
<?php
admin_footer();
