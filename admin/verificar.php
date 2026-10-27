<?php
/** Inicio de sesión, paso 2: código de la app de autenticación o código de recuperación. */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
if (current_user()) {
    redirect('admin/');
}
$pending = pending_2fa_user();
if (!$pending) {
    flash('info', 'Vuelve a escribir tu usuario y contraseña.');
    redirect('admin/login.php');
}

$key = '2fa:' . (int) $pending['id'];
$error = null;

if (is_post()) {
    csrf_check();
    $mins = login_blocked($key, 5);
    if ($mins > 0) {
        unset($_SESSION['pending_uid']);
        flash('error', "Demasiados códigos incorrectos. Espera {$mins} min y vuelve a entrar.");
        redirect('admin/login.php');
    }
    $code = input_str($_POST, 'codigo', 20);
    $step = totp_verify((string) $pending['totp_secret'], $code, $pending['totp_ultimo'] !== null ? (int) $pending['totp_ultimo'] : null);
    if ($step !== null) {
        q('UPDATE usuarios SET totp_ultimo = ? WHERE id = ?', [$step, (int) $pending['id']]);
        complete_login((int) $pending['id']);
        redirect('admin/');
    }
    $newJson = recovery_codes_use($pending['totp_recovery'], $code);
    if ($newJson !== null) {
        q('UPDATE usuarios SET totp_recovery = ? WHERE id = ?', [$newJson, (int) $pending['id']]);
        complete_login((int) $pending['id']);
        $left = recovery_codes_left($newJson);
        flash('info', "Has entrado con un código de recuperación. Te quedan {$left}." . ($left <= 2 ? ' Genera nuevos en Mi cuenta.' : ''));
        redirect('admin/');
    }
    record_failure($key);
    $error = 'El código no es correcto o ya se usó. Espera al siguiente código de la app.';
}

admin_header('Verificación en dos pasos', '', false);
?>
<section class="login-box panel-card">
  <a class="brand login-brand" href="<?= e(url()) ?>"><?= logo_svg() ?><span class="brand-name"><?= e((string) cfg('site_name')) ?></span></a>
  <h1>Verificación en dos pasos</h1>
  <p class="muted">Hola, <?= e($pending['nombre']) ?>. Abre tu app de autenticación y escribe el código de 6 cifras.</p>
  <?php if ($error): ?><p class="flash flash-error" role="alert" id="code-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="form-stack" action="<?= e(url('admin/verificar.php')) ?>">
    <?= csrf_field() ?>
    <label for="codigo">Código</label>
    <input id="codigo" name="codigo" required maxlength="20" inputmode="numeric" autocomplete="one-time-code" autofocus
           class="code-input" <?= $error ? 'aria-describedby="code-error"' : '' ?>>
    <button type="submit" class="btn btn-primary">Verificar y entrar</button>
  </form>
  <p class="hint">¿No tienes el móvil? Escribe uno de tus códigos de recuperación (formato <code>abcde-12345</code>). Si tampoco los tienes, tu compañero puede desactivarte la verificación desde <em>Mi cuenta</em>.</p>
  <p class="hint"><a href="<?= e(url('admin/login.php')) ?>">Volver al inicio de sesión</a></p>
</section>
<?php
admin_footer();
