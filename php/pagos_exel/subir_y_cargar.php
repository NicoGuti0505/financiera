<?php
/**
 * CSV (18 columnas fijas) -> staging (VARCHAR(MAX)) -> validaciones -> INSERT dbo.pagos
 * Solo mapea columnas CSV; lo demás en dbo.pagos queda NULL.
 * Registra evento_carga y evento_pagos (con id_fomag insertado).
 * Genera TXTs de errores con el formato: "id=<valor> <motivo>" en __DIR__/errores/.
 */

set_time_limit(0);
while (ob_get_level()) { @ob_end_clean(); }
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// === Carpeta local para logs y TXTs ===
if (!is_dir(__DIR__ . '/errores')) { @mkdir(__DIR__ . '/errores', 0777, true); }
ini_set('error_log', __DIR__ . '/errores/php_' . date('Ymd_His') . '.log');
header('Content-Type: application/json; charset=utf-8');

function respond($payload, $httpCode = 200){
  @ob_clean(); http_response_code($httpCode);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit;
}
function uuidv4(){
  $d = random_bytes(16);
  $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
  $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d),4));
}
function base_url_no_query() {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $uri    = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
  return $scheme . '://' . $host . $uri;
}

require '../../vendor/autoload.php';
session_start();

// ====== DESCARGA DE TXT (antes de nada) ======
$PHP_ERR_DIR = rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'errores' . DIRECTORY_SEPARATOR;
if (isset($_GET['download'])) {
  $f = basename($_GET['download']);         // evita path traversal
  $path = $PHP_ERR_DIR . $f;
  if (!is_file($path)) { http_response_code(404); echo "Archivo no encontrado"; exit; }
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$f.'"');
  header('Content-Length: ' . filesize($path));
  readfile($path);
  exit;
}

// ====== Verifica carpeta de errores ======
if (!is_dir($PHP_ERR_DIR)) {
  if (!mkdir($PHP_ERR_DIR, 0777, true)) {
    respond(['success'=>false, 'message'=>'No pude crear la carpeta de errores', 'path'=>$PHP_ERR_DIR], 500);
  }
}
if (!is_writable($PHP_ERR_DIR)) {
  respond(['success'=>false, 'message'=>'La carpeta de errores no es escribible', 'path'=>$PHP_ERR_DIR], 500);
}

// ===== helpers para TXT de errores =====
function dump_txt(string $name, array $lines): ?array {
  global $PHP_ERR_DIR;
  if (empty($lines)) return null;
  $path = $PHP_ERR_DIR . $name;
  $ok   = file_put_contents($path, implode("\n", $lines));
  if ($ok === false) {
    return ['name'=>$name, 'path'=>$path, 'ok'=>false, 'url'=>null, 'msg'=>'file_put_contents falló'];
  }
  $url = base_url_no_query() . '?download=' . urlencode($name);
  return ['name'=>$name, 'path'=>$path, 'ok'=>true, 'url'=>$url];
}
function idstr($v){ $v = trim((string)$v); return ($v === '' ? '(vacío)' : $v); }

// $conn desde config.php (sqlsrv_connect a gestion_terceros)
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp  = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

$SCHEMA      = 'dbo';
$TBL_PAGOS   = "[$SCHEMA].[pagos]";
$TBL_STAGING = "[$SCHEMA].[pagos_staging]";
$TBL_EC      = "[$SCHEMA].[evento_carga]";
$TBL_EP      = "[$SCHEMA].[evento_pagos]";

// Carpetas que LEE/ESCRIBE el servicio de SQL Server (para BULK)
$UPLOAD_DIR = 'C:\\SqlBulk\\uploads\\';
$ERROR_DIR  = 'C:\\SqlBulk\\errors\\';
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0777, true);
if (!is_dir($ERROR_DIR))  @mkdir($ERROR_DIR,  0777, true);

// ===== Archivo =====
if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
  respond(['success'=>false,'message'=>'Error al subir el archivo.'], 400);
}
$tmp = $_FILES['archivo_csv']['tmp_name'];
$csv = $UPLOAD_DIR . uniqid('csv_') . '.csv';
if (!move_uploaded_file($tmp, $csv)) {
  respond(['success'=>false,'message'=>'No se pudo mover el archivo al directorio de cargas.'], 500);
}

