<?php
/**
 * Subida de imágenes.
 * - Solo JPG, PNG y WebP, máximo 2 MB (configurable).
 * - El tipo se comprueba por el CONTENIDO (getimagesize), no por la extensión que manda el navegador.
 * - Si el servidor tiene GD, la imagen se vuelve a codificar: así se eliminan metadatos
 *   (GPS de la cámara, etc.) y cualquier código escondido dentro del archivo, y se reduce
 *   si es más ancha de lo necesario.
 * - El nombre final es aleatorio; nunca se usa el nombre original en disco.
 */
declare(strict_types=1);

const UPLOAD_TYPES = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
];

/**
 * Procesa $_FILES[$field]. Devuelve ['ok' => true, 'archivo' => ..., 'id' => ...] o ['ok' => false, 'error' => ...].
 * Si no se ha enviado archivo devuelve null.
 */
function handle_image_upload(string $field, int $userId): ?array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f) || is_array($f['error'])) {
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $max = (int) cfg('upload_max_bytes');
    $maxTxt = human_bytes($max);

    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => "La imagen pesa demasiado. Máximo: {$maxTxt}."];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return ['ok' => false, 'error' => 'La imagen no se ha recibido bien. Vuelve a intentarlo.'];
    }
    if ($f['size'] <= 0 || $f['size'] > $max) {
        return ['ok' => false, 'error' => "La imagen pesa demasiado. Máximo: {$maxTxt}."];
    }

    $origName = (string) $f['name'];
    $extSent = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($extSent, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'error' => 'Formato no admitido. Usa JPG, PNG o WebP.'];
    }

    $info = @getimagesize($f['tmp_name']);
    if ($info === false || !isset(UPLOAD_TYPES[$info[2]])) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen JPG, PNG o WebP válida.'];
    }
    [$w, $h, $type] = $info;
    $maxPx = (int) cfg('upload_max_px');
    if ($w < 1 || $h < 1 || $w > $maxPx || $h > $maxPx) {
        return ['ok' => false, 'error' => "La imagen es demasiado grande en píxeles (máximo {$maxPx} × {$maxPx})."];
    }

    $ext = UPLOAD_TYPES[$type];
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = APP_ROOT . '/uploads/' . $name;

    $saved = reencode_image($f['tmp_name'], $dest, $type, $w, $h);
    if ($saved === null) {
        // Sin GD (o sin soporte para ese formato): se guarda el original ya validado.
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'No se pudo guardar la imagen. Revisa los permisos de la carpeta uploads.'];
        }
        $saved = [$w, $h];
    } elseif ($saved === false) {
        return ['ok' => false, 'error' => 'No se pudo procesar la imagen. Prueba con otra o redúcela antes de subirla.'];
    }
    @chmod($dest, 0644);

    $size = (int) filesize($dest);
    q('INSERT INTO imagenes (archivo, nombre_original, tamano, ancho, alto, subida_por, creada_en) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$name, mb_substr(basename($origName), 0, 255), $size, (int) $saved[0], (int) $saved[1], $userId, now()]);

    return ['ok' => true, 'archivo' => $name, 'id' => (int) db()->lastInsertId()];
}

/**
 * Vuelve a codificar con GD. Devuelve [ancho, alto], false si falla o null si GD no está disponible.
 */
function reencode_image(string $src, string $dest, int $type, int $w, int $h): array|false|null
{
    $fns = [
        IMAGETYPE_JPEG => ['imagecreatefromjpeg', 'imagejpeg'],
        IMAGETYPE_PNG  => ['imagecreatefrompng', 'imagepng'],
        IMAGETYPE_WEBP => ['imagecreatefromwebp', 'imagewebp'],
    ];
    [$load, $save] = $fns[$type];
    if (!function_exists($load) || !function_exists($save) || !function_exists('imagecreatetruecolor')) {
        return null;
    }
    // Evita agotar la memoria del hosting gratuito (≈ 5 bytes por píxel, origen + destino).
    if ($w * $h * 10 > 96 * 1024 * 1024) {
        return null;
    }
    $img = @$load($src);
    if (!$img) {
        return false;
    }
    $maxW = (int) cfg('image_max_width');
    $nw = $w;
    $nh = $h;
    if ($w > $maxW) {
        $nw = $maxW;
        $nh = (int) round($h * $maxW / $w);
    }
    $out = imagecreatetruecolor($nw, $nh);
    if ($type !== IMAGETYPE_JPEG) {   // conserva la transparencia
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    }
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($out, $dest, 85),
        IMAGETYPE_PNG  => imagepng($out, $dest, 7),
        IMAGETYPE_WEBP => imagewebp($out, $dest, 82),
    };
    unset($img, $out); // libera memoria (imagedestroy() está obsoleta desde PHP 8)
    return $ok ? [$nw, $nh] : false;
}

/** Lista de imágenes subidas (para los selectores del panel). */
function list_images(int $limit = 60): array
{
    return q_all('SELECT id, archivo, nombre_original, ancho, alto FROM imagenes ORDER BY id DESC LIMIT ?', [$limit]);
}
