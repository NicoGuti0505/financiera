function handleFocusEvent(e, type) {
    const formatters = {
        'iva[]': { in: parsearPorcentaje, out: formatearPorcentaje },
        'base_iva[]': { in: parsearMoneda, out: formatearMoneda },
        'base_excenta[]': { in: parsearMoneda, out: formatearMoneda },
        'valor_total[]': { in: parsearMoneda, out: formatearMoneda },
        'temp_iva[]': { in: parsearMoneda, out: formatearMoneda }
    };

    const field = e.target.name;
    const container = e.target.closest('div');

    const articuloInput = container.querySelector('input[name="articulo[]"]');
    if (!articuloInput.value) {
        articuloInput.value = '204';
    }

    if (formatters[field]) {
        e.target.value = type === 'in' ? 
            formatters[field].in(e.target.value) : 
            formatters[field].out(formatters[field].in(e.target.value));
            
        
        // Solo establecer 19% si el campo IVA está vacío
        const ivaInput = container.querySelector('input[name="iva[]"]');
        if (!ivaInput.value.trim()) {
            ivaInput.value = formatearPorcentaje(19);
        }
        
        //Tomamos el valor_total
        valorTotalInput = container.querySelector('input[name="valor_total[]"]');
        valorTotalValue = parsearMoneda(valorTotalInput.value || '0');

        baseExcentaInput = container.querySelector('input[name="base_excenta[]"]');
        baseIvaInput = container.querySelector('input[name="base_iva[]"]');

        baseIvaValue = parsearMoneda(baseIvaInput.value || '0');
        baseExcentaValue = parsearMoneda(baseExcentaInput.value || '0');

        // Si es base_iva calculamos la base_excenta
        if (field === 'base_iva[]' && type === 'out') {
            baseExcentaValue = valorTotalValue - baseIvaValue;
            baseExcentaInput.value = formatearMoneda(baseExcentaValue);
        }

        // Si es base_excenta calculamos la base_IVA
        if (field === 'base_excenta[]' && type === 'out') {
            baseIvaValue = valorTotalValue - baseExcentaValue;
            baseIvaInput.value = formatearMoneda(baseIvaValue);
        }
        
        // Calcular el VALOR IVA
        tempIvaInput = container.querySelector('input[name="temp_iva[]"]');  
        ivaAmount = baseIvaValue * (parsearPorcentaje(ivaInput.value) / 100);
        tempIvaInput.value = formatearMoneda(ivaAmount);
    }
}

function agregarCampos() {
    const camposHTML = `
        <div class="flex space-x-1 mb-2">
            <input type="text" name="nit[]" placeholder="NIT" class="border p-1 w-1/4" required readonly>
            <input type="text" name="inicio_factura[]" placeholder="Factura Inicial" class="border p-1 w-1/4" required readonly>
            <input type="text" name="final_factura[]" placeholder="Factura Final" class="border p-1 w-1/4 readonly-field" required readonly>
            <input type="text" name="id_tercero[]" placeholder="ID Tercero" class="border p-1 w-1/4 readonly-field" required readonly>
            <input type="text" name="valor_total[]" placeholder="Valor Total" class="border p-1 w-2/5" required readonly>
            <input type="text" name="ubicacion[]" placeholder="Ubicación" class="border p-1 w-1/6" required readonly>
            <input type="text" name="articulo[]" placeholder="Artículo" class="border p-1 w-1/6" required>
            <input type="text" name="base_iva[]" placeholder="Base Iva" class="border p-1 w-2/5" required>
            <input type="text" name="iva[]" placeholder="IVA" class="border p-2 w-1/6" required>
            <input type="text" name="temp_iva[]" placeholder="VALOR IVA" class="border p-1 w-1/6" required readonly>
            <input type="text" name="base_excenta[]" placeholder="Base Excenta" class="border p-1 w-2/5" required>
            <input type="hidden" name="validacion_terceros_id[]" value="">
            <button type="button" class="eliminar-fila bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 ml-2">X</button>
        </div>
    `;

    const contenedor = document.createElement('div');
    contenedor.innerHTML = camposHTML;

    // Añadir el evento click al botón eliminar después de que el HTML se haya insertado en el DOM
    const fila = contenedor.firstElementChild;
    fila.querySelector('.eliminar-fila').addEventListener('click', () => fila.remove());

    // Eventos para formato y cálculos
    ['articulo[]','iva[]', 'base_iva[]', 'base_excenta[]', 'valor_total[]', 'temp_iva[]'].forEach(name => {
        const input = fila.querySelector(`input[name="${name}"]`);
        input.addEventListener('focusin', e => handleFocusEvent(e, 'in'));
        input.addEventListener('focusout', e => handleFocusEvent(e, 'out'));
    });

    return fila;
}

