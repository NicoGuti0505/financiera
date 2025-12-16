<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard | Formulario Primaria</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    :root{ --card-radius:.75rem; }
    body{background:#f6f7fb}
    .card{border:0;border-radius:var(--card-radius)}
    .muted{color:#6c757d}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace}

    /* Autocomplete */
    .ac-wrap{position:relative}
    .ac-menu{
      position:absolute; z-index:1050; top:100%; left:0; right:0;
      margin-top:4px; max-height:260px; overflow:auto; background:#fff;
      border:1px solid rgba(0,0,0,.12); border-radius:.5rem;
      box-shadow:0 .5rem 1rem rgba(0,0,0,.08)
    }
    .ac-item{padding:.5rem .75rem; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
    .ac-item:hover,.ac-item.active{background:#0d6efd; color:#fff}
    .ac-empty{padding:.6rem .75rem; color:#6c757d}
    .d-none{display:none!important}

    /* Scroll contenedores de charts */
    .chart-scroll-x{overflow-x:auto; border-radius:.5rem;}
    .chart-scroll-y{overflow-y:auto; max-height:480px; border-radius:.5rem;}
    .chart-scroll-x canvas, .chart-scroll-y canvas{display:block}
    /* añade a tu <style> */
.pie-wrap{
  height: 340px;           /* alto fijo y razonable */
  position: relative;
}
.pie-wrap canvas{
  width: 100% !important;  /* que llene el ancho */
  height: 100% !important; /* que respete el alto de .pie-wrap */
}

  </style>
</head>
<body>
<div class="container-fluid py-3">
  <div class="row g-3">
    <!-- Lado izquierdo: KPI + Filtros -->
    <div class="col-xl-3">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="text-muted small">Total registrados</div>
          <div class="display-6 fw-semibold" id="kpiTotal">0</div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <h5 class="card-title mb-0">Filtros</h5>
            <span class="muted small">Escribe para buscar</span>
          </div>

          <form class="row g-3 align-items-end">
            <div class="col-12">
              <label class="form-label">Departamento (residencia)</label>
              <div class="ac-wrap">
                <input id="dep" class="form-control" placeholder="Ej: Bogotá D.C.">
                <div class="ac-menu d-none" id="depMenu"></div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Municipio (residencia)</label>
              <div class="ac-wrap">
                <input id="mun" class="form-control" placeholder="Ej: Arauca">
                <div class="ac-menu d-none" id="munMenu"></div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">NIT prestador</label>
              <div class="ac-wrap">
                <input id="nit" class="form-control" placeholder="Ej: 900784482">
                <div class="ac-menu d-none" id="nitMenu"></div>
              </div>
            </div>


            <div class="col-12 d-flex gap-2 justify-content-end">
              <button type="button" id="btnLimpiar" class="btn btn-light border">Limpiar</button>
              <button type="button" id="btnAplicar" class="btn btn-primary">Aplicar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Lado derecho: grilla 2×2 de charts -->
    <div class="col-xl-9">
      <div class="row g-3">
        <!-- 1) Departamentos (scroll horizontal) -->
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h6 class="muted mb-2">Registros por departamento</h6>
              <div class="chart-scroll-x" id="wrapChartDep">
                <canvas id="chartDep"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- 2) Municipios (barras horizontales con scroll vertical) -->
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h6 class="muted mb-2">Registros por municipio</h6>
              <div class="chart-scroll-y" id="wrapChartMun">
                <canvas id="chartMun"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- 3) NIT (barras horizontales con scroll vertical) -->
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h6 class="muted mb-2">Registros por NIT (prestador)</h6>
              <div class="chart-scroll-y" id="wrapChartNit">
                <canvas id="chartNit"></canvas>
              </div>
            </div>
          </div>
        </div>

<div class="col-12 col-lg-6" id="cardPie" style="display:none;">
  <div class="card shadow-sm h-100">
    <div class="card-body">
      <h6 class="muted mb-2">Distribución por naturaleza</h6>
      <div class="pie-wrap">
        <canvas id="chartPie"></canvas>
      </div>
    </div>
  </div>
</div>
<script>
/* === Refs === */
const depEl = document.getElementById('dep');
const munEl = document.getElementById('mun');
const nitEl = document.getElementById('nit');
const docEl = document.getElementById('doc');
let chartDep, chartMun, chartNit, chartPie;

/* === Utils === */
async function fetchJSON(url){
  const r = await fetch(url);
  const t = await r.text();
  const j = JSON.parse(t || '{}');
  if (!r.ok) throw new Error(j.error || j.message || ('HTTP ' + r.status));
  return j;
}
function buildParams(){
  const p = new URLSearchParams();
  if (depEl.value) p.set('dep', depEl.value);
  if (munEl.value) p.set('mun', munEl.value);
  if (nitEl.value) {
    const n = nitEl.value.replace(/\D/g,'').trim();
    if (n) p.set('nit', n);
  }
  if (docEl && docEl.value) p.set('doc', docEl.value);
  return p;
}

/* === Charts con scroll === */
/* Barras verticales con scroll horizontal (departamentos) */
function drawBarX(canvas, labels, values){
  const perBar = 70;
  const minW = canvas.parentElement.clientWidth;
  const w = Math.max(minW, labels.length * perBar);
  canvas.width  = w;    // provoca scrollbar horizontal
  canvas.height = 320;

  if (chartDep) chartDep.destroy();
  chartDep = new Chart(canvas, {
    type: 'bar',
    data: { labels, datasets:[{ label:'Registros', data: values }] },
    options: {
      responsive: false, // respetar width/height manuales
      plugins: { legend: { display:false } },
      scales: {
        x: { ticks:{ autoSkip:false, maxRotation:60, minRotation:0 }, grid:{ display:false } },
        y: { beginAtZero:true }
      }
    }
  });
}

/* Barras horizontales con scroll vertical (municipios / NIT) */
function drawBarY(canvas, labels, values, chartRefSetter){
  const rowH = 22;
  const h = Math.max(260, labels.length * rowH);
  canvas.height = h;  // wrapper con overflow-y:auto
  canvas.width  = canvas.parentElement.clientWidth;

  const prev = chartRefSetter('get');
  if (prev) prev.destroy();

  const inst = new Chart(canvas, {
    type: 'bar',
    data: { labels, datasets:[{ label:'Registros', data: values }] },
    options: {
      indexAxis: 'y',
      responsive: false,
      plugins: { legend: { display:false } },
      scales: { x:{ beginAtZero:true }, y:{ grid:{ display:false } } }
    }
  });
  chartRefSetter('set', inst);
}

/* Get/Set para referencias de charts */
function chartSetter(which){
  return (mode, v) => {
    if (which === 'mun'){
      if (mode === 'get') return chartMun;
      chartMun = v;
    } else if (which === 'nit'){
      if (mode === 'get') return chartNit;
      chartNit = v;
    }
  };
}

/* === Autocomplete minimal === */
function makeAutocomplete(input, menu, options=[], { onSelect } = {}){
  let list = [], idx = -1, open = false, deb = null;
  const fold = s => (s ?? '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');

  function normalize(arr){
    list = (arr || []).map(it => {
      if (it && typeof it === 'object'){
        const value = (it.value ?? '').toString();
        const label = (it.label ?? value).toString();
        return { value, label, search: fold(label || value) };
      }
      const value = (it ?? '').toString();
      return { value, label: value, search: fold(value) };
    });
  }
  function render(qText=''){
    const q = fold(qText);
    const filtered = q ? list.filter(it => it.search.includes(q)) : list;
    menu.innerHTML = filtered.length ? '' : '<div class="ac-empty">Sin resultados</div>';
    filtered.slice(0,60).forEach((it,i) => {
      const d = document.createElement('div');
      d.className = 'ac-item' + (i===idx ? ' active' : '');
      d.textContent = it.label;
      d.addEventListener('mousedown', e => { e.preventDefault(); choose(i, filtered); });
      menu.appendChild(d);
    });
    if (!open){ menu.classList.remove('d-none'); open = true; }
  }
  function choose(i, arr){
    const it = arr[i];
    if (it) input.value = it.value;
    close();
    onSelect && onSelect(it?.value, it);
  }
  function close(){ if (open){ menu.classList.add('d-none'); open=false; idx=-1; } }
  function setOptions(arr){
    normalize(arr);
    if (document.activeElement === input && input.value.trim() !== '') render(input.value);
    else close();
  }
  input.addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(() => render(input.value), 120); });
  input.addEventListener('focus', () => { if (input.value.trim() !== '') render(input.value); });
  input.addEventListener('blur', () => setTimeout(close, 100));
  input.addEventListener('keydown', e => {
    const items = [...menu.querySelectorAll('.ac-item')];
    if (['ArrowDown','ArrowUp','Enter','Escape'].includes(e.key)) e.preventDefault();
    if (e.key === 'ArrowDown' && !open){ idx=-1; render(input.value); return; }
    if (e.key === 'ArrowDown'){ idx = Math.min(idx+1,items.length-1); items.forEach((el,i)=>el.classList.toggle('active', i===idx)); }
    if (e.key === 'ArrowUp'){   idx = Math.max(idx-1,0);            items.forEach((el,i)=>el.classList.toggle('active', i===idx)); }
    if (e.key === 'Enter'){ if (idx>=0 && items[idx]) items[idx].dispatchEvent(new Event('mousedown')); else close(); }
    if (e.key === 'Escape'){ close(); }
  });
  function hasOption(v){ return list.some(it => it.value === (v ?? '').toString()); }
  return { setOptions, hasOption };
}

