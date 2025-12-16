<?php

// Obtiene el directorio del archivo actual y dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'  
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
if ($conn === false) {
    die(json_encode(array("error" => sqlsrv_errors())));
}

// Obtener datos del usuario si se selecciona un ID
if (isset($_GET['id'])) {
    $userId = $_GET['id'];
    $query = "SELECT id, nombre, tipo_usuario_id, grupor_id, tipo_usuario_id2 FROM usuario WHERE id = ?";
    $params = array($userId);
    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        die(json_encode(array("error" => sqlsrv_errors())));
    }

    $userData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    echo json_encode($userData);
    exit;
}

// Manejo de la actualización del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $userId = $_POST['user_id'];
    $nombre = $_POST['nombre'];
    $cargo_id = $_POST['cargo'];
    $cargo_id2 = $_POST['permiso'];
    $grupor_id = $_POST['grupo'];
    $contrasena = $_POST['contrasena'];

    if (!empty($contrasena) && $contrasena !== '*****') {
        $queryUpdate = "UPDATE usuario SET nombre = ?, tipo_usuario_id = ?, grupor_id = ?, tipo_usuario_id2 = ?, contrasena = ? WHERE id = ?";
        $params = array($nombre, $cargo_id, $grupor_id, $cargo_id2, password_hash($contrasena, PASSWORD_DEFAULT), $userId);
    } else {
        $queryUpdate = "UPDATE usuario SET nombre = ?, tipo_usuario_id = ?, grupor_id = ?, tipo_usuario_id2 = ? WHERE id = ?";
        $params = array($nombre, $cargo_id, $grupor_id, $cargo_id2, $userId);
    }

    $stmt = sqlsrv_query($conn, $queryUpdate, $params);

    if ($stmt === false) {
        echo "<p class='error'>Error al actualizar: " . print_r(sqlsrv_errors(), true) . "</p>";
    } else {
        echo "<p class='success'>Usuario actualizado exitosamente.</p>";
    }
}

// Consultas para listas desplegables
$queryUsuarios = "SELECT id, nombre FROM usuario";
$resultadoUsuarios = sqlsrv_query($conn, $queryUsuarios);

$queryCargos = "SELECT id, descripcion FROM tipo_usuario";
$resultadoCargos = sqlsrv_query($conn, $queryCargos);

$queryRegiones = "SELECT id, descripcion FROM grupo_region";
$resultadoRegiones = sqlsrv_query($conn, $queryRegiones);

$queryPermisos = "SELECT id, descripcion FROM tipo_usuario";
$resultadoPermisos = sqlsrv_query($conn, $queryPermisos);

session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 1) {
    header('Location: ../../menu.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Usuarios</title>
    <link rel="stylesheet" href="diseno.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#userSelect').select2({
                placeholder: "Selecciona un usuario",
                allowClear: true
            });

            $('#userSelect').on('change', function() {
                cargarDatosUsuario(this.value);
            });
        });

        function cargarDatosUsuario(userId) {
            if (!userId) return;
            fetch(`?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('user_id').value = data.id;
                    document.getElementById('nombre').value = data.nombre;
                    document.getElementById('cargo').value = data.tipo_usuario_id;
                    document.getElementById('permiso').value = data.tipo_usuario_id2;
                    document.getElementById('grupo').value = data.grupor_id;
                    document.getElementById('contrasena').value = '';  // Deja vacío para evitar confusión
                })
                .catch(error => console.error("Error al cargar datos del usuario:", error));
        }
    </script>
</head>
<body>

    <form>
        <label for="userSelect">Selecciona un usuario:</label>
        <select id="userSelect" style="width: 100%;">
            <option value="" disabled selected>Selecciona un usuario</option>
            <?php while ($row = sqlsrv_fetch_array($resultadoUsuarios, SQLSRV_FETCH_ASSOC)): ?>
                <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    </form>

    <form method="post" action="">
        <input type="hidden" name="user_id" id="user_id">

        <div class="input-wrapper">
            <input type="text" name="nombre" id="nombre" placeholder="Nombre">
            <img class="input-icon" src="images/name.svg" alt="">
        </div>

        <div class="input-wrapper">
            <select name="cargo" id="cargo">
                <option value="" disabled selected>Cargo del Trabajador</option>
                <?php while ($row = sqlsrv_fetch_array($resultadoCargos, SQLSRV_FETCH_ASSOC)): ?>
                    <option value="<?= $row['id'] ?>"><?= $row['descripcion'] ?></option>
                <?php endwhile; ?>
            </select>
            <img class="input-icon" src="images/name.svg" alt="">
        </div>

        <div class="input-wrapper">
            <select name="permiso" id="permiso">
                <option value="" disabled selected>Cargo Adicional(opcional)</option>
                <?php while ($row = sqlsrv_fetch_array($resultadoPermisos, SQLSRV_FETCH_ASSOC)): ?>
                    <option value="<?= $row['id'] ?>"><?= $row['descripcion'] ?></option>
                <?php endwhile; ?>
            </select>
            <img class="input-icon" src="images/name.svg" alt="">
        </div>

        <div class="input-wrapper">
            <select name="grupo" id="grupo">
                <option value="" disabled selected>Región Encargada</option>
                <?php while ($row = sqlsrv_fetch_array($resultadoRegiones, SQLSRV_FETCH_ASSOC)): ?>
                    <option value="<?= $row['id'] ?>"><?= $row['descripcion'] ?></option>
                <?php endwhile; ?>
            </select>
            <img class="input-icon" src="images/direction.svg" alt="">
        </div>

        <div class="input-wrapper">
            <input type="text" name="contrasena" id="contrasena" placeholder="Nueva Contraseña (opcional)">
            <img class="input-icon" src="images/password.svg" alt="">
        </div>

        <input class="btn" type="submit" name="update" value="Actualizar">
        <a href="../../menu.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block">Atrás</a>
    </form>

</body>
</html>
