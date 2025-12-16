<?php
session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: ../../inicio_sesion.php');
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
    <title>Solicitud de viáticos</title>
    <link rel="stylesheet" href="estilo2.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="rembolsos.js" defer></script>
</head>
<body>
<form action="generar_pdf_viaticos.php" id="formulario" method="POST">
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
         <td colspan="6"><label for="nombre">Nombre del usuario </label><br><input type="text" name="nombre" id="nombre"></td>
        </tr>
    <tr>
    <td>Tipo y Número de Identificación:</td>
            <td colspan="2"><input type="text" name="c" id="c" style="width: 280px;"></td>
            <td colspan="1">
             <select name="t_identificacion" id="t_identificacion" style="width: 100px;">
                <option value="">Seleccione una opción</option>
            </select>
            </td>
           <td colspan="1">
            <label for="fe_na">Fecha de Nacimiento:</label>
            <input  type="date" name="fe_na" id="fe_na" style="width: 170px;">
            </td>
            <td colspan="1">
            <label for="c">Edad</label>
            <input  type="text" name="edad" id="edad" style="width: 170px;">
            </td>
        </tr>
<table>
    <tbody>
    <tr>
        <td>Discapacidad</td>
        <td><label for="fisica">Física</label><br><input type="checkbox" id="fisica" name="fisica" value="x"></td>
        <td><label for="visual">Visual</label><br><input type="checkbox" id="visual" name="visual" value="x"></td>
        <td><label for="auditiva">Auditiva</label><br><input type="checkbox" id="auditiva" name="auditiva" value="x"></td>
        <td><label for="intelectual">Intelectual</label><br><input type="checkbox" id="intelectual" name="intelectual" value="x"></td>
        <td><label for="psicosocial">Psicosocial</label><br><input type="checkbox" id="psicosocial" name="psicosocial" value="x"></td>
        <td><label for="sordoceguera">Sordoceguera</label><br><input type="checkbox" id="sordoceguera" name="sordoceguera" value="x"></td>
        <td><label for="multiple">Múltiple</label><br><input type="checkbox" id="multiple" name="multiple" value="x"></td>
        <td><label for="no">No</label><br><input type="checkbox" id="no" name="no" value="x"></td>
    </tr>
    <tr>
 </table>
 <table>
    <td>Ciudad de Residencia:</td>
            <td colspan="1">
                <select name="ciudad_residencia" id="ciudad_residencia" style="width: 220px;">
                    <option value="">Seleccione una opción</option>
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
        <td>Dirección</td>
        <td colspan="2"><input type="text" name="Dirección" id="Dirección"></td>
        <td>Barrio</td>
        <td colspan="2"><input type="text" name="Barrio" id="Barrio"></td>
    </tr>
    <tr>
        <td> Correo Electrónico</td>
        <td colspan="2"><input type="text" name="correo" id="correo"></td>
        <td> Teléfono Celular</td>
        <td colspan="2"><input type="text" name="telefono_Celular" id="telefono_Celular"></td>
        <td>Teléfono Fijo</td>
        <td colspan="2"><input type="text" name="Telefono_Fijo" id="Telefono_Fijo"></td>
    </tr>
    </tbody>
</table>
<table>
        <table id="reembolsoTable">
            <tr class="section-title">
                <th colspan="6"style="text-align: center;">2. Motivo de la solicitud</th>
            </tr>
            <tr>
                <th style="width: 170px;"> Fecha de programación dd/mm/aaaa</th>
                <th style="width: 170px;">Hora de programación am - pm</th>
                <th>Departamento de atención </th>
                <th>Municipio de atención </th>
                <th>IPS de atención </th>

            </tr>
            <table>
