<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_autenticado'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

/** CONFIG **/
$BASE_DIR = 'E:/financiera_doc/DOCUMENTOS ADMINISTRATIVOS';


/** Helpers **/
function norm_nit($s){ return preg_replace('/[^0-9]/','', (string)$s); }
function is_pdf($path){ return is_file($path) && preg_match('/\.pdf$/i', $path); }

$nitRaw = isset($_POST['nit']) ? trim((string)$_POST['nit']) : '';
$nitNorm = norm_nit($nitRaw);

// nombre de carpeta: exactamente lo que está en el campo identificación,
// pero saneado para evitar caracteres inválidos en Windows.
$folderNameSafe = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $nitRaw);
if ($folderNameSafe === '') $folderNameSafe = $nitNorm;

if ($folderNameSafe === '') {
    echo json_encode(['error' => 'NIT vacío']);
    exit;
}

if (!is_dir($BASE_DIR)) {
    echo json_encode(['error' => 'No existe el directorio base']);
    exit;
}

/** 1) Buscar carpeta que contenga el NIT (o similar) **/
$targetFolders = [];

// búsqueda fuerte (contenga los dígitos del NIT en el nombre)
$it1 = new DirectoryIterator($BASE_DIR);
foreach ($it1 as $f) {
    if ($f->isDot() || !$f->isDir()) continue;
    $nameNorm = norm_nit($f->getFilename());
    if ($nameNorm !== '' && strpos($nameNorm, $nitNorm) !== false) {
        $targetFolders[] = $f->getPathname();
    }
}

// si no hay, búsqueda blanda por el texto crudo
if (empty($targetFolders) && $nitRaw !== '') {
    $it2 = new DirectoryIterator($BASE_DIR);
    foreach ($it2 as $f) {
        if ($f->isDot() || !$f->isDir()) continue;
        if (mb_stripos($f->getFilename(), $nitRaw) !== false) {
            $targetFolders[] = $f->getPathname();
        }
    }
}

/** 2) Si no existe carpeta -> crearla con el identificador (folderNameSafe) **/
$created = false;
if (empty($targetFolders)) {
    $newFolder = rtrim($BASE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $folderNameSafe;
    if (!is_dir($newFolder)) {
        if (!@mkdir($newFolder, 0777, true)) {
            echo json_encode(['error' => 'No se pudo crear la carpeta destino']);
            exit;
        }
        // en Windows/IIS puede requerir permisos del usuario del servicio web
    }
    $targetFolders[] = $newFolder;
    $created = true;
}

/** 3) Recorrer PDFs de forma recursiva en todas las carpetas encontradas **/
$files = [];
$rutCandidates = [];
$certCandidates = [];

$baseReal = realpath($BASE_DIR);

foreach ($targetFolders as $folder) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $fileInfo) {
        $path = $fileInfo->getPathname();
        if (!is_pdf($path)) continue;
        $mtime = $fileInfo->getMTime();
        $relPath = substr(realpath($path), strlen($baseReal) + 1); // relativo a base

        $files[] = [
            'name' => $fileInfo->getFilename(),
            'rel'  => $relPath,
            'mtime'=> $mtime,
            'size' => $fileInfo->getSize(),
            'dir'  => dirname($relPath),
        ];

        $fnameLower = mb_strtolower($fileInfo->getFilename(), 'UTF-8');
        if (strpos($fnameLower, 'rut') !== false) {
            $rutCandidates[] = ['rel' => $relPath, 'mtime' => $mtime];
        }
        if (strpos($fnameLower, 'cert') !== false && (strpos($fnameLower, 'banc') !== false || strpos($fnameLower, 'occidente') !== false)) {
            $certCandidates[] = ['rel' => $relPath, 'mtime' => $mtime];
        }
    }
}

/** 4) Ordenar por fecha desc **/
usort($files, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
usort($rutCandidates, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
usort($certCandidates, fn($a,$b) => $b['mtime'] <=> $a['mtime']);

$result = [
    'folder'         => $targetFolders[0],
    'files'          => $files,
    'rut_last'       => $rutCandidates[0]['rel']  ?? null,
    'cert_banc_last' => $certCandidates[0]['rel'] ?? null,
];

if ($created) {
    $result['created'] = true;
    $result['message'] = 'Carpeta creada';
}

echo json_encode($result);
