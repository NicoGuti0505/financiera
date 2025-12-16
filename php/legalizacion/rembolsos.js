$(document).ready(function () {

    // Cargar combos al iniciar
    function cargarCatalogo(id, tipo, valorSeleccionado = null) {
        $.get('catalogos_select.php', { tipo }, function (data) {
            $('#' + id)
                .html('<option value="">Seleccione una opción</option>' + data)
                .val(valorSeleccionado) // Si hay un valor a seleccionar
                .trigger('change');
        });
    }

    // Inicializar Select2
    $('#ciudad_residencia, #t_identificacion, #t_cuenta, #t_banco').select2({
        placeholder: "Seleccione una opción",
        allowClear: true
    });

    // Cargar municipio inicialmente
    $.get("municipios.php", function (data) {
        $("#ciudad_residencia").html('<option value="">Seleccione una opción</option>' + data);
    });

    // Al escribir un radicado
    $('#radicado').on('change', function () {
        const radicado = $(this).val().trim();
        if (!radicado) return;

        $.ajax({
            url: 'buscar_solicitud.php',
            method: 'GET',
            data: { radicado },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const d = response.data;

                    // Datos básicos
                    const nombreCompleto = [d.primer_nombre, d.segundo_nombre, d.primer_apellido, d.segundo_apellido].filter(Boolean).join(' ');
                    $('#nombre').val(nombreCompleto);
                    $('#c').val(d.numero_identificacion_titular);
                    $('#correo').val(d.correo_principal);
                    $('#telefono_celular').val(d.celular_principal);
                    $('#telefono_fijo').val(d.telefono);
                    $('#direccion').val(d.direccion_Residencia_cargue);
                    $('#val_rembolso').val(d.val_rembolso);
                    $('#nombre_repre').val(d.primer_nombre);
                    $('#segundo_n').val(d.segundo_n);
                    $('#primer_p').val(d.primer_p);
                    $('#segundo_p').val(d.segundo_p);
                    $('#id_titular').val(d.numero_identificacion);
                    $('#n_cuenta').val(d.numero_cuenta);
                    $('#url').val(d.url_drive);

                    // Autocompletar selects
                    cargarCatalogo('t_identificacion', 'tipo_documento', d.tipo_documento);
                    cargarCatalogo('t_cuenta', 'tipo_cuenta', d.tipo_cuenta);
                    cargarCatalogo('t_banco', 'banco', d.entidad_bancaria);

                    const codigoDane = (d.codigo_dane_municipio_atencion || '').trim();
                    if ($("#ciudad_residencia option[value='" + codigoDane + "']").length > 0) {
                        $('#ciudad_residencia').val(codigoDane).trigger('change');
                    } else {
                        console.warn('Código DANE no encontrado en el select:', codigoDane);
                    }
                } else {
                    alert(response.message || 'No se encontraron datos.');
                }
            },
            error: function () {
                alert('Error consultando los datos.');
            }
        });
    });

    // Al cambiar ciudad de residencia
    $('#ciudad_residencia').on('change', function () {
        const codigoDane = $(this).val();
        if (!codigoDane) return;

        $.ajax({
            url: 'buscar_solicitud.php',
            method: 'POST',
            data: {
                action: 'get_municipio_info',
                codigo_dane: codigoDane
            },
            dataType: 'json',
            success: function (response) {
                if (response.error) {
                    alert('Error: ' + response.error);
                } else {
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
    });
});

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

    let totalInput = document.getElementById("val_rembolso");
    if (totalInput) {
        totalInput.value = new Intl.NumberFormat("es-CO").format(totalSolicitado);
    }
}


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


document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('descargar_pdf').addEventListener('click', function () {
      const form = document.getElementById('formulario');
      if (!form) {
        console.error("Formulario no encontrado");
        return;
      }
  
      const formData = new FormData(form);
      const datos = Object.fromEntries(formData.entries());
  
      // Captura arrays manuales
      datos['cantidad1'] = formData.getAll('cantidad1[]');
      datos['cantidad2'] = formData.getAll('cantidad2[]');
      datos['cantidad3'] = formData.getAll('cantidad3[]');
      datos['cantidad4'] = formData.getAll('cantidad4[]');
      datos['cantidad5'] = formData.getAll('cantidad5[]');
      datos['cantidad6'] = formData.getAll('cantidad6[]');
      datos['valor_unitario1'] = formData.getAll('valor_unitario1[]');
      datos['valor_unitario2'] = formData.getAll('valor_unitario2[]');
      datos['valor_unitario3'] = formData.getAll('valor_unitario3[]');
      datos['valor_unitario4'] = formData.getAll('valor_unitario4[]');
      datos['valor_unitario5'] = formData.getAll('valor_unitario5[]');
      datos['valor_unitario6'] = formData.getAll('valor_unitario6[]');
      datos['valor_total1'] = formData.getAll('valor_total1[]');
  
      document.getElementById('datos_json_pdf').value = JSON.stringify(datos);
      document.getElementById('form_pdf').submit();
    });
  });




document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("abrirPopup").addEventListener("click", function () {
        const departamento = document.getElementById("departamento")?.value?.trim();
        const identificacion = document.getElementById("c")?.value?.trim();

        if (!departamento || !identificacion) {
            alert("Debe completar el campo de Departamento y Número de Identificación antes de subir archivos.");
            return;
        }

        // Construir URL con ambos valores
        const url = `subir_archivo.php?departamento=${encodeURIComponent(departamento)}&identificacion=${encodeURIComponent(identificacion)}`;

        // Dimensiones y posición centrada
        const width = 600;
        const height = 400;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        // Abrir popup centrado
        const popup = window.open(
            url,
            "SubirArchivoPopup",
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );

        if (!popup) {
            alert("Activa las ventanas emergentes para continuar.");
        }
    });
});

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


$('#guardar_solicitud').on('click', guardarSolicitud);

function guardarSolicitud() {

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



    // Enviar datos con AJAX
    $.ajax({
        url: 'guardar.php',
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