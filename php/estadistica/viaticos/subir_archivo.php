<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    http_response_code(403);
    exit("Sesión no válida.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']) && isset($_POST['ruta_destino'])) {
    $archivo = $_FILES['archivo'];
    $ruta = $_POST['ruta_destino'];

    // Validación básica (evitar rutas remotas o maliciosas)
    if (strpos($ruta, 'E:') !== 0 && strpos($ruta, '/drive') !== 0) {
        exit("Ruta de destino no permitida.");
    }

    // Crear directorio si no existe
    if (!is_dir($ruta)) {
        mkdir($ruta, 0777, true);
    }

    $nombreArchivo = basename($archivo['name']);
    $destino = rtrim($ruta, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreArchivo;

    if (move_uploaded_file($archivo['tmp_name'], $destino)) {
        echo "Archivo subido exitosamente.";
    } else {
        echo "Error al mover el archivo.";
    }
} else {
    echo "Solicitud no válida.";
}
