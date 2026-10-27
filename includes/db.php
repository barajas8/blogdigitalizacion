<?php
/**
 * Conexión a MySQL con PDO. Se abre solo cuando hace falta (una vez por petición).
 * Todas las consultas del proyecto usan sentencias preparadas.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        cfg('db_host'),
        (int) cfg('db_port', 3306),
        cfg('db_name')
    );
    try {
        $pdo = new PDO($dsn, (string) cfg('db_user'), (string) cfg('db_pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  // preparadas reales en el servidor
            PDO::ATTR_TIMEOUT            => 10,
        ]);
    } catch (PDOException $ex) {
        error_log('Error de conexión MySQL: ' . $ex->getMessage());
        render_error(503, 'No se puede conectar con la base de datos',
            cfg('debug') ? $ex->getMessage() : 'Inténtalo de nuevo en unos minutos.');
    }
    return $pdo;
}

/** Ejecuta una consulta preparada. Los enteros se enlazan como enteros (necesario para LIMIT). */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $name = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k);
        $type = is_int($v) ? PDO::PARAM_INT : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue($name, $v, $type);
    }
    $st->execute();
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Escapa los comodines de LIKE (% y _) en un término de búsqueda. */
function like_escape(string $s): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s) . '%';
}

/** Busca un slug libre en una tabla: "mi-entrada", "mi-entrada-2"... */
function unique_slug(string $table, string $base, ?int $exceptId = null): string
{
    $allowed = ['entradas', 'categorias', 'etiquetas'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabla no permitida');
    }
    $base = $base !== '' ? $base : 'sin-titulo';
    $slug = $base;
    for ($i = 2; $i < 500; $i++) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?" . ($exceptId ? ' AND id <> ?' : '') . ' LIMIT 1';
        $params = $exceptId ? [$slug, $exceptId] : [$slug];
        if (q_val($sql, $params) === null) {
            return $slug;
        }
        $slug = substr($base, 0, 190) . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

/** Número de entradas fijadas (sin contar la indicada). */
function pinned_count(?int $exceptId = null): int
{
    return (int) q_val('SELECT COUNT(*) FROM entradas WHERE fijada = 1' . ($exceptId ? ' AND id <> ?' : ''), $exceptId ? [$exceptId] : []);
}

/** Contenido de una página editable (portada, sobre-nosotros). */
function get_pagina(string $slug): ?array
{
    return q_one('SELECT * FROM paginas WHERE slug = ?', [$slug]);
}
