<?php
/** Página "Sobre nosotros / La asignatura" (contenido editable desde el panel). */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$page = get_pagina('sobre-nosotros');
if (!$page) {
    render_404();
}
public_header($page['titulo'], md_plain($page['contenido'], 160), 'sobre');
?>
<header class="post-hero tone-2">
  <div class="hero-bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="hero-mesh"></span></div>
  <div class="wrap narrow post-head">
    <h1 class="post-title"><?= e($page['titulo']) ?></h1>
  </div>
</header>
<article class="wrap narrow post" data-reading>
  <div class="prose">
    <?= md_to_html($page['contenido']) ?>
  </div>
</article>
<?php
public_footer();
