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
/* Si NO hay usuario real => error */
if (!$usuario_id) {
  http_response_code(401);
  echo json_encode([
    "status" => "error",
    "message" => "Sesión inválida: no se encontró id_usuario en sesión. Vuelve a iniciar sesión."
  ]);
  exit;
}


/* ===== Entrada ===== */
$raw    = file_get_contents('php://input');
$asJson = json_decode($raw, true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$asJson) {
  echo json_encode(["status"=>"error","message"=>"Método no permitido"]); exit;
}

$rad_via     = trim($asJson['radicado_via'] ?? $asJson['rad_via'] ?? ($_POST['rad_via'] ?? ''));
$estadoNuevo = trim($asJson['estado']       ?? $asJson['proceso']  ?? ($_POST['proceso'] ?? '')); // Revision/Aprobado/Rechazado/Subsanacion
$observacion = trim($asJson['observacion']  ?? ($_POST['observacion'] ?? ''));

$motivoInput = $asJson['motivo'] ?? ($_POST['motivo'] ?? []);
if (is_string($motivoInput))      $motivos = array_filter(array_map('trim', explode(',', $motivoInput)));
elseif (is_array($motivoInput))   $motivos = array_values(array_filter(array_map('trim', $motivoInput)));
else                              $motivos = [];

/* ===== Validaciones ===== */
if ($rad_via === '' || $estadoNuevo === '') {
  echo json_encode(["status"=>"error","message"=>"Datos inválidos"]); exit;
}
if (in_array($estadoNuevo, ['Aprobado','Rechazado','Subsanacion'], true) && $observacion === '') {
  echo json_encode(["status"=>"error","message"=>"La observación es obligatoria."]); exit;
}
if (in_array($estadoNuevo, ['Rechazado','Subsanacion'], true) && count($motivos) === 0) {
  echo json_encode(["status"=>"error","message"=>"Selecciona al menos un motivo."]); exit;
}
if (mb_strlen($observacion) > 500) $observacion = mb_substr($observacion, 0, 500);

/* ===== Obtener radicado y estado actuales ===== */
$stRad = sqlsrv_query(
  $conn,
  "SELECT s.radicado, s.proceso, s.apr_departamental FROM solicitudes s WHERE s.rad_via = ?",
  [$rad_via]
);
if (!$stRad) { echo json_encode(["status"=>"error","message"=>"Error consultando radicado","err"=>sqlsrv_errors()]); exit; }
$rw = sqlsrv_fetch_array($stRad, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stRad);
if (!$rw) { echo json_encode(["status"=>"error","message"=>"No se encontró el radicado asociado"]); exit; }

$radicado   = $rw['radicado'];
$procesoAnt = mb_strtolower((string)($rw['proceso'] ?? ''), 'UTF-8');
$aprAnt     = mb_strtolower((string)($rw['apr_departamental'] ?? ''), 'UTF-8');

/* “Venía objetado” si proceso='objecion' o apr_departamental='objetado' */
$veniaObjetado = ($procesoAnt === 'objecion' || $aprAnt === 'objetado');

/* Para legalización (rad_via con punto) actualizamos por radicado */
$usar_radicado = (strpos($rad_via, '.') !== false);

/* ===== Helpers ===== */
$setVisibleProceso = function(string $valor) use ($conn, $usar_radicado, $radicado, $rad_via) {
  $sql = $usar_radicado
    ? "UPDATE solicitudes SET proceso = ? WHERE radicado = ?"
    : "UPDATE solicitudes SET proceso = ? WHERE rad_via = ?";
  $params = $usar_radicado ? [$valor, $radicado] : [$valor, $rad_via];
  $ok = sqlsrv_query($conn, $sql, $params);
  return $ok !== false;
};

/**
 * Actualiza el PRIMER evento 'creacion_viaticos' cuya TERNA (estado_proceso, fecha_estado, observacion)
 * esté completamente NULL. Si no hay, inserta un nuevo evento con el nombre indicado.
 *
 * @return array [ok(bool), mode('update'|'insert')]
 */
function upsertEventoTerna($conn, $radicado, $eventoInsert, $usuario_id, $estadoNuevo, $observacion) {
  // 1) ¿Existe primer creacion_viaticos con los 3 campos NULL?
  $q = "SELECT TOP 1 id_solicitudes
        FROM evento_solicitudes
        WHERE radicado = ?
          AND evento = 'creacion_viaticos'
          AND estado_proceso IS NULL
          AND fecha_estado  IS NULL
          AND (observacion IS NULL OR LTRIM(RTRIM(observacion)) = '')
        ORDER BY id_solicitudes ASC";
  $st = sqlsrv_query($conn, $q, [$radicado]);
  if ($st === false) return [false, 'error'];
  $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($st);

  if ($row) {
    // 2) Actualizar esa TERNA (no crear nuevo)
    $upd = "UPDATE evento_solicitudes
            SET estado_proceso = ?,
                fecha_estado   = SYSDATETIME(),
                observacion    = ?,
                id_usuario     = ISNULL(id_usuario, ?)
            WHERE id_solicitudes = ?";
    $ok = sqlsrv_query($conn, $upd, [$estadoNuevo, $observacion, $usuario_id, $row['id_solicitudes']]);
    if ($ok === false) return [false, 'error'];
    return [true, 'update'];
  }

  // 3) Si no existe, insertar evento nuevo con la TERNA
  $ins = "INSERT INTO evento_solicitudes
            (radicado, id_usuario, evento, fecha_solicitud,
             estado_proceso, fecha_estado, observacion)
          VALUES
            (?, ?, ?, SYSDATETIME(), ?, SYSDATETIME(), ?)";
  $ok = sqlsrv_query($conn, $ins, [$radicado, $usuario_id, $eventoInsert, $estadoNuevo, $observacion]);
  if ($ok === false) return [false, 'error'];
  return [true, 'insert'];
}

