<?php
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

function respond($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['tipo_usuario_id']) || ($_SESSION['tipo_usuario_id'] != 7 && $_SESSION['tipo_usuario_id'] != 1)) {
    respond(['success' => false, 'message' => 'No autorizado'], 403);
}

if (!isset($_SESSION['fa_actualizacion'])) {
    respond(['success' => false, 'message' => 'No hay datos validados para actualizar'], 400);
}

$data = $_SESSION['fa_actualizacion'];
$corte = $data['corte'] ?? null;
$candidatos = $data['candidatos'] ?? [];
if ($corte === null || empty($candidatos)) {
    respond(['success' => false, 'message' => 'No hay candidatos para actualizar'], 400);
}

// Cambiar a la base de datos correcta
sqlsrv_query($conn, "USE Vacunacion;");

$updateSql = "UPDATE Vacunacion.dbo.VacunacionFiebreAmarilla
SET FechaAplicacionDepartamento = ?
WHERE corte = ? AND TipoDocumento = ? AND NumeroDocumento = ?
AND FechaAplicacionMinisterio IS NULL
AND FechaAplicacionDepartamento IS NULL";

// Detectar si podemos actualizar la tabla resumen
$colsStmt = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='VacunacionResumen'");
$cols = [];
if ($colsStmt) {
    while ($c = sqlsrv_fetch_array($colsStmt, SQLSRV_FETCH_ASSOC)) {
        $cols[] = $c['COLUMN_NAME'];
    }
    sqlsrv_free_stmt($colsStmt);
}
$canUpdateResumen = in_array('Region', $cols, true)
    && in_array('Departamento', $cols, true)
    && in_array('Municipio', $cols, true)
    && in_array('HabitaEnMunicipioRiesgo', $cols, true)
    && in_array('DocenteCotizante', $cols, true)
    && in_array('Sexo', $cols, true)
    && in_array('GrupoEtario', $cols, true)
    && in_array('EtapaVacunacion', $cols, true)
    && in_array('Menor19', $cols, true)
    && in_array('Vacunados_Reporte_Departamento', $cols, true)
    && in_array('Vacunados_Totales', $cols, true)
    && in_array('Poblacion_a_Vacunar', $cols, true)
    && in_array('Poblacion_Total', $cols, true)
    && in_array('Vacunados_Reporte_Ministerio', $cols, true)
    && in_array('Porcentaje_Vacunados', $cols, true)
    && in_array('Cobertura_departamento', $cols, true)
    && in_array('Porcentaje_Vacunados_Total', $cols, true)
    && in_array('corte', $cols, true);

function clasificarGrupoEtario($edad)
{
    if (!is_int($edad)) return 'Sin Clasificar';
    if ($edad >= 0 && $edad <= 5) return 'Primera Infancia';
    if ($edad >= 6 && $edad <= 11) return 'Infancia';
    if ($edad >= 12 && $edad <= 17) return 'Adolescencia';
    if ($edad >= 18 && $edad <= 28) return 'Juventud';
    if ($edad >= 29 && $edad <= 59) return 'Adultos';
    if ($edad >= 60) return 'Vejez';
    return 'Sin Clasificar';
}

function clasificarEtapa($edadMeses)
{
    if (!is_int($edadMeses)) return 'Sin Clasificar';
    if ($edadMeses >= 0 && $edadMeses <= 12) return 'Primera Etapa';
    if ($edadMeses >= 13 && $edadMeses <= 719) return 'Segunda Etapa';
    if ($edadMeses >= 720) return 'Tercera Etapa';
    return 'Sin Clasificar';
}

function calcPercent($num, $den)
{
    if (!is_numeric($num) || !is_numeric($den) || $den <= 0) {
        return 0;
    }
    return round(($num / $den) * 100, 2);
}

$actualizados = 0;
$intentos = 0;
$resumenActualizados = 0;

if (!sqlsrv_begin_transaction($conn)) {
    respond(['success' => false, 'message' => 'No se pudo iniciar transaccion', 'sqlsrv' => sqlsrv_errors()], 500);
}

