<?php
/**
 * Panel: mi cuenta.
 * - Nombre de autor y contraseña propia (cambiarla cierra tus otras sesiones).
 * - Verificación en dos pasos (TOTP) con códigos de recuperación.
 * - Cerrar la sesión en todos los dispositivos y ver los últimos accesos.
 * - Rescate del compañero: nueva contraseña y/o quitarle la verificación en dos pasos
 *   (sustituye a la recuperación por correo, que el hosting no permite).
 * Todo lo sensible pide tu contraseña actual y tiene límite de intentos.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];

$errors = [];
$me = q_one('SELECT id, usuario, nombre, password_hash, ultimo_acceso, totp_activo, totp_secret, totp_ultimo, totp_recovery FROM usuarios WHERE id = ?', [$uid]);
$others = q_all('SELECT id, usuario, nombre, totp_activo FROM usuarios WHERE id <> ? ORDER BY nombre', [$uid]);
$needsPassword = ['nombre', 'password', 'reset', 'totp_start', 'totp_off', 'totp_codes', 'cerrar_todas'];

if (is_post()) {
    csrf_check();
    $accion = (string) ($_POST['accion'] ?? '');
    $actual = (string) ($_POST['actual'] ?? '');
    $guardKey = 'cuenta:' . $uid;

    if (in_array($accion, $needsPassword, true)) {
        if (login_blocked($guardKey, 5) > 0) {
            $errors[] = 'Demasiadas contraseñas incorrectas. Espera unos minutos.';
        } elseif (!password_verify($actual, $me['password_hash'])) {
            record_failure($guardKey);
            $errors[] = 'Tu contraseña actual no es correcta.';
        }
    }

    if (!$errors) {
        switch ($accion) {
            case 'nombre':
                $nombre = input_str($_POST, 'nombre', 80);
                if ($nombre === '') {
                    $errors[] = 'El nombre no puede quedar vacío.';
                    break;
                }
                q('UPDATE usuarios SET nombre = ? WHERE id = ?', [$nombre, $uid]);
                flash('ok', 'Nombre de autor actualizado.');
                redirect('admin/cuenta.php');

            case 'password':
            case 'reset':
                $nueva = (string) ($_POST['nueva'] ?? '');
                $repite = (string) ($_POST['repite'] ?? '');
                $target = $accion === 'password' ? $uid : (int) ($_POST['usuario_id'] ?? 0);
                $targetRow = q_one('SELECT id, nombre FROM usuarios WHERE id = ?', [$target]);
                $quitar2fa = $accion === 'reset' && !empty($_POST['quitar_2fa']);
                $soloQuitar2fa = $accion === 'reset' && $nueva === '' && $repite === '' && $quitar2fa;
                if (!$targetRow) {
                    $errors[] = 'Ese usuario no existe.';
                } elseif (!$soloQuitar2fa && ($msg = password_problem($nueva))) {
                    $errors[] = $msg;
                } elseif (!$soloQuitar2fa && $nueva !== $repite) {
                    $errors[] = 'Las dos contraseñas nuevas no coinciden.';
                } elseif ($accion === 'password' && hash_equals($actual, $nueva)) {
                    $errors[] = 'La contraseña nueva tiene que ser distinta de la actual.';
                } else {
                    if (!$soloQuitar2fa) {
                        q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [hash_password($nueva), $target]);
                    }
                    if ($quitar2fa) {
                        q('UPDATE usuarios SET totp_activo = 0, totp_secret = NULL, totp_ultimo = NULL, totp_recovery = NULL WHERE id = ?', [$target]);
                    }
                    bump_session_version($target); // cierra sus sesiones abiertas
                    if ($accion === 'password') {
                        refresh_own_session($uid);
                        flash('ok', 'Contraseña cambiada. Se han cerrado tus sesiones en otros dispositivos.');
                    } else {
                        $partes = array_filter([$soloQuitar2fa ? null : 'contraseña nueva', $quitar2fa ? 'verificación en dos pasos desactivada' : null]);
                        flash('ok', $targetRow['nombre'] . ': ' . implode(' y ', $partes) . '. Pásale los datos en persona.');
                    }
                    redirect('admin/cuenta.php');
                }
                break;

            case 'totp_start':
                $_SESSION['totp_pending'] = totp_new_secret();
                redirect('admin/cuenta.php#dos-pasos');

            case 'totp_cancel':
                unset($_SESSION['totp_pending']);
                redirect('admin/cuenta.php#dos-pasos');

            case 'totp_confirm':
                $secret = (string) ($_SESSION['totp_pending'] ?? '');
                $step = $secret !== '' ? totp_verify($secret, input_str($_POST, 'codigo', 20)) : null;
                if ($step === null) {
                    record_failure($guardKey);
                    $errors[] = 'El código no coincide. Comprueba que la hora del móvil es automática y prueba con el siguiente código.';
                    break;
                }
                [$codes, $json] = recovery_codes_new();
                q('UPDATE usuarios SET totp_secret = ?, totp_activo = 1, totp_ultimo = ?, totp_recovery = ? WHERE id = ?', [$secret, $step, $json, $uid]);
                unset($_SESSION['totp_pending']);
                bump_session_version($uid);
                refresh_own_session($uid);
                $_SESSION['show_codes'] = $codes;
                flash('ok', 'Verificación en dos pasos activada. Guarda ahora tus códigos de recuperación.');
                redirect('admin/cuenta.php#dos-pasos');

            case 'totp_codes':
                if ((int) $me['totp_activo'] !== 1) {
                    break;
                }
                [$codes, $json] = recovery_codes_new();
                q('UPDATE usuarios SET totp_recovery = ? WHERE id = ?', [$json, $uid]);
                $_SESSION['show_codes'] = $codes;
                flash('ok', 'Códigos nuevos generados. Los anteriores ya no sirven.');
                redirect('admin/cuenta.php#dos-pasos');

            case 'totp_off':
                $code = input_str($_POST, 'codigo', 20);
                $valid = totp_verify((string) $me['totp_secret'], $code) !== null || recovery_codes_use($me['totp_recovery'], $code) !== null;
                if (!$valid) {
                    record_failure($guardKey);
                    $errors[] = 'Para desactivarla escribe un código válido de la app o de recuperación.';
                    break;
                }
                q('UPDATE usuarios SET totp_activo = 0, totp_secret = NULL, totp_ultimo = NULL, totp_recovery = NULL WHERE id = ?', [$uid]);
                flash('ok', 'Verificación en dos pasos desactivada.');
                redirect('admin/cuenta.php#dos-pasos');

            case 'cerrar_todas':
                bump_session_version($uid);
                refresh_own_session($uid);
                flash('ok', 'Se han cerrado todas tus sesiones salvo esta.');
                redirect('admin/cuenta.php');
        }
    }
}

$pendingSecret = (string) ($_SESSION['totp_pending'] ?? '');
$showCodes = $_SESSION['show_codes'] ?? null;
unset($_SESSION['show_codes']);
$accesos = q_all('SELECT ip, agente, creado_en FROM accesos WHERE usuario_id = ? ORDER BY id DESC LIMIT 10', [$uid]);

admin_header('Mi cuenta', 'cuenta');
?>
<div class="page-head"><h1>Mi cuenta</h1></div>
<p class="muted">Usuario: <strong><?= e($me['usuario']) ?></strong>. Último acceso: <?= e(fecha_es($me['ultimo_acceso'], true)) ?>.</p>

<?php if ($errors): ?>
  <div class="flash flash-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="panel-card twofa" id="dos-pasos">
  <h2>Verificación en dos pasos <?= (int) $me['totp_activo'] === 1 ? '<span class="badge badge-pub">Activada</span>' : '<span class="badge badge-draft">Desactivada</span>' ?></h2>

  <?php if ($showCodes): ?>
    <div class="codes-box" role="status">
      <p><strong>Tus códigos de recuperación.</strong> Cada uno sirve una sola vez para entrar si pierdes el móvil. Apúntalos o haz una captura y guárdalos fuera del ordenador. No se volverán a mostrar.</p>
      <ol class="codes"><?php foreach ($showCodes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?></ol>
    </div>
  <?php endif; ?>

  <?php if ((int) $me['totp_activo'] === 1): ?>
    <p>Al entrar, además de la contraseña, se te pide el código de 6 cifras de tu app. Te quedan <strong><?= recovery_codes_left($me['totp_recovery']) ?></strong> códigos de recuperación.</p>
    <div class="cards-2">
      <form method="post" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="totp_codes">
        <label for="tc-a">Tu contraseña actual</label>
        <input id="tc-a" name="actual" type="password" required autocomplete="current-password">
        <button class="btn">Generar códigos de recuperación nuevos</button>
      </form>
      <form method="post" class="form-stack" data-confirm="¿Desactivar la verificación en dos pasos? Tu cuenta quedará protegida solo por la contraseña.">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="totp_off">
        <label for="to-c">Código de la app (o de recuperación)</label>
        <input id="to-c" name="codigo" required maxlength="20" inputmode="numeric" autocomplete="one-time-code">
        <label for="to-a">Tu contraseña actual</label>
        <input id="to-a" name="actual" type="password" required autocomplete="current-password">
        <button class="btn btn-danger">Desactivar</button>
      </form>
    </div>
  <?php elseif ($pendingSecret !== ''): ?>
    <ol class="steps">
      <li>Instala una app de autenticación en el móvil: Google Authenticator, Microsoft Authenticator, 2FAS o Authy.</li>
      <li>En la app, pulsa «Añadir» y escanea este código QR. Si no puedes escanearlo, elige «Introducir clave» y escribe la clave de debajo.</li>
      <li>Escribe el código de 6 cifras que te muestra la app.</li>
    </ol>
    <div class="qr-row">
      <div class="qr" data-qr="<?= e(totp_uri($pendingSecret, $me['usuario'])) ?>" aria-label="Código QR para la app de autenticación" role="img">
        <noscript><p class="hint">Activa JavaScript para ver el QR o usa la clave manual.</p></noscript>
      </div>
      <div>
        <p class="hint">Clave manual (tipo «basada en tiempo»):</p>
        <p class="secret"><code><?= e(trim(chunk_split($pendingSecret, 4, ' '))) ?></code></p>
        <form method="post" class="form-stack">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="totp_confirm">
          <label for="tf-c">Código de 6 cifras</label>
          <input id="tf-c" name="codigo" required maxlength="8" inputmode="numeric" autocomplete="one-time-code" class="code-input">
          <div class="btn-row">
            <button class="btn btn-primary">Activar</button>
          </div>
        </form>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="totp_cancel">
          <button class="btn btn-ghost btn-small">Cancelar</button>
        </form>
      </div>
    </div>
  <?php else: ?>
    <p>Añade una segunda llave a tu cuenta: aunque alguien descubra tu contraseña, no podrá entrar sin el código de tu móvil. <strong>Tu contraseña no cambia.</strong></p>
    <form method="post" class="form-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="totp_start">
      <div class="grow"><label for="ts-a">Tu contraseña actual</label><input id="ts-a" name="actual" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary">Empezar a configurar</button>
    </form>
  <?php endif; ?>
</section>

<div class="cards-2 account-grid">
  <section class="panel-card">
    <h2>Cambiar mi contraseña</h2>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="password">
      <label for="pa">Contraseña actual</label>
      <input id="pa" name="actual" type="password" required autocomplete="current-password">
      <label for="pn">Contraseña nueva</label>
      <input id="pn" name="nueva" type="password" required minlength="10" maxlength="72" autocomplete="new-password" aria-describedby="pw-rule">
      <label for="pr">Repite la nueva</label>
      <input id="pr" name="repite" type="password" required minlength="10" maxlength="72" autocomplete="new-password">
      <p class="hint" id="pw-rule">Mínimo 10 caracteres, con al menos una letra y un número. Opcional: tu contraseña actual sigue valiendo si no la cambias.</p>
      <button class="btn btn-primary">Cambiar contraseña</button>
    </form>
  </section>

  <section class="panel-card">
    <h2>Nombre de autor</h2>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="nombre">
      <label for="nm">Nombre que aparece en las entradas</label>
      <input id="nm" name="nombre" required maxlength="80" value="<?= e($me['nombre']) ?>">
      <label for="na">Tu contraseña actual</label>
      <input id="na" name="actual" type="password" required autocomplete="current-password">
      <button class="btn">Guardar nombre</button>
    </form>
  </section>

  <section class="panel-card">
    <h2>Sesiones y accesos</h2>
    <?php if ($accesos): ?>
      <ul class="access-list">
        <?php foreach ($accesos as $a): ?>
          <li><span><?= e($a['agente']) ?></span><span class="muted"><?= e(fecha_es($a['creado_en'], true)) ?>, IP <?= e($a['ip']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="hint">Los accesos se registran a partir de esta versión.</p>
    <?php endif; ?>
    <form method="post" class="form-stack" data-confirm="¿Cerrar tu sesión en todos los demás dispositivos?">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="cerrar_todas">
      <label for="ca">Tu contraseña actual</label>
      <input id="ca" name="actual" type="password" required autocomplete="current-password">
      <button class="btn btn-ghost">Cerrar sesión en todos los demás dispositivos</button>
    </form>
  </section>

  <?php if ($others): ?>
  <section class="panel-card">
    <h2>Rescatar la cuenta de tu compañero</h2>
    <p class="hint">Si olvida la contraseña o pierde el móvil. Puedes ponerle una contraseña nueva, quitarle la verificación en dos pasos o ambas cosas. Sus sesiones abiertas se cierran.</p>
    <form method="post" class="form-stack" data-confirm="¿Seguro que quieres cambiar la seguridad de la cuenta de tu compañero?">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="reset">
      <label for="ru">Cuenta</label>
      <select id="ru" name="usuario_id">
        <?php foreach ($others as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['nombre']) ?> (<?= e($o['usuario']) ?>)<?= (int) $o['totp_activo'] === 1 ? ', con verificación en dos pasos' : '' ?></option><?php endforeach; ?>
      </select>
      <label for="rn">Contraseña nueva (déjalo vacío para no cambiarla)</label>
      <input id="rn" name="nueva" type="password" minlength="10" maxlength="72" autocomplete="new-password">
      <label for="rr">Repítela</label>
      <input id="rr" name="repite" type="password" minlength="10" maxlength="72" autocomplete="new-password">
      <label class="check"><input type="checkbox" name="quitar_2fa" value="1"> Quitarle la verificación en dos pasos</label>
      <label for="ra">Tu contraseña actual</label>
      <input id="ra" name="actual" type="password" required autocomplete="current-password">
      <button class="btn btn-ghost">Aplicar</button>
    </form>
  </section>
  <?php endif; ?>
</div>
<?php
admin_footer();
