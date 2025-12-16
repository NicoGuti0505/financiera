<?php
if (!isset($_GET['carpeta'])) {
    echo "<p style='color:red;'>Carpeta no especificada.</p>";
    exit;
}

$carpeta = $_GET['carpeta'];
$archivos = [];

if (is_dir($carpeta)) {
    $scan = scandir($carpeta);
    foreach ($scan as $archivo) {
        if ($archivo !== '.' && $archivo !== '..') {
            $path = $carpeta . DIRECTORY_SEPARATOR . $archivo;
            if (is_file($path)) {
                // Ajustar ruta visible en navegador
                $url = str_replace("E:/FINANCIERA", "/financiera", $path);
                $url = str_replace("\\", "/", $url); // compatible en Windows
                $archivos[] = [
                    'nombre' => $archivo,
                    'url' => $url
                ];
            }
        }
    }
}

if (empty($archivos)) {
    echo "<p>No se encontraron archivos.</p>";
} else {
    // Estilos embebidos
    echo '<style>
        .archivo-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-family: "Segoe UI", sans-serif;
        }
        .archivo-item {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }
        .archivo-item:hover {
            background-color: #e9ecef;
        }
        .archivo-icono {
            font-size: 1.3rem;
            color: #28a745;
            margin-right: 10px;
        }
        .archivo-nombre {
            color: #212529;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
        }
    </style>';

    // Contenido HTML
    echo "<div class='archivo-lista'>";
    foreach ($archivos as $a) {
        echo "<div class='archivo-item'>
                <span class='archivo-icono'>📎</span>
                <a href='{$a['url']}' download class='archivo-nombre'>{$a['nombre']}</a>
              </div>";
    }
    echo "</div>";
}
?>
