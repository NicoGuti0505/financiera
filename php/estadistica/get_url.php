<?php
session_start();

if (!isset($_SESSION['tipo_usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// URL privada de Power BI
$powerbi_url = "https://app.powerbi.com/view?r=eyJrIjoiMmI0MzFkOTgtOWYxOS00NmI4LWI0NWItYWRiZDFmYWNmYWFhIiwidCI6IjRmYTk5MmI4LWFkYWItNDA5Ny04M2VmLTIyZWNlMzMzYWEyZCIsImMiOjR9";

// Codifica la URL para ocultarla un poco más
$encoded_url = base64_encode(rawurlencode($powerbi_url));

header('Content-Type: application/json');
echo json_encode(['url' => $encoded_url]);
?>
