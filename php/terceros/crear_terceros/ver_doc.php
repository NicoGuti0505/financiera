<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['usuario_autenticado'])) {
    http_response_code(401);
    exit('No autenticado');
}

/** CONFIG **/
$BASE_DIR = 'E:/financiera_doc/DOCUMENTOS ADMINISTRATIVOS';


function safe_path($base, $relB64) {
    $rel = base64_decode($relB64, true);
    if ($rel === false) return false;
    $full = realpath($base . DIRECTORY_SEPARATOR . $rel);
    if ($full === false) return false;
    $baseReal = realpath($base);
    if (strpos($full, $baseReal) !== 0) return false;
    if (!preg_match('/\.pdf$/i', $full)) return false;
    return $full;
}

if (!isset($_GET['f'])) {
    http_response_code(400);
    exit('Parámetro faltante');
}

$path = safe_path($BASE_DIR, $_GET['f']);
if ($path === false || !is_file($path)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.basename($path).'"');
header('Content-Length: ' . filesize($path));
readfile($path);
