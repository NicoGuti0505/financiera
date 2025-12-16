<?php
// data.php — API JSON robusta (usa tu config.php que ya crea $conn)
ob_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Convierte warnings/notices en excepciones
set_error_handler(function($sev, $msg, $file, $line) {
  if (!(error_reporting() & $sev)) return;
  throw new ErrorException($msg, 0, $sev, $file, $line);
});

// ===== Buscar config.php subiendo hasta 8 niveles =====
$dir = __DIR__; $configPath = null;
for ($i=0; $i<8; $i++) {
  $try = $dir . DIRECTORY_SEPARATOR . 'config.php';
  if (file_exists($try)) { $configPath = $try; break; }
  $dir = dirname($dir);
}
if (!$configPath) { http_response_code(500); @ob_end_clean(); echo json_encode(["error"=>"No se encontró config.php desde ". __DIR__], JSON_UNESCAPED_UNICODE); exit; }

// Carga tu config.php (crea $conn)
require_once $configPath;
if (!isset($conn) || $conn === false) {
  http_response_code(500); @ob_end_clean();
  echo json_encode(["error"=>"Conexión SQL no disponible desde config.php","detail"=>sqlsrv_errors()], JSON_UNESCAPED_UNICODE);
  exit;
}

// === util para ejecutar consultas y normalizar fechas ===
function runQuery($conn, $sql, $params = []) {
  $stmt = sqlsrv_query($conn, $sql, $params);
  if ($stmt === false) { $detail = json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE); throw new RuntimeException("SQL error: $detail | SQL: $sql"); }
  $rows = [];
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    foreach ($r as $k => $v) if ($v instanceof DateTime) $r[$k] = $v->format('Y-m-d H:i:s');
    $rows[] = $r;
  }
  sqlsrv_free_stmt($stmt);
  return $rows;
}

// ===== Ping =====
if (($_GET['action'] ?? '') === 'ping') {
  try { $ok = runQuery($conn, "SELECT TOP 1 1 AS ok"); @ob_end_clean(); echo json_encode(["status"=>"ok","db"=>"conectado","result"=>$ok], JSON_UNESCAPED_UNICODE); }
  catch (Throwable $e) { http_response_code(500); @ob_end_clean(); echo json_encode(["error"=>"ping failed","message"=>$e->getMessage()], JSON_UNESCAPED_UNICODE); }
  exit;
}

