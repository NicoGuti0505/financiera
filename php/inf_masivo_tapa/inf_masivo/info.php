<?php

//Ruta
$Ruta = dirname(__FILE__);
// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', $Ruta);
// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

// Encontrar el texto '\php\' y dividir la ruta
$partes = explode('\\php\\', $Ruta);
// Cambiar las barras invertidas por barras normales
$RutaBase = str_replace('\\', '/', $partes[1]) . '/';

$fechaActual = date('Y-m-d');
$fechaAnterior = date('Y-m-d', strtotime('-1 day'));

require_once 'functions.php';

if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: " . url('inicio_sesion.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'ajax_handler.php';
    exit;
}
$grupor_id = isset($_SESSION['grupor_id']) ? (int)$_SESSION['grupor_id'] : null;


$queryRegiones = "SELECT rf.id, rf.descripcion
FROM region_fomag rf 
JOIN grupo_region gr ON rf.grupor_id = gr.id
WHERE gr.id = ?";
$params = [$grupor_id];
$resultadoRegiones = sqlsrv_query($conn, $queryRegiones, $params);

if (!isset($_SESSION['tipo_usuario_id']) || 
    ($_SESSION['tipo_usuario_id'] != 1 && $_SESSION['tipo_usuario_id'] != 2 && $_SESSION['tipo_usuario_id'] != 3 && $_SESSION['tipo_usuario_id'] != 4)) {
    header('Location: ../../menu.php');
    exit;
}
?>
<script>
        let inactivityTime =30 * 60 * 1000; // 30 minutos en milisegundos
    let inactivityTimer;

    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            window.location.href = "../../logout.php"; // Redirige al menú
        }, inactivityTime);
    }

    // Detectar eventos de actividad del usuario
    document.addEventListener("mousemove", resetTimer);
    document.addEventListener("keypress", resetTimer);
    document.addEventListener("click", resetTimer);
    document.addEventListener("scroll", resetTimer);
    document.addEventListener("keydown", resetTimer);

    // Iniciar el temporizador
    resetTimer();
</script>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MASIVO</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="<?php echo url('inf_masivo_tapa/estilos/inf_masivo.css'); ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="container">
        <h1 class="text-2xl font-bold mb-6 text-center">INFORMACIÓN PARA MASIVO</h1>
        
        <div class="flex items-center space-x-3 mb-4">
    <select name="regionf_id" id="regionf_id" class="border p-2 rounded text-base w-60">
        <option value="" selected>Seleccione una región</option>
        <?php while ($row = sqlsrv_fetch_array($resultadoRegiones, SQLSRV_FETCH_ASSOC)): ?>
            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['descripcion'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endwhile; ?>
    </select>

    <input type="date" id="start_date" class="border p-2 rounded text-base w-40" value="<?php echo $fechaAnterior; ?>">
    <input type="date" id="end_date" class="border p-2 rounded text-base w-40" value="<?php echo $fechaActual; ?>">

    <button type="button" id="btnBuscar" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-base">
        Buscar
    </button>
</div>



        
        <form id="formularioInfTapa" class="space-y-4">
            <div id="camposInfTapa"></div>
            <div id="mensajeExistentes" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                <p>Se encontraron registros existentes. Por favor, verifique la información.</p>
            </div>
            
            <div class="flex space-x-4">
                <button type="button" id="guardarDatos" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Guardar</button>
                <button type="button" id="registrosValidados" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 hidden">Registros Validados</button>
                <a href="<?php echo url('menu.php'); ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block">Atrás</a>
            </div>
        </form>
    </div>
    
    <script>
        const MENU_URL = '<?php echo url("menu.php"); ?>';
    </script>
    <script src="<?php echo url($RutaBase.'js/inf_masivo.js'); ?>"></script>
</body>
</html>