// ===== Normaliza a 18 columnas exactas =====
$RPT_COLS = $PHP_ERR_DIR . 'cols_' . time() . '.txt';
$lines = @file($csv, FILE_IGNORE_NEW_LINES);
if ($lines === false) respond(['success'=>false,'message'=>'No fue posible leer el CSV movido.'], 500);

$headers = [
  'id','modalidad','nit','nombre','prefijo','no fact','prefijo factura',
  'fecha de factura','fecha de radicacion','mes - año de radicacion',
  'valor factura','valor pagado','%','estado','voucher','fecha de pago',
  'fuente de origen','observacion'
];
$hasHeader = false;
if (!empty($lines)) {
  $l1 = mb_strtolower(trim(preg_replace('/\s+/', ' ', $lines[0])));
  foreach ($headers as $h) { if (strpos($l1, $h) !== false) { $hasHeader = true; break; } }
}

$norm = []; $report = []; $i = 0;
foreach ($lines as $ln) {
  $i++;
  if ($hasHeader && $i === 1) { $norm[] = $ln; continue; }
  $ln   = preg_replace('/\x{00A0}+/u', '', $ln); // NBSP
  $cols = explode(';', $ln);
  $c    = count($cols);
  if ($c < 18) {
    $faltan = 18 - $c; $ln .= str_repeat(';', $faltan);
    $report[] = "Fila $i tenía $c columnas -> +$faltan (ahora 18).";
  } elseif ($c > 18) {
    file_put_contents($RPT_COLS, "Fila $i: $c columnas (>18). Línea:\n$ln\n", FILE_APPEND);
    respond(['success'=>false,'message'=>'❌ El CSV trae filas con MÁS de 18 columnas. Corrige el archivo.','detalle'=>basename($RPT_COLS)], 400);
  }
  $norm[] = $ln;
}
if (!empty($report)) file_put_contents($RPT_COLS, implode("\n", $report), FILE_APPEND);
file_put_contents($csv, implode("\r\n", $norm));

// ===== TX =====
if (!sqlsrv_begin_transaction($conn)) respond(['success'=>false,'message'=>'No fue posible iniciar transacción SQL.'], 500);
$evento_id = uuidv4();

// Usa la BD explícitamente
if (!sqlsrv_query($conn, "USE [gestion_terceros];")) {
  sqlsrv_rollback($conn); respond(['success'=>false,'message'=>'No se pudo hacer USE gestion_terceros','sqlsrv'=>sqlsrv_errors()],500);
}

// ===== STAGING todo VARCHAR(MAX) =====
$createStaging = "
IF OBJECT_ID('$TBL_STAGING','U') IS NOT NULL DROP TABLE $TBL_STAGING;
CREATE TABLE $TBL_STAGING(
  id                 VARCHAR(MAX) NULL,
  modalidad          VARCHAR(MAX) NULL,
  nit                VARCHAR(MAX) NULL,
  nombre_prest       VARCHAR(MAX) NULL,
  prefijo            VARCHAR(MAX) NULL,
  no_fact            VARCHAR(MAX) NULL,
  num_factura        VARCHAR(MAX) NULL,
  fecha_factura      VARCHAR(MAX) NULL,
  fecha_radicacion   VARCHAR(MAX) NULL,
  mes_anio_radicacion VARCHAR(MAX) NULL,
  valor_factura      VARCHAR(MAX) NULL,
  valor_pagado       VARCHAR(MAX) NULL,
  porcentaje_pago    VARCHAR(MAX) NULL,
  estado             VARCHAR(MAX) NULL,
  voucher            VARCHAR(MAX) NULL,
  feccha_pago        VARCHAR(MAX) NULL,
  fuente_origen      VARCHAR(MAX) NULL,
  observacion        VARCHAR(MAX) NULL
);";
if (!sqlsrv_query($conn,$createStaging)) {
  sqlsrv_rollback($conn); respond(['success'=>false,'message'=>'Error creando staging','sqlsrv'=>sqlsrv_errors()],500);
}

// ===== BULK (probando CRLF y LF) =====
array_map('unlink', glob($ERROR_DIR.'bulk_*'));
$errBase  = $ERROR_DIR.'bulk_'.time();
$ok = false; $tried = [];
$firstRow = $hasHeader ? "FIRSTROW = 2," : "";

