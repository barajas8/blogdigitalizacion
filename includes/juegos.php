<?php
/**
 * Juegos: tabla, migración y lectura del formato de texto.
 *
 * Cada juego se escribe desde Panel > Juegos con un formato de texto sencillo
 * (ver juego_tipos()). Aquí se convierte ese texto en datos para assets/js/juegos.js
 * y se avisa de los errores con su número de línea.
 *
 * Reglas comunes a todos los tipos:
 *   // comentario         línea ignorada
 *   > explicación         se muestra al responder (va DEBAJO del elemento al que explica)
 *   @ slug-de-la-entrada  enlaza el elemento anterior a una entrada publicada.
 *                         Si va al principio, antes de cualquier elemento, vale para todo el juego.
 */
declare(strict_types=1);

const JUEGO_MAX_TEXTO = 60000;

/** Crea la tabla de juegos si falta (blogs instalados antes de esta versión). Solo añade. */
function ensure_juegos_schema(): void
{
    $lock = APP_ROOT . '/includes/schema-juegos.lock';
    if (is_file($lock)) {
        return;
    }
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS juegos (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo          VARCHAR(120) NOT NULL,
            tipo            VARCHAR(20)  NOT NULL,
            descripcion     VARCHAR(255) NULL,
            contenido       MEDIUMTEXT   NOT NULL,
            por_partida     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            orden           INT NOT NULL DEFAULT 0,
            estado          ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador',
            creado_en       DATETIME NOT NULL,
            actualizado_en  DATETIME NOT NULL,
            actualizado_por INT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY idx_juegos_estado (estado, orden),
            CONSTRAINT fk_juegos_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @file_put_contents($lock, date('d/m/Y H:i'));
    } catch (Throwable $ex) {
        error_log('Migración de juegos: ' . $ex->getMessage());
        render_error(500, 'Falta actualizar la base de datos',
            'No se pudo crear la tabla de juegos. Ejecuta sql/migracion-juegos.sql en phpMyAdmin (pestaña SQL) y vuelve a entrar.', false);
    }
}

/** ¿Hay algún juego publicado? (para enseñar o no «Juegos» en el menú). Nunca rompe la página. */
function juegos_publicados_count(): int
{
    static $n = null, $busy = false;
    if ($busy) {
        return 0;   // la base de datos está fallando y se está pintando la página de error
    }
    if ($n === null) {
        $busy = true;
        try {
            $n = (int) q_val("SELECT COUNT(*) FROM juegos WHERE estado = 'publicado'");
        } catch (Throwable $ex) {
            $n = 0;   // la tabla aún no existe: se crea al entrar en Panel > Juegos
        }
        $busy = false;
    }
    return $n;
}

/**
 * Tipos de juego: nombre, icono de la tarjeta, mínimo de elementos, si admite «por partida»,
 * explicación y ejemplo del formato (el ejemplo es genérico, no es contenido de ningún blog).
 */
