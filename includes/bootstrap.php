<?php
/**
 * Arranque común. Lo incluye cada página pública y del panel.
 * - Carga config.php
 * - Ajusta zona horaria, codificación y errores
 * - Fuerza HTTPS (si está activado) y envía cabeceras de seguridad
 * - Carga las funciones auxiliares
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (!extension_loaded('mbstring') || !extension_loaded('pdo_mysql') || PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Este blog necesita PHP 8.1 o superior con las extensiones mbstring y pdo_mysql.');
}

// Configuración: valores por defecto + config.php (lo crea el asistente install.php)
$GLOBALS['CONFIG'] = require __DIR__ . '/defaults.php';
$hasConfig = is_file(APP_ROOT . '/config.php');
if ($hasConfig) {
    $GLOBALS['CONFIG'] = array_merge($GLOBALS['CONFIG'], (array) require APP_ROOT . '/config.php');
}

date_default_timezone_set($GLOBALS['CONFIG']['timezone'] ?? 'Europe/Madrid');
mb_internal_encoding('UTF-8');

if (!empty($GLOBALS['CONFIG']['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/markdown.php';
require __DIR__ . '/session.php';
require __DIR__ . '/security.php';
require __DIR__ . '/juegos.php';
require __DIR__ . '/layout.php';

// Primera vez: sin config.php, todo lleva al asistente de instalación.
if (!$hasConfig && !defined('INSTALLER')) {
    if (is_file(APP_ROOT . '/install.php')) {
        header('Location: ' . url('install.php'), true, 302);
        exit;
    }
    http_response_code(503);
    exit('Falta config.php y no está install.php. Sube install.php para configurar el blog.');
}

// Redirección a HTTPS desde PHP (complementa a la del .htaccess).
if (cfg('force_https') && !is_https() && PHP_SAPI !== 'cli') {
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? '');
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

send_security_headers();