<table id="filas_mecanismo">
    <tr>
        <td><input type="date" name="fecha_m" style="width: 170px;"></td>
        <td><input type="time" name="tiempo_m" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_a" style="width: 240px;"></td>
        <td><input type="text" name="Municipio_a"></td>
        <td><input type="text" name="IPS_a"></td>
    </tr>

    <tr class="fila-mecanismo-oculta" style="display: none;">
        <td><input type="date" name="fecha_m1" style="width: 170px;"></td>
        <td><input type="time" name="tiempo_m1" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_a1" style="width: 240px;"></td>
        <td><input type="text" name="Municipio_a1"></td>
        <td><input type="text" name="IPS_a1"></td>
    </tr>

    <tr class="fila-mecanismo-oculta" style="display: none;">
        <td><input type="date" name="fecha_m2" style="width: 170px;"></td>
        <td><input type="time" name="tiempo_m2" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_a2" style="width: 240px;"></td>
        <td><input type="text" name="Municipio_a2"></td>
        <td><input type="text" name="IPS_a2"></td>
    </tr>

    <tr class="fila-mecanismo-oculta" style="display: none;">
        <td><input type="date" name="fecha_m3" style="width: 170px;"></td>
        <td><input type="time" name="tiempo_m3" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_a3" style="width: 240px;"></td>
        <td><input type="text" name="Municipio_a3"></td>
        <td><input type="text" name="IPS_a3"></td>
    </tr>

    <tr class="fila-mecanismo-oculta" style="display: none;">
        <td><input type="date" name="fecha_m4" style="width: 170px;"></td>
        <td><input type="time" name="tiempo_m4" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_a4" style="width: 240px;"></td>
        <td><input type="text" name="Municipio_a4"></td>
        <td><input type="text" name="IPS_a4"></td>
    </tr>
</table>

<!-- Botones para mostrar y eliminar filas -->
<button type="button" style="background-color:rgb(0, 123, 255); color: white; padding: 5px 10px; border: none; cursor: pointer;" onclick="mostrarSiguienteMecanismo()">Mostrar Más Filas (max 5)</button>
<button type="button" style="background-color:rgb(220, 53, 69); color: white; padding: 5px 10px; border: none; cursor: pointer;" onclick="ocultarUltimoMecanismo()">Eliminar Último Mecanismo</button>
<script>
let indiceMecanismo = 0;

function mostrarSiguienteMecanismo() {
    const filas = document.querySelectorAll('.fila-mecanismo-oculta');
    if (indiceMecanismo < filas.length) {
        filas[indiceMecanismo].style.display = 'table-row';
        indiceMecanismo++;
    }
}

function ocultarUltimoMecanismo() {
    const filas = document.querySelectorAll('.fila-mecanismo-oculta');
    if (indiceMecanismo > 0) {
        indiceMecanismo--;
        filas[indiceMecanismo].style.display = 'none';
    }
}
</script>

    
</table>
<!-- Reembolsos viáticos -->
<table>
        <table id="ReembolsosviÃ¡ticos">
            <tr>
             <td colspan="6" style="text-align: center;">Reembolsos/viáticos</td>
            </tr>

            <tr>
                <th style="width: 180px;"> Servicio</th>
                <th style="width: 170px;"> Departamento de atención</th>
                <th style="width: 190px;">Municipio de atención</th>
                <th style="width: 170px;">Cantidad</th>
                <th> Valor solicitado en reembolso</th>

            </tr>
            <table>
    <tr>
        <td><input type="text" name="Servicio1" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_at1" style="width: 170px;"></td>
        <td><input type="text" name="Municipio_at1"></td>
        <td><input type="text" name="cantidad1[]" class="cantidad" oninput="calcularFila(this)" oninput="formatIntegerInput(this)"></td>
        <td><input type="text" name="valor_unitario1[]" class="valor_unitario"  placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="hidden" name="valor_total1[]" class="valor_total" readonly placeholder="$0"></td>
    </tr>
