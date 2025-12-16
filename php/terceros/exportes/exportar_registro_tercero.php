<?php
session_start();

if (!isset($_SESSION['tipo_usuario_id']) || 
    ($_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 3 && $_SESSION['tipo_usuario_id'] != 4)) {
    header('Location: ../../menu.php');
    exit;
}

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
require str_repeat('../', $levelsUp) . 'vendor/autoload.php';

use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Verificar autenticación
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location:" . str_repeat('../', $levelsUp) . "inicio_sesion.php");
    exit();
}

$mensaje = '';

// Función para exportar a Excel
function exportToExcel($conn, $startDate = null, $endDate = null, $userId = null, $levelsUp) {
    // Aumentar límite de memoria
    ini_set('memory_limit', '1024M');
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Tu consulta SQL actual
    $sql = "SELECT 
            vt.[tercero_id] AS 'Id Tercero', 
            t.[identificacion] AS 'Identificación',
            t.[tipo_documento_id] AS 'Tipo Documento',
            t.[primer_apellido] AS 'Primer Apellido', 
            t.[segundo_apellido] AS 'Segundo Apellido', 
            t.[nombre_nit] AS 'Nombre Nit / Primer Nombre',
            t.[segundo_nombre] AS 'Segundo Nombre', 
            t.[direccion] AS 'Dirección', 
            t.[telefono] AS 'Teléfono', 
            'COL' AS 'País',
            m.[descripcion_dep] AS dpto,
            t.[municipio_id] AS ciudad, 
            t.[ciiu_id] AS 'Código Ciiu', 
            t.[tipo_contribuyente_id] AS 'Tipo Contribuyente', 
            t.[retencion] AS 'Sujeto Retención (Y) Sin Retención',
            t.[banco_id] AS 'Banco Destino', 
            t.[tipo_cuenta_id] AS 'Tipo Cuenta', 
            t.[num_cuenta_bancaria] AS 'Numero Cuenta', 
            vt.[cantidad_facturas] AS 'Cantidad De Facturas',
            vt.[fac_desde] AS 'Factura Desde', 
            vt.[fac_hasta] AS 'Factura Hasta', 
            vt.[valor_total] AS 'Valor de la Factura', 
            r.[descripcion] AS Region, 
            vt.[observacion] AS Observacion, 
            tc.[descripcion] AS 'Tipo de Contrato'
        FROM validacion_terceros vt
        LEFT JOIN tercero t ON vt.tercero_id = t.id
        LEFT JOIN municipio m ON t.municipio_id = m.id
        JOIN region r ON m.region_id = r.id
        LEFT JOIN tipo_contrato tc ON vt.tipo_contrato_id = tc.id
        LEFT JOIN (
            SELECT id_validacion_tercero, MAX(fecha) AS max_fecha
            FROM evento_tercero
            GROUP BY id_validacion_tercero) et ON vt.id = et.id_validacion_tercero
        LEFT JOIN evento_tercero e ON vt.id = e.id_validacion_tercero AND e.fecha = et.max_fecha";

    $params = array();

    if ($startDate !== null) {
        $startDateTime = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);
        $endDateTime->setTime(23, 59, 59);
        $sql .= " WHERE e.fecha BETWEEN ? AND ?";
        $params[] = $startDateTime->format('Y-m-d H:i:s');
        $params[] = $endDateTime->format('Y-m-d H:i:s');
    }

    if ($userId !== null) {
        $sql .= ($startDate !== null) ? " AND e.id_usuario = ?" : " WHERE e.id_usuario = ?";
        $params[] = $userId;
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        error_log("Error en la consulta SQL: " . print_r(sqlsrv_errors(), true));
        return false;
    }

    if (sqlsrv_has_rows($stmt) === false) {
        return 'no_data';
    }

    // Configurar encabezados
    $metadata = sqlsrv_field_metadata($stmt);
    $headers = array();
    foreach ($metadata as $field) {
        $headers[] = $field['Name'];
    }
    $sheet->fromArray($headers, null, 'A1');

    // Obtener y escribir datos
    $row = 2;
    while ($data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rowValues = array();
        foreach ($data as $key => $value) {
            if ($value instanceof DateTime) {
                $rowValues[] = $value->format('Y-m-d H:i:s');
            } elseif ($key === 'Valor de la Factura') {
                $rowValues[] = is_numeric($value) ? (float)$value : 0;
            } elseif ($key === 'dpto') {
                $trimmedValue = preg_replace('/\s+/', '', $value);
                $rowValues[] = strtoupper(substr($trimmedValue, 0, 3));
            } else {
                $rowValues[] = $value;
            }
        }
        $sheet->fromArray($rowValues, null, 'A' . $row);
        $row++;
    }

        // Configurar el formato de las columnas
        $moneyColumns = ['A:B','I:I', 'L:N', 'P:R', 'T:U'];
        foreach ($moneyColumns as $column) {
            $sheet->getStyle($column)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }            
        $sheet->getStyle('V:V')->getNumberFormat()->setFormatCode('"$"#,##0.00');;
    
        // Ajustar el ancho de columna automáticamente
        $moneyColumns = ['A', 'B', 'C', 'D', 'E', 'G', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S',
                         'T', 'U', 'V', 'W', 'X', 'Y'];
        foreach ($moneyColumns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Establecer ancho de columnas (0.58)
        $sheet->getColumnDimension('F')->setWidth(28);

    // Generar nombre del archivo
    $filename = 'exports_';
    if ($userId) {
        $filename .= 'user_' . $userId . '_';
    }
    if ($startDate !== null) {
        $filename .= date('Y-m-d', strtotime($startDate));
        if ($startDate !== $endDate) {
            $filename .= '_to_' . date('Y-m-d', strtotime($endDate));
        }
    }
    $filename .= '_' . date('His') . '.xlsx';

    // Guardar archivo
    $filepath = str_repeat('../', $levelsUp) . 'exports/' . $filename;
    
    try {
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        // Liberar memoria
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        return $filepath;
    } catch (Exception $e) {
        error_log("Error al guardar Excel: " . $e->getMessage());
        return false;
    }
}

