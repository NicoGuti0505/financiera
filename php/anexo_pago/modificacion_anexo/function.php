<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'ajax_handler.php';
    exit;
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: " . url('inicio_sesion.php'));
    exit();
}

function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, ',', '.');
}


function buscar_anexo($conn, $nit, $factura){
    $sql = "SELECT 
            vt.[tercero_id],
            vt.[id] AS validacion_terceros_id,
            ap.[id] AS anexo_pago_id,
            ap.[iva],
            ap.[base_iva],
            ap.[base_excenta],
            ap.[id_articulo],
            t.[identificacion],
            t.[nombre_nit] AS 'nombre',
            t.[segundo_nombre],
            t.[primer_apellido],
            t.[segundo_apellido],
            vt.[cantidad_facturas],
            vt.[valor_total],
            vt.[fac_hasta],
            t.[municipio_id]
            FROM validacion_terceros vt
            LEFT JOIN anexo_pago ap ON vt.id = ap.validacion_terceros_id
            LEFT JOIN evento_anexo ea ON ap.id = ea.anexo_pago_id
            JOIN tercero t ON vt.tercero_id = t.id
            WHERE t.[identificacion] = ? AND vt.[fac_desde] = ?";

    $params = [$nit, $factura];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
    }
    return $stmt;
}

function v_campos($conn, $row, $existingRecord) {
    $descripcion = '';

    $campos = [
        'id_articulo','iva','base_iva','base_excenta'
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
        'success' => (empty($descripcion)), // Cambia a verdadero si no hay cambios
        'descripcion' => $descripcion
    ];
}

function insertAnexoPago($conn, $row) {
    $usuario_logeado = $_SESSION['identificacion_usuario'];
    $fecha_actual = date('Y-m-d H:i:s');

    // Inserción en anexo_pago
    $camposBase = "validacion_terceros_id, id_articulo, iva, base_iva, base_excenta";
    $valoresBase = "?, ?, ?, ?, ?";
    $insertParams = [$row['val_ter_id'], $row['articulo'], $row['iva'], $row['base_iva'], $row['base_excenta']];
    $insertSql = "INSERT INTO anexo_pago ($camposBase) VALUES ($valoresBase)";
    
    $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
    if ($insertStmt === false) {
        throw new Exception("Error al insertar en anexo_pago: " . print_r(sqlsrv_errors(), true));
    }

    // Obtener el ID del último registro insertado
    $sql = "SELECT id FROM anexo_pago WHERE validacion_terceros_id = ? ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql, [$row['val_ter_id']]);
    
    if ($stmt === false) {
        throw new Exception("Error al seleccionar ID de anexo_pago: " . print_r(sqlsrv_errors(), true));
    }

    $anexo_pago_row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($anexo_pago_row === null) {
        throw new Exception("No se pudo obtener el ID de anexo_pago.");
    }
    
    $anexo_pago_id = $anexo_pago_row['id'];

    // Inserción en evento_anexo
    $Base = "anexo_pago_id, usuario_id, evento, fecha, descripcion";
    $vBase = "?, ?, ?, ?, ?";
   
    $vSql = "INSERT INTO evento_anexo ($Base) VALUES ($vBase)";
    $vParams = [$anexo_pago_id, $row['usuario_id'], 'Creación',  $fecha_actual, 'Creación'];
    
    $vStmt = sqlsrv_query($conn, $vSql, $vParams);
    if ($vStmt === false) {
        throw new Exception("Error al insertar en evento_anexo: " . print_r(sqlsrv_errors(), true));
    }
    
    return $vStmt;
}

