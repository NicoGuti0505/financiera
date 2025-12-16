<?php
session_start();
require_once 'config.php'; // Configuración de la base de datos y accesos

$message = '';

// Función para verificar si Node.js está corriendo en el puerto 3000
function isNodeRunning($port = 3000) {
    $connection = @fsockopen('localhost', $port);
    if ($connection) {
        fclose($connection);
        return true; // Node.js está activo
    }
    return false; // Node.js no está activo
}

// Función para iniciar Node.js si no está en ejecución
function startNodeServer() {
    $nodePath = 'C:\Program Files\nodejs\node.exe'; // Ruta de Node.js
    $serverPath = 'C:\xampp\htdocs\aplicacion\proyecto_mapa\src\server.js'; // Ruta del servidor Node.js

    if (!isNodeRunning(3000)) {
        $command = "\"$nodePath\" \"$serverPath\" > NUL 2>&1 &";
        pclose(popen($command, 'r'));
    }
}

// Función para verificar el usuario con contraseñas encriptadas
function verificarUsuario($conn, $identificacion, $contrasena) {
    $sql = "SELECT id, nombre, grupor_id, tipo_usuario_id, tipo_usuario_id2, contrasena FROM usuario WHERE id = ?";
    $params = array($identificacion);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $usuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
        return $usuario;
    }

    return false;
}

if (isset($_SESSION['usuario_autenticado'])) {
    header("Location: php/menu.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $usuario = verificarUsuario($conn, $_POST['identificacion'], $_POST['contrasena']);

    if ($usuario) {
        session_regenerate_id(true);
        $newSessionId = session_id();

        // Actualizar session_id en la base de datos
        $updateSql = "UPDATE usuario SET session_id = ? WHERE id = ?";
        $params = array($newSessionId, $usuario['id']);
        $stmtUpdate = sqlsrv_query($conn, $updateSql, $params);

        if ($stmtUpdate === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        // Guardar datos de sesión
        $_SESSION['usuario_autenticado'] = true;
        $_SESSION['nombre_usuario'] = $usuario['nombre'];
        $_SESSION['identificacion_usuario'] = $usuario['id'];
        $_SESSION['grupor_id'] = $usuario['grupor_id'];
        $_SESSION['tipo_usuario_id'] = $usuario['tipo_usuario_id'];
        $_SESSION['tipo_usuario_id2'] = $usuario['tipo_usuario_id2'];
        $_SESSION['session_id'] = $newSessionId;

        // 🔹 Iniciar el servidor Node.js si no está corriendo
        startNodeServer();

        header("Location: php/menu.php");
        exit();
    } else {
        $message = "Usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INICIAR SESIÓN</title>
    <link rel="stylesheet" href="php/estilos/inicio_estilo.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="logo.png" alt="Logo">
        </div>

        <h2 class="login-title">INICIAR SESIÓN</h2>

        <?php if (!empty($message)): ?>
            <div class="error-message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label for="identificacion">Usuario:</label>
                <input type="text" id="identificacion" name="identificacion" required>
            </div>
            <div class="input-group">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </div>
            <button type="submit" name="login" class="login-button">Ingresar</button>
        </form>
    </div>

    <script>
        document.querySelector("form").addEventListener("submit", function(event) {
            let inputs = document.querySelectorAll("input");
            let valid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    valid = false;
                    input.classList.add("error");
                } else {
                    input.classList.remove("error");
                }
            });

            if (!valid) {
                event.preventDefault();
                this.classList.add("shake");
                setTimeout(() => this.classList.remove("shake"), 500);
            }
        });
    </script>
</body>
</html>
