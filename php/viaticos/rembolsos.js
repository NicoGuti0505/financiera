$(document).ready(function () {
    console.log("âœ… Script cargado correctamente");

    // Obtener el Ãºltimo radicado al cargar la pÃ¡gina
    obtenerRadicado();

    // Evento para autocompletar datos al ingresar nÃºmero de documento

    // Evento para guardar solicitud con confirmaciÃ³n
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
                alert('No se pudo obtener el radicado. IntÃ©ntalo mÃ¡s tarde.');
            }
        },
        error: function (xhr, status, error) {
            console.error('Error en la solicitud AJAX:', status, error);
            alert('OcurriÃ³ un error al intentar obtener el radicado.');
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
                    // Actualizar los campos con la nueva informaciÃ³n
                    $('#departamento').val(response.descripcion_dep);
                    $('#ciudad').val(response.descripcion_mun);
                    $('#regional').val("Region " + response.region_id);

                }
            },
            error: function (xhr, status, error) {
                console.error('Error en la solicitud AJAX:', error);
                alert('OcurriÃ³ un error al obtener la informaciÃ³n del municipio.');
            }
        });
    }
});

// FunciÃ³n para obtener datos del usuario basado en el nÃºmero de documento
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
                    console.log("No se encontrÃ³ afiliado.");
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
                select.empty().append('<option value="">Seleccione una opciÃ³n</option>');
                tipos_documento.forEach(tipo => select.append(`<option value="${tipo.id}">${tipo.descripcion}</option>`));
                if (datos.tipo_documento) select.val(datos.tipo_documento);

                // Rellenar select de banco
                const selectBanco = $('#t_banco');
                selectBanco.empty().append('<option value="">Seleccione una opciÃ³n</option>');
                tipos_banco.forEach(tipo => selectBanco.append(`<option value="${tipo.id}">${tipo.descripcion}</option>`));

                // Rellenar select de tipo de cuenta
                const selectCuenta = $('#t_cuenta');
                selectCuenta.empty().append('<option value="">Seleccione una opciÃ³n</option>');
                tipos_cuen.forEach(tipo => selectCuenta.append(`<option value="${tipo.id}">${tipo.descripcion}</option>`));

                // Rellenar select de ciudad
                const selectCiudad = $('#ciudad_residencia');
                selectCiudad.empty().append('<option value="">Seleccione una opciÃ³n</option>');
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
    }, 800); // Esperar 800ms despuÃ©s de que el usuario deje de escribir
});

// FunciÃ³n para llenar selects
function llenarSelect(selector, opciones, valorSeleccionado = '') {
    const select = $(selector);
    select.empty();
    select.append('<option value="">Seleccione una opciÃ³n</option>');

    opciones.forEach(function(opcion) {
        select.append(`<option value="${opcion.id}">${opcion.descripcion}</option>`);
    });

    if (valorSeleccionado) {
        select.val(valorSeleccionado);
    }
}

function dividirNombreCompleto(nombreCompleto) {
    let partes = nombreCompleto.trim().split(/\s+/); // Divide por uno o mÃ¡s espacios

    // Ajustar segÃºn la cantidad de partes del nombre
    return {
        primer_nombre: partes[0] || '',
        segundo_nombre: partes.length === 4 ? partes[1] : (partes.length >= 3 ? partes[1] : ''),
        primer_apellido: partes.length === 4 ? partes[2] : (partes.length >= 2 ? partes[partes.length - 2] : ''),
        segundo_apellido: partes.length >= 4 ? partes[3] : (partes.length === 3 ? partes[2] : '')
    };
}



function guardarSolicitud() {
    const form = document.getElementById("formulario");
    if (!form) {
        alert('No se encontró el formulario.');
        return;
    }
    // 🔒 Asegurar que REGION viaje solo como número al PHP
    const regionInput = document.getElementById('regional');
    if (regionInput) {
        regionInput.value = regionInput.value.replace(/\D/g, '');
    }

    const formData = new FormData(form);
    formData.append('action', 'guardar_viaticos');

    $.ajax({
        url: 'funcion_rembolso.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
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
        console.error("âš ï¸ ERROR: Falta algÃºn campo en la fila.");
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


// FunciÃ³n para calcular el total solicitado
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


// FunciÃ³n para formatear a moneda colombiana (COP)
function formatCurrency(input) {
    let value = input.value.replace(/[^0-9]/g, ''); // Quitar caracteres no numÃ©ricos

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
                alert("MÃ¡ximo permitido: 250 caracteres.");
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
            alert("Debe completar el campo de Departamento y NÃºmero de IdentificaciÃ³n antes de subir archivos.");
            return;
        }

        // Construir URL con ambos valores
        const url = `subir_archivo.php?departamento=${encodeURIComponent(departamento)}&identificacion=${encodeURIComponent(identificacion)}`;

        // Dimensiones y posiciÃ³n centrada
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




