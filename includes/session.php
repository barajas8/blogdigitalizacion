<?php
/**
 * Sesión, CSRF, mensajes flash y autenticación del panel (v3, reforzada).
 * La parte pública NO abre sesión (menos carga y ninguna cookie para visitantes).
 *
 * Capas del login:
 *  1. Token CSRF + comprobación de Origin / Sec-Fetch-Site en todo POST.
 *  2. Campo trampa (honeypot) contra bots.
 *  3. Límite de intentos por usuario y por IP guardado en la base de datos + retardo en cada fallo.
 *  4. Contraseña con bcrypt (las actuales NO cambian) y comparación en tiempo constante.
 *  5. Verificación en dos pasos opcional (TOTP) con códigos de recuperación.
 *  6. Sesión: ID nuevo al entrar, cookie HttpOnly/SameSite/Secure solo en /admin/, atada al
 *     navegador, caducidad por inactividad y absoluta, y "versión de sesión" para cerrar
 *     todas las sesiones abiertas al cambiar contraseña o pulsar "cerrar en todos los dispositivos".
 *  7. Sesiones guardadas en una carpeta propia protegida (no en /tmp compartido del hosting).
 */
declare(strict_types=1);

/** Abre la sesión con cookies seguras. */
function start_secure_session(bool $installer = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');   // rechaza IDs de sesión inventados
    ini_set('session.use_only_cookies', '1');  // nunca el ID en la URL
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) max(1440, (int) cfg('session_timeout')));

    // Carpeta propia para los archivos de sesión (en un hosting compartido /tmp lo comparten
    // muchas cuentas). includes/ ya está bloqueada desde la web por .htaccess.
    $dir = APP_ROOT . '/includes/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '50');
    }

    session_name($installer ? 'dap_install' : 'dap_admin');
    session_set_cookie_params([
        'lifetime' => 0,                       // muere al cerrar el navegador
        'path'     => base_path() . ($installer ? '/' : '/admin/'), // la cookie del panel solo viaja a /admin/
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Regenera el ID cada 15 minutos para acortar la vida de un ID robado.
    $now = time();
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = $now;
        $_SESSION['_regen'] = $now;
    } elseif ($now - (int) $_SESSION['_regen'] > 900) {
        session_regenerate_id(true);
        $_SESSION['_regen'] = $now;
    }
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** ¿La petición viene de otra web? (Origin y Sec-Fetch-Site los pone el navegador y no se pueden falsificar desde JS) */
function cross_site_request(): bool
{
    $site = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($site === 'cross-site') {
        return true;
    }
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && $origin !== 'null') {
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $port = parse_url($origin, PHP_URL_PORT);
        $self = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $selfHost = (string) preg_replace('/:\d+$/', '', $self);
        return !($host === $self || $host === $selfHost || ($port && "{$host}:{$port}" === $self));
    }
    return false;
}

/** Comprueba el token en cualquier POST. Si falla, corta la petición. */
function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (cross_site_request() || !is_string($sent) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $sent)) {
        render_error(400, 'El formulario ha caducado',
            'Vuelve a la página anterior, recárgala e inténtalo otra vez. Por seguridad, cada formulario lleva un código que caduca con la sesión.',
            true);
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---------- Mensajes flash ---------- */

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------- Autenticación ---------- */

/** Usuario actual o null. Comprueba inactividad, caducidad, navegador y versión de sesión. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    start_secure_session();
    ensure_schema();
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return $user = null;
    }
    $now = time();
    $idle = $now - (int) ($_SESSION['last_activity'] ?? 0);
    $age = $now - (int) ($_SESSION['login_time'] ?? 0);
    $expired = $idle > (int) cfg('session_timeout') || $age > (int) cfg('session_absolute_max');
    $otherBrowser = !hash_equals((string) ($_SESSION['ua'] ?? ''), ua_fingerprint());

    $row = $expired || $otherBrowser ? null
        : q_one('SELECT id, usuario, nombre, sesion_version, totp_activo FROM usuarios WHERE id = ?', [$uid]);
    $revoked = $row && (int) $row['sesion_version'] !== (int) ($_SESSION['sv'] ?? 0);

    if (!$row || $revoked) {
        logout_user();
        start_secure_session();
        if ($expired) {
            flash('info', 'Tu sesión se ha cerrado por inactividad. Vuelve a entrar.');
        } elseif ($revoked) {
            flash('info', 'Tu sesión se cerró porque se cambió la contraseña o se cerraron todas las sesiones.');
        }
        return $user = null;
    }
    $_SESSION['last_activity'] = $now;
    return $user = $row;
}

/** Corta el acceso a páginas del panel si no hay sesión. */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('admin/login.php');
    }
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, private');
    return $u;
}

