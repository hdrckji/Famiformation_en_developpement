<?php
// ============================================================
// volontaire/fichier.php — sert les MÉDIAS d'exemple du mini-site volontaire
// (PDF + vidéo) depuis le VOLUME Railway persistant. PUBLIC (pas de login : le
// site volontaire est public). Repli sur la copie d'exemple du dépôt (ex/) tant
// que rien n'a été uploadé sur le volume. En-tête correct + support Range.
// ============================================================

// Base du volume persistant (comme config.php, mais sans session ni DB : c'est
// un endpoint binaire public, on garde ça léger et propre).
$vol = getenv('RAILWAY_VOLUME_MOUNT_PATH');
if ((!$vol || $vol === '') && isset($_SERVER['RAILWAY_VOLUME_MOUNT_PATH'])) { $vol = $_SERVER['RAILWAY_VOLUME_MOUNT_PATH']; }
$base = ($vol && $vol !== '') ? rtrim($vol, "/\\") : (__DIR__ . '/../uploads');

// Fichiers autorisés : clé -> (chemin volume, repli dépôt, type MIME).
$map = [
    'pdf'   => ['vol' => 'volontaire/exemple-pdf.pdf',   'repo' => __DIR__ . '/ex/exemple-pdf-arroser.pdf',   'ct' => 'application/pdf'],
    'video' => ['vol' => 'volontaire/exemple-video.mp4', 'repo' => __DIR__ . '/ex/exemple-video-arroser.mp4', 'ct' => 'video/mp4'],
];
$k = isset($_GET['f']) ? (string) $_GET['f'] : '';
if (!isset($map[$k])) { http_response_code(404); exit('introuvable'); }

$volPath = $base . '/' . $map[$k]['vol'];
$path = is_file($volPath) ? $volPath : $map[$k]['repo'];   // volume prioritaire, sinon exemple du dépôt
if (!is_file($path)) { http_response_code(404); exit('fichier absent'); }

while (ob_get_level() > 0) { @ob_end_clean(); }

$size = filesize($path);
header('Content-Type: ' . $map[$k]['ct']);
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

$start = 0; $end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') { $start = (int) $m[1]; }
    if ($m[2] !== '') { $end = (int) $m[2]; }
    if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    if ($end >= $size) { $end = $size - 1; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));

$fp = fopen($path, 'rb');
if ($fp === false) { http_response_code(500); exit; }
fseek($fp, $start);
$remaining = $end - $start + 1;
$chunk = 512 * 1024;
while ($remaining > 0 && !feof($fp)) {
    $buf = fread($fp, ($remaining > $chunk) ? $chunk : $remaining);
    if ($buf === false) { break; }
    echo $buf;
    $remaining -= strlen($buf);
}
fclose($fp);
exit;
