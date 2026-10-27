<?php
/**
 * Funciones auxiliares generales.
 */
declare(strict_types=1);

/** Lee un valor de config.php. */
function cfg(string $key, $default = null)
{
    return $GLOBALS['CONFIG'][$key] ?? $default;
}

/** Escapa texto para HTML (protección XSS en toda la salida). */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Fecha y hora actual en formato MySQL (se genera en PHP para no depender de la zona horaria del servidor MySQL). */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/** ¿La petición llega por HTTPS? (InfinityFree puede indicarlo con X-Forwarded-Proto). */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Ruta base del proyecto en la URL, sin barra final ('' si está en la raíz).
 * Se calcula comparando la carpeta del script con la raíz del proyecto, así
 * funciona igual en la raíz de htdocs, en una subcarpeta y dentro de /admin.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $manual = (string) cfg('base_url', '');
    if ($manual !== '') {
        return $base = rtrim($manual, '/');
    }
    $norm = static fn(string $p): string => str_replace('\\', '/', $p);

    $scriptFile = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $root = $norm((string) realpath(APP_ROOT));
    $scriptDir = $norm(dirname($scriptFile));
    $rel = trim(substr($scriptDir, strlen($root)), '/');
    $depth = $rel === '' ? 0 : substr_count($rel, '/') + 1;

    $urlDir = $norm(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    for ($i = 0; $i < $depth; $i++) {
        $urlDir = $norm(dirname($urlDir));
    }
    return $base = rtrim($urlDir, '/');
}

/** URL interna a partir de una ruta relativa al proyecto. */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** URL de un recurso estático con versión para la caché del navegador. */
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . $path) . '?v=' . $v;
}

/** ¿Existe de verdad el archivo en uploads/? (evita pintar imágenes rotas si se borró o no se subió) */
function upload_exists(?string $file): bool
{
    static $cache = [];
    $file = basename((string) $file);
    if ($file === '') {
        return false;
    }
    return $cache[$file] ??= is_file(APP_ROOT . '/uploads/' . $file);
}

function url_upload(string $file): string
{
    return url('uploads/' . rawurlencode($file));
}

/** Enlaces públicos (amigables o con parámetros según config). */
function url_post(string $slug): string
{
    return cfg('pretty_urls') ? url('entrada/' . rawurlencode($slug)) : url('post.php?slug=' . rawurlencode($slug));
}

function url_cat(string $slug): string
{
    return cfg('pretty_urls') ? url('categoria/' . rawurlencode($slug)) : url('index.php?categoria=' . rawurlencode($slug));
}

function url_tag(string $slug): string
{
    return cfg('pretty_urls') ? url('etiqueta/' . rawurlencode($slug)) : url('index.php?etiqueta=' . rawurlencode($slug));
}

function url_sobre(): string
{
    return cfg('pretty_urls') ? url('sobre-nosotros') : url('sobre.php');
}

/** Página con todas las categorías. */
function url_categorias(): string
{
    return cfg('pretty_urls') ? url('categorias') : url('categorias.php');
}

/** Página con todas las entradas. */
function url_entradas(): string
{
    return cfg('pretty_urls') ? url('entradas') : url('entradas.php');
}

/** Añade parámetros GET a una URL (los vacíos se omiten). */
function with_query(string $u, array $params): string
{
    if (isset($params['pagina']) && (int) $params['pagina'] <= 1) {
        unset($params['pagina']);
    }
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    if (!$params) {
        return $u;
    }
    return $u . (str_contains($u, '?') ? '&' : '?') . http_build_query($params);
}

/** Redirige (siempre a una ruta interna) y termina. */
function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/** Convierte un texto en slug: "Gemelos digitales en la industria" -> "gemelos-digitales-en-la-industria". */
function slugify(string $text, int $max = 200): string
{
    $map = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c',
        'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a', 'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e',
        'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i', 'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o',
        'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u', 'Û' => 'u', 'Ñ' => 'n', 'Ç' => 'c', 'º' => '', 'ª' => '',
    ];
    $s = strtolower(strtr($text, $map));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if (strlen($s) > $max) {
        $s = rtrim(substr($s, 0, $max), '-');
    }
    return $s;
}

/** "2026-10-09 10:00:00" -> "9 de octubre de 2026" */
function fecha_es(?string $mysqlDate, bool $conHora = false): string
{
    if (!$mysqlDate) {
        return '';
    }
    $t = strtotime($mysqlDate);
    if ($t === false) {
        return '';
    }
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $s = date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
    return $conHora ? $s . ', ' . date('H:i', $t) : $s;
}

/** Mes abreviado para el sello de fecha del listado. */
function mes_corto(string $mysqlDate): string
{
    $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return $m[(int) date('n', (int) strtotime($mysqlDate)) - 1];
}

/** Recorta texto sin partir palabras. */
function str_limit(string $s, int $len): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    if (mb_strlen($s) <= $len) {
        return $s;
    }
    $cut = mb_substr($s, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp > $len * 0.6) {
        $cut = mb_substr($cut, 0, $sp);
    }
    return rtrim($cut, " ,.;:") . '…';
}

/** IP del cliente. Se usa REMOTE_ADDR porque las cabeceras X-Forwarded-For las puede falsificar cualquiera. */
function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Lee un entero positivo de GET con valor por defecto. */
function get_int(string $key, int $default = 1): int
{
    $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    return is_int($v) ? $v : $default;
}

/** Lee un texto de GET/POST, recortado y con longitud máxima. Quita caracteres de control. */
function input_str(array $src, string $key, int $max = 255): string
{
    $v = $src[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}

/** Cabeceras de seguridad para todas las respuestas. */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self'; "
        . "script-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    if (cfg('force_https') && is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
    header_remove('X-Powered-By');
}

/** Formatea bytes. */
function human_bytes(int $b): string
{
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';
}

/** Iniciales para el avatar de autor: "Ana García" -> "AG". */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $s .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $s !== '' ? $s : '?';
}

/** Minutos de lectura aproximados (200 palabras por minuto). */
function reading_minutes(string $md): int
{
    $words = count(preg_split('/\s+/u', trim(strip_tags($md))) ?: []);
    return max(1, (int) round($words / 200));
}

/** Variante de color (0-5) estable para cada entrada, para las portadas sin imagen. */
function tone(int $id): int
{
    return $id % 6;
}
