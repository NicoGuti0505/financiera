<?php

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
session_start();

// Depuración: Registrar información de la sesión al cargar la página de menú
error_log("Sesión al cargar la página de menú: " . print_r($_SESSION, true));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MENU ANEXO DE PAGO</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .fade-out {
            transition: opacity 1s ease-out;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">MENU ANEXO DE PAGO</h2>
        <a href="listado_trabajo/anexo_pago.php" class="block w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 text-center mb-4">LISTA DE TRABAJO</a>
        <a href="modificacion_anexo/anexo_pago.php"class="block w-full bg-purple-700 text-white py-2 px-4 rounded-md hover:bg-purple-900 focus:outline-none focus:ring-2 focus:bg-purple-800 focus:ring-opacity-50 text-center mb-4">MODIFICAR ANEXO</a>
        <a href="exportes/exportar_anexo_pago.php" class="block w-full bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 text-center mb-4">Exportar Anexo de Pago (Excel)</a>
        <a href="<?php echo url('menu.php'); ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block">Atrás</a>
    </div>
</body>
</html>