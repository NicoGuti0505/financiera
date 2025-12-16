<?php

// Definir la URL base de la aplicación
define('BASE_URL', '/aplicacion/php/');

// Función para generar URLs
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// Conexión a la base de datos (asegúrate de tener tus propias credenciales aquí)
$serverName = "DESKTOP-QD6U0R7";
$connectionInfo = array(
    "Database" => "gestion_terceros",
    "CharacterSet" => "UTF-8",
   // "UID" => "sa",
   // "PWD" => "Fomag2024*",
    "TrustServerCertificate" => true,
    "Encrypt" => true
);
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Configuración de la zona horaria de Colombia
date_default_timezone_set('America/Bogota');

?>