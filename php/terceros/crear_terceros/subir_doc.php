<?php
// terceros/crear_terceros/subir_doc.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // evita romper el JSON en producción
header('Content-Type: application/json; charset=utf-8');

// Carpeta base
$BASE_DIR = 'E:/financiera_doc/DOCUMENTOS ADMINISTRATIVOS';

// Helpers
function only_digits($s){ return preg_replace('/\D/','', (string)$s); }
function clean_filename($s){ return preg_replace('/[^A-Za-z0-9._\-\sáéíóúÁÉÍÓÚñÑ()]/u','_', basename((string)$s)); }

$nit = isset($_POST['nit']) ? only_digits($_POST['nit']) : '';
if ($nit === ''){
    echo json_encode(['success'=>false,'message'=>'NIT inválido']); exit;
}
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK){
    echo json_encode(['success'=>false,'message'=>'Archivo no recibido']); exit;
}
if (!is_dir($BASE_DIR)){
    echo json_encode(['success'=>false,'message'=>'Ruta base no disponible']); exit;
}

// Buscar carpeta que contenga el NIT (última modificada)
$bestDir   = null;
$bestMtime = 0;
try {
    $it = new DirectoryIterator($BASE_DIR);
    foreach ($it as $f){
        if ($f->isDot() || !$f->isDir()) continue;
        if (stripos($f->getFilename(), $nit) !== false){
            $mt = $f->getMTime();
            if ($mt > $bestMtime){ $bestMtime = $mt; $bestDir = $f->getPathname(); }
        }
    }
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'No se pudo leer el directorio base']); exit;
}

// Si no existe carpeta para ese NIT, crearla con el NIT
if (!$bestDir){
    $bestDir = rtrim($BASE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nit;
    if (!is_dir($bestDir) && !@mkdir($bestDir, 0777, true)){
        echo json_encode(['success'=>false,'message'=>'No se pudo crear la carpeta destino']); exit;
    }
}

// Nombre de archivo saneado y evitar colisión
$fn   = clean_filename($_FILES['archivo']['name']);
$dest = $bestDir . DIRECTORY_SEPARATOR . $fn;

$pi   = pathinfo($dest);
$base = $pi['filename'] ?? 'archivo';
$ext  = isset($pi['extension']) && $pi['extension'] !== '' ? ('.'.$pi['extension']) : '';
$idx  = 1;
while (file_exists($dest)){
    $dest = $bestDir . DIRECTORY_SEPARATOR . $base . " ($idx)" . $ext;
    $idx++;
}

if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)){
    echo json_encode(['success'=>false,'message'=>'No se pudo mover el archivo']); exit;
}

// Ruta relativa (con /) para el visor
$baseReal = rtrim(str_replace('\\','/', realpath($BASE_DIR)), '/');
$destReal = str_replace('\\','/', realpath($dest));
$rel      = ltrim(substr($destReal, strlen($baseReal)), '/');

echo json_encode([
    'success' => true,
    'file' => [
        'name'  => basename($dest),
        'rel'   => $rel,
        'size'  => filesize($dest),
        'mtime' => filemtime($dest),
    ]
]);
