<?php

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

// Desactivar la salida del búfer
ob_start();

// Establecer el encabezado de tipo de contenido a JSON
header('Content-Type: application/json');

// Desactivar la visualización de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Función para manejar errores
function handleError($errno, $errstr, $errfile, $errline) {
    $error = [
        'success' => false,
        'message' => "PHP Error: [$errno] $errstr in $errfile on line $errline"
    ];
    echo json_encode($error);
    exit;
}

// Establecer el manejador de errores
set_error_handler("handleError");

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    $result = verData($conn, $data);
    echo json_encode($result);
} catch (Exception $e) {
    $error = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
    echo json_encode($error);
}

// Limpiar y enviar el búfer de salida
ob_end_flush();

function v_campos($conn, $row, $existingRecord) {
    $descripcion = '';

    $campos = [
        'tipo_contrato_id','cantidad_facturas','fac_hasta','valor_total','observacion','embargo'
    ];

    foreach ($campos as $campo) {
        if ($existingRecord[$campo] != $row[$campo]) {
            if (!empty($descripcion)) {
                $descripcion .= ' | ';
            }
            if (!empty($existingRecord[$campo])){
                if (!empty($row[$campo])){
                    $descripcion .="Cambio $campo de: " . $existingRecord[$campo] . " a " . $row[$campo];
                }else{
                    $descripcion .="Eliminación dato de $campo";
                }
            }else{
                if (!empty($row[$campo])){
                    $descripcion .="Diligenciamiento $campo: " . $row[$campo];
                }
            }

        }
    }

    return [
        'success' => (empty($descripcion)), // Cambia a verdadero si no hay cambios
        'id_validacion_tercero' => $existingRecord['id'],
        'descripcion' => $descripcion
    ];
}

function verData($conn, $data) {
    $existingRecords = [];
    $updatedRecords = [];
    $newRecords = [];

    foreach ($data as $row) {
        // Verificar si ya existe un registro con el mismo tercero_id y fac_desde
        $sql = "SELECT * FROM validacion_terceros WHERE tercero_id = ? AND fac_desde = ?";
        $params = array($row['tercero_id'], $row['fac_desde']);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Error en la consulta SQL: " . print_r(sqlsrv_errors(), true));
        }

        if ($existingRecord = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $val = v_campos($conn, $row, $existingRecord);
            if($val['success']){
                $existingRecords[] = $row;
            }else{
                $row['id_validacion_tercero'] = $val['id_validacion_tercero'];
                $row['descripcion'] = $val['descripcion'];
                $updatedRecords[] = $row;
            }
        } else {
            $newRecords[] = $row;
        }
    }
    if (!empty($existingRecords)) {
        return [
            'success' => false,
            'message' => 'Se encontraron registros existentes. Por favor, verifique la información.',
            'existingRecords' => $existingRecords,
            'updatedRecords' => $updatedRecords,
            'newRecords' => $newRecords
        ];
    }else{
        try {
            return [
                'success' => true,
                'message' => 'Validación exitosa',
                'updatedRecords' => $updatedRecords,
                'newRecords' => $newRecords
            ];
        } catch (Exception $e) {
            error_log("Error al validar: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al procesar los datos: ' . $e->getMessage()
            ];
        }
    }
}
