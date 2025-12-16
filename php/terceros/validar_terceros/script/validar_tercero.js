// Constants
const CAMPOS_VALIDACION_ID = 'camposValidacion';
const AGREGAR_CAMPOS_ID = 'agregarCampos';
const FORM_ID = 'validacionForm';

// Helper functions
const createElement = (tag, attributes = {}) => {
    const element = document.createElement(tag);
    Object.entries(attributes).forEach(([key, value]) => {
        if (key === 'classList') {
            element.classList.add(...value);
        } else {
            element.setAttribute(key, value);
        }
    });
    return element;
};

// Main functions
function agregarCampos() {
    
    const camposValidacion = document.getElementById(CAMPOS_VALIDACION_ID);
    const contenedorPrincipal = createElement('div', {
        classList: ['border', 'p-1', 'mb-1']
    });

    // Primera fila con sus botones
    const fila1Container = createElement('div', {
        classList: ['flex', 'space-x-1', 'mb-1']
    });

    const fila1 = createElement('div', {
        classList: ['flex', 'space-x-1', 'flex-grow']
    });

    // Segunda fila con sus botones
    const fila2Container = createElement('div', {
        classList: ['flex', 'space-x-1']
    });

    const fila2 = createElement('div', {
        classList: ['flex', 'space-x-1', 'flex-grow']
    });
    const selectEmbargo = createElement('select', {
        id: 'embargo_select',
        name: 'embargo[]', 
        class: ['border', 'p-2', 'rounded', 'w-full'],
    });
    
    selectEmbargo.innerHTML = `
        <option value="" selected disabled>Embargo</option>
        <option value="0">No</option>
        <option value="1">Sí</option>
    `;
    
    const inputs1 = [
        createInput('id', 'Identificación', { required: '' }),
        createInput('tercero_id', 'ID Tercero', { required: '', readonly: true }),
        createInput('nombre', 'Nombre', { readonly: true}),
        createSelect('tipo_contrato_id', tipos_contrato, { required: ''}),
        createInput('cantidad_facturas', 'Cant. Fact', { required: ''}),
        createInput('fac_desde', 'Desde', { required: '' })
    ];
    
    const inputs2 = [
        createInput('fac_hasta', 'Hasta', { required: '' }),
        createInput('valor_total', 'Valor Total', { inputmode: 'numeric', required: '' }),
        createInput('porcentaje_retencion', '% Ret', { inputmode: 'numeric', class: 'border p-2', style: 'width: 80px' }),
        createInput('valor_retener', 'Valor Retener', { inputmode: 'numeric', required: '' }),
        createInput('valor_pago', 'Valor Pago', { inputmode: 'numeric', required: '', readonly: '' }),
        createInput('zese', 'ZESE'),
        createInput('observacion', 'Observación'),
        selectEmbargo
    ]




    const buscarButton = createElement('button', {
        type: 'button',
        classList: ['abrirFormulario', 'bg-blue-500', 'text-white', 'px-2', 'py-1', 'rounded', 'hover:bg-blue-600', 'ml-1']
    });
    buscarButton.textContent = '🔍';

    const deleteButton = createElement('button', {
        type: 'button',
        classList: ['eliminarCampos', 'bg-red-500', 'text-white', 'px-2', 'py-1', 'rounded', 'hover:bg-red-600', 'ml-1']
    });
    deleteButton.textContent = '🗑️';

    // Agregar inputs a las filas
    inputs1.forEach(input => fila1.appendChild(input));
    inputs2.forEach(input => fila2.appendChild(input));

    // Agregar filas y botones a sus contenedores
    fila1Container.appendChild(fila1);
    fila1Container.appendChild(buscarButton);
    
    fila2Container.appendChild(fila2);
    fila2Container.appendChild(deleteButton);

    // Construir el contenedor principal
    contenedorPrincipal.appendChild(fila1Container);
    contenedorPrincipal.appendChild(fila2Container);

    camposValidacion.appendChild(contenedorPrincipal);

    // Event listeners
    const inputNit = inputs1[0];
    const inputNombre = inputs1[2];
    const inputValorTotal = inputs2[1];
    const inputPorcentajeRetencion = inputs2[2];
    const inputValorRetener = inputs2[3];
    const inputValorPago = inputs2[4];
    const inputCantidadFacturas = inputs1[4];
    
    inputNit.addEventListener('blur', () => buscarInformacion(contenedorPrincipal));
    inputNombre.addEventListener('blur', () => bloquear(contenedorPrincipal));
    buscarButton.addEventListener('click', () => buscarInformacion(contenedorPrincipal, true));
    deleteButton.addEventListener('click', () => eliminarCampos(contenedorPrincipal));
    
    inputCantidadFacturas.addEventListener('blur', formatearCampoEntero);
    inputValorTotal.addEventListener('blur', formatearCampoMoneda);
    inputValorRetener.addEventListener('blur', formatearCampoMoneda);
    inputValorPago.addEventListener('blur', formatearCampoMoneda);
    inputPorcentajeRetencion.addEventListener('blur', formatearCampoPorcentaje);

    // Calcular valor_pago cuando cambien valor_total o valor_retener
    const calcularValores = (origen) => {
        const valorTotal = parsearMoneda(inputValorTotal.value) || 0;
        let valorRetener = parsearMoneda(inputValorRetener.value) || 0;
        let porcentajeRetencion = parsearPorcentaje(inputPorcentajeRetencion.value) || 0;
    
        if (origen === 'porcentaje') {
            // Si se modificó el porcentaje, calcular el valor a retener
            valorRetener = valorTotal * (porcentajeRetencion / 100);
            inputValorRetener.value = formatearMoneda(valorRetener);
        } else if (origen === 'valor') {
            // Si se modificó el valor a retener, calcular el porcentaje
            porcentajeRetencion = (valorRetener / valorTotal) * 100;
            inputPorcentajeRetencion.value = formatearPorcentaje(porcentajeRetencion);
        }
    
        // Calcular el valor a pagar
        const valorPago = valorTotal - valorRetener;
        inputValorPago.value = formatearMoneda(valorPago);
    };
    
    // Event listeners para los cálculos
    inputValorTotal.addEventListener('blur', () => calcularValores('valor'));
    inputPorcentajeRetencion.addEventListener('blur', () => calcularValores('porcentaje'));
    inputValorRetener.addEventListener('blur', () => calcularValores('valor'));
}