<table id="filas_ocultas">
 <tr class="fila-oculta" style="display: none;">
        <td><input type="text" name="Servicio2" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_at2" style="width: 170px;"></td>
        <td><input type="text" name="Municipio_at2"></td>
        <td><input type="text" name="cantidad2[]" class="cantidad" oninput="calcularFila(this)" oninput="formatIntegerInput(this)"></td>
        <td><input type="text" name="valor_unitario2[]" class="valor_unitario"  placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="hidden" name="valor_total2[]" class="valor_total" readonly placeholder="$0"></td>
    </tr>

    <tr class="fila-oculta" style="display: none;">
        <td><input type="text" name="Servicio3" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_at3" style="width: 170px;"></td>
        <td><input type="text" name="Municipio_at3"></td>
        <td><input type="text" name="cantidad3[]" class="cantidad" oninput="calcularFila(this)" oninput="formatIntegerInput(this)"></td>
        <td><input type="text" name="valor_unitario3[]" class="valor_unitario"  placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="hidden" name="valor_total3[]" class="valor_total" readonly placeholder="$0"></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="text" name="Servicio4" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_at4" style="width: 170px;"></td>
        <td><input type="text" name="Municipio_at4"></td>
        <td><input type="text" name="cantidad4[]" class="cantidad" oninput="calcularFila(this)" oninput="formatIntegerInput(this)"></td>
        <td><input type="text" name="valor_unitario4[]" class="valor_unitario"  placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="hidden" name="valor_total4[]" class="valor_total" readonly placeholder="$0"></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="text" name="Servicio5" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_at5" style="width: 170px;"></td>
        <td><input type="text" name="Municipio_at5"></td>
        <td><input type="text" name="cantidad5[]" class="cantidad" oninput="calcularFila(this)" oninput="formatIntegerInput(this)"></td>
        <td><input type="text" name="valor_unitario5[]" class="valor_unitario"  placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="hidden" name="valor_total5[]" class="valor_total" readonly placeholder="$0"></td>
    </tr>
    <tr class="fila-oculta" style="display: none;">
        <td><input type="text" name="Servicio6" style="width: 170px;"></td>
        <td><input type="text" name="Departamento_at6" style="width: 170px;"></td>
        <td><input type="text" name="Municipio_at6"></td>
        <td><input type="text" name="cantidad6[]" class="cantidad" oninput="calcularFila(this)" oninput="formatIntegerInput(this)"></td>
        <td><input type="text" name="valor_unitario6[]" class="valor_unitario"  placeholder="$0" oninput="formatCurrency(this); calcularFila(this);"></td>
        <td><input type="hidden" name="valor_total6[]" class="valor_total" readonly placeholder="$0"></td>
    </tr>

</table>
<button type="button" style="background-color:rgb(250, 0, 0); color: white; padding: 5px 10px; border: none; cursor: pointer;" onclick="mostrarSiguienteFila()">Mostrar Más Filas (max 6)</button>
<button type="button" style="background-color:rgb(250, 0, 0); color: white; padding: 5px 10px; border: none; cursor: pointer;" onclick="ocultarUltimaFila()">Eliminar Última Fila</button>
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
</table>
    <table>
        <tr>
                <td>Describa brevemente el motivo de la solicitud
                <td colspan="1"><input  style="width: 300px;" type="text" name="motivo_des"></td>
             <td colspan="5" style="text-align: right;">Valor total solicitado
             <input style="width: 190px;" type="text" name='val_rembolso' id="val_rembolso" readonly></td>
        </tr>
        <td colspan="2" style="text-align: left;"> Usuario cuenta con tutela asociada a la solicitud
            <input type="radio" name="tutela" value="si"> SI
            <input type="radio" name="tutela" value="no"> NO
        </td> 
        <td  style="text-align: left;">N°</td>
        <td><input type="text" name="N°" id="N°"></td>
