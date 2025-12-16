<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();
// Rutas/config
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp  = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';
$usuario_id = $_SESSION['identificacion_usuario'] ?? null;

// Normaliza
$usuario_id = $usuario_id !== null ? trim((string)$usuario_id) : '';

if ($usuario_id === '' || $usuario_id === '0') {
  http_response_code(401);
  echo json_encode([
    "status"  => "error",
    "message" => "Sesión inválida: no se encontró el usuario autenticado. Inicia sesión nuevamente."
  ]);
  exit;
}

/* ==== Entrada ==== */
$raw    = file_get_contents('php://input');
$asJson = json_decode($raw, true);
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
if (!$isPost && !$asJson) { echo json_encode(["status"=>"error","message"=>"❌ Método no permitido"]); exit; }

$rad_via       = trim($asJson['radicado_via'] ?? $asJson['rad_via'] ?? ($_POST['rad_via'] ?? ''));
$nuevo_proceso = trim($asJson['estado']       ?? $asJson['proceso']  ?? ($_POST['proceso'] ?? ''));
$observacion   = trim($asJson['observacion']  ?? ($_POST['observacion'] ?? ''));

$motivoInput = $asJson['motivo'] ?? ($_POST['motivo'] ?? []);
if (is_string($motivoInput))      $motivos = array_filter(array_map('trim', explode(',', $motivoInput)));
elseif (is_array($motivoInput))   $motivos = array_values(array_filter(array_map('trim', $motivoInput)));
else                              $motivos = [];

/* ==== Validaciones ==== */
if ($rad_via === '' || $nuevo_proceso === '') {
  echo json_encode(["status"=>"error","message"=>"⚠ Datos inválidos"]); exit;
}
if (in_array($nuevo_proceso, ['Aprobado','Rechazado'], true) && $observacion === '') {
  echo json_encode(["status"=>"error","message"=>"⚠ La observación es obligatoria para Aprobado o Rechazado"]); exit;
}
if (mb_strlen($observacion) > 500) $observacion = mb_substr($observacion, 0, 500);
if ($nuevo_proceso === 'Rechazado' && count($motivos) === 0) {
  echo json_encode(["status"=>"error","message"=>"⚠ Debe seleccionar al menos un motivo para Rechazado"]); exit;
}

/* 0) Obtener radicado y estado actual */
$sqlRad = "SELECT s.radicado, s.apr_departamental FROM solicitudes s WHERE s.rad_via = ?";
$stRad  = sqlsrv_query($conn, $sqlRad, [$rad_via]);
if (!$stRad) { echo json_encode(["status"=>"error","message"=>"❌ Error consultando radicado","err"=>sqlsrv_errors()]); exit; }
$rowRad = sqlsrv_fetch_array($stRad, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stRad);
if (!$rowRad) { echo json_encode(["status"=>"error","message"=>"⚠ No se encontró el radicado asociado"]); exit; }

$radicado         = $rowRad['radicado'];
$apr_actual       = trim((string)($rowRad['apr_departamental'] ?? ''));

/* normalizar comparador de “Objetado” */
$venia_objetado = (mb_strtolower($apr_actual,'UTF-8') === 'objetado');

/* --- Transacción --- */
if (!sqlsrv_begin_transaction($conn)) { echo json_encode(["status"=>"error","message"=>"❌ No se pudo iniciar transacción"]); exit; }

/* 1) evento base (por si aplica) */
$sqlTarget = "SELECT TOP 1 id_solicitudes
              FROM evento_solicitudes
              WHERE radicado = ? AND evento = 'creacion_viaticos'
              ORDER BY id_solicitudes ASC";
$stTarget = sqlsrv_query($conn, $sqlTarget, [$radicado]);
if ($stTarget === false) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error buscando evento base","err"=>sqlsrv_errors()]); exit; }
$target   = sqlsrv_fetch_array($stTarget, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stTarget);
$targetId = $target ? (int)$target['id_solicitudes'] : null;

/* helper: cuando rad_via trae punto, actualizamos por radicado */
$usar_radicado = (strpos($rad_via, '.') !== false);

/* 2) Caso especial: venía Objetado y llega Rechazado o Aprobado */
if ($venia_objetado && in_array($nuevo_proceso, ['Rechazado','Aprobado'], true)) {

  // a) Insertar respuesta_objecion
  $sqlInsertResp = "
    INSERT INTO evento_solicitudes
      (radicado, id_usuario, evento, fecha_solicitud,
       estado_proceso, fecha_estado, observacion,
       fecha_departamental, estado_departamental, observacion_departamental)
    SELECT
      L.radicado, ?, 'respuesta_objecion', SYSDATETIME(),
      L.estado_proceso, L.fecha_estado, L.observacion,
      SYSDATETIME(), ?, ?
    FROM (
      SELECT TOP 1 radicado, id_usuario, evento, fecha_solicitud, estado_proceso, fecha_estado, observacion
      FROM evento_solicitudes
      WHERE radicado = ?
      ORDER BY id_solicitudes DESC
    ) AS L";
  $okInsResp = sqlsrv_query($conn, $sqlInsertResp, [$usuario_id, $nuevo_proceso, $observacion, $radicado]);
  if ($okInsResp === false) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error insertando respuesta_objecion","err"=>sqlsrv_errors()]); exit; }

  // b) UPDATE visible -> por radicado si rad_via trae punto
  $proceso_visible = ($nuevo_proceso === 'Rechazado') ? 'Objeción Rechazada' : 'Aprobado';
  if ($usar_radicado) {
    $stUpd = sqlsrv_query($conn, "UPDATE solicitudes SET apr_departamental = ? WHERE radicado = ?", [$proceso_visible, $radicado]);
  } else {
    $stUpd = sqlsrv_query($conn, "UPDATE solicitudes SET apr_departamental = ? WHERE rad_via = ?", [$proceso_visible, $rad_via]);
  }
  if ($stUpd === false) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error actualizando solicitud (objeción resuelta)","err"=>sqlsrv_errors()]); exit; }

  // c) Motivos si terminó Rechazado
  if ($nuevo_proceso === 'Rechazado' && !empty($motivos)) {
    $sqlInsMot = "INSERT INTO dbo.motivos_viaticos (radicado, motivo, procedencia, calificacion)
                  VALUES (?, ?, 'respuesta_objecion', 'Rechazado')";
    foreach ($motivos as $m) {
      if ($m === '') continue;
      if (sqlsrv_query($conn, $sqlInsMot, [$radicado, $m]) === false) {
        sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error insertando motivo de respuesta_objecion","err"=>sqlsrv_errors()]); exit;
      }
    }
  }

  if (!sqlsrv_commit($conn)) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ No se pudo confirmar la transacción"]); exit; }
  echo json_encode([
    "status"          => "success",
    "message"         => "✅ Registrada respuesta a objeción.",
    "proceso_visible" => $proceso_visible
  ]);
  exit;
}

