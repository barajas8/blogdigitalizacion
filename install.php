<?php
/**
 * ============================================================
 *  ASISTENTE DE INSTALACIÓN (se abre solo la primera vez)
 * ============================================================
 *  1. Comprueba los requisitos del servidor.
 *  2. Pide los datos de MySQL y prueba la conexión.
 *  3. Pide el nombre del blog, el subtítulo y el texto del pie.
 *  4. Crea las cuentas de administrador (o reconecta un blog que ya existía).
 *  5. Crea las tablas, escribe config.php, se bloquea y se intenta borrar.
 *
 *  Nada de esto está escrito en el código: todo lo decide quien instala.
 */
declare(strict_types=1);

define('INSTALLER', true);
require __DIR__ . '/includes/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$lock = APP_ROOT . '/includes/install.lock';
$configFile = APP_ROOT . '/config.php';

function install_page(string $title, string $body): never
{
    ?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title><?= e($title) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<script src="<?= e(asset('js/install.js')) ?>" defer></script>
</head><body class="admin admin-guest"><main id="panel-main" class="admin-main install wizard">
<div class="wiz-brand"><?= logo_svg() ?><span>Asistente de instalación</span></div>
<h1><?= e($title) ?></h1><?= $body ?></main></body></html>
<?php
    exit;
}

// ---------- Ya instalado: bloqueado ----------
if (is_file($lock)) {
    http_response_code(403);
    install_page('El blog ya está instalado',
        '<p class="flash flash-error" role="alert">El asistente está bloqueado desde el ' . e(trim((string) file_get_contents($lock))) . '.</p>'
        . '<p>Borra <code>install.php</code> del servidor. Si necesitas volver a configurar, borra <code>config.php</code> e <code>includes/install.lock</code> y vuelve a subir <code>install.php</code>: tus entradas siguen en la base de datos.</p>'
        . '<p><a class="btn btn-primary" href="' . e(url('admin/')) . '">Ir al panel</a></p>');
}

start_secure_session(true);

// ---------- 1. Requisitos ----------
$writable = static fn(string $p): bool => is_dir($p) ? is_writable($p) : is_writable(dirname($p));
$rewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : null;
[$p2sLevel, $p2sText] = php2shell_status();
$reqs = [
    ['PHP 8.1 o superior', PHP_VERSION_ID >= 80100, true, 'Tienes PHP ' . PHP_VERSION . '.'],
    ['Extensión PDO MySQL', extension_loaded('pdo_mysql'), true, 'Para conectar con la base de datos.'],
    ['Extensión mbstring', extension_loaded('mbstring'), true, 'Para textos con tildes y eñes.'],
    ['Extensión GD', function_exists('imagecreatetruecolor'), false, 'Opcional: reduce las fotos y les quita los metadatos.'],
    ['Carpeta uploads/ con permiso de escritura', $writable(APP_ROOT . '/uploads'), true, 'Ahí se guardan las imágenes.'],
    ['Carpeta includes/ con permiso de escritura', $writable(APP_ROOT . '/includes'), false, 'Para el bloqueo del instalador y las sesiones privadas.'],
    ['Poder crear config.php', $writable($configFile), false, 'Si no se puede, al final te daré el archivo para subirlo a mano.'],
    ['mod_rewrite (URLs amigables)', $rewrite !== false, false, $rewrite === null ? 'No se puede comprobar desde PHP; casi todos los hostings Apache lo tienen.' : ($rewrite ? 'Activo.' : 'No está: se usarán URLs con ?slug=.')],
    ['PHP con el parche de PHP2Shell', $p2sLevel === 'ok', false, $p2sText],
];
$reqFail = array_filter($reqs, static fn($r) => $r[2] && !$r[1]);

// ---------- Valores del formulario ----------
$v = [
    'db_host' => (string) cfg('db_host'), 'db_port' => (string) cfg('db_port'), 'db_name' => (string) cfg('db_name'),
    'db_user' => (string) cfg('db_user'), 'db_pass' => '',
    'site_name' => (string) cfg('site_name'), 'site_tagline' => (string) cfg('site_tagline'),
    'footer_text' => (string) cfg('footer_text'), 'timezone' => (string) cfg('timezone'),
    'force_https' => is_https(), 'pretty_urls' => $rewrite !== false, 'ejemplos' => true,
    'existente' => false, 'dos' => true,
    'u1' => '', 'n1' => '', 'u2' => '', 'n2' => '',
];
$errors = [];

