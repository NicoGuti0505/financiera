<?php
session_start();

//Ruta
$Ruta = dirname(__FILE__);
// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', $Ruta);
// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'ajax_handler.php';
    exit;
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: " . url('inicio_sesion.php'));
    exit();
}
function buscar_inf_masivo($conn, $start_date = null, $end_date = null,$regionf_id = null) {
    $grupor_id = (int)$_SESSION['grupor_id'];
    $sql = " SELECT 
         t.id AS tercero_id,
         t.identificacion AS nit,
         tc.descripcion AS concepto2,
         vt.fac_desde,
         vt.fac_hasta,
         vt.id AS validacion_terceros_id,
		 t.regionf_id,
         ap.*
         FROM anexo_pago ap
         RIGHT JOIN validacion_terceros vt ON vt.id = ap.validacion_terceros_id
         RIGHT JOIN tercero t ON t.id = vt.tercero_id
		 join region_fomag rf on t.regionf_id = rf.id
		 join grupo_region gr on rf.grupor_id=gr.id
         LEFT JOIN evento_anexo ea ON ap.id = ea.anexo_pago_id
         RIGHT JOIN tipo_contrato tc ON tc.id = vt.tipo_contrato_id
         WHERE ap.radicado IS NULL
		 and gr.id=?";
     
    $params = [$grupor_id];



    if ($regionf_id) {
        $regionf_id = (int)$_POST['regionf_id'];
        $sql .= " AND rf.id = ?";
        $params[] = $regionf_id;
    }


    if ($start_date && $end_date) {

        $startDateTime = new DateTime($start_date);
        $endDateTime = new DateTime($end_date);
        $endDateTime->setTime(23, 59, 59);
        $sql .= " AND ea.[fecha] BETWEEN ? AND ?";
        $params[] = $startDateTime->format('Y-m-d H:i:s');
        $params[] = $endDateTime->format('Y-m-d H:i:s');
        
    } else {
        $params = [];
    }
    
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
    }
    
    return $stmt;
}

function v_campos($conn, $row, $existingRecord) {
    $descripcion = '';

    $campos = [
        'linea_rep', 'num_pedido', 'linea_dis_ped', 'plan_contable','pago_causar_memorando',
        'concepto', 'mes_servicio', 'contrato', 'crp_id', 'voucher', 'observacion'
    ];

    foreach ($campos as $campo) {
        if ($existingRecord[$campo] != $row[$campo]) {
            if (!empty($descripcion)) {
                $descripcion .= ' | ';
            }
            if (!empty($existingRecord[$campo])){
                if (!empty($row[$campo])){
                    $descripcion .="Cambio $campo de: " . $existingRecord[$campo] . " a " . $row[$campo];
                }else{
                    $descripcion .="Eliminación dato de $campo";
                }
            }else{
                if (!empty($row[$campo])){
                    $descripcion .="Diligenciamiento $campo: " . $row[$campo];
                }
            }

        }
    }

    return [
        'success' => (empty($descripcion)),
        'descripcion' => $descripcion
    ];
}

function insertInfTapa($conn, $row) {
    $usuario_logeado = $_SESSION['identificacion_usuario'];

    $campos = "radicado, linea_rep, num_pedido, linea_dis_ped, plan_contable, pago_causar_memorando, concepto, mes_servicio, contrato, crp_id, anexo_id, voucher, observacion";
    $valores = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
    $params = [
        $row['radicado'], $row['linea_rep'], $row['num_pedido'], $row['linea_dis_ped'],
        $row['plan_contable'], $row['pago_causar_memorando'], $row['concepto'], $row['mes_servicio'],
        $row['contrato'], $row['crp_id'], $row['anexo_id'], $row['voucher'], $row['observacion']
    ];

    $insertSql = "INSERT INTO anexo_pago ($campos) VALUES ($valores)";
    
    $insertStmt = sqlsrv_query($conn, $insertSql, $params);
    if ($insertStmt === false) {
        throw new Exception("Error al insertar en anexo_pago: " . print_r(sqlsrv_errors(), true));
    }

    $sql = "SELECT id FROM anexo_pago WHERE radicado = ? ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql, [$row['radicado']]);
    
    if ($stmt === false) {
        throw new Exception("Error al seleccionar ID de anexo_pago: " . print_r(sqlsrv_errors(), true));
    }

    $anexo_pago_row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($anexo_pago_row === null) {
        throw new Exception("No se pudo obtener el ID de anexo_pago.");
    }
    
    $anexo_pago_id = $anexo_pago_row['id'];

    $eventoSql = "
        INSERT INTO evento_anexo (anexo_pago_id, usuario_id, evento, fecha, descripcion)
        VALUES (?, ?, ?, SYSDATETIME(), ?)
    ";
    $eventoParams = [
        $anexo_pago_id,
        $usuario_logeado,
        'Creación',
        'Creación'
    ];
    
    $eventoStmt = sqlsrv_query($conn, $eventoSql, $eventoParams);
    if ($eventoStmt === false) {
        throw new Exception("Error al insertar en evento_anexo: " . print_r(sqlsrv_errors(), true));
    }
    
    return $eventoStmt;
}

