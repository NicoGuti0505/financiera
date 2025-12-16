// Manejo de actualización individual
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
    const proceso = form.querySelector('.proceso-select').value;
    const observacion = form.querySelector('.observacion-field').value;
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

// Actualización masiva
document.getElementById('update-massive')?.addEventListener('click', () => {
    document.querySelectorAll('.proceso-form').forEach(form => {
        enviarActualizacion(form);
    });
});

// Mostrar mensajes flotantes
function mostrarMensaje(tipo, mensaje) {
    let mensajeDiv = document.getElementById("mensajeActualizacion");
    mensajeDiv.classList.remove("error", "success");

    mensajeDiv.classList.add(tipo === "success" ? "success" : "error");
    mensajeDiv.innerHTML = mensaje;
    mensajeDiv.style.display = "block";

    setTimeout(() => {
        mensajeDiv.style.display = "none";
    }, 4000);
}

// Abrir modal con observaciones
document.querySelectorAll('.ver-observacion-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const radicado = btn.dataset.radicado;

        fetch('funciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=ver_observacion&radicado=${radicado}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const lista = document.getElementById('lista-observaciones');
                lista.innerHTML = '';

                data.observaciones.forEach(obs => {
                    const li = document.createElement('li');
                    li.textContent = obs;
                    lista.appendChild(li);
                });

                document.getElementById('modalObservacion').style.display = 'block';
            } else {
                mostrarMensaje("error", "❌ No se encontraron observaciones.");
            }
        })
        .catch(() => mostrarMensaje("error", "❌ Error al obtener observaciones."));
    });
});

// Cerrar el modal al hacer clic en la X
document.querySelector('.cerrar-modal').addEventListener('click', () => {
    document.getElementById('modalObservacion').style.display = 'none';
});

// Cerrar modal si se hace clic fuera del contenido
window.addEventListener('click', (e) => {
    const modal = document.getElementById('modalObservacion');
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});
