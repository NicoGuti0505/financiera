<?php

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: " . '../../../inicio_sesion.php');
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}
    $id = $_POST['identificacion'] ?? null; // Permite que factura sea null si no se proporciona
    $factura = $_POST['factura'] ?? null; // Permite que factura sea null si no se proporciona
    error_log("Identificación recibida: " . print_r($id, true));

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Identificación no proporcionada']);
        exit();
    }
    
    $sql = "SELECT vt.*,
                t.tipo_contribuyente_id,  tc.descripcion     AS tipo_contribuyente,
                t.banco_id,                b.descripcion     AS banco,
                t.tipo_cuenta_id,        tcu.descripcion     AS tipo_cuenta,
                t.ciiu_id,                 c.descripcion     AS ciiu,
                t.municipio_id,            m.descripcion_mun AS municipio,
	
                rf.descripcion,
    
                vt.valor_retener,
                vt.valor_pago,
                vt.zese,

                t.tipo_documento_id,
                t.nombre_nit,
                t.segundo_nombre,
                t.primer_apellido,
                t.segundo_apellido,
                t.direccion,
                t.telefono,
                t.num_cuenta_bancaria,
                t.identificacion,
                t.retencion
            FROM 
                validacion_terceros vt
            
            INNER JOIN tercero              t ON vt.tercero_id            =   t.id
            LEFT  JOIN tipo_contribuyente  tc ON  t.tipo_contribuyente_id =  tc.id
            LEFT  JOIN banco                b ON  t.banco_id              =   b.id
            LEFT  JOIN tipo_cuenta        tcu ON  t.tipo_cuenta_id        = tcu.id
            LEFT  JOIN ciiu                 c ON  t.ciiu_id               =   c.id
            LEFT  JOIN municipio            m ON  t.municipio_id          =   m.id
            LEFT  JOIN region               r ON  m.region_id             =   r.id
            LEFT JOIN region_fomag         rf on  t.regionf_id            =   rf.id
            WHERE t.identificacion = ?";
    
    $params = [$id];
    
    if($factura !== null){
        $sql .= ($id !== null) ? " AND " : " WHERE ";
        $sql .= "inicio_factura = ?)";
        $params[] = $factura;
    }

    error_log("SQL: " . $sql);
    error_log("Parámetros: " . print_r($params, true));

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("Error en la consulta SQL: " . print_r($errors, true));
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $errors[0]['message']]);
        exit();
    }
    
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

        $tercero_data = array_map(function($value) {
            return $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : $value;
        }, $row);

        $response = [
            'success' => true,
            'row' => $tercero_data,
            'nombre_completo' => trim($row['nombre_nit'] . ' ' . $row['segundo_nombre'] . ' ' . $row['primer_apellido'] . ' ' . $row['segundo_apellido']),
        ];
        error_log("Datos encontrados: " . print_r($response, true));
        echo json_encode($response);
    } else {
        $row = ['identificacion' => $id];
        echo json_encode(['success' => false, 'message' => 'No se encontró información para la identificación proporcionada', 'row' => $row]);
    }
