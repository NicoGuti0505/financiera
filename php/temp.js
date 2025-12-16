 document.addEventListener('DOMContentLoaded', function() {
    inicializarEventListeners();
    cargarRegistros();
});

function inicializarEventListeners() {
    // Botones principales
    document.getElementById('guardarDatos').addEventListener('click', () => guardarDatos(false));
    document.getElementById('registrosValidados').addEventListener('click', () => guardarDatos(true));
    document.getElementById('btnBuscar').addEventListener('click', cargarRegistros);
    
    // Delegación de eventos para campos dinámicos
    document.getElementById('camposInfTapa').addEventListener('input', function(e) {
        const input = e.target;
        if (input.name === 'nit[]' || input.name === 'fac_desde[]') {
            const contenedor = input.closest('.form-container');
            buscarInformacion(contenedor);
        }
    });
}

function cargarRegistros() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const camposInfTapa = document.getElementById('camposInfTapa');
    
    // Limpiar registros existentes
    camposInfTapa.innerHTML = '';
    document.getElementById('mensajeExistentes').classList.add('hidden');
    document.getElementById('registrosValidados').classList.add('hidden');

    // Mostrar indicador de carga
    const loadingIndicator = document.createElement('div');
    loadingIndicator.textContent = 'Cargando registros...';
    loadingIndicator.classList.add('text-blue-500', 'text-center', 'my-4');
    camposInfTapa.appendChild(loadingIndicator);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `ajax=1&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`
    })
    .then(response => response.json())
    .then(data => {
        loadingIndicator.remove();
        if (data.success) {
            document.getElementById('totalRegistros').textContent = 
                `Total de registros encontrados: ${data.registros.length}`;
            data.registros.forEach(registro => agregarCampos(registro));
        } else {
            mostrarMensajeError('No se encontraron registros para las fechas seleccionadas');
        }
    })
    .catch(error => {
        loadingIndicator.remove();
        mostrarMensajeError('Error al cargar los registros');
        console.error('Error:', error);
    });
}

function mostrarMensajeError(mensaje) {
    const camposInfTapa = document.getElementById('camposInfTapa');
    const mensajeError = document.createElement('div');
    mensajeError.textContent = mensaje;
    mensajeError.classList.add('text-red-500', 'text-center', 'my-4');
    camposInfTapa.appendChild(mensajeError);
    setTimeout(() => mensajeError.remove(), 3000);
}

function agregarCampos(datos = null) {
    const contenedor = document.createElement('div');
    contenedor.classList.add('form-container', 'border', 'p-4', 'rounded', 'mb-4');
    
    contenedor.appendChild(crearPrimeraFila());
    contenedor.appendChild(crearSegundaFila());
    contenedor.appendChild(crearBotonEliminar(contenedor));
    
    document.getElementById('camposInfTapa').appendChild(contenedor);
    
    if (datos) {
        actualizarCampos(datos, contenedor);
    }
}

function crearPrimeraFila() {
    const fila = document.createElement('div');
    fila.classList.add('form-row');
    fila.innerHTML = `
        <div>
            <label class="form-label">NIT</label>
            <input type="text" name="nit[]" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Factura Inicial</label>
            <input type="text" name="fac_desde[]" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Factura Final</label>
            <input type="text" name="fac_hasta[]" class="form-input" readonly required>
        </div>
        <div>
            <label class="form-label">ID Tercero</label>
            <input type="text" name="tercero_id[]" class="form-input" readonly required>
        </div>
        <div>
            <label class="form-label">Radicado</label>
            <input type="text" name="radicado[]" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Línea Rep</label>
            <select name="linea_rep[]" class="form-input" required>
                <option value="">Seleccione...</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
            </select>
        </div>
        <div>
            <label class="form-label">Número Pedido</label>
            <input type="text" name="num_pedido[]" class="form-input" required>
        </div>
        <div class="voucher-container hidden">
            <label class="form-label">Voucher</label>
            <input type="text" name="voucher[]" class="form-input">
        </div>
    `;
    return fila;
}

function crearSegundaFila() {
    const fila = document.createElement('div');
    fila.classList.add('form-row');
    fila.innerHTML = `
        <div>
            <label class="form-label">Línea Dis Ped</label>
            <select name="linea_dis_ped[]" class="form-input" required>
            <option value="">Seleccione...</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            </select>
        </div>
        <div>
            <label class="form-label">Plan Contable</label>
            <select name="plan_contable[]" class="form-input" required>
            <option value="">Seleccione...</option>
            <option value="AP_PROVEED">AP_PROVEED</option>
            <option value="AP_HONORARIOS">AP_HONORARIOS</option>
            <option value="AP_CUENTAS POR PAGAR">AP_CUENTAS POR PAGAR</option>
            <option value="AP_ARRENDAMIENTO">AP_ARRENDAMIENTO</option>
            </select>
        </div>
        <div>
            <label class="form-label">Pago/Causar Memo..</label>
            <select name="pago_causar_memorando[]" class="form-input" required>
            <option value="">Seleccione...</option>
            <option value="AUTORIZACION PAGO">AUTORIZACION PAGO</option>
            <option value="PAGO MEMORANDO">PAGO MEMORANDO</option>
            </select>
        </div>
        <div>
            <label class="form-label">Concepto</label>
            <input type="text" name="concepto[]" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Mes Servicio</label>
            <input type="text" name="mes_servicio[]" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Contrato</label>
            <input type="text" name="contrato[]" class="form-input" required>
        </div>
        <div>
            <label class="form-label">CRP ID</label>
            <input type="text" name="crp_id[]" class="form-input" required>
        </div>
        <input type="hidden" name="id[]" value="">

        <div class="observacion-container hidden">
            <label class="form-label">Observación</label>
            <input type="text" name="observacion[]" class="form-input">
        </div>
    `;
    return fila;
}