<table>

 <tr class="section-title">
        <th colspan="6" style="text-align: center;"> En caso de requerir acompañante, diligenciar la siguiente información</th>
    </tr>
    <tr>
        <td colspan="3" > Nombre del acompañante <input type="text" name="nombre_acom" id="nombre_acom"></td>
        <td>  Parentesco<input type="text" name="Parentesco" id="Parentesco"></td>
    </tr>
    <tr>
        <td colspan="1">
                <label for="tipo_doc">tipo de documento</label>
                        <select id="tipo_doc" name="tipo_doc">
                            <option value="">Selecciona</option>
                            <option value="AS">AS</option>
                            <option value="CC">CC</option>
                            <option value="CD">CD</option>
                            <option value="CE">CE</option>
                            <option value="CN">CN</option>
                            <option value="DE">DE</option>
                            <option value="MS">MS</option>
                            <option value="NIT">NIT</option>
                            <option value="PA">PA</option>
                            <option value="PE">PE</option>
                            <option value="PT">PT</option>
                            <option value="RC">RC</option>
                            <option value="SC">SC</option>
                            <option value="TI">TI</option>
                        </select>
                </td>
                <td colspan="1">
                <label for="numero_idn">Número de identificación</label>
                <input  type="text" name="numero_idn" id="numero_idn" style="width: 170px;">
                </td>

                <td colspan="1">
                <label for="fec_na">Fecha de nacimiento</label>
                <input  type="date" name="fec_na" id="fec_na" style="width: 170px;">
                </td>

                <td colspan="1">
                <label for="tel_acom"> Teléfono Celular</label>
                <input  type="text" name="tel_acom" id="tel_acom" style="width: 170px;">
                </td>
</table>

<table>
<tr class="section-title">
        <th colspan="6" style="text-align: center;">Información bancaria a quien se le realizará el pago</th>
    </tr>
    
    <tr>            
        <td>Primer Nombre <input type="text" name="nombre_repre" id="nombre_repre"></td>
        <td>Segundo Nombre <input type="text" name="segundo_n" id="segundo_n"></td>
        <td>Primer Apellido <input type="text" name="primer_p" id="primer_p"></td>
        <td>Segundo Apellido <input type="text" name="segundo_p" id="segundo_p"></td>

     <td>Parentesco</td>
     <td><input type="text" name="Parentesco_pago" id="Parentesco_pago"></td>
    </tr>

    <tr>
        <td colspan="1">N° de identificación del titular de la cuenta bancaria</td>
        <td colspan="2"><input type="text" name="id_titular" id="id_titular"></td>
        <td>Tipo de cuenta</td>
        <td colspan="2">
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
<td>Dirección de la carpeta</td>
<td colspan="6">
    <input type="text" name="url" id="url" readonly>
</td>
    </tr>
</table>
<!-- Botón que abre una ventana emergente -->
<button type="button" id="abrirPopup" class='btn-onedrive'>
    Subir Archivos
</button>
<table>
    <tr class="section-title">
        <th colspan="6"style="text-align: center;"> 3. RADICACIÓN ANTE LA COORDINACIÓN DEPARTAMENTAL</th>
    </tr>
    <tr>
             <td colspan="6" style="text-align: center;">Quién recibe</td>
            </tr>
            <tr>
                <td colspan="1">Nombre Completo</td>
                <td><input type="text" name="nom_entidad"></td>
                
                <td colspan="1">Cargo</td>
                <td><input type="text" name="cargo"></td>

                <td >Fecha</td>
                <td><input type="date" name="fecha_na"></td>
            </tr>
            <tr>
                <td>Departamento</td>
                <td><input type="text" name="departamento_res" id="departamento_res"></td>
                
                <td >Regional</td>
                <td><input type="text" name="regional_res" id="regional_res"></td>
    </tr>
</table>

 <!-- 5. APROBACIÓN O NEGACIÓN -->
