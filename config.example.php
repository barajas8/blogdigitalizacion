<?php
/**
 * PLANTILLA DE CONFIGURACIÓN
 * ------------------------------------------------------------
 * Normalmente NO hace falta: el asistente install.php crea config.php solo.
 * Úsala solo para instalar a mano: cópiala como config.php y rellénala.
 * config.php contiene contraseñas: nunca lo subas a GitHub (.gitignore lo evita).
 * Los ajustes que no pongas aquí toman su valor de includes/defaults.php.
 */
return [
    // Base de datos
    'db_host' => 'localhost',            // en InfinityFree: sqlXXX.infinityfree.com
    'db_port' => 3306,
    'db_name' => 'nombre_de_la_base',
    'db_user' => 'usuario_mysql',
    'db_pass' => 'contraseña_mysql',

    // Tu blog
    'site_name'    => 'Digitalización aplicada al sector productivo',
    'site_tagline' => 'Blog del módulo',
    'footer_text'  => 'Blog del módulo de Digitalización aplicada al sector productivo.',
    'timezone'     => 'Europe/Madrid',

    // Opciones
    'pretty_urls' => true,   // false si las entradas dan 404
    'force_https' => false,  // true solo cuando el SSL ya funcione
    'debug'       => false,  // nunca true en producción
];
