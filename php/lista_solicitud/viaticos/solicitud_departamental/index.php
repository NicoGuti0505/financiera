<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

session_start();
// Verificar sesión
if (!isset($_SESSION['tipo_usuario_id']) && !isset($_SESSION['tipo_usuario_id2'])) {
    header('Location: ../../inicio_sesion.php');
    exit;
}

$usuarios_permitidos = [1, 2, 9];
if (!in_array($_SESSION['tipo_usuario_id'], $usuarios_permitidos) && 
    !in_array($_SESSION['tipo_usuario_id2'], $usuarios_permitidos)) {
    header('Location: ../../../menu.php');
    exit;
}

// Obtener usuarios
$usuarios_sql = "SELECT id, nombre FROM usuario WHERE tipo_usuario_id = 5";
$usuarios_stmt = sqlsrv_query($conn, $usuarios_sql);
$usuarios = [];
while ($row = sqlsrv_fetch_array($usuarios_stmt, SQLSRV_FETCH_ASSOC)) {
    $usuarios[] = $row;
}
sqlsrv_free_stmt($usuarios_stmt);
// === Cargar opciones de selects (una sola vez, antes del form) ===
$deps_sql = "SELECT DISTINCT descripcion_dep AS dep FROM municipio WHERE descripcion_dep IS NOT NULL ORDER BY descripcion_dep";
$deps_stmt = sqlsrv_query($conn, $deps_sql);
$departamentos = [];
while ($r = sqlsrv_fetch_array($deps_stmt, SQLSRV_FETCH_ASSOC)) { $departamentos[] = $r['dep']; }
sqlsrv_free_stmt($deps_stmt);

$muni_sql = "SELECT DISTINCT descripcion_mun AS mun FROM municipio WHERE descripcion_mun IS NOT NULL ORDER BY descripcion_mun";
$muni_stmt = sqlsrv_query($conn, $muni_sql);
$municipios = [];
while ($r = sqlsrv_fetch_array($muni_stmt, SQLSRV_FETCH_ASSOC)) { $municipios[] = $r['mun']; }
sqlsrv_free_stmt($muni_stmt);
// ==== ENDPOINTS AJAX DEPENDIENTES (devuelven JSON y terminan) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');

    if ($_POST['action'] === 'deps_by_region') {
        $region = isset($_POST['region']) ? (int) $_POST['region'] : null;
        $sql = "SELECT DISTINCT descripcion_dep AS dep
                FROM municipio
                WHERE descripcion_dep IS NOT NULL"
             . ($region ? " AND TRY_CAST(region_id AS INT) = ?" : "")
             . " ORDER BY descripcion_dep";
        $params = $region ? [$region] : [];
        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $r['dep'];
            }
            sqlsrv_free_stmt($stmt);
        }
        echo json_encode(['ok' => true, 'departamentos' => $data]);
        exit;
    }

    if ($_POST['action'] === 'mun_by_dep') {
        $dep    = isset($_POST['departamento']) ? trim($_POST['departamento']) : '';
        $region = isset($_POST['region']) ? (int) $_POST['region'] : null;

        $sql = "SELECT DISTINCT descripcion_mun AS mun
                FROM municipio
                WHERE descripcion_mun IS NOT NULL";
        $params = [];

        if ($dep !== '') {
            $sql .= " AND LTRIM(RTRIM(descripcion_dep)) = LTRIM(RTRIM(?))";
            $params[] = $dep;
        }
        if ($region) {
            $sql .= " AND TRY_CAST(region_id AS INT) = ?";
            $params[] = $region;
        }
        $sql .= " ORDER BY descripcion_mun";

        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $r['mun'];
            }
            sqlsrv_free_stmt($stmt);
        }
        echo json_encode(['ok' => true, 'municipios' => $data]);
        exit;
    }
}

$radicado_filtro         = trim($_POST['radicado_filtro'] ?? '');
$identificacion_filtro   = trim($_POST['identificacion_filtro'] ?? '');
$region_filtro           = trim($_POST['region_filtro'] ?? '');
$departamento_filtro     = trim($_POST['departamento_filtro'] ?? '');
$municipio_filtro        = trim($_POST['municipio_filtro'] ?? '');
$usuario_filtro          = $_POST['usuario_filtro'] ?? '';



$sql = "SELECT
    s.radicado,                    -- <-- agrega esto
    s.numero_identificacion_titular,
    s.numero_identificacion,
    s.url_drive,
    s.apr_departamental,
    s.rad_via,
    s.region,
    s.municipio,
    s.departamento
