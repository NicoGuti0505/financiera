<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

session_start();

$raw = file_get_contents('php://input');
$inp = json_decode($raw, true);

$radicado   = isset($inp['radicado'])   ? trim((string)$inp['radicado'])   : '';
$comentario = isset($inp['comentario']) ? trim((string)$inp['comentario']) : '';

if ($radicado === '' || $comentario === '') {
  echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']);
  exit;
}

// ✅ toma el usuario desde la sesión
$usuario_id = $_SESSION['identificacion_usuario'] ?? '';
$usuario_id = trim((string)$usuario_id);

// ✅ unifica el nombre que usa todo el script
$currentUserId = $usuario_id;

if ($currentUserId === '' || $currentUserId === '0') {
  http_response_code(401);
  echo json_encode([
    'ok'      => false,
    'msg'     => 'Sesión inválida: no se encontró el usuario. Inicia sesión de nuevo.',
    'session' => array_keys($_SESSION) // 👈 opcional para depurar (puedes quitarlo luego)
  ]);
  exit;
}



if (!$currentUserId) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'Sesión inválida: no se encontró el usuario. Inicia sesión de nuevo.']);
  exit;
}

try {
  if (!sqlsrv_begin_transaction($conn)) {
    throw new Exception('No se pudo iniciar la transacción.');
  }

  // 0) Saber dónde está el RECHAZADO actualmente en solicitudes
  $sqlStat = "
    SELECT apr_departamental, proceso
    FROM gestion_terceros.dbo.solicitudes
    WHERE CAST(radicado AS VARCHAR(50)) = ?
  ";
  $stStat = sqlsrv_query($conn, $sqlStat, [$radicado]);
  if ($stStat === false) {
    throw new Exception('Error leyendo estado de la solicitud: '.print_r(sqlsrv_errors(), true));
  }
  $sol = sqlsrv_fetch_array($stStat, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($stStat);

  if (!$sol) {
    throw new Exception('No existe la solicitud para el radicado indicado.');
  }

  $aprDep  = strtoupper(trim((string)($sol['apr_departamental'] ?? '')));
  $procNat = strtoupper(trim((string)($sol['proceso'] ?? '')));

  $isRechDep = ($aprDep  === 'RECHAZADO');
  $isRechNat = ($procNat === 'RECHAZADO');

  // 1) Último evento
  $sqlLast = "
    SELECT TOP 1
      id_solicitudes, radicado, id_usuario, evento,
      fecha_solicitud, estado_proceso, fecha_estado,
      observacion, fecha_departamental, estado_departamental, observacion_departamental
    FROM gestion_terceros.dbo.evento_solicitudes
    WHERE CAST(radicado AS VARCHAR(50)) = ?
    ORDER BY 
      CASE WHEN fecha_estado IS NULL THEN 1 ELSE 0 END,
      fecha_estado DESC,
      id_solicitudes DESC
  ";
  $stLast = sqlsrv_query($conn, $sqlLast, [$radicado]);
  if ($stLast === false) {
    throw new Exception('Error consultando último evento: '.print_r(sqlsrv_errors(), true));
  }
  $last = sqlsrv_fetch_array($stLast, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($stLast);

  if (!$last) {
    throw new Exception('No existe evento previo para este radicado.');
  }

  // 2) Insertar evento OBJECION (id_usuario SIEMPRE el de sesión)
  $sqlInsert = "
    INSERT INTO gestion_terceros.dbo.evento_solicitudes
    (
      radicado, id_usuario, evento, fecha_solicitud,
      estado_proceso, fecha_estado,
      observacion, fecha_departamental, estado_departamental, observacion_departamental,
      observacion_objecion, fecha_objecion
    )
    SELECT
      L.radicado,
      ? AS id_usuario,
      'OBJECION' AS evento,
      CONVERT(date, GETDATE()) AS fecha_solicitud,   -- 👈 tu tabla fecha_solicitud es DATE (no datetime)

      CASE WHEN ? = 1 THEN 'Objetado' ELSE L.estado_proceso END AS estado_proceso,
      L.fecha_estado,

      L.observacion,
      L.fecha_departamental,
      CASE WHEN ? = 1 THEN 'Objetado' ELSE L.estado_departamental END AS estado_departamental,
      L.observacion_departamental,

      ? AS observacion_objecion,
      SYSDATETIME() AS fecha_objecion
    FROM (
      SELECT TOP 1
        radicado, id_usuario, evento, fecha_solicitud,
        estado_proceso, fecha_estado,
        observacion, fecha_departamental, estado_departamental, observacion_departamental
      FROM gestion_terceros.dbo.evento_solicitudes
      WHERE CAST(radicado AS VARCHAR(50)) = ?
      ORDER BY 
        CASE WHEN fecha_estado IS NULL THEN 1 ELSE 0 END,
        fecha_estado DESC,
        id_solicitudes DESC
    ) AS L
  ";

  $stIns = sqlsrv_query($conn, $sqlInsert, [
    (string)$currentUserId,
    $isRechNat ? 1 : 0,
    $isRechDep ? 1 : 0,
    $comentario,
    $radicado
  ]);
  if ($stIns === false) {
    throw new Exception('Error insertando evento de objeción: '.print_r(sqlsrv_errors(), true));
  }

  // 3) Update en solicitudes: RECHAZADO -> Objetado
  $sqlUpd = "
    UPDATE gestion_terceros.dbo.solicitudes
    SET
      apr_departamental = CASE WHEN UPPER(LTRIM(RTRIM(apr_departamental))) = 'RECHAZADO' THEN 'Objetado' ELSE apr_departamental END,
      proceso           = CASE WHEN UPPER(LTRIM(RTRIM(proceso)))           = 'RECHAZADO' THEN 'Objetado' ELSE proceso END
    WHERE CAST(radicado AS VARCHAR(50)) = ?
  ";
  $stUpd = sqlsrv_query($conn, $sqlUpd, [$radicado]);
  if ($stUpd === false) {
    throw new Exception('Error actualizando solicitud: '.print_r(sqlsrv_errors(), true));
  }

  if (!sqlsrv_commit($conn)) {
    throw new Exception('No se pudo confirmar la transacción.');
  }

  $scope = ($isRechDep && $isRechNat) ? 'ambos' : ($isRechDep ? 'departamental' : ($isRechNat ? 'nacional' : 'ninguno'));
  echo json_encode(['ok' => true, 'scope' => $scope]);

} catch (Exception $ex) {
  sqlsrv_rollback($conn);
  echo json_encode(['ok' => false, 'msg' => $ex->getMessage()]);
}