foreach (['0x0D0A','0x0A'] as $rt) {
  $bulk = "
  BULK INSERT $TBL_STAGING
  FROM '$csv'
  WITH (
    $firstRow
    FIELDTERMINATOR=';',
    ROWTERMINATOR='$rt',
    DATAFILETYPE='char',
    CODEPAGE='65001',
    KEEPNULLS, TABLOCK, MAXERRORS=1000,
    ERRORFILE='$errBase'
  );";
  $tried[] = $bulk;
  if (sqlsrv_query($conn,$bulk)) { $ok = true; break; }
}
if (!$ok) {
  $err = sqlsrv_errors(); sqlsrv_rollback($conn);
  $log = $PHP_ERR_DIR . 'bulk_'.time().'.log';
  file_put_contents($log, "CSV: $csv\n\n".implode("\n----\n",$tried)."\n\n".print_r($err,true));
  respond([
    'success'=>false,'message'=>'❌ BULK INSERT falló (terminador/encoding/permisos).',
    'sqlsrv'=>$err,'errorfile'=>['err'=>$errBase.'.Error.Txt','row'=>$errBase.'.Row.Txt'],
    'debug_log'=>'errores/'.basename($log)
  ],500);
}

/* =======================
   VALIDACIONES + TXTs
   ======================= */

$error_txts_objs = [];   // {name, path, ok, url}
$hay_errores = false;

// 1) Vacíos/obligatorios (id, nit, voucher, feccha_pago válida)
$q = "
SELECT
  COALESCE(NULLIF(LTRIM(RTRIM(id)) ,''), '(vacío)') AS xid,
  CASE WHEN NULLIF(LTRIM(RTRIM(id)),'') IS NULL THEN 1 ELSE 0 END AS miss_id,
  CASE WHEN NULLIF(LTRIM(RTRIM(nit)),'') IS NULL THEN 1 ELSE 0 END AS miss_nit,
  CASE WHEN NULLIF(LTRIM(RTRIM(voucher)),'') IS NULL THEN 1 ELSE 0 END AS miss_voucher,
  CASE WHEN TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(feccha_pago)) ,''), 103) IS NULL THEN 1 ELSE 0 END AS bad_fecpago
FROM $TBL_STAGING";
$r = sqlsrv_query($conn,$q);
$lines = [];
while($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)){
  $f = [];
  if ($row['miss_id'])      $f[]='id vacío';
  if ($row['miss_nit'])     $f[]='nit vacío';
  if ($row['miss_voucher']) $f[]='voucher vacío';
  if ($row['bad_fecpago'])  $f[]='fecha_pago inválida';
  if (!empty($f)) { $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." ".implode(', ',$f); }
}
if ($obj = dump_txt('errores_valores_vacios.txt', $lines)) $error_txts_objs[] = $obj;

// 2) Fechas inválidas (factura/radicación)
$q = "
SELECT COALESCE(NULLIF(LTRIM(RTRIM(id)) ,''), '(vacío)') AS xid,
       CASE WHEN NULLIF(LTRIM(RTRIM(fecha_factura)),'') IS NOT NULL
                 AND TRY_CONVERT(date, LTRIM(RTRIM(fecha_factura)),103) IS NULL
            THEN 1 ELSE 0 END AS bad_ffac,
       CASE WHEN NULLIF(LTRIM(RTRIM(fecha_radicacion)),'') IS NOT NULL
                 AND TRY_CONVERT(date, LTRIM(RTRIM(fecha_radicacion)),103) IS NULL
            THEN 1 ELSE 0 END AS bad_frad
FROM $TBL_STAGING";
$r = sqlsrv_query($conn,$q);
$lines = [];
while($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)){
  if ($row['bad_ffac']) { $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." fecha_factura inválida"; }
  if ($row['bad_frad']) { $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." fecha_radicacion inválida"; }
}
if ($obj = dump_txt('errores_fechas.txt', $lines)) $error_txts_objs[] = $obj;

// 3) Coma decimal en valores
$q = "SELECT COALESCE(NULLIF(LTRIM(RTRIM(id)) ,''), '(vacío)') AS xid,
             (CASE WHEN valor_factura LIKE '%,%' THEN 1 ELSE 0 END) vfac,
             (CASE WHEN valor_pagado  LIKE '%,%' THEN 1 ELSE 0 END) vpag
      FROM $TBL_STAGING";
