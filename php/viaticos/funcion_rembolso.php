<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuÃ¡ntos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuraciÃ³n de la conexiÃ³n
require_once str_repeat('../', $levelsUp) . 'config.php';

// Verificar autenticaciÃ³n
if (!isset($_SESSION['usuario_autenticado'])) {
    header('Location:'. str_repeat('../', $levelsUp) .'inicio_sesion.php');
    exit();
}


// Verifica si se estÃ¡ realizando una solicitud POST con la acciÃ³n correcta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_next_radicado') {
    try {


        // Consulta para obtener el Ãºltimo radicado como entero (ignorando decimales)
        $sql = "
            SELECT MAX(CAST(FLOOR(CAST(rad_via AS FLOAT)) AS INT)) AS last_radicado
            FROM solicitudes
            WHERE ISNUMERIC(rad_via) = 1
        ";

        $stmt = sqlsrv_query($conn, $sql);

        // Verifica si la consulta fallÃ³
        if ($stmt === false) {
            throw new Exception('Error al ejecutar la consulta SQL: ' . print_r(sqlsrv_errors(), true));
        }

        // Obtiene el resultado de la consulta
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        // Verifica si se obtuvieron datos
        if ($row === false) {
            throw new Exception('No se encontrÃ³ el Ãºltimo radicado.');
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

    // Validar que el nÃºmero de documento no estÃ© vacÃ­o
    if (empty($numero_documento)) {
        echo json_encode(['error' => 'El nÃºmero de documento no puede estar vacÃ­o']);
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
// Si la tabla estÃ¡ vacÃ­a, comienza desde 1

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

    // Validar que el cÃ³digo DANE no estÃ© vacÃ­o
    if (empty($codigo_dane)) {
        echo json_encode(['error' => 'El cÃ³digo DANE del municipio no puede estar vacÃ­o']);
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
        echo json_encode(['error' => 'No se encontrÃ³ informaciÃ³n para el municipio seleccionado']);
        exit;
    }

    // Retornar los datos en formato JSON
    echo json_encode($municipio);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
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

$region = isset($_POST['regional'])
    ? (int) preg_replace('/[^0-9]/', '', $_POST['regional'])
    : null;

$departamento = isset($_POST['departamento'])
    ? trim($_POST['departamento'])
    : null;

$municipio = isset($_POST['ciudad'])
    ? trim($_POST['ciudad'])
    : null;

    // Validar campos obligatorios
    if (empty($id_titular) || empty($nombre_repre) || empty($segundo_n)|| empty($primer_p)|| empty($segundo_p)|| empty($t_banco) ||
        empty($c) || empty($t_cuenta) || empty($n_cuenta) || empty($url)|| empty($val_rembolso)) {
        echo json_encode(['error' => 'Todos los campos son obligatorios.']);
        exit;
    }

    try {
        $fecha_actual = date('Y-m-d H:i:s');
        
    // Asegurar que el radicado no estÃ© repetido; si lo estÃ¡, calcular uno nuevo
        $check_sql = "SELECT COUNT(*) AS total FROM solicitudes WHERE rad_via = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, [$radicado]);
        $check_row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);

        // Si ya existe, se obtiene el siguiente valor disponible (solo parte entera)
        if ($check_row && $check_row['total'] > 0) {
            $sql_next = "
                SELECT ISNULL(MAX(CAST(FLOOR(CAST(rad_via AS FLOAT)) AS INT)), 0) + 1 AS next_radicado
                FROM solicitudes
                WHERE ISNUMERIC(rad_via) = 1
            ";
            $stmt_next = sqlsrv_query($conn, $sql_next);
            $row_next = sqlsrv_fetch_array($stmt_next, SQLSRV_FETCH_ASSOC);
            $radicado = $row_next['next_radicado'];
        }


        $sql = "INSERT INTO solicitudes (
            numero_identificacion_titular, nombre, entidad_bancaria, numero_identificacion,
            tipo_cuenta, numero_cuenta, url_drive, segundo_n, primer_p, segundo_p,
            val_rembolso, proceso_tercero, rad_via,
            region, departamento, municipio
        )
        OUTPUT INSERTED.radicado AS radicado_sistema
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $c, $nombre_repre, $t_banco, $id_titular,
            $t_cuenta, $n_cuenta, $url, $segundo_n, $primer_p, $segundo_p,
            $val_rembolso, 'viaticos', $radicado,
            $region,          // <— número limpio
            $departamento,    // <— texto limpio
            $municipio        // <— texto limpio
        ];


    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new Exception('Error al insertar datos en solicitudes: ' . print_r(sqlsrv_errors(), true));
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row || !isset($row['radicado_sistema'])) {
        throw new Exception('No se pudo obtener el RADICADO del sistema.');
    }
    $radicado_sistema = (string)$row['radicado_sistema'];

    // 2) (SIN CAMBIOS) Actualizar datos del afiliado
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
    $params_update = [$direccion, $t_identificacion,$primer_nombre,$segundo_nombre,$primer_apellido,$segundo_apellido,
                    $telefono_fijo, $telefono_celular, $correo, $ciudad_residencia ,$c];
    $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);
    if ($stmt_update === false) {
        throw new Exception('Error al actualizar los datos en afiliado.');
    }

    // 3) Evento usando el RADICADO oficial (NO rad_via)
    $usuario_logeado = $_SESSION['identificacion_usuario'] ?? null;
    $fecha_actual    = date('Y-m-d H:i:s');
    $sql_evento      = "INSERT INTO evento_solicitudes (radicado, id_usuario, evento, fecha_solicitud) 
                        VALUES (?, ?, ?, ?)";
    $params_evento   = [$radicado_sistema, $usuario_logeado, 'creacion_viaticos', $fecha_actual];
    $stmt_evento     = sqlsrv_query($conn, $sql_evento, $params_evento);
    if ($stmt_evento === false) {
        echo json_encode(['error' => 'Error al insertar en evento_solicitudes: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'message'  => "Evento registrado correctamente.",
        'radicado' => $radicado_sistema, // oficial
        'rad_via'  => (string)$radicado   // opcional informativo
    ]);
    exit;

   
    } catch (Exception $e) {
        // Si ocurre un error, retornar el mensaje de error
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
    

}



