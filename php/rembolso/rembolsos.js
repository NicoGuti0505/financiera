$(document).ready(function () {
    console.log("✅ Script cargado correctamente");

    // Obtener el último radicado al cargar la página
    obtenerRadicado();

    // Evento para autocompletar datos al ingresar número de documento
    $('#c').on('input', function () {
        let numero_documento = $(this).val();
        if (numero_documento.length > 0) {
            obtenerDatosUsuario(numero_documento);
        }
    });

    // Evento para guardar solicitud con confirmación
    $('#guardar_solicitud').on('click', function () {
        if (confirm("¿Está seguro de que desea guardar la solicitud? se reiniciara el formulario al aceptar")) {
            guardarSolicitud();
        } else {
            alert("Operación cancelada.");
        }
    });

    // Evento para cálculo de valores en la tabla
    document.querySelectorAll(".cantidad, .valor_unitario").forEach(input => {
        input.addEventListener("input", function () {
            calcularFila(this);
        });
    });
});


// Función para obtener el próximo radicado
function obtenerRadicado() {
    console.log("Iniciando solicitud para obtener el siguiente radicado...");
    $.ajax({
        url: 'funcion_rembolso.php',
        method: 'POST',
        data: { action: 'get_next_radicado' },
        dataType: 'json',
        success: function (response) {
            console.log('Respuesta del servidor:', response);
            if (response.error) {
                alert('Error: ' + response.error);
            } else if (response.next_radicado) {
                $('#radicado').val(response.next_radicado);
            } else {
                alert('No se pudo obtener el radicado. Inténtalo más tarde.');
            }
        },
        error: function (xhr, status, error) {
            console.error('Error en la solicitud AJAX:', status, error);
            alert('Ocurrió un error al intentar obtener el radicado.');
        }
    });
}

$('#ciudad_residencia').on('change', function () {
    let codigo_dane_municipio = $(this).val();
    
    if (codigo_dane_municipio) {
        $.ajax({
            url: 'funcion_rembolso.php',
            method: 'POST',
            data: { action: 'get_municipio_info', codigo_dane: codigo_dane_municipio },
            dataType: 'json',
            success: function (response) {
                if (response.error) {
                    alert('Error: ' + response.error);
                } else {
                    // Actualizar los campos con la nueva información
                    $('#departamento').val(response.descripcion_dep);
                    $('#ciudad').val(response.descripcion_mun);
                    $('#regional').val("Region " + response.region_id);

                }
            },
            error: function (xhr, status, error) {
                console.error('Error en la solicitud AJAX:', error);
                alert('Ocurrió un error al obtener la información del municipio.');
            }
        });
    }
});

// Función para obtener datos del usuario basado en el número de documento
let timer; // Variable para manejar el tiempo de espera

