<?php
// Obtiene el directorio del archivo actual y divide la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
session_start();

// Verificar el tipo de usuario
function verificarAcceso() {
    if ($_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 2) {
        echo "<div class='alert alert-danger text-center mt-4' style='font-size: 24px; background-color: #f8d7da; color: #721c24; padding: 30px; border-radius: 10px; width: 80%; max-width: 600px; margin: 0 auto;'>
                <strong>Acceso denegado:</strong> No tienes permisos para acceder a esta función.
                Comunícate con un directivo.
                <br><br>
                <a href='javascript:history.back()' class='btn btn-danger' style='font-size: 18px;'>Volver</a>
              </div>";
        exit();
    }
}

verificarAcceso();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta y Actualización de Datos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
body {
    background: linear-gradient(to right, #E3F2FD, #CFEBF9);
    font-family: 'Arial', sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

.container {
    width: 100%;
    max-width: 400px;
    padding: 20px;
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
    padding: 20px;
    background:rgba(255, 255, 255, 0.38);
}

.form-group {
    margin-bottom: 15px;
}

.form-control {
    border-radius: 10px;
    padding: 12px;
    border: 1px solid #ced4da;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
}

.btn-primary {
    background-color: #007bff;
    border: none;
    border-radius: 10px;
    padding: 10px;
    width: 100%;
    transition: all 0.3s;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    border: none;
    border-radius: 10px;
    padding: 10px;
    width: 100%;
    margin-top: 10px;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h3 class="text-center mb-4">Actualización de Datos</h3>
                <form id="consultaForm" method="post" class="d-flex flex-column align-items-center">
                    <div class="mb-3 w-100">
                        <label for="identificacion" class="form-label">Identificación:</label>
                        <input type="text" name="identificacion" id="identificacion" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Consultar</button>
                    <a href="<?php echo url('menu.php'); ?>" class="bg-gray-500 text-black px-4 py-2 rounded hover:bg-gray-600 inline-block">Atrás</a>
                </form>
            </div>

        <div id="mensaje" class="alert d-none mt-4"></div>

            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['identificacion'])) {
                $identificacion = htmlspecialchars($_POST['identificacion']);
                $sql = "SELECT id, identificacion, nombre_nit FROM tercero WHERE identificacion = ?";
                $stmt = sqlsrv_prepare($conn, $sql, array(&$identificacion));

                if ($stmt && sqlsrv_execute($stmt)) {
                    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    if ($result) {
                        echo "<h4>Resultados:</h4>";
                        echo "<form method='post' id='updateForm' class='mt-4'>";
                        echo "<div class='mb-3'>
                                <label for='original_id' class='form-label'>ID Actual:</label>
                                <input type='text' name='original_id' id='original_id' class='form-control' value='" . htmlspecialchars($result['id']) . "' readonly>
                              </div>";
                        echo "<div class='mb-3'>
                                <label for='nuevo_id' class='form-label'>Nuevo ID:</label>
                                <input type='text' name='nuevo_id' id='nuevo_id' class='form-control' value='' required pattern='\\d+' title='El ID solo debe contener números.'>
                              </div>";
                        echo "<div class='mb-3'>
                                <label for='identificacion' class='form-label'>Identificación:</label>
                                <input type='text' name='identificacion' id='identificacion' class='form-control' value='" . htmlspecialchars($result['identificacion']) . "' required>
                              </div>";
                        echo "<div class='mb-3'>
                                <label for='nombre' class='form-label'>Nombre Completo:</label>
                                <input type='text' name='nombre' id='nombre' class='form-control' value='" . htmlspecialchars($result['nombre_nit']) . "' required>
                              </div>";
                        echo "<button type='submit' name='update' class='btn btn-success'>Actualizar</button>";
                        echo "</form>";
                    } else {
                        echo "<div class='alert alert-warning'>No se encontraron resultados para la identificación proporcionada.</div>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>Error al ejecutar la consulta: " . print_r(sqlsrv_errors(), true) . "</div>";
                }
                sqlsrv_free_stmt($stmt);
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
                $originalId = htmlspecialchars($_POST['original_id']);
                $nuevoId = htmlspecialchars($_POST['nuevo_id']);
                $nombre = htmlspecialchars($_POST['nombre']);
                $identificacion = htmlspecialchars($_POST['identificacion']);

                if (!ctype_digit($nuevoId)) {
                    echo "<div class='alert alert-danger'>El nuevo ID debe contener únicamente números.</div>";
                    exit();
                }

                $updateSql = "UPDATE tercero SET id = ?, nombre_nit = ?, identificacion = ? WHERE id = ?";
                $updateStmt = sqlsrv_prepare($conn, $updateSql, array(&$nuevoId, &$nombre, &$identificacion, &$originalId));

                if ($updateStmt === false) {
                    $mensaje = "Error al preparar la consulta.";
                    $status = "danger";
                } else {
                    if (sqlsrv_execute($updateStmt)) {
                        $mensaje = "Los datos se actualizaron correctamente.";
                        $status = "success";
                    } else {
                        $mensaje = "Error al actualizar los datos.";
                        $status = "danger";
                    }
                }
                sqlsrv_free_stmt($updateStmt);

                echo "<script>
                        document.getElementById('resultados').classList.add('d-none');
                        const mensaje = document.getElementById('mensaje');
                        mensaje.classList.remove('d-none', 'alert-success', 'alert-danger');
                        mensaje.classList.add('alert-$status');
                        mensaje.textContent = '$mensaje';
                      </script>";
            }

            sqlsrv_close($conn);
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

