<?php
require_once 'funciones.php';
session_start();
verificar_sesion();

if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: ../../inicio_sesion.php');
    exit;
}

/* --------- Filtros (GET) --------- */
$rad_via_filtro               = trim((string)($_GET['rad_via_filtro'] ?? ''));
$numero_identificacion_filtro = trim((string)($_GET['numero_identificacion_filtro'] ?? ''));
$proceso_filtro               = trim((string)($_GET['proceso_filtro'] ?? ''));   // Revision|Aprobado|Rechazado|Subsanacion
$pago_filtro                  = trim((string)($_GET['pago_filtro'] ?? ''));       // '', 'pagado', 'sin_pago'

/* --------- Datos --------- */
$resultados = obtener_reembolsos(
    $numero_identificacion_filtro,
    $proceso_filtro,
    $rad_via_filtro,
    $pago_filtro
);

/* Helpers */
function selected($a,$b){ return $a===$b ? 'selected' : ''; }
function fmt_date_cell($v){
  if ($v instanceof DateTimeInterface) return $v->format('Y-m-d');
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
/** Toma la primera columna disponible de la lista dada */
function col(array $row, array $keys){
  foreach ($keys as $k) { if (array_key_exists($k, $row)) return $row[$k]; }
  return null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Control de Viáticos</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="estilo.css">
<style>
  :root{
    --primary:#0f62fe; --primary-600:#0c4edc;
    --ink:#0b1220; --muted:#667085; --line:#e5e7eb;
    --bg:#f7fafc; --card:#fff;
  }
  body{background:var(--bg);}
  .container{
    max-width:1100px; margin:28px auto; padding:0 16px;
  }

  /* título */
  h1{ text-align:center; color:var(--primary); margin:10px 0 18px; }

  /* barra de búsqueda compacta */
  .search-bar form{
    display:grid; grid-template-columns: 1fr 1fr 220px 180px auto auto;
    gap:10px; align-items:center; margin-bottom:12px;
  }
  .search-bar input[type="text"],
  .search-bar select{
    height:36px; padding:6px 10px;
    border:1px solid var(--line); border-radius:10px; outline:none;
    background:#fff;
  }

  /* botones pequeños */
  .btn, .btn-mini{
    border:0; border-radius:10px; padding:6px 10px;
    cursor:pointer; font-weight:600; color:#fff; background:var(--primary);
  }
  .btn:hover{ background:var(--primary-600); }
  .btn-gray{ background:#6b7280; }
  .btn-mini{ font-size:12px; }
  .btn-mini.secondary{ background:#6b7280; }

  /* tabla estable y legible */
  table{ width:100%; border-collapse:collapse; table-layout:fixed; background:#fff;
         border:1px solid var(--line); border-radius:12px; overflow:hidden; }
  thead th{
    position:sticky; top:0; z-index:1;
    background:var(--primary); color:#fff; text-align:left;
    padding:10px; font-weight:700;
  }
  tbody td{ padding:10px; border-bottom:1px solid #eef2f7; }
  tbody tr:nth-child(odd){ background:#fcfdff; }
  tbody tr:hover{ background:#f5f8ff; }
  td.nowrap, th.nowrap{ white-space:nowrap; }
  td.right{ text-align:right; }
  td.muted{ color:var(--muted); }

  /* Modal */
  .modal-backdrop{position:fixed; inset:0; background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center; z-index:9999; padding:16px;}
  .modal{ width:min(1100px,98vw); height:min(90vh,880px); background:#fff;
    border-radius:12px; overflow:hidden; display:flex; flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.35); }
  .modal-header{ display:flex; align-items:center; justify-content:space-between;
    padding:10px 14px; background:#0f172a; color:#fff; }
  .modal-title{ font-weight:700; }
  .modal-actions{ display:flex; gap:8px; }
  .modal-close{ background:transparent; border:1px solid rgba(255,255,255,.35);
    color:#fff; padding:6px 10px; border-radius:8px; cursor:pointer; }
  .modal-iframe{ width:100%; height:100%; border:0; background:#111; }

  /* Responsive: apilar filtros si hay poco ancho */
  @media (max-width: 920px){
    .search-bar form{ grid-template-columns: 1fr 1fr 1fr 1fr auto; }
  }
  @media (max-width: 680px){
    .search-bar form{ grid-template-columns: 1fr 1fr; }
  }
</style>

</head>
<body>
<div class="container">
  <h1>Control de Viáticos</h1>

  <div class="search-bar">
    <form method="GET">
      <input type="text" name="rad_via_filtro" placeholder="Buscar por Radicado"
             value="<?= htmlspecialchars($rad_via_filtro) ?>">

      <input type="text" name="numero_identificacion_filtro" placeholder="Buscar por documento"
             value="<?= htmlspecialchars($numero_identificacion_filtro) ?>">

      <select name="proceso_filtro" title="Proceso (Departamental)">
        <option value="">Filtrar por proceso</option>
        <option value="Revision"    <?=selected($proceso_filtro,'Revision')?>>Revisión</option>
        <option value="Aprobado"    <?=selected($proceso_filtro,'Aprobado')?>>Aprobado</option>
        <option value="Rechazado"   <?=selected($proceso_filtro,'Rechazado')?>>Rechazado</option>
        <option value="Subsanacion" <?=selected($proceso_filtro,'Subsanacion')?>>Subsanación</option>
      </select>

      <select name="pago_filtro" title="Pago">
        <option value="">Pago: Todos</option>
        <option value="pagado"   <?=selected($pago_filtro,'pagado')?>>Pagado (con comprobante)</option>
        <option value="sin_pago" <?=selected($pago_filtro,'sin_pago')?>>Sin pago (sin comprobante)</option>
      </select>

      <button type="submit" class="btn btn-blue">🔍 Filtrar</button>
      <a href="../../menu.php" class="btn btn-gray">⬅ Atrás</a>
    </form>
  </div>

  <table>
    <thead>
      <tr>
        <th>Radicado</th>
        <th>Fecha de solicitud</th>
        <th>Número de Identificación</th>
        <th>Departamental</th>
        <th>Proceso Nacional</th>
        <th>Fecha de proceso</th>
        <th>Comprobante</th>
        <th>Fecha de pago</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($resultados)): ?>
        <tr><td colspan="8" class="no-results">No se encontraron resultados</td></tr>
      <?php else: ?>
        <?php foreach ($resultados as $row): 
          $radicado      = col($row, ['rad_via','Radicado']);
          $f_solicitud   = col($row, ['fecha_solicitud','Fecha de solicitud']);
          $ident         = col($row, ['numero_identificacion','Número de Identificación']);
          $dep           = col($row, ['apr_departamental','Departamental']);
          $proc_nac      = col($row, ['proceso_nacional','Proceso Nacional']);
          $f_proceso     = col($row, ['fecha_estado','Fecha de proceso']);
          $comprobante   = trim((string) col($row, ['comprobante','comprobante'])); // mismo nombre en ambas
          $f_pago        = col($row, ['fecha_pago','fecha_pago']);
        ?>
          <tr>
            <td><?= htmlspecialchars((string)$radicado) ?></td>
            <td><?= fmt_date_cell($f_solicitud) ?></td>
            <td><?= htmlspecialchars((string)$ident) ?></td>
            <td><?= htmlspecialchars((string)($dep ?? 'En Revisión')) ?></td>
            <td><?= htmlspecialchars((string)($proc_nac ?? '')) ?></td>
            <td class="nowrap">
              <?= fmt_date_cell($f_proceso) ?>
              <?php if ($comprobante !== ''): ?>
                <button type="button" class="btn-mini btn-preview" data-comp="<?= htmlspecialchars($comprobante) ?>" title="Buscar y ver comprobante">🔎 Ver</button>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($comprobante) ?></td>
            <td><?= fmt_date_cell($f_pago) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal de previsualización -->
<div id="backdrop" class="modal-backdrop" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Comprobante</div>
      <div class="modal-actions">
        <a id="downloadLink" class="btn-mini" href="#" target="_blank" rel="noopener">⬇ Descargar</a>
        <button type="button" class="modal-close" id="btnClose">Cerrar ✕</button>
      </div>
    </div>
    <iframe id="previewFrame" class="modal-iframe" src="about:blank"></iframe>
  </div>
</div>

<script>
(function(){
  const backdrop = document.getElementById('backdrop');
  const frame    = document.getElementById('previewFrame');
  const btnClose = document.getElementById('btnClose');
  const dl       = document.getElementById('downloadLink');

  function openPreview(comp){
    const url = 'ver_comprobante.php?comp=' + encodeURIComponent(comp) + '&find=1&inline=1';
    frame.src = url;
    dl.href   = 'ver_comprobante.php?comp=' + encodeURIComponent(comp) + '&find=1';
    backdrop.style.display = 'flex';
    backdrop.setAttribute('aria-hidden', 'false');
  }
  function closePreview(){
    frame.src = 'about:blank';
    backdrop.style.display = 'none';
    backdrop.setAttribute('aria-hidden', 'true');
  }
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('.btn-preview');
    if (btn){
      ev.preventDefault();
      openPreview(btn.dataset.comp);
    }
  });
  btnClose.addEventListener('click', closePreview);
  backdrop.addEventListener('click', (e)=>{ if(e.target === backdrop) closePreview(); });
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closePreview(); });
})();
</script>
</body>
</html>
