<?php
session_start();
// Verificar sesión
if (!isset($_SESSION['tipo_usuario_id']) && !isset($_SESSION['tipo_usuario_id2'])) {
    header('Location: ../../inicio_sesion.php');
    exit;
}

$usuarios_permitidos = [1, 2, 3, 4, 7];
if (!in_array($_SESSION['tipo_usuario_id'], $usuarios_permitidos) && 
    !in_array($_SESSION['tipo_usuario_id2'], $usuarios_permitidos)) {
    header('Location: ../menu.php');
    exit;
}
?>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<div class="min-h-screen bg-gray-100 flex flex-col items-center justify-center p-6">
  <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl p-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">📄 Carga de Pagos CSV</h1>
    
    <form id="form-carga" enctype="multipart/form-data" class="space-y-6">
      <label class="block text-sm font-medium text-gray-700">
        Selecciona archivo CSV
        <input type="file" name="archivo_csv" accept=".csv" required
               class="block w-full mt-2 text-sm text-gray-700 border border-gray-300 rounded-lg shadow-sm
                      file:mr-4 file:py-2 file:px-4
                      file:rounded-full file:border-0 file:text-sm file:font-semibold
                      file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200" />
      </label>

      <button type="submit"
              class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition duration-300">
        📤 Cargar CSV
      </button>
    </form>

    <!-- Spinner -->
    <div id="spinner" class="flex justify-center mt-6 hidden">
      <div class="animate-spin rounded-full h-10 w-10 border-t-4 border-b-4 border-blue-500"></div>
    </div>

    <!-- Resultado -->
    <div id="resultado" class="mt-6 text-center text-sm text-gray-800"></div>

    <!-- Botón de descarga -->
<!-- Botones de descarga -->
<div class="mt-8 text-center space-y-4">
  <!-- Siempre visible: botón del día anterior -->
  <a href="descargar_dia_anterior.php"
     class="inline-block bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition">
    📥 Descargar pagos del día anterior (CSV)
  </a>

  <?php if ($_SESSION['tipo_usuario_id'] == 1 || $_SESSION['tipo_usuario_id2'] == 1): ?>
    <!-- Solo para root (ID 1): selector de fecha -->
    <form method="GET" action="descargar_dia_anterior.php" class="mt-4 flex items-center justify-center gap-2">
      <input type="date" name="fecha" required
             class="border border-gray-300 rounded-lg px-3 py-1 text-sm text-gray-700" />
      <button type="submit"
              class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition">
        📅 Descargar por fecha
      </button>
    </form>
  <?php endif; ?>
</div>


<script>
document.getElementById('form-carga').addEventListener('submit', function (e) {
  e.preventDefault();

  const formData  = new FormData(this);
  const spinner   = document.getElementById('spinner');
  const resultado = document.getElementById('resultado');

  spinner.classList.remove('hidden');
  resultado.innerHTML = '';

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'subir_y_cargar.php');

  xhr.onerror = function () {
    spinner.classList.add('hidden');
    resultado.innerHTML = '<div class="text-red-500">⚠️ Error de red al enviar el archivo.</div>';
  };

  xhr.onload = function () {
    spinner.classList.add('hidden');

    let respuesta;
    try {
      respuesta = JSON.parse(xhr.responseText);
    } catch (_) {
      resultado.innerHTML = '<div class="text-red-500">⚠️ Respuesta no válida del servidor.</div>';
      return;
    }

    if (respuesta.success) {
      resultado.innerHTML = `<div class="text-green-600 font-semibold">✅ ${respuesta.message}</div>`;
      return;
    }

    // Construye enlaces de descarga de TXT
    let enlaces = '';
    if (respuesta.error_txt_main) {
      enlaces += `<a href="${respuesta.error_txt_main}" target="_blank"
                    class="text-blue-600 underline block">📥 Descargar TXT principal</a>`;
    }
    if (Array.isArray(respuesta.error_txts) && respuesta.error_txts.length > 0) {
      enlaces += `<div class="mt-2 text-sm space-y-1">`;
      enlaces += respuesta.error_txts
        .filter(e => e && e.url)
        .map(e => `<a href="${e.url}" target="_blank" class="text-blue-600 underline block">• ${e.name}</a>`)
        .join('');
      enlaces += `</div>`;
    }
    if (!enlaces) {
      enlaces = `<div class="text-gray-600 text-sm">No hay archivos de error para descargar.</div>`;
    }

    resultado.innerHTML = `
      <div class="text-red-600 font-semibold mb-2">❌ ${respuesta.message || 'Hubo errores en la validación.'}</div>
      ${enlaces}
    `;
  };

  xhr.send(formData);
});
</script>

