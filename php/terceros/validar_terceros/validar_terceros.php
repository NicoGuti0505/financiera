<?php

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

session_start();

if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: " . url('inicio_sesion.php'));
    exit();
}

// Manejar la solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'validacion/val_registros.php';
    exit;
}

// Obtener los tipos de contrato de la base de datos
$sql = "SELECT * FROM tipo_contrato";
$stmt = sqlsrv_query($conn, $sql);
$tipos_contrato = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tipos_contrato[] = $row;
}


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
    <title>VALIDAR TERCERO</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="estilos/validar_terceros.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>VALIDAR TERCERO</h1>
        <form id="validacionForm" method="POST">
            <div id="camposValidacion"></div>
            <div id="mensajeExistentes" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                <p>Se encontraron registros existentes !ESTOS NO SE GUARDARAN!. Por favor, verifique la información.</p>
            </div>
            <div class="form-actions">
                <button type="button" id="agregarCampos">Agregar</button>
                <button type="submit" name="guardar_validacion">Guardar Validaciones</button>
                <button type="button" id="registrosValidados" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 hidden">Registros Validados</button>
                <a href="<?php echo url('menu.php'); ?>" class="btn-back">Atrás</a>
            </div>
        </form>
    </div>

    <script> var tipos_contrato = <?= json_encode($tipos_contrato) ?>;
             
             var inf_tercero = 'busquedas/inf_tercero.php'; 
             var val_registros = 'validacion/val_registros.php';
             var guard_validaciones = 'importe/guard_validaciones.php';
             var formulario = '<?= url('terceros/crear_terceros/crear_terceros.php') ?>'; 
    </script>
    <script src="<?= url('script/campos_fomulario.js') ?>"></script>
    <script src="<?= url('script/formato_texto.js') ?>"></script>
    <script src="script/validar_tercero.js"></script>
</body>
</html>