$('#c').on('keyup', function () {
    clearTimeout(timer); // Limpiar el temporizador anterior

    let numero_documento = $(this).val().trim(); 

    if (numero_documento.length < 6) return; // Evita consultas con pocos caracteres

    timer = setTimeout(function () { 
        $.ajax({
            url: 'funcion_rembolso.php',
            method: 'POST',
            data: { numero_documento: numero_documento },
            dataType: 'json',
            success: function (data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                const { datos, tipos_documento, tipos_banco, tipos_cuen, tipos_municipios } = data;

                if (!datos) {
                    console.log("No se encontró afiliado.");
                    return;
                }

                $('#regional').val("Region  " + datos.region_id).prop('readonly', true);
                $('#departamento').val(datos.descripcion_dep);
                $('#ciudad').val(datos.descripcion_mun);
                $('#nombre').val(
                    [datos.primer_nombre, datos.segundo_nombre, datos.primer_apellido, datos.segundo_apellido]
                        .filter(Boolean)
                        .join(' ')
                );
                $('#estado_afiliacion').val(datos.estado_afiliacion);
                $('#direccion').val(datos.direccion_Residencia_cargue);
                $('#telefono_fijo').val(datos.telefono);
                $('#telefono_celular').val(datos.celular_principal);
                $('#correo').val(datos.correo_principal);

                // Rellenar select de tipo de documento
                const select = $('#t_identificacion');
                select.empty().append('<option value="">Seleccione una opción</option>');
                tipos_documento.forEach(tipo => select.append(`<option value="${tipo.id}">${tipo.descripcion}</option>`));
                if (datos.tipo_documento) select.val(datos.tipo_documento);

                // Rellenar select de banco
                const selectBanco = $('#t_banco');
                selectBanco.empty().append('<option value="">Seleccione una opción</option>');
                tipos_banco.forEach(tipo => selectBanco.append(`<option value="${tipo.id}">${tipo.descripcion}</option>`));

                // Rellenar select de tipo de cuenta
                const selectCuenta = $('#t_cuenta');
                selectCuenta.empty().append('<option value="">Seleccione una opción</option>');
                tipos_cuen.forEach(tipo => selectCuenta.append(`<option value="${tipo.id}">${tipo.descripcion}</option>`));

                // Rellenar select de ciudad
                const selectCiudad = $('#ciudad_residencia');
                selectCiudad.empty().append('<option value="">Seleccione una opción</option>');
                tipos_municipios.forEach(tipo => selectCiudad.append(`<option value="${tipo.id}">${tipo.descripcion_mun}</option>`));

                if (datos.codigo_dane_municipio_atencion) {
                    selectCiudad.val(datos.codigo_dane_municipio_atencion);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error al obtener los datos:', error);
                alert('Error al obtener los datos.');
            }
        });
    }, 800); // Esperar 800ms después de que el usuario deje de escribir
});

// Función para llenar selects
function llenarSelect(selector, opciones, valorSeleccionado = '') {
    const select = $(selector);
    select.empty();
    select.append('<option value="">Seleccione una opción</option>');

    opciones.forEach(function(opcion) {
        select.append(`<option value="${opcion.id}">${opcion.descripcion}</option>`);
    });

    if (valorSeleccionado) {
        select.val(valorSeleccionado);
    }
}

function dividirNombreCompleto(nombreCompleto) {
    let partes = nombreCompleto.trim().split(/\s+/); // Divide por uno o más espacios

    // Ajustar según la cantidad de partes del nombre
    return {
        primer_nombre: partes[0] || '',
        segundo_nombre: partes.length === 4 ? partes[1] : (partes.length >= 3 ? partes[1] : ''),
        primer_apellido: partes.length === 4 ? partes[2] : (partes.length >= 2 ? partes[partes.length - 2] : ''),
        segundo_apellido: partes.length >= 4 ? partes[3] : (partes.length === 3 ? partes[2] : '')
    };
}


// Función para guardar la solicitud
function guardarSolicitud() {
    // Obtener los motivos seleccionados
    const motivosSeleccionados = $('input[name="motivo[]"]:checked').map(function () {
        return this.value;
    }).get();

    console.log("Motivos seleccionados:", motivosSeleccionados); // ✅ Verificar los motivos

    // Obtener y dividir el nombre completo
    let nombreCompleto = $('#nombre').val();
    let nombreDividido = dividirNombreCompleto(nombreCompleto) || {};

    // Limpiar val_rembolso (dejar solo números)
    let val_rembolso = $('#val_rembolso').val().replace(/\D/g, '');

    // Crear el objeto de datos
    const data = {
        radicado: $('#radicado').val(),
        ciudad_residencia: $('#ciudad_residencia').val(),
        t_identificacion: $('#t_identificacion').val(),
        c: $('#c').val(),
        primer_nombre: nombreDividido.primer_nombre || '',
        segundo_nombre: nombreDividido.segundo_nombre || '',
        primer_apellido: nombreDividido.primer_apellido || '',
        segundo_apellido: nombreDividido.segundo_apellido || '',
        nombre_repre: $('#nombre_repre').val(),
        segundo_n: $('#segundo_n').val(),
        primer_p: $('#primer_p').val(),
        segundo_p: $('#segundo_p').val(),
        t_banco: $('#t_banco').val(),
        id_titular: $('#id_titular').val(),
        t_cuenta: $('#t_cuenta').val(),
        n_cuenta: $('#n_cuenta').val(),
        url: $('#url').val(),
        direccion: $('#direccion').val(),
        telefono_fijo: $('#telefono_fijo').val(),
        telefono_celular: $('#telefono_celular').val(),
        correo: $('#correo').val(),
        val_rembolso: val_rembolso, // ✅ Valor limpio
        motivos: JSON.stringify(motivosSeleccionados) // ✅ Convertir a JSON
    };

    // Validar campos obligatorios
    const camposObligatorios = [
        'radicado', 'ciudad_residencia', 't_identificacion', 'c', 'nombre_repre', 'segundo_n',
        'primer_p', 'segundo_p', 't_banco', 'id_titular', 't_cuenta', 'n_cuenta', 'url', 'val_rembolso'
    ];

    let primerCampoVacio = null;

    for (const campo of camposObligatorios) {
        let input = $('#' + campo);
        if (!data[campo]) {
            input.css("border", "2px solid red"); // 🔴 Resaltar campo vacío
            if (!primerCampoVacio) {
                primerCampoVacio = input; // Guardar el primer campo vacío
            }
        } else {
            input.css("border", ""); // ✅ Restaurar borde si está lleno
        }
    }

    if (primerCampoVacio) {
        primerCampoVacio.focus(); // 🔥 Llevar foco al primer campo vacío
        alert("Por favor, complete los campos obligatorios.");
        return;
    }

    if (motivosSeleccionados.length === 0) {
        alert('Debe seleccionar al menos un motivo.');
        return;
    }

    // Enviar datos con AJAX
    $.ajax({
        url: 'funcion_rembolso.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function (response) {
            console.log("Respuesta del servidor:", response);
            if (response.error) {
                alert(response.error);
            } else {
                alert('Solicitud guardada correctamente.');
                location.reload();
            }
        },
        error: function (xhr, status, error) {
            console.error('Error en la solicitud AJAX:', error, xhr.responseText);
            alert('Ocurrió un error al guardar la solicitud.');
        }
    });
}