function buscarInformacion(contenedor) {
    const nit = contenedor.querySelector('input[name="nit[]"]').value;
    const factura = contenedor.querySelector('input[name="fac_desde[]"]').value;
    
    if (!nit || !factura) return;
    
    mostrarIndicadorCarga(contenedor);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `ajax=1&nit=${encodeURIComponent(nit)}&factura=${encodeURIComponent(factura)}`
    })
    .then(response => response.json())
    .then(data => {
        removerIndicadorCarga(contenedor);
        if (data.success) {
            actualizarCampos(data, contenedor);
        } else {
            mostrarError(contenedor, data.message || 'No se encontró información');
        }
    })
    .catch(error => {
        removerIndicadorCarga(contenedor);
        mostrarError(contenedor, 'Error al buscar la información');
        console.error('Error:', error);
    });
}

function mostrarIndicadorCarga(contenedor) {
    removerIndicadorCarga(contenedor);
    const loadingIndicator = document.createElement('div');
    loadingIndicator.textContent = 'Buscando...';
    loadingIndicator.classList.add('loading-indicator', 'text-blue-500', 'text-sm', 'mt-2');
    contenedor.appendChild(loadingIndicator);
}

function removerIndicadorCarga(contenedor) {
    const indicador = contenedor.querySelector('.loading-indicator');
    if (indicador) indicador.remove();
}

function mostrarError(contenedor, mensaje) {
    const errorMsg = document.createElement('div');
    errorMsg.textContent = mensaje;
    errorMsg.classList.add('error-message', 'text-red-500', 'text-sm', 'mt-2');
    contenedor.appendChild(errorMsg);
    setTimeout(() => errorMsg.remove(), 3000);
}

function actualizarCampos(data, contenedor) {
    const campos = [
        'fac_hasta', 'tercero_id', 'radicado', 'linea_rep', 'num_pedido',
        'linea_dis_ped', 'plan_contable', 'pago_causar_memorando', 'concepto',
        'mes_servicio', 'contrato', 'crp_id', 'id', 'voucher', 'observacion'
    ];
    
    campos.forEach(campo => {
        const input = contenedor.querySelector(`[name="${campo}[]"]`);
        if (input) {
            input.value = data[campo] || '';
            if (campo === 'concepto' && !data[campo]) {
                input.value = data['concepto2'] || '';
            }
        }
    });
    
    // Manejar visibilidad de campos especiales
    ['voucher', 'observacion'].forEach(campo => {
        const container = contenedor.querySelector(`.${campo}-container`);
        if (container) {
            container.classList.toggle('hidden', !data.radicado);
            if (data.radicado) {
                const input = container.querySelector(`[name="${campo}[]"]`);
                if (input) input.value = data[campo] || '';
            }
        }
    });
}

function guardarDatos(validado = false) {
    const formulario = document.getElementById('formularioInfTapa');
    const datos = recolectarDatos(formulario);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `data=${encodeURIComponent(JSON.stringify(datos))}&validado=${validado}`
    })
    .then(response => response.json())
    .then(result => procesarResultadoGuardado(result, validado))
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar los datos: ' + error.message);
    });
}

function recolectarDatos(formulario) {
    return Array.from(formulario.querySelectorAll('.form-container')).map(contenedor => {
        const inputs = contenedor.querySelectorAll('input[name], select[name]');
        const datos = {};
        inputs.forEach(input => {
            const name = input.name.replace('[]', '');
            datos[name] = input.value;
        });
        return datos;
    });
}

function procesarResultadoGuardado(result, validado) {
    if (result.success) {
        alert(result.message);
        window.location.href = MENU_URL;
    } else if (result.existingRecords?.length > 0 && !validado) {
        mostrarRegistrosExistentes(result.existingRecords);
        document.getElementById('mensajeExistentes').classList.remove('hidden');
        document.getElementById('registrosValidados').classList.remove('hidden');
    } else {
        alert(result.message || 'Error desconocido al guardar los datos');
    }
}

function mostrarRegistrosExistentes(registros) {
    document.querySelectorAll('.form-container').forEach(contenedor => {
        const radicado = contenedor.querySelector('input[name="radicado[]"]')?.value;
        if (registros.some(record => record.radicado === radicado)) {
            contenedor.classList.add('existing-record', 'border-red-500');
            const mensaje = document.createElement('div');
            mensaje.textContent = 'Registro Existente';
            mensaje.classList.add('text-red-500', 'text-sm', 'mt-2');
            contenedor.appendChild(mensaje);
        }
    });
}