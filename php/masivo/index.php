<?php
// VERIFICACION DE ERRORES
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Obtiene el directorio del archivo actual y divide la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
require_once __DIR__ . '../../../vendor/autoload.php';

// Incluir el archivo script_tapa.php donde está definida la función pdf_tapa()
require_once 'script_tapa.php';  // Asegúrate de que esta ruta sea correcta

// Verificar autenticación
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: ../../inicio_sesion.php");
    exit();
}
if (!isset($_SESSION['tipo_usuario_id']) || 
    ($_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 3 && $_SESSION['tipo_usuario_id'] != 4)) {
    header('Location: ../menu.php');
    exit;
}
$mensaje = '';

if (isset($_POST["submit"])) {
    set_time_limit(1500);
    $fecha_hora_inicio = $_POST["start_date"] . " " . $_POST["start_time"];
    $fecha_hora_fin = $_POST["end_date"] . " " . $_POST["end_time"];

    if ($_POST["submit"] == "csv") {
        include("script_masivo_txt.php");
        txt($serverName, $connectionInfo, $fecha_hora_inicio, $fecha_hora_fin);
        $mensaje = 'Archivo Masivo descargado con éxito.';
    
    } elseif ($_POST["submit"] == "tapa") {
        // Ahora podemos llamar a pdf_tapa directamente, ya que está definida en script_tapa.php
        $pdfFiles = pdf_tapa($serverName, $connectionInfo, $fecha_hora_inicio, $fecha_hora_fin);

        // Verificar si hay archivos para comprimir
        if (!empty($pdfFiles)) {
            $zip = new ZipArchive();
            $zipFileName = 'TAPA.zip';

            if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
                foreach ($pdfFiles as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();

                ob_end_clean();

                // Descargar el archivo ZIP
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                header('Content-Length: ' . filesize($zipFileName));
                readfile($zipFileName);

                unlink($zipFileName); // Eliminar el ZIP del servidor
                foreach ($pdfFiles as $file) {
                    unlink($file); // Eliminar los PDFs individuales
                }
            } else {
                echo 'Error al crear el archivo ZIP';
            }

            $mensaje = 'Archivo TAPA descargado con éxito.';
        } else {
            $mensaje = 'No existe información para mostrar.';
        }
    }
}
?>



<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exportar a Excel</title>
    <link rel="stylesheet" href="style.css">
    <script>
        window.onload = function() {
            var mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(function() {
                    mensaje.style.display = 'none';
                }, 7000);
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">Exportar Archivo Masivo</h2>
        
        <?php if ($mensaje): ?>
            <div id="mensaje" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $mensaje; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 mb-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Fecha inicio:</label>
                <input type="date" id="start_date" name="start_date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            <div>
                <label for="start_time" class="block text-sm font-medium text-gray-700">Hora inicio:</label>
                <input type="time" id="start_time" name="start_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">Fecha fin:</label>
                <input type="date" id="end_date" name="end_date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            <div>
                <label for="end_time" class="block text-sm font-medium text-gray-700">Hora fin:</label>
                <input type="time" id="end_time" name="end_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            <div>
                <button type="submit" name="submit" value="csv" class="btn-blue">
                    Descargar CSV
                </button>
            </div>
        </form>
       <a href="../menu.php" class="block w-full bg-gray-500 text-white py-2 px-4 rounded-md hover:bg-gray-600 text-center flex justify-center items-center">
           Regresar al Menú
       </a>

    </div>
</body>
</html>