function eliminarCampos(campoSet) {
    if (document.querySelectorAll('#' + CAMPOS_VALIDACION_ID + ' > div').length > 1) {
        campoSet.remove();
    } else {
        alert('Debe haber al menos un conjunto de campos.');
    }
}

function formatearCampoEntero(event) {
    const input = event.target;
    const valor = parseInt(parsearMoneda(input.value), 10);
    if (!isNaN(valor)) {
        input.value = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor);
    } else {
        input.value = '';
    }
}

function formatearCampoMoneda(event) {
    const input = event.target;
    const valor = parsearMoneda(input.value);
    if (valor !== null) {
        input.value = formatearMoneda(valor);
    }
}

function formatearCampoPorcentaje(event) {
    const input = event.target;
    const valor = parsearPorcentaje(input.value);
    if (valor !== null) {
        input.value = formatearPorcentaje(valor);
    }
}

async function bloquear(campoSet, form = false) {
    
    const idValue = campoSet.querySelector('input[name="id[]"]').value;
    const nombreInput = campoSet.querySelector('input[name="nombre[]"]');
    if (idValue== 'No Existe') {
        nombreInput.value = '';
        campoSet.querySelector('input[name="tercero_id[]"]').value = 'Debe Crear el Tercero';
    }
}

async function buscarInformacion(campoSet, form = false) {
    const identificacion = campoSet.querySelector('input[name="id[]"]').value;
    
    if (!identificacion) return;

    try {
        const response = await fetch(inf_tercero, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `identificacion=${encodeURIComponent(identificacion)}`
        });

        const responseText = await response.text();
        let data;

        try {
            data = JSON.parse(responseText);
        } catch (error) {
            console.error('Error al parsear la respuesta JSON:', error);
            console.error('Respuesta del servidor:', responseText);
            alert(`Error al procesar la respuesta del servidor. Por favor, contacte al administrador y proporcione el siguiente error:\n\n${responseText}`);
            return;
        }

        if (data.success) {
            if (!form){
                const nombreInput = campoSet.querySelector('input[name="nombre[]"]');
                campoSet.querySelector('input[name="tercero_id[]"]').value = data.row.id;
                nombreInput.value = data.nombre_completo;
                nombreInput.setAttribute('readonly', true);
            }else{
                abrirFormulario(data);
            }
        } else {
            if (!form){
                const nombreInput = campoSet.querySelector('input[name="nombre[]"]');
                campoSet.querySelector('input[name="tercero_id[]"]').value = 'No existe';
                nombreInput.removeAttribute('readonly');
                alert(`${data.message}`);
            }else{
                abrirFormulario(data);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert(`Ocurrió un error al buscar la información del tercero: ${error.message}`);
    }
}

async function abrirFormulario(data) {
    console.log("Datos a enviar:", data);

    try {
        const response = await fetch(formulario, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            const htmlContent = await response.text();
            const newWindow = window.open('', '_blank');
            newWindow.document.write(htmlContent);
            newWindow.document.close();
        } else {
            throw new Error('Error en la respuesta del servidor');
        }
    } catch (error) {
        console.error('Error al abrir el formulario:', error);
        alert('Ocurrió un error al abrir el formulario. Por favor, intente de nuevo.');
    }
}

var newRecords = [];
var updatedRecords = [];

async function guardarValidaciones(event) {
    
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    const validaciones = [];
    const identificaciones = formData.getAll('id[]');
    
    for (let i = 0; i < identificaciones.length; i++) {
        let valor_total = formData.getAll('valor_total[]')[i];
        valor_total = valor_total.replace(/[$\s]/g, '');
        valor_total = valor_total.replace(/\./g, '');
        valor_total = parseFloat(valor_total);

        let valor_retener = formData.getAll('valor_retener[]')[i];
        valor_retener = valor_retener.replace(/[$\s]/g, '');
        valor_retener = valor_retener.replace(/\./g, '');
        valor_retener = parseFloat(valor_retener);

        let valor_pago = formData.getAll('valor_pago[]')[i];
        valor_pago = valor_pago.replace(/[$\s]/g, '');
        valor_pago = valor_pago.replace(/\./g, '');
        valor_pago = parseFloat(valor_pago);

        let cantidad_facturas = formData.getAll('cantidad_facturas[]')[i];
        cantidad_facturas = cantidad_facturas.replace(/\./g, '');
        cantidad_facturas = parseInt(cantidad_facturas);

       

        


        validaciones.push({
            id: formData.getAll('id[]')[i],
            tercero_id: formData.getAll('tercero_id[]')[i],
            nombre: formData.getAll('nombre[]')[i],
            tipo_contrato_id: formData.getAll('tipo_contrato_id[]')[i],
            cantidad_facturas,
            fac_desde: formData.getAll('fac_desde[]')[i],
            fac_hasta: formData.getAll('fac_hasta[]')[i],
            valor_total,
            valor_retener,
            valor_pago,
            zese: formData.getAll('zese[]')[i],
            observacion: formData.getAll('observacion[]')[i],
            embargo : formData.getAll('embargo[]')[i]

          
        });

         
    }

    try {
        const response = await fetch(val_registros, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(validaciones),
        });

        const responseText = await response.text();
        let result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Error parsing JSON:', e);
            console.error('Response text:', responseText);
            alert('Error al procesar la respuesta del servidor. Por favor, contacte al administrador y proporcione el siguiente error:\n\n' + responseText);
            return;
        }

        if (result.success) {
            // Si la validación fue exitosa, guardamos directamente
            await guardarRegistros(result.newRecords, result.updatedRecords);

        } else {
            if (result.existingRecords && result.existingRecords.length > 0) {
                // Mostrar registros existentes y pedir confirmación
                mostrarRegistrosExistentes(result.existingRecords);
                document.getElementById('mensajeExistentes').classList.remove('hidden');
                document.getElementById('registrosValidados').classList.remove('hidden');
                
                // Guardar los registros nuevos y actualizados en variables globales
                newRecords = result.newRecords || [];
                updatedRecords = result.updatedRecords || [];
            } else {
                alert('Error al validar las validaciones: ' + result.message);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Ocurrió un error al validar las validaciones: ' + error.message);
    }
}

async function guardarRegistros(newRecords, updatedRecords) {
    limpiarAvisosRegistrosExistentes();
    try {
        const response = await fetch(guard_validaciones, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ newRecords, updatedRecords }),
        });

        const result = await response.json();

        if (result.success) {
            alert('Validaciones guardadas con éxito');
            window.location.href = '../../menu.php';
        } else {
            alert('Error al guardar las validaciones: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Ocurrió un error al guardar las validaciones: ' + error.message);
    }
}

