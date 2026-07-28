<?php
// ============================================================
// pdf-onboarding.php — sert les PDF de l'onboarding « classique » (op.pdf /
// os.pdf) avec le BON en-tête (application/pdf). Sur certains hébergements le
// PDF servi en statique arrivait sans type correct → le navigateur affichait
// les octets bruts (« texte bizarre »). Ici on force l'en-tête + le streaming
// avec support Range (lecture/navigation fluide d'un gros PDF).
// ============================================================
require_once __DIR__ . '/config.php';
verifierConnexion($db);

// Fichiers autorisés uniquement (pas d'accès arbitraire au disque).
$map = ['op' => 'op.pdf', 'os' => 'os.pdf'];
$k = isset($_GET['f']) ? (string) $_GET['f'] : 'op';
$file = $map[$k] ?? 'op.pdf';
$path = __DIR__ . '/' . $file;
if (!is_file($path)) { http_response_code(404); exit('PDF introuvable'); }

while (ob_get_level() > 0) { @ob_end_clean(); }

$size = filesize($path);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $file . '"');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400');

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
