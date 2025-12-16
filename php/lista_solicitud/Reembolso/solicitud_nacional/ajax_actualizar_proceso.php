<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

require_once str_repeat('../', $levelsUp) . 'config.php';

$response = ["status" => "error", "message" => "❌ Datos incompletos"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_identificacion = $_POST['numero_identificacion'] ?? '';
    $nuevo_proceso = $_POST['proceso'] ?? '';
    $observacion = $_POST['observacion'] ?? null;

    if (!empty($numero_identificacion) && !empty($nuevo_proceso)) {
        $radicado_sql = "SELECT e.radicado, e.estado_proceso, e.id_usuario 
                         FROM solicitudes s 
                         JOIN evento_solicitudes e ON s.radicado = e.radicado 
                         WHERE s.numero_identificacion = ?";
        $radicado_stmt = sqlsrv_query($conn, $radicado_sql, [$numero_identificacion]);

        if ($radicado_stmt === false) {
            $response["message"] = "⚠ Error en la consulta SQL";
            echo json_encode($response);
            exit;
        }

        if ($row = sqlsrv_fetch_array($radicado_stmt, SQLSRV_FETCH_ASSOC)) {
            $radicado = $row['radicado'];
            $estado_proceso = $row['estado_proceso'];
            $id_usuario = $row['id_usuario'];

            if (is_null($estado_proceso)) {
                $update_sql = "UPDATE evento_solicitudes 
                               SET estado_proceso = ?, fecha_estado = GETDATE(), observacion = ? 
                               WHERE radicado = ?";
                sqlsrv_query($conn, $update_sql, [$nuevo_proceso, $observacion, $radicado]);
            } else {
                $insert_sql = "INSERT INTO evento_solicitudes (radicado, id_usuario, evento, estado_proceso, fecha_estado, fecha_solicitud, observacion) 
                               VALUES (?, ?, 'actualizacion_Reembolso', ?, GETDATE(), GETDATE(), ?)";
                sqlsrv_query($conn, $insert_sql, [$radicado, $id_usuario, $nuevo_proceso, $observacion]);
            }

            if ($nuevo_proceso === 'Aprobado') {
                $estado_pago = 'Inactivo';
            } else {
                $estado_pago = 'activado'; 
            }
            
            $update_solicitudes_sql = "UPDATE solicitudes SET proceso = ?, estado_pago = ? WHERE numero_identificacion = ?";
            sqlsrv_query($conn, $update_solicitudes_sql, [$nuevo_proceso, $estado_pago, $numero_identificacion]);
            

            $response = ["status" => "success", "message" => "✅ Actualizado correctamente"];
        } else {
            $response["message"] = "⚠ Radicado no encontrado";
        }

        sqlsrv_free_stmt($radicado_stmt);
    }
}

sqlsrv_close($conn);
echo json_encode($response);
?>