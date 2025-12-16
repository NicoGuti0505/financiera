<?php
// PDF DE ARCHIVO TAPA
// Obtiene el directorio del archivo actual y divide la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
require_once __DIR__ . '../../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

// Función para validar el embargo
function validar_embargo($embargo) {
    if ($embargo == 1) {
        return "PAGO CON EMBARGO";
    } else {
        return "";  // Si no hay embargo, no se muestra nada
    }
}

// Función principal para generar el archivo TAPA
function pdf_tapa($host, $option, $fecha_inicio, $fecha_final) {
    // Conectar a la base de datos
    $conexion = sqlsrv_connect($host, $option);
    if (!$conexion) {
        die("ERROR: No Se Estableció La Conexión Con La Base de Datos.");
    }

    $sentencia = "WITH cte_evento AS (
                        SELECT *, ROW_NUMBER() OVER(PARTITION BY anexo_pago_id ORDER BY fecha DESC) as rn
                        FROM evento_anexo
                    )
                    SELECT ev.id AS evento_id, ev.anexo_pago_id, ev.fecha, ev.usuario_id,
                           ap.*, vt.*, t.*, ap.observacion AS ap_observacion,
                           vt.observacion AS vt_observacion, vt.embargo
                    FROM cte_evento ev
                    JOIN anexo_pago ap ON ev.anexo_pago_id = ap.id
                    JOIN validacion_terceros vt ON ap.validacion_terceros_id = vt.id
                    JOIN tercero t ON vt.tercero_id = t.id
                    WHERE (ev.fecha BETWEEN ? AND ?) 
                    AND ev.usuario_id = ? 
                    AND ev.rn = 1 
                    AND ap.radicado IS NOT NULL 
                    AND ap.radicado != '' 
                    AND ap.voucher IS NOT NULL 
                    AND ap.voucher != ''";

    $params = [$fecha_inicio, $fecha_final, $_SESSION['identificacion_usuario']];
    $sql = sqlsrv_query($conexion, $sentencia, $params);

    if (!$sql) {
        die("Error de conexión: " . print_r(sqlsrv_errors(), true));
    }

    $pdfFiles = [];

    // Ruta de la plantilla PDF
    $rutaPlantilla = "TAPA 221.pdf";
    if (!file_exists($rutaPlantilla)) {
        die("Error: No se encontró la plantilla PDF.");
    }

    while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
        // Determinar el nombre del tercero
        if ($row["tipo_documento_id"] == "NIT") {
            $dato_tercero = $row["nombre_nit"];
        } else {
            $dato_tercero = trim("{$row['nombre_nit']} {$row['segundo_nombre']} {$row['primer_apellido']} {$row['segundo_apellido']}");
        }

        // Buscar el nombre del usuario
        $sql_usuario = sqlsrv_query($conexion, "SELECT nombre FROM usuario WHERE id = ?", [$row["usuario_id"]]);
        $info_usuario = ($dat_user = sqlsrv_fetch_array($sql_usuario, SQLSRV_FETCH_ASSOC)) ? $dat_user["nombre"] : "Desconocido";

        // CAPTURA DE DATOS DEL FORMULARIO 
        $inf_radicado = $row["radicado"];
        $inf_peoplesoft = "221"; // CODIGO FIJO
        $inf_cod_neg_fidu = "12076"; // CODIGO ES DE LA FIDUPREVISORA
        $inf_nom_neg = "FOMAG - GERENCIA DE SALUD";
        $inf_voucher_desde = $row["voucher"];
        $inf_voucher_hasta = $row["voucher"];

        // VALIDACION DE EXISTENCIA DE VALORES
        if (empty($row["valor_total"])) {
            $valor_total = number_format(0, 2, ',', '.');
        } else {
            $valor_total = number_format($row["valor_total"], 2, ',', '.');
        }
        if (empty($row["valor_retener"])) {
            $valor_retener = number_format(0, 2, ',', '.');
        } else {
            $valor_retener = number_format($row["valor_retener"], 2, ',', '.');
        }
        if (empty($row["valor_pago"])) {
            $valor_pago = number_format(0, 2, ',', '.');
        } else {
            $valor_pago = number_format($row["valor_pago"], 2, ',', '.');
        }

        // VALIDAR SI EXISTE FACTURA FINAL O SE ASIGNA PRINCIPAL
        if (!empty($row["fac_hasta"])) {
            $fac_fin = $row["fac_hasta"];
        } else {
            $fac_fin = $row["fac_desde"];
        }

        $inf_con_especial = "Informativo Fomag: NUEV_MODE_SALUD_EVENTO_AL_".$row["mes_servicio"]." Fact. ".$row["fac_desde"]."-".$fac_fin;
        $inf_con_tercero = $dato_tercero."-ID".$row["crp_id"];
        $inf_con_memo_para = htmlspecialchars(preg_replace('/\xC2\xA0/', ' ', $row["ap_observacion"]), ENT_QUOTES, 'UTF-8');
        $inf_con_valor_ret = "VALOR A RETENER $ ".$valor_retener."PAGAR VALOR RESTANTES DESPUES DE IMPUESTOS";
        $inf_estado_tercero = validar_embargo($row['embargo']);

        // VALIDAR SI EXISTE REGISTRO CONTABLE
        if (empty($row["plan_contable"])) {
            $reg_contable = "SIN DEFINIR";
        } else {
            $reg_contable = $row["plan_contable"];
        }

        $inf_con_tipo_tran = "Provedores - " . $reg_contable;
        $inf_valor_bruto = $valor_total; // VALIDAR SI ESTE ES EL DATO
        $inf_autorizacion = "N/A"; // SIN INFORMACION DE ELLO 
        $inf_aprobacion = "Marco Aurelio Reina Alejandra Gomez"; // VALIDAR SI ES UN DATO FIJO

        // INSERTAR REGISTRO DE GENERACION DE ARCHIVO TAPA EN TABLA EVENTO_PAGO PARA LLEVAR CONTROL DE EVENTOS
        $sql_evento = "INSERT INTO evento_anexo (anexo_pago_id, usuario_id, evento, fecha, descripcion) VALUES (?, ?, ?, ?, ?)";
        $parametros = array($row["anexo_pago_id"], $_SESSION["identificacion_usuario"], 'Evento TAPA', date('Y-m-d H:i:s'), 'Se Genera Archivo TAPA');
        $stmt = sqlsrv_query($conexion, $sql_evento, $parametros);
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        // Crear una instancia de FPDI y cargar la plantilla
        $pdf = new Fpdi();
        $pdf->AddPage();
        
        $pdf->setSourceFile($rutaPlantilla);
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, 0, 0, 210);

    
        $pdf->SetFont('Arial', '', 9);  // Fuente predeterminada para el resto de los campos
        $pdf->SetTextColor(0, 0, 0);  // Color negro para el texto

    // Posicionar y escribir el primer campo (más grande)
    $pdf->SetXY(145,  32);
    $pdf->SetFont('Arial', '', 10);  // Cambiar tamaño para este campo
    $pdf->Write(10, $inf_radicado);  // Escribir el valor de $inf_radicado

    // Posicionar y escribir el siguiente campo (normal tamaño)
    $pdf->SetXY(31,y: 48);
    $pdf->SetFont('Arial', '', 6);  // Tamaño 10 para este campo
    $pdf->Write(10, $inf_peoplesoft);

    // Posicionar y escribir el siguiente campo (normal tamaño)
    $pdf->SetXY(74, 48);
    $pdf->SetFont('Arial', '', 6);  // Tamaño 10 para este campo
    $pdf->Write(10, $inf_cod_neg_fidu);

    // Posicionar y escribir el siguiente campo (normal tamaño)
    $pdf->SetXY(118, 48);
    $pdf->SetFont('Arial', '', 6);  // Tamaño 10 para este campo
    $pdf->Write(10,$inf_nom_neg);

    // Posicionar y escribir el siguiente campo (normal tamaño)
    $pdf->SetXY(45, 59);
    $pdf->SetFont('Arial', '', 6);  // Tamaño 10 para este campo
    $pdf->Write(10,$inf_voucher_desde);

    // Posicionar y escribir el siguiente campo (tamaño más pequeño)
    $pdf->SetXY(85, 59);
    $pdf->SetFont('Arial', '', 6);  // Tamaño 8 para este campo
    $pdf->Write(10,$inf_voucher_hasta);

    // Posicionar y escribir el siguiente campo (tamaño más pequeño)
    $texto = $inf_con_especial;  
    $anchoTexto = $pdf->GetStringWidth($texto);
    $posX = (134 - $anchoTexto) / 2;
    $posY = 70; 
    $pdf->SetFont('Arial', 'b', 6);
    $pdf->SetXY($posX, $posY);
    $pdf->Write(10, $texto);
   


    $texto = $inf_con_tercero;  
    $anchoTexto = $pdf->GetStringWidth($texto);
    $posX = (134 - $anchoTexto) / 2;
    $posY = 73; 
    $pdf->SetFont('Arial', 'b', 6);
    $pdf->SetXY($posX, $posY);
    $pdf->Write(10, $texto);
   
    $pdf->SetXY(34, 83);
    $pdf->SetFont('Arial', 'b', 6);  // Tamaño 12 para este campo
    $pdf->MultiCell(100, 10, "NO APLICAR CDP/CRP - Pago por separado - Estado: No aplicable");

    $pdf->SetTextColor(255, 0, 0);
    $pdf->SetXY(24, 95);
    $pdf->SetFont('Arial', 'b', 5);   // Coordenadas iniciales
    $pdf->SetX(44);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(50, 2, $inf_con_memo_para, 0, 'C', false);

    $pdf->SetTextColor(255, 0, 0);
    $pdf->SetXY(24, 112);
    $pdf->SetFont('Arial', 'b', 5);   // Coordenadas iniciales
    $pdf->SetX(44);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(50, 2, $inf_con_valor_ret, 0, 'C', false);
   
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(57, 125);
    $pdf->SetFont('Arial', 'b', 6);  // Tamaño 8 para este campo
    $pdf->Write(10,$inf_estado_tercero);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(24, 71);
    $pdf->SetFont('Arial', 'b', 6);   // Coordenadas iniciales
    $pdf->SetX(140.5);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(30, 3, $info_usuario, 0, 'C', false);

    $pdf->SetXY(151, 81.6);
    $pdf->SetFont('Arial', 'b', 6);  // Tamaño 8 para este campo
    $pdf->Write(10,$inf_autorizacion);

    $pdf->SetXY(151, 109.3);
    $pdf->SetFont('Arial', 'b', 6);  // Tamaño 8 para este campo
    $pdf->Write(10,$inf_autorizacion);

    $pdf->SetXY(151, 121.5);
    $pdf->SetFont('Arial', 'b', 6);  // Tamaño 8 para este campo
    $pdf->Write(10,$inf_autorizacion);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(24, 98);
    $pdf->SetFont('Arial', 'b', 6);   // Coordenadas iniciales
    $pdf->SetX(140.5);  // Ajusta el margen izquierdo moviendo la posición X
    $pdf->MultiCell(30, 3, $inf_aprobacion, 0, 'C', false);

    $pdf->SetXY(92, 140);
    $pdf->SetFont('Arial', 'b', 7);  // Tamaño 8 para este campo
    $pdf->Write(10, '$'.$inf_valor_bruto);




    
    


    // Nombre del archivo
    $nombreArchivo = "AP 221 - " . $row["voucher"] . " - " . $row["radicado"] . ".pdf";
    $rutaPDF = "downloads/" . $nombreArchivo;

    // Guardar el PDF en el servidor
    $pdf->Output($rutaPDF, 'F');
    $pdfFiles[] = $rutaPDF;
    }

    sqlsrv_free_stmt($sql);
    sqlsrv_close($conexion);

    return $pdfFiles;
}
?>
