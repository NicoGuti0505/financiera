<?php
require_once __DIR__ . '/../../config.php'; 
header('Content-Type: application/json; charset=utf-8');

$params = [];
$where  = "WHERE departamento IS NOT NULL AND LTRIM(RTRIM(departamento)) <> ''";
if (!empty($_GET['region'])) {
  $where .= " AND region = ?";
  $params[] = $_GET['region'];
}

$sql = "SELECT DISTINCT departamento FROM dbo.solicitudes $where ORDER BY departamento";
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];
if ($stmt) {
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = trim($r['departamento']);
  }
}
echo json_encode($data);
