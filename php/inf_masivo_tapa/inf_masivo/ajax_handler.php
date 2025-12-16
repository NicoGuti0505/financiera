<?php
//Ruta
$Ruta = dirname(__FILE__);
$pathParts = explode('\\', $Ruta);
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Configuración de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr on line $errline in file $errfile");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
 
try {
    require_once str_repeat('../', $levelsUp) . 'config.php';
    require_once 'functions.php';

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Procesar la solicitud AJAX
    if (isset($_POST['ajax'])) {
        $result = ['success' => false, 'message' => 'No se encontró información', 'registros' => []];
        
        // Obtener fechas del filtro si están presentes
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;
        $regionf_id = isset($_POST['regionf_id']) ? $_POST['regionf_id'] :  null;
    
        try {
            $stmt = buscar_inf_masivo($conn, $start_date, $end_date,$regionf_id);
            
            $registros = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Sanitizar datos antes de enviarlos
                array_walk_recursive($row, function(&$item) {
                    if ($item instanceof DateTime) {
                        $item = $item->format('Y-m-d');
                    }
                    $item = $item === null ? '' : htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
                });
                $registros[] = $row;
            }
            
            if (!empty($registros)) {
                $result = [
                    'success' => true,
                    'message' => 'Registros encontrados',
                    'registros' => $registros
                ];
            }
            
            echo json_encode($result);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    // Procesar guardado de datos
    if (isset($_POST['data'])) {
        $datos = json_decode($_POST['data'], true);
        $validado = isset($_POST['validado']) ? filter_var($_POST['validado'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if ($datos === null) {
            throw new Exception('Error al decodificar los datos JSON');
        }
        
        $resultado = verData($datos, $conn, $validado);
        echo json_encode($resultado);
        exit;
    }

    throw new Exception('Acción no reconocida');

} catch (Throwable $e) {
    error_log("Error capturado en ajax_handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>