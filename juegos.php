<?php
/**
 * Sección pública de juegos.
 * Los juegos se crean en Panel > Juegos. Aquí solo se leen los publicados
 * y se pasan (como JSON en un atributo) a assets/js/juegos.js.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
ensure_juegos_schema();

$web = juegos_para_web();

public_header('Juegos', 'Juegos para repasar las entradas del blog.', 'juegos');
?>
<header class="post-hero tone-1 jg-hero">
  <div class="hero-bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="hero-mesh"></span></div>
  <div class="wrap post-head">
    <span class="chip">Repasa jugando</span>
    <h1 class="post-title">Juegos</h1>
    <p class="jg-lead">Pon a prueba lo que has leído en el blog. Al responder te enlazamos a la entrada de donde sale cada pregunta.</p>
  </div>
</header>

<?php if (!$web['juegos']): ?>
<div class="wrap jg-page">
  <div class="empty-state reveal">
    <h2>Aún no hay juegos</h2>
    <p>Cuando el equipo publique juegos sobre las entradas, aparecerán aquí. Mientras tanto, puedes leer todas las <a href="<?= e(url_entradas()) ?>">entradas</a>.</p>
  </div>
</div>
<?php else: ?>
<div class="wrap jg-page" data-juegos data-datos="<?= e(json_encode($web, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
  <noscript>
    <div class="empty-state">
      <h2>Los juegos necesitan JavaScript</h2>
      <p>Activa JavaScript en tu navegador para jugar. Mientras tanto, puedes leer todas las <a href="<?= e(url_entradas()) ?>">entradas</a>.</p>
    </div>
  </noscript>
</div>
<script src="<?= e(asset('js/juegos.js')) ?>" defer></script>
<?php endif; ?>
<?php
public_footer();