/* 3) Flujo normal */
$rowsUpdBase = 0;
if ($targetId !== null) {
  $sqlUpdBase = "UPDATE evento_solicitudes
                 SET  id_usuario = ?,
                      estado_departamental      = CASE WHEN estado_departamental IS NULL THEN ? ELSE estado_departamental END,
                      fecha_departamental       = CASE WHEN fecha_departamental  IS NULL THEN SYSDATETIME() ELSE fecha_departamental END,
                      observacion_departamental = CASE WHEN observacion_departamental IS NULL THEN ? ELSE observacion_departamental END
                 WHERE id_solicitudes = ?
                   AND estado_departamental IS NULL
                   AND fecha_departamental  IS NULL
                   AND observacion_departamental IS NULL";
  $stUpdBase = sqlsrv_query($conn, $sqlUpdBase, [$usuario_id, $nuevo_proceso, $observacion, $targetId]);
  if ($stUpdBase === false) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error actualizando evento base","err"=>sqlsrv_errors()]); exit; }
  $rowsUpdBase = sqlsrv_rows_affected($stUpdBase);
}

/* 3b) Insertar calificacion_departamental si no se pudo actualizar base */
if ((int)$rowsUpdBase === 0) {
  $sqlInsEvt = "
    INSERT INTO evento_solicitudes
      (radicado, id_usuario, evento, fecha_solicitud,
       estado_proceso, fecha_estado, observacion,
       fecha_departamental, estado_departamental, observacion_departamental)
    SELECT
      L.radicado, ?, 'calificacion_departamental', SYSDATETIME(),
      L.estado_proceso, L.fecha_estado, L.observacion,
      SYSDATETIME(), ?, ?
    FROM (
      SELECT TOP 1 radicado, id_usuario, evento, fecha_solicitud, estado_proceso, fecha_estado, observacion
      FROM evento_solicitudes
      WHERE radicado = ?
      ORDER BY id_solicitudes DESC
    ) AS L";
  $okInsEvt = sqlsrv_query($conn, $sqlInsEvt, [$usuario_id, $nuevo_proceso, $observacion, $radicado]);
  if ($okInsEvt === false) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error insertando evento departamental","err"=>sqlsrv_errors()]); exit; }
}

/* 4) UPDATE visible (flujo normal) — misma regla: por radicado si trae punto */
$proceso_visible = $nuevo_proceso;
if ($usar_radicado) {
  $stUpd2 = sqlsrv_query($conn, "UPDATE solicitudes SET apr_departamental = ? WHERE radicado = ?", [$proceso_visible, $radicado]);
} else {
  $stUpd2 = sqlsrv_query($conn, "UPDATE solicitudes SET apr_departamental = ? WHERE rad_via = ?", [$proceso_visible, $rad_via]);
}
if ($stUpd2 === false) {
  sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error actualizando solicitud","err"=>sqlsrv_errors()]); exit;
}

/* 5) Motivos si Rechazado */
if ($nuevo_proceso === 'Rechazado') {
  sqlsrv_query($conn, "DELETE FROM dbo.motivos_viaticos WHERE radicado = ?", [$radicado]);
  if (!empty($motivos)) {
    $sqlInsMot = "INSERT INTO dbo.motivos_viaticos (radicado, motivo, procedencia, calificacion)
                  VALUES (?, ?, 'departamental', 'Rechazado')";
    foreach ($motivos as $m) {
      if ($m === '') continue;
      if (sqlsrv_query($conn, $sqlInsMot, [$radicado, $m]) === false) {
        sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ Error guardando motivos","err"=>sqlsrv_errors()]); exit;
      }
    }
  }
}

/* Commit */
if (!sqlsrv_commit($conn)) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"❌ No se pudo confirmar la transacción"]); exit; }

/* Respuesta */
echo json_encode([
  "status"          => "success",
  "message"         => ((int)$rowsUpdBase > 0)
    ? "✅ Se actualizó el evento base 'creacion_viaticos' y la solicitud."
    : "✅ Se registró evaluación departamental y se actualizó la solicitud.",
  "proceso_visible" => $proceso_visible
]);