$r = sqlsrv_query($conn,$q);
$lines = [];
while($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)){
  if ($row['vfac']) { $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." coma decimal en valor_factura"; }
  if ($row['vpag']) { $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." coma decimal en valor_pagado"; }
}
if ($obj = dump_txt('errores_coma_decimal.txt', $lines)) $error_txts_objs[] = $obj;

// 4) No numéricos y <= 0
$q = "
SELECT COALESCE(NULLIF(LTRIM(RTRIM(id)) ,''), '(vacío)') AS xid,
       TRY_CONVERT(numeric(20,2), NULLIF(REPLACE(REPLACE(valor_factura,'$',''),' ',''),'')) vf,
       TRY_CONVERT(numeric(20,2), NULLIF(REPLACE(REPLACE(valor_pagado ,'$',''),' ',''),'')) vp
FROM $TBL_STAGING";
$r = sqlsrv_query($conn,$q);
$lines_badnum = []; $lines_le0 = [];
while($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)){
  if ($row['vf'] === null) { $hay_errores = true; $lines_badnum[] = "id=".idstr($row['xid'])." valor_factura no numérico"; }
  if ($row['vp'] === null) { $hay_errores = true; $lines_badnum[] = "id=".idstr($row['xid'])." valor_pagado no numérico"; }
  if ($row['vf'] !== null && $row['vf'] <= 0) { $hay_errores = true; $lines_le0[] = "id=".idstr($row['xid'])." valor_factura <= 0"; }
  if ($row['vp'] !== null && $row['vp'] <= 0) { $hay_errores = true; $lines_le0[] = "id=".idstr($row['xid'])." valor_pagado <= 0"; }
}
if ($obj = dump_txt('errores_valores_no_numericos.txt', $lines_badnum)) $error_txts_objs[] = $obj;
if ($obj = dump_txt('errores_valores_menor_igual_cero.txt', $lines_le0)) $error_txts_objs[] = $obj;

// 5) Pagado > Facturado
$q = "
SELECT COALESCE(NULLIF(LTRIM(RTRIM(id)) ,''), '(vacío)') AS xid
FROM $TBL_STAGING
WHERE TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(valor_pagado ,'$',''),' ','')) >
      TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(valor_factura,'$',''),' ',''))";
$r = sqlsrv_query($conn,$q);
$lines = [];
while($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)){
  $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." valor pagado superior al facturado";
}
if ($obj = dump_txt('errores_pagado_mayor_que_factura.txt', $lines)) $error_txts_objs[] = $obj;

// 6) Duplicados dentro del archivo
$q = "
WITH base AS (
  SELECT
    LTRIM(RTRIM(modalidad)) AS modalidad,
    LTRIM(RTRIM(num_factura)) AS num_factura,
    TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(valor_pagado ,'$',''),' ','')) AS vp,
    TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(feccha_pago)) ,''), 103) AS fp,
    COUNT(*) cnt
  FROM $TBL_STAGING
  GROUP BY LTRIM(RTRIM(modalidad)), LTRIM(RTRIM(num_factura)),
           TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(valor_pagado ,'$',''),' ','')),
           TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(feccha_pago)) ,''), 103)
  HAVING COUNT(*) > 1
)
SELECT COALESCE(NULLIF(LTRIM(RTRIM(s.id)) ,''), '(vacío)') AS xid
FROM $TBL_STAGING s
JOIN base b ON
  LTRIM(RTRIM(s.modalidad)) = b.modalidad AND
  LTRIM(RTRIM(s.num_factura)) = b.num_factura AND
  TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(s.valor_pagado ,'$',''),' ','')) = b.vp AND
  TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(s.feccha_pago)) ,''), 103) = b.fp";
$r = sqlsrv_query($conn,$q);
$lines = [];
while($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)){
  $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." duplicado";
}
if ($obj = dump_txt('errores_duplicados_archivo.txt', $lines)) $error_txts_objs[] = $obj;

// 7) Duplicados contra dbo.pagos
$q = "
SELECT COALESCE(NULLIF(LTRIM(RTRIM(s.id)) ,''), '(vacío)') AS xid
FROM $TBL_STAGING s
JOIN $TBL_PAGOS p ON
  LTRIM(RTRIM(p.modalidad)) = LTRIM(RTRIM(s.modalidad)) AND
  LTRIM(RTRIM(p.num_factura)) = LTRIM(RTRIM(s.num_factura)) AND
  p.valor_pagado = TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(s.valor_pagado,'$',''),' ','')) AND
  p.feccha_pago  = TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(s.feccha_pago)) ,''), 103)";
