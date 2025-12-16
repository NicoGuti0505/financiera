<?php 
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
// FUNCION REPORTE CSV O TXT
function txt($host, $option, $fecha_inicio, $fecha_final, $tables=false, $backup_name=false) { 
	// VACIAR CONTENIDO
	$contenido = "";
	/********************** DATOS DE CONEXION BASE DE DATOS **********************/
	// CODIGO DE CONEXION MYSQL
	$conexion = sqlsrv_connect($host, $option);
	// SI NO CONECTA ENVIA ERROR Y DATOS DE ESTE
	if (!$conexion) { die("ERROR: No Se Estableció La Conexión Con La Base de Datos."); }	
		$sentencia = "WITH cte_evento AS (
						SELECT *, ROW_NUMBER() OVER(PARTITION BY anexo_pago_id ORDER BY fecha DESC) as rn
						FROM evento_anexo
					)
					SELECT 
						ev.id AS evento_id, 
						ev.anexo_pago_id, 
						ev.fecha, 
    					ev.usuario_id,
						ap.*, 
						vt.*, 
						t.*,
						ap.observacion AS ap_observacion,
                        vt.observacion AS vt_observacion
					FROM cte_evento ev
					JOIN anexo_pago ap ON ev.anexo_pago_id = ap.id
					JOIN validacion_terceros vt ON ap.validacion_terceros_id = vt.id
					JOIN tercero t ON vt.tercero_id = t.id
					WHERE (ev.fecha BETWEEN '".$fecha_inicio."' AND '".$fecha_final."') AND ev.usuario_id = '".$_SESSION['identificacion_usuario']."'
					AND ev.rn = 1 
					AND ap.radicado IS NOT NULL 
					AND ap.radicado != ''";
		// echo $sentencia."\n"; pause();
		$sql=sqlsrv_query($conexion, $sentencia);
	if(!$sql) {
		// SI FALLA LA CONSULTA MUESTRA ERROR
		die("Error de conexión: ".print_r(sqlsrv_errors(), true));
	} else {
			// GUIA DEL CONTENIDO 
			//$contenido = "IDENTIFICACIÓN;TIPO DCTO;PRIMER APELLIDO;SEGUNDO APELLIDO;PRIMER NOMBRE;SEGUNDO NOMBRE;DIRECCIÓN;TELÉFONO;PAÍS;DPTO;CIUDAD;CÓDIGO CIIU;TIPO CONTRIBUYENTE;SUJETO A RETENCIÓN;BANCO DESTINO;TIPO;#;UNIDAD DE NEGOCIO PO;COMENTARIO;ARTÍCULO;CANT. RECEPCIÓN;UM RECEPCIÓN;PRECIO;LÍNEA DE RECEPCIÓN;NÚMERO PEDIDO;LÍNEA DISTRIBUCIÓN PEDIDO;UN EXPLOTACIÓN;CD FONDO;DEPARTAMENTO;PROGRAMA;CLASE;PRODUCTO;PROYECTO;ACTIVIDAD;SUBFIDEICOMISO;BANCARIA;TERCERO;TIPO COMPROBANTE;NÚMERO FACTURA;NÚMERO RADICADO;CÓDIGO IVA;VALOR IVA;IMPOCONSUMO;CÓDIGO BANCO;BANCARIA;MÉTODO PAGO;DETALLE DE PAGO;CÓDIGO GRUPO DE PAGO;TIPO TRANSACCIÓN;PLANTILLA CONTABLE;PORCENTAJE PAGO;VALOR PAGO;IDENTIFICACIÓN;TIPO DCTO;PRIMER APELLIDO;SEGUNDO APELLIDO;PRIMER NOMBRE;SEGUNDO NOMBRE;MÉTODO DE PAGO;DETALLE DE PAGO;BANCO DESTINO;TIPO;#;VALOR CESIÓN;NÚMERO DE DOCUMENTO;TIPO DOCUMENTO;PRIMER APELLIDO;SEGUNDO APELLIDO;PRIMER NOMBRE;SEGUNDO NOMBRE;N° OPERACIÓN"."\n";
			// CONSULTAS DE LAS BASES DE DATOS 
			while ($row = sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC)) {
				if ($row) {
					// Procesar y mostrar los datos
					// VALIDAR FACTURA HASTA 
					if (!empty($row["fac_hasta"])) { $fac_hasta = $row["fac_hasta"]; } else {  $fac_hasta = $row["fac_desde"]; } 
					// COMENZAR CON EL CONTENIDO
					$contenido = $contenido.substr($row["identificacion"], 0, 20).";";
					$contenido = $contenido.substr($row["tipo_documento_id"], 0, 6).";";
					$contenido = $contenido.substr($row["primer_apellido"], 0, 30).";";
					$contenido = $contenido.substr($row["segundo_apellido"], 0, 30).";";
					$contenido = $contenido.substr($row["nombre_nit"], 0, 30).";";
					$contenido = $contenido.substr($row["segundo_nombre"], 0, 30).";";
					$contenido = $contenido.substr($row["direccion"], 0, 55).";";
					$contenido = $contenido.substr($row["telefono"], 0, 24).";";
					$contenido = $contenido."COL;BOG;";
					$contenido = $contenido.substr($row["municipio_id"], 0, 12).";";
					$contenido = $contenido.substr($row["ciiu_id"], 0, 4).";";
					$contenido = $contenido.substr($row["tipo_contribuyente_id"], 0, 2).";";
					$contenido = $contenido.substr($row["retencion"], 0, 1).";";
					$contenido = $contenido.substr($row["banco_id"], 0, 30).";";
					$contenido = $contenido.substr($row["tipo_cuenta_id"], 0, 2).";";
					$contenido = $contenido.substr($row["num_cuenta_bancaria"], 0, 35).";";
					$contenido = $contenido."PO221;";
					// UNIFICAR DATOS PARA COMENTARIO
					$info_comentario ="NUEV_MODE_SALUD_EVENTO_AL_".$row["concepto"]." ".$row["mes_servicio"]." ".$row["vt_observacion"]." - ".$row["cantidad_facturas"]." Fact. No. ".$row["fac_desde"]."-".$fac_hasta." - CRP ID ".$row["crp_id"]." NIT. ".$row["identificacion"];
					$contenido = $contenido.substr($info_comentario, 0, 253).";";
					// ASIGNAR 18 CARACTERES A ARTICULO
					$contenido = $contenido.str_pad($row["id_articulo"], 18, '0', STR_PAD_LEFT).";";
					$contenido = $contenido.$row["valor_total"].";";
					// MAS INFORMACIÓN
					$contenido = $contenido."UND;1;";
					$contenido = $contenido.substr($row["linea_rep"], 0, 38).";";
					// ASIGNAR 10 CARACTERES A PEDIDO
					$contenido = $contenido.substr(str_pad($row["crp_id"], 10, '0', STR_PAD_LEFT), 0, 10).";"; // $contenido.substr($row["num_pedido"], 0, 10).";";
					$contenido = $contenido.substr($row["linea_dis_ped"], 0, 38).";";
					$contenido = $contenido.substr($row["municipio_id"], 0, 8).";";
					$contenido = $contenido.";SALUD;;;;;;012076004;";
					// BUSCAR CUENTA EN TABLA ARTICULO
					$sql_articulo = "SELECT * FROM articulo WHERE id = '".$row["id_articulo"]."'";
					if ($res_articulo = sqlsrv_fetch_array(sqlsrv_query($conexion,$sql_articulo), SQLSRV_FETCH_ASSOC)) {
						$contenido = $contenido.substr($res_articulo["cuenta"], 0, 35).";";
					} else {
						$contenido = $contenido."SIN CUENTA;";;
					}
					$contenido = $contenido.substr($row["identificacion"], 0, 20).";";
					$contenido = $contenido."REG;";
					$contenido = $contenido.substr(($row["fac_desde"]." - ".$fac_hasta), 0, 30).";";
					$contenido = $contenido.substr($row["radicado"], 0, 20).";";
					$contenido = $contenido.";;;13;5581;EFT;X;PR;PGPR;";
					$contenido = $contenido.$row["plan_contable"].";";
					$contenido = $contenido.";";
					$contenido = $contenido.$row["valor_total"].";";
					// PREPARAR NUEVA LINEA PARA EL REGISTRO SIGUIENTE  
					$contenido = $contenido."\n";
				} else {
					// echo "No hay datos.";
				}
			}
		} 
	// NOMBRE DEL ARCHIVO A GENERAR EN TXT
	// $backup_name = $backup_name ? $backup_name : "Masivo_".date('Y-m-d').".txt";
	// NOMBRE DEL ARCHIVO A GENERAR EN CSV
	$backup_name = $backup_name ? $backup_name : "Masivo_".date('Y-m-d').".csv";
	// DESCARGA DEL ARCHIVO
    // LIMPIAR TODO EL CODIGO Y PARTIR DE AQUI
	ob_get_clean();
	// PREPARAR EL ARCHIVO .CSV
	header('Pragma: public'); 
	header('Pragma: no-cache'); 
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate'); 
	header('Cache-Control: pre-check=0, post-check=0, max-age=0');
	header("Content-disposition: attachment; filename=\"".$backup_name."\"");
	header('Content-Type: text/plain; charset=utf-8');
	header("Content-Type: application/force-download");
	header("Content-Transfer-Encoding: binary");
	header("Expires: 0");
	echo $contenido; 
	exit();
} ?>