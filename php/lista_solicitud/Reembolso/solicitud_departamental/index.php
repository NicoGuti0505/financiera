<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

session_start();
// Verificar sesión
if (!isset($_SESSION['tipo_usuario_id']) && !isset($_SESSION['tipo_usuario_id2'])) {
    header('Location: login.php');
    exit;
}

$usuarios_permitidos = [1, 2, 9];
if (!in_array($_SESSION['tipo_usuario_id'], $usuarios_permitidos) && 
    !in_array($_SESSION['tipo_usuario_id2'], $usuarios_permitidos)) {
    header('Location: ../menu.php');
    exit;
}

// Obtener usuarios
$usuarios_sql = "SELECT id, nombre FROM usuario WHERE tipo_usuario_id = 5";
$usuarios_stmt = sqlsrv_query($conn, $usuarios_sql);
$usuarios = [];
while ($row = sqlsrv_fetch_array($usuarios_stmt, SQLSRV_FETCH_ASSOC)) {
    $usuarios[] = $row;
}
sqlsrv_free_stmt($usuarios_stmt);

$usuario_filtro = $_POST['usuario_filtro'] ?? '';

// Consulta principal
$sql = "SELECT s.numero_identificacion, s.url_drive, s.proceso
        FROM solicitudes s
        JOIN (SELECT id_usuario, radicado, MIN(fecha_estado) AS primer_evento
              FROM evento_solicitudes GROUP BY id_usuario, radicado) e 
        ON s.radicado = e.radicado
        WHERE s.estado_pago = 'activado' 
        AND s.proceso_tercero = 'Rembolso'
        AND s.apr_departamental IS NULL";

$params = [];
if (!empty($usuario_filtro)) {
    $sql .= " AND e.id_usuario = ?";
    $params[] = $usuario_filtro;
}

$stmt = sqlsrv_query($conn, $sql, $params);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Departamental</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="script.js" defer></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Verificación Departamental</h1>
        
        <div class="search-bar">
            <form method="POST" action="">
                <select name="usuario_filtro" id="usuario_filtro">
                    <option value="">Seleccionar usuario</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= $usuario['id'] ?>" <?= ($usuario['id'] == $usuario_filtro) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($usuario['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-blue">🔍 Buscar</button>
                <a href="../../../menu.php" class="btn btn-red">⬅ Regresar al Menú</a>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Identificación</th>
                    <th>URL Drive</th>
                    <th>Proceso</th>
                    <th>Actualizar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($stmt !== false) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['numero_identificacion']) . "</td>";
                        echo "<td><a href='" . htmlspecialchars($row['url_drive']) . "' target='_blank'>📂 Abrir</a></td>";
                        echo "<td>" . htmlspecialchars($row['proceso']) . "</td>";
                        echo "<td>
                            <form class='proceso-form' data-identificacion='" . htmlspecialchars($row['numero_identificacion']) . "'>
                                <select class='proceso-select'>
                                    <option value='Revision'>Revisión</option>
                                    <option value='Aprobado'>Aprobado</option>
                                    <option value='Rechazado'>Rechazado</option>
                                </select>
                                <button type='button' class='update-individual btn btn-green'>🔄 Actualizar</button>
                            </form>
                        </td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <!-- Botón de actualización masiva -->
        <button id="update-massive" class="btn btn-yellow">🔄 Actualizar Todo</button>
    </div>

    <!-- Mensaje flotante -->
    <div id="mensajeActualizacion" class="mensaje-flotante"></div>
</body>
</html>

<?php
if ($stmt !== false) {
    sqlsrv_free_stmt($stmt);
}
sqlsrv_close($conn);
?>
