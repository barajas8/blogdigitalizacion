<?php
/**
 * Panel: estado de seguridad.
 * Revisa el PHP del servidor (PHP2Shell y CVE-2026-115560) y la configuración del blog.
 * Solo lee información; no cambia nada.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$checks = [];
$add = static function (string $lvl, string $title, string $text) use (&$checks): void {
    $checks[] = [$lvl, $title, $text];
};

// --- PHP del servidor ---
[$lvl, $txt] = php2shell_status();
$add($lvl, 'PHP2Shell (CVE-2026-109618 y CVE-2026-109619)', $txt . ' Este fallo está en el propio PHP del servidor: solo lo arregla tu hosting actualizando PHP. Ningún código de la web puede parchearlo.');

$up = filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN);
$add($up ? 'warn' : 'ok', 'session.upload_progress (mitigación de PHP2Shell y CVE-2026-115560)',
    $up ? 'Está activado en el servidor. Es la mitigación que recomienda PHP mientras no haya parche, pero solo la puede cambiar el hosting en php.ini (en hostings gratuitos como InfinityFree no se puede desde .htaccess ni con ini_set).'
        : 'Desactivado: el servidor ya aplica la mitigación recomendada.');

$add('ok', 'CVE-2026-115560 (sin parche oficial todavía)',
    'Solo afecta a aplicaciones que cambian session.serialize_handler en tiempo de ejecución. Este blog no lo hace en ningún archivo, así que no cumple esa condición. Manejador actual: ' . e((string) ini_get('session.serialize_handler')) . '.');

$add(ini_get('display_errors') && ini_get('display_errors') !== '0' && strtolower((string) ini_get('display_errors')) !== 'off' ? 'warn' : 'ok',
    'Errores ocultos al público', 'display_errors = ' . e((string) ini_get('display_errors')) . '. La web además los desactiva por su cuenta si debug está en false.');

// --- Configuración del blog ---
$add(cfg('debug') ? 'bad' : 'ok', 'Modo debug', cfg('debug') ? 'Está activado en config.php: ponlo en false, enseña detalles internos.' : 'Desactivado.');
$add(is_https() ? 'ok' : 'warn', 'Conexión HTTPS', is_https() ? 'Estás entrando por HTTPS: la contraseña y la cookie viajan cifradas.' : 'Estás entrando por HTTP: la contraseña viaja sin cifrar. Activa el SSL de tu hosting y luego force_https.');
$add(cfg('force_https') ? 'ok' : 'warn', 'Forzar HTTPS', cfg('force_https') ? 'Activado, con cabecera HSTS.' : 'Desactivado. Cuando el SSL funcione, pon force_https en true y descomenta la redirección del .htaccess.');
$add(is_file(APP_ROOT . '/install.php') ? 'bad' : 'ok', 'Instalador', is_file(APP_ROOT . '/install.php') ? 'install.php sigue en el servidor: bórralo.' : 'install.php no está en el servidor.');
$add(is_file(APP_ROOT . '/uploads/.htaccess') ? 'ok' : 'bad', 'Carpeta de imágenes protegida', is_file(APP_ROOT . '/uploads/.htaccess') ? 'uploads/.htaccess presente: allí solo se sirven imágenes y nunca se ejecuta código.' : 'Falta uploads/.htaccess: vuelve a subirlo.');
$sessDir = APP_ROOT . '/includes/sessions';
$add(session_save_path() === $sessDir ? 'ok' : 'warn', 'Sesiones en carpeta privada',
    session_save_path() === $sessDir ? 'Las sesiones del panel se guardan en includes/sessions (bloqueada desde la web), no en la carpeta temporal compartida.' : 'No se pudo usar includes/sessions; se usa la carpeta del hosting.');
$perm = @fileperms(APP_ROOT . '/config.php');
$add($perm !== false && ($perm & 0x0002) ? 'warn' : 'ok', 'Permisos de config.php', $perm !== false ? 'Permisos ' . substr(sprintf('%o', $perm), -3) . ($perm & 0x0002 ? ': cualquiera puede escribirlo; ponlo en 644 desde el gestor de archivos.' : '.') : 'No se pudieron leer.');

// --- Cuentas ---
foreach (q_all('SELECT nombre, totp_activo FROM usuarios ORDER BY nombre') as $u) {
    $add((int) $u['totp_activo'] === 1 ? 'ok' : 'warn', 'Verificación en dos pasos de ' . $u['nombre'],
        (int) $u['totp_activo'] === 1 ? 'Activada.' : 'Desactivada. Se activa en Mi cuenta en 1 minuto y no cambia la contraseña.');
}
$fails = (int) q_val('SELECT COUNT(*) FROM intentos_login WHERE creado_en > ?', [date('Y-m-d H:i:s', time() - 86400)]);
$add($fails > 20 ? 'warn' : 'ok', 'Intentos fallidos en las últimas 24 h', $fails . ' intento(s). Se bloquea al usuario tras 5 fallos y a la IP tras 10, durante 15 minutos.');

$icons = ['ok' => '✓', 'warn' => '!', 'bad' => '×'];
admin_header('Seguridad', 'seguridad');
?>
<div class="page-head"><h1>Seguridad</h1></div>
<p class="muted">Servidor: PHP <?= e(PHP_VERSION) ?> (<?= e(PHP_SAPI) ?>). Esta página solo comprueba, no cambia nada.</p>
<ul class="check-list">
  <?php foreach ($checks as [$lvl, $title, $text]): ?>
    <li class="lvl-<?= e($lvl) ?>"><span class="dot" aria-hidden="true"><?= $icons[$lvl] ?></span>
      <div><strong><?= e($title) ?></strong><p><?= $text ?></p></div></li>
  <?php endforeach; ?>
</ul>
<?php
admin_footer();