// Guardado completo de viáticos en tablas propias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_viaticos') {
    $pick = function (array $keys, $default = null) {
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                return is_array($_POST[$key]) ? $_POST[$key] : trim((string) $_POST[$key]);
            }
        }
        return $default;
    };

    $checksToString = function (array $keys) {
        $out = [];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $out[] = $key;
            }
        }
        return implode(',', $out);
    };

    $toNumber = function ($value) {
        if ($value === null) {
            return null;
        }
        $clean = preg_replace('/[^0-9]/', '', (string) $value);
        return $clean === '' ? null : $clean;
    };

    $radicado = $toNumber($pick(['radicado']));
    $valRembolsoNumber = $toNumber($pick(['val_rembolso']));

    // Si no hay radicado o est� repetido, calcular uno nuevo (solo parte num�rica)
    $sqlNextRadicado = "
        SELECT ISNULL(MAX(CAST(FLOOR(CAST(rad_via AS FLOAT)) AS INT)), 0) + 1 AS next_radicado
        FROM solicitudes
        WHERE ISNUMERIC(rad_via) = 1
    ";
    if (!$radicado) {
        $stmtNext = sqlsrv_query($conn, $sqlNextRadicado);
        $rowNext = $stmtNext ? sqlsrv_fetch_array($stmtNext, SQLSRV_FETCH_ASSOC) : null;
        $radicado = $rowNext && isset($rowNext['next_radicado']) ? (string) $rowNext['next_radicado'] : null;
    } else {
        $stmtCheck = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM solicitudes WHERE rad_via = ?", [$radicado]);
        $rowCheck = $stmtCheck ? sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC) : null;
        if ($rowCheck && (int) $rowCheck['total'] > 0) {
            $stmtNext = sqlsrv_query($conn, $sqlNextRadicado);
            $rowNext = $stmtNext ? sqlsrv_fetch_array($stmtNext, SQLSRV_FETCH_ASSOC) : null;
            $radicado = $rowNext && isset($rowNext['next_radicado']) ? (string) $rowNext['next_radicado'] : $radicado;
        }
    }

    if (!$radicado) {
        echo json_encode(['error' => 'No se pudo generar el radicado. Int�ntelo nuevamente.']);
        exit;
    }

    $discapacidad = $checksToString(['fisica', 'visual', 'auditiva', 'intelectual', 'psicosocial', 'sordoceguera', 'multiple', 'no']);
    $afiliacion = $checksToString(['Activo', 'Protección_Laboral', 'Protecci�n_Laboral', 'Proteccion_Laboral', 'Suspendido', 'Retirado']);
    $soportes = $checksToString(['cop_identificacion', 'orden_medica', 'soporte_pro', 'cert_bancaria', 'doc_id_titular', 'fallo_tutela', 'aut_tuto', 'cop_documental', 'facturas_apro']);
    $pertinencia_medios = $checksToString(['Intermunicipal', 'Fluvial', 'Aereo', 'Aéreo', 'AǸreo', 'A�reo', 'Otros']);

    $sqlSolicitud = "INSERT INTO viaticos_solicitudes (
        radicado, fecha_solicitud, regional, departamento, ciudad,
        nombre, t_identificacion, numero_identificacion, fe_na, edad,
        direccion, barrio, telefono_fijo, telefono_celular, correo, ciudad_residencia, departamento_res,
        discapacidad, motivo_des, tutela, numero_tutela, val_rembolso,
        nombre_acom, parentesco_acom, tipo_doc, numero_idn, fec_na, tel_acom,
        nombre_repre, segundo_n, primer_p, segundo_p, parentesco_pago, id_titular, t_cuenta, t_banco, n_cuenta, url,
        nom_entidad, cargo, fecha_na, regional_res,
        afiliacion_estado, oportunidad, completitud, observaciones,
        soportes, fomag, intermunicipal, fluvial, aereo, otros, can,
        hospedaje, criterio_medico, criterio_administrativo, can1,
        alimentacion, alimentacion_tute, can2,
        acompanante, men_18, may_65, discapacidad_acom,
        otro_opc, otro_text, can3,
        pertinencia
    ) OUTPUT INSERTED.id AS id VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?
    );";

    $paramsSolicitud = [
        $radicado,
        $pick(['fecha_solicitud']),
        $pick(['regional']),
        $pick(['departamento']),
        $pick(['ciudad']),
        $pick(['nombre']),
        $pick(['t_identificacion']),
        $pick(['c']),
        $pick(['fe_na']),
        $pick(['edad']),
        $pick(['Dirección','Direcci�n','direccion','DirecciÃ³n']),
        $pick(['Barrio','barrio']),
        $pick(['Telefono_Fijo','telefono_fijo','Teléfono_Fijo']),
        $pick(['telefono_Celular','Telefono_Celular','telefono_celular']),
        $pick(['correo']),
        $pick(['ciudad_residencia']),
        $pick(['departamento_res']),
        $discapacidad,
        $pick(['motivo_des']),
        $pick(['tutela']),
        $pick(['N�','N?','N°','N�']),
        $valRembolsoNumber,
        $pick(['nombre_acom']),
        $pick(['Parentesco']),
        $pick(['tipo_doc']),
        $pick(['numero_idn']),
        $pick(['fec_na']),
        $pick(['tel_acom']),
        $pick(['nombre_repre']),
        $pick(['segundo_n']),
        $pick(['primer_p']),
        $pick(['segundo_p']),
        $pick(['Parentesco_pago']),
        $pick(['id_titular']),
        $pick(['t_cuenta']),
        $pick(['t_banco']),
        $pick(['n_cuenta']),
        $pick(['url']),
        $pick(['nom_entidad']),
        $pick(['cargo']),
        $pick(['fecha_na']),
        $pick(['regional_res']),
        $afiliacion,
        $pick(['Oportunidad']),
        $pick(['Completitud']),
        $pick(['Observaciones']),
        $soportes,
        $pick(['FOMAG']),
        $pick(['Intermunicipal']),
        $pick(['Fluvial']),
        $pick(['A�reo','Aéreo','AǸreo','A�reo']),
        $pick(['Otros']),
        $pick(['can']),
        $pick(['Hospedaje']),
        $pick(['Criterio_medico']),
        $pick(['Criterio_administrativo']),
        $pick(['can1']),
        $pick(['Alimentaci�n','Alimentación','Alimentacion']),
        $pick(['Alimentaci�n_tute','Alimentación_tute','Alimentacion_tute']),
        $pick(['can2']),
        $pick(['Acompa�ante','Acompañante','Acompanante']),
        $pick(['men_18']),
        $pick(['may_65']),
        $pick(['Discapacidad']),
        $pick(['Otro_opc']),
        $pick(['otro_text']),
        $pick(['can3']),
        $pick(['pertinencia'])
    ];

    sqlsrv_begin_transaction($conn);