/* Calificación de motivos según estado */
$califMotivo = null;
if ($estadoNuevo === 'Rechazado')   $califMotivo = 'Rechazado';
if ($estadoNuevo === 'Subsanacion') $califMotivo = 'Subsanacion';

/* ===== Transacción ===== */
if (!sqlsrv_begin_transaction($conn)) {
  echo json_encode(["status"=>"error","message"=>"No se pudo iniciar transacción"]); exit;
}

/* ===== Flujo OBJECIÓN ===== */
if ($veniaObjetado) {
  // Actualizar primer creacion_viaticos vacío; si no hay, insertar 'respuesta_objecion'
  [$okUpsert, $mode] = upsertEventoTerna($conn, $radicado, 'respuesta_objecion', $usuario_id, $estadoNuevo, $observacion);
  if (!$okUpsert) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"Error registrando respuesta de objeción","err"=>sqlsrv_errors()]); exit; }

  // Visible
  if (!$setVisibleProceso($estadoNuevo)) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"Error actualizando proceso visible"]); exit; }

  // Motivos (procedencia respuesta_objecion)
  sqlsrv_query($conn, "DELETE FROM dbo.motivos_viaticos WHERE radicado = ? AND procedencia = 'respuesta_objecion'", [$radicado]);
  if ($califMotivo !== null && !empty($motivos)) {
    $sqlMot = "INSERT INTO dbo.motivos_viaticos (radicado, motivo, procedencia, calificacion)
               VALUES (?, ?, 'respuesta_objecion', ?)";
    foreach ($motivos as $m) {
      if ($m==='') continue;
      if (sqlsrv_query($conn, $sqlMot, [$radicado, $m, $califMotivo]) === false) {
        sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"Error guardando motivos (objeción)","err"=>sqlsrv_errors()]); exit;
      }
    }
  } else {
    // Si no aplica calificación, limpiar
    sqlsrv_query($conn, "DELETE FROM dbo.motivos_viaticos WHERE radicado = ? AND procedencia = 'respuesta_objecion'", [$radicado]);
  }

  if (!sqlsrv_commit($conn)) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"No se pudo confirmar la transacción"]); exit; }
  echo json_encode([
    "status" => "success",
    "message" => $mode === 'update'
      ? "Se actualizó el primer evento vacío (creación) con la respuesta a objeción."
      : "Se insertó 'respuesta_objecion'.",
    "proceso_visible" => $estadoNuevo
  ]);
  exit;
}

/* ===== Flujo DEPARTAMENTAL (normal) ===== */
// Actualizar primer creacion_viaticos vacío; si no hay, insertar 'calificacion_departamental'
[$okUpsert, $mode] = upsertEventoTerna($conn, $radicado, 'calificacion_nacional', $usuario_id, $estadoNuevo, $observacion);
if (!$okUpsert) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"Error registrando calificación departamental","err"=>sqlsrv_errors()]); exit; }

// Visible
if (!$setVisibleProceso($estadoNuevo)) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"Error actualizando proceso visible"]); exit; }

// Motivos (procedencia departamental)
sqlsrv_query($conn, "DELETE FROM dbo.motivos_viaticos WHERE radicado = ? AND procedencia = 'departamental'", [$radicado]);
if ($califMotivo !== null && !empty($motivos)) {
  $sqlMot = "INSERT INTO dbo.motivos_viaticos (radicado, motivo, procedencia, calificacion)
             VALUES (?, ?, 'nacional', ?)";
  foreach ($motivos as $m) {
    if ($m==='') continue;
    if (sqlsrv_query($conn, $sqlMot, [$radicado, $m, $califMotivo]) === false) {
      sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"Error guardando motivos (departamental)","err"=>sqlsrv_errors()]); exit;
    }
  }
}

if (!sqlsrv_commit($conn)) { sqlsrv_rollback($conn); echo json_encode(["status"=>"error","message"=>"No se pudo confirmar la transacción"]); exit; }

echo json_encode([
  "status"          => "success",
  "message"         => $mode === 'update'
    ? "Se actualizó el primer evento vacío (creación) con la calificación departamental."
    : "Se insertó 'calificacion_nacional'.",
  "proceso_visible" => $estadoNuevo
]);