const ac = {
  dep: makeAutocomplete(depEl, document.getElementById('depMenu'), [], { onSelect: async () => { munEl.value=''; await loadFilters(); } }),
  mun: makeAutocomplete(munEl, document.getElementById('munMenu')),
  nit: makeAutocomplete(nitEl, document.getElementById('nitMenu')),
};

/* === Carga de filtros === */
async function loadFilters(){
  const p = buildParams();
  const json = await fetchJSON('data.php?action=filters&' + p.toString());
  const deps = (json?.departamentos || []).map(x => x.value).filter(Boolean);
  const muns = (json?.municipios    || []).map(x => x.value).filter(Boolean);
  const nits = (json?.nits          || [])
                .filter(x => x.value)
                .map(x => ({ value:String(x.value), label: x.label ? `${x.value} — ${x.label}` : String(x.value) }));
  ac.dep.setOptions(deps);
  ac.mun.setOptions(muns);
  ac.nit.setOptions(nits);
}

/* === Donut Naturaleza con agrupación de “Publica” y tooltips % === */
function drawNaturePie(byNat){
  const total = byNat.reduce((s,r)=> s + Number(r.total||0), 0);

  // agrupa categorías que representen <3% en "Publica"
  const main = [], Publica = { naturaleza:'Publica', total:0 };
  byNat.forEach(r => {
    const v = Number(r.total||0);
    if (total && v/total < 0.03) Publica.total += v; else main.push(r);
  });
  if (Publica.total > 0) main.push(Publica);

  const labels = main.map(x => x.naturaleza || 'Sin dato');
  const vals   = main.map(x => Number(x.total||0));
  const colors = labels.map((_,i)=> `hsl(${Math.round(i*360/labels.length)} 70% 55%)`);

  if (chartPie) chartPie.destroy();
  chartPie = new Chart(document.getElementById('chartPie'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: vals, backgroundColor: colors }] },
    options: {
      responsive: true,
      maintainAspectRatio: false, // respeta el alto del wrapper .pie-wrap
      cutout: '55%',
      plugins: {
        legend: { position: 'right' },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const v = ctx.parsed;
              const pct = total ? (v*100/total) : 0;
              return `${ctx.label}: ${v.toLocaleString('es-CO')} (${pct.toFixed(1)}%)`;
            }
          }
        }
      }
    }
  });
}

