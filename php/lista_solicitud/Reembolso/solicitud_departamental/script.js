document.querySelectorAll('.proceso-form').forEach(form => {
    const select = form.querySelector('.proceso-select');
    const observacion = form.querySelector('.observacion-field');
    const button = form.querySelector('.update-individual');

    // Muestra u oculta el campo de observación según la opción seleccionada
    select.addEventListener('change', () => {
        if (select.value === 'Rechazado' || select.value === 'Subsanacion') {
            observacion.style.display = 'block';
        } else {
            observacion.style.display = 'none';
            observacion.value = '';
        }
    });

    button.addEventListener('click', () => {
        enviarActualizacion(form);
    });
});

function enviarActualizacion(form) {
    const proceso = form.querySelector('.proceso-select')?.value;
    const observacionField = form.querySelector('.observacion-field');
    const observacion = observacionField ? observacionField.value : '';
    const identificacion = form.dataset.identificacion;

    fetch('ajax_actualizar_proceso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `numero_identificacion=${identificacion}&proceso=${proceso}&observacion=${encodeURIComponent(observacion)}`
    })
    .then(res => res.json())
    .then(response => {
        mostrarMensaje(response.status, response.message);

        if (response.status === 'success') {
            const fila = form.closest('tr');
            const celdaProceso = fila.querySelectorAll('td')[2];
            celdaProceso.textContent = proceso;
        }
    })
    .catch(() => mostrarMensaje("error", "❌ Error en la actualización"));
}


// Función para actualizar masivamente
document.getElementById('update-massive').addEventListener('click', () => {
    document.querySelectorAll('.proceso-form').forEach(form => {
        enviarActualizacion(form);
    });
});

// Función para mostrar mensajes flotantes
function mostrarMensaje(tipo, mensaje) {
    let mensajeDiv = document.getElementById("mensajeActualizacion");
    mensajeDiv.classList.remove("error", "success");

    if (tipo === "success") {
        mensajeDiv.classList.add("success");
    } else {
        mensajeDiv.classList.add("error");
    }

    mensajeDiv.innerHTML = mensaje;
    mensajeDiv.style.display = "block";

    setTimeout(() => {
        mensajeDiv.style.display = "none";
    }, 4000);
}
