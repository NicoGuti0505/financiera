<?php

// Deshabilitar la salida de errores PHP al navegador
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Asegurarse de que no haya salida previa
ob_start();

// Establecer headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function handleError($message, $code = 400) {
    ob_clean(); // Limpiar cualquier salida previa
    http_response_code($code);
    error_log("Error en ajax_handler.php: " . $message);
    die(json_encode(['success' => false, 'message' => $message]));
}

//Ruta
$Ruta = dirname(__FILE__);
// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', $Ruta);
// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
require_once 'function.php';

// Asegúrate de que todas las excepciones se capturen y se manejen
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['ajax'])) {
            $nit = $_POST['nit'] ?? '';
            $factura = $_POST['factura'] ?? '';
            $result = ['success' => false, 'message' => 'No se encontró información'];
            
            if ($row = sqlsrv_fetch_array(buscar_anexo($conn, $nit, $factura), SQLSRV_FETCH_ASSOC)) {
                $name = ($row['nombre'] ?? '') . 
                    (!empty($row['segundo_nombre']) ? ' ' . $row['segundo_nombre'] : '') . 
                    (!empty($row['primer_apellido']) ? ' ' . $row['primer_apellido'] : '') . 
                    (!empty($row['segundo_apellido']) ? ' ' . $row['segundo_apellido'] : '');
                $result = [
                    'success' => true,
                    'id_anexo' => $row['anexo_pago_id'] ?? null,
                    'val_ter_id' => $row['validacion_terceros_id'],
                    'id_tercero' => $row['tercero_id'] ?? null,
                    'beneficiario' => $name,
                    'final_factura' => $row['fac_hasta'] ?? null,
                    'valor_total' => $row['valor_total'] ? formatNumber($row['valor_total'], 2) : null,
                    'ubicacion' => $row['municipio_id'] ?? null,
                    'articulo' => $row['id_articulo'] ?? '204',
                    'iva' => $row['iva'] !== null ? formatNumber($row['iva'], 2) . '%' : null,
                    'base_iva' => $row['base_iva'] !== null ? formatNumber($row['base_iva'], 2) : null,
                    'base_excenta' => $row['base_excenta'] !== null ? formatNumber($row['base_excenta'], 2) : null
                ];
            }
            echo json_encode($result);
        } elseif (isset($_POST['ver'])) {
            $data = json_decode($_POST['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
            }
            $validado = isset($_POST['validado']) ? filter_var($_POST['validado'], FILTER_VALIDATE_BOOLEAN) : false;
            error_log("Datos recibidos en ajax_handler.php: " . print_r($data, true));
            error_log("Validado: " . ($validado ? 'true' : 'false'));
            $result = verData($data, $conn, $validado);
            echo json_encode($result);
        } else {
            throw new Exception('Acción no reconocida');
        }
    } else {
        throw new Exception('Método no permitido');
    }
} catch (Exception $e) {
    handleError($e->getMessage(), 500);
}