function verData($data, $conn, $validado = false) {
    if (!$conn) {
        error_log("Error de conexión a la base de datos.");
        return [
            'success' => false,
            'message' => 'Error de conexión a la base de datos.'
        ];
    }

    $usuario_logeado = $_SESSION['identificacion_usuario'];
    try {
        $existingRecords = [];
        $newRecords = [];
        $updatedRecords = [];

        foreach ($data as $row) {
            if (!$row['id_anexo']) {
                $row['usuario_id'] = $usuario_logeado;
                $newRecords[] = $row;
                error_log("Nuevo registro encontrado: " . json_encode($row));
            } else {
                $sql = "SELECT * FROM anexo_pago WHERE id = ?";
                $stmt = sqlsrv_query($conn, $sql, [$row['id_anexo']]);
                
                if ($stmt === false) {
                    throw new Exception("Error en la consulta SQL: " . print_r(sqlsrv_errors(), true));
                }

                if ($existingRecord = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $val = v_campos($conn, $row, $existingRecord);
                    if ($val['success']) {
                        $existingRecords[] = $row;
                        error_log("Registro existente sin cambios: " . json_encode($row));
                    } else {
                        $row['usuario_id'] = $usuario_logeado;
                        $row['descripcion'] = $val['descripcion'];
                        $updatedRecords[] = $row;
                        error_log("Registro a actualizar: " . json_encode($row));
                    }
                }
            }
        }

        if (!empty($existingRecords) && !$validado) {
            error_log("Se encontraron registros existentes no validados");
            return [
                'success' => false,
                'message' => 'Se encontraron registros existentes. Por favor, verifique la información.',
                'existingRecords' => $existingRecords,
                'newRecords' => $newRecords,
                'updatedRecords' => $updatedRecords
            ];
        } else {
            error_log("Enviando datos para procesamiento");
            return enviardata($conn, $newRecords, $updatedRecords);
        }
    } catch (Exception $e) {
        error_log("Error en verData: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al procesar los datos: ' . $e->getMessage()
        ];
    }
}

function enviardata($conn, $newRecords, $updatedRecords) {
    if (!$conn) {
        error_log("Error de conexión a la base de datos en enviardata.");
        return [
            'success' => false,
            'message' => 'Error de conexión a la base de datos.'
        ];
    }

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
            'updatedRecords' => count($updatedRecords),
            'allRecords' => array_merge($newRecords, $updatedRecords)
        ];
    } catch (Exception $e) {
        error_log("Error en enviardata: " . $e->getMessage());
        throw $e;
    }
}

function newData($data, $conn) {    
    foreach ($data as $row) {
        try {
            if (sqlsrv_begin_transaction($conn) === false) {
                throw new Exception("No se pudo iniciar la transacción");
            }

            $result = insertAnexoPago($conn, $row);
            if ($result === false) {
                throw new Exception("Error al insertar en anexo_pago y evento_anexo");
            }

            if (sqlsrv_commit($conn) === false) {
                throw new Exception("No se pudo confirmar la transacción");
            }

            error_log("Nuevo registro insertado con éxito: " . json_encode($row));
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            error_log("Error en newData: " . $e->getMessage());
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

            $updateSql = "UPDATE anexo_pago SET iva = ?, base_iva = ?, base_excenta = ? WHERE id = ?";
            $updateParams = [$row['iva'], $row['base_iva'], $row['base_excenta'], $row['id_anexo']];
            $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

            if ($updateStmt === false) {
                throw new Exception("Error al actualizar anexo_pago: " . print_r(sqlsrv_errors(), true));
            }

            $insertSql = "INSERT INTO evento_anexo (anexo_pago_id, usuario_id, evento, fecha, descripcion) VALUES (?, ?, ?, ?, ?)";
            $insertParams = [$row['id_anexo'], $usuario_logeado, 'Actualización', $fecha_actual, $row['descripcion']];
            $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

            if ($insertStmt === false) {
                throw new Exception("Error al insertar en evento_anexo: " . print_r(sqlsrv_errors(), true));
            }

            if (sqlsrv_commit($conn) === false) {
                throw new Exception("No se pudo confirmar la transacción");
            }

            error_log("Registro actualizado con éxito: " . json_encode($row));
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            error_log("Error en upData con ID {$row['id_anexo']}: " . $e->getMessage());
            throw $e;
        }
    }
}