if (is_post()) {
    csrf_check();
    foreach (['db_host' => 255, 'db_port' => 5, 'db_name' => 64, 'db_user' => 80, 'site_name' => 120, 'site_tagline' => 120, 'footer_text' => 255, 'timezone' => 64, 'u1' => 40, 'n1' => 80, 'u2' => 40, 'n2' => 80] as $k => $max) {
        $v[$k] = input_str($_POST, $k, $max);
    }
    $v['db_pass'] = is_string($_POST['db_pass'] ?? null) ? (string) $_POST['db_pass'] : '';
    foreach (['force_https', 'pretty_urls', 'ejemplos', 'existente', 'dos'] as $k) {
        $v[$k] = !empty($_POST[$k]);
    }
    $v['u1'] = mb_strtolower($v['u1']);
    $v['u2'] = mb_strtolower($v['u2']);
    $pass = [1 => (string) ($_POST['p1'] ?? ''), 2 => (string) ($_POST['p2'] ?? '')];
    $pass2 = [1 => (string) ($_POST['p1b'] ?? ''), 2 => (string) ($_POST['p2b'] ?? '')];

    // Validación
    if ($reqFail) {
        $errors[] = 'El servidor no cumple los requisitos obligatorios (en rojo arriba).';
    }
    if (!preg_match('/^[A-Za-z0-9.\-_]{1,255}$/', $v['db_host'])) {
        $errors[] = 'Base de datos: el servidor (host) no es válido.';
    }
    $port = (int) $v['db_port'];
    if ($port < 1 || $port > 65535) {
        $errors[] = 'Base de datos: el puerto debe estar entre 1 y 65535 (normalmente 3306).';
    }
    if (!preg_match('/^[A-Za-z0-9_$\-]{1,64}$/', $v['db_name'])) {
        $errors[] = 'Base de datos: el nombre de la base de datos no es válido.';
    }
    if ($v['db_user'] === '') {
        $errors[] = 'Base de datos: falta el usuario.';
    }
    if ($v['site_name'] === '') {
        $errors[] = 'Tu blog: falta el nombre.';
    }
    if (!in_array($v['timezone'], DateTimeZone::listIdentifiers(), true)) {
        $errors[] = 'Tu blog: la zona horaria no es válida.';
    }
    if ($v['existente']) {
        if ($v['u1'] === '' || $pass[1] === '') {
            $errors[] = 'Para reconectar un blog existente escribe el usuario y la contraseña de una de sus cuentas.';
        }
    } else {
        $cuentas = $v['dos'] ? [1, 2] : [1];
        foreach ($cuentas as $k) {
            if (!preg_match('/^[a-z0-9._-]{3,40}$/', $v["u$k"])) {
                $errors[] = "Cuenta $k: el usuario debe tener de 3 a 40 caracteres (minúsculas, números, punto, guion o guion bajo).";
            }
            if ($v["n$k"] === '') {
                $errors[] = "Cuenta $k: falta el nombre que se mostrará como autor.";
            }
            if ($msg = password_problem($pass[$k])) {
                $errors[] = "Cuenta $k: $msg";
            }
            if ($pass[$k] !== $pass2[$k]) {
                $errors[] = "Cuenta $k: las dos contraseñas no coinciden.";
            }
        }
        if ($v['dos'] && $v['u1'] !== '' && $v['u1'] === $v['u2']) {
            $errors[] = 'Las dos cuentas deben tener usuarios distintos.';
        }
    }

    // Prueba de conexión
    if (!$errors) {
        try {
            $test = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $v['db_host'], $port, $v['db_name']),
                $v['db_user'], $v['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
            $test = null;
        } catch (PDOException $ex) {
            $msg = $ex->getMessage();
            $hint = match (true) {
                str_contains($msg, '1045') => 'usuario o contraseña incorrectos.',
                str_contains($msg, '1049') => 'esa base de datos no existe. Créala primero en el panel del hosting.',
                str_contains($msg, '2002'), str_contains($msg, '2005') => 'no se encuentra el servidor. Revisa el host (en hostings gratuitos no suele ser localhost).',
                default => 'revisa los datos.',
            };
            $errors[] = 'No se pudo conectar con MySQL: ' . $hint;
        }
    }

    if (!$errors) {
        // A partir de aquí, la web usa estos datos
        $GLOBALS['CONFIG'] = array_merge($GLOBALS['CONFIG'], [
            'db_host' => $v['db_host'], 'db_port' => $port, 'db_name' => $v['db_name'],
            'db_user' => $v['db_user'], 'db_pass' => $v['db_pass'], 'timezone' => $v['timezone'],
        ]);
        date_default_timezone_set($v['timezone']);
        try {
            // Tablas (CREATE TABLE IF NOT EXISTS: si ya existen, no se tocan)
            $sql = (string) file_get_contents(APP_ROOT . '/sql/schema.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
            foreach (preg_split('/;\s*(?:\n|$)/', $sql) as $stmt) {
                if (trim($stmt) !== '') {
                    db()->exec($stmt);
                }
            }
            @unlink(APP_ROOT . '/includes/schema-v3.lock');
            ensure_schema(); // añade columnas nuevas si la base de datos venía de una versión anterior

            $hayCuentas = (int) q_val('SELECT COUNT(*) FROM usuarios') > 0;
            if ($v['existente']) {
                $row = q_one('SELECT password_hash FROM usuarios WHERE usuario = ?', [$v['u1']]);
                if (!$row || !password_verify($pass[1], $row['password_hash'])) {
                    usleep(700000);
                    $errors[] = 'Ese usuario y contraseña no coinciden con ninguna cuenta de esta base de datos.';
                }
            } elseif ($hayCuentas) {
                $errors[] = 'Esta base de datos ya tiene un blog con cuentas. Para conservarlo, marca «Ya tengo un blog en esta base de datos» y escribe tu usuario y contraseña actuales.';
            } else {
                db()->beginTransaction();
                foreach ($v['dos'] ? [1, 2] : [1] as $k) {
                    q('INSERT INTO usuarios (usuario, nombre, password_hash, creado_en) VALUES (?, ?, ?, ?)',
                        [$v["u$k"], $v["n$k"], hash_password($pass[$k]), now()]);
                }
                if ($v['ejemplos']) {
                    foreach (['Industria 4.0', 'IoT industrial', 'Ciberseguridad industrial', 'Cloud y big data', 'ERP y MES'] as $c) {
                        q('INSERT IGNORE INTO categorias (nombre, slug) VALUES (?, ?)', [$c, slugify($c, 100)]);
                    }
                }
                db()->commit();
            }
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('Instalador: ' . $ex->getMessage());
            $errors[] = 'Error al preparar la base de datos. Comprueba que el usuario de MySQL tiene permisos para crear tablas.';
        }
    }

    if (!$errors) {
        // config.php
        $out = [
            'db_host' => $v['db_host'], 'db_port' => $port, 'db_name' => $v['db_name'],
            'db_user' => $v['db_user'], 'db_pass' => $v['db_pass'],
            'site_name' => $v['site_name'], 'site_tagline' => $v['site_tagline'], 'footer_text' => $v['footer_text'],
            'timezone' => $v['timezone'], 'base_url' => '', 'pretty_urls' => $v['pretty_urls'],
            'force_https' => $v['force_https'] && is_https(), 'debug' => false,
        ];
        $php = "<?php\n/**\n * Configuración generada por el asistente el " . date('d/m/Y H:i') . ".\n"
            . " * Contiene la contraseña de la base de datos: NO lo subas a GitHub (está en .gitignore).\n"
            . " * El resto de ajustes y sus valores por defecto están en includes/defaults.php.\n */\n"
            . 'return ' . var_export($out, true) . ";\n";
        $written = @file_put_contents($configFile, $php, LOCK_EX) !== false;
        if ($written) {
            @chmod($configFile, 0644);
        }
        $locked = @file_put_contents($lock, date('d/m/Y H:i')) !== false;
        $deleted = $written && @unlink(__FILE__);
        $_SESSION = [];
        session_destroy();

        $body = '<p class="flash flash-ok" role="status">' . ($v['existente'] ? 'Blog reconectado: tus entradas y cuentas siguen como estaban.' : 'Blog instalado y cuentas creadas.') . '</p>';
        if (!$written) {
            $body .= '<div class="panel-card"><h2>Falta un paso: sube config.php</h2>'
                . '<p>El servidor no deja crear archivos. Copia este texto en un archivo llamado <code>config.php</code> y súbelo a la misma carpeta que <code>index.php</code>. Después borra <code>install.php</code>.</p>'
                . '<textarea class="config-out" rows="16" readonly>' . e($php) . '</textarea></div>';
        } elseif ($deleted) {
            $body .= '<p>El instalador se ha borrado solo. Comprueba igualmente que <code>install.php</code> ya no está en el servidor.</p>';
        } else {
            $body .= '<p class="flash flash-error" role="alert"><strong>Borra ahora <code>install.php</code></strong> del servidor.'
                . ($locked ? ' Mientras tanto ha quedado bloqueado.' : '') . '</p>';
        }
        $body .= '<h2>Siguientes pasos</h2><ol class="steps">'
            . '<li>Entra al panel en <code>' . e(url('admin/')) . '</code> y guárdalo en favoritos.</li>'
            . '<li>En <em>Mi cuenta</em>, activa la verificación en dos pasos.</li>'
            . '<li>En <em>Páginas</em>, escribe el texto de portada y «Sobre nosotros».</li>'
            . '<li>Si aún no tienes HTTPS, actívalo en tu hosting y revisa <em>Panel &gt; Seguridad</em>.</li></ol>'
            . '<p><a class="btn btn-primary" href="' . e(url('admin/login.php')) . '">Entrar al panel</a></p>';
        install_page('¡Listo!', $body);
    }
}

// ---------- Formulario ----------
$tzs = DateTimeZone::listIdentifiers();
$icon = static fn(bool $ok, bool $req) => $ok ? 'ok' : ($req ? 'bad' : 'warn');
ob_start();
?>
<p class="lead">Rellena estos cuatro bloques y pulsa <strong>Instalar</strong>. Tarda unos segundos. Se puede usar una sola vez.</p>

<nav class="wiz-steps" aria-label="Pasos de la instalación">
  <a href="#paso-1"><span>1</span>Requisitos</a>
  <a href="#paso-2"><span>2</span>Base de datos</a>
  <a href="#paso-3"><span>3</span>Tu blog</a>
  <a href="#paso-4"><span>4</span>Cuentas</a>
</nav>

<?php if ($errors): ?>
  <div class="flash flash-error" role="alert"><p>Revisa estos puntos:</p><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form-stack" novalidate>
  <?= csrf_field() ?>

  <section class="panel-card wiz-card" id="paso-1">
    <h2><span class="wiz-n">1</span>Requisitos del servidor</h2>
    <ul class="check-list">
      <?php foreach ($reqs as [$label, $ok, $req, $detail]): $l = $icon($ok, $req); ?>
        <li class="lvl-<?= $l ?>"><span class="dot" aria-hidden="true"><?= $l === 'ok' ? '✓' : ($l === 'bad' ? '×' : '!') ?></span>
          <div><strong><?= e($label) ?><?= $req ? '' : ' (opcional)' ?></strong><p><?= e($detail) ?></p></div></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="panel-card wiz-card" id="paso-2">
    <h2><span class="wiz-n">2</span>Base de datos MySQL</h2>
    <p class="hint">Créala antes en el panel de tu hosting (en InfinityFree: <em>MySQL Databases</em>). Allí aparecen estos datos.</p>
    <div class="wiz-grid">
      <div><label for="db_host">Servidor (host)</label><input id="db_host" name="db_host" required value="<?= e($v['db_host']) ?>" placeholder="sql123.infinityfree.com" autocomplete="off" spellcheck="false"></div>
      <div><label for="db_port">Puerto</label><input id="db_port" name="db_port" inputmode="numeric" value="<?= e($v['db_port'] ?: '3306') ?>"></div>
      <div><label for="db_name">Nombre de la base de datos</label><input id="db_name" name="db_name" required value="<?= e($v['db_name']) ?>" placeholder="if0_12345678_blog" autocomplete="off" spellcheck="false"></div>
      <div><label for="db_user">Usuario</label><input id="db_user" name="db_user" required value="<?= e($v['db_user']) ?>" placeholder="if0_12345678" autocomplete="off" spellcheck="false"></div>
      <div class="span-2"><label for="db_pass">Contraseña de MySQL</label><input id="db_pass" name="db_pass" type="password" autocomplete="new-password" aria-describedby="dbp-h"><p class="hint" id="dbp-h">En InfinityFree es la contraseña de la cuenta de hosting (<em>Account Details</em>).</p></div>
    </div>
  </section>

  <section class="panel-card wiz-card" id="paso-3">
    <h2><span class="wiz-n">3</span>Tu blog</h2>
    <div class="wiz-grid">
      <div class="span-2"><label for="site_name">Nombre del blog</label><input id="site_name" name="site_name" required maxlength="120" value="<?= e($v['site_name']) ?>"></div>
      <div><label for="site_tagline">Subtítulo (debajo del nombre)</label><input id="site_tagline" name="site_tagline" maxlength="120" value="<?= e($v['site_tagline']) ?>" placeholder="Blog de Ana y Luis"></div>
      <div><label for="timezone">Zona horaria</label>
        <select id="timezone" name="timezone"><?php foreach ($tzs as $tz): ?><option<?= $tz === $v['timezone'] ? ' selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div>
      <div class="span-2"><label for="footer_text">Texto del pie de página</label><input id="footer_text" name="footer_text" maxlength="255" value="<?= e($v['footer_text']) ?>"></div>
    </div>
    <label class="check"><input type="checkbox" name="pretty_urls" value="1"<?= $v['pretty_urls'] ? ' checked' : '' ?>> URLs amigables (<code>/entrada/mi-articulo</code>)</label>
    <label class="check"><input type="checkbox" name="force_https" value="1"<?= $v['force_https'] ? ' checked' : '' ?><?= is_https() ? '' : ' disabled' ?>> Forzar HTTPS <?= is_https() ? '' : '<span class="muted">(entra por https:// para poder activarlo)</span>' ?></label>
    <label class="check"><input type="checkbox" name="ejemplos" value="1"<?= $v['ejemplos'] ? ' checked' : '' ?>> Crear categorías de ejemplo (Industria 4.0, IoT industrial…)</label>
  </section>

  <section class="panel-card wiz-card" id="paso-4">
    <h2><span class="wiz-n">4</span>Cuentas de administrador</h2>
    <label class="check"><input type="checkbox" name="existente" value="1" data-toggle="existente"<?= $v['existente'] ? ' checked' : '' ?>> Ya tengo un blog en esta base de datos (solo quiero reconectarlo, sin crear cuentas)</label>
    <p class="hint">Marca esto si estás reinstalando: escribe abajo el usuario y la contraseña de una cuenta que ya exista. No se borra nada.</p>

    <fieldset class="wiz-account">
      <legend>Cuenta 1</legend>
      <div class="wiz-grid">
        <div><label for="u1">Usuario para entrar</label><input id="u1" name="u1" maxlength="40" autocomplete="off" autocapitalize="none" spellcheck="false" value="<?= e($v['u1']) ?>"></div>
        <div data-only-new><label for="n1">Nombre que se verá como autor</label><input id="n1" name="n1" maxlength="80" value="<?= e($v['n1']) ?>"></div>
        <div><label for="p1">Contraseña</label><input id="p1" name="p1" type="password" maxlength="72" autocomplete="new-password"></div>
        <div data-only-new><label for="p1b">Repite la contraseña</label><input id="p1b" name="p1b" type="password" maxlength="72" autocomplete="new-password"></div>
      </div>
    </fieldset>

    <div data-only-new>
      <label class="check"><input type="checkbox" name="dos" value="1"<?= $v['dos'] ? ' checked' : '' ?>> Crear una segunda cuenta (por ejemplo, para tu compañero)</label>
      <fieldset class="wiz-account">
        <legend>Cuenta 2</legend>
        <div class="wiz-grid">
          <div><label for="u2">Usuario para entrar</label><input id="u2" name="u2" maxlength="40" autocomplete="off" autocapitalize="none" spellcheck="false" value="<?= e($v['u2']) ?>"></div>
          <div><label for="n2">Nombre que se verá como autor</label><input id="n2" name="n2" maxlength="80" value="<?= e($v['n2']) ?>"></div>
          <div><label for="p2">Contraseña</label><input id="p2" name="p2" type="password" maxlength="72" autocomplete="new-password"></div>
          <div><label for="p2b">Repite la contraseña</label><input id="p2b" name="p2b" type="password" maxlength="72" autocomplete="new-password"></div>
        </div>
      </fieldset>
      <p class="hint">Contraseñas: mínimo 10 caracteres, con al menos una letra y un número.</p>
    </div>
  </section>

  <button type="submit" class="btn btn-glow wiz-submit"<?= $reqFail ? ' disabled' : '' ?>>Instalar el blog</button>
</form>
<?php
install_page('Instalar el blog', (string) ob_get_clean());
