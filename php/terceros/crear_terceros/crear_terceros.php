<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Obtiene el directorio del archivo actual y divide la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_autenticado'])) {
    header('Location:' . str_repeat('../', $levelsUp) . 'inicio_sesion.php');
    exit();
}

// === Funciones auxiliares ===
function obtenerOpciones($conn, $tabla, $descripcion = 'descripcion') {
    $sql = "SELECT t.id AS id, t.$descripcion FROM $tabla AS t ORDER BY t.$descripcion ASC";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) die(print_r(sqlsrv_errors(), true));

    $opciones = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Filtrar solo para la tabla tipo_documento
        if ($tabla === 'tipo_documento') {
            if (!in_array($row['id'], ['CC', 'NIT'])) {
                continue; // Si no es CC o NIT, lo ignora
            }
        }

        if ($tabla === 'banco') {
            $opciones[$row['id']] = $row['id'] . ' - ' . $row[$descripcion];
        } else {
            $opciones[$row['id']] = $row[$descripcion];
        }
    }
    return $opciones;
}

// === Inicialización de variables para selects ===
$message = '';
$bancos = obtenerOpciones($conn, 'banco');
$tipos_cuenta = obtenerOpciones($conn, 'tipo_cuenta');
$municipios = obtenerOpciones($conn, 'municipio', 'descripcion_mun');
$region = obtenerOpciones($conn, 'region_fomag');
$tipos_contribuyente = obtenerOpciones($conn, 'tipo_contribuyente');
$tipos_documento = obtenerOpciones($conn, 'tipo_documento', 'id');
$ciiu = obtenerOpciones($conn, 'ciiu', 'id');
$retencion = [
    'Y' => 'Sí',
    'N' => 'No'
];

// === Procesar datos JSON provenientes de abrirFormulario(data) ===
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);
if (!is_array($data)) { $data = []; }