<table>
    <tr class="section-title">
        <th colspan="6"style="text-align: center;">4. VALIDACIÓN COORDINACIÓN DEPARTAMENTAL FOMAG</th>
        
    </tr>
    <tr>
        <td colspan="2">Estado de afiliación del usuario</td>
        <td><label for="Activo">Activo</label><br><input type="checkbox" id="Activo" name="Activo" value="x"></td>
        <td><label for="Protección_Laboral">Protección Laboral</label><br><input type="checkbox" id="Protección_Laboral" name="Protección_Laboral" value="x"></td>
        <td><label for="Suspendido">Suspendido</label><br><input type="checkbox" id="Suspendido" name="Suspendido" value="x"></td>
        <td><label for="Retirado">Retirado</label><br><input type="checkbox" id="Retirado" name="Retirado" value="x"></td>
    </tr>
    
    <td colspan="6">La solicitud cumple con la </td>
    <tr>
        <td colspan="2" style="text-align: left;"> - Oportunidad de radicación
            <input type="radio" name="Oportunidad" value="si"> SI
            <input type="radio" name="Oportunidad" value="no"> NO
        </td> 
        <td >Observaciones</td>
        <td colspan="3"><input  type="text" name="Observaciones" id="Observaciones" style="width: 470px;" ></td>

    </tr>
   
    <tr>
    <td colspan="6" style="text-align: left;">  - Completitud documental
            <input type="radio" name="Completitud" value="si"> SI
            <input type="radio" name="Completitud" value="no"> NO
        </td> 
    </tr> 
    <td colspan="5"> Para el trámite de la solicitud se requiere adjuntar los soportes relacionados a continuación en formato PDF, legible sin enmendaduras ni tachones</td>
    <tr>
     <td colspan="2"><input type="checkbox" name="cop_identificacion" value="x">1.Copia documento de identificación del usuario solicitante</td>
     <td colspan="4"><input type="checkbox" name="cert_bancaria" value="x">4. Certificación de la cuenta bancaria con fecha de expedición no mayor a 90 días</td>    
    </tr>

    <tr>
     <td colspan="2"><input type="checkbox" name="orden_medica" value="x">2. Copia de la orden médica del servicio asociado a la solicitud</td>
     <td colspan="4"><input type="checkbox" name="doc_id_titular" value="x">5. Copia documento de identificación del titular de la cuenta bancaria</td>    
    </tr>

    <tr>
     <td colspan="6"><input type="checkbox" name="soporte_pro" value="x">3. Soporte de programación o asistencia al servicio en salud asociado a la solicitud</td>
    </tr>

    <td colspan="5">Otros:</td>

    <tr>
     <td colspan="3"><input type="checkbox" name="fallo_tutela" value="x"> 6. Fallo de tutela, solo SI el resuelve se encuentra asociado a la solicitud</td>
     <td colspan="3"><input type="checkbox" name="cop_documental" value="x">8. Copia documento de identificación del acompañante</td>    
    </tr>

    <tr>
     <td colspan="3"><input type="checkbox" name="aut_tuto" value="x">7. Autorización otorgada como tutor o representante del usuario</td>
     <td colspan="3"><input type="checkbox" name="facturas_apro" value="x">9. Factura(s) electrónica(s) de venta a nombre del usuario </td>    
    </tr>

    <td colspan="6">- Pertinencia</td>

    <tr>
    <td colspan="1" style="padding-left: 70px;"> - Transporte por trayecto ≥ 1 SMDLV
            <input type="radio" name="FOMAG" value="si"> SI
            <input type="radio" name="FOMAG" value="no"> NO
            <td><label for="Intermunicipal">Intermunicipal</label><br><input type="checkbox" id="Intermunicipal" name="Intermunicipal" value="x"></td>
            <td><label for="Fluvial">Fluvial</label><br><input type="checkbox" id="Fluvial" name="Fluvial" value="x"></td>
            <td><label for="Aéreo">Aéreo</label><br><input type="checkbox" id="Aéreo" name="Aéreo" value="x"></td>
            <td><label for="Otros">Otros</label><br><input type="checkbox" id="Otros" name="Otros" value="x"></td>
            <td colspan="1">
            <label for="c">valor</label>
            <input type="text" name="can" id="can" style="width: 170px;" >
            </td>
        </td> 

    </tr>

    <tr>
    <td colspan="1" style="padding-left: 70px;">  - Hospedaje
            <input type="radio" name="Hospedaje" value="si"> SI
            <input type="radio" name="Hospedaje" value="no"> NO
            <td colspan="2"><label for="Criterio_medico">Criterio médico</label><br><input type="checkbox" id="Criterio_medico" name="Criterio_medico" value="x"></td>
            <td colspan="2"><label for="Criterio_administrativo">Criterio administrativo</label><br><input type="checkbox" id="Criterio_administrativo" name="Criterio_administrativo" value="x"></td>
            <td colspan="1">
            <label for="c">valor</label>
            <input type="text" name="can1" id="can1" style="width: 170px;">
            </td>
        </td> 
    </tr>

    <tr>
    <td colspan="3" style="padding-left: 70px;">  - Alimentación
            <input type="radio" name="Alimentación" value="si"> SI
            <input type="radio" name="Alimentación" value="no"> NO
            <td colspan="2" style="padding-right: 70px;"><label for="Alimentación_tute">Solo en caso de tutela</label><br><input type="checkbox" id="Alimentación_tute" name="Alimentación_tute" value="x"></td>
            <td colspan="2">
            <label for="c">valor</label>
            <input type="text" name="can2" id="can2" style="width: 170px;">
            </td>
        </td> 
    </tr>


    <tr>
    <td colspan="2" style="padding-left: 70px;">  - Acompañante
            <input type="radio" name="Acompañante" value="si"> SI
            <input type="radio" name="Acompañante" value="no"> NO
            <td  style="padding-right: 70px;"><label for="men_18">Menor 18 años</label><br><input type="checkbox" id="men_18" name="men_18" value="x"></td>
            <td  style="padding-right: 70px;"><label for="may_65">Mayor 65 años</label><br><input type="checkbox" id="may_65" name="may_65" value="x"></td>
            <td  style="padding-right: 70px;"><label for="Discapacidad">Discapacidad</label><br><input type="checkbox" id="Discapacidad" name="Discapacidad" value="x"></td>
        </td> 
    </tr>

    <tr>
    <td colspan="2" style="padding-left: 70px;">   Viáticos
            <input type="radio" name="Otro_opc" value="si"> SI
            <input type="radio" name="Otro_opc" value="no"> NO
            <td colspan="3">
            <input  type="text" name="otro_text" id="otro_text" style="width: 350px;">
            </td>
            <td colspan="1">
            <label for="c">Valor total</label>
            <input type="text" name="can3" id="can3" style="width: 170px;" placeholder="Resultado" readonly>
            </td>
        </td> 
    </tr>
    <script>
