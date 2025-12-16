document.addEventListener('DOMContentLoaded', () => {
    // Registrar corrección
    document.querySelectorAll('.corregir-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const radicado = btn.dataset.radicado;

            fetch('funciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `accion=corregir&radicado=${radicado}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') location.reload();
            })
            .catch(() => alert('❌ Error al enviar corrección.'));
        });
    });

    // Ver observaciones (mostrar en modal)
    document.querySelectorAll('.ver-observacion-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const radicado = btn.dataset.radicado;

            fetch(`funciones.php?accion=ver_observaciones&radicado=${radicado}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.observaciones.length > 0) {
                        const lista = document.getElementById('lista-observaciones');
                        lista.innerHTML = '';
                        data.observaciones.forEach(obs => {
                            const li = document.createElement('li');
                            li.textContent = obs;
                            lista.appendChild(li);
                        });

                        document.getElementById('modalObservacion').style.display = 'block';
                    } else {
                        alert('ℹ No se encontraron observaciones.');
                    }
                })
                .catch(() => alert('❌ Error al obtener observaciones.'));
        });
    });

    // Cierre del modal
    document.querySelector('.cerrar-modal').addEventListener('click', () => {
        document.getElementById('modalObservacion').style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === document.getElementById('modalObservacion')) {
            document.getElementById('modalObservacion').style.display = 'none';
        }
    });
});


document.querySelectorAll('.ver-archivos-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const ruta = this.dataset.url;
        abrirModalArchivos(ruta);
    });
});

function abrirModalArchivos(ruta) {
    const modal = document.getElementById('modalArchivos');
    const contenido = document.getElementById('contenido-archivos');
    modal.style.display = 'block';
    contenido.innerHTML = 'Cargando...';

    fetch('ver_archivos.php?ruta=' + encodeURIComponent(ruta))
        .then(response => response.text())
        .then(html => {
            contenido.innerHTML = html;
        })
        .catch(err => {
            contenido.innerHTML = '<p>Error al cargar archivos.</p>';
        });
}

function cerrarModalArchivos() {
    document.getElementById('modalArchivos').style.display = 'none';
}

function abrirPopup(url) {
    window.open(url, '_blank', 'width=800,height=600,resizable=yes,scrollbars=yes');
}


document.querySelectorAll('.subir-archivo-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const ruta = btn.getAttribute('data-url');
        document.getElementById('ruta_destino').value = ruta;
        document.getElementById('modalSubida').style.display = 'block';
    });
});

function cerrarModalSubida() {
    document.getElementById('modalSubida').style.display = 'none';
}

document.getElementById('formSubida').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('subir_archivo.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(res => {
        document.getElementById('mensajeSubida').innerText = res;
        this.reset();
    })
    .catch(err => {
        document.getElementById('mensajeSubida').innerText = "Error al subir archivo.";
        console.error(err);
    });
});