/** Registra un intento fallido (contraseña, código 2FA o trampa). */
function record_failure(string $key): void
{
    q('INSERT INTO intentos_login (ip, usuario, creado_en) VALUES (?, ?, ?)', [client_ip(), mb_substr($key, 0, 40), now()]);
    // Retardo aleatorio: frena la fuerza bruta y no da pistas por tiempos.
    usleep(random_int(400000, 900000));
}

/** Minutos de bloqueo restantes para un usuario o la IP actual (0 = no bloqueado). */
function login_blocked(string $usuario, ?int $maxUser = null): int
{
    $window = (int) cfg('login_window');
    $since = date('Y-m-d H:i:s', time() - $window);
    $byUser = q_one('SELECT COUNT(*) n, MIN(creado_en) primero FROM intentos_login WHERE usuario = ? AND creado_en > ?', [$usuario, $since]);
    $byIp = q_one('SELECT COUNT(*) n, MIN(creado_en) primero FROM intentos_login WHERE ip = ? AND creado_en > ?', [client_ip(), $since]);

    $first = null;
    if ((int) $byUser['n'] >= ($maxUser ?? (int) cfg('login_max_per_user'))) {
        $first = $byUser['primero'];
    }
    if ((int) $byIp['n'] >= (int) cfg('login_max_per_ip')) {
        $first = $first ? min($first, $byIp['primero']) : $byIp['primero'];
    }
    if (!$first) {
        return 0;
    }
    return max(1, (int) ceil((strtotime($first) + $window - time()) / 60));
}

/**
 * Primer paso del login (usuario + contraseña).
 * Devuelve ['error' => texto], ['2fa' => true] (falta el código) o ['ok' => true].
 */
function attempt_login(string $usuario, string $password, bool $bot = false): array
{
    $usuario = mb_strtolower($usuario);
    ensure_schema();

    if (random_int(1, 20) === 1) {
        q('DELETE FROM intentos_login WHERE creado_en < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }

    $mins = login_blocked($usuario);
    if ($mins > 0) {
        return ['error' => "Demasiados intentos fallidos. Espera {$mins} min y vuelve a probar."];
    }

    $row = q_one('SELECT id, password_hash, totp_activo, ultimo_acceso FROM usuarios WHERE usuario = ?', [$usuario]);
    // Hash falso para que un usuario inexistente tarde lo mismo.
    $hash = $row['password_hash'] ?? '$2y$12$0000000000000000000000.000000000000000000000000000000';
    $ok = password_verify($password, $hash) && $row !== null && !$bot;

    if (!$ok) {
        record_failure($usuario);
        return ['error' => 'Usuario o contraseña incorrectos.'];
    }

    // Mejora el hash si PHP recomienda otro coste/algoritmo (la contraseña sigue siendo la misma).
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT, ['cost' => 12])) {
        q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]), $row['id']]);
    }

    // Fallos con este usuario desde su último acceso (se avisa al entrar)
    $fails = (int) q_val('SELECT COUNT(*) FROM intentos_login WHERE usuario = ? AND creado_en > ?',
        [$usuario, $row['ultimo_acceso'] ?? '1970-01-01 00:00:00']);

    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['_created'] = time();
    $_SESSION['_regen'] = time();
    $_SESSION['fails_seen'] = $fails;

    if ((int) $row['totp_activo'] === 1) {
        // Contraseña correcta, pero aún NO hay sesión: falta el segundo paso.
        $_SESSION['pending_uid'] = (int) $row['id'];
        $_SESSION['pending_time'] = time();
        $_SESSION['pending_ua'] = ua_fingerprint();
        return ['2fa' => true];
    }
    complete_login((int) $row['id']);
    return ['ok' => true];
}