function parseValor(valor) {
    if (!valor) return 0;
    return parseFloat(valor.replace(',', '.')) || 0;
}

function sumarValores() {
    const val1 = parseValor(document.getElementById('can1').value);
    const val2 = parseValor(document.getElementById('can').value);
    const val3 = parseValor(document.getElementById('can2').value);
    const suma = val1 + val2 + val3;
    document.getElementById('can3').value = suma.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('can1').addEventListener('input', sumarValores);
    document.getElementById('can').addEventListener('input', sumarValores);
     document.getElementById('can2').addEventListener('input', sumarValores);
});
</script>
    <td colspan="6">
            <label for="pertinencia">Justificación de pertinencia</label>
            <input  type="text" name="pertinencia" id="pertinencia" style="width: 850px;">
    </td>

</table>

</form>
<form id="form_pdf" action="generar_pdf_viaticos.php" method="POST" target="_blank" style="display: none;">
  <input type="hidden" name="datos_json" id="datos_json_pdf">
</form>

<!-- Contenedor de botones alineado al inicio del formulario -->
<!-- Botones alineados completamente a la izquierda -->
<div style="display: flex; gap: 10px; justify-content: flex-start;">
    <button type="button" id="guardar_solicitud" style="background: linear-gradient(to right, #673ab7, #03a9f4); color: white; border: none; padding: 10px 20px; border-radius: 5px;">
        Guardar Solicitud
    </button>

    <button type="button" id="descargar_pdf" style="background: linear-gradient(to right, #673ab7, #03a9f4); color: white; border: none; padding: 10px 20px; border-radius: 5px;">
        Descargar PDF
    </button>

    <button type="button" id="previsualizar_solicitud" style="background: linear-gradient(to right, #009688, #4caf50); color: white; border: none; padding: 10px 20px; border-radius: 5px;">
        Previsualizar solicitud
    </button>
    <a href="../menu.php" style="background-color: red; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        Atrás
    </a>
