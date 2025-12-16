<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MENU</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .fade-out {
            transition: opacity 1s ease-out;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <?php
    // Inicia la sesión y verifica el tipo de usuario
    session_start();
    

    if($_SESSION['tipo_usuario_id'] == 1 || $_SESSION['tipo_usuario_id'] == 2) {
    ?>
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">MENU DE USUARIOS</h2>
            <?php
            require_once '../../config.php';
            if (isset($_SESSION['mensaje_exito'])) {
                echo "<div id='mensaje-exito' class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 fade-out' role='alert'>";
                echo "<p class='font-bold'>Éxito!</p>";
                echo "<p>" . htmlspecialchars($_SESSION['mensaje_exito']) . "</p>";
                echo "</div>";
                unset($_SESSION['mensaje_exito']);
            }
            ?>
            <a href="crear_usuarios/formulario_usuarios.php" class="block w-full bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 text-center mb-4">Crear Usuarios</a>
            <a href="editar_usuarios/modificar.php" class="block w-full bg-gray-700 text-white py-2 px-4 rounded-md hover:bg-gray-900 focus:outline-none focus:ring-2 focus:bg-gray-800 focus:ring-opacity-50 text-center mb-4">Editar usuarios</a>
            <a href="lista_usuarios/listado.php" class="block w-full bg-yellow-700 text-white py-2 px-4 rounded-md hover:bg-gray-900 focus:outline-none focus:ring-2 focus:bg-gray-800 focus:ring-opacity-50 text-center mb-4">Lista de usuarios</a>
            <a href="<?php echo url('menu.php'); ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block">Atrás</a>
        </div>
    <?php
    } else {
        // Mensaje o redirección si el usuario no tiene permisos
        echo "<p class='text-center text-red-600'>No tienes permisos para acceder a esta página.</p>";
        
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mensajeExito = document.getElementById('mensaje-exito');
            if (mensajeExito) {
                setTimeout(function() {
                    mensajeExito.style.opacity = '0';
                }, 6000);
                
                setTimeout(function() {
                    mensajeExito.style.display = 'none';
                }, 7000);
            }
        });
    </script>
</body>
</html>