// 👇 Añadir esto, antes de $sqlSolicitudBasica / $paramsSolicitudBasica
$regionNum = $toNumber($pick(['regional']));            // solo dígitos (o null)
$deptoText = trim((string) $pick(['departamento']));    // texto limpio
$muniText  = trim((string) $pick(['ciudad']));          // texto limpio

    // INSERT a solicitudes devolviendo el RADICADO oficial (PK)
    $sqlSolicitudBasica = "INSERT INTO solicitudes (
        numero_identificacion_titular, nombre, entidad_bancaria, numero_identificacion,
        tipo_cuenta, numero_cuenta, url_drive, segundo_n, primer_p, segundo_p,
        val_rembolso, proceso_tercero, rad_via,
        region, departamento, municipio
    )
    OUTPUT INSERTED.radicado AS radicado_sistema
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $paramsSolicitudBasica = [
        $pick(['c']),
        $pick(['nombre_repre']),
        $pick(['t_banco']),
        $pick(['id_titular']),
        $pick(['t_cuenta']),
        $pick(['n_cuenta']),
        $pick(['url']),
        $pick(['segundo_n']),
        $pick(['primer_p']),
        $pick(['segundo_p']),
        $valRembolsoNumber,
        'viaticos',
        $radicado,      // rad_via
        $regionNum,     // ✅ SOLO número
        $deptoText,     // ✅ texto
        $muniText       // ✅ texto
    ];