function mostrarRegistrosExistentes(existingRecords) {
    const conjuntos = document.querySelectorAll('#camposValidacion > div');
    conjuntos.forEach(conjunto => {
        const nit = conjunto.querySelector('input[name="id[]"]').value;
        const factura = conjunto.querySelector('input[name="fac_desde[]"]').value;
        if (existingRecords.some(record => record.id === nit && record.fac_desde === factura)) {
            conjunto.classList.add('existing-record', 'bg-yellow-100');
            const mensajeExistente = document.createElement('div');
            mensajeExistente.textContent = 'Registro Existente';
            mensajeExistente.classList.add('text-red-500', 'text-sm', 'mt-1');
            conjunto.appendChild(mensajeExistente);
        }
    });
}
function limpiarAvisosRegistrosExistentes() {
    // Ocultar el mensaje general de registros existentes
    document.getElementById('mensajeExistentes').classList.add('hidden');
    
    // Ocultar el botón de registros validados
    document.getElementById('registrosValidados').classList.add('hidden');
    
    // Eliminar las clases y mensajes de registros existentes de cada conjunto de campos
    const conjuntos = document.querySelectorAll('#camposValidacion > div');
    conjuntos.forEach(conjunto => {
        conjunto.classList.remove('existing-record', 'bg-yellow-100');
        const mensajeExistente = conjunto.querySelector('div.text-red-500');
        if (mensajeExistente) {
            mensajeExistente.remove();
        }
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById(AGREGAR_CAMPOS_ID).addEventListener('click', agregarCampos);
    document.getElementById(FORM_ID).addEventListener('submit', guardarValidaciones);
    document.getElementById('registrosValidados').addEventListener('click', () => guardarRegistros(newRecords, updatedRecords));
    agregarCampos(); // Add the first set of fields when the page loads
});

