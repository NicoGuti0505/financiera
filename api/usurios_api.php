<?php
/**
 * API pública (sin token) para listar usuarios y ver sus cargas agrupadas por fecha + voucher.
 * - GET  /api/public/usuarios
 * - GET  /api/public/usuarios/{usuario_id}/cargas?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&page=1&per_page=50
 *
 * Si voucher es NULL/'' => se rotula "No se tiene voucher" y se agrupa solo por fecha.
 */

declare(strict_types=1);
while (ob_get_level()) { ob_end_clean(); }
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0777, true); }
ini_set('error_log', __DIR__ . '/logs/public_api_' . date('Ymd') . '.log');

header('Content-Type: application/json; charset=utf-8');

// === CORS muy abierto (público)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Vary: Origin");
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

// === Helpers
function json_out($data, int $code = 200) {
    @ob_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function param($key, $def = null) { return $_GET[$key] ?? $def; }

// === Conexión
require_once __DIR__ . '/config.php';

$conn = db_connect();  // ← crear la conexión aquí

if ($conn === false) {
    json_out([
        'error' => 'No hay conexión a la base de datos',
        'sqlsrv' => sqlsrv_errors()
    ], 500);
}


if (!$conn) json_out(['error' => 'No hay conexión a la base de datos'], 500);

// === Router muy simple
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Normaliza base: ajusta si lo pones en otra carpeta
// Por ejemplo, si sirves como http://tu-ip/aplicacion/php/public_api.php/...
// quieres que $base pueda recortar hasta "/aplicacion/php/public_api.php"
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$base = rtrim($scriptName, '/');
if (str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . ltrim($path, '/');

// ========================
//       ENDPOINTS
// ========================

/**
 * GET /api/public/usuarios
 * Lista usuarios que han hecho cargas, con:
 * - usuario_id
 * - total_eventos
 * - fecha_ultima_carga
 */
if ($method === 'GET' && preg_match('#^/api/public/usuarios$#', $path)) {
    $sql = "
        SELECT 
            ec.usuario_id,
            COUNT(*) AS total_eventos,
            CONVERT(varchar(19), MAX(ec.fecha_carga), 120) AS fecha_ultima_carga
        FROM gestion_terceros.dbo.evento_carga ec
        GROUP BY ec.usuario_id
        ORDER BY MAX(ec.fecha_carga) DESC, ec.usuario_id ASC;
    ";
    $stmt = sqlsrv_query($conn, $sql);
    if (!$stmt) json_out(['error' => 'Error consultando usuarios', 'sqlsrv' => sqlsrv_errors()], 500);
    $data = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'usuario_id'        => $r['usuario_id'],
            'total_eventos'     => (int)$r['total_eventos'],
            'fecha_ultima_carga'=> $r['fecha_ultima_carga'],
            // URL directa al detalle de este usuario (ayuda al front)
            'detalle_url'       => sprintf('%s/api/public/usuarios/%s/cargas', rtrim(dirname($scriptName), '/'), rawurlencode((string)$r['usuario_id'])),
        ];
    }
    sqlsrv_free_stmt($stmt);
    json_out(['data' => $data]);
}

/**
 * GET /api/public/usuarios/{usuario_id}/cargas?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&page=1&per_page=50
 *
 * Devuelve grupos por fecha (DATE(fecha_carga)) + voucher (o "No se tiene voucher" cuando es NULL/'').
 * Cada grupo incluye:
 * - fecha (YYYY-MM-DD)
 * - voucher_label ("No se tiene voucher" o el valor del voucher)
 * - lineas (SUM lineas_cargadas)
 * - valor_pagado (SUM suma_valor_pagado)
 * - valor_factura (SUM suma_valor_factura)
 * - eventos: cantidad de filas evento_carga involucradas en ese grupo
 * - evento_ids: lista (opcional) si deseas saber qué cargas participaron (puede ser útil)
 */