$r = sqlsrv_query($conn,$q);
$lines = [];
while($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)){
  $hay_errores = true; $lines[] = "id=".idstr($row['xid'])." duplicado en BD";
}
if ($obj = dump_txt('errores_duplicados_bd.txt', $lines)) $error_txts_objs[] = $obj;

// Si hubo cualquier error crítico, abortamos y devolvemos la lista de TXTs + URLs
if ($hay_errores) {
  sqlsrv_rollback($conn);
  @unlink($csv);

  // toma la primera URL de los TXT generados
  $firstUrl = null;
  foreach ($error_txts_objs as $e) {
    if (!empty($e['url'])) { $firstUrl = $e['url']; break; }
  }

  respond([
    'success'         => false,
    'message'         => '❌ Validaciones críticas. Revisa los TXT generados.',
    'error_dir'       => $PHP_ERR_DIR,
    'error_txt_main'  => $firstUrl,  // <- esta es la URL de descarga principal
    'error_txts'      => $error_txts_objs, // lista completa
  ], 400);
}


/* =======================
   INSERT + EVENTOS
   ======================= */

// Captura IDs realmente insertados usando id_fomag (INT IDENTITY)
if (!sqlsrv_query($conn,"IF OBJECT_ID('tempdb..#nuevos_pagos') IS NOT NULL DROP TABLE #nuevos_pagos; CREATE TABLE #nuevos_pagos(id_pago INT NOT NULL PRIMARY KEY);")) {
  sqlsrv_rollback($conn); respond(['success'=>false,'message'=>'Error creando #nuevos_pagos','sqlsrv'=>sqlsrv_errors()],500);
}

$insert = "
INSERT INTO $TBL_PAGOS(
  id, modalidad, nit, nombre_prest, num_factura, concepto,
  valor_factura, valor_pagado, porcentaje_pago, estado, voucher,
  feccha_pago, observacion, estado_registro, estado_pago,
  prefijo, no_fact, fecha_factura, fecha_radicacion, mes_anio_radicacion,
  fuente_origen, fecha
)
OUTPUT inserted.id_fomag INTO #nuevos_pagos(id_pago)
SELECT
  TRY_CONVERT(numeric(18,0), NULLIF(LTRIM(RTRIM(id)) ,''))                                 AS id,
  NULLIF(LTRIM(RTRIM(modalidad)),'')                                                       AS modalidad,
  TRY_CONVERT(numeric(18,0), NULLIF(LTRIM(RTRIM(nit)) ,''))                                AS nit,
  NULLIF(LTRIM(RTRIM(nombre_prest)),'')                                                    AS nombre_prest,
  NULLIF(LTRIM(RTRIM(num_factura)),'')                                                     AS num_factura,
  NULL                                                                                      AS concepto,
  TRY_CONVERT(numeric(20,2), NULLIF(REPLACE(REPLACE(valor_factura,'$',''),' ',''),''))      AS valor_factura,
  TRY_CONVERT(numeric(20,2), NULLIF(REPLACE(REPLACE(valor_pagado ,'$',''),' ',''),''))      AS valor_pagado,
  TRY_CONVERT(numeric(5,2),  NULLIF(REPLACE(REPLACE(porcentaje_pago,'%',''),' ',''),''))    AS porcentaje_pago,
  NULLIF(LTRIM(RTRIM(estado)),'')                                                           AS estado,
  NULLIF(LTRIM(RTRIM(voucher)),'')                                                          AS voucher,
  TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(feccha_pago)) ,''),103)                              AS feccha_pago,
  NULLIF(LTRIM(RTRIM(observacion)),'')                                                      AS observacion,
  NULL                                                                                      AS estado_registro,
  NULL                                                                                      AS estado_pago,
  NULLIF(LTRIM(RTRIM(prefijo)),'')                                                          AS prefijo,
  NULLIF(LTRIM(RTRIM(no_fact)),'')                                                          AS no_fact,
  TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(fecha_factura)) ,''),103)                            AS fecha_factura,
  TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(fecha_radicacion)) ,''),103)                         AS fecha_radicacion,
  NULLIF(LTRIM(RTRIM(mes_anio_radicacion)),'')                                              AS mes_anio_radicacion,
  NULLIF(LTRIM(RTRIM(fuente_origen)),'')                                                    AS fuente_origen,
  GETDATE()                                                                                 AS fecha
