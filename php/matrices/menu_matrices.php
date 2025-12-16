<?php
session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['tipo_usuario_id']) || ($_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 5 && $_SESSION['tipo_usuario_id'] != 6)) {
    header('Location: ../menu.php');
    exit;
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Matrices a Excel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">Descarga de Matrices</h2>   

        <!-- Filtro de fechas único -->
        <div class="mb-4">
            <label for="start_date" class="block text-sm font-medium text-gray-700">Fecha de inicio:</label>
            <input type="date" id="start_date" name="start_date" required 
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
        </div>
        <div class="mb-4">
            <label for="end_date" class="block text-sm font-medium text-gray-700">Fecha final:</label>
            <input type="date" id="end_date" name="end_date" required 
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
        </div>

        <!-- Formularios con botones -->
        <form id="form_aprobados" method="POST" action="export_aprobados.php" class="space-y-4">
            <input type="hidden" name="start_date" id="hidden_start_aprobados">
            <input type="hidden" name="end_date" id="hidden_end_aprobados">
            <button type="submit" class="w-full bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 mb-2">
                Descargar Matriz Aprobados
            </button>
        </form>

        <form id="form_rechazados" method="POST" action="export_rechazados.php" class="space-y-4">
            <input type="hidden" name="start_date" id="hidden_start_rechazados">
            <input type="hidden" name="end_date" id="hidden_end_rechazados">
            <button type="submit" class="w-full bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 mb-2">
                Descargar Matriz Rechazados
            </button>
        </form>

        <form id="form_subsanados" method="POST" action="export_subsanados.php" class="space-y-4">
            <input type="hidden" name="start_date" id="hidden_start_subsanados">
            <input type="hidden" name="end_date" id="hidden_end_subsanados">
            <button type="submit" class="w-full bg-yellow-500 text-white py-2 px-4 rounded-md hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-opacity-50 mb-2">
                Descargar Matriz Subsanados
            </button>
        </form>

        <form id="form_masivo" method="POST" action="export_masivo.php" class="space-y-4">
            <input type="hidden" name="start_date" id="hidden_start_masivo">
            <input type="hidden" name="end_date" id="hidden_end_masivo">
            <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-opacity-50 mb-2">
                Descargar Reporte Masivo
            </button>
        </form>

        <!-- Botón para regresar al menú -->
        <a href="../menu.php" 
   class="block w-full bg-gray-500 text-white py-2 px-4 rounded-lg text-center font-semibold shadow-md hover:bg-red-600 transition duration-300 mt-5">
    Regresar al Menú
</a>




    </div>

    <script>
        // Función para copiar las fechas en los formularios antes de enviarlos
        function copiarFechas(event, formId) {
            event.preventDefault(); // Evita el envío inmediato

            let startDate = document.getElementById("start_date").value;
            let endDate = document.getElementById("end_date").value;

            if (!startDate || !endDate) {
                alert("Por favor, selecciona un rango de fechas antes de descargar.");
                return;
            }

            document.getElementById("hidden_start_" + formId).value = startDate;
            document.getElementById("hidden_end_" + formId).value = endDate;

            document.getElementById("form_" + formId).submit(); // Envía el formulario con las fechas copiadas
        }

        // Asigna el evento de clic a cada botón
        document.getElementById("form_aprobados").addEventListener("submit", function(event) {
            copiarFechas(event, "aprobados");
        });

        document.getElementById("form_rechazados").addEventListener("submit", function(event) {
            copiarFechas(event, "rechazados");
        });

        document.getElementById("form_subsanados").addEventListener("submit", function(event) {
            copiarFechas(event, "subsanados");
        });

        document.getElementById("form_masivo").addEventListener("submit", function(event) {
         copiarFechas(event, "masivo");
        });

    </script>

</body>
</html>
