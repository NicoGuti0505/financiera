<?php
require_once __DIR__ . '/../../config.php'; 
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT DISTINCT region FROM dbo.solicitudes WHERE region IS NOT NULL AND LTRIM(RTRIM(region)) <> '' ORDER BY region";
$stmt = sqlsrv_query($conn, $sql);
$data = [];
if ($stmt) {
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = trim($r['region']);
  }
}
echo json_encode($data);
