<?php

// Ruta al archivo de configuración
// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';


require_once __DIR__ . '/../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Forzar la codificación UTF-8
session_start();

// Forzar UTF-8 en la salida
header('Content-Type: text/html; charset=UTF-8');

// Configurar la codificación interna de PHP
mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");





if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Información General
    $regional = isset($_POST['regional']) ? htmlspecialchars(trim($_POST['regional'])) : '';
    $departamento = isset($_POST['departamento']) ? htmlspecialchars(trim($_POST['departamento'])) : '';
    $ciudad = isset($_POST['ciudad']) ? htmlspecialchars(trim($_POST['ciudad'])) : '';
    $fecha_solicitud = isset($_POST['fecha_solicitud']) ? htmlspecialchars(trim($_POST['fecha_solicitud'])) : '';
    $radicado = isset($_POST['radicado']) ? htmlspecialchars(trim($_POST['radicado'])) : '';

    // 2. Datos Generales del Solicitante
    $nombre = isset($_POST['nombre']) ? htmlspecialchars(trim($_POST['nombre'])) : '';
    $c = isset($_POST['c']) ? htmlspecialchars(trim($_POST['c'])) : '';
    $t_identificacion = isset($_POST['t_identificacion']) ? htmlspecialchars(trim($_POST['t_identificacion'])) : '';
    $direccion = isset($_POST['direccion']) ? htmlspecialchars(trim($_POST['direccion'])) : '';
    $ciudad_residencia = isset($_POST['ciudad_residencia']) ? (int) $_POST['ciudad_residencia'] : 0;
    $ciudad_descripcion = '';
    
    if ($ciudad_residencia > 0) {
        // Preparar la consulta para obtener la descripción del banco
        $sql = "SELECT descripcion_mun FROM municipio WHERE id= ?";
        $params = [$ciudad_residencia];
    
        // Ejecutar la consulta
        $stmt = sqlsrv_query($conn, $sql, $params);
    
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));  // Muestra los errores si la consulta falla
        }
    
        // Obtener la descripción
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $ciudad_descripcion = $row['descripcion_mun'];  // Asignar la descripción del banco
        } else {
            $ciudad_descripcion = 'ciudad no encontrada.';  // Si no se encuentra el banco
        }
    
        // Liberar recursos
        sqlsrv_free_stmt($stmt);
    }
    $barrio = isset($_POST['barrio']) ? htmlspecialchars(trim($_POST['barrio'])) : '';
    $telefono_fijo = isset($_POST['telefono_fijo']) ? htmlspecialchars(trim($_POST['telefono_fijo'])) : '';
    $telefono_celular = isset($_POST['telefono_celular']) ? htmlspecialchars(trim($_POST['telefono_celular'])) : '';
    $correo = isset($_POST['correo']) ? htmlspecialchars(trim($_POST['correo'])) : '';

    $motivo = isset($_POST['motivo']) ? $_POST['motivo'] : []; // Recibimos los motivos seleccionados

    // Mapeo de motivos a mensajes y coordenadas
    $mapa_motivos = [
        'servicio_salud' => ['mensaje' => 'x', 'x' => 71.6, 'y' => 44],
        'tecnologia_salud' => ['mensaje' => 'x', 'x' => 141.5, 'y' => 44],
        // Puedes agregar más opciones si es necesario
    ];

    // Datos del reembolso (uno por cada servicio)
    $orden_medica1 = isset($_POST['orden_medica1'][0]) ? $_POST['orden_medica1'][0] : '';
    $servicio1 = isset($_POST['servicio1'][0]) ? $_POST['servicio1'][0] : '';
    $cantidad1 = isset($_POST['cantidad1'][0]) ? $_POST['cantidad1'][0] : '';
    $valor_unitario1 = isset($_POST['valor_unitario1'][0]) ? $_POST['valor_unitario1'][0] : '';
    $valor_total1 = isset($_POST['valor_total1'][0]) ? $_POST['valor_total1'][0] : '';
    
    $orden_medica2 = isset($_POST['orden_medica2'][0]) ? $_POST['orden_medica2'][0] : '';
    $servicio2 = isset($_POST['servicio2'][0]) ? $_POST['servicio2'][0] : '';
    $cantidad2 = isset($_POST['cantidad2'][0]) ? $_POST['cantidad2'][0] : '';
    $valor_unitario2 = isset($_POST['valor_unitario2'][0]) ? $_POST['valor_unitario2'][0] : '';
    $valor_total2 = isset($_POST['valor_total2'][0]) ? $_POST['valor_total2'][0] : '';

    $orden_medica3 = isset($_POST['orden_medica3'][0]) ? $_POST['orden_medica3'][0] : '';
    $servicio3 = isset($_POST['servicio3'][0]) ? $_POST['servicio3'][0] : '';
    $cantidad3 = isset($_POST['cantidad3'][0]) ? $_POST['cantidad3'][0] : '';
    $valor_unitario3 = isset($_POST['valor_unitario3'][0]) ? $_POST['valor_unitario3'][0] : '';
    $valor_total3 = isset($_POST['valor_total3'][0]) ? $_POST['valor_total3'][0] : '';

    $orden_medica4 = isset($_POST['orden_medica4'][0]) ? $_POST['orden_medica4'][0] : '';
    $servicio4= isset($_POST['servicio4'][0]) ? $_POST['servicio4'][0] : '';
    $cantidad4 = isset($_POST['cantidad4'][0]) ? $_POST['cantidad4'][0] : '';
    $valor_unitario4 = isset($_POST['valor_unitario4'][0]) ? $_POST['valor_unitario4'][0] : '';
    $valor_total4 = isset($_POST['valor_total4'][0]) ? $_POST['valor_total4'][0] : '';

    $orden_medica5 = isset($_POST['orden_medica5'][0]) ? $_POST['orden_medica5'][0] : '';
    $servicio5= isset($_POST['servicio5'][0]) ? $_POST['servicio5'][0] : '';
    $cantidad5 = isset($_POST['cantidad5'][0]) ? $_POST['cantidad5'][0] : '';
    $valor_unitario5 = isset($_POST['valor_unitario5'][0]) ? $_POST['valor_unitario5'][0] : '';
    $valor_total5 = isset($_POST['valor_total5'][0]) ? $_POST['valor_total5'][0] : '';

    $orden_medica6 = isset($_POST['orden_medica6'][0]) ? $_POST['orden_medica6'][0] : '';
    $servicio6= isset($_POST['servicio6'][0]) ? $_POST['servicio6'][0] : '';
    $cantidad6 = isset($_POST['cantidad6'][0]) ? $_POST['cantidad6'][0] : '';
    $valor_unitario6 = isset($_POST['valor_unitario6'][0]) ? $_POST['valor_unitario6'][0] : '';
    $valor_total6 = isset($_POST['valor_total6'][0]) ? $_POST['valor_total6'][0] : '';

    $orden_medica7 = isset($_POST['orden_medica7'][0]) ? $_POST['orden_medica7'][0] : '';
    $servicio7= isset($_POST['servicio7'][0]) ? $_POST['servicio7'][0] : '';
    $cantidad7 = isset($_POST['cantidad7'][0]) ? $_POST['cantidad7'][0] : '';
    $valor_unitario7 = isset($_POST['valor_unitario7'][0]) ? $_POST['valor_unitario7'][0] : '';
    $valor_total7 = isset($_POST['valor_total7'][0]) ? $_POST['valor_total7'][0] : '';

    $orden_medica8 = isset($_POST['orden_medica8'][0]) ? $_POST['orden_medica8'][0] : '';
    $servicio8= isset($_POST['servicio8'][0]) ? $_POST['servicio8'][0] : '';
    $cantidad8 = isset($_POST['cantidad8'][0]) ? $_POST['cantidad8'][0] : '';
    $valor_unitario8 = isset($_POST['valor_unitario8'][0]) ? $_POST['valor_unitario8'][0] : '';
    $valor_total8 = isset($_POST['valor_total8'][0]) ? $_POST['valor_total8'][0] : '';

    $orden_medica9 = isset($_POST['orden_medica9'][0]) ? $_POST['orden_medica9'][0] : '';
    $servicio9= isset($_POST['servicio9'][0]) ? $_POST['servicio9'][0] : '';
    $cantidad9 = isset($_POST['cantidad9'][0]) ? $_POST['cantidad9'][0] : '';
    $valor_unitario9 = isset($_POST['valor_unitario9'][0]) ? $_POST['valor_unitario9'][0] : '';
    $valor_total9 = isset($_POST['valor_total9'][0]) ? $_POST['valor_total9'][0] : '';

     // 4. Información del prestador
    $factura_in = isset($_POST['factura_in']) ? htmlspecialchars(trim($_POST['factura_in'])) : '';
    $prestador = isset($_POST['prestador']) ? htmlspecialchars(trim($_POST['prestador'])) : '';
    $valor_total_solicitado = isset($_POST['valor_total_solicitado']) ? htmlspecialchars(trim($_POST['valor_total_solicitado'])) : '';
    $motivo_des = isset($_POST['motivo_des']) ? $_POST['motivo_des'] : '';

    $cop_identificacion = isset($_POST['cop_identificacion']) ? 'X' : '';
    $orden_medica = isset($_POST['orden_medica']) ? 'X' : '';
    $HC = isset($_POST['HC']) ? 'X' : '';
    $tecnologia = isset($_POST['tecnologia']) ? 'X' : '';
    $cuen_bancaria = isset($_POST['cuen_bancaria']) ? 'X' : '';
    $tit_identificacion = isset($_POST['tit_identificacion']) ? 'X' : '';
    $tutela = isset($_POST['tutela']) ? 'X' : '';
    $autorizacion = isset($_POST['autorizacion']) ? 'X' : '';
    $aut_servicio = isset($_POST['aut_servicio']) ? 'X' : '';
    $firma = isset($_POST['firma']) ? 'Formato con firma se encuentra en el one drive' : '';


    // 6. Información bancaria
    $nombre_repre = isset($_POST['nombre_repre']) ? htmlspecialchars(trim($_POST['nombre_repre'])) : '';
    $segundo_n = isset($_POST['segundo_n']) ? htmlspecialchars(trim($_POST['segundo_n'])) : '';
    $primer_p = isset($_POST['primer_p']) ? htmlspecialchars(trim($_POST['primer_p'])) : '';
    $segundo_p = isset($_POST['segundo_p']) ? htmlspecialchars(trim($_POST['segundo_p'])) : '';
    $id_titular = isset($_POST['id_titular']) ? htmlspecialchars(trim($_POST['id_titular'])) : '';
    $t_cuenta = isset($_POST['t_cuenta']) ? htmlspecialchars(trim($_POST['t_cuenta'])) : '';
    $t_banco_id = isset($_POST['t_banco']) ? (int) $_POST['t_banco'] : 0;
    $t_banco_descripcion = '';
    
    if ($t_banco_id > 0) {
        // Preparar la consulta para obtener la descripción del banco
        $sql = "SELECT descripcion FROM banco WHERE id= ?";
        $params = [$t_banco_id];
    
        // Ejecutar la consulta
        $stmt = sqlsrv_query($conn, $sql, $params);
    
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));  // Muestra los errores si la consulta falla
        }
    
        // Obtener la descripción
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $t_banco_descripcion = $row['descripcion'];  // Asignar la descripción del banco
        } else {
            $t_banco_descripcion = 'Banco no encontrado.';  // Si no se encuentra el banco
        }
    
        // Liberar recursos
        sqlsrv_free_stmt($stmt);
    }
    $n_cuenta = isset($_POST['n_cuenta']) ? htmlspecialchars(trim($_POST['n_cuenta'])) : '';
    $Parentesco = isset($_POST['Parentesco']) ? htmlspecialchars(trim($_POST['Parentesco'])) : '';

    // 7. Información de radicación
    $nom_entidad = isset($_POST['nom_entidad']) ? htmlspecialchars(trim($_POST['nom_entidad'])) : '';
    $cargo = isset($_POST['cargo']) ? htmlspecialchars(trim($_POST['cargo'])) : '';
    $departamento_res = isset($_POST['departamento_res']) ? htmlspecialchars(trim($_POST['departamento_res'])) : '';
    $regional_res = isset($_POST['regional_res']) ? htmlspecialchars(trim($_POST['regional_res'])) : '';
    $fecha_na = isset($_POST['fecha_na']) ? htmlspecialchars(trim($_POST['fecha_na'])) : '';

    // 8. Revisión y Validación
    $estado_afiliacion = isset($_POST['estado_afiliacion']) ? htmlspecialchars(trim($_POST['estado_afiliacion'])) : '';
    $observaciones = isset($_POST['observaciones']) ? htmlspecialchars(trim($_POST['observaciones'])) : '';
    $oportunidad = isset($_POST['Oportunidad']) ? $_POST['Oportunidad'] : '';
    $com_documentos = isset($_POST['com_documentos']) ? $_POST['com_documentos'] : '';
    $medico_cientifica = isset($_POST['Médico_científica']) ? $_POST['Médico_científica'] : '';
    $administrativa = isset($_POST['Administrativa']) ? $_POST['Administrativa'] : '';
    $juridica = isset($_POST['Jurídica']) ? $_POST['Jurídica'] : '';
    $referencia = isset($_POST['Referencia']) ? $_POST['Referencia'] : '';
    $auditoria_medica = isset($_POST['Auditoria_médica']) ? $_POST['Auditoria_médica'] : '';
    $fomag = isset($_POST['FOMAG']) ? $_POST['FOMAG'] : '';
    $justificacion = isset($_POST['justificacion']) ? htmlspecialchars(trim($_POST['justificacion'])) : '';

    // 9. Aprobación o Negación
    $apro_rembolso = isset($_POST['apro_rembolso']) ? $_POST['apro_rembolso'] : '';
    $val_rembolso = isset($_POST['val_rembolso']) ? htmlspecialchars(trim($_POST['val_rembolso'])) : '';
    $justi_rembolso = isset($_POST['justi_rembolso']) ? htmlspecialchars(trim($_POST['justi_rembolso'])) : '';




    // Crear una nueva instancia de FPDI
    $pdf = new FPDI();

    // Ruta del archivo PDF plantilla
    $plantilla = 'rembolso.pdf';

    // Cargar la plantilla PDF
    $pageCount = $pdf->setSourceFile($plantilla);
    $template = $pdf->importPage(1);

    // Añadir una nueva página
    $pdf->addPage();

    // Usar la plantilla
    $pdf->useTemplate($template);

