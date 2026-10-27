<?php
/** Inicio de sesión del panel (paso 1: usuario y contraseña). */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
if (current_user()) {
    redirect('admin/');
}

$error = null;
$usuario = '';

if (is_post()) {
    csrf_check();
    $usuario = input_str($_POST, 'usuario', 40);
    $pass = $_POST['password'] ?? '';
    // Campo trampa: invisible para personas, los bots lo rellenan
    $bot = !empty($_POST['web']);
    if ($usuario === '' || !is_string($pass) || $pass === '') {
        $error = 'Escribe tu usuario y tu contraseña.';
    } elseif (strlen($pass) > 200) {
        $error = 'Usuario o contraseña incorrectos.';
    } else {
        $r = attempt_login($usuario, $pass, $bot);
        if (!empty($r['2fa'])) {
            redirect('admin/verificar.php');
        }
        if (!empty($r['ok'])) {
            redirect('admin/');
        }
        $error = $r['error'];
    }
}

admin_header('Entrar', '', false);
?>
<section class="login-box panel-card">
  <a class="brand login-brand" href="<?= e(url()) ?>"><?= logo_svg() ?><span class="brand-name"><?= e((string) cfg('site_name')) ?></span></a>
  <h1>Entrar al panel</h1>
  <?php if ($error): ?>
    <p class="flash flash-error" role="alert" id="login-error"><?= e($error) ?></p>
  <?php endif; ?>
  <form method="post" class="form-stack" action="<?= e(url('admin/login.php')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true">
      <label for="web">No rellenes este campo</label>
      <input id="web" name="web" type="text" tabindex="-1" autocomplete="off">
    </div>
    <label for="usuario">Usuario</label>
    <input id="usuario" name="usuario" required maxlength="40" autocomplete="username" autocapitalize="none" spellcheck="false"
           value="<?= e($usuario) ?>" <?= $error ? 'aria-describedby="login-error"' : 'autofocus' ?>>
    <label for="password">Contraseña</label>
    <input id="password" name="password" type="password" required maxlength="200" autocomplete="current-password">
    <button type="submit" class="btn btn-primary">Entrar</button>
  </form>
  <p class="hint">¿Has olvidado la contraseña? No hay recuperación por correo: tu compañero puede ponerte una nueva desde <em>Mi cuenta</em>.</p>
</section>
<?php
admin_footer();
