<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['usuario_autenticado'])) {
    http_response_code(401);
    exit('No autenticado');
}

/** MISMA base que usas en buscar_docs.php */
$BASE_DIR = 'E:/financiera_doc/DOCUMENTOS ADMINISTRATIVOS';


/** Helpers */
function safe_path(string $base, string $rel_b64) {
    $rel = base64_decode($rel_b64, true);
    if ($rel === false) return false;

    $full = realpath($base . DIRECTORY_SEPARATOR . $rel);
    if (!$full) return false;

    $baseReal = realpath($base);
    if (strpos($full, $baseReal) !== 0) return false; // fuera del base

    return $full;
}

$f = $_GET['f'] ?? '';
$path = safe_path($BASE_DIR, $f);
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$fname = basename($path);
$size  = filesize($path);

header('Content-Type: application/pdf');
header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('X-Content-Type-Options: nosniff');

@ob_end_clean();
$fp = fopen($path, 'rb');
fpassthru($fp);
exit;