</div>


<!-- Modal -->
<div id="modalArchivo" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:20px; border-radius:10px; width:90%; max-width:500px; position:relative;">
        <h2>Subir archivo</h2>
        <form id="formSubirArchivo" method="post" enctype="multipart/form-data">
            <input type="file" name="archivo" required><br><br>
            <button type="submit" style="background:#28a745; color:white; padding:10px 15px; border:none; border-radius:5px;">Subir</button>
        </form>
        <br>
        <button onclick="document.getElementById('modalArchivo').style.display='none'" style="background:#dc3545; color:white; padding:10px 15px; border:none; border-radius:5px;">
            Cerrar
        </button>
    </div>
</div>

     
    <div id="preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:20px; width:90%; max-width:900px; max-height:85%; overflow:auto; border-radius:8px;">
        <h3 style="margin-top:0;">Vista previa de la solicitud</h3>
        <pre id="preview-content" style="white-space:pre-wrap; word-break:break-word;"></pre>
        <div style="margin-top:10px; text-align:right;">
            <button type="button" id="cerrar-preview">Cerrar</button>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('formulario');
    const btnPreview = document.getElementById('previsualizar_solicitud');
    const modal = document.getElementById('preview-modal');
    const content = document.getElementById('preview-content');
    const btnCerrar = document.getElementById('cerrar-preview');

    if (!form || !btnPreview || !modal || !content || !btnCerrar) return;

    const checkGroups = {
        discapacidad: ['fisica', 'visual', 'auditiva', 'intelectual', 'psicosocial', 'sordoceguera', 'multiple', 'no'],
        afiliacion_estado: ['Activo', 'Protecci�n_Laboral', 'Proteccion_Laboral', 'Suspendido', 'Retirado'],
        soportes: ['cop_identificacion', 'orden_medica', 'soporte_pro', 'cert_bancaria', 'doc_id_titular', 'fallo_tutela', 'aut_tuto', 'cop_documental', 'facturas_apro'],
        pertinencia_medios: ['Intermunicipal', 'Fluvial', 'A�reo', 'Aereo', 'AǸreo', 'Otros']
    };

    function agruparChecks(data, keys) {
        return keys.filter((key) => data.has(key));
    }

    function recolectarFormulario() {
        const data = new FormData(form);
        const obj = {};

        for (const [key, value] of data.entries()) {
            if (Object.prototype.hasOwnProperty.call(obj, key)) {
                if (!Array.isArray(obj[key])) obj[key] = [obj[key]];
                obj[key].push(value);
            } else {
                obj[key] = value;
            }
        }

        Object.entries(checkGroups).forEach(([nombre, llaves]) => {
            obj[nombre] = agruparChecks(data, llaves);
        });

        return obj;
    }

    btnPreview.addEventListener('click', () => {
        const obj = recolectarFormulario();
        content.textContent = JSON.stringify(obj, null, 2);
        modal.style.display = 'flex';
    });

    btnCerrar.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
})();
</script>
    </body>
</html>










