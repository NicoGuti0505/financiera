<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$pathParts = explode('\\', dirname(__FILE__));
$levelsUp  = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

session_start();

/* --------- ENTRADA --------- */
$radicado     = isset($_POST['radicado']) ? trim((string)$_POST['radicado']) : '';
$observacion  = isset($_POST['observacion']) ? trim((string)$_POST['observacion']) : '';
$url_drive    = isset($_POST['url_drive']) ? trim((string)$_POST['url_drive']) : '';

if ($radicado === '' || $observacion === '') {
  echo json_encode(['ok'=>false,'msg'=>'Parámetros incompletos.']); exit;
}
if (mb_strlen($observacion) > 500) $observacion = mb_substr($observacion, 0, 500);

/* --------- USUARIO (OBLIGATORIO PARA INSERT) --------- */
// Ajusta esto según tu sesión real
$idUsuario = '';
if (!empty($_SESSION['usuario_id'])) {
  $idUsuario = (string)$_SESSION['usuario_id'];
} elseif (!empty($_SESSION['id_usuario'])) {
  $idUsuario = (string)$_SESSION['id_usuario'];
} elseif (!empty($_SESSION['nombre_usuario'])) {
  $idUsuario = (string)$_SESSION['nombre_usuario'];
} else {
  // fallback: evita NULL (pero ideal es tener el usuario real)
  $idUsuario = 'sistema';
}

/* --------- RESOLVER CARPETA (opcional) --------- */
if ($url_drive === '') {
  $q = sqlsrv_query($conn, "SELECT TOP 1 url_drive FROM solicitudes WHERE radicado = ?", [$radicado]);
  if ($q && ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))) {
    $url_drive = trim((string)$r['url_drive']);
  }
  if ($q) sqlsrv_free_stmt($q);
}
if ($url_drive !== '') {
  $baseDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $url_drive), DIRECTORY_SEPARATOR);
  if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0775, true);
  }
}

/* --------- SUBIDA DE ARCHIVOS (opcional) --------- */
$guardados = [];
if (!empty($_FILES['files']) && is_array($_FILES['files']['name']) && !empty($url_drive)) {
  $count = count($_FILES['files']['name']);
  for ($i=0; $i<$count; $i++) {
    if (!isset($_FILES['files']['error'][$i]) || $_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

    $name = (string)$_FILES['files']['name'][$i];
    $tmp  = (string)$_FILES['files']['tmp_name'][$i];

    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
    $dest = $baseDir . DIRECTORY_SEPARATOR . $safe;

    $k = 1; $pi = pathinfo($safe);
    while (file_exists($dest)) {
      $cand = $pi['filename'] . "_$k" . (isset($pi['extension']) ? ".".$pi['extension'] : "");
      $dest = $baseDir . DIRECTORY_SEPARATOR . $cand;
      $k++;
    }

    if (@move_uploaded_file($tmp, $dest)) { $guardados[] = $dest; }
  }
}

/* --------- TRANSACCIÓN --------- */
if (!sqlsrv_begin_transaction($conn)) {
  echo json_encode(['ok'=>false,'msg'=>'No se pudo iniciar transacción.']); exit;
}

try {
  $affected_evt = 0;
  $modo = '';

  /* 1) Buscar el evento a corregir (Subsanación sin corrección) */
  $sqlFind = "
    SELECT TOP 1 id_solicitudes
    FROM evento_solicitudes
    WHERE radicado = ?
      AND estado_departamental = 'Subsanacion'
      AND (correccion_observacion IS NULL OR correccion_observacion = '')
      AND (fecha_correccion IS NULL)
    ORDER BY id_solicitudes DESC";
  $st = sqlsrv_query($conn, $sqlFind, [$radicado]);
  if ($st === false) throw new Exception('Error buscando evento: '.print_r(sqlsrv_errors(), true));
  $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($st);

  $targetId = $row['id_solicitudes'] ?? null;

  /* 2) Si no hubo, intenta el último evento evaluativo sin corrección */
  if ($targetId === null) {
    $sqlFind2 = "
      SELECT TOP 1 id_solicitudes
      FROM evento_solicitudes
      WHERE radicado = ?
        AND (evento IN ('calificacion_departamental','creacion_viaticos','respuesta_objecion'))
        AND (correccion_observacion IS NULL OR correccion_observacion = '')
        AND (fecha_correccion IS NULL)
      ORDER BY id_solicitudes DESC";
    $st2 = sqlsrv_query($conn, $sqlFind2, [$radicado]);
    if ($st2 === false) throw new Exception('Error buscando evento alterno: '.print_r(sqlsrv_errors(), true));
    $row2 = sqlsrv_fetch_array($st2, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($st2);
    $targetId = $row2['id_solicitudes'] ?? null;
  }

  /* 3) UPDATE si hay evento; si no hay, INSERT cumpliendo NOT NULL */
  if ($targetId !== null) {

    $upd = sqlsrv_query(
      $conn,
      "UPDATE evento_solicitudes
         SET correccion_observacion = ?, fecha_correccion = SYSDATETIME()
       WHERE id_solicitudes = ?",
      [$observacion, (int)$targetId]
    );
    if ($upd === false) throw new Exception('Error actualizando evento: '.print_r(sqlsrv_errors(), true));

    $affected_evt = sqlsrv_rows_affected($upd);
    $modo = 'update';

  } else {

    // ✅ INSERT válido según tu tabla:
    // id_usuario NOT NULL, evento NOT NULL, fecha_solicitud NOT NULL
    $ins = sqlsrv_query(
      $conn,
      "INSERT INTO evento_solicitudes
        (radicado, id_usuario, evento, fecha_solicitud, correccion_observacion, fecha_correccion)
       VALUES
        (?, ?, 'correccion', CONVERT(date, GETDATE()), ?, SYSDATETIME())",
      [$radicado, $idUsuario, $observacion]
    );
    if ($ins === false) throw new Exception('Error insertando corrección: '.print_r(sqlsrv_errors(), true));

    $affected_evt = sqlsrv_rows_affected($ins);
    $modo = 'insert';
  }

  /* 4) Actualizar proceso en solicitudes */
  $updProc = sqlsrv_query(
    $conn,
    "UPDATE solicitudes SET proceso = 'proceso_corregido' WHERE radicado = ?",
    [$radicado]
  );
  if ($updProc === false) throw new Exception('Error actualizando solicitudes: '.print_r(sqlsrv_errors(), true));
  $affected_proc = sqlsrv_rows_affected($updProc);

  /* 5) Commit */
  if (!sqlsrv_commit($conn)) throw new Exception('No se pudo confirmar la transacción.');

  echo json_encode([
    'ok' => true,
    'msg' => 'Corrección guardada exitosamente.',
    'files' => $guardados,
    'detalle' => [
      'modo' => $modo,
      'eventos_afectados' => $affected_evt,
      'solicitudes_actualizadas' => $affected_proc,
      'id_usuario' => $idUsuario
    ]
  ]);
  exit;

} catch (Exception $e) {
  sqlsrv_rollback($conn);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
  exit;
}
