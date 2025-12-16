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

if (!isset($_SESSION['tipo_usuario_id']) || !in_array($_SESSION['tipo_usuario_id'], [1, 2, 6])) {
    header('Location: ../menu.php');
    exit;
}

$numero_identificacion_filtro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_identificacion'])) {
    $numero_identificacion_filtro = $_POST['numero_identificacion_filtro'];
}

$sql = "SELECT s.numero_identificacion, s.url_drive, s.proceso
        FROM solicitudes s
        JOIN (
            SELECT radicado, MIN(fecha_estado) AS primer_evento
            FROM evento_solicitudes
            GROUP BY radicado
        ) e ON s.radicado = e.radicado
        WHERE s.estado_pago = 'activado' 
          AND s.proceso_tercero = 'Anticipo'
          AND s.apr_departamental = 'Aprobado'";

$params = [];

if (!empty($numero_identificacion_filtro)) {
    $sql .= " AND s.numero_identificacion = ?";
    $params[] = $numero_identificacion_filtro;
}

$stmt = sqlsrv_query($conn, $sql, $params);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Anticipos Pendientes</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
      <div id="mensajeActualizacion" class="mensaje-flotante"></div>
        <h1>Anticipos Pendientes</h1>
        <form method="POST">
            <div class="search-bar">
                <input type="text" name="numero_identificacion_filtro" placeholder="Buscar por número de identificación" value="<?= htmlspecialchars($numero_identificacion_filtro) ?>">
                <button type="submit" name="search_identificacion" class="btn btn-blue">🔍 Buscar</button>
                <a href="../../../menu.php" class="btn btn-red">⬅ Regresar al Menú</a>
            </div>
        </form>

        <form id="massive-update-form">
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
                    <?php if ($stmt): ?>
                        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['numero_identificacion']) ?></td>
                                <td><a href="<?= htmlspecialchars($row['url_drive']) ?>" target="_blank" class="drive-link">📂 Abrir</a></td>
                                <td><?= htmlspecialchars($row['proceso']) ?></td>
                                <td>
                                    <div class="proceso-form" data-identificacion="<?= htmlspecialchars($row['numero_identificacion']) ?>">
                                        <select name="proceso" class="proceso-select">
                                            <option value="Revision">Revision</option>
                                            <option value="Aprobado">Aprobado</option>
                                            <option value="Rechazado">Rechazado</option>
                                            <option value="Subsanacion">Subsanacion</option>
                                        </select>
                                        <textarea name="observacion" class="observacion-field" placeholder="Escriba la observación..."></textarea>
                                        <button type="button" class="btn btn-green update-individual">🔄 Actualizar</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No se encontraron resultados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-blue massive-btn" id="update-massive">Actualizar Todos</button>
        </form>
    </div>
    <script src="script.js"></script>
</body>
</html>
<?php
if ($stmt) sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
