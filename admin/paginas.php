<?php
/** Panel: páginas editables ("Sobre nosotros" y el texto de la portada). */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();

$PAGES = [
    'sobre-nosotros' => ['Sobre nosotros', 'Página «Sobre nosotros / La asignatura». El título aparece como encabezado de la página.'],
    'portada'        => ['Texto de portada', 'Texto de presentación que sale en la cabecera de la página de inicio. Mejor corto: uno o dos párrafos.'],
];
$slug = (string) ($_GET['p'] ?? 'sobre-nosotros');
if (!isset($PAGES[$slug])) {
    $slug = 'sobre-nosotros';
}

$page = get_pagina($slug);
$form = ['titulo' => $page['titulo'] ?? $PAGES[$slug][0], 'contenido' => $page['contenido'] ?? ''];
$errors = [];
$preview = null;

if (is_post()) {
    csrf_check();
    $form['titulo'] = input_str($_POST, 'titulo', 200);
    $form['contenido'] = is_string($_POST['contenido'] ?? null) ? str_replace("\r\n", "\n", (string) $_POST['contenido']) : '';
    if ($form['titulo'] === '') {
        $errors[] = 'Escribe un título.';
    }
    if (mb_strlen($form['contenido']) > 100000) {
        $errors[] = 'El texto es demasiado largo.';
    }
    if (($_POST['accion'] ?? '') === 'previsualizar') {
        $preview = md_to_html($form['contenido']);
    } elseif (!$errors) {
        q('INSERT INTO paginas (slug, titulo, contenido, actualizada_en, actualizada_por) VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), contenido = VALUES(contenido),
                                   actualizada_en = VALUES(actualizada_en), actualizada_por = VALUES(actualizada_por)',
            [$slug, $form['titulo'], $form['contenido'], now(), (int) $user['id']]);
        flash('ok', 'Página guardada y visible en la web.');
        redirect('admin/paginas.php?p=' . $slug);
    }
}

$editor = $page && $page['actualizada_por'] ? q_val('SELECT nombre FROM usuarios WHERE id = ?', [(int) $page['actualizada_por']]) : null;

admin_header('Páginas', 'paginas');
?>
<div class="page-head"><h1>Páginas</h1></div>
<nav class="filter-tabs" aria-label="Elegir página">
  <?php foreach ($PAGES as $s => [$label]): ?>
    <a href="<?= e(url('admin/paginas.php?p=' . $s)) ?>"<?= $s === $slug ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<p class="hint"><?= e($PAGES[$slug][1]) ?>
<?php if ($page): ?> Última edición: <?= e(fecha_es($page['actualizada_en'], true)) ?><?= $editor ? ' por ' . e((string) $editor) : '' ?>.<?php endif; ?></p>

<?php if ($errors): ?>
  <div class="flash flash-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($preview !== null): ?>
  <section class="panel-card preview-box" aria-labelledby="pv-title">
    <h2 id="pv-title" class="preview-label">Vista previa (aún no guardada)</h2>
    <article class="prose"><?= $preview ?></article>
  </section>
<?php endif; ?>

<form method="post" class="form-stack wide">
  <?= csrf_field() ?>
  <label for="titulo">Título</label>
  <input id="titulo" name="titulo" required maxlength="200" value="<?= e($form['titulo']) ?>">
  <label for="contenido">Contenido (Markdown)</label>
  <div class="md-editor" data-md-editor>
    <textarea id="contenido" name="contenido" rows="18" data-base="<?= e(base_path()) ?>"><?= e($form['contenido']) ?></textarea>
  </div>
  <div class="btn-row">
    <button class="btn btn-primary" name="accion" value="guardar">Guardar página</button>
    <button class="btn btn-ghost" name="accion" value="previsualizar">Vista previa</button>
    <a class="btn btn-ghost" href="<?= e($slug === 'portada' ? url() : url_sobre()) ?>" target="_blank" rel="noopener">Ver en la web</a>
  </div>
</form>
<?php
admin_footer();
