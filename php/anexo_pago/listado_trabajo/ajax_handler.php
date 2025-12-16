<?php

// Limpiar cualquier salida anterior
if (ob_get_level()) ob_end_clean();

// Iniciar nuevo buffer
ob_start();


// Deshabilitar la salida de errores PHP al navegador
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Establecer headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Asegurarse de que no haya salida antes de los headers
ob_clean();

// Función para manejar errores
function handleError($message, $code = 400) {
    // Limpiar cualquier salida previa
    if (ob_get_level()) ob_end_clean();
    
    http_response_code($code);
    error_log("Error en ajax_handler.php: " . $message);
    echo json_encode([
        'success' => false, 
        'message' => $message,
        'error' => true
    ]);
    exit;
}

//Ruta
$Ruta = dirname(__FILE__);
// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', $Ruta);
// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
require_once 'functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Método no permitido', 405);
    }

    if (empty($_POST)) {
        handleError('No se recibieron datos POST');
    }

    if (isset($_POST['ajax'])) {
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;
        $regionf_id = isset($_POST['regionf_id']) ? $_POST['regionf_id'] :  null;
        try {
            $registros = buscar_anexo($conn, $startDate, $endDate, $regionf_id);
            echo json_encode([
                'success' => true,
                'records' => $registros,
                'message' => empty($registros) ? "No se encontraron registros" : ""
            ]);
            exit;
        } catch (Exception $e) {
            handleError("Error al buscar registros: " . $e->getMessage());
        }
    } elseif (isset($_POST['ver'])) {
        $data = json_decode($_POST['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            handleError('Error al decodificar JSON: ' . json_last_error_msg());
        }
        $validado = isset($_POST['validado']) ? filter_var($_POST['validado'], FILTER_VALIDATE_BOOLEAN) : false;
        $result = verData($data, $conn, $validado);
        echo json_encode($result);
        exit;
    } else {
        handleError('Acción no reconocida');
    }
} catch (Exception $e) {
    handleError($e->getMessage(), 500);
}