document.getElementById('btnBuscar').addEventListener('click', async () => {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const regionf_id = document.getElementById('regionf_id').value; // Capturar región seleccionada

    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);
        formData.append('regionf_id', regionf_id); 
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        console.log('Respuesta del servidor:', data);

        if (data.success) {
            const camposFacturas = document.getElementById('camposFacturas');
            camposFacturas.innerHTML = '';

            if (data.records && data.records.length > 0) {
                data.records.forEach(record => {
                    const nuevoCampo = agregarCampos();
                    for (const [key, value] of Object.entries(record)) {
                        const input = nuevoCampo.querySelector(`input[name="${key}[]"]`);
                        if (input) {
                            if (['valor_total', 'base_iva', 'base_excenta'].includes(key) && value !== null) {
                                input.value = formatearMoneda(value);
                            } else {
                                input.value = value ?? '';
                            }
                        }
                    }
                    camposFacturas.appendChild(nuevoCampo);
                });
            } else {
                alert(data.message || 'No se encontraron registros');
            }
        } else {
            throw new Error(data.message || 'Error al cargar los datos');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al cargar los datos: ' + error.message);
    }
});


document.getElementById('guardarDatos').addEventListener('click', () => {
    guardarDatos(false);
});

document.getElementById('registrosValidados').addEventListener('click', () => {
    guardarDatos(true);
});

function guardarDatos(validado = false) {
    const formulario = document.getElementById('formularioFacturas');
    const todosLosConjuntos = Array.from(formulario.querySelectorAll('#camposFacturas > div'));
    
    const conjuntosConArticulo = todosLosConjuntos.filter(conjunto => 
        conjunto.querySelector('input[name="articulo[]"]').value.trim() !== ''
    );
    
    const conjuntosSinArticulo = todosLosConjuntos.length - conjuntosConArticulo.length;
    
    if (conjuntosSinArticulo > 0) {
        if (!confirm(`Hay ${conjuntosSinArticulo} registros sin artículo. Solo se guardarán los registros que tengan el campo artículo diligenciado. ¿Desea continuar?`)) {
            return;
        }
    }

    const datos = conjuntosConArticulo.map(conjunto => {
        const baseIva = conjunto.querySelector('input[name="base_iva[]"]').value;
        
        // Si base_iva está vacío, null o es una cadena vacía
        if (!baseIva || baseIva.trim() === '') {
            return {
                id_tercero: conjunto.querySelector('input[name="id_tercero[]"]').value,
                articulo: conjunto.querySelector('input[name="articulo[]"]').value,
                iva: null,
                base_iva: null,
                base_excenta: null,
                val_ter_id: conjunto.querySelector('input[name="validacion_terceros_id[]"]').value,
            };
        }
        
        // Caso normal cuando base_iva tiene un valor
        return {
            id_tercero: conjunto.querySelector('input[name="id_tercero[]"]').value,
            articulo: conjunto.querySelector('input[name="articulo[]"]').value,
            iva: parsearPorcentaje(conjunto.querySelector('input[name="iva[]"]').value),
            base_iva: parsearMoneda(baseIva),
            base_excenta: parsearMoneda(conjunto.querySelector('input[name="base_excenta[]"]').value),
            val_ter_id: conjunto.querySelector('input[name="validacion_terceros_id[]"]').value,
        };
    });

    fetch(window.location.href, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `ver=1&data=${encodeURIComponent(JSON.stringify(datos))}&validado=${validado}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Texto de respuesta:', text);
                throw new Error('Error al parsear JSON: ' + text);
            }
        });
    })
    .then(result => {
        if (result.success) {
            alert(result.message);
            window.location.href = menuUrl;
        } else {
            if (result.existingRecords && result.existingRecords.length > 0 && !validado) {
                mostrarRegistrosExistentes(result.existingRecords);
                document.getElementById('mensajeExistentes').classList.remove('hidden');
                document.getElementById('registrosValidados').classList.remove('hidden');
            } else {
                throw new Error(result.message || 'Error desconocido al guardar los datos');
            }
        }
    })
    .catch(error => {
        console.error('Error completo:', error);
        alert('Error al guardar los datos: ' + error.message);
    });
}

