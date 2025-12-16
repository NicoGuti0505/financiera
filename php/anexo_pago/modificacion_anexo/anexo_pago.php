<?php

//Ruta
$Ruta = dirname(__FILE__);
// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', $Ruta);
// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

// Encontrar el texto '\php\' y dividir la ruta
$partes = explode('\\php\\', $Ruta);
// Cambiar las barras invertidas por barras normales
$RutaBase = str_replace('\\', '/', $partes[1]) . '/';

require_once 'function.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: " . url('inicio_sesion.php'));
    exit();
}

// Manejar la solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'ajax_handler.php';
    exit;
}
if (!isset($_SESSION['tipo_usuario_id']) || 
    ($_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 3 && $_SESSION['tipo_usuario_id'] != 4)) {
    header('Location: ../../menu.php');
    exit;
}
?>
<script>
        let inactivityTime =30* 60 * 1000; // 30 minutos en milisegundos
    let inactivityTimer;

    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            window.location.href = "../../logout.php"; // Redirige al menú
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
    <title>ANEXO DE PAGO</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="<?php echo url($RutaBase. 'estilos/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="container">

        <h1 class="text-2xl font-bold mb-6 text-center">MODIFICAR ANEXO</h1>
        <form id="formularioFacturas" class="space-y-4">
            <div id="camposFacturas"></div>
            <div id="mensajeExistentes" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                <p>Se encontraron registros existentes !ESTOS NO SE EXPORTARAN!. Por favor, verifique la información.</p>
            </div>
            <div class="flex space-x-4">
                <button type="button" id="agregarCampos" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Agregar</button>
                <button type="button" id="guardarDatos" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Guardar</button>
                <button type="button" id="registrosValidados" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 hidden">Registros Validados</button>
                <a href="<?php echo url('menu.php'); ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block">Atrás</a>
            </div>
        </form>
    </div>
    
    <script>
    function handleFocusOut(e) {
        if (e.target.name === 'iva[]') {
            e.target.value = formatearPorcentaje(parsearPorcentaje(e.target.value));
        } else if (e.target.name === 'base_iva[]' || e.target.name === 'base_excenta[]' || e.target.name === 'valor_total[]') {
            e.target.value = formatearMoneda(parsearMoneda(e.target.value));
        }
    }
    
    function handleFocusIn(e) {
        if (e.target.name === 'iva[]') {
            e.target.value = parsearPorcentaje(e.target.value);
        } else if (e.target.name === 'base_iva[]' || e.target.name === 'base_excenta[]' || e.target.name === 'valor_total[]') {
            e.target.value = parsearMoneda(e.target.value);
        }
    }

    function agregarCampos() {
        const camposFacturas = document.getElementById('camposFacturas');
        const nuevosCampos = document.createElement('div');
        nuevosCampos.classList.add('flex', 'space-x-1', 'mb-2');
        nuevosCampos.innerHTML = `
            <input type="text" name="nit[]" placeholder="NIT" class="border p-1 w-1/4" required>
            <input type="text" name="inicio_factura[]" placeholder="Factura Inicial" class="border p-1 w-1/4" required>
            <input type="text" name="final_factura[]" placeholder="Factura Final" class="border p-1 w-1/4 readonly-field" required readonly>
            <input type="text" name="id_tercero[]" placeholder="ID Tercero" class="border p-1 w-1/4 readonly-field" required readonly>
            <input type="text" name="valor_total[]" placeholder="Valor Total" class="border p-1 w-2/5" required>
            <input type="text" name="ubicacion[]" placeholder="Ubicación" class="border p-1 w-1/6" required>
            <input type="text" name="articulo[]" placeholder="Artículo" class="border p-1 w-1/6" required>
            <input type="text" name="iva[]" placeholder="IVA" class="border p-1 w-1/6" required>
            <input type="text" name="base_iva[]" placeholder="Base Iva" class="border p-1 w-2/5" required>
            <input type="text" name="base_excenta[]" placeholder="Base Excenta" class="border p-1 w-2/5" required>
            <input type="hidden" name="beneficiario[]" value="">
            <input type="hidden" name="id_anexo[]" value="">
            <input type="hidden" name="val_ter_id[]" value="">
        `;
        camposFacturas.appendChild(nuevosCampos);
        
        const inputNit = nuevosCampos.querySelector('input[name="nit[]"]');
        const inputFactura = nuevosCampos.querySelector('input[name="inicio_factura[]"]');
        
        inputNit.addEventListener('blur', () => buscarInformacion(nuevosCampos));
        inputFactura.addEventListener('blur', () => buscarInformacion(nuevosCampos));
        
        ['iva[]', 'base_iva[]', 'base_excenta[]', 'valor_total[]'].forEach(name => {
            const input = nuevosCampos.querySelector(`input[name="${name}"]`);
            input.addEventListener('focusout', handleFocusOut);
            input.addEventListener('focusin', handleFocusIn);
        });
    }

    function buscarInformacion(campoSet) {
        const nit = campoSet.querySelector('input[name="nit[]"]').value;
        const factura = campoSet.querySelector('input[name="inicio_factura[]"]').value;
        
        if (nit && factura) {
            const loadingIndicator = document.createElement('div');
            loadingIndicator.textContent = 'Buscando...';
            loadingIndicator.classList.add('text-blue-500', 'text-sm', 'mt-1');
            campoSet.appendChild(loadingIndicator);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=1&nit=${encodeURIComponent(nit)}&factura=${encodeURIComponent(factura)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                loadingIndicator.remove();
                if (data.success) {
                    const safeSetValue = (selector, value, formatter = (v) => v) => {
                        const element = campoSet.querySelector(selector);
                        if (element && value !== null && value !== undefined) {
                            element.value = formatter(value);
                        }
                    };
                    safeSetValue('input[name="iva[]"]', data.iva, (v) => formatearPorcentaje(parsearPorcentaje(v)));
                safeSetValue('input[name="base_iva[]"]', data.base_iva, (v) => formatearMoneda(parsearMoneda(v)));
                safeSetValue('input[name="base_excenta[]"]', data.base_excenta, (v) => formatearMoneda(parsearMoneda(v)));
                safeSetValue('input[name="valor_total[]"]', data.valor_total, (v) => formatearMoneda(parsearMoneda(v)));
                safeSetValue('input[name="id_tercero[]"]', data.id_tercero);
                safeSetValue('input[name="final_factura[]"]', data.final_factura);
                safeSetValue('input[name="ubicacion[]"]', data.ubicacion);
                safeSetValue('input[name="articulo[]"]', data.articulo);
                safeSetValue('input[name="beneficiario[]"]', data.beneficiario);
                safeSetValue('input[name="id_anexo[]"]', data.id_anexo);
                safeSetValue('input[name="val_ter_id[]"]', data.val_ter_id);
                const successMsg = document.createElement('div');
                    successMsg.textContent = `Información cargada`;
                    successMsg.classList.add('text-green-500', 'text-sm', 'mt-1');
                    campoSet.appendChild(successMsg);
                    setTimeout(() => successMsg.remove(), 10000);
                } else {
                    throw new Error(data.message || 'No se encontró información');
                }
            })
            .catch(error => {
                loadingIndicator.remove();
                console.error('Error detallado:', error);
                const errorMsg = document.createElement('div');
                errorMsg.textContent = `Error: ${error.message}. Por favor, intente nuevamente.`;
                errorMsg.classList.add('text-red-500', 'text-sm', 'mt-1');
                campoSet.appendChild(errorMsg);
                setTimeout(() => errorMsg.remove(), 5000);
            });
        }
    }

    function guardarDatos(validado = false) {
    const formulario = document.getElementById('formularioFacturas');
    const datos = Array.from(formulario.querySelectorAll('#camposFacturas > div')).map(conjunto => ({
        id_tercero: conjunto.querySelector('input[name="id_tercero[]"]').value,
        identificacion: conjunto.querySelector('input[name="nit[]"]').value,
        inicio_factura: conjunto.querySelector('input[name="inicio_factura[]"]').value,
        final_factura: conjunto.querySelector('input[name="final_factura[]"]').value,
        valor_total: parsearMoneda(conjunto.querySelector('input[name="valor_total[]"]').value),
        articulo: conjunto.querySelector('input[name="articulo[]"]').value,
        ubicacion: conjunto.querySelector('input[name="ubicacion[]"]').value,
        iva: parsearPorcentaje(conjunto.querySelector('input[name="iva[]"]').value),
        base_iva: parsearMoneda(conjunto.querySelector('input[name="base_iva[]"]').value),
        base_excenta: parsearMoneda(conjunto.querySelector('input[name="base_excenta[]"]').value),
        beneficiario: conjunto.querySelector('input[name="beneficiario[]"]').value,
        id_anexo: conjunto.querySelector('input[name="id_anexo[]"]').value,
        val_ter_id: conjunto.querySelector('input[name="val_ter_id[]"]').value,
    }));
    console.log('Datos a enviar:', JSON.stringify(datos));
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
        // Intentar leer el texto primero
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
            if (result.allRecords) {
              
            }
            setTimeout(() => {
                window.location.href = '<?php echo url("menu.php"); ?>';
            }, 2000);
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

    function mostrarRegistrosExistentes(existingRecords) {
        const conjuntos = document.querySelectorAll('#camposFacturas > div');
        conjuntos.forEach(conjunto => {
            const nit = conjunto.querySelector('input[name="nit[]"]').value;
            const factura = conjunto.querySelector('input[name="inicio_factura[]"]').value;
            if (existingRecords.some(record => record.identificacion === nit && record.inicio_factura === factura)) {
                conjunto.classList.add('existing-record');
                const mensajeExistente = document.createElement('div');
                mensajeExistente.textContent = 'Registro Existente';
                mensajeExistente.classList.add('text-red-500', 'text-sm', 'mt-1');
                conjunto.appendChild(mensajeExistente);
            }
        });
    }

    document.getElementById('agregarCampos').addEventListener('click', agregarCampos);
    document.getElementById('guardarDatos').addEventListener('click', () => guardarDatos(false));
    document.getElementById('registrosValidados').addEventListener('click', () => guardarDatos(true));

    // Agregar el primer conjunto de campos al cargar la página
    agregarCampos();
    </script>
    <script src="<?= url('script/formato_texto.js') ?>"></script>
</body>
</html>