FROM solicitudes s
JOIN (
    SELECT id_usuario, radicado, MIN(fecha_estado) AS primer_evento
    FROM evento_solicitudes
    GROUP BY id_usuario, radicado
) e ON s.radicado = e.radicado
WHERE
    s.estado_pago = 'activado'
    AND s.proceso_tercero = 'viaticos'
    AND (s.apr_departamental IS NULL OR s.apr_departamental = 'Objetado')";


$params = [];

// Filtro por usuario (ya lo tenías)
if (!empty($usuario_filtro)) {
    $sql .= " AND e.id_usuario = ?";
    $params[] = $usuario_filtro;
}

// ✅ Filtro por radicado (rad_via) - busca parcial
if ($radicado_filtro !== '') {
    $sql .= " AND CAST(s.rad_via AS VARCHAR(50)) LIKE ?";
    $params[] = "%{$radicado_filtro}%";
}

// ✅ Filtro por región (numérica)
if ($region_filtro !== '') {
    $sql .= " AND TRY_CAST(s.region AS INT) = ?";
    $params[] = (int)$region_filtro;
}

// ✅ Filtro por departamento (texto exacto)
if ($departamento_filtro !== '') {
    $sql .= " AND s.departamento = ?";
    $params[] = $departamento_filtro;
}

// ✅ Filtro por municipio (texto exacto)
if ($municipio_filtro !== '') {
    $sql .= " AND s.municipio = ?";
    $params[] = $municipio_filtro;
}
// Filtro por identificación titular - parcial
if ($identificacion_filtro !== '') {
    $where .= " AND CAST(s.numero_identificacion_titular AS VARCHAR(50)) LIKE ?";
    $params[] = "%{$identificacion_filtro}%";
}

$sql .= " ORDER BY TRY_CAST(s.rad_via AS INT) ASC";

$stmt = sqlsrv_query($conn, $sql, $params);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Departamental</title>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="script.js" defer></script>
</head>

<body>

<div class="container">
    <h1>Verificación Departamental</h1>
