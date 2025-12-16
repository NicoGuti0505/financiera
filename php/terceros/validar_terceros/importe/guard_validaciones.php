<?php
// Error handling and logging setup
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler("errorHandler");
register_shutdown_function("fatalErrorHandler");

function errorHandler($errno, $errstr, $errfile, $errline) {
    $message = date('Y-m-d H:i:s') . ": Error [$errno] $errstr in $errfile on line $errline\n";
    error_log($message, 3, 'error.log');
}

function fatalErrorHandler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $message = date('Y-m-d H:i:s') . ": Fatal Error [{$error['type']}] {$error['message']} in {$error['file']} on line {$error['line']}\n";
        error_log($message, 3, 'error.log');
    }
}

function log_debug($message) {
    error_log(date('Y-m-d H:i:s') . ": DEBUG - $message\n", 3, 'debug.log');
}

// Start output buffering
ob_start();

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

session_start();

// Main code
try {
    log_debug("Script started");
    
    if (!isset($_SESSION['identificacion_usuario'])) {
        throw new Exception("Usuario no autenticado");
    }
    
    $usuario_id = $_SESSION['identificacion_usuario'];
    log_debug("Usuario ID: $usuario_id");

    $input = file_get_contents('php://input');
    log_debug("Raw input: $input");

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }
    
    log_debug("Decoded data: " . print_r($data, true));

    $newRecords = $data['newRecords'] ?? [];
    $updatedRecords = $data['updatedRecords'] ?? [];
    $fecha_actual = date('Y-m-d H:i:s');

    log_debug("New records count: " . count($newRecords));
    log_debug("Updated records count: " . count($updatedRecords));

    function enviardata($conn, $newRecords, $updatedRecords, $usuario_id) {
        log_debug("enviardata called");
        if (!empty($newRecords)) {
            newData($conn, $newRecords, $usuario_id);
        }
        if (!empty($updatedRecords)) {
            upData($conn, $updatedRecords, $usuario_id);
        }
        return ['success' => true, 'message' => 'Datos guardados exitosamente'];
    }

    function newData($conn, $data, $usuario_id) {
        log_debug("newData called");
        global $fecha_actual;
        if (sqlsrv_begin_transaction($conn) === false) {
            throw new Exception("No se pudo iniciar la transacción.");
        }
        foreach ($data as $row) {
            $newsql = "INSERT INTO validacion_terceros (
            tercero_id, tipo_contrato_id, cantidad_facturas, 
            fac_desde, fac_hasta, valor_total, valor_retener, 
            valor_pago, zese, observacion , embargo  ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)";

            $newparams = [
                $row['tercero_id'],
                intval($row['tipo_contrato_id']),
                $row['cantidad_facturas'],
                $row['fac_desde'],
                $row['fac_hasta'],
                $row['valor_total'],
                $row['valor_retener'],
                $row['valor_pago'],
                $row['zese'],
                $row['observacion'],
                $row['embargo']
            ];
            
            $newstmt = sqlsrv_query($conn, $newsql, $newparams);
            if ($newstmt === false) {
                throw new Exception("Error agregando validacion_terceros: " . print_r(sqlsrv_errors(), true));
            }

            $busqsql = "SELECT id FROM validacion_terceros WHERE tercero_id = ? AND fac_desde = ?";
            $busqparams = array($row['tercero_id'], $row['fac_desde']);
            $busqstmt = sqlsrv_query($conn, $busqsql, $busqparams);

            if ($busqstmt === false) {
                throw new Exception("Error al buscar id_validacion_tercero: " . print_r(sqlsrv_errors(), true));
            }
    
            if (sqlsrv_fetch($busqstmt) === false) {
                throw new Exception("No se pudo obtener el id_validacion_tercero");
            }
    
            $id_validacion_tercero = sqlsrv_get_field($busqstmt, 0);
            
            if ($id_validacion_tercero === false) {
                throw new Exception("Error al obtener el id_validacion_tercero");
            }

            $sql = "INSERT INTO evento_tercero (id_validacion_tercero, id_usuario, evento, fecha, descripcion) VALUES (?, ?, ?, ?, ?)";
            $params = [$id_validacion_tercero, $usuario_id, 'Creación', $fecha_actual, 'Creación'];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                throw new Exception("Error en inserción: " . print_r(sqlsrv_errors(), true));
            }
        }

        if (sqlsrv_commit($conn) === false) {
            throw new Exception("No se pudo realizar el commit de la transacción.");
        }
    }

    function upData($conn, $data, $usuario_id) {
        log_debug("upData called");
        global $fecha_actual;
        foreach ($data as $row) {
            $updateSql = "UPDATE validacion_terceros SET 
            tipo_contrato_id = ?, 
            cantidad_facturas = ?, 
            fac_hasta = ?, 
            valor_total = ?, 
            valor_retener = ?,
            valor_pago = ?,
            zese = ?,
            observacion = ?, 
            embargo = ? 
            WHERE tercero_id = ? AND fac_desde = ?";
            $updateParams = [
                intval($row['tipo_contrato_id']),
                intval(str_replace(',', '', $row['cantidad_facturas'])),
                $row['fac_hasta'],
                floatval($row['valor_total']),  // Asegurar que es numérico
                floatval($row['valor_retener']),
                floatval($row['valor_pago']),
                $row['zese'],
                $row['observacion'],
                $row['embargo'], // <-- Estaba en la posición incorrecta
                $row['tercero_id'], // <-- Ahora en la posición correcta
                $row['fac_desde'] // <-- Ahora en la posición correcta
            ];

            $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
            if ($updateStmt === false) {
                throw new Exception("Error updating validacion_terceros: " . print_r(sqlsrv_errors(), true));
            }

            $sql = "INSERT INTO evento_tercero (id_validacion_tercero, id_usuario, evento, fecha, descripcion) VALUES (?, ?, ?, ?, ?)";
            $params = [$row['id_validacion_tercero'], $usuario_id, 'Actualización', $fecha_actual, $row['descripcion']];
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Error inserting into evento_tercero: " . print_r(sqlsrv_errors(), true));
            }
        }
    }

    $result = enviardata($conn, $newRecords, $updatedRecords, $usuario_id);
    log_debug("enviardata result: " . print_r($result, true));

    // Clear the output buffer and set the response header
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($result);

} catch (Exception $e) {
    log_debug("Exception caught: " . $e->getMessage());
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al guardar las validaciones: ' . $e->getMessage()]);
}

// End output buffering and flush
ob_end_flush();