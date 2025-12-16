<?php
session_start();
// Obtiene el directorio del archivo actual y divide la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $c = $_POST['c'];
    $ciudad_residencia=$_POST['ciudad_residencia'];
    $t_identificacion=$_POST['t_identificacion'];
    $primer_nombre = $_POST['primer_nombre'];
    $segundo_nombre = $_POST['segundo_nombre'];
    $primer_apellido = $_POST['primer_apellido'];
    $segundo_apellido = $_POST['segundo_apellido'];
    $nombre_repre = $_POST['nombre_repre'];
    $segundo_n = $_POST['segundo_n'];
    $primer_p = $_POST['primer_p'];
    $segundo_p = $_POST['segundo_p'];
    $t_banco = $_POST['t_banco'];
    $id_titular = $_POST['id_titular'];
    $t_cuenta = $_POST['t_cuenta'];
    $n_cuenta = $_POST['n_cuenta'];
    $url = $_POST['url'];
    $direccion = $_POST['direccion'];
    $telefono_fijo = $_POST['telefono_fijo'];
    $telefono_celular = $_POST['telefono_celular'];
    $correo = $_POST['correo'];
    $val_rembolso = $_POST['val_rembolso'];
    $radicado= $_POST['radicado'];


    // Validar campos obligatorios
    if (empty($id_titular) || empty($nombre_repre) || empty($segundo_n)|| empty($primer_p)|| empty($segundo_p)|| empty($t_banco) ||
        empty($c) || empty($t_cuenta) || empty($n_cuenta) || empty($url)|| empty($val_rembolso)) {
        echo json_encode(['error' => 'Todos los campos son obligatorios.']);
        exit;
    }

    try {
        $fecha_actual = date('Y-m-d H:i:s');
        
        // Asegurar que el radicado no esté repetido; si lo está, calcular uno nuevo
// Obtener la base del radicado (ej: 17)
        $radicado_base = trim($radicado);

        // Consultar cuántos radicados existen con ese prefijo (ej: '17.%')
        $check_sql = "
            SELECT COUNT(*) + 1 AS siguiente
            FROM solicitudes
            WHERE rad_via LIKE ? + '.%'
        ";
        $check_stmt = sqlsrv_query($conn, $check_sql, [$radicado_base]);
        $check_row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);

        // Generar nuevo radicado tipo 17.1, 17.2, etc.
        $contador = $check_row['siguiente'] ?? 1;
        $radicado_final = $radicado_base . '.' . $contador;

        // Insertar con el radicado generado
        $sql = "INSERT INTO solicitudes (
            numero_identificacion_titular, nombre, entidad_bancaria, numero_identificacion,
            tipo_cuenta, numero_cuenta, url_drive, segundo_n, primer_p, segundo_p,
            val_rembolso, proceso_tercero, rad_via
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $c, $nombre_repre, $t_banco, $id_titular,
            $t_cuenta, $n_cuenta, $url, $segundo_n, $primer_p, $segundo_p,
            $val_rembolso, 'viaticos', $radicado_final
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception('Error al insertar datos en solicitudes: ' . print_r(sqlsrv_errors(), true));
        }



        // Actualizar los datos en la tabla afiliado
        $sql_update = "UPDATE afiliado
                       SET direccion_Residencia_cargue = ?,
                            tipo_documento = ?,
                            primer_nombre= ?,
                            segundo_nombre= ?,
                            primer_apellido= ?,
                            segundo_apellido= ?,
                            telefono = ?, 
                            celular_principal = ?, 
                            correo_principal = ?,
                            codigo_dane_municipio_atencion=?
                       WHERE numero_documento = ?";
        
        $params_update = [$direccion, $t_identificacion,$primer_nombre,$segundo_nombre,$primer_apellido,$segundo_apellido,$telefono_fijo, $telefono_celular, $correo, $ciudad_residencia ,$c];
        $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);
        
        if ($stmt_update === false) {
            throw new Exception('Error al actualizar los datos en afiliado.');
        }
// 1. Obtener el último radicado
        $sql_max_radicado = "SELECT MAX(radicado) AS last_radicado FROM solicitudes";
        $stmt_max = sqlsrv_query($conn, $sql_max_radicado);
        $row = sqlsrv_fetch_array($stmt_max, SQLSRV_FETCH_ASSOC);
        $radicado2 = $row['last_radicado'] ?? null; // Si no hay radicados, será NULL

        if ($radicado2 !== null) {
            // 2. Insertar en evento_solicitudes con el último radicado obtenido
            $usuario_logeado = $_SESSION['identificacion_usuario']; // Usuario autenticado
            $fecha_actual = date('Y-m-d H:i:s'); // Fecha actual


            $sql_evento = "INSERT INTO evento_solicitudes (radicado, id_usuario, evento, fecha_solicitud) 
            VALUES (?, ?, ?, ?)";
            $params_evento = [$radicado2, $usuario_logeado, 'legalizacion', $fecha_actual];
            $stmt_evento = sqlsrv_query($conn, $sql_evento, $params_evento);

            if ($stmt_evento) {
            $response = ['success' => true, 'message' => "Evento registrado correctamente.", 'radicado' => $radicado2];
            } else {
            $response = ['error' => 'Error al insertar en evento_solicitudes.'];
            }
            } else {
            $response = ['error' => 'No se encontró un radicado existente.'];
            }

// Limpia el buffer de salida y envía la respuesta JSON
            ob_end_clean();
            echo json_encode($response);
            exit;
        
        if ($stmt_evento === false) {
            // Capturamos el error de SQL y lo mostramos
            $error = sqlsrv_errors();
            throw new Exception('Error al insertar datos en evento_solicitudes: ' . print_r($error, true));
        }
        
        echo json_encode(['success' => 'Solicitud guardada correctamente.']);
    } catch (Exception $e) {
        // Si ocurre un error, retornar el mensaje de error
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
    

}