function juego_tipos(): array
{
    return [
        'test' => [
            'nombre' => 'Test', 'icono' => '?', 'min' => 1, 'partida' => true, 'unidad' => 'pregunta',
            'ayuda' => 'Cada pregunta empieza con «?». Debajo, la respuesta correcta con «+» y las incorrectas con «-» (entre 1 y 5). Se barajan solas.',
            'ejemplo' => "? Escribe aquí la pregunta\n+ La respuesta correcta\n- Una respuesta incorrecta\n- Otra respuesta incorrecta\n> Explicación que se ve al responder (opcional)\n@ slug-de-la-entrada\n\n? Segunda pregunta\n+ Correcta\n- Incorrecta",
        ],
        'vf' => [
            'nombre' => 'Verdadero o falso', 'icono' => 'V/F', 'min' => 1, 'partida' => true, 'unidad' => 'afirmación',
            'ayuda' => 'Una afirmación por línea, empezando por «V:» si es verdadera o «F:» si es falsa.',
            'ejemplo' => "V: Una afirmación verdadera según tu entrada\n> Por qué es verdadera (opcional)\n@ slug-de-la-entrada\nF: Una afirmación falsa\n> Lo que dice de verdad la entrada",
        ],
        'clasificar' => [
            'nombre' => 'Clasificar', 'icono' => '⇄', 'min' => 2, 'partida' => true, 'unidad' => 'elemento',
            'ayuda' => 'Escribe cada grupo entre corchetes y debajo sus elementos, uno por línea. Entre 2 y 8 grupos. Sirve también para «relacionar» (cada grupo es una opción).',
            'ejemplo' => "[Grupo A]\nUn elemento del grupo A\n> Explicación (opcional)\nOtro elemento del grupo A\n[Grupo B]\nUn elemento del grupo B\n@ slug-de-la-entrada",
        ],
        'memoria' => [
            'nombre' => 'Memoria', 'icono' => '▦', 'min' => 3, 'max' => 10, 'partida' => false, 'unidad' => 'pareja',
            'ayuda' => 'Una pareja por línea: concepto = descripción. Entre 3 y 10 parejas. Se juega dando la vuelta a las cartas.',
            'ejemplo' => "@ slug-de-la-entrada\nConcepto 1 = Su descripción corta\nConcepto 2 = Su descripción corta\nConcepto 3 = Su descripción corta",
        ],
        'ahorcado' => [
            'nombre' => 'Ahorcado', 'icono' => 'A_', 'min' => 1, 'partida' => true, 'unidad' => 'palabra',
            'ayuda' => 'Una palabra por línea: PALABRA = pista. Solo letras y espacios (las tildes valen). Se juegan 6 por partida si no dices otra cosa.',
            'ejemplo' => "PALABRA = Pista que ayuda a adivinarla\n@ slug-de-la-entrada\nDOS PALABRAS = Otra pista",
        ],
        'sopa' => [
            'nombre' => 'Sopa de letras', 'icono' => '⌗', 'min' => 3, 'max' => 12, 'partida' => false, 'unidad' => 'palabra',
            'ayuda' => 'Una palabra por línea: PALABRA = pista. Sin espacios, de 3 a 12 letras, y entre 3 y 12 palabras.',
            'ejemplo' => "@ slug-de-la-entrada\nPALABRA = Pista corta\nOTRA = Pista corta\nTERCERA = Pista corta",
        ],
        'completar' => [
            'nombre' => 'Completa la frase', 'icono' => '_ _', 'min' => 1, 'partida' => true, 'unidad' => 'frase',
            'ayuda' => 'Una frase por línea. Pon entre corchetes las palabras que hay que adivinar. Las opciones de cada hueco salen de las demás respuestas.',
            'ejemplo' => "Una frase con un [hueco] que hay que [completar].\n@ slug-de-la-entrada\nOtra frase con su [respuesta].",
        ],
        'origen' => [
            'nombre' => '¿De qué entrada es?', 'icono' => '¶', 'min' => 2, 'partida' => true, 'unidad' => 'pista',
            'ayuda' => 'Una frase o idea por línea y, debajo, «@ slug» de la entrada donde aparece. Las opciones son los títulos de las entradas usadas en el juego (mínimo 2 distintas y publicadas).',
            'ejemplo' => "Una idea que aparece en una entrada\n@ slug-de-una-entrada\nUna idea de otra entrada\n@ slug-de-otra-entrada",
        ],
    ];
}

/** Quita tildes a las vocales (para comparar letras en el ahorcado y la sopa). La Ñ se queda. */
function juego_sin_tildes(string $s): string
{
    return strtr(mb_strtoupper($s), ['Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U']);
}

/**
 * Convierte el texto de un juego en datos.
 * Devuelve ['items' => [...], 'errores' => [...], 'avisos' => [...], 'slugs' => [...]].
 * Los slugs son las entradas que se citan con «@» (se comprueban después contra la base de datos).
 */
function juego_parse(string $tipo, string $texto): array
{
    $tipos = juego_tipos();
    $err = [];
    $items = [];
    $slugs = [];
    if (!isset($tipos[$tipo])) {
        return ['items' => [], 'errores' => ['Tipo de juego desconocido.'], 'avisos' => [], 'slugs' => []];
    }
    $lines = preg_split('/\R/u', str_replace("\t", ' ', $texto)) ?: [];
    $defaultSlug = null;
    $cats = [];       // clasificar
    $curCat = null;
    $last = null;     // índice del último elemento (para > y @)
    $q = null;        // test: pregunta en construcción

    $closeTest = static function () use (&$q, &$items, &$err): void {
        if ($q === null) {
            return;
        }
        $ok = array_values(array_filter($q['o'], fn($o) => $o['ok']));
        $ko = array_values(array_filter($q['o'], fn($o) => !$o['ok']));
        if (count($ok) !== 1) {
            $err[] = "Línea {$q['line']}: la pregunta necesita exactamente una respuesta correcta (con «+»).";
        } elseif (count($ko) < 1 || count($ko) > 5) {
            $err[] = "Línea {$q['line']}: la pregunta necesita entre 1 y 5 respuestas incorrectas (con «-»).";
        } else {
            $items[] = ['q' => $q['q'], 'o' => array_merge([$ok[0]['t']], array_map(fn($o) => $o['t'], $ko)),
                'why' => $q['why'], 'e' => $q['e']];
        }
        $q = null;
    };

    foreach ($lines as $i => $raw) {
        $n = $i + 1;
        $line = trim($raw);
        if ($line === '' || str_starts_with($line, '//')) {
            continue;
        }
        if (mb_strlen($line) > 400) {
            $err[] = "Línea {$n}: es demasiado larga (máximo 400 caracteres).";
            continue;
        }
        $first = mb_substr($line, 0, 1);
        $rest = trim(mb_substr($line, 1));

        // ---- Explicación y entrada enlazada (comunes) ----
        if ($first === '>') {
            if ($tipo === 'test' && $q !== null) { $q['why'] = $rest; continue; }
            if ($last === null) { $err[] = "Línea {$n}: la explicación «>» tiene que ir debajo de un elemento."; continue; }
            $items[$last]['why'] = $rest;
            continue;
        }
        if ($first === '@') {
            $slug = slugify($rest, 220);
            if ($slug === '') { $err[] = "Línea {$n}: falta el slug de la entrada después de «@»."; continue; }
            $slugs[$slug] = true;
            if ($tipo === 'test' && $q !== null) { $q['e'] = $slug; continue; }
            if ($last === null && $q === null && !$items) { $defaultSlug = $slug; continue; }
            if ($last === null) { $err[] = "Línea {$n}: «@» tiene que ir debajo de un elemento o al principio del todo."; continue; }
            $items[$last]['e'] = $slug;
            continue;
        }

        switch ($tipo) {
            case 'test':
                if ($first === '?') {
                    $closeTest();
                    if ($rest === '') { $err[] = "Línea {$n}: falta el texto de la pregunta."; break; }
                    $q = ['q' => $rest, 'o' => [], 'why' => '', 'e' => null, 'line' => $n];
                } elseif ($first === '+' || $first === '-') {
                    if ($q === null) { $err[] = "Línea {$n}: las respuestas tienen que ir debajo de una pregunta (que empieza por «?»)."; break; }
                    if ($rest === '') { $err[] = "Línea {$n}: respuesta vacía."; break; }
                    $q['o'][] = ['t' => $rest, 'ok' => $first === '+'];
                } else {
                    $err[] = "Línea {$n}: en un test cada línea empieza por «?», «+», «-», «>» o «@».";
                }
                break;

            case 'vf':
                if (preg_match('/^([VvFf])\s*:\s*(.+)$/u', $line, $m)) {
                    $items[] = ['q' => trim($m[2]), 'v' => strtoupper($m[1]) === 'V', 'why' => '', 'e' => null];
                    $last = count($items) - 1;
                } else {
                    $err[] = "Línea {$n}: empieza la afirmación por «V:» (verdadera) o «F:» (falsa).";
                }
                break;

            case 'clasificar':
                if (preg_match('/^\[(.+)\]$/u', $line, $m)) {
                    $name = trim($m[1]);
                    $idx = array_search($name, $cats, true);
                    if ($idx === false) { $cats[] = $name; $idx = count($cats) - 1; }
                    $curCat = $idx;
                    $last = null;
                } elseif ($curCat === null) {
                    $err[] = "Línea {$n}: escribe primero un grupo entre corchetes, por ejemplo [Grupo A].";
                } else {
                    $items[] = ['t' => $line, 'c' => $curCat, 'why' => '', 'e' => null];
                    $last = count($items) - 1;
                }
                break;

            case 'memoria':
            case 'ahorcado':
            case 'sopa':
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                    $err[] = $tipo === 'memoria' ? "Línea {$n}: escribe la pareja así: concepto = descripción." : "Línea {$n}: escribe la palabra así: PALABRA = pista.";
                    break;
                }
                [$a, $b] = [trim($parts[0]), trim($parts[1])];
                if ($tipo === 'memoria') {
                    $items[] = ['a' => $a, 'b' => $b, 'e' => null, 'why' => ''];
                } else {
                    $w = preg_replace('/\s+/u', ' ', mb_strtoupper($a)) ?? '';
                    $plain = juego_sin_tildes($w);
                    if ($tipo === 'sopa') {
                        if (!preg_match('/^[A-ZÑ]{3,12}$/u', $plain)) { $err[] = "Línea {$n}: «{$a}» tiene que ser una sola palabra de 3 a 12 letras, sin espacios ni números."; break; }
                        $items[] = ['w' => $plain, 'p' => $b, 'e' => null, 'why' => ''];
                    } else {
                        if (!preg_match('/^[A-ZÑ]+( [A-ZÑ]+)*$/u', $plain) || mb_strlen($w) > 30) { $err[] = "Línea {$n}: «{$a}» solo puede tener letras y espacios (máximo 30)."; break; }
                        $items[] = ['w' => $w, 'p' => $b, 'e' => null, 'why' => ''];
                    }
                }
                $last = count($items) - 1;
                break;

            case 'completar':
                if (!preg_match_all('/\[([^\[\]]+)\]/u', $line, $mm)) {
                    $err[] = "Línea {$n}: la frase necesita al menos un hueco entre corchetes, por ejemplo [palabra].";
                    break;
                }
                $parts = preg_split('/\[[^\[\]]+\]/u', $line) ?: [];
                $s = [];
                foreach ($parts as $k => $p) {
                    if ($p !== '') { $s[] = $p; }
                    if ($k < count($mm[1])) { $s[] = $k; }
                }
                $items[] = ['s' => $s, 'r' => array_map('trim', $mm[1]), 'e' => null, 'why' => ''];
                $last = count($items) - 1;
                break;

            case 'origen':
                $items[] = ['t' => $line, 'e' => null, 'why' => '', 'line' => $n];
                $last = count($items) - 1;
                break;
        }
    }
    if ($tipo === 'test') {
        $closeTest();
    }

    if ($defaultSlug !== null) {
        foreach ($items as &$it) { $it['e'] ??= $defaultSlug; }
        unset($it);
    }

    // ---- Comprobaciones por tipo ----
    $def = $tipos[$tipo];
    $avisos = [];
    if ($tipo === 'clasificar') {
        if (count($cats) < 2) { $err[] = 'Hacen falta al menos 2 grupos entre corchetes.'; }
        if (count($cats) > 8) { $err[] = 'Como máximo 8 grupos.'; }
        $usados = array_unique(array_column($items, 'c'));
        if (count($cats) >= 2 && count($usados) < count($cats)) { $avisos[] = 'Hay algún grupo sin elementos: saldrá como opción, pero ningún elemento será suyo.'; }
    }
    if ($tipo === 'origen') {
        foreach ($items as $it) {
            if (!$it['e']) { $err[] = "Línea {$it['line']}: indica debajo «@ slug» de la entrada de donde sale."; }
        }
        if (count(array_unique(array_filter(array_column($items, 'e')))) < 2) { $err[] = 'Usa al menos 2 entradas distintas para que haya entre qué elegir.'; }
    }
    if ($tipo === 'completar') {
        $todas = [];
        foreach ($items as $it) { $todas = array_merge($todas, $it['r']); }
        if (count(array_unique($todas)) < 2) { $err[] = 'Hacen falta al menos 2 respuestas distintas entre todas las frases.'; }
    }
    if (count($items) < $def['min']) {
        $err[] = "Hace falta al menos {$def['min']} {$def['unidad']}" . ($def['min'] === 1 ? '' : 's') . '.';
    }
    if (isset($def['max']) && count($items) > $def['max']) {
        $err[] = "Como máximo {$def['max']} {$def['unidad']}s en este tipo de juego.";
    }
    foreach ($items as &$it) { unset($it['line']); }
    unset($it);
    // errores en orden de línea (los generales, sin línea, al final)
    usort($err, static function (string $a, string $b): int {
        $la = preg_match('/^Línea (\d+)/u', $a, $m) ? (int) $m[1] : PHP_INT_MAX;
        $lb = preg_match('/^Línea (\d+)/u', $b, $m) ? (int) $m[1] : PHP_INT_MAX;
        return $la <=> $lb;
    });

    $data = ['items' => $items];
    if ($tipo === 'clasificar') {
        $data['cats'] = $cats;
    }
    return ['items' => $items, 'data' => $data, 'errores' => $err, 'avisos' => $avisos, 'slugs' => array_keys($slugs)];
}

/** Entradas publicadas de una lista de slugs: [slug => ['t' => título, 'u' => url]]. */
function juego_entradas(array $slugs): array
{
    $slugs = array_values(array_unique(array_filter($slugs)));
    if (!$slugs) {
        return [];
    }
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $out = [];
    foreach (q_all("SELECT slug, titulo FROM entradas WHERE estado = 'publicada' AND slug IN ($in)", $slugs) as $r) {
        $out[$r['slug']] = ['t' => $r['titulo'], 'u' => url_post($r['slug'])];
    }
    return $out;
}

/**
 * Juegos publicados listos para la web: quita enlaces a entradas que no existen
 * o no están publicadas, y descarta los juegos que se queden sin contenido válido.
 */
function juegos_para_web(): array
{
    $tipos = juego_tipos();
    $rows = q_all("SELECT id, titulo, tipo, descripcion, contenido, por_partida FROM juegos WHERE estado = 'publicado' ORDER BY orden, id");
    $parsed = [];
    $slugs = [];
    foreach ($rows as $r) {
        if (!isset($tipos[$r['tipo']])) {
            continue;
        }
        $p = juego_parse($r['tipo'], $r['contenido']);
        $parsed[] = [$r, $p];
        $slugs = array_merge($slugs, $p['slugs']);
    }
    $ent = juego_entradas($slugs);
    $games = [];
    foreach ($parsed as [$r, $p]) {
        $data = $p['data'];
        foreach ($data['items'] as &$it) {
            if ($it['e'] && !isset($ent[$it['e']])) { $it['e'] = null; }
        }
        unset($it);
        if ($r['tipo'] === 'origen') {
            $data['items'] = array_values(array_filter($data['items'], fn($it) => $it['e'] !== null));
            if (count(array_unique(array_column($data['items'], 'e'))) < 2) { continue; }
        }
        if (count($data['items']) < $tipos[$r['tipo']]['min'] || ($r['tipo'] !== 'origen' && $p['errores'])) {
            continue;
        }
        $t = $tipos[$r['tipo']];
        $games[] = [
            'id' => 'j' . $r['id'],
            'tipo' => $r['tipo'],
            'tipoNombre' => $t['nombre'],
            'icono' => $t['icono'],
            'titulo' => $r['titulo'],
            'desc' => (string) $r['descripcion'],
            'n' => $t['partida'] ? (int) $r['por_partida'] : 0,
            'data' => $data,
        ];
    }
    return ['juegos' => $games, 'entradas' => $ent];
}
