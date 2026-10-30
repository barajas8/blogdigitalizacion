<?php
/** Panel: crear o editar un juego. */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
ensure_juegos_schema();

$tipos = juego_tipos();
$id = get_int('id', 0);
$row = $id > 0 ? q_one('SELECT * FROM juegos WHERE id = ?', [$id]) : null;
if ($id > 0 && !$row) {
    render_error(404, 'Ese juego no existe', 'Puede que se haya borrado.', true);
}

$form = [
    'titulo'      => $row['titulo'] ?? '',
    'tipo'        => $row['tipo'] ?? 'test',
    'descripcion' => $row['descripcion'] ?? '',
    'contenido'   => $row['contenido'] ?? '',
    'por_partida' => (int) ($row['por_partida'] ?? 0),
    'orden'       => (int) ($row['orden'] ?? 0),
    'estado'      => $row['estado'] ?? 'borrador',
];
$errors = [];
$check = null;   // resultado de «Comprobar»

/** Comprueba el texto y las entradas citadas. */
$analizar = static function (array $f) use ($tipos): array {
    $p = juego_parse($f['tipo'], $f['contenido']);
    $ent = juego_entradas($p['slugs']);
    $faltan = array_values(array_diff($p['slugs'], array_keys($ent)));
    if ($faltan) {
        $p['avisos'][] = 'Estas entradas no existen o no están publicadas, así que no saldrá su enlace'
            . ($f['tipo'] === 'origen' ? ' (y sus pistas no se usarán)' : '') . ': ' . implode(', ', $faltan) . '.';
    }
    $p['entradas'] = $ent;
    return $p;
};

