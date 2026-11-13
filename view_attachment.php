<?php
/** Streams an evidence photo to signed-in staff only. Uploads are never served directly. */
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = $_GET['id'] ?? '';
if (!is_string($id) || !ctype_digit($id)) {
    http_response_code(400);
    exit('Invalid request.');
}
$stmt = db()->prepare('SELECT photo_path FROM reports WHERE id = :id LIMIT 1');
$stmt->execute([':id' => (int)$id]);
$photo = $stmt->fetchColumn();
if (!$photo) {
    http_response_code(404);
    exit('Not found.');
}
$path = rtrim((string)cfg('uploads.dir'), '/\\') . DIRECTORY_SEPARATOR . basename((string)$photo);
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Unsupported file.');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="evidence-' . (int)$id . '.' . ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] . '"');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
readfile($path);
exit;
