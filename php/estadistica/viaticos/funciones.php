<?php
// funciones.php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (!function_exists('buscarEnProyecto')) {
  function buscarEnProyecto(string $archivo, int $niveles = 7): string {
    $dir = __DIR__;
    for ($i = 0; $i < $niveles; $i++) {
      $ruta = $dir . DIRECTORY_SEPARATOR . $archivo;
      if (file_exists($ruta)) return $ruta;
      $dir = dirname($dir);
    }
    throw new RuntimeException("No se encontró {$archivo} buscando en el proyecto.");
  }
}

require_once buscarEnProyecto('config.php'); // debe definir $conn (sqlsrv)

/** ---------------- Sesión / Seguridad ---------------- */
function verificar_sesion(): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (!isset($_SESSION['tipo_usuario_id']) || !in_array((int)$_SESSION['tipo_usuario_id'], [1,2,5,6], true)) {
    header('Location: ../../menu.php');
    exit;
  }
}

/**
 * Obtener reembolsos (radicación única) con comprobante y fecha de pago.
 * Filtros:
 *  - $identificacion: número de identificación exacto (en solicitudes.numero_identificacion)
 *  - $proceso: filtra por estado departamental (e.estado_proceso)
 *  - $rad_via: radicado exacto
 *  - $pago: '', 'pagado' (comprobante NOT NULL) o 'sin_pago' (comprobante IS NULL)
 *
 * Devuelve: arreglo de filas con columnas:
 *  rad_via, fecha_solicitud, numero_identificacion, apr_departamental,
 *  proceso_nacional, fecha_estado, comprobante, fecha_pago
 */
function obtener_reembolsos(
  string $identificacion = '',
  string $proceso = '',
  string $rad_via = '',
  string $pago = ''
): array {
  global $conn;

  $sql = <<<'SQL'
;WITH e_last AS (   -- último evento por radicado
  SELECT es.radicado, MAX(es.id_solicitudes) AS id_ultimo
  FROM dbo.evento_solicitudes es
  GROUP BY es.radicado
),
pv_comp AS (        -- comprobante y fecha de pago más recientes por radicado
  SELECT pv.radicado,
         pv.comprobante,
         pv.fecha       AS fecha_pago,
         ROW_NUMBER() OVER (
           PARTITION BY pv.radicado
           ORDER BY ISNULL(pv.fecha,'1900-01-01') DESC, pv.id DESC
         ) AS rn
  FROM dbo.pagos_viaticos pv
)
SELECT
    s.rad_via                              AS Radicado,
    e.fecha_solicitud                      AS [Fecha de solicitud],
    s.numero_identificacion                AS [Número de Identificación],
    s.apr_departamental                    AS Departamental,   -- ajusta si tu tabla guarda "estado departamental"
    s.proceso                              AS [Proceso Nacional], -- o cambia al campo correcto de "proceso nacional"
    e.fecha_estado                         AS [Fecha de proceso],
    pvx.comprobante,
    pvx.fecha_pago
FROM dbo.solicitudes s
JOIN e_last el 
  ON el.radicado = s.radicado
JOIN dbo.evento_solicitudes e
  ON e.radicado = el.radicado
 AND e.id_solicitudes = el.id_ultimo
LEFT JOIN pv_comp pvx
  ON pvx.radicado = s.rad_via
 AND pvx.rn = 1
ORDER BY TRY_CAST(s.rad_via AS INT);
SQL;

  // Ejecutar tal cual, sin modificar el query
  $stmt = sqlsrv_query($conn, $sql, []);
  if ($stmt === false) {
    throw new RuntimeException('Error ejecutando consulta: '.print_r(sqlsrv_errors(), true));
  }
  $rows = [];
  while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
  }
  sqlsrv_free_stmt($stmt);
  return $rows;
}

/**
 * Devuelve todas las observaciones (no nulas) de un radicado.
 */
function obtener_observaciones(string $radicado): array {
  global $conn;
  $sql = "SELECT observacion
          FROM dbo.evento_solicitudes
          WHERE radicado = ? AND observacion IS NOT NULL";
  $stmt = sqlsrv_query($conn, $sql, [$radicado]);
  if ($stmt === false) {
    throw new RuntimeException('Error consultando observaciones: '.print_r(sqlsrv_errors(), true));
  }
  $obs = [];
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $obs[] = $r['observacion'];
  }
  sqlsrv_free_stmt($stmt);
  return $obs;
}