<form method="POST" action="" class="filters-wrapper">
  <div class="filters-grid">

    <div class="filter-group">
      <label for="radicado_filtro">Radicado</label>
      <input type="text"
             class="filter-input"
             name="radicado_filtro"
             id="radicado_filtro"
             value="<?= htmlspecialchars($radicado_filtro ?? '') ?>"
             placeholder="Ej: 12345">
    </div>
    <div class="filter-group">
      <label for="identificacion_filtro">Identificación titular</label>
      <input type="text"
            class="filter-input"
            name="identificacion_filtro"
            id="identificacion_filtro"
            value="<?= htmlspecialchars($identificacion_filtro ?? '') ?>"
            placeholder="Ej: 1234567890">
    </div>

    <div class="filter-group">
      <label for="region_filtro">Región</label>
      <input type="number"
             class="filter-input"
             name="region_filtro"
             id="region_filtro"
             value="<?= htmlspecialchars($region_filtro ?? '') ?>"
             placeholder="Ej: 1">
    </div>

    <div class="filter-group">
      <label for="departamento_filtro">Departamento</label>
      <select name="departamento_filtro" id="departamento_filtro" style="width:100%;">
        <option value="">Departamento...</option>
        <?php foreach ($departamentos as $dep): ?>
          <option value="<?= htmlspecialchars($dep) ?>"
            <?= ($dep === ($departamento_filtro ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars($dep) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="municipio_filtro">Municipio</label>
      <select name="municipio_filtro" id="municipio_filtro" style="width:100%;">
        <option value="">Municipio...</option>
        <?php foreach ($municipios as $mun): ?>
          <option value="<?= htmlspecialchars($mun) ?>"
            <?= ($mun === ($municipio_filtro ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars($mun) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-actions">
      <button type="submit" class="btn btn-blue">🔍 Buscar</button>
      <a href="../../../menu.php" class="btn btn-red">⬅ Regresar</a>
    </div>

    <!-- Badges de filtros activos (visual) -->
    <div class="active-filters" id="activeFilters"></div>
  </div>
</form>


<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Radicado</th>
            <th>Identificación</th>
            <th>URL Drive</th>
            <th>Proceso</th>
            <th id="th-motivo">Motivo(s)</th>
            <th>Actualizar</th>
            <th>Observación</th>

        </tr>
        </thead>
<tbody>
<?php if ($stmt !== false): ?>
  <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
    <?php
      $radicado_via = $row['rad_via'] ?? '';
      $proceso_view = $row['apr_departamental'] ?? null;

      if ($proceso_view === null || $proceso_view === '') {
          $proceso_view = 'Revisión';
      }
      if (strpos((string)$radicado_via, '.') !== false) {
          $proceso_view = 'Legalización';
      }

      // 👇 aquí calculas si debe mostrarse el botón
      $apr_dep_val = strtoupper(trim((string)($row['apr_departamental'] ?? '')));
      $es_objetado = ($apr_dep_val === 'OBJETADO');
    ?>
    <tr>
      <!-- Radicado -->
      <td><?= htmlspecialchars($radicado_via) ?></td>

      <!-- Identificación -->
      <td><?= htmlspecialchars($row['numero_identificacion_titular']) ?></td>

      <!-- Drive -->
      <td>
        <button type="button"
                class="btn btn-green ver-archivos-btn"
                data-url="<?= htmlspecialchars($row['url_drive']) ?>">
          📂 Ver Archivos
        </button>
      </td>

      <!-- Proceso (vista) -->
      <td class="celda-proceso"><?= htmlspecialchars($proceso_view) ?></td>

      <!-- Motivo(s) (tu bloque oculto) -->
      <td class="motivo-td" style="display:none;">
        <div class="motivo-field">
          <label><input type="checkbox" name="motivo[]" value="soportes"> Soportes</label>
          <label><input type="checkbox" name="motivo[]" value="tarifas"> Tarifas</label>
          <label><input type="checkbox" name="motivo[]" value="pertinencia"> Pertinencia</label>
          <label><input type="checkbox" name="motivo[]" value="requisitos administrativos"> Requisitos administrativos</label>
          <label><input type="checkbox" name="motivo[]" value="oportunidad"> Oportunidad</label>
          <label><input type="checkbox" name="motivo[]" value="solicitudes duplicadas"> Solicitudes duplicadas</label>
        </div>
      </td>

      <!-- ACTUALIZAR -->
      <td>
        <form class="proceso-form" data-identificacion="<?= htmlspecialchars($radicado_via) ?>">
          <select class="proceso-select">
            <option value="Revision">Revisión</option>
            <option value="Aprobado">Aprobado</option>
            <option value="Rechazado">Rechazado</option>
          </select>

          <textarea class="observacion-field"
                    style="display:none; width:240px; height:60px; margin-top:6px;"
                    maxlength="500"
                    placeholder="Escriba la observación..."></textarea>

          <button type="button" class="update-individual btn btn-green">🔄 Actualizar</button>
        </form>
      </td>

      <!-- 🔵 Observación (historial) -->
      <td>
        <?php if ($es_objetado): ?>
          <button type="button"
                  class="btn btn-blue ver-observaciones-btn"
                  data-radicado="<?= htmlspecialchars((string)$row['radicado']) ?>">
            📝 Observación
          </button>
        <?php else: ?>
          <span class="text-muted">—</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endwhile; ?>
<?php endif; ?>
</tbody>

    </table>

    <button id="update-massive" class="btn btn-yellow">🔄 Actualizar Todo</button>

</div>

<!-- Mensaje flotante -->
<div id="mensajeActualizacion" class="mensaje-flotante"></div>

<!-- Modal archivos -->
<div id="modalArchivos" class="modal">
    <div class="modal-contenido">
        <span class="cerrar-modal" onclick="cerrarModalArchivos()">&times;</span>
        <h3>📁 Archivos en la carpeta</h3>
        <div id="contenido-archivos">Cargando...</div>
    </div>
</div>
<!-- Modal Observaciones -->
<div id="modalObservaciones" class="modal">
  <div class="modal-contenido" style="max-width:780px;">
    <span class="cerrar-modal" onclick="cerrarModalObs()">&times;</span>
    <h3>📝 Historial de observaciones — Radicado: <span id="obsRad"></span></h3>
    <div id="contenido-observaciones">Cargando…</div>
  </div>
</div>

</body>
</html>

<style>
/* badges simples */
.badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:12px; }
.badge-azul { background:#e6f0ff; color:#0b61d8; }
.badge-verde { background:#eaffea; color:#1d7a1d; }
.badge-roja { background:#ffeaea; color:#b20d0d; }
.muted { color:#777; }

/* tarjetas */
.card-obs { border:1px solid #e4e4e4; border-radius:8px; padding:8px 10px; margin-bottom:10px; background:#fff; }
.card-obs-head { display:flex; justify-content:space-between; align-items:center; }
.card-obs-body { margin-top:6px; }
</style>

<?php
if ($stmt !== false) {
    sqlsrv_free_stmt($stmt);
}
sqlsrv_close($conn);
?>
