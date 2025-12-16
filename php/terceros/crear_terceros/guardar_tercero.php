<?php
// Desactivar la salida de errores al navegador
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Asegurarse de que no haya salida antes de este punto
ob_start();

header('Content-Type: application/json');

session_start();

// Obtener el directorio del archivo actual y dividir la ruta en partes
$pathParts = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));

// Verificar si el directorio 'php' existe en la ruta
$phpIndex = array_search('php', $pathParts);
if ($phpIndex === false) {
    echo json_encode(['success' => false, 'message' => "No se encontró el directorio 'php' en la ruta"]);
    exit();
}

// Calcular cuántos niveles subir para encontrar 'config.php'
$levelsUp = count($pathParts) - $phpIndex;
require_once str_repeat('../', $levelsUp) . 'config.php';

// Verificar conexión a la base de datos
if ($conn === false) {
    echo json_encode(['success' => false, 'message' => 'Conexión a la base de datos fallida']);
    exit();
}

// Verificar autenticación
if (!isset($_SESSION['usuario_autenticado'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Función para sanitizar los datos de entrada
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Función para registrar errores
function logError($message, $details = null) {
    error_log("Error en guardar_tercero: " . $message . ($details ? " - Detalles: " . print_r($details, true) : ""));
}

// Verificar si se recibieron datos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método no permitido
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    // Validar que los campos requeridos estén presentes
    $required_fields = ['identificacion', 'tipo_documento_id', 'nombre_nit', 'direccion', 'telefono', 'regionf_id'];
    $missing_fields = array_filter($required_fields, fn($field) => empty($_POST[$field]));
    if (!empty($missing_fields)) {
        throw new Exception("Faltan los siguientes campos: " . implode(', ', $missing_fields));
    }

    // Recoger y sanitizar los datos del formulario
    $id = $_POST['id'] ?? null;
    $tipo_contribuyente_id = $_POST['tipo_contribuyente_id'] ?? null;
    $tipo_documento_id = $_POST['tipo_documento_id'] ?? null;
    $banco_id = $_POST['banco_id'] ?? null;
    $ciiu_id = $_POST['ciiu_id'] ?? null;
    $tipo_cuenta_id = $_POST['tipo_cuenta_id'] ?? null;
    $municipio_id = $_POST['municipio_id'] ?? null;
    $identificacion = sanitizeInput($_POST['identificacion']);
    $nombre_nit = sanitizeInput($_POST['nombre_nit']);
    $segundo_nombre = sanitizeInput($_POST['segundo_nombre'] ?? '');
    $primer_apellido = sanitizeInput($_POST['primer_apellido'] ?? '');
    $segundo_apellido = sanitizeInput($_POST['segundo_apellido'] ?? '');
    $direccion = sanitizeInput($_POST['direccion']);
    $telefono = sanitizeInput($_POST['telefono']);
    $num_cuenta_bancaria = sanitizeInput($_POST['num_cuenta_bancaria'] ?? '');
    $retencion = sanitizeInput($_POST['retencion'] ?? '');
    $regionf_id = sanitizeInput($_POST['regionf_id'] ?? null);

    // Verificar si el tercero ya existe (para actualización)
    $check_sql = "SELECT id FROM tercero WHERE identificacion = ?";
    $check_stmt = sqlsrv_query($conn, $check_sql, [$identificacion]);

    if ($check_stmt === false) {
        throw new Exception("Error al verificar existencia del tercero: " . print_r(sqlsrv_errors(), true));
    }

    $exists = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);

    if ($exists) {
        // Actualizar registro existente
        $sql = "UPDATE tercero SET 
                    tipo_contribuyente_id = ?, 
                    tipo_documento_id = ?, 
                    banco_id = ?, 
                    ciiu_id = ?, 
                    tipo_cuenta_id = ?, 
                    municipio_id = ?, 
                    nombre_nit = ?, 
                    segundo_nombre = ?, 
                    primer_apellido = ?, 
                    segundo_apellido = ?, 
                    direccion = ?, 
                    telefono = ?, 
                    num_cuenta_bancaria = ?, 
                    retencion = ?, 
                    regionf_id = ?
                WHERE identificacion = ?";
        $params = [
            $tipo_contribuyente_id, $tipo_documento_id, $banco_id, $ciiu_id,
            $tipo_cuenta_id, $municipio_id, $nombre_nit, $segundo_nombre,
            $primer_apellido, $segundo_apellido, $direccion, $telefono,
            $num_cuenta_bancaria, $retencion, $regionf_id, $identificacion
        ];
    } else {
        // Insertar nuevo registro
        $sql = "INSERT INTO tercero (id, tipo_contribuyente_id, tipo_documento_id, banco_id, ciiu_id, tipo_cuenta_id, 
                municipio_id, identificacion, nombre_nit, segundo_nombre, primer_apellido, segundo_apellido, direccion, 
                telefono, num_cuenta_bancaria, retencion, regionf_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $id, $tipo_contribuyente_id, $tipo_documento_id, $banco_id, $ciiu_id,
            $tipo_cuenta_id, $municipio_id, $identificacion, $nombre_nit, $segundo_nombre,
            $primer_apellido, $segundo_apellido, $direccion, $telefono, $num_cuenta_bancaria,
            $retencion, $regionf_id
        ];
    }

    // Ejecutar la consulta
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception('Error en la consulta SQL: ' . print_r(sqlsrv_errors(), true));
    }

    // Limpiar el buffer de salida
    ob_clean();

    // Enviar respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Datos guardados correctamente',
        'operation' => $exists ? 'update' : 'insert'
    ]);

} catch (Exception $e) {
    // Limpiar el buffer de salida
    ob_clean();

    // Registrar el error
    logError($e->getMessage());

    // Enviar respuesta de error
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar los datos: ' . $e->getMessage()
    ]);
}

exit;
?>
