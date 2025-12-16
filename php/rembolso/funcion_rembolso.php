<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_autenticado'])) {
    header('Location:'. str_repeat('../', $levelsUp) .'inicio_sesion.php');
    exit();
}


// Verifica si se está realizando una solicitud POST con la acción correcta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_next_radicado') {
    try {
        // Consulta para obtener el último radicado
        $sql = "SELECT MAX(rad_rem) AS last_radicado FROM solicitudes";
        $stmt = sqlsrv_query($conn, $sql);

        // Verifica si la consulta falló
        if ($stmt === false) {
            throw new Exception('Error al ejecutar la consulta SQL: ' . print_r(sqlsrv_errors(), true));
        }

        // Obtiene el resultado de la consulta
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        // Verifica si se obtuvieron datos
        if ($row === false) {
            throw new Exception('No se encontró el último radicado.');
        }

        // Calcula el siguiente radicado
        $last_radicado = isset($row['last_radicado']) ? intval($row['last_radicado']) : 0;
        $next_radicado = $last_radicado + 1;

        // Retorna el siguiente radicado
        echo json_encode(['next_radicado' => $next_radicado]);
        exit;

    } catch (Exception $e) {
        // Retorna el error capturado
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['numero_documento'])) {
    $numero_documento = $_POST['numero_documento'];

    // Validar que el número de documento no esté vacío
    if (empty($numero_documento)) {
        echo json_encode(['error' => 'El número de documento no puede estar vacío']);
        exit;
    }

    // Consulta SQL
    $sql = "SELECT
            m.region_id,
            m.descripcion_dep,
            m.descripcion_mun,
            d.descripcion AS descripcion_tipo_documento,
            a.tipo_documento, 
            a.codigo_dane_municipio_atencion,
            a.primer_nombre,
            a.segundo_nombre,
            a.primer_apellido,
            a.segundo_apellido,
            a.estado_afiliacion,
            a.direccion_Residencia_cargue,
            a.telefono,
            a.celular_principal,
            a.correo_principal
            FROM afiliado a
            JOIN municipio m ON a.codigo_dane_municipio_atencion = m.id
            JOIN tipo_documento d ON a.tipo_documento = d.id
            WHERE a.numero_documento = ?";
;
    $params = [$numero_documento];
    $stmt = sqlsrv_query($conn, $sql, $params);

    // Validar errores en la consulta
    if ($stmt === false) {
        echo json_encode(['error' => 'Error en la consulta: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

// Obtener resultado de la consulta
$resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$resultado) {
// Si la tabla está vacía, comienza desde 1

    // Insertar nuevo afiliado con el ID calculado
    $sql_insert = "INSERT INTO afiliado (numero_documento, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, estado_afiliacion, direccion_Residencia_cargue, telefono, celular_principal, correo_principal, codigo_dane_municipio_atencion, tipo_documento)
                   VALUES ( ?, '', '', '', '', 'Activo', '', '', '', '', '', '')";

    $params_insert = [$numero_documento];
    $stmt_insert = sqlsrv_query($conn, $sql_insert, $params_insert);

    if ($stmt_insert === false) {
        echo json_encode(['error' => 'Error al crear nuevo afiliado: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    // Volver a consultar los datos insertados
    $sql_consulta = "SELECT * FROM afiliado WHERE numero_documento = ?";
    $stmt_consulta = sqlsrv_query($conn, $sql_consulta, [$numero_documento]);

    if ($stmt_consulta === false) {
        echo json_encode(['error' => 'Error al consultar el nuevo afiliado: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    $resultado = sqlsrv_fetch_array($stmt_consulta, SQLSRV_FETCH_ASSOC);
    
    // Agregar el nuevo id_afiliado a la respuesta
   
}


    

    // Consulta para obtener tipos de documento
    $sql_tipos = "SELECT id, descripcion FROM tipo_documento";
    $stmt_tipos = sqlsrv_query($conn, $sql_tipos);

    $tipos_documento = [];
    if ($stmt_tipos) {
        while ($row = sqlsrv_fetch_array($stmt_tipos, SQLSRV_FETCH_ASSOC)) {
            $tipos_documento[] = $row;
        }
    }

        // Consulta para obtener tipos de banco
    $sql_tipos = "SELECT id, descripcion FROM banco";
     $stmt_tipos = sqlsrv_query($conn, $sql_tipos);
    
     $tipos_banco = [];
     if ($stmt_tipos) {
         while ($row = sqlsrv_fetch_array($stmt_tipos, SQLSRV_FETCH_ASSOC)) {
            $tipos_banco[] = $row;
         }
     }
     
    // Consulta para obtener tipos de cuenta
    $sql_tipos = "SELECT id, descripcion FROM tipo_cuenta";
    $stmt_tipos = sqlsrv_query($conn, $sql_tipos);
   
    $tipos_cuen = [];
    if ($stmt_tipos) {
        while ($row = sqlsrv_fetch_array($stmt_tipos, SQLSRV_FETCH_ASSOC)) {
           $tipos_cuen[] = $row;
        }
    }

    $sql_tipos = "SELECT id, descripcion_mun FROM municipio";
    $stmt_tipos = sqlsrv_query($conn, $sql_tipos);
   
    $tipos_municipios = [];
    if ($stmt_tipos) {
        while ($row = sqlsrv_fetch_array($stmt_tipos, SQLSRV_FETCH_ASSOC)) {
           $tipos_municipios[] = $row;
        }
    }


     

    // Responder con datos completos
    echo json_encode([
        'datos' => $resultado,
        'tipos_documento' => $tipos_documento,
        'tipos_banco' => $tipos_banco,
        'tipos_cuen' => $tipos_cuen,
        'tipos_municipios' => $tipos_municipios
        
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_municipio_info') {
    $codigo_dane = $_POST['codigo_dane'];

    // Validar que el código DANE no esté vacío
    if (empty($codigo_dane)) {
        echo json_encode(['error' => 'El código DANE del municipio no puede estar vacío']);
        exit;
    }

    // Consulta SQL para obtener los datos del municipio
    $sql = "SELECT descripcion_dep, descripcion_mun ,region_id FROM municipio WHERE id = ?";
    $params = [$codigo_dane];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(['error' => 'Error en la consulta: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    $municipio = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$municipio) {
        echo json_encode(['error' => 'No se encontró información para el municipio seleccionado']);
        exit;
    }

    // Retornar los datos en formato JSON
    echo json_encode($municipio);
    exit;
}


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
    $motivos = isset($_POST['motivos']) ? json_decode($_POST['motivos'], true) : [];


    // Validar campos obligatorios
    if (empty($id_titular) || empty($nombre_repre) || empty($segundo_n)|| empty($primer_p)|| empty($segundo_p)|| empty($t_banco) ||
        empty($c) || empty($t_cuenta) || empty($n_cuenta) || empty($url)|| empty($val_rembolso)) {
        echo json_encode(['error' => 'Todos los campos son obligatorios.']);
        exit;
    }

    try {
        $fecha_actual = date('Y-m-d H:i:s');
        
        // Insertar datos en la tabla solicitudes
        $sql = "INSERT INTO solicitudes (numero_identificacion_titular, nombre, entidad_bancaria, numero_identificacion, tipo_cuenta, numero_cuenta, url_drive, segundo_n, primer_p, segundo_p, val_rembolso, motivo_rembolso,proceso_tercero,rad_rem)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?)";
        $params = [$c, $nombre_repre, $t_banco, $id_titular, $t_cuenta, $n_cuenta, $url, $segundo_n, $primer_p, $segundo_p, $val_rembolso, $motivos,'Rembolso',$radicado];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception('Error al insertar datos en solicitudes.');
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
            $params_evento = [$radicado2, $usuario_logeado, 'creacion_reembolso', $fecha_actual];
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