$row = $data['row'] ?? [];
$tipoDcto = $row['tipo_documento_id'] ?? '';
$identificacion = $row['identificacion'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registro de Tercero</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 min-h-screen py-2 px-2">
  <div class="max-w-7xl mx-auto bg-white p-4 rounded-lg shadow-md">
    <h2 class="text-3xl font-bold mb-2 text-center text-blue-600">Formulario de Registro para Tercero</h2>

    <?php if (!empty($message)): ?>
      <div class="mb-6 p-2 <?php echo (strpos($message, 'correctamente') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'); ?> rounded">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form id="terceroForm" class="space-y-6">
      <!-- Cabecera: tipo documento + identificación -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="tipo_documento_id" class="block text-sm font-medium text-gray-700 mb-1">
            Tipo de Documento: <span class="text-red-500">*</span>
          </label>
          <select id="tipo_documento_id" name="tipo_documento_id" required
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            <option value="">Seleccione un tipo de documento</option>
            <?php foreach ($tipos_documento as $id => $tipo): ?>
              <option value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
                      <?php echo ($tipoDcto == $id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Columna: Identificación + Botón Abrir carpeta -->
        <div>
        <label for="identificacion" class="block text-sm font-medium text-gray-700 mb-1">
            Identificación: <span class="text-red-500">*</span>
        </label>
        <div class="flex gap-2">
            <input
            type="text"
            id="identificacion"
            name="identificacion"
            value="<?php echo htmlspecialchars($identificacion ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            required
            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
            />
            <button
            type="button"
            id="btnAbrirCarpeta"
            class="whitespace-nowrap bg-gray-700 text-white px-3 py-2 rounded hover:bg-gray-800"
            title="Ver documentos del NIT y subir nuevos"
            >
            Abrir carpeta
            </button>
        </div>
        </div>

      </div>

      <!-- Layout: formulario (izquierda) + visor (derecha) -->
      <div class="mt-3 grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
        <!-- IZQUIERDA -->
        <aside id="leftColumn" class="lg:col-span-4 flex flex-col gap-3">
          <!-- Formulario en lista (una sola columna) -->
          <div id="formContainer" class="grid grid-cols-1 gap-3"></div>

          <!-- Panel opcional con datos del JSON (oculto) -->
          <div id="jsonPanel" class="hidden bg-slate-700 text-white rounded p-4 overflow-auto"></div>
        </aside>

        <!-- DERECHA: Visor con botones (sin alturas fijas) -->
        <section class="lg:col-span-8 flex">
          <div id="viewerBox" class="rounded border w-full flex flex-col">
            <div class="flex items-center justify-between gap-2 p-2 border-b">
              <div class="flex items-center gap-2">
                <button type="button" id="btnRut"
                        class="bg-indigo-600 text-white px-3 py-2 rounded hover:bg-indigo-700">
                  Consultar RUT
                </button>
                <button type="button" id="btnCert"
                        class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">
                  Consultar Cert. Bancaria
                </button>
              </div>
              <span id="viewerTitle" class="text-sm font-medium text-gray-700"></span>
            </div>
            <iframe id="viewerFrame" class="flex-1 w-full" src="" loading="lazy"></iframe>
          </div>
        </section>
      </div>

      <div class="flex justify-end mt-6 space-x-3">
        <button type="submit"
                class="bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
          Guardar
        </button>
        <button type="button" id="cancelar"
                class="bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
          Cancelar
        </button>
      </div>
    </form>
  </div>
<!-- Modal: Archivos de la carpeta del NIT -->
<div id="modalCarpeta" class="fixed inset-0 hidden z-50 bg-black/40 items-center justify-center p-4">
  <div class="bg-white w-full max-w-5xl h-[85vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col">
    <!-- Header -->
    <div class="px-5 py-3 border-b bg-gradient-to-r from-slate-50 to-white">
      <div class="flex items-center justify-between">
        <div class="min-w-0">
          <h3 id="mcTitle" class="font-semibold text-slate-800 truncate">Documentos</h3>
          <div class="mt-0.5 text-xs text-slate-500 truncate">
            Carpeta: <span id="mcFolder" class="font-medium"></span>
          </div>
        </div>
        <button id="mcCerrar" type="button"
                class="inline-flex items-center gap-1.5 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-md">
          <!-- X icon -->
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Cerrar
        </button>
      </div>
    </div>

        <!-- Upload -->
        <div class="px-5 py-3 border-b bg-white">
        <form id="mcUploadForm" class="flex flex-wrap items-center gap-3">
            <input id="mcFile" name="archivo" type="file"
                class="block text-sm" />
            <button id="mcUploadBtn" type="submit"
                    class="inline-flex items-center gap-1.5 bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-700">
            Subir
            </button>
            <span id="mcUploadHint" class="text-sm text-gray-500 hidden">Subiendo…</span>
        </form>
        </div>

    <!-- Listado -->
    <div class="flex-1 overflow-auto bg-slate-50">
      <table class="w-full text-sm">
        <thead class="sticky top-0 bg-white/90 backdrop-blur border-b">
          <tr class="text-slate-600">
            <th class="text-left px-4 py-2 font-medium">Nombre</th>
            <th class="text-right px-4 py-2 font-medium whitespace-nowrap">Tamaño</th>
            <th class="text-left px-4 py-2 font-medium whitespace-nowrap">Fecha</th>
            <th class="text-right px-4 py-2 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody id="mcFilesBody" class="divide-y divide-slate-100 bg-white"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
  // Endpoints
  const urlf            = '<?= url('terceros/crear_terceros/guardar_tercero.php') ?>';
  const URL_BUSCAR_DOCS = '<?= url('terceros/crear_terceros/buscar_docs.php') ?>';
  const URL_VER_DOC     = '<?= url('terceros/crear_terceros/ver_doc.php') ?>';
  const URL_SUBIR_DOC   = '<?= url('terceros/crear_terceros/subir_doc.php') ?>';
  const URL_DESCARGAR_DOC = '<?= url('terceros/crear_terceros/descargar_doc.php') ?>';

  // Ver PDF sin barras/miniaturas
  const PDF_PARAMS = '#toolbar=0&navpanes=0&scrollbar=0&statusbar=0&messages=0&view=FitH&zoom=page-width';

  document.addEventListener('DOMContentLoaded', function () {
    // --------- refs DOM ----------
    var tipoDctoSelect      = document.getElementById('tipo_documento_id');
    var formContainer       = document.getElementById('formContainer');
    var identificacionInput = document.getElementById('identificacion');
    var leftColumn          = document.getElementById('leftColumn');
    var viewerBox           = document.getElementById('viewerBox');
    var viewerTitle         = document.getElementById('viewerTitle');
    var viewerFrame         = document.getElementById('viewerFrame');

    // Modal carpeta
    var modal        = document.getElementById('modalCarpeta');
    var mcTitle      = document.getElementById('mcTitle');
    var mcFolder     = document.getElementById('mcFolder');
    var mcFilesBody  = document.getElementById('mcFilesBody');
    var mcCerrar     = document.getElementById('mcCerrar');
    var mcUploadForm = document.getElementById('mcUploadForm');
    var mcFile       = document.getElementById('mcFile');
    var mcUploadBtn  = document.getElementById('mcUploadBtn');
    var btnAbrirCarpeta = document.getElementById('btnAbrirCarpeta');

    // --------- datos PHP ----------
    var serverData             = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
    var bancosData             = <?= json_encode($bancos, JSON_UNESCAPED_UNICODE) ?>;
    var municipiosData         = <?= json_encode($municipios, JSON_UNESCAPED_UNICODE) ?>;
    var regionData             = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
    var tiposCuentaData        = <?= json_encode($tipos_cuenta, JSON_UNESCAPED_UNICODE) ?>;
    var tiposContribuyenteData = <?= json_encode($tipos_contribuyente, JSON_UNESCAPED_UNICODE) ?>;
    var ciiuData               = <?= json_encode($ciiu, JSON_UNESCAPED_UNICODE) ?>;
    var retencionData          = <?= json_encode($retencion, JSON_UNESCAPED_UNICODE) ?>;
    var rowData                = <?= json_encode($row, JSON_UNESCAPED_UNICODE) ?>;

    // --------- utilidades ----------
    function formatBytes(b){
      var u = ['B','KB','MB','GB','TB'], i = 0;
      while (b >= 1024 && i < u.length - 1){ b /= 1024; i++; }
      return (b < 10 && i > 0 ? b.toFixed(1) : Math.round(b)) + ' ' + u[i];
    }

    // Ajusta alto del visor para empatar con el último campo
    function syncHeights(){
      var last = formContainer.lastElementChild;
      var h = leftColumn.offsetHeight;
      if (last){
        var lastRect = last.getBoundingClientRect();
        var colRect  = leftColumn.getBoundingClientRect();
        h = Math.ceil((lastRect.bottom - colRect.top)) + 16;
      }
      var topViewer     = viewerBox.getBoundingClientRect().top;
      var maxByViewport = window.innerHeight - topViewer - 24;
      if (maxByViewport < 300) maxByViewport = 300;
      if (h < 300) h = 300;
      viewerBox.style.height = Math.min(h, maxByViewport) + 'px';
    }
    new ResizeObserver(syncHeights).observe(formContainer);
    window.addEventListener('resize', syncHeights);

    // Crea un campo (DOM puro)
    function createField(cfg){
      var wrap  = document.createElement('div');
      var label = document.createElement('label');
      label.setAttribute('for', cfg.name);
      label.className = 'block text-sm font-medium text-gray-700 mb-1';
      label.innerHTML = cfg.label + ':' + (cfg.required ? ' <span class="text-red-500">*</span>' : '');
      wrap.appendChild(label);

      var el;
      if (cfg.type === 'select'){
        el = document.createElement('select');
        el.id = cfg.name; el.name = cfg.name;
        el.className = 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50';
        if (cfg.required) el.required = true;

        var opt0 = document.createElement('option');
        opt0.value = ''; opt0.textContent = 'Seleccione una opción';
        el.appendChild(opt0);

        var src = cfg.data || {};
        Object.keys(src).forEach(function (key){
          var opt = document.createElement('option');
          opt.value = key; opt.textContent = src[key];
          el.appendChild(opt);
        });
      } else {
        el = document.createElement('input');
        el.type = 'text'; el.id = cfg.name; el.name = cfg.name;
        el.className = 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50';
        if (cfg.required) el.required = true;
      }
      wrap.appendChild(el);
      return wrap;
    }

    function loadForm(){
      var tipoDcto = (tipoDctoSelect.value || '').trim().toUpperCase();

      var camposNIT = [
        {name:'nombre_nit',label:'Nombre',required:true},
        {name:'id',label:'Id tercero',required:true},
        {name:'ciiu_id',label:'CIIU',type:'select',data:ciiuData,required:true},
        {name:'municipio_id',label:'Municipio',type:'select',data:municipiosData,required:true},
        {name:'regionf_id',label:'region',type:'select',data:regionData,required:true},
        {name:'direccion',label:'Dirección',required:true},
        {name:'telefono',label:'Teléfono',required:true},
        {name:'tipo_contribuyente_id',label:'Tipo de Contribuyente',type:'select',data:tiposContribuyenteData,required:true},
        {name:'retencion',label:'Retención',type:'select',data:retencionData,required:true},
        {name:'banco_id',label:'Banco',type:'select',data:bancosData,required:true},
        {name:'tipo_cuenta_id',label:'Tipo de Cuenta',type:'select',data:tiposCuentaData,required:true},
        {name:'num_cuenta_bancaria',label:'Número de Cuenta Bancaria',required:true}
      ];
      var camposCC = [
        {name:'nombre_nit',label:'Nombre',required:true},
        {name:'segundo_nombre',label:'Segundo Nombre',required:false},
        {name:'primer_apellido',label:'Primer Apellido',required:true},
        {name:'segundo_apellido',label:'Segundo Apellido',required:false},
        {name:'id',label:'Id tercero',required:true},
        {name:'ciiu_id',label:'CIIU',type:'select',data:ciiuData,required:true},
        {name:'municipio_id',label:'Municipio',type:'select',data:municipiosData,required:true},
        {name:'regionf_id',label:'region',type:'select',data:regionData,required:true},
        {name:'direccion',label:'Dirección',required:true},
        {name:'telefono',label:'Teléfono',required:true},
        {name:'tipo_contribuyente_id',label:'Tipo de Contribuyente',type:'select',data:tiposContribuyenteData,required:true},
        {name:'retencion',label:'Retención',type:'select',data:retencionData,required:true},
        {name:'banco_id',label:'Banco',type:'select',data:bancosData,required:true},
        {name:'tipo_cuenta_id',label:'Tipo de Cuenta',type:'select',data:tiposCuentaData,required:true},
        {name:'num_cuenta_bancaria',label:'Número de Cuenta Bancaria',required:true}
      ];

      var campos = (tipoDcto === 'NIT') ? camposNIT : camposCC;
      formContainer.innerHTML = '';
      campos.map(createField).forEach(function (n){ formContainer.appendChild(n); });

      // Relleno con datos
      Object.keys(rowData || {}).forEach(function (k){
        var el = document.getElementById(k);
        if (el) el.value = rowData[k];
      });

      syncHeights();
    }

    // -------- visor PDF --------
    function openInViewer(relPath, title){
      var safe = btoa(relPath);
      var url  = URL_VER_DOC + '?f=' + encodeURIComponent(safe) + PDF_PARAMS;
      viewerTitle.textContent = title || relPath.split('/').pop();
      viewerFrame.src = url;
    }

    // -------- buscar RUT/CERT --------
    async function buscarDocsPorNit(nit){
    const fd = new FormData();
    fd.append('nit', nit);
    const resp = await fetch(URL_BUSCAR_DOCS, { method:'POST', body: fd });
    const txt  = await resp.text();
    let data;
    try { data = JSON.parse(txt); } catch(e){ alert('Respuesta inválida:\n' + txt); return null; }
    if (data.error){ alert(data.error); return null; }

    return {
        rut: data.rut_last || null,
        cert: data.cert_banc_last || null,
        files: data.files || [],
        folder: data.folder || '',
        created: !!data.created,
        message: data.message || ''
    };
    }


    document.getElementById('btnRut').addEventListener('click', async function (){
      var nit = (identificacionInput.value || '').trim();
      if (!nit){ alert('Ingrese la Identificación (NIT)'); return; }
      var r = await buscarDocsPorNit(nit);
      if (r && r.rut) openInViewer(r.rut, 'RUT');
      else alert('No se encontró RUT para este NIT.');
    });
    document.getElementById('btnCert').addEventListener('click', async function (){
      var nit = (identificacionInput.value || '').trim();
      if (!nit){ alert('Ingrese la Identificación (NIT)'); return; }
      var r = await buscarDocsPorNit(nit);
      if (r && r.cert) openInViewer(r.cert, 'Certificación bancaria');
      else alert('No se encontró certificación bancaria para este NIT.');
    });

    // -------- modal carpeta --------
    function showModal(){ modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function hideModal(){ modal.classList.add('hidden'); modal.classList.remove('flex'); mcFilesBody.innerHTML=''; mcUploadForm.reset(); }
    modal.addEventListener('click', function (e){ if (e.target === modal) hideModal(); });
    mcCerrar.addEventListener('click', hideModal);

   // === ICONOS (una sola vez, fuera de cualquier función) ===
const PDF_ICON_SVG = `
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
    <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5"/>
    <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.5"/>
  </svg>`;
const EYE_SVG = `
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" fill="none"/>
    <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/>
  </svg>`;
const DOWNLOAD_SVG = `
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
    <path d="M12 3v10m0 0l-4-4m4 4l4-4M4 21h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>`;

// === PINTA LA TABLA DEL MODAL ===
function renderFiles(files) {
  const tbody = document.getElementById('mcFilesBody');
  tbody.innerHTML = '';

  if (!Array.isArray(files) || files.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" class="px-4 py-10 text-center text-slate-500">
          No hay documentos en esta carpeta.
        </td>
      </tr>`;
    return;
  }

  files.forEach(f => {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50';
    tr.innerHTML = `
      <td class="px-4 py-2">
        <div class="flex items-center gap-3 min-w-0">
          <span class="inline-flex items-center justify-center w-8 h-8 rounded bg-slate-100 text-slate-600">${PDF_ICON_SVG}</span>
          <div class="min-w-0">
            <div class="font-medium text-slate-800 truncate" title="${f.name}">${f.name}</div>
            <div class="text-xs text-slate-500 truncate">${f.dir || ''}</div>
          </div>
        </div>
      </td>
      <td class="px-4 py-2 text-right text-slate-700 whitespace-nowrap">${formatBytes(f.size || 0)}</td>
      <td class="px-4 py-2 text-slate-700 whitespace-nowrap">${new Date((f.mtime || 0) * 1000).toLocaleString('es-CO')}</td>
      <td class="px-4 py-2">
        <div class="flex justify-end gap-2">
          <button type="button"
                  class="btn-preview inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-700"
                  data-rel="${f.rel}" data-name="${f.name}">
            ${EYE_SVG}<span class="hidden sm:inline">Ver</span>
          </button>
          <a class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50"
             href="${URL_DESCARGAR_DOC}?f=${encodeURIComponent(btoa(f.rel))}"
             target="_blank" rel="noopener">
            ${DOWNLOAD_SVG}<span class="hidden sm:inline">Descargar</span>
          </a>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });

  // Ver en visor derecho -> cierra modal
  tbody.querySelectorAll('.btn-preview').forEach(btn => {
    btn.addEventListener('click', () => {
      openInViewer(btn.dataset.rel, btn.dataset.name);
      const modal = document.getElementById('modalCarpeta');
      modal.classList.add('hidden'); modal.classList.remove('flex');
    });
  });
}


    async function openCarpetaModal(nit){
    if (!nit){ alert('Ingrese la Identificación (NIT)'); return; }
    const r = await buscarDocsPorNit(nit);
    if (!r) return;

    if (r.created) {
        alert(r.message || 'Carpeta creada');
    }

    mcTitle.textContent  = 'Documentos del NIT ' + nit;
    mcFolder.textContent = r.folder || '(sin carpeta)';
    renderFiles(r.files || []);
    showModal();
    }

    btnAbrirCarpeta.addEventListener('click', function (){
      openCarpetaModal((identificacionInput.value || '').trim());
    });

    mcUploadForm.addEventListener('submit', async function (e){
      e.preventDefault();
      var nit = (identificacionInput.value || '').trim();
      if (!nit){ alert('Ingrese la Identificación (NIT)'); return; }
      if (!mcFile.files.length){ alert('Seleccione un archivo.'); return; }
      var fd = new FormData(mcUploadForm);
      fd.append('nit', nit);

      mcUploadBtn.disabled = true; mcUploadBtn.textContent = 'Subiendo...';
      try {
        var r = await fetch(URL_SUBIR_DOC, { method:'POST', body: fd });
        var t = await r.text();
        var data; try { data = JSON.parse(t); } catch(e){ alert('Respuesta inválida:\n' + t); return; }
        if (!data.success){ alert(data.message || 'No se pudo subir el archivo'); return; }
        // refrescar
        var info = await buscarDocsPorNit(nit);
        if (info){ mcFolder.textContent = info.folder || '(sin carpeta)'; renderFiles(info.files || []); }
      } catch (err) {
        alert('Error al subir: ' + err.message);
      } finally {
        mcUploadBtn.disabled = false; mcUploadBtn.textContent = 'Subir';
      }
    });

    // -------- init --------
    if (rowData && rowData['tipo_documento_id']) tipoDctoSelect.value = rowData['tipo_documento_id'];
    if (rowData && rowData['identificacion'])    identificacionInput.value = rowData['identificacion'];
    loadForm();

    // Re-render al cambiar tipo documento o identificación
    tipoDctoSelect.addEventListener('change', loadForm);
    identificacionInput.addEventListener('change', function(){ /* si quieres recargar docs aquí */ });

    // Guardar
    document.getElementById('cancelar').addEventListener('click', function(){ window.close(); });
    document.getElementById('terceroForm').addEventListener('submit', function(e){
      e.preventDefault();
      var idEl = document.getElementById('id');
      var idTercero = idEl ? idEl.value : '';
      if (!/^\d+$/.test((idTercero || '').trim())){
        alert('El campo "Id tercero" debe contener solo números.');
        return;
      }
      var fd = new FormData(this);
      fetch(urlf, { method:'POST', body: fd })
        .then(function(r){ return r.text(); })
        .then(function(t){
          var data; try { data = JSON.parse(t); } catch(e){ alert('Respuesta inválida del servidor:\n' + t); return; }
          if (data.success){ alert(data.message); window.close(); }
          else { alert('Error: ' + (data.message || 'No especificado')); }
        })
        .catch(function(err){ alert('Error al guardar los datos: ' + err.message); });
    });
  });
</script>


</body>
</html>