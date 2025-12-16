<?php
// Obtiene el directorio del archivo actual y conecta config.php
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

$sql_tipos = "SELECT id, descripcion_mun FROM municipio";
$stmt_tipos = sqlsrv_query($conn, $sql_tipos);

if ($stmt_tipos) {
    while ($row = sqlsrv_fetch_array($stmt_tipos, SQLSRV_FETCH_ASSOC)) {
        $codigo_dane = str_pad($row['id'], 5, '0', STR_PAD_LEFT);
        $descripcion = htmlspecialchars($row['descripcion_mun']);
        echo "<option value='$codigo_dane'>$descripcion</option>";
    }
}
?>
