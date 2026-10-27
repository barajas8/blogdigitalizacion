<?php
/** Cierre de sesión (solo por POST con token CSRF, para que un enlace externo no pueda cerrarla). */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

start_secure_session();
if (is_post()) {
    csrf_check();
    logout_user();
    start_secure_session();
    flash('ok', 'Has cerrado la sesión.');
}
redirect('admin/login.php');
