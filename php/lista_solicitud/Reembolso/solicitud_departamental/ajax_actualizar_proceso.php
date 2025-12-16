<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_identificacion = $_POST['numero_identificacion'] ?? '';
    $nuevo_proceso = $_POST['proceso'] ?? '';

    if (empty($numero_identificacion) || empty($nuevo_proceso)) {
        echo json_encode(["status" => "error", "message" => "⚠ Datos inválidos"]);
        exit;
    }

    // Verificar conexión a la base de datos
    if (!$conn) {
        echo json_encode(["status" => "error", "message" => "❌ Error de conexión a la base de datos"]);
        exit;
    }

    // Buscar radicado asociado a la identificación
    $radicado_sql = "SELECT e.radicado FROM solicitudes s 
                     JOIN evento_solicitudes e ON s.radicado = e.radicado 
                     WHERE s.numero_identificacion = ?";
    $radicado_stmt = sqlsrv_query($conn, $radicado_sql, [$numero_identificacion]);

    if (!$radicado_stmt) {
        echo json_encode(["status" => "error", "message" => "❌ Error en la consulta: " . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    if ($row = sqlsrv_fetch_array($radicado_stmt, SQLSRV_FETCH_ASSOC)) {
        $radicado = $row['radicado'];

        // Actualizar proceso en la tabla solicitudes
        $update_solicitudes_sql = "UPDATE solicitudes SET apr_departamental = ? WHERE numero_identificacion = ?";
        $update_stmt = sqlsrv_query($conn, $update_solicitudes_sql, [$nuevo_proceso, $numero_identificacion]);

        if (!$update_stmt) {
            echo json_encode(["status" => "error", "message" => "❌ Error al actualizar: " . print_r(sqlsrv_errors(), true)]);
        } else {
            echo json_encode(["status" => "success", "message" => "✅ Proceso actualizado exitosamente"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "⚠ No se encontró el radicado"]);
    }

    sqlsrv_free_stmt($radicado_stmt);
    sqlsrv_close($conn);
} else {
    echo json_encode(["status" => "error", "message" => "❌ Método no permitido"]);
}
?>