function verData($data, $conn, $validado = false) {
    $usuario_logeado = $_SESSION['identificacion_usuario'];
    try {
        $existingRecords = [];
        $newRecords = [];
        $updatedRecords = [];
        $invalidRecords = [];
        $noVoucherRecords = [];

        foreach ($data as $row) {
            // Verificar si tiene radicado o voucher
            if (empty($row['radicado']) && empty($row['voucher'])) {
                $invalidRecords[] = $row;
                continue;
            }

            // Buscar usando NIT y factura inicial
            $sql = "SELECT 
    t.id AS tercero_id,
    t.identificacion AS nit,
    tc.descripcion AS concepto2,
    vt.fac_desde,
    vt.fac_hasta,
    vt.id AS validacion_terceros_id,
    ap.*
FROM anexo_pago ap
INNER JOIN validacion_terceros vt ON vt.id = ap.validacion_terceros_id
INNER JOIN tercero t ON t.id = vt.tercero_id
LEFT JOIN evento_anexo ea ON ea.id = ea.anexo_pago_id
INNER JOIN tipo_contrato tc ON tc.id = vt.tipo_contrato_id
WHERE t.identificacion = ? AND vt.fac_desde = ?";

$params = [$row['nit'], $row['fac_desde']];
$stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Error al buscar registros existentes");
            }
            
            if ($existingRecord = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Verificar si tenía radicado y no se añadió voucher
                if (!empty($existingRecord['radicado']) && empty($row['voucher'])) {
                    $noVoucherRecords[] = $row;
                    continue;
                }

                $val = v_campos($conn, $row, $existingRecord);
                if (!$val['success']) {
                    $row['usuario_id'] = $usuario_logeado;
                    $row['descripcion'] = $val['descripcion'];
                    $row['id'] = $existingRecord['id'];
                    $updatedRecords[] = $row;
                } else {
                    $existingRecords[] = $row;
                }
            } else {
                $row['usuario_id'] = $usuario_logeado;
                $newRecords[] = $row;
            }
        }

        if (!empty($existingRecords) && !$validado) {
            return [
                'success' => false,
                'message' => 'Se encontraron registros existentes. Por favor, verifique la información.',
                'existingRecords' => $existingRecords,
                'invalidCount' => count($invalidRecords),
                'noVoucherCount' => count($noVoucherRecords)
            ];
        }

        if (!empty($invalidRecords) || !empty($noVoucherRecords)) {
            return [
                'success' => false,
                'message' => 'Hay registros que no cumplen con los requisitos',
                'invalidCount' => count($invalidRecords),
                'noVoucherCount' => count($noVoucherRecords)
            ];
        }

        return enviardata($conn, $newRecords, $updatedRecords);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al procesar los datos: ' . $e->getMessage()
        ];
    }
}

function enviardata($conn, $newRecords, $updatedRecords) {
    try {
        if (!empty($newRecords)) {
            newData($newRecords, $conn);
        }
        if (!empty($updatedRecords)) {
            upData($updatedRecords, $conn);
        }

        return [
            'success' => true,
            'message' => 'Datos guardados exitosamente',
            'newRecords' => count($newRecords),
            'updatedRecords' => count($updatedRecords)
        ];
    } catch (Exception $e) {
        throw $e;
    }
}

function newData($data, $conn) {    
    foreach ($data as $row) {
        try {
            if (sqlsrv_begin_transaction($conn) === false) {
                throw new Exception("No se pudo iniciar la transacción");
            }

            $result = insertInfTapa($conn, $row);
            
            if ($result === false) {
                throw new Exception("Error al insertar en anexo_pago y evento_anexo");
            }

            if (sqlsrv_commit($conn) === false) {
                throw new Exception("No se pudo confirmar la transacción");
            }

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            throw $e;
        }
    }
}

function upData($data, $conn) {
    $usuario_logeado = $_SESSION['identificacion_usuario'];
    $fecha_actual = date('Y-m-d H:i:s');
    
    foreach ($data as $row) {
        try {
            if (sqlsrv_begin_transaction($conn) === false) {
                throw new Exception("No se pudo iniciar la transacción");
            }

            $updateSql = "UPDATE anexo_pago SET 
                linea_rep = ?, num_pedido = ?, linea_dis_ped = ?, plan_contable = ?, 
                pago_causar_memorando = ?, concepto = ?, mes_servicio = ?, contrato = ?, 
                crp_id = ?, radicado = ?, voucher = ?, observacion=?
                WHERE id = ?";
            $updateParams = [
                $row['linea_rep'], $row['num_pedido'], $row['linea_dis_ped'], $row['plan_contable'],
                 $row['pago_causar_memorando'], $row['concepto'], $row['mes_servicio'], $row['contrato'],
                 $row['crp_id'], $row['radicado'], $row['voucher'], $row['observacion'], $row['id']
            ];
            $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
            
            if ($updateStmt === false) {
                throw new Exception("Error al actualizar anexo_pago: " . print_r(sqlsrv_errors(), true));
            }

            $insertSql = "INSERT INTO evento_anexo (anexo_pago_id, usuario_id, evento, fecha, descripcion) VALUES (?, ?, ?, ?, ?)";
            $insertParams = [$row['id'], $row['usuario_id'], 'Actualización', $fecha_actual, $row['descripcion']];
            $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

            if ($insertStmt === false) {
                throw new Exception("Error al insertar en evento_anexo: " . print_r(sqlsrv_errors(), true));
            }

            if (sqlsrv_commit($conn) === false) {
                throw new Exception("No se pudo confirmar la transacción");
            }

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            throw $e;
        }
    }
}
?>
