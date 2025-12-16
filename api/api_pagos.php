<?php
require_once __DIR__ . '/config.php';

// ====== CORS (abierto) ======
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Vary: Origin");
header('Access-Control-Allow-Headers: Content-Type'); // sin Authorization
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ====== Utilidades ======
function json_out($data, $code=200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function param($key, $def=null){ return $_GET[$key] ?? $_POST[$key] ?? $def; }
function read_json(){ return json_decode(file_get_contents('php://input'), true) ?? []; }

// ====== SIN AUTENTICACIÓN ======
function require_auth() { /* abierto totalmente */ }

// Conexión y request info
require_auth();
$conn   = db_connect();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$e      = param('e'); // ruteo alterno por query

// ====== Normalizador de fechas SQLSRV -> string ======
function normalize_dates(array &$r, array $cols=['fecha','feccha_pago','fecha_factura','fecha_radicacion']) {
    foreach ($cols as $c) {
        if (isset($r[$c]) && $r[$c] instanceof DateTime) {
            $r[$c] = $r[$c]->format('Y-m-d H:i:s');
        }
    }
}

// ========================
//       ENDPOINTS
// ========================

$handleListar = function() use ($conn) {
    $desde = param('desde'); $hasta = param('hasta');
    $page  = max(1,(int)param('page',1));
    $per = max(1, (int) param('per_page', 50)); // sin tope

    if(!$desde || !$hasta) json_out(["error"=>"Parámetros 'desde' y 'hasta' obligatorios (YYYY-MM-DD)"],422);

    // Conteo
    $countSql="SELECT COUNT(*) AS total FROM gestion_terceros.dbo.pagos WITH (NOLOCK) WHERE fecha BETWEEN ? AND ?";
    $stmt=sqlsrv_query($conn,$countSql,[$desde,$hasta]);
    if(!$stmt) json_out(["error"=>"Error count","det"=>sqlsrv_errors()],500);
    $row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC); $total=(int)($row['total']??0);
    sqlsrv_free_stmt($stmt);

    // Datos paginados
    $offset=($page-1)*$per;
    $sql="SELECT id, modalidad, nit, nombre_prest, num_factura, concepto,
                 valor_factura, valor_pagado, porcentaje_pago, estado, voucher,
                 feccha_pago, observacion, id_fomag, estado_registro, estado_pago,
                 prefijo, no_fact, fecha_factura, fecha_radicacion,
                 mes_anio_radicacion, fuente_origen, fecha
          FROM gestion_terceros.dbo.pagos WITH (NOLOCK)
          WHERE fecha BETWEEN ? AND ?
          ORDER BY fecha DESC, id DESC
          OFFSET ? ROWS FETCH NEXT ? ROWS ONLY;";
    $stmt=sqlsrv_query($conn,$sql,[$desde,$hasta,(int)$offset,(int)$per]);
    if(!$stmt) json_out(["error"=>"Error select","det"=>sqlsrv_errors()],500);
    $data=[];
    while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){
        normalize_dates($r);
        $data[]=$r;
    }
    sqlsrv_free_stmt($stmt);

    json_out([
        "meta"=>[
            "page"=>$page,"per_page"=>$per,"total"=>$total,
            "pages"=>(int)ceil($total/max($per,1)),
            "range"=>["desde"=>$desde,"hasta"=>$hasta]
        ],
        "data"=>$data
    ]);
};

$handleResumen = function() use ($conn) {
    $desde=param('desde'); $hasta=param('hasta');
    if(!$desde||!$hasta) json_out(["error"=>"Parámetros obligatorios (YYYY-MM-DD)"],422);
    $sql="SELECT
            COUNT(*)                       AS cantidad_registros,
            COUNT(DISTINCT nit)            AS nits_unicos,
            SUM(COALESCE(valor_factura,0)) AS total_valor_factura,
            SUM(COALESCE(valor_pagado,0))  AS total_valor_pagado
          FROM gestion_terceros.dbo.pagos WITH (NOLOCK)
          WHERE fecha BETWEEN ? AND ?";
    $stmt=sqlsrv_query($conn,$sql,[$desde,$hasta]);
    if(!$stmt) json_out(["error"=>"Error resumen","det"=>sqlsrv_errors()],500);
    $row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC) ?? [];
    sqlsrv_free_stmt($stmt);
    json_out($row);
};

$handleVouchers = function() use ($conn) {
    $fecha=param('fecha'); if(!$fecha) json_out(["error"=>"Parámetro 'fecha' requerido (YYYY-MM-DD)"],422);

    $vs=[]; $stmt=sqlsrv_query($conn,"
        SELECT DISTINCT voucher
        FROM gestion_terceros.dbo.pagos WITH (NOLOCK)
        WHERE CAST(fecha AS date)=? AND voucher IS NOT NULL
        ORDER BY voucher",[$fecha]);
    if(!$stmt) json_out(["error"=>"Error vouchers","det"=>sqlsrv_errors()],500);
    while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $vs[]=$r['voucher'];
    sqlsrv_free_stmt($stmt);

    $stmt=sqlsrv_query($conn,"
        SELECT
            SUM(COALESCE(valor_factura,0)) AS total_valor_factura,
            SUM(COALESCE(valor_pagado,0))  AS total_valor_pagado,
            COUNT(*) AS cantidad
        FROM gestion_terceros.dbo.pagos WITH (NOLOCK)
        WHERE CAST(fecha AS date)=?",[$fecha]);
    if(!$stmt) json_out(["error"=>"Error métricas","det"=>sqlsrv_errors()],500);
    $m=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC) ?? [];
    sqlsrv_free_stmt($stmt);

    json_out(["fecha"=>$fecha,"metricas"=>$m,"vouchers"=>$vs]);
};

$handleAprobar = function() use ($conn) {
    $body=read_json(); $ids=$body['ids']??[]; $obs=$body['observacion']??null;
    if(!is_array($ids)||empty($ids)) json_out(["error"=>"Debe enviar 'ids' (array)"],422);

    $marks=implode(',', array_fill(0,count($ids),'?'));
    $params=array_merge([$obs],$ids);
    $sql="UPDATE gestion_terceros.dbo.pagos
          SET estado_registro='Aprobado', observacion=?
          WHERE id IN ($marks)";
    $stmt=sqlsrv_query($conn,$sql,$params);
    if(!$stmt) json_out(["error"=>"Error al actualizar","det"=>sqlsrv_errors()],500);
    $aff = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    json_out(["ok"=>true,"aprobados"=>$aff]);
};

// ========== Ruteo por query (?e=...) ==========
if ($e === 'pagos'   && $method==='GET')  $handleListar();
if ($e === 'resumen' && $method==='GET')  $handleResumen();
if ($e === 'vouchers'&& $method==='GET')  $handleVouchers();
if ($e === 'aprobar' && $method==='POST') $handleAprobar();

// ========== Ruteo por PATH (si el servidor lo permite) ==========
if ($method==='GET'  && preg_match('#/api/pagos$#',$path))             $handleListar();
if ($method==='GET'  && preg_match('#/api/pagos/resumen$#',$path))     $handleResumen();
if ($method==='GET  '&& preg_match('#/api/pagos/vouchers$#',$path))    $handleVouchers();
if ($method==='POST' && preg_match('#/api/pagos/aprobar$#',$path))     $handleAprobar();

// 404
json_out(["error"=>"Endpoint no encontrado. Use ?e=pagos|resumen|vouchers|aprobar"],404);
