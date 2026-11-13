<?php
/**
 * Evidence photo handling. Never trusts the browser's filename or MIME type.
 * Images are decoded and re-encoded, which strips EXIF metadata (including GPS
 * location and device details) and neutralises polyglot files.
 */

/**
 * @return array{path:?string, error:?string}  path is the stored filename (no directory)
 */
function store_evidence_photo(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    $maxBytes = (int)cfg('uploads.max_bytes');
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > $maxBytes) {
        return ['path' => null, 'error' => 'The photo is larger than ' . (int)($maxBytes / 1048576) . ' MB. Please choose a smaller image.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['path' => null, 'error' => 'The photo could not be uploaded. Please try again.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || $info === false || ($info['mime'] ?? '') !== $mime) {
        return ['path' => null, 'error' => 'Please upload a JPG, PNG or WebP photo.'];
    }
    [$width, $height] = $info;
    if ($width < 50 || $height < 50 || $width * $height > 40_000_000) {
        return ['path' => null, 'error' => 'That image size is not supported. Please choose a normal photo.'];
    }

    $dir = rtrim((string)cfg('uploads.dir'), '/\\');
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        error_log('[CivicServe] Upload directory is not writable: ' . $dir);
        return ['path' => null, 'error' => 'Photo uploads are temporarily unavailable. You can submit without a photo.'];
    }

    $name = bin2hex(random_bytes(16));
    $target = null;

    if (extension_loaded('gd')) {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        };
        if ($src === false) {
            return ['path' => null, 'error' => 'That photo appears to be damaged. Please choose another.'];
        }
        $max = (int)cfg('uploads.max_px', 2400);
        $scale = min(1, $max / max($width, $height));
        $w = max(1, (int)round($width * $scale));
        $h = max(1, (int)round($height * $scale));
        $dst = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);                // flatten transparency onto white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $width, $height);
        $target = $name . '.jpg';
        $ok = imagejpeg($dst, $dir . DIRECTORY_SEPARATOR . $target, 85);
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            return ['path' => null, 'error' => 'We could not save the photo. You can submit without a photo.'];
        }
    } else {
        // Fallback when GD is unavailable: store the validated original under a random name.
        $target = $name . '.' . $types[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $target)) {
            return ['path' => null, 'error' => 'We could not save the photo. You can submit without a photo.'];
        }
    }
    @chmod($dir . DIRECTORY_SEPARATOR . $target, 0640);
    return ['path' => $target, 'error' => null];
}

function delete_evidence_photo(?string $stored): void
{
    if (!$stored) {
        return;
    }
    $path = rtrim((string)cfg('uploads.dir'), '/\\') . DIRECTORY_SEPARATOR . basename($stored);
    if (is_file($path)) {
        @unlink($path);
    }
}
