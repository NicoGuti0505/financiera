<?php
session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['tipo_usuario_id'], [1, 2, 5, 6])) {
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

        <form method="POST" action="export_masivo5.php" class="space-y-4">
            <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-opacity-50 mb-2">
                Descargar Reporte 
            </button>
        </form>

        <!-- Botón para regresar al menú -->
        <a href="../menu.php" 
           class="block w-full bg-gray-500 text-white py-2 px-4 rounded-lg text-center font-semibold shadow-md hover:bg-red-600 transition duration-300 mt-5">
            Regresar al Menú
        </a>
    </div>

</body>
</html>