/** Usuario pendiente del segundo paso (o null si no hay o ha caducado: 5 minutos). */
function pending_2fa_user(): ?array
{
    $uid = (int) ($_SESSION['pending_uid'] ?? 0);
    if ($uid <= 0 || time() - (int) ($_SESSION['pending_time'] ?? 0) > 300
        || !hash_equals((string) ($_SESSION['pending_ua'] ?? ''), ua_fingerprint())) {
        unset($_SESSION['pending_uid'], $_SESSION['pending_time'], $_SESSION['pending_ua']);
        return null;
    }
    return q_one('SELECT id, usuario, nombre, totp_secret, totp_ultimo, totp_recovery FROM usuarios WHERE id = ? AND totp_activo = 1', [$uid]);
}

/** Abre la sesión definitiva y deja constancia del acceso. */
function complete_login(int $uid): void
{
    $row = q_one('SELECT usuario, sesion_version FROM usuarios WHERE id = ?', [$uid]);
    q('DELETE FROM intentos_login WHERE usuario IN (?, ?) OR ip = ?', [$row['usuario'], '2fa:' . $uid, client_ip()]);
    q('UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?', [now(), $uid]);
    q('INSERT INTO accesos (usuario_id, ip, agente, creado_en) VALUES (?, ?, ?, ?)', [$uid, client_ip(), short_agent(), now()]);
    // Se guardan solo los 30 accesos más recientes de cada usuario
    $keep = q_val('SELECT id FROM accesos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1 OFFSET 29', [$uid]);
    if ($keep !== null) {
        q('DELETE FROM accesos WHERE usuario_id = ? AND id < ?', [$uid, (int) $keep]);
    }

    $fails = (int) ($_SESSION['fails_seen'] ?? 0);
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['uid'] = $uid;
    $_SESSION['sv'] = (int) $row['sesion_version'];
    $_SESSION['ua'] = ua_fingerprint();
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['_created'] = time();
    $_SESSION['_regen'] = time();
    if ($fails > 0) {
        flash('error', "Aviso: desde tu último acceso ha habido {$fails} intento(s) fallido(s) de entrar con tu usuario. Si no fuiste tú, activa la verificación en dos pasos en Mi cuenta.");
    }
}

/** Invalida todas las sesiones abiertas de un usuario (en cualquier dispositivo). */
function bump_session_version(int $uid): void
{
    q('UPDATE usuarios SET sesion_version = sesion_version + 1 WHERE id = ?', [$uid]);
}

/** Mantiene viva la sesión actual tras cambiar la versión (para no echarte a ti mismo). */
function refresh_own_session(int $uid): void
{
    $_SESSION['sv'] = (int) q_val('SELECT sesion_version FROM usuarios WHERE id = ?', [$uid]);
    session_regenerate_id(true);
}

function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_destroy();
}

/** Reglas para contraseñas NUEVAS: 10 a 72 bytes (límite de bcrypt). Las actuales no se tocan. */
function password_problem(string $pass): ?string
{
    if (mb_strlen($pass) < 10) {
        return 'La contraseña debe tener al menos 10 caracteres.';
    }
    if (strlen($pass) > 72) {
        return 'La contraseña es demasiado larga (máximo 72 caracteres).';
    }
    if (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        return 'La contraseña debe incluir al menos una letra y un número.';
    }
    return null;
}

function hash_password(string $pass): string
{
    return password_hash($pass, PASSWORD_DEFAULT, ['cost' => 12]);
}
