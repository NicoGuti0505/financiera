<?php
session_start();
require_once '../../config.php';
if (!isset($_SESSION['tipo_usuario_id']) || ($_SESSION['tipo_usuario_id'] != 7 && $_SESSION['tipo_usuario_id'] != 1)) {
    http_response_code(403);
    exit('No autorizado');
}

// Cambiar a la base de datos correcta
sqlsrv_query($conn, "USE Vacunacion;");

$sql = "SELECT Region, Departamento, Municipio, HabitaEnMunicipioDeRiesgo,
PrimerNombre, SegundoNombre, PrimerApellido, SegundoApellido,
TipoDocumento, NumeroDocumento, DocenteCotizante, Sexo, FechaNacimiento,
EdadEnMeses, EdadCumplida, FechaAplicacionMinisterio, FechaAplicacionDepartamento,
NombreIpsVacunacion, CodigoHabilitacionIpsVacunacion, corte
FROM Vacunacion.dbo.VacunacionFiebreAmarilla";

$headers = [
    'Region','Departamento','Municipio','HabitaEnMunicipioDeRiesgo',
    'PrimerNombre','SegundoNombre','PrimerApellido','SegundoApellido',
    'TipoDocumento','NumeroDocumento','DocenteCotizante','Sexo','FechaNacimiento',
    'EdadEnMeses','EdadCumplida','FechaAplicacionMinisterio','FechaAplicacionDepartamento',
    'NombreIpsVacunacion','CodigoHabilitacionIpsVacunacion','corte'
];

$stmt = sqlsrv_query($conn, $sql, [], ['Scrollable' => SQLSRV_CURSOR_FORWARD]);
if ($stmt === false) {
    http_response_code(500);
    exit('Error consultando datos: ' . print_r(sqlsrv_errors(), true));
}

$filename = 'reporte_vacunacion_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// BOM para que Excel abra UTF-8 correctamente
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, $headers);

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $line = [];
    foreach ($headers as $key) {
        $value = $row[$key] ?? '';
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        $line[] = $value;
    }
    fputcsv($out, $line);
}
sqlsrv_free_stmt($stmt);
fclose($out);
exit;
