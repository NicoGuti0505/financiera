<?php
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

header('Content-Type: text/csv; charset=utf-8');

// ✅ Determinar la fecha: por defecto, el día anterior
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d', strtotime('-1 day'));
$nombreArchivo = 'pagos_' . str_replace('-', '', $fecha) . '.csv';

header("Content-Disposition: attachment; filename=$nombreArchivo");

$output = fopen('php://output', 'w');

// Encabezados en el mismo formato que el archivo original
$headers = [
    'ID', 'Modalidad', 'NIT', 'Nombre', 'Prefijo', 'No Fact', 'Prefijo Factura',
    'Fecha de factura', 'Fecha de Radicacion', 'Mes - Año de radicacion',
    'Valor Factura', 'Valor Pagado', '%', 'Estado', 'Voucher',
    'Fecha de Pago', 'Fuente de origen', 'Observacion'
];

fputcsv($output, $headers, ';');

// Consulta con la fecha dinámica
$sql = "
SELECT 
    id AS [ID],
    modalidad AS [Modalidad],
    nit AS [NIT],
    nombre_prest AS [Nombre],
    prefijo AS [Prefijo],
    no_fact AS [No Fact],
    num_factura AS [Prefijo Factura],
    fecha_factura AS [Fecha de factura],
    fecha_radicacion AS [Fecha de Radicacion],
    mes_anio_radicacion AS [Mes - Año de radicacion],
    valor_factura AS [Valor Factura],
    valor_pagado AS [Valor Pagado],
    porcentaje_pago AS [%],
    estado AS [Estado],
    voucher AS [Voucher],
    feccha_pago AS [Fecha de Pago],
    fuente_origen AS [Fuente de origen],
    observacion AS [Observacion]
FROM pagos
WHERE CONVERT(DATE, fecha) = ?
";

$params = [$fecha];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    fputcsv($output, ['Error al ejecutar la consulta'], ';');
    fclose($output);
    exit;
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $line = array_map(function ($val) {
        if (is_null($val)) return '';
        if ($val instanceof DateTime) return $val->format('d/m/Y');
        return trim(preg_replace('/[\r\n]+/', ' ', (string)$val));
    }, $row);
    fputcsv($output, $line, ';');
}

fclose($output);
exit;
