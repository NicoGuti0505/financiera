<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_autenticado'])) {
    header("HTTP/1.1 403 Forbidden");
    exit('Acceso no autorizado');
}

// Verificar que se proporcionó un nombre de archivo
if (!isset($_GET['file'])) {
    header("HTTP/1.1 400 Bad Request");
    exit('No se especificó ningún archivo para descargar.');
}

// Validar el nombre del archivo para seguridad
$filename = basename($_GET['file']); // Solo obtener el nombre del archivo, sin ruta
if (!preg_match('/^[a-zA-Z0-9_-]+\.xlsx$/', $filename)) {
    header("HTTP/1.1 400 Bad Request");
    exit('Nombre de archivo no válido');
}

// Construir la ruta completa del archivo
$file = '../../../exports/' . $filename;

// Verificar que el archivo existe y está en el directorio correcto
if (!file_exists($file) || !is_file($file)) {
    header("HTTP/1.1 404 Not Found");
    exit('El archivo no existe.');
}

try {
    // Verificar que el archivo es accesible y legible
    if (!is_readable($file)) {
        throw new Exception('El archivo no es accesible.');
    }

    // Obtener el tamaño del archivo
    $filesize = filesize($file);
    if ($filesize === false) {
        throw new Exception('No se pudo determinar el tamaño del archivo.');
    }

    // Limpiar cualquier salida anterior
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Configurar las cabeceras para la descarga
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Leer y enviar el archivo en chunks para manejar archivos grandes
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        throw new Exception('No se pudo abrir el archivo para lectura.');
    }

    while (!feof($handle)) {
        $buffer = fread($handle, 1048576); // Leer en chunks de 1MB
        echo $buffer;
        flush();
    }
    fclose($handle);

    // Eliminar el archivo después de la descarga exitosa
    unlink($file);

    exit();

} catch (Exception $e) {
    // Log del error
    error_log('Error en la descarga del archivo: ' . $e->getMessage());
    
    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Enviar respuesta de error
    header("HTTP/1.1 500 Internal Server Error");
    exit('Error al descargar el archivo: ' . $e->getMessage());
}
?>