/* === Carga de resumen (gráficas) === */
async function loadSummary(){
  const p = buildParams();
  const data = await fetchJSON('data.php?action=summary&' + p.toString());

  // KPI
  document.getElementById('kpiTotal').textContent =
    Number(data?.total ?? 0).toLocaleString('es-CO');

  // Departamentos (scroll horizontal)
  const depLabels = (data?.byDepartamento || []).map(r => r.departamento ?? '(Sin depto)');
  const depVals   = (data?.byDepartamento || []).map(r => Number(r.total ?? 0));
  drawBarX(document.getElementById('chartDep'), depLabels, depVals);

  // Municipios (scroll vertical)
  const munLabels = (data?.byMunicipio || [])
                      .map(r => `${r.municipio ?? '(Sin)'} (${r.departamento ?? '-'})`);
  const munVals   = (data?.byMunicipio || []).map(r => Number(r.total ?? 0));
  drawBarY(document.getElementById('chartMun'), munLabels, munVals, chartSetter('mun'));

  // NIT (scroll vertical)
  const nitLabels = (data?.byNit || [])
                      .map(r => r.ips_nombre ? `${r.nit} — ${r.ips_nombre}` : r.nit);
  const nitVals   = (data?.byNit || []).map(r => Number(r.total ?? 0));
  drawBarY(document.getElementById('chartNit'), nitLabels, nitVals, chartSetter('nit'));

  // Pastel por Naturaleza
  const byNat   = Array.isArray(data?.byNaturaleza) ? data.byNaturaleza : [];
  const cardPie = document.getElementById('cardPie');
  if (byNat.length){
    cardPie.style.display = 'block';
    drawNaturePie(byNat);
  } else {
    cardPie.style.display = 'none';
    if (chartPie) { chartPie.destroy(); chartPie = null; }
  }
}

/* === Eventos === */
document.getElementById('btnAplicar').addEventListener('click', async () => {
  await loadFilters();
  await loadSummary();
});
document.getElementById('btnLimpiar').addEventListener('click', async () => {
  depEl.value=''; munEl.value=''; nitEl.value=''; if (docEl) docEl.value='';
  await loadFilters();
  await loadSummary();
});
depEl.addEventListener('blur', async () => {
  if (ac.dep.hasOption(depEl.value)) { munEl.value = ''; await loadFilters(); }
});

/* === Inicio === */
document.addEventListener('DOMContentLoaded', async () => {
  try { await fetchJSON('data.php?action=ping'); } catch(_) {}
  await loadFilters();
  await loadSummary();
});
</script>

</body>
</html>
