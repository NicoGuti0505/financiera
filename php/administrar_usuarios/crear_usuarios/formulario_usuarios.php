<?php

// Obtiene el directorio del archivo actual y dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'  
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

// Manejar la solicitud de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    $documento = $_POST['id'];
    $nombre = $_POST['nombre'];
    $cargo_id = $_POST['cargo'];
    $grupor_id = $_POST['grupo'];
    $contrasena = $_POST['contrasena'];

    // Si no se selecciona "Cargo Adicional", se asigna NULL
    $cargo_id2 = !empty($_POST['permiso']) ? $_POST['permiso'] : NULL;

    // Asegurarse de que los campos obligatorios estén llenos
    if (empty($documento) || empty($nombre) || empty($cargo_id) || empty($grupor_id) || empty($contrasena)) {
        echo "<p class='error'>Por favor, completa todos los campos obligatorios.</p>";
    } else {
        // Encriptar la contraseña antes de insertarla en la base de datos
        $contrasena_encriptada = password_hash($contrasena, PASSWORD_DEFAULT);

        // Preparar la consulta de inserción
        $queryInsert = "INSERT INTO usuario (id, nombre, tipo_usuario_id, grupor_id, contrasena, tipo_usuario_id2) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $params = array($documento, $nombre, $cargo_id, $grupor_id, $contrasena_encriptada, $cargo_id2);

        $stmt = sqlsrv_query($conn, $queryInsert, $params);

        if ($stmt === false) {
            echo "<p class='error'>Error en la inserción: " . print_r(sqlsrv_errors(), true) . "</p>";
        } else {
            echo "<p class='success'>Usuario registrado exitosamente.</p>";
        }
    }
}

// Consultar los cargos
$querytipo_usuario = "SELECT id, descripcion FROM tipo_usuario";
$resultadotipo_usuario = sqlsrv_query($conn, $querytipo_usuario);

// Consultar las regiones
$queryregion = "SELECT id, descripcion FROM grupo_region";
$resultadoregion = sqlsrv_query($conn, $queryregion);

// Segunda consulta para tipo_usuario (Cargo Adicional)
$querytipo_usuario2 = "SELECT id, descripcion FROM tipo_usuario";
$resultadotipo2_usuario = sqlsrv_query($conn, $querytipo_usuario2);

session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['tipo_usuario_id']) || ($_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 1 )) {
    header('Location: ../../menu.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuarios</title>
    <link rel="stylesheet" href="diseno.css">
</head>
<body>
    <form method="post" action="">
        <h2>INICIA NUEVO REGISTRO</h2>
        <p>Por favor, rellena todos los campos obligatorios</p>
        
        <div class="input-wrapper">
            <input type="text" name="id" placeholder="Usuario" required>
            <img class="input-icon" src="images/name.svg" alt="">
        </div>
        
        <div class="input-wrapper">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <img class="input-icon" src="images/name.svg" alt="">
        </div>
        
        <div class="input-wrapper">
            <select name="cargo" required>
                <option value="" disabled selected>Cargo del Trabajador</option>
                <?php
                    while ($row = sqlsrv_fetch_array($resultadotipo_usuario, SQLSRV_FETCH_ASSOC)) {
                        if ($row['descripcion'] !== 'ROOT') {
                            echo "<option value='{$row['id']}'>{$row['descripcion']}</option>";
                        }
                    }
                ?>
            </select>
            <img class="input-icon" src="images/name.svg" alt="">   
        </div>

        <div class="input-wrapper">
            <select name="permiso">
                <option value="" selected>Cargo Adicional (Opcional)</option>
                <?php
                    while ($row = sqlsrv_fetch_array($resultadotipo2_usuario, SQLSRV_FETCH_ASSOC)) {
                        if ($row['descripcion'] !== 'ROOT') {
                            echo "<option value='{$row['id']}'>{$row['descripcion']}</option>";
                        }
                    }
                ?>
            </select>
            <img class="input-icon" src="images/name.svg" alt="">   
        </div>
        
        <div class="input-wrapper">
            <select name="grupo" required>
                <option value="" disabled selected>Grupo Encargado</option>
                <?php
                    while ($row = sqlsrv_fetch_array($resultadoregion, SQLSRV_FETCH_ASSOC)) {
                        echo "<option value='{$row['id']}'>{$row['descripcion']}</option>";
                    }
                ?>
            </select>
            <img class="input-icon" src="images/direction.svg" alt="">
        </div>
        <div class="input-wrapper">
    <input type="text" name="contrasena" placeholder="Contraseña" required>
    <img class="input-icon" src="images/password.svg" alt="">
</div>

        
        
        <input class="btn" type="submit" name="registro" value="Registrar">
        <a href="../../menu.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block">Atrás</a>

    </form>
</body>
</html>