function calcularFila(elemento) {
    let fila = elemento.closest("tr");
    let cantidadInput = fila.querySelector(".cantidad");
    let valorUnitarioInput = fila.querySelector(".valor_unitario");
    let valorTotalInput = fila.querySelector(".valor_total");

    if (!cantidadInput || !valorUnitarioInput || !valorTotalInput) {
        console.error("⚠️ ERROR: Falta algún campo en la fila.");
        return;
    }

    let cantidad = parseFloat(cantidadInput.value.replace(/\D/g, "")) || 0;
    let valorUnitario = parseFloat(valorUnitarioInput.value.replace(/\D/g, "")) || 0;
    let total = cantidad * valorUnitario;

    // Aplicar formato a Valor Unitario y Valor Total
    valorUnitarioInput.value = new Intl.NumberFormat("es-CO").format(valorUnitario);
    valorTotalInput.value = new Intl.NumberFormat("es-CO").format(total);

    calcularTotalSolicitado();
}


// Función para calcular el total solicitado
function calcularTotalSolicitado() {
    let totalSolicitado = 0;

    document.querySelectorAll(".valor_total").forEach(input => {
        let valor = input.value.replace(/\./g, "").replace(/[^0-9]/g, "");
        let numero = parseFloat(valor) || 0;
        totalSolicitado += numero;
    });

    let totalInput = document.getElementById("valor_total_solicitado");
    if (totalInput) {
        totalInput.value = new Intl.NumberFormat("es-CO").format(totalSolicitado);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    let valReembolsoInput = document.getElementById("val_rembolso");

    if (valReembolsoInput) {
        valReembolsoInput.addEventListener("input", function () {
            formatCurrency(this);
        });
    }
});

// Función para formatear a moneda colombiana (COP)
function formatCurrency(input) {
    let value = input.value.replace(/[^0-9]/g, ''); // Quitar caracteres no numéricos

    if (value === "") {
        input.value = "$0";
        return;
    }

    let formattedValue = new Intl.NumberFormat('es-CO').format(parseInt(value, 10));
    input.value = `$${formattedValue}`;
}

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("input[name='motivo_des'], input[name='observaciones'], input[name='justificacion'], input[name='justi_rembolso']")
    .forEach(input => {
        input.addEventListener("input", function () {
            if (this.value.length > 250) {
                this.value = this.value.substring(0, 250); // Recorta el texto
                alert("Máximo permitido: 250 caracteres.");
            }
        });
    });
});


$(document).ready(function () {
    function validarValores() {
        let solicitado = limpiarNumero($("#valor_total_solicitado").val());
        let reembolso = limpiarNumero($("#val_rembolso").val());

        let btnGuardar = $("#guardar_solicitud");

        if (reembolso < solicitado) {
            $("#val_rembolso").css("border", "2px solid green");
            btnGuardar.prop("disabled", false);
        } else if (reembolso === solicitado) {
            $("#val_rembolso").css("border", "2px solid orange");
            btnGuardar.prop("disabled", false);
        } else {
            $("#val_rembolso").css("border", "2px solid red");
            btnGuardar.prop("disabled", true);
        }
    }

    function limpiarNumero(valor) {
        // Elimina puntos, comas y símbolos de moneda antes de convertirlo en número
        return parseFloat(valor.replace(/[$,.]/g, '')) || 0;
    }

    $("#val_rembolso").on("input", function () {
        validarValores();
    });

    validarValores();
});
