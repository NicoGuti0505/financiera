<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

$sql = "SELECT cedula, radicado, departamento, MIN(ruta_archivo) as ruta_archivo
        FROM gestion_terceros.dbo.archivo_viaticos
        GROUP BY cedula, radicado, departamento";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) die(print_r(sqlsrv_errors(), true));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Archivos Viáticos</title>
    <style>
        body { font-family: Arial; background: #f7f9fb; margin: 0; padding: 0; }
        h2 { text-align: center; margin-top: 1rem; }
        .top-bar {
            width: 90%;
            margin: 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-back {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-back:hover { background-color: #c82333; }
        #filtro {
            width: 300px;
            padding: 6px 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        table {
            width: 90%; margin: 10px auto;
            border-collapse: collapse; background: #fff;
        }
        th, td {
            border: 1px solid #ccc; padding: 10px; text-align: center;
        }
        th {
            background-color: #007bff; color: #fff;
        }
        .btn {
            padding: 6px 12px;
            background-color: #28a745;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn:hover { background-color: #218838; }
        .modal {
            display: none; position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); justify-content: center; align-items: center;
        }
        .modal-content {
            background: #fff; padding: 20px; border-radius: 10px;
            width: 80%; max-height: 80vh; overflow-y: auto;
        }
        .close {
            float: right; font-size: 1.5rem; cursor: pointer; color: red;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="javascript:history.back()" class="btn-back">← Regresar al menú</a>
    <input type="text" id="filtro" placeholder="Buscar por cédula o radicado..." onkeyup="filtrarTabla()">
</div>

<h2>Archivos por Cédula</h2>

<table id="tabla-archivos">
    <thead>
        <tr>
            <th>Radicado</th>
            <th>Cédula</th>
            <th>Departamento</th>
            <th>Archivos</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) :
            $carpeta = "E:/FINANCIERA/pagos/" . $row['departamento'] . "/" . $row['cedula'];
        ?>
            <tr>
                <td><?= $row['radicado'] ?></td>
                <td><?= $row['cedula'] ?></td>
                <td><?= $row['departamento'] ?></td>
                <td><button class="btn" onclick="verArchivos('<?= addslashes($carpeta) ?>')">📁 Ver Archivos</button></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<!-- Modal -->
<div id="modalArchivos" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">×</span>
        <h3>Archivos encontrados:</h3>
        <div id="listaArchivos"></div>
    </div>
</div>

<script>
function verArchivos(carpeta) {
    fetch('listar_archivos.php?carpeta=' + encodeURIComponent(carpeta))
        .then(res => res.text())
        .then(html => {
            document.getElementById('listaArchivos').innerHTML = html;
            document.getElementById('modalArchivos').style.display = 'flex';
        });
}

function cerrarModal() {
    document.getElementById('modalArchivos').style.display = 'none';
}

// Filtro por cédula o radicado
function filtrarTabla() {
    let input = document.getElementById("filtro").value.toLowerCase();
    let filas = document.querySelectorAll("#tabla-archivos tbody tr");

    filas.forEach(fila => {
        const radicado = fila.cells[0].innerText.toLowerCase();
        const cedula   = fila.cells[1].innerText.toLowerCase();
        const coincide = radicado.includes(input) || cedula.includes(input);
        fila.style.display = coincide ? "" : "none";
    });
}
</script>

</body>
</html>