if ($method === 'GET' && preg_match('#^/api/public/usuarios/([^/]+)/cargas$#', $path, $m)) {
    $usuario = urldecode($m[1]);

    $desde = param('desde'); // YYYY-MM-DD opcional
    $hasta = param('hasta'); // YYYY-MM-DD opcional
    $page  = max(1, (int)param('page', 1));
    $per   = min(max(1, (int)param('per_page', 50)), 500);

    // Filtro base
    $where = "ec.usuario_id = ?";
    $params = [ $usuario ];

    // Filtros por fecha (sobre la fecha_carga)
    if ($desde) { $where .= " AND CAST(ec.fecha_carga AS date) >= ?"; $params[] = $desde; }
    if ($hasta) { $where .= " AND CAST(ec.fecha_carga AS date) <= ?"; $params[] = $hasta; }

    // Usamos una clave de agrupación para voucher:
    // voucher_key = '__NO_VOUCHER__' cuando es NULL/'' y así rotulamos luego.
    // Agrupamos por fecha (DATE) + voucher_key
    $countSql = "
        WITH base AS (
            SELECT 
                CAST(ec.fecha_carga AS date) AS fecha,
                CASE 
                    WHEN ec.voucher IS NULL OR LTRIM(RTRIM(ec.voucher)) = '' THEN '__NO_VOUCHER__'
                    ELSE LTRIM(RTRIM(ec.voucher))
                END AS voucher_key
            FROM gestion_terceros.dbo.evento_carga ec
            WHERE $where
        )
        SELECT COUNT(*) AS total
        FROM (
            SELECT fecha, voucher_key
            FROM base
            GROUP BY fecha, voucher_key
        ) g;
    ";
    $stmt = sqlsrv_query($conn, $countSql, $params);
    if (!$stmt) json_out(['error' => 'Error contando grupos', 'sqlsrv' => sqlsrv_errors()], 500);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $total = (int)($row['total'] ?? 0);
    sqlsrv_free_stmt($stmt);

    $pages = (int)ceil($total / max($per, 1));
    $offset = ($page - 1) * $per;

    // Consulta paginada de los grupos con agregaciones
    $sql = "
        WITH base AS (
            SELECT 
                CAST(ec.fecha_carga AS date) AS fecha,
                CASE 
                    WHEN ec.voucher IS NULL OR LTRIM(RTRIM(ec.voucher)) = '' THEN '__NO_VOUCHER__'
                    ELSE LTRIM(RTRIM(ec.voucher))
                END AS voucher_key,
                ec.lineas_cargadas,
                ec.suma_valor_pagado,
                ec.suma_valor_factura,
                ec.evento_id
            FROM gestion_terceros.dbo.evento_carga ec
            WHERE $where
        ),
        agrupado AS (
            SELECT 
                fecha,
                voucher_key,
                SUM(COALESCE(lineas_cargadas,0))               AS lineas,
                SUM(COALESCE(suma_valor_pagado,0))             AS valor_pagado,
                SUM(COALESCE(suma_valor_factura,0))            AS valor_factura,
                COUNT(*)                                        AS eventos,
                STRING_AGG(CONVERT(varchar(36), evento_id), ',') AS evento_ids
            FROM base
            GROUP BY fecha, voucher_key
        )
        SELECT
            CONVERT(varchar(10), fecha, 120) AS fecha,
            CASE WHEN voucher_key='__NO_VOUCHER__' THEN 'No se tiene voucher' ELSE voucher_key END AS voucher_label,
            lineas,
            valor_pagado,
            valor_factura,
            eventos,
            evento_ids
        FROM agrupado
        ORDER BY fecha DESC, voucher_label ASC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY;
    ";

    $params2 = array_merge($params, [ (int)$offset, (int)$per ]);
    $stmt = sqlsrv_query($conn, $sql, $params2);
    if (!$stmt) json_out(['error' => 'Error consultando grupos', 'sqlsrv' => sqlsrv_errors()], 500);

    $data = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'fecha'          => $r['fecha'],
            'voucher'        => $r['voucher_label'],            // ya rotulado
            'lineas'         => (int)$r['lineas'],
            'valor_pagado'   => (float)$r['valor_pagado'],
            'valor_factura'  => (float)$r['valor_factura'],
            'eventos'        => (int)$r['eventos'],
            'evento_ids'     => $r['evento_ids'] ? explode(',', $r['evento_ids']) : [],
        ];
    }
    sqlsrv_free_stmt($stmt);

    json_out([
        'meta' => [
            'usuario_id' => $usuario,
            'page'       => $page,
            'per_page'   => $per,
            'total'      => $total,
            'pages'      => $pages,
            'filter'     => ['desde' => $desde, 'hasta' => $hasta]
        ],
        'data' => $data
    ]);
}

// 404
json_out(['error' => 'Endpoint no encontrado'], 404);
