
// 🔧 Endpoint dinámico
const AJAX_ENDPOINT = new URL('ajax_actualizar_proceso.php', window.location.href).toString();

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.proceso-form').forEach(form => {
    const row         = form.closest('tr');
    const select      = form.querySelector('.proceso-select');
    const observacion = form.querySelector('.observacion-field');
    const motivoTd    = row.querySelector('.motivo-td');   // Motivos Rechazo
    const motivoTd2   = row.querySelector('.motivo-td2');  // Motivos Subsanación
    const btnUpdate   = form.querySelector('.update-individual');

    const toggleCampos = () => {
      const v = select.value;
      const showObs     = (v === 'Aprobado' || v === 'Rechazado' || v === 'Subsanacion'); // si no quieres obs en Subsanación, quita "|| v === 'Subsanacion'"
      const showMotivo  = (v === 'Rechazado');
      const showMotivo2 = (v === 'Subsanacion');

      if (observacion) {
        observacion.style.display = showObs ? 'block' : 'none';
        if (!showObs) observacion.value = '';
      }

      if (motivoTd) {
        motivoTd.style.display = showMotivo ? 'table-cell' : 'none';
        if (!showMotivo) {
          motivoTd.querySelectorAll('input[name="motivo[]"]').forEach(chk => { chk.checked = false; });
        }
      }

      if (motivoTd2) {
        motivoTd2.style.display = showMotivo2 ? 'table-cell' : 'none';
        if (!showMotivo2) {
          motivoTd2.querySelectorAll('input[name="motivo2[]"]').forEach(chk => { chk.checked = false; });
        }
      }

      btnUpdate.disabled = (v === 'Revision');
      refreshMotivoHeader();
    };

    // Eventos
    select.addEventListener('change', toggleCampos);
    toggleCampos(); // Estado inicial

    // Guardado individual
    btnUpdate.addEventListener('click', (e) => {
      e.preventDefault();
      const v = select.value;

      if (v === 'Revision') {
        mostrarMensaje('ℹ Selecciona Aprobado / Rechazado / Subsanación para actualizar.', false);
        return;
      }

      const payload = buildPayloadFromRow(form, row);

      if ((v === 'Aprobado' || v === 'Rechazado' || v === 'Subsanacion') && !payload.observacion) {
        mostrarMensaje('✖ La observación es obligatoria.', false);
        return;
      }
      if (v === 'Rechazado' && !payload.motivo) {
        mostrarMensaje('✖ Debes seleccionar al menos un motivo para Rechazado.', false);
        return;
      }
      // Si quieres motivo obligatorio en Subsanación, descomenta:
      // if (v === 'Subsanacion' && !payload.motivo) {
      //   mostrarMensaje('✖ Debes seleccionar al menos un motivo de Subsanación.', false);
      //   return;
      // }

      enviarActualizacion(payload, row);
    });
  });

  // Guardado masivo
  document.getElementById('update-massive')?.addEventListener('click', () => {
    document.querySelectorAll('.proceso-form').forEach(form => {
      const row    = form.closest('tr');
      const select = form.querySelector('.proceso-select');
      const v      = select.value;

      if (v === 'Revision') {
        mostrarMensaje('ℹ Hay filas en Revisión: cámbialas para guardar.', false);
        return;
      }

      const payload = buildPayloadFromRow(form, row);

      if ((v === 'Aprobado' || v === 'Rechazado' || v === 'Subsanacion') && !payload.observacion) {
        mostrarMensaje(`✖ ${payload.radicado_via}: falta observación.`, false);
        return;
      }
      if (v === 'Rechazado' && !payload.motivo) {
        mostrarMensaje(`✖ ${payload.radicado_via}: selecciona al menos un motivo.`, false);
        return;
      }
      // Motivo obligatorio en Subsanación (opcional)
      // if (v === 'Subsanacion' && !payload.motivo) {
      //   mostrarMensaje(`✖ ${payload.radicado_via}: selecciona al menos un motivo de Subsanación.`, false);
      //   return;
      // }

      enviarActualizacion(payload, row);
    });
  });
});

// Construye payload desde la fila
function buildPayloadFromRow(form, row) {
  const radicado_via = form.getAttribute('data-identificacion') || '';
  const estado       = form.querySelector('.proceso-select')?.value || '';
  const observacion  = (form.querySelector('.observacion-field')?.value || '').trim();

  const motivoTd  = row.querySelector('.motivo-td');
  const motivoTd2 = row.querySelector('.motivo-td2');

  const motivos1 = motivoTd
    ? Array.from(motivoTd.querySelectorAll('input[name="motivo[]"]:checked')).map(i => i.value)
    : [];

  const motivos2 = motivoTd2
    ? Array.from(motivoTd2.querySelectorAll('input[name="motivo2[]"]:checked')).map(i => i.value)
    : [];

  return {
    radicado_via,
    estado,
    observacion,
    motivo: [...motivos1, ...motivos2].join(', ')
  };
}

// Muestra/oculta encabezado de motivos
function refreshMotivoHeader() {
  const th = document.getElementById('th-motivo');
  if (!th) return;

  const anyShown = Array.from(document.querySelectorAll('.proceso-select'))
    .some(sel => sel.value === 'Rechazado' || sel.value === 'Subsanacion');

  th.style.display = anyShown ? 'table-cell' : 'none';

  if (!anyShown) {
    document.querySelectorAll('td.motivo-td, td.motivo-td2').forEach(td => td.style.display = 'none');
  }
}

// POST al backend
function enviarActualizacion(payload, row) {
  const btn = row.querySelector('.update-individual');
  const sel = row.querySelector('.proceso-select');

  btn.disabled = true;
  const oldText = btn.textContent;
  btn.textContent = 'Guardando...';

  fetch(AJAX_ENDPOINT, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  })
  .then(async r => {
    const text = await r.text().catch(() => '');
    let resp;
    try { resp = JSON.parse(text || '{}'); }
    catch { resp = { status: 'error', message: `Respuesta no válida (${r.status})`, raw: text }; }
    resp._httpStatus = r.status;
    return resp;
  })
  .then(resp => {
    const ok  = (typeof resp.ok === 'boolean') ? resp.ok : (resp.status === 'success');
    const msg = resp.message || resp.msg || (ok ? 'Actualizado' : `Error HTTP ${resp._httpStatus || ''}`);

    mostrarMensaje(ok ? `✔ ${msg}` : `✖ ${msg}`, ok);

    if (ok) {
      const celdaProceso = row.querySelector('.celda-proceso');
      const visible = resp.proceso_visible || payload.estado || '';
      if (celdaProceso) celdaProceso.textContent = visible;
      marcarFilaGuardada(row);
      btn.textContent = '✓ Guardado';
      setTimeout(() => { btn.textContent = oldText; }, 1200);
    } else {
      btn.textContent = oldText;
    }

    refreshMotivoHeader();
  })
  .catch(err => {
    console.error('Error de red/fetch:', err);
    mostrarMensaje('✖ Error de red', false);
    btn.textContent = oldText;
  })
  .finally(() => {
    btn.disabled = (sel?.value === 'Revision');
  });
}

// Efecto visual de guardado
function marcarFilaGuardada(row) {
  row.classList.add('row-saved');
  setTimeout(() => row.classList.remove('row-saved'), 900);
}

// Toast simple
function mostrarMensaje(texto, ok) {
  const div = document.getElementById('mensajeActualizacion');
  if (!div) { alert(texto); return; }
  div.textContent = texto;
  div.style.background = ok ? '#18a558' : '#d64545';
  div.style.display = 'block';
  setTimeout(() => { div.style.display = 'none'; }, 3000);
}

