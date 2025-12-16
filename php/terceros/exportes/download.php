<?php
session_start();

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

if (isset($_GET['file'])) {
    $file = str_repeat('../', $levelsUp) . 'exports/' . $_GET['file'];
    
    if (file_exists($file)) {
        // Limpiar cualquier salida anterior
        if (ob_get_level()) ob_end_clean();
        
        // Configurar las cabeceras para la descarga
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        
        // Leer el archivo
        readfile($file);
        
        // Eliminar el archivo después de la descarga
        unlink($file);
        exit;
    } else {
        echo "El archivo no existe.";
    }
} else {
    echo "No se especificó ningún archivo para descargar.";
}
?>