$stmtSolicitudBasica = sqlsrv_query($conn, $sqlSolicitudBasica, $paramsSolicitudBasica);
if ($stmtSolicitudBasica === false) {
    sqlsrv_rollback($conn);
    echo json_encode(['error' => 'Error al insertar en solicitudes: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}
$rowBase = sqlsrv_fetch_array($stmtSolicitudBasica, SQLSRV_FETCH_ASSOC);
if (!$rowBase || !isset($rowBase['radicado_sistema'])) {
    sqlsrv_rollback($conn);
    echo json_encode(['error' => 'No se pudo obtener el RADICADO del sistema.']);
    exit;
}
$radicado_sistema = (string)$rowBase['radicado_sistema'];

// Insert a viaticos_solicitudes (tu SQL ya trae OUTPUT INSERTED.id)
$stmtSolicitud = sqlsrv_query($conn, $sqlSolicitud, $paramsSolicitud);
if ($stmtSolicitud === false) {
    sqlsrv_rollback($conn);
    echo json_encode(['error' => 'Error al insertar solicitud: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}
$rowSolicitud = sqlsrv_fetch_array($stmtSolicitud, SQLSRV_FETCH_ASSOC);
$solicitudId = isset($rowSolicitud['id']) ? (int) $rowSolicitud['id'] : null;
if (!$solicitudId) {
    sqlsrv_rollback($conn);
    echo json_encode(['error' => 'No se pudo obtener el ID de la solicitud insertada.']);
    exit;
}

// Inserta mecanismos (sin cambios)
$sqlMecanismo = "INSERT INTO viaticos_mecanismos (solicitud_id, fecha_m, tiempo_m, departamento_a, municipio_a, ips_a) VALUES (?, ?, ?, ?, ?, ?)";
$mecanismos = [
    ['fecha_m','tiempo_m','Departamento_a','Municipio_a','IPS_a'],
    ['fecha_m1','tiempo_m1','Departamento_a1','Municipio_a1','IPS_a1'],
    ['fecha_m2','tiempo_m2','Departamento_a2','Municipio_a2','IPS_a2'],
    ['fecha_m3','tiempo_m3','Departamento_a3','Municipio_a3','IPS_a3'],
    ['fecha_m4','tiempo_m4','Departamento_a4','Municipio_a4','IPS_a4']
];
foreach ($mecanismos as [$f,$t,$d,$m,$i]) {
    $vals = [$pick([$f]), $pick([$t]), $pick([$d]), $pick([$m]), $pick([$i])];
    if (array_filter($vals)) {
        $params = array_merge([$solicitudId], $vals);
        $stmt = sqlsrv_query($conn, $sqlMecanismo, $params);
        if ($stmt === false) {
            sqlsrv_rollback($conn);
            echo json_encode(['error' => 'Error al insertar mecanismos: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }
    }
}

// Inserta reembolsos (sin cambios)
$sqlReembolso = "INSERT INTO viaticos_reembolsos (solicitud_id, servicio, departamento_atencion, municipio_atencion, cantidad, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?, ?, ?)";
for ($i = 1; $i <= 6; $i++) {
    $servicio = $pick(["Servicio{$i}"]);
    $deptAt = $pick(["Departamento_at{$i}"]);
    $munAt = $pick(["Municipio_at{$i}"]);
    $cantidades = $_POST["cantidad{$i}"] ?? [];
    $valorUnitario = $_POST["valor_unitario{$i}"] ?? [];
    $valorTotal = $_POST["valor_total{$i}"] ?? [];
    if (!is_array($cantidades)) { $cantidades = [$cantidades]; }
    if (!is_array($valorUnitario)) { $valorUnitario = [$valorUnitario]; }
    if (!is_array($valorTotal)) { $valorTotal = [$valorTotal]; }
    $max = max(count($cantidades), count($valorUnitario), count($valorTotal));
    for ($j = 0; $j < $max; $j++) {
        $c = $cantidades[$j] ?? null;
        $vu = $valorUnitario[$j] ?? null;
        $vt = $valorTotal[$j] ?? null;
        if (!empty($servicio) || !empty($deptAt) || !empty($munAt) || !empty($c) || !empty($vu) || !empty($vt)) {
            $params = [$solicitudId, $servicio, $deptAt, $munAt, $c, $vu, $vt];
            $stmt = sqlsrv_query($conn, $sqlReembolso, $params);
            if ($stmt === false) {
                sqlsrv_rollback($conn);
                echo json_encode(['error' => 'Error al insertar reembolsos: ' . print_r(sqlsrv_errors(), true)]);
                exit;
            }
        }
    }
}

// Evento: usar el RADICADO oficial (no rad_via)
$usuarioLogeado = $_SESSION['identificacion_usuario'] ?? null;
$sqlEvento = "INSERT INTO evento_solicitudes (radicado, id_usuario, evento, fecha_solicitud) 
              VALUES (?, ?, ?, ?)";
$paramsEvento = [$radicado_sistema, $usuarioLogeado, 'creacion_viaticos', date('Y-m-d H:i:s')];
$stmtEvento = sqlsrv_query($conn, $sqlEvento, $paramsEvento);
if ($stmtEvento === false) {
    sqlsrv_rollback($conn);
    echo json_encode(['error' => 'Error al registrar el evento: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

sqlsrv_commit($conn);
echo json_encode([
    'success'      => true,
    'solicitud_id' => $solicitudId,
    'radicado'     => $radicado_sistema, // oficial
    'rad_via'      => (string)$radicado   // opcional informativo
]);
exit;

}