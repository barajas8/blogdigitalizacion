<?php
/**
 * Conversor Markdown -> HTML seguro.
 *
 * Por qué uno propio y no una librería: no hay Composer en el hosting y este
 * parser es "seguro por diseño": TODO el texto se escapa con htmlspecialchars
 * ANTES de aplicar el formato, así que el HTML que escriba un usuario en el
 * contenido nunca se ejecuta (se ve como texto). Los únicos atributos que se
 * generan son href/src, y pasan por una lista blanca de protocolos
 * (javascript:, data:, vbscript:... quedan bloqueados).
 *
 * Soporta: títulos (#), párrafos, saltos de línea (dos espacios al final),
 * **negrita**, *cursiva*, ~~tachado~~, `código`, bloques ``` de código,
 * > citas, listas con - o 1., tablas con |, ---, [enlaces](url) e
 * ![imágenes](uploads/archivo.jpg).
 *
 * El mismo comportamiento está replicado en assets/js/admin.js para la
 * vista previa instantánea del panel.
 */
declare(strict_types=1);

function md_to_html(string $src): string
{
    $src = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $src);
    // Fuera caracteres de control (incluido el que usamos como marcador interno).
    $src = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $src) ?? '';
    return md_blocks(explode("\n", $src));
}

/** Procesa una lista de líneas y devuelve HTML de bloques. */
function md_blocks(array $lines): string
{
    $out = [];
    $para = [];
    $n = count($lines);

    $flush = static function () use (&$para, &$out): void {
        if ($para) {
            $out[] = '<p>' . md_inline(implode("\n", $para)) . '</p>';
            $para = [];
        }
    };

    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        $trim = trim($line);

        // Línea vacía: fin de párrafo
        if ($trim === '') {
            $flush();
            continue;
        }

        // Bloque de código ```
        if (preg_match('/^```\s*([a-z0-9+#.\-]*)\s*$/i', $trim, $m)) {
            $flush();
            $code = [];
            for ($i++; $i < $n && !preg_match('/^```\s*$/', trim($lines[$i])); $i++) {
                $code[] = $lines[$i];
            }
            $cls = $m[1] !== '' ? ' class="lang-' . e(strtolower($m[1])) . '"' : '';
            $out[] = '<pre><code' . $cls . '>' . e(implode("\n", $code)) . '</code></pre>';
            continue;
        }

        // Título
        if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $trim, $m)) {
            $flush();
            // El h1 es el título de la página: # y ## salen como h2, ### como h3...
            $lvl = max(2, strlen($m[1]));
            $out[] = "<h{$lvl}>" . md_inline($m[2]) . "</h{$lvl}>";
            continue;
        }

        // Separador
        if (preg_match('/^([-*_])(\s*\1){2,}$/', $trim)) {
            $flush();
            $out[] = '<hr>';
            continue;
        }

        // Cita
        if (str_starts_with($trim, '>')) {
            $flush();
            $quote = [];
            while ($i < $n && str_starts_with(trim($lines[$i]), '>')) {
                $quote[] = preg_replace('/^\s*>\s?/', '', $lines[$i]);
                $i++;
            }
            $i--;
            $out[] = '<blockquote>' . md_blocks($quote) . '</blockquote>';
            continue;
        }

        // Tabla: fila con | seguida de separador |---|---|
        if (str_contains($trim, '|') && isset($lines[$i + 1])
            && preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/', trim($lines[$i + 1]))) {
            $flush();
            $head = md_table_cells($trim);
            $html = '<div class="tabla"><table><thead><tr>';
            foreach ($head as $c) {
                $html .= '<th scope="col">' . md_inline($c) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            for ($i += 2; $i < $n && str_contains($lines[$i], '|') && trim($lines[$i]) !== ''; $i++) {
                $cells = md_table_cells(trim($lines[$i]));
                $html .= '<tr>';
                for ($c = 0; $c < count($head); $c++) {
                    $html .= '<td>' . md_inline($cells[$c] ?? '') . '</td>';
                }
                $html .= '</tr>';
            }
            $i--;
            $out[] = $html . '</tbody></table></div>';
            continue;
        }

        // Listas (- * + o 1. 1))
        if (preg_match('/^\s{0,3}([-*+]|\d{1,9}[.)])\s+(.*)$/', $line, $m)) {
            $flush();
            $ordered = ctype_digit($m[1][0]);
            $items = [];
            $start = $ordered ? (int) $m[1] : 1;
            while ($i < $n) {
                $l = $lines[$i];
                if (preg_match('/^\s{0,3}([-*+]|\d{1,9}[.)])\s+(.*)$/', $l, $mm) && ctype_digit($mm[1][0]) === $ordered) {
                    $items[] = $mm[2];
                } elseif ($items && trim($l) !== '' && preg_match('/^\s{2,}\S/', $l)) {
                    // Línea de continuación (sangrada)
                    $items[count($items) - 1] .= "\n" . trim($l);
                } else {
                    break;
                }
                $i++;
            }
            $i--;
            $tag = $ordered ? 'ol' : 'ul';
            $attr = ($ordered && $start !== 1) ? ' start="' . $start . '"' : '';
            $html = "<{$tag}{$attr}>";
            foreach ($items as $it) {
                $html .= '<li>' . md_inline($it) . '</li>';
            }
            $out[] = $html . "</{$tag}>";
            continue;
        }

        $para[] = $line;
    }
    $flush();
    return implode("\n", $out);
}