// Lógica para exportar a Excel (modificada para manejar los nuevos botones)
if (isset($_POST['export_excel']) || isset($_POST['export_all']) || isset($_POST['export_all_between']) || isset($_POST['export_user'])) {
    
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;
    $userId = $_SESSION['identificacion_usuario'];
    
    if (isset($_POST['export_excel'])) {
        $result = exportToExcel($conn, $startDate, $endDate, $userId, $levelsUp);

    } elseif (isset($_POST['export_user'])) {
        $result = exportToExcel($conn,null,null,$userId, $levelsUp);
        
    } elseif (isset($_POST['export_all_between'])) {
        $result = exportToExcel($conn, $startDate, $endDate, null, $levelsUp);

    } elseif (isset($_POST['export_all'])) {
        $result = exportToExcel($conn, null, null, null, $levelsUp);
    }

    if ($result === 'no_data') {
        $_SESSION['mensaje'] = "No se encontraron datos entre estas fechas";
    } elseif ($result === false) {
        $_SESSION['mensaje'] = "Error al generar el archivo" . print_r(sqlsrv_errors(), true);
    }else{
        $_SESSION['mensaje'] = "Archivo generado exitosamente";
         $_SESSION['download_file'] = basename($result); // Guardamos solo el nombre del archivo
    }

    // Redirigir para evitar reenvío del formulario
    $_SESSION['mensaje_tiempo'] = time();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Verificar si hay un mensaje para mostrar
if (isset($_SESSION['mensaje']) && isset($_SESSION['mensaje_tiempo'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tiempo']);
}

// Verificar si hay un archivo para descargar
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
    <title>Exportar a Excel</title>

    <link rel="stylesheet" href="styles.css">

    <script>
        let downloadWindow;
        
        function openDownloadWindow() {
            <?php if (isset($download_file)): ?>
            // Abrir una nueva ventana para la descarga
            downloadWindow = window.open('download.php?file=<?php echo $download_file; ?>', 'DownloadWindow', 'width=1,height=1,toolbar=no,menubar=no,location=no,status=no,scrollbars=no,resizable=no');
            
            // Intentar cerrar la ventana después de un tiempo
            setTimeout(function() {
                if (downloadWindow && !downloadWindow.closed) {
                    downloadWindow.close();
                }
            }, 5000); // 5 segundos, ajusta según sea necesario
            <?php endif; ?>
        }

        window.onload = function() {
            var mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(function() {
                    mensaje.style.display = 'none';
                }, 7000);
            }
            
            // Iniciar la descarga
            openDownloadWindow();
        }

        // Función para cerrar la ventana de descarga desde la ventana principal
        function closeDownloadWindow() {
            if (downloadWindow && !downloadWindow.closed) {
                downloadWindow.close();
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="container">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">Exportar a Excel</h2>
        
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
            <button type="submit" name="export_excel" class="btn-blue">Exportar entre fechas del usuario actual</button>
            <button type="submit" name="export_user" class="btn-green">Exportar todo del usuario actual</button>
            <button type="submit" name="export_all_between" class="btn-yellow">Exportar todo entre fechas</button>
            <button type="submit" name="export_all" class="btn-purple">Exportar todo</button>
        </form>
        
        <a href="<?php echo url('menu.php'); ?>"class="block w-full bg-gray-500 text-white py-2 px-4 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50 text-center">Regresar al Menú</a>
    </div>
</body>
</html>