if (is_post()) {
    csrf_check();
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $form['titulo'] = input_str($_POST, 'titulo', 120);
    $form['tipo'] = isset($tipos[$_POST['tipo'] ?? '']) ? (string) $_POST['tipo'] : 'test';
    $form['descripcion'] = input_str($_POST, 'descripcion', 255);
    $form['contenido'] = is_string($_POST['contenido'] ?? null) ? str_replace("\r\n", "\n", (string) $_POST['contenido']) : '';
    $form['por_partida'] = max(0, min(200, (int) ($_POST['por_partida'] ?? 0)));
    $form['orden'] = max(-999, min(999, (int) ($_POST['orden'] ?? 0)));

    if ($form['titulo'] === '') {
        $errors[] = 'Escribe un título.';
    }
    if (mb_strlen($form['contenido']) > JUEGO_MAX_TEXTO) {
        $errors[] = 'El contenido es demasiado largo.';
    }
    $check = $analizar($form);

    if ($accion !== 'comprobar') {
        $estado = match ($accion) {
            'publicar' => 'publicado',
            'borrador' => 'borrador',
            default    => $form['estado'],
        };
        if ($estado === 'publicado' && $check['errores']) {
            $errors[] = 'No se puede publicar mientras el contenido tenga errores. Corrígelos o guárdalo como borrador.';
        }
        if (!$errors) {
            $vals = [$form['titulo'], $form['tipo'], $form['descripcion'] !== '' ? $form['descripcion'] : null, $form['contenido'],
                $form['por_partida'], $form['orden'], $estado, now(), (int) $user['id']];
            if ($row) {
                q('UPDATE juegos SET titulo = ?, tipo = ?, descripcion = ?, contenido = ?, por_partida = ?, orden = ?, estado = ?,
                   actualizado_en = ?, actualizado_por = ? WHERE id = ?', array_merge($vals, [$id]));
            } else {
                q('INSERT INTO juegos (titulo, tipo, descripcion, contenido, por_partida, orden, estado, actualizado_en, actualizado_por, creado_en)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($vals, [now()]));
                $id = (int) db()->lastInsertId();
            }
            $msg = $estado === 'publicado' ? 'Juego guardado y publicado: ya se ve en la web.' : 'Juego guardado como borrador.';
            if ($check['errores']) {
                $msg .= ' Ojo: aún tiene errores (pulsa «Comprobar» para verlos).';
            }
            flash('ok', $msg);
            redirect('admin/juego.php?id=' . $id);
        }
    }
}

$entradas = q_all("SELECT titulo, slug FROM entradas WHERE estado = 'publicada' ORDER BY publicada_en DESC");
$nuevo = !$row;
admin_header($nuevo ? 'Nuevo juego' : 'Editar juego', 'juegos');
?>
<div class="page-head">
  <h1><?= $nuevo ? 'Nuevo juego' : 'Editar juego' ?></h1>
  <a class="btn btn-ghost" href="<?= e(url('admin/juegos.php')) ?>">Volver a juegos</a>
</div>

<?php if ($errors): ?>
  <div class="flash flash-error" role="alert"><p>Revisa estos puntos:</p><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($check !== null && (($_POST['accion'] ?? '') === 'comprobar' || $errors)):
    $tp = $tipos[$form['tipo']];
    $n = count($check['items']); ?>
  <section class="panel-card preview-box jg-check" aria-labelledby="ck-title">
    <h2 id="ck-title" class="preview-label">Comprobación (aún no guardado)</h2>
    <?php if (!$check['errores']): ?>
      <p class="jg-check-ok"><strong>Todo correcto:</strong> <?= $n ?> <?= e($tp['unidad']) ?><?= $n === 1 ? '' : 's' ?><?php if ($form['tipo'] === 'clasificar'): ?> en <?= count($check['data']['cats']) ?> grupos (<?= e(implode(', ', $check['data']['cats'])) ?>)<?php endif; ?>.
        <?php if ($check['entradas']): ?>Enlaza a: <?= e(implode(', ', array_column($check['entradas'], 't'))) ?>.<?php endif; ?></p>
    <?php else: ?>
      <p><strong><?= count($check['errores']) ?> error<?= count($check['errores']) === 1 ? '' : 'es' ?>:</strong></p>
      <ul class="jg-check-err"><?php foreach ($check['errores'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($check['avisos']): ?>
      <ul class="jg-check-warn"><?php foreach ($check['avisos'] as $av): ?><li><?= e($av) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/juego.php' . ($id ? '?id=' . $id : ''))) ?>" class="editor-form" novalidate>
  <?= csrf_field() ?>
  <div class="editor-main">
    <label for="titulo">Título</label>
    <input id="titulo" name="titulo" required maxlength="120" value="<?= e($form['titulo']) ?>" class="input-title" placeholder="Por ejemplo: ¿Cuánto sabes del blog?">

    <label for="contenido">Contenido del juego</label>
    <p class="hint" id="ct-help">Se escribe con un formato de texto sencillo. Mira la ayuda del tipo de juego aquí debajo. Pulsa «Comprobar» para ver si está bien antes de publicar.</p>
    <div class="jg-editor">
      <textarea id="contenido" name="contenido" rows="22" spellcheck="true" aria-describedby="ct-help"><?= e($form['contenido']) ?></textarea>
    </div>

    <section class="panel-card jg-formatos" aria-labelledby="fmt-title">
      <h2 id="fmt-title">Cómo se escribe cada tipo</h2>
      <p class="hint">En todos: <code>&gt; explicación</code> debajo de un elemento se ve al responder; <code>@ slug-de-la-entrada</code> debajo de un elemento lo enlaza a esa entrada (si va al principio del todo, vale para todo el juego); las líneas que empiezan por <code>//</code> se ignoran.</p>
      <?php foreach ($tipos as $k => $t): ?>
        <details<?= $k === $form['tipo'] ? ' open' : '' ?>>
          <summary><?= e($t['nombre']) ?></summary>
          <p><?= e($t['ayuda']) ?></p>
          <pre><code><?= e($t['ejemplo']) ?></code></pre>
        </details>
      <?php endforeach; ?>
    </section>
  </div>

  <aside class="editor-side">
    <section class="panel-card">
      <h2>Publicación</h2>
      <p>Estado: <?= $form['estado'] === 'publicado' ? '<span class="badge badge-pub">Publicado</span>' : '<span class="badge badge-draft">Borrador</span>' ?></p>
      <div class="btn-col">
        <button class="btn btn-ghost" name="accion" value="comprobar">Comprobar</button>
        <?php if ($form['estado'] === 'publicado'): ?>
          <button class="btn btn-primary" name="accion" value="guardar">Guardar cambios</button>
          <button class="btn btn-ghost" name="accion" value="borrador">Pasar a borrador</button>
        <?php else: ?>
          <button class="btn btn-primary" name="accion" value="publicar">Publicar</button>
          <button class="btn" name="accion" value="borrador">Guardar borrador</button>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel-card">
      <h2>Ajustes</h2>
      <label for="tipo">Tipo de juego</label>
      <select id="tipo" name="tipo">
        <?php foreach ($tipos as $k => $t): ?>
          <option value="<?= e($k) ?>"<?= $k === $form['tipo'] ? ' selected' : '' ?>><?= e($t['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="descripcion">Descripción corta</label>
      <input id="descripcion" name="descripcion" maxlength="255" value="<?= e($form['descripcion']) ?>" aria-describedby="desc-help">
      <p class="hint" id="desc-help">Sale en la tarjeta del juego.</p>

      <label for="por_partida">Elementos por partida</label>
      <input id="por_partida" name="por_partida" type="number" min="0" max="200" value="<?= (int) $form['por_partida'] ?>" aria-describedby="pp-help">
      <p class="hint" id="pp-help">0 = todos (el ahorcado usa 6). Si escribes muchos, cada partida coge unos al azar. No se usa en memoria ni sopa de letras.</p>

      <label for="orden">Orden</label>
      <input id="orden" name="orden" type="number" min="-999" max="999" value="<?= (int) $form['orden'] ?>" aria-describedby="ord-help">
      <p class="hint" id="ord-help">Los números más bajos salen primero.</p>
    </section>

    <section class="panel-card">
      <h2>Entradas publicadas</h2>
      <?php if (!$entradas): ?>
        <p class="hint">Aún no hay entradas publicadas. Los juegos funcionan igual, pero sin enlaces para repasar.</p>
      <?php else: ?>
        <p class="hint">Copia el slug para enlazar con <code>@</code>:</p>
        <ul class="jg-slugs">
          <?php foreach ($entradas as $en): ?>
            <li><span><?= e($en['titulo']) ?></span><code><?= e($en['slug']) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</form>
<?php
admin_footer();
