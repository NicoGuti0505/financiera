<?php
session_start();

if (!isset($_SESSION['tipo_usuario_id']) || 
    ($_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 3 && $_SESSION['tipo_usuario_id'] != 4)) {
    header('Location: ../../menu.php');
    exit;
}

// Obtiene el directorio del archivo actual y divide la ruta en partes
$pathParts = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
$levelsUp = count($pathParts) - array_search('php', $pathParts);

// Conectar con la configuración
require_once str_repeat('../', $levelsUp) . 'config.php';
require_once str_repeat('../', $levelsUp) . 'vendor/autoload.php';
require_once 'busq_inf.php';
require_once 'excel_exporter.php';
require_once 'functions.php';

// Verificar autenticación  
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location:" . str_repeat('../', $levelsUp) . "inicio_sesion.php");
    exit();
}

$mensaje = '';
$download_file = null;

// Procesar la solicitud de exportación
if (isset($_POST['export_excel']) || isset($_POST['export_all']) || isset($_POST['export_all_between']) || isset($_POST['export_user'])) {
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;
    $userId = $_SESSION['identificacion_usuario'];
    
    try {
        $dataFetcher = new DataFetcher($conn);
        
        // Determinar qué datos obtener según el botón presionado
        if (isset($_POST['export_excel'])) {
            $data = $dataFetcher->buscarInformacion($startDate, $endDate, $userId);
        } elseif (isset($_POST['export_user'])) {
            $data = $dataFetcher->buscarInformacion(null, null, $userId);
        } elseif (isset($_POST['export_all_between'])) {
            $data = $dataFetcher->buscarInformacion($startDate, $endDate);
        } else {
            $data = $dataFetcher->buscarInformacion();
        }

        if (empty($data)) {
            throw new Exception("No se encontraron datos para exportar");
        }

        $exporter = new ExcelExporter();
        $filename = $exporter->export($data);
        
        $_SESSION['mensaje'] = "Archivo generado exitosamente";
        $_SESSION['download_file'] = basename($filename);

    } catch (Exception $e) {
        $_SESSION['mensaje'] = "Error: " . $e->getMessage();
    }

    $_SESSION['mensaje_tiempo'] = time();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Verificar mensajes y archivos para descargar
if (isset($_SESSION['mensaje']) && isset($_SESSION['mensaje_tiempo'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tiempo']);
}

if (isset($_SESSION['download_file'])) {
    $download_file = $_SESSION['download_file'];
    unset($_SESSION['download_file']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Anexo de Pago a Excel</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        let downloadWindow;
        
        function openDownloadWindow() {
            <?php if (isset($download_file)): ?>
            downloadWindow = window.open('download.php?file=<?php echo $download_file; ?>', 'DownloadWindow', 'width=1,height=1,toolbar=no,menubar=no,location=no,status=no,scrollbars=no,resizable=no');
            
            setTimeout(function() {
                if (downloadWindow && !downloadWindow.closed) {
                    downloadWindow.close();
                }
            }, 5000);
            <?php endif; ?>
        }

        window.onload = function() {
            var mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(function() {
                    mensaje.style.display = 'none';
                }, 7000);
            }
            
            openDownloadWindow();
        }

        function closeDownloadWindow() {
            if (downloadWindow && !downloadWindow.closed) {
                downloadWindow.close();
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="container">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">Exportar Anexo de Pago a Excel</h2>   
        
        <?php if ($mensaje): ?>
            <div id="mensaje" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $mensaje; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 mb-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Fecha de inicio:</label>
                <input type="date" id="start_date" name="start_date" required value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">Fecha final:</label>
                <input type="date" id="end_date" name="end_date" required value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            <button type="submit" name="export_excel" class="btn-blue">Exportar entre fechas usuario actual</button>
            <button type="submit" name="export_user" class="btn-blue">Exportar todo usuario actual</button>
            <!--<button type="submit" name="export_all_between" class="w-full bg-yellow-500 text-white py-2 px-4 rounded-md hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-100 focus:ring-opacity-50">Exportar todo entre fechas</button>
            <button type="submit" name="export_all" class="w-full bg-purple-500 text-white py-2 px-4 rounded-md hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50">Exportar todo</button>-->
        </form>
        
        <a href="<?php echo url('menu.php'); ?>" class="block w-full bg-gray-500 text-white py-2 px-4 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50 text-center">Regresar al Menú</a>
    </div>
</body>
</html>
