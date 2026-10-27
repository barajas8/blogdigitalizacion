<?php
/**
 * Seguridad v3
 * - Migración automática de la base de datos (añade columnas, no borra nada).
 * - Verificación en dos pasos (TOTP, compatible con Google Authenticator, Microsoft
 *   Authenticator, Authy, 2FAS...). Opcional por usuario: NO cambia las contraseñas.
 * - Códigos de recuperación de un solo uso.
 * - Diagnóstico del PHP del servidor frente a PHP2Shell (CVE-2026-109618 / 109619)
 *   y CVE-2026-115560.
 */
declare(strict_types=1);

/* ================= Migración automática ================= */

/**
 * Añade lo que falte en la base de datos. Solo AÑADE columnas/tablas: nunca borra datos.
 * Se ejecuta una vez y deja includes/schema-v3.lock para no repetirse.
 */
function ensure_schema(): void
{
    $lock = APP_ROOT . '/includes/schema-v3.lock';
    if (is_file($lock)) {
        return;
    }
    $col = static fn(string $t, string $c): bool => (int) q_val(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]) > 0;
    try {
        if (!$col('entradas', 'fijada')) {
            db()->exec('ALTER TABLE entradas ADD COLUMN fijada TINYINT(1) NOT NULL DEFAULT 0 AFTER estado, ADD KEY idx_entradas_fijada (estado, fijada, publicada_en)');
        }
        $add = [];
        if (!$col('usuarios', 'totp_secret'))    { $add[] = 'ADD COLUMN totp_secret VARCHAR(64) NULL'; }
        if (!$col('usuarios', 'totp_activo'))    { $add[] = 'ADD COLUMN totp_activo TINYINT(1) NOT NULL DEFAULT 0'; }
        if (!$col('usuarios', 'totp_ultimo'))    { $add[] = 'ADD COLUMN totp_ultimo BIGINT NULL'; }
        if (!$col('usuarios', 'totp_recovery'))  { $add[] = 'ADD COLUMN totp_recovery TEXT NULL'; }
        if (!$col('usuarios', 'sesion_version')) { $add[] = 'ADD COLUMN sesion_version INT UNSIGNED NOT NULL DEFAULT 1'; }
        if ($add) {
            db()->exec('ALTER TABLE usuarios ' . implode(', ', $add));
        }
        db()->exec("CREATE TABLE IF NOT EXISTS accesos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id INT UNSIGNED NOT NULL,
            ip VARCHAR(45) NOT NULL,
            agente VARCHAR(200) NOT NULL,
            creado_en DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_accesos_usuario (usuario_id, creado_en),
            CONSTRAINT fk_accesos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @file_put_contents($lock, date('d/m/Y H:i'));
    } catch (Throwable $ex) {
        error_log('Migración de seguridad: ' . $ex->getMessage());
        render_error(500, 'Falta actualizar la base de datos',
            'No se pudo aplicar la migración automática. Ejecuta sql/migracion-seguridad.sql en phpMyAdmin (pestaña SQL) y vuelve a entrar.', false);
    }
}

/* ================= TOTP (RFC 6238) ================= */

function base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '');
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20)); // 160 bits, lo recomendado por el RFC
}

function totp_code(string $secret, int $step): string
{
    $key = base32_decode($secret);
    $msg = pack('N*', 0, $step);              // contador de 64 bits big-endian
    $h = hash_hmac('sha1', $msg, $key, true);
    $o = ord($h[19]) & 0x0F;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Comprueba un código de 6 cifras con ±1 intervalo de margen (relojes desfasados).
 * Devuelve el intervalo usado (para impedir reutilizar el mismo código) o null.
 */
function totp_verify(string $secret, string $code, ?int $lastStep = null): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return null;
    }
    $now = intdiv(time(), 30);
    for ($d = -1; $d <= 1; $d++) {
        $step = $now + $d;
        if ($lastStep !== null && $step <= $lastStep) {
            continue; // ese código (o uno anterior) ya se usó
        }
        if (hash_equals(totp_code($secret, $step), $code)) {
            return $step;
        }
    }
    return null;
}

function totp_uri(string $secret, string $user): string
{
    $issuer = 'Blog ' . (string) cfg('site_tagline', 'ASIR');
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $user)
        . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

/** 8 códigos de recuperación. Devuelve [texto plano para mostrar una vez, JSON con hashes para guardar]. */
function recovery_codes_new(): array
{
    $plain = [];
    for ($i = 0; $i < 8; $i++) {
        $h = bin2hex(random_bytes(5));
        $plain[] = substr($h, 0, 5) . '-' . substr($h, 5);
    }
    $hashes = array_map(static fn($c) => hash('sha256', $c), $plain);
    return [$plain, json_encode($hashes)];
}

/** Consume un código de recuperación si es válido. Devuelve el JSON actualizado o null. */
function recovery_codes_use(?string $json, string $code): ?string
{
    $code = strtolower(trim($code));
    $list = json_decode((string) $json, true);
    if (!is_array($list) || !preg_match('/^[0-9a-f]{5}-[0-9a-f]{5}$/', $code)) {
        return null;
    }
    $h = hash('sha256', $code);
    foreach ($list as $i => $stored) {
        if (is_string($stored) && hash_equals($stored, $h)) {
            unset($list[$i]);
            return json_encode(array_values($list));
        }
    }
    return null;
}

function recovery_codes_left(?string $json): int
{
    $l = json_decode((string) $json, true);
    return is_array($l) ? count($l) : 0;
}

/* ================= Huella del navegador para la sesión ================= */

function ua_fingerprint(): string
{
    return hash('sha256', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400));
}

function short_agent(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = match (true) {
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Mac OS') => 'macOS',
        str_contains($ua, 'Linux') => 'Linux',
        default => 'Otro sistema',
    };
    $br = match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'OPR/') => 'Opera',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Safari/') => 'Safari',
        default => 'navegador desconocido',
    };
    return "$br en $os";
}

/* ================= Diagnóstico del servidor ================= */

/**
 * Estado frente a PHP2Shell. Versiones con el parche oficial (21/10/2026):
 * 8.2.35, 8.3.36, 8.4.27 y 8.5.12.
 * Devuelve [nivel ok|warn|bad, texto].
 */
function php2shell_status(): array
{
    $fixed = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
    $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    if (isset($fixed[$branch])) {
        return version_compare(PHP_VERSION, $fixed[$branch], '>=')
            ? ['ok', 'PHP ' . PHP_VERSION . ' ya incluye el parche oficial de PHP2Shell (mínimo ' . $fixed[$branch] . ').']
            : ['warn', 'PHP ' . PHP_VERSION . ' es anterior a ' . $fixed[$branch] . '. Algunos hostings aplican el parche sin cambiar el número, pero hay que confirmarlo con tu hosting.'];
    }
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        return ['bad', 'PHP ' . PHP_VERSION . ' ya no recibe parches oficiales. Pide a tu hosting pasar a PHP 8.2.35 o superior.'];
    }
    return ['ok', 'PHP ' . PHP_VERSION . ' es una rama posterior a las afectadas.'];
}
