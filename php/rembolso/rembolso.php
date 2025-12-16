<?php
session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['tipo_usuario_id']) || ($_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 5 && $_SESSION['tipo_usuario_id'] != 6)) {
    header('Location: ../menu.php');
    exit;
}
?>
<script>
        let inactivityTime =30 * 60 * 1000; // 30 minutos en milisegundos
    let inactivityTimer;

    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            window.location.href = "../logout.php"; // Redirige al menú
        }, inactivityTime);
    }

    // Detectar eventos de actividad del usuario
    document.addEventListener("mousemove", resetTimer);
    document.addEventListener("keypress", resetTimer);
    document.addEventListener("click", resetTimer);
    document.addEventListener("scroll", resetTimer);
    document.addEventListener("keydown", resetTimer);

    // Iniciar el temporizador
    resetTimer();
</script>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Reembolso</title>
    <link rel="stylesheet" href="estilo1.css">
    <!-- CSS de Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="rembolsos.js" defer></script>
</head>
<body>
<form action="generar_pdf_rembolso.php" id="formulario" method="POST"  accept-charset="UTF-8">
    <table>
        <tr>
            <th>Regional</th>
            <th>Departamento</th>
            <th>Ciudad</th>
            <th>Fecha de Solicitud</th>
            <th>N.solicitud</th>
        </tr>
        <tr>
            <td><input type="text" name="regional" id="regional" readonly></td>
            <td><input type="text" name="departamento" id="departamento" readonly></td>
            <td><input type="text" name="ciudad" id="ciudad" readonly></td>
            <td><input type="date" name="fecha_solicitud" id="fecha_solicitud"></td>
            <td><input type="text" name="radicado" id="radicado" readonly></td>
        </tr>
    </table>

    <table>
        <tr class="section-title">
            <th colspan="6" style="text-align: center;">1. Datos Generales del Solicitante</th>
        </tr>
        <tr>
            <td>Nombre del usuario afectado:</td>
            <td colspan="5"><input type="text" name="nombre" id="nombre"></td>
        </tr>
        <tr>
            <td>Tipo y Número de Identificación:</td>
            <td colspan="2"><input type="text" name="c" id="c"></td>
            <td colspan="3">
             <select name="t_identificacion" id="t_identificacion" style="width: 220px;">
                <option value="">Seleccione Una Opción</option>
            </select>
            </td>

        </tr>
        <tr>
          <td>Dirección:</td>
          <td ><input type="text" name="direccion" id="direccion" ></td>
            <td>Ciudad de Residencia:</td>
            <td colspan="1">
                <select name="ciudad_residencia" id="ciudad_residencia" style="width: 220px;">
                    <option value="">Seleccione Una Opción</option>
                    <?php
                    if ($resultado->num_rows > 0) {
                        while ($fila = $resultado->fetch_assoc()) {
                            echo "<option value='" . $fila['id'] . "'>" . $fila['nombre'] . "</option>";
                        }
                    } else {
                        echo "<option value=''>No hay ciudades disponibles</option>";
                    }
                    ?>
                </select>
            </td>
            <script>
                $(document).ready(function() {
                    $('#ciudad_residencia').select2({
                        placeholder: "Seleccione una opción",
                        allowClear: true
                    });
                });
            </script>


            <td>Barrio:</td>
            <td ><input type="text" name="barrio" id="barrio"></td>
        </tr>
        <tr>
            <td>Teléfono Fijo:</td>
            <td colspan="1" ><input type="text" name="telefono_fijo" id="telefono_fijo" ></td>
            <td>Teléfono Celular:</td>
            <td colspan="1"><input type="text" name="telefono_celular" id="telefono_celular"></td>
            <td>Correo Electrónico:</td>
            <td colspan="3" ><input type="text" name="correo" id="correo" ></td>
    </table>
 <!-- Tabla de reembolso -->
        <table id="reembolsoTable">
            <tr class="section-title">
                <th colspan="6"style="text-align: center;">2. Motivo Del Reembolso</th>
            </tr>
            <tr>
                <td colspan="3"><input type="checkbox" name="motivo[]" value="servicio_salud"> Servicio En Salud</td>
                <td colspan="3"><input type="checkbox" name="motivo[]" value="tecnologia_salud"> Tecnología En Salud</td>
            </tr>
            <tr>
                <th>Facturacion</th>
                <th>Servicio o Tecnología Ordenada</th>
                <th>Cantidad</th>
                <th>Valor Unitario</th>
                <th>Valor Total</th>

            </tr>
            <table>
    <tr>
        <td><input type="date" name="orden_medica1[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio1[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad1[]" class="cantidad" oninput="calcularFila(this)" oninput="formatIntegerInput(this)"></td>
        <td><input type="text" name="valor_unitario1[]" class="valor_unitario"  placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total1[]" class="valor_total" readonly placeholder="$0"></td>

    </tr>