// Establecer la fuente TrueType que soporta UTF-8 (dejaVu)
     // Asegúrate de tener esta fuente en tu servidor
    $pdf->SetFont('Arial', '', 5);

    $pdf->SetXY(52, 17); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $regional);

    $pdf->SetXY(82, 17); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $departamento);

    $pdf->SetXY(111, 17); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $ciudad);

    $pdf->SetXY(131, 18.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $fecha_solicitud);

    $pdf->SetXY(155, 19); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $radicado);
    
    $pdf->SetXY(65, 34.8); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $nombre);

    $pdf->SetXY(139, 34.8); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $c);

    $pdf->SetXY(135, 34.8); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $t_identificacion.' -');

    $pdf->SetXY(100, 37.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,$direccion);
    
    $pdf->SetXY(64,37.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $ciudad_descripcion);

    $pdf->SetXY(152, 37.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,$barrio);

    $pdf->SetXY(150, 40.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $telefono_fijo);

    $pdf->SetXY(113, 40.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $telefono_celular);

    $pdf->SetXY(65, 40.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $correo);

    // Recorrer los motivos seleccionados
    foreach ($motivo as $opcion) {
        if (isset($mapa_motivos[$opcion])) {
            $mensaje = $mapa_motivos[$opcion]['mensaje'];
            $x = $mapa_motivos[$opcion]['x'];
            $y = $mapa_motivos[$opcion]['y'];

            // Imprimir el mensaje en la posición específica
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 10, $mensaje);
        }
    }

    $pdf->SetXY(45.7, 56.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica1);

    $pdf->SetXY(72, 56.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio1);

    $pdf->SetXY(115, 56.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad1);

    $pdf->SetXY(130, 56.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $valor_unitario1);

    $pdf->SetXY(150, 56.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $valor_total1);

    $pdf->SetXY(45.7, 59.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica2);

    $pdf->SetXY(72, 59.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio2);

    $pdf->SetXY(115, 59.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad2);

    $pdf->SetXY(130, 59.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_unitario2);

    
    $pdf->SetXY(150, 59.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_total2);

    $pdf->SetXY(45.7, 62.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica3);

    $pdf->SetXY(72, 62.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio3);

    $pdf->SetXY(115, 62.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad3);

    $pdf->SetXY(130, 62.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $valor_unitario3);

    
    $pdf->SetXY(150, 62.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_total3);

    $pdf->SetXY(45.7, 65.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica4);

    $pdf->SetXY(72, 65.7); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio4);

    $pdf->SetXY(115, 65.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad4);

    $pdf->SetXY(130, 65.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_unitario4);

    
    $pdf->SetXY(150, 65.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_total4);

    $pdf->SetXY(45.7, 68.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica5);

    $pdf->SetXY(72, 68); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio5);

    $pdf->SetXY(115, 68.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad5);

    $pdf->SetXY(130, 68.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $valor_unitario5);

    
    $pdf->SetXY(150, 68.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $valor_total5);

    $pdf->SetXY(45.7, 71.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica6);

    $pdf->SetXY(72, 71); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio6);

    $pdf->SetXY(115, 71.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad6);

    $pdf->SetXY(130, 71.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $valor_unitario6);

    
    $pdf->SetXY(150, 71.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_total6);

    $pdf->SetXY(45.7, 74.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica7);

    $pdf->SetXY(72, 74); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio7);

    $pdf->SetXY(115, 74.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad7);

    $pdf->SetXY(130, 74.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_unitario7);

    
    $pdf->SetXY(150, 74.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_total7);

    $pdf->SetXY(45.7, 77.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica8);

    $pdf->SetXY(72, 77); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio8);

    $pdf->SetXY(115, 77.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad8);

    $pdf->SetXY(130, 77.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_unitario8);

    
    $pdf->SetXY(150, 77.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_total8);

    $pdf->SetXY(45.7, 80.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $orden_medica9);

    $pdf->SetXY(72, 80); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,   $servicio9);

    $pdf->SetXY(115, 80.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $cantidad9);

    $pdf->SetXY(130, 80.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_unitario9);

    
    $pdf->SetXY(150, 80.6); // Cambiar coordenadas según la plantilla
    $pdf->Write(0, $valor_total9);

    $pdf->SetXY(112, 83.3); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $factura_in);

    $pdf->SetXY(150, 83.3); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $valor_total_solicitado);

    $pdf->SetXY(95.1, 87.4); // Cambiar coordenadas según la plantilla
    $pdf->Write(0,  $prestador);

    $pdf->SetXY(40.5, 93.4);  // Coordenadas iniciales
    $pdf->SetX(40.5);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(127, 2, $motivo_des, 0, 'L', false);


    $pdf->SetXY(44, 111); // Posición para Copia de Identificación
    $pdf->Write(0, $cop_identificacion);
    
    $pdf->SetXY(44, 114); // Posición para Orden Médica
    $pdf->Write(0, $orden_medica);
    
    $pdf->SetXY(44, 117); // Posición para HC
    $pdf->Write(0, $HC);
    
    $pdf->SetXY(44, 120); // Posición para Tecnología
    $pdf->Write(0, $tecnologia);
    
    $pdf->SetXY(44, 123); // Posición para Cuenta Bancaria
    $pdf->Write(0, $cuen_bancaria);
    
    $pdf->SetXY(44, 125.8); // Posición para Titular Identificación
    $pdf->Write(0, $tit_identificacion);
    
    $pdf->SetXY(44, 128.7); // Posición para Tutela
    $pdf->Write(0, $tutela);
    
    $pdf->SetXY(44, 132); // Posición para Autorización
    $pdf->Write(0, $autorizacion);
    

 
    $pdf->SetXY(44, 135); // Posición para Autorización de Servicio
    $pdf->Write(0, $aut_servicio);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'b', 8); 
    $pdf->SetXY(69, 175); // Posición para Autorización de Servicio
    $pdf->Write(0, $firma);
    
    
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 5);


    $pdf->SetXY(65, 142.5); // Posición para Autorización de Servicio
    $pdf->Write(0, $nombre_repre.'  '.$segundo_n.'  '.$primer_p.'  '.$segundo_p);

    $pdf->SetXY(85, 146.6); // Posición para Autorización de Servicio
    $pdf->Write(0, $id_titular);

    $pdf->SetXY(80, 150.5); // Posición para Autorización de Servicio
    $pdf->Write(0, $t_banco_descripcion);

    if ($t_cuenta == '03') { 
        $x_pos = 151.4;   
        $y_pos = 146.3;  
    } elseif ($t_cuenta == 'SV') {  
        $x_pos = 133.5;  
        $y_pos = 146.3;  
    }

    if ($t_cuenta == '03' || $t_cuenta == 'SV') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }
    $pdf->SetXY(140, 150.4); // Posición para Autorización de Servicio
    $pdf->Write(0, $n_cuenta);

    $pdf->SetXY(143, 142.5); // Posición para Autorización de Servicio
    $pdf->Write(0, $Parentesco);

    $pdf->SetXY(53.6, 188.3); // Posición para Autorización de Servicio
    $pdf->Write(0, $nom_entidad);
    
    $pdf->SetXY(121, 188.3); // Posición para Autorización de Servicio
    $pdf->Write(0, $cargo);
    
    $pdf->SetXY(111, 191.2); // Posición para Autorización de Servicio
    $pdf->Write(0, $regional_res);
    
    $pdf->SetXY(140, 191.3); // Posición para Autorización de Servicio
    $pdf->Write(0, $fecha_na);

    $pdf->SetXY(68, 191.2); // Posición para Autorización de Servicio
    $pdf->Write(0, $departamento_res);

    if ($estado_afiliacion == 'Activo') { 
        $x_pos = 71.5;   
        $y_pos = 204;  
    } elseif ($estado_afiliacion == 'Retirado') {  
        $x_pos = 143.7;  
        $y_pos = 204;  
    }

    if ($estado_afiliacion == 'Activo' || $estado_afiliacion == 'Retirado') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    $pdf->SetXY(111, 209.6);  // Coordenadas iniciales
    $pdf->SetX(98);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(70, 2, $observaciones, 0, 'L', false);

    if ($oportunidad == 'si') { 
        $x_pos = 71.6;   
        $y_pos = 211.5;  
    } elseif ($oportunidad == 'no') {  
        $x_pos = 86.6;  
        $y_pos = 211.5;  
    }

    if ($oportunidad == 'si' || $oportunidad == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    if ($com_documentos == 'si') { 
        $x_pos = 71.6;   
        $y_pos = 214.5;  
    } elseif ($com_documentos == 'no') {  
        $x_pos = 86.6;  
        $y_pos = 214.5;  
    }

    if ($com_documentos == 'si' || $com_documentos == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    if ($medico_cientifica == 'si') { 
        $x_pos = 71.6;   
        $y_pos = 220.8;  
    } elseif ($medico_cientifica == 'no') {  
        $x_pos = 86.6;  
        $y_pos = 220.8;  
    }

    if ($medico_cientifica == 'si' || $medico_cientifica == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    if ($administrativa == 'si') { 
        $x_pos = 71.6;   
        $y_pos = 223.7;  
    } elseif ($administrativa == 'no') {  
        $x_pos = 86.6;  
        $y_pos = 223.7;  
    }

    if ($administrativa == 'si' || $administrativa == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    if ($juridica == 'si') { 
        $x_pos = 71.6;   
        $y_pos = 226.7;  
    } elseif ($juridica == 'no') {  
        $x_pos = 86.6;  
        $y_pos = 226.7;  
    }

    if ($juridica == 'si' || $juridica == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    if ($referencia == 'si') { 
        $x_pos = 134.8;   
        $y_pos = 220.8;  
    } elseif ($referencia == 'no') {  
        $x_pos = 141.9;  
        $y_pos = 220.8;  
    }

    if ($referencia == 'si' || $referencia == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    if ($auditoria_medica == 'si') { 
        $x_pos = 134.8;   
        $y_pos = 223.9;  
    } elseif ($auditoria_medica == 'no') {  
        $x_pos = 141.9;  
        $y_pos = 223.9;  
    }

    if ($auditoria_medica == 'si' || $auditoria_medica == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    if ($fomag == 'si') { 
        $x_pos = 134.8;   
        $y_pos = 227.3;  
    } elseif ($fomag == 'no') {  
        $x_pos = 141.9;  
        $y_pos = 227.3;  
    }

    if ($fomag == 'si' || $fomag == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    $pdf->SetXY(45, 233);  // Coordenadas iniciales
    $pdf->SetX(45);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(112, 2, $justificacion, 0, 'L', false);

    if ($apro_rembolso == 'si') { 
        $x_pos = 68.8;   
        $y_pos = 255;  
    } elseif ($apro_rembolso == 'no') {  
        $x_pos = 85.5;  
        $y_pos = 255;  
    }

    if ($apro_rembolso == 'si' || $apro_rembolso == 'no') {
        $pdf->SetXY($x_pos, $y_pos);  
        $pdf->Write(0, 'X');  
    }

    $pdf->SetXY(141.7, 255); // Posición para Autorización de Servicio
    $pdf->Write(0, $val_rembolso);

    $pdf->SetXY(45, 260);  // Coordenadas iniciales
    $pdf->SetX(45);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(112, 2, $justi_rembolso, 0, 'L', false);


 // Output del PDF al navegador
    $pdf->Output('D', 'Rembolso.pdf'); // 'I' para enviar al navegador
}