function md_table_cells(string $row): array
{
    $row = trim($row);
    $row = preg_replace('/^\||\|$/', '', $row) ?? $row;
    return array_map('trim', explode('|', $row));
}

/** Formato en línea. Escapa primero y luego aplica el formato sobre el texto ya seguro. */
function md_inline(string $text): string
{
    $t = e($text);
    $store = [];
    $hold = static function (string $html) use (&$store): string {
        $store[] = $html;
        return "\x1A" . (count($store) - 1) . "\x1A";
    };

    // `código` (su contenido no se formatea)
    $t = preg_replace_callback('/`([^`\n]+)`/', static fn($m) => $hold('<code>' . $m[1] . '</code>'), $t);

    // ![alt](url)
    $t = preg_replace_callback('/!\[([^\]\n]*)\]\(([^)\s]+)\)/', static function ($m) use ($hold) {
        $src = md_safe_url($m[2], true);
        if ($src === null) {
            return $m[0];
        }
        // Imagen de uploads/ que ya no existe: se omite en vez de mostrar el icono roto
        $rawSrc = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (preg_match('#^/?uploads/([^/?\#]+)$#', $rawSrc, $um) && !upload_exists(rawurldecode($um[1]))) {
            return '';
        }
        return $hold('<img src="' . $src . '" alt="' . $m[1] . '" loading="lazy" decoding="async">');
    }, $t);

    // [texto](url)
    $t = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', static function ($m) use ($hold) {
        $href = md_safe_url($m[2], false);
        if ($href === null) {
            return $m[0];
        }
        $ext = preg_match('#^(https?:)?//#i', html_entity_decode($href, ENT_QUOTES, 'UTF-8')) ? ' rel="noopener noreferrer nofollow"' : '';
        return $hold('<a href="' . $href . '"' . $ext . '>' . md_emphasis($m[1]) . '</a>');
    }, $t);

    $t = md_emphasis($t);

    // Salto de línea forzado: dos espacios al final; el resto de saltos se convierten en espacio visual normal.
    $t = preg_replace('/ {2,}\n/', "<br>\n", $t);

    // Restaurar marcadores (en bucle por si hay anidados)
    for ($k = 0; $k < 3 && str_contains($t, "\x1A"); $k++) {
        $t = preg_replace_callback('/\x1A(\d+)\x1A/', static fn($m) => $store[(int) $m[1]] ?? '', $t);
    }
    return $t;
}

function md_emphasis(string $t): string
{
    $t = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $t);
    $t = preg_replace('/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])/s', '<em>$1</em>', $t);
    $t = preg_replace('/(?<![\w])_(?=\S)(.+?)(?<=\S)_(?![\w])/su', '<em>$1</em>', $t);
    $t = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $t);
    return $t;
}

/**
 * Valida una URL de enlace o imagen (llega ya escapada para HTML).
 * Devuelve la URL lista para un atributo, o null si no es segura.
 */
function md_safe_url(string $escaped, bool $isImage): ?string
{
    $raw = trim(html_entity_decode($escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($raw === '' || preg_match('/[\s\x00-\x1F\x7F]/', $raw)) {
        return null;
    }
    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $raw, $m)) {
        $scheme = strtolower($m[1]);
        $ok = $isImage ? ['https', 'http'] : ['https', 'http', 'mailto'];
        if (!in_array($scheme, $ok, true)) {
            return null;
        }
    } elseif (str_starts_with($raw, '//')) {
        return null; // sin protocolo explícito, no se permite
    } elseif (!str_starts_with($raw, '/') && !str_starts_with($raw, '#') && !str_starts_with($raw, '?')) {
        // Ruta relativa al proyecto (p. ej. uploads/foto.jpg): se le antepone la base
        $raw = base_path() . '/' . $raw;
    }
    return e($raw);
}

/** Texto plano a partir de Markdown (para resúmenes automáticos). */
function md_plain(string $md, int $len = 220): string
{
    $s = preg_replace('/```.*?```/s', ' ', $md) ?? $md;
    $s = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $s) ?? $s;
    $s = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $s) ?? $s;
    $s = preg_replace('/^\s{0,3}(#{1,6}|>|[-*+]|\d+[.)])\s+/m', '', $s) ?? $s;
    $s = preg_replace('/^\|?\s*:?-{3,}.*$/m', ' ', $s) ?? $s;
    $s = str_replace(['**', '__', '~~', '`', '|', '*'], ['', '', '', '', ' ', ''], $s);
    return str_limit($s, $len);
}