try {
  $action = $_GET['action'] ?? 'summary';

  // Filtros (por NOMBRE) + documento
  $dep = isset($_GET['dep']) ? trim($_GET['dep']) : null;  // nombre del departamento
  $mun = isset($_GET['mun']) ? trim($_GET['mun']) : null;  // nombre del municipio
  $nit = isset($_GET['nit']) ? trim($_GET['nit']) : null;  // prestador_nit
  $doc = isset($_GET['doc']) ? trim($_GET['doc']) : null;  // documento

  $limit  = max(1, (int)($_GET['limit']  ?? 1000));
  $offset = max(0, (int)($_GET['offset'] ?? 0));

  // Tablas
  $form  = "[formarauca].[dbo].[formulario_primaria]";
  $muni  = "[formarauca].[dbo].[municipios]";
  $prest = "[formarauca].[dbo].[prestadores_libre_eleccion]";

  // WHERE por nombres
  $filters = [];
  $params  = [];
  if ($dep !== null && $dep !== '') { $filters[] = "m.departamento = ?"; $params[] = $dep; }
  if ($mun !== null && $mun !== '') { $filters[] = "m.municipio = ?";    $params[] = $mun; }
  if ($nit !== null && $nit !== '') { $filters[] = "f.prestador_nit = ?"; $params[] = $nit; }
  if ($doc !== null && $doc !== '') { $filters[] = "f.documento = ?";     $params[] = $doc; }
  $where = $filters ? "WHERE " . implode(" AND ", $filters) : "";

  switch ($action) {
    // ---------- Filtros (combos buscables) ----------
    case 'filters': {
      $deps = runQuery($conn, "
        SELECT DISTINCT m.departamento AS value
        FROM $form f
        JOIN $muni m ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
        WHERE m.departamento IS NOT NULL
        ORDER BY value
      ");
      if ($dep) {
        $muns = runQuery($conn, "
          SELECT DISTINCT m.municipio AS value
          FROM $form f
          JOIN $muni m ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
          WHERE m.departamento = ?
          ORDER BY value
        ", [$dep]);
      } else {
        $muns = runQuery($conn, "
          SELECT DISTINCT m.municipio AS value
          FROM $form f
          JOIN $muni m ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
          ORDER BY value
        ");
      }
      // traemos NIT + etiqueta (nombre IPS) por si lo quieres usar en el front
      $nits = runQuery($conn, "
        SELECT DISTINCT 
               LTRIM(RTRIM(f.prestador_nit)) AS value,
               COALESCE(p.nombre_prestador,'') AS label
        FROM $form f
        LEFT JOIN $prest p 
               ON LTRIM(RTRIM(f.prestador_nit)) = LTRIM(RTRIM(p.nit))
        WHERE f.prestador_nit IS NOT NULL
        ORDER BY value
      ");

      @ob_end_clean();
      echo json_encode([
        "departamentos" => is_array($deps) ? $deps : [],
        "municipios"    => is_array($muns) ? $muns : [],
        "nits"          => is_array($nits) ? $nits : []
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // ---------- Resumen (KPI + gráficos) ----------
    case 'summary': {
      $totalRows = runQuery($conn, "
        SELECT COUNT(1) AS total
        FROM $form f
        JOIN $muni m ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
        $where
      ", $params);

      $byDep = runQuery($conn, "
        SELECT m.departamento AS departamento, COUNT(1) AS total
        FROM $form f
        JOIN $muni m ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
        $where
        GROUP BY m.departamento
        ORDER BY total DESC
      ", $params);

      $byMun = runQuery($conn, "
        SELECT m.departamento AS departamento, m.municipio AS municipio, COUNT(1) AS total
        FROM $form f
        JOIN $muni m ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
        $where
        GROUP BY m.departamento, m.municipio
        ORDER BY total DESC
      ", $params);

      // Por NIT + nombre IPS
      $byNit = runQuery($conn, "
      SELECT 
        LTRIM(RTRIM(f.prestador_nit)) AS nit,
        COALESCE(p.nombre_prestador,'') AS ips_nombre,
        COUNT(1) AS total
      FROM $form f
      JOIN $muni m
        ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
      LEFT JOIN (
        SELECT 
            LTRIM(RTRIM(nit)) AS nit,
            MAX(nombre_prestador) AS nombre_prestador,
            MAX(naturaleza)      AS naturaleza
        FROM $prest
        GROUP BY LTRIM(RTRIM(nit))
      ) p
        ON LTRIM(RTRIM(f.prestador_nit)) = p.nit
      $where
      GROUP BY LTRIM(RTRIM(f.prestador_nit)), p.nombre_prestador
      ORDER BY total DESC

      ", $params);

        // Naturaleza (para la torta)
        $byNat = runQuery($conn, "
          SELECT
            COALESCE(NULLIF(LTRIM(RTRIM(p.naturaleza)), ''), 'Sin dato') AS naturaleza,
            COUNT(1) AS total
          FROM $form f
          JOIN $muni m
            ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
          LEFT JOIN (
            SELECT 
                LTRIM(RTRIM(nit)) AS nit,
                MAX(nombre_prestador) AS nombre_prestador,
                MAX(naturaleza)      AS naturaleza
            FROM $prest
            GROUP BY LTRIM(RTRIM(nit))
          ) p
            ON LTRIM(RTRIM(f.prestador_nit)) = p.nit
          $where
          GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(p.naturaleza)), ''), 'Sin dato')
          ORDER BY total DESC

        ", $params);

        $byNat = array_map(
          fn($r) => [
            "naturaleza" => $r["naturaleza"] ?? "Sin dato",
            "total"      => (int)($r["total"] ?? 0)
          ],
          is_array($byNat) ? $byNat : []
        );

      $total = (is_array($totalRows) && isset($totalRows[0]['total'])) ? (int)$totalRows[0]['total'] : 0;
      $byDep = array_map(fn($r)=>["departamento"=>$r["departamento"]??null,"total"=>(int)($r["total"]??0)], is_array($byDep)?$byDep:[]);
      $byMun = array_map(fn($r)=>[
        "departamento"=>$r["departamento"]??null,
        "municipio"=>$r["municipio"]??null,
        "total"=>(int)($r["total"]??0)
      ], is_array($byMun)?$byMun:[]);
      $byNit = array_map(fn($r)=>[
        "nit"=>$r["nit"]??null,
        "ips_nombre"=>$r["ips_nombre"]??'',
        "total"=>(int)($r["total"]??0)
      ], is_array($byNit)?$byNit:[]);

      @ob_end_clean();
      echo json_encode([
        "total" => $total,
        "byDepartamento" => $byDep,
        "byMunicipio"    => $byMun,
        "byNit"          => $byNit,
        "byNaturaleza" => $byNat

      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // ---------- Desglose por NIT ----------
    case 'nit_breakdown': {
      if (!$nit) { @ob_end_clean(); echo json_encode([]); exit; }
      $rows = runQuery($conn, "
        SELECT LTRIM(RTRIM(f.prestador_nit)) AS nit,
               COALESCE(p.nombre_prestador,'') AS ips_nombre,
               m.departamento AS departamento,
               m.municipio    AS municipio,
               COUNT(1)       AS total
        FROM $form f
        JOIN $muni m ON TRY_CONVERT(INT, f.municipio_residencia) = m.id_municipio
        LEFT JOIN $prest p 
               ON LTRIM(RTRIM(f.prestador_nit)) = LTRIM(RTRIM(p.nit))
        $where
        GROUP BY f.prestador_nit, p.nombre_prestador, m.departamento, m.municipio
        ORDER BY total DESC
      ", $params);
      $rows = array_map(fn($r)=>[
        "nit"=>$r["nit"]??null,
        "ips_nombre"=>$r["ips_nombre"]??'',
        "departamento"=>$r["departamento"]??null,
        "municipio"=>$r["municipio"]??null,
        "total"=>(int)($r["total"]??0)
      ], is_array($rows)?$rows:[]);
      @ob_end_clean();
      echo json_encode($rows, JSON_UNESCAPED_UNICODE);
      exit;
    }

    



  default:
      @ob_end_clean();
      echo json_encode(["error" => "Acción no válida"]);
      exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  @ob_end_clean();
  echo json_encode(["error"=>"Excepción","message"=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
