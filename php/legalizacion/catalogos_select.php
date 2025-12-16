<?php

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';


$tipo = $_GET['tipo'] ?? '';

$tabla = '';
switch ($tipo) {
    case 'tipo_documento':
        $tabla = 'tipo_documento';
        break;
    case 'banco':
        $tabla = 'banco';
        break;
    case 'tipo_cuenta':
        $tabla = 'tipo_cuenta';
        break;
    default:
        http_response_code(400);
        echo 'Tipo no válido';
        exit;
}

$sql = "SELECT id, descripcion FROM $tabla";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['descripcion']) . "</option>";
    }
}
?>