FROM $TBL_STAGING
WHERE NULLIF(LTRIM(RTRIM(voucher)),'') IS NOT NULL;
";


if (!sqlsrv_query($conn,$insert)) {
  $err = sqlsrv_errors(); sqlsrv_rollback($conn);
  respond(['success'=>false,'message'=>'❌ Error insertando en pagos','sqlsrv'=>$err],500);
}

// Ver cuántas filas se insertaron realmente
$filasInsertadas = 0;
$stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS filas FROM #nuevos_pagos;");
if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $filasInsertadas = (int)$row['filas'];
}

// Si no se insertó ninguna fila, devolvemos error en vez de “éxito”
if ($filasInsertadas === 0) {
    // Tomamos una muestra de lo que había en staging para depuración
    $debugSample = [];
    $qDebug = "SELECT TOP 10 id, modalidad, nit, voucher, feccha_pago,
                      TRY_CONVERT(date, NULLIF(LTRIM(RTRIM(feccha_pago)) ,''),103) AS feccha_pago_conv
               FROM $TBL_STAGING";
    $stmt2 = sqlsrv_query($conn, $qDebug);
    if ($stmt2) {
        while ($r2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            $debugSample[] = $r2;
        }
    }

    sqlsrv_rollback($conn);
    @unlink($csv);

    respond([
        'success'          => false,
        'message'          => 'El archivo pasó validaciones, pero ninguna fila cumplió el WHERE del INSERT (voucher y feccha_pago). No se insertó nada.',
        'filas_insertadas' => $filasInsertadas,
        'debug_sample'     => $debugSample
    ], 400);
}


// evento_carga por voucher (solo vouchers no vacíos)
$usuario   = $_SESSION['nombre_usuario'] ?? null;
$nom_arch  = basename($csv);
$nom_orig  = $_FILES['archivo_csv']['name'] ?? $nom_arch;
$ahora     = date('Y-m-d H:i:s');

$insEC = "
INSERT INTO $TBL_EC(
  evento_id, usuario_id, nombre_archivo, nombre_archivo_original,
  fecha_carga, voucher, lineas_cargadas, suma_valor_pagado, suma_valor_factura
)
SELECT
  CAST(? AS uniqueidentifier), ?, ?, ?, ?,
  s.voucher,
  COUNT(*),
  SUM(TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(s.valor_pagado ,'$',''),' ',''))),
  SUM(TRY_CONVERT(numeric(20,2), REPLACE(REPLACE(s.valor_factura,'$',''),' ','')))
FROM $TBL_STAGING s
WHERE s.voucher IS NOT NULL AND LTRIM(RTRIM(s.voucher))<> ''
GROUP BY s.voucher;";
if (!sqlsrv_query($conn,$insEC,[$evento_id,$usuario,$nom_arch,$nom_orig,$ahora])) {
  $err=sqlsrv_errors(); sqlsrv_rollback($conn);
  respond(['success'=>false,'message'=>'❌ Error registrando evento_carga','sqlsrv'=>$err],500);
}

// evento_pagos con los ids insertados (id_fomag)
$insEP = "INSERT INTO $TBL_EP(evento_id,id_pago) SELECT CAST(? AS uniqueidentifier), id_pago FROM #nuevos_pagos;";
if (!sqlsrv_query($conn,$insEP,[$evento_id])) {
  $err=sqlsrv_errors(); sqlsrv_rollback($conn);
  respond(['success'=>false,'message'=>'❌ Error registrando mapeo evento_pagos','sqlsrv'=>$err],500);
}

// Limpieza staging
if (!sqlsrv_query($conn,"DROP TABLE $TBL_STAGING;")) {
  $err=sqlsrv_errors(); sqlsrv_rollback($conn);
  respond(['success'=>false,'message'=>'Error eliminando staging','sqlsrv'=>$err],500);
}

sqlsrv_commit($conn);
@unlink($csv);

respond([
  'success'=>true,
  'message'=>'Archivo cargado con validaciones y TXTs por id (duplicados, valores, fechas, etc.).',
  'evento_id'=>$evento_id,
  'txt_dir'=>$PHP_ERR_DIR,
  'filas_insertadas'=>$filasInsertadas
],200);

