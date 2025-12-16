<?php
require_once __DIR__ . '/../../config.php'; 
header('Content-Type: application/json; charset=utf-8');

$params = [];
$where  = "WHERE municipio IS NOT NULL AND LTRIM(RTRIM(municipio)) <> ''";
if (!empty($_GET['region'])) {
  $where .= " AND region = ?";
  $params[] = $_GET['region'];
}
if (!empty($_GET['departamento'])) {
  $where .= " AND departamento = ?";
  $params[] = $_GET['departamento'];
}

$sql = "SELECT DISTINCT municipio FROM dbo.solicitudes $where ORDER BY municipio";
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];
if ($stmt) {
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = trim($r['municipio']);
  }
}
echo json_encode($data);
