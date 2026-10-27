<?php
/**
 * Valores por defecto de la configuración.
 * config.php (lo crea el asistente install.php) solo sobrescribe lo que cambie.
 * No pongas aquí datos reales: este archivo se publica en el repositorio.
 */
return [
    // Base de datos
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',

    // Sitio
    'site_name'    => 'Digitalización aplicada al sector productivo',
    'site_tagline' => 'Blog del módulo',
    'footer_text'  => 'Blog del módulo de Digitalización aplicada al sector productivo.',
    'timezone'     => 'Europe/Madrid',
    'base_url'     => '',      // vacío = se detecta solo
    'pretty_urls'  => true,    // /entrada/mi-articulo (requiere mod_rewrite)
    'force_https'  => false,   // true solo cuando el SSL ya funcione

    // Contenido
    'posts_per_page'       => 8,
    'admin_posts_per_page' => 20,
    'max_pinned'           => 3,

    // Imágenes
    'upload_max_bytes' => 2 * 1024 * 1024,
    'upload_max_px'    => 4000,
    'image_max_width'  => 1600,

    // Seguridad
    'session_timeout'      => 30 * 60,
    'session_absolute_max' => 8 * 3600,
    'login_max_per_user'   => 5,
    'login_max_per_ip'     => 10,
    'login_window'         => 15 * 60,

    'debug' => false,
];