</table>
<table id="filas_ocultas">
    <tr class="fila-oculta" style="display: none;">
        <td><input type="date" name="orden_medica2[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio2[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad2[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario2[]" class="valor_unitario" placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total2[]" class="valor_total" readonly placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
    </tr>
    <tr class="fila-oculta" style="display: none;" >
        <td><input type="date" name="orden_medica3[]"style="width: 170px;"></td>
        <td><input type="text" name="servicio3[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad3[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario3[]" class="valor_unitario" placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total3[]" class="valor_total" readonly ></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="date" name="orden_medica4[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio4[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad4[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario4[]" class="valor_unitario" placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total4[]" class="valor_total" readonly></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="date" name="orden_medica5[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio5[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad5[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario5[]" class="valor_unitario" placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total5[]" class="valor_total" readonly></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="date" name="orden_medica6[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio6[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad6[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario6[]" class="valor_unitario" placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total6[]" class="valor_total" readonly></td> 
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="date" name="orden_medica7[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio7[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad7[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario7[]" class="valor_unitario" placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total7[]" class="valor_total" readonly></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="date" name="orden_medica8[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio8[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad8[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario8[]" class="valor_unitario"placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total8[]" class="valor_total" readonly></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="date" name="orden_medica9[]" style="width: 170px;"></td>
        <td><input type="text" name="servicio9[]" style="width: 350px;"></td>
        <td><input type="text" name="cantidad9[]" class="cantidad" oninput="calcularFila(this)"></td>
        <td><input type="text" name="valor_unitario9[]" class="valor_unitario" placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="text" name="valor_total9[]" class="valor_total" readonly></td>
    
</table>
<button type="button" style="background-color:rgb(250, 0, 0); color: white; padding: 5px 10px; border: none; cursor: pointer;" onclick="mostrarSiguienteFila()">Mostrar Más Filas (max 9)</button>
<button type="button" style="background-color:rgb(250, 0, 0); color: white; padding: 5px 10px; border: none; cursor: pointer;" onclick="ocultarUltimaFila()">Eliminar Ultima Fila</button>
<script>
let indiceFila = 0;
function mostrarSiguienteFila() {
    let filas = document.querySelectorAll('.fila-oculta');

    if (indiceFila < filas.length) {
        filas[indiceFila].style.display = 'table-row';
        indiceFila++;
    }
}
function ocultarUltimaFila() {
    let filas = document.querySelectorAll('.fila-oculta');
    if (indiceFila > 0) {
        indiceFila--;
        filas[indiceFila].style.display = 'none';
    }
}
</script>
    
    <table>
            <tr>
             <td>Factura Electrónica Del (Día/Mes/Año):</td>
             <td colspan="1"><input type="date" name="factura_in"></td>   
             <td>Prestador o proveedor donde se garantizo el servicio o tecnología
              <input type="text" name="prestador"></td>
              <td>Valor total solicitado
                <input type="text" name="valor_total_solicitado" id="valor_total_solicitado" readonly >
            </td>
            </tr>
            <tr>
             <td>Describa brevemente el motivo de la solicitud:</td>
             <td colspan="5"><input type="text" name="motivo_des"></td>
            </tr>
         <!-- listado de documentos  -->
                    <tr>
                <td colspan="6">
                    Para el trámite de la solicitud se requiere adjuntar los soportes relacionados a continuación (formato PDF, claro y legible).
                    <div style="text-align: left;">
                        <a href="https://netorg16808743-my.sharepoint.com/personal/financiera_fomag_gov_co/_layouts/15/onedrive.aspx?e=5%3Ae0a879b718e2437889ddfa4fabd6e32f&sharingv2=true&fromShare=true&at=9&CT=1738163099256&OR=OWA%2DNT%2DMail&CID=70853454%2De185%2D17d5%2D359e%2D5a598411aa6a&id=%2Fpersonal%2Ffinanciera%5Ffomag%5Fgov%5Fco%2FDocuments%2FREEMBOLSOS%202025%20NUEVO%20APLICATIVO&FolderCTID=0x012000DF4881D00FA55E4CB71C2046BBD5003B&view=0" 
                        class="boton" 
                        target="_blank">
                            Ir a OneDrive
                        </a>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="cop_identificacion" value="cop_identificacion"> Copia documento de identificación</td>   
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="orden_medica" value="orden_medica"> Copia orden medica</td>  
            <tr>
                <td colspan="6"><input type="checkbox" name="HC" value="HC"> Copia HC de la prestación del servicio</td> 
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="tecnologia" value="tecnologia">Factura o documento equivalente del servicio o tecnología</td>
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="cuen_bancaria" value="cuen_bancaria">Certificación de la cuenta bancaria con fecha de expedición no</td>
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="tit_identificacion" value="tit_identificacion">Copia documento de identificación del titular de la cuenta bancaria</td>
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="tutela" value="tutela">Fallo de tutela* (Si el resuelve se encuentra asociado al motivo causal de la solicitud)</td>
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="autorizacion" value="autorizacion">Autorización otorgada como tutor o representante del usuario*, debidamente firmada y con huella digital.</td>
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="aut_servicio" value="aut_servicio">Autorización del servicio o tecnología motivo de la solicitud*</td>
            </tr>
            <tr>
                <td colspan="6"><input type="checkbox" name="firma" value="firma">Formato con firma del afiliado</td>
            </tr>
     <!-- 3.radicacion insert  -->

<table>
 <tr class="section-title">
        <th colspan="6" style="text-align: center;">Información bancaria a quien se le realizará el pago</th>
    </tr>
    <tr>
        <td>Primer Nombre <input type="text" name="nombre_repre" id="nombre_repre"></td>
        <td>Segundo Nombre <input type="text" name="segundo_n" id="segundo_n"></td>
        <td>Primer Apellido <input type="text" name="primer_p" id="primer_p"></td>
        <td>Segundo Apellido <input type="text" name="segundo_p" id="segundo_p"></td>
    </tr>

    <tr>
        <td>N° identificación del titular de la cuenta bancaria</td>
        <td><input type="text" name="id_titular" id="id_titular"></td>
        <td>Tipo de cuenta</td>
        <td>
            <select name="t_cuenta" id="t_cuenta">
                <option value="">Seleccione una opción</option>
            </select>
        </td>
    </tr>
    
    <tr>
        <td>Entidad bancaria</td>
        <td>
            <select name="t_banco" id="t_banco">
                <option value="">Seleccione una opción</option>
            </select>
        </td>
        <td>Número de Cuenta</td>
        <td><input type="text" name="n_cuenta" id="n_cuenta"></td>
    </tr>
    
    <tr>
        <td>Link del drive</td>
        <td><input type="text" name="url" id="url"></td>
        <td>Parentesco</td>
        <td><input type="text" name="Parentesco" id="Parentesco"></td>
    </tr>
</table>
    <!-- hasta aqui se guarda¡¡¡ -->  
<table>
    <!-- 3. RADICACIÓN ANTE LA COORDINACIÓN DEPARTAMENTAL -->
    <tr class="section-title">
        <th colspan="6" style="text-align: center;">3. RADICACIÓN ANTE LA COORDINACIÓN DEPARTAMENTAL</th>
    </tr>
            <tr>
             <td colspan="6">A través de la radicación del presente formato, ACEPTO la política de tratamiento de datos en el marco de la Ley 1581 de 2012 y su Decreto reglamentario; la información recopilada en este formato, es confidencial y se utilizará únicamente para fines administrativos asociados a la solicitud de reconocimiento de reembolsos de servicios o tecnologías en salud a los usuarios en salud del FOMAG, en el marco de la prestación de servicios de salud establecido en mi plan de manejo.								
             </td>
            </tr>
            <tr>
             <td colspan="6" style="text-align: center;">Quien recibe</td>
            </tr>
            <tr>
                <td colspan="1">Nombre Completo</td>
                <td><input type="text" name="nom_entidad"></td>
                
                <td colspan="1">Cargo</td>
                <td><input type="text" name="cargo"></td>
            </tr>
            <tr>
                <td>Departamento</td>
                <td><input type="text" name="departamento_res" id="departamento_res"></td>
                
                <td >Regional</td>
                <td><input type="text" name="regional_res" id="regional_res"></td>

                <td >Fecha</td>
                <td><input type="date" name="fecha_na"></td>
            </tr>
</table>
 <!-- 4. VALIDACIÓN COORDINACIÓN DEPARTAMENTAL FOMAG -->
    <table>
            <tr class="section-title">
                <th colspan="6"style="text-align: center;">4.REVISIÓN Y VALIDACIÓN POR LA COORDINACIÓN DEPARTAMENTAL FOMAG</th>
            </tr>
    <tr>
        <td>Estado de afiliación:</td>
        <td><input type="text" name="estado_afiliacion" id="estado_afiliacion" readonly></td>
        <td>Observaciones</td>
        <td><input type="text" name="observaciones"></td>
    </tr>
</table>
<table>
    <tr>
        <td colspan="4">La solicitud cumple con la: </td> <!-- colspan="4" para que ocupe todo el ancho -->
    </tr>
    <tr>
        <td colspan="2" style="text-align: left;"> - Oportunidad de radicación
            <input type="radio" name="Oportunidad" value="si"> SI
            <input type="radio" name="Oportunidad" value="no"> NO
        </td> 
    </tr>
    <tr>
        <td  colspan="2" style="text-align: left;"> - Completitud documental
            <input type="radio" name="com_documentos" value="si"> SI
            <input type="radio" name="com_documentos" value="no"> NO
        </td> 
    </tr>
    <tr>
        <td>- Pertinencia</td>
        <td colspan="3"> <!-- colspan="3" para centrar el texto en la tabla -->
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-left: 70px;"> Médico-científica
            <input type="radio" name="Médico_científica" value="si"> SI
            <input type="radio" name="Médico_científica" value="no"> NO
        </td> 
    </tr>
    <tr>
        <td colspan="2" style="padding-left: 70px;">Administrativa
            <input type="radio" name="Administrativa" value="si"> SI
            <input type="radio" name="Administrativa" value="no"> NO
        </td> 
    </tr>
    <tr>
    <td  colspan="2" style="padding-left: 70px;">Jurídica
            <input type="radio" name="Jurídica" value="si"> SI
            <input type="radio" name="Jurídica" value="no"> NO
        </td> 
    </tr>
    <tr>
        <td colspan="2" style="padding-left: 70px;">Referencia 
            <input type="radio" name="Referencia" value="si"> SI
            <input type="radio" name="Referencia" value="no"> NO
        </td> 
    </tr>
    <tr>
        <td colspan="2" style="padding-left: 70px;">Auditoria médica
            <input type="radio" name="Auditoria_médica" value="si"> SI
            <input type="radio" name="Auditoria_médica" value="no"> NO
        </td> 
    </tr>
    <tr>
        <td colspan="2" style="padding-left: 70px;"> Prestación o suministro a cargo del FOMAG
            <input type="radio" name="FOMAG" value="si"> SI
            <input type="radio" name="FOMAG" value="no"> NO
        </td> 
    </tr>
    <tr>
        <td  colspan="2">Justificación de pertinencia:
    <input type="text" name="justificacion"></td>
    </tr>
</table>

 <!-- 5. APROBACIÓN O NEGACIÓN -->
       <table>
            <tr class="section-title">
                <th colspan="4"style="text-align: center;">5. APROBACIÓN O NEGACIÓN</th>
                
            </tr>
            <tr>
                <td colspan="1">Rembolso aprobado</td>
                <td>
                    <input type="radio" name="apro_rembolso" value="si"> SI
                    <input type="radio" name="apro_rembolso" value="no"> NO
                </td>
                <td colspan="1">Valor del reembolso aprobado</td>
                <td>
                 <input type="text" name="val_rembolso" id="val_rembolso">
                </td>
                </td>
            </tr>
            <tr>
             <td colspan="1">Justificación de la aprobación o negación </td>
             <td colspan="3"><input type="text" name="justi_rembolso"></td>
              
            </tr>
        </table>
        <button type="button" id="guardar_solicitud">Guardar Solicitud</button>
        <button type="submit" id="descargar_pdf">Descarga PDF</button>

        <a href="../menu.php" style="background-color:rgb(250, 0, 0); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
    Atrás
</a>
       
    </body>
</html>