foreach ($candidatos as $row) {
    $intentos++;
    $params = [
        $row['FechaAplicacionDepartamento'],
        $corte,
        $row['TipoDocumento'],
        $row['NumeroDocumento']
    ];
    $stmt = sqlsrv_query($conn, $updateSql, $params);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        respond(['success' => false, 'message' => 'Error actualizando', 'sqlsrv' => sqlsrv_errors()], 500);
    }
    $affected = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);

    if ($affected > 0) {
        $actualizados++;
        if ($canUpdateResumen) {
            $grupo = clasificarGrupoEtario($row['EdadCumplida'] ?? null);
            $etapa = clasificarEtapa($row['EdadEnMeses'] ?? null);
            $menor19 = (is_int($row['EdadCumplida'] ?? null) && $row['EdadCumplida'] >= 20) ? 'SI' : 'NO';
            // Obtener la fila exacta para recalcular y limitar a una sola
            $selectResumen = "
                SELECT TOP 1 Vacunados_Reporte_Departamento, Vacunados_Totales, Poblacion_a_Vacunar, Poblacion_Total, Vacunados_Reporte_Ministerio
                FROM Vacunacion.dbo.VacunacionResumen
                WHERE corte = ? AND Region = ? AND Departamento = ? AND Municipio = ?
                  AND HabitaEnMunicipioRiesgo = ? AND DocenteCotizante = ? AND Sexo = ? AND GrupoEtario = ? AND EtapaVacunacion = ? AND Menor19 = ?";
            $stmtSel = sqlsrv_query($conn, $selectResumen, [
                $corte,
                $row['Region'] ?? null,
                $row['Departamento'] ?? null,
                $row['Municipio'] ?? null,
                $row['HabitaEnMunicipioDeRiesgo'] ?? null,
                $row['DocenteCotizante'] ?? null,
                $row['Sexo'] ?? null,
                $grupo,
                $etapa,
                $menor19
            ]);
            if ($stmtSel === false) {
                sqlsrv_rollback($conn);
                respond(['success' => false, 'message' => 'Error consultando resumen', 'sqlsrv' => sqlsrv_errors()], 500);
            }
            $resRow = sqlsrv_fetch_array($stmtSel, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtSel);

            if ($resRow) {
                $vacDepto = (int)($resRow['Vacunados_Reporte_Departamento'] ?? 0) + 1;
                $vacTotales = (int)($resRow['Vacunados_Totales'] ?? 0) + 1;
                $poblacionVac = (int)($resRow['Poblacion_a_Vacunar'] ?? 0);
                $poblacionTotal = (int)($resRow['Poblacion_Total'] ?? 0);
                $porcVac = calcPercent($vacDepto, $poblacionVac);
                $cobertura = $porcVac;
                $porcVacTotal = calcPercent($vacTotales, $poblacionTotal);

                $stmtR2 = sqlsrv_query($conn, "
                    UPDATE TOP (1) Vacunacion.dbo.VacunacionResumen
                    SET Vacunados_Reporte_Departamento = ?, Vacunados_Totales = ?,
                        Cobertura_departamento = ?, Porcentaje_Vacunados_Total = ?
                    WHERE corte = ? AND Region = ? AND Departamento = ? AND Municipio = ?
                      AND HabitaEnMunicipioRiesgo = ? AND DocenteCotizante = ? AND Sexo = ?
                      AND GrupoEtario = ? AND EtapaVacunacion = ? AND Menor19 = ?
                ", [
                    $vacDepto,
                    $vacTotales,
                    $cobertura,
                    $porcVacTotal,
                    $corte,
                    $row['Region'] ?? null,
                    $row['Departamento'] ?? null,
                    $row['Municipio'] ?? null,
                    $row['HabitaEnMunicipioDeRiesgo'] ?? null,
                    $row['DocenteCotizante'] ?? null,
                    $row['Sexo'] ?? null,
                    $grupo,
                    $etapa,
                    $menor19
                ]);
                if ($stmtR2 === false) {
                    sqlsrv_rollback($conn);
                    respond(['success' => false, 'message' => 'Error aplicando incremento en resumen', 'sqlsrv' => sqlsrv_errors()], 500);
                }
                $affR = sqlsrv_rows_affected($stmtR2);
                if ($affR > 0) {
                    $resumenActualizados++;
                }
                sqlsrv_free_stmt($stmtR2);
            }
        }
    }
}

if (!sqlsrv_commit($conn)) {
    sqlsrv_rollback($conn);
    respond(['success' => false, 'message' => 'No se pudo confirmar la transaccion', 'sqlsrv' => sqlsrv_errors()], 500);
}

unset($_SESSION['fa_actualizacion']);

respond([
    'success' => true,
    'message' => 'Actualizacion completada',
    'corte' => $corte,
    'intentos' => $intentos,
    'actualizados' => $actualizados,
    'resumen_actualizados' => $resumenActualizados,
    'resumen_ejecutado' => $canUpdateResumen
]);
