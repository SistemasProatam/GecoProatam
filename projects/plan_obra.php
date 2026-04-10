<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
include(__DIR__ . "/../conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {

            case 'obtener_proyectos':
                $stmt = $conn->prepare(
                    "SELECT id, nombre_proyecto AS nombre, numero_contrato
                     FROM proyectos ORDER BY nombre_proyecto ASC");
                $stmt->execute();
                echo json_encode(['success'=>true,
                    'proyectos'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
                break;

            case 'obtener_obras':
                $pid = (int)($_POST['proyecto_id'] ?? 0);
                if ($pid <= 0) { echo json_encode(['success'=>false,'error'=>'proyecto_id invalido']); exit; }
                $stmt = $conn->prepare(
                    "SELECT id, nombre_obra AS nombre FROM obras WHERE proyecto_id=? ORDER BY nombre_obra ASC");
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                echo json_encode(['success'=>true,
                    'obras'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
                break;

            case 'obtener_catalogos':
                $obra_id = (int)($_POST['obra_id'] ?? 0);
                if ($obra_id <= 0) { echo json_encode(['success'=>false,'error'=>'obra_id invalido']); exit; }
                $stmt = $conn->prepare(
                    "SELECT c.id, c.nombre_catalogo, COUNT(co.id) AS total_conceptos
                     FROM catalogos c
                     LEFT JOIN conceptos co ON co.catalogo_id = c.id
                     WHERE c.obra_id = ?
                     GROUP BY c.id ORDER BY c.fecha_creacion DESC");
                $stmt->bind_param("i", $obra_id);
                $stmt->execute();
                echo json_encode(['success'=>true,
                    'catalogos'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
                break;

            case 'obtener_conceptos_plan':
                $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
                $obra_id     = (int)($_POST['obra_id']     ?? 0);
                if ($catalogo_id <= 0 || $obra_id <= 0) {
                    echo json_encode(['success'=>false,'error'=>'Parametros invalidos']); exit;
                }
                $sql = "SELECT
                            c.id,
                            c.codigo_concepto,
                            c.nombre_concepto,
                            c.unidad_medida,
                            c.categoria,
                            c.subcategoria,
                            c.numero_original,
                            COALESCE(po.es_referencia,   0) AS es_referencia,
                            COALESCE(po.precio_unitario, 0) AS precio_unitario,
                            COALESCE(po.importe,         0) AS importe,
                            po.fecha_inicio,
                            po.fecha_fin,
                            COALESCE(po.terminado,       0) AS terminado,
                            po.fecha_terminado,
                            po.notas,
                            sc.id                           AS subcontrato_id,
                            COALESCE(sc.total_estimado,  0) AS sc_total_estimado,
                            COALESCE(sc.monto_real,      0) AS sc_monto_real,
                            p.id                            AS proveedor_id,
                            COALESCE(p.nombre,          '') AS proveedor_nombre
                        FROM conceptos c
                        LEFT JOIN plan_obra po
                               ON po.concepto_id = c.id AND po.obra_id = ?
                        LEFT JOIN subcontrato_conceptos scc
                               ON scc.concepto_id = c.id
                        LEFT JOIN subcontratos sc
                               ON sc.id = scc.subcontrato_id AND sc.obra_id = ?
                        LEFT JOIN proveedores p
                               ON p.id = sc.proveedor_id
                        WHERE c.catalogo_id = ?
                        ORDER BY
                            CASE WHEN p.id IS NULL THEN 1 ELSE 0 END ASC,
                            COALESCE(p.nombre,'ZZZZ') ASC,
                            CASE c.categoria
                                WHEN 'I'    THEN 1  WHEN 'II'   THEN 2
                                WHEN 'III'  THEN 3  WHEN 'IV'   THEN 4
                                WHEN 'V'    THEN 5  WHEN 'VI'   THEN 6
                                WHEN 'VII'  THEN 7  WHEN 'VIII' THEN 8
                                WHEN 'IX'   THEN 9  WHEN 'X'    THEN 10
                                WHEN '' THEN 999 ELSE 100
                            END ASC,
                            CASE
                                WHEN c.subcategoria REGEXP '^[IVX]+\\.[0-9]+$'
                                    THEN CAST(SUBSTRING_INDEX(c.subcategoria,'.',-1) AS UNSIGNED)
                                WHEN c.subcategoria IS NULL OR c.subcategoria='' THEN 999
                                ELSE 100
                            END ASC,
                            CASE
                                WHEN c.numero_original REGEXP '^[0-9]+$'
                                    THEN CAST(c.numero_original AS UNSIGNED)
                                ELSE 9999
                            END ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $obra_id, $obra_id, $catalogo_id);
                $stmt->execute();
                echo json_encode(['success'=>true,
                    'conceptos'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
                break;

            case 'guardar_plan_concepto':
                $obra_id         = (int)($_POST['obra_id']         ?? 0);
                $catalogo_id     = (int)($_POST['catalogo_id']     ?? 0);
                $concepto_id     = (int)($_POST['concepto_id']     ?? 0);
                $es_referencia   = (int)($_POST['es_referencia']   ?? 0);
                $precio_unitario = (float)($_POST['precio_unitario'] ?? 0);
                $importe         = (float)($_POST['importe']         ?? 0);
                $fecha_inicio    = $_POST['fecha_inicio'] ?: null;
                $fecha_fin       = $_POST['fecha_fin']    ?: null;
                $notas           = $_POST['notas']        ?? '';
                if ($obra_id <= 0 || $concepto_id <= 0) {
                    echo json_encode(['success'=>false,'error'=>'Parametros invalidos']); exit;
                }
                $sql = "INSERT INTO plan_obra
                            (obra_id,catalogo_id,concepto_id,es_referencia,
                             precio_unitario,importe,fecha_inicio,fecha_fin,notas)
                        VALUES (?,?,?,?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE
                            es_referencia   = VALUES(es_referencia),
                            precio_unitario = VALUES(precio_unitario),
                            importe         = VALUES(importe),
                            fecha_inicio    = VALUES(fecha_inicio),
                            fecha_fin       = VALUES(fecha_fin),
                            notas           = VALUES(notas)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiiiddsss",
                    $obra_id,$catalogo_id,$concepto_id,$es_referencia,
                    $precio_unitario,$importe,$fecha_inicio,$fecha_fin,$notas);
                echo json_encode(['success'=>$stmt->execute(),'error'=>$stmt->error?:null]);
                break;

            case 'marcar_terminado':
                $obra_id         = (int)($_POST['obra_id']     ?? 0);
                $concepto_id     = (int)($_POST['concepto_id'] ?? 0);
                $terminado       = (int)($_POST['terminado']   ?? 0);
                $fecha_terminado = $_POST['fecha_terminado']  ?: null;
                if ($obra_id <= 0 || $concepto_id <= 0) {
                    echo json_encode(['success'=>false,'error'=>'Parametros invalidos']); exit;
                }
                if (!$terminado) $fecha_terminado = null;
                $sql = "INSERT INTO plan_obra
                            (obra_id,catalogo_id,concepto_id,terminado,fecha_terminado)
                        VALUES (?,
                            (SELECT catalogo_id FROM conceptos WHERE id=? LIMIT 1),
                            ?,?,?)
                        ON DUPLICATE KEY UPDATE
                            terminado       = VALUES(terminado),
                            fecha_terminado = VALUES(fecha_terminado)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiiis",
                    $obra_id,$concepto_id,$concepto_id,$terminado,$fecha_terminado);
                if ($stmt->execute()) {
                    $dias_diff = null; $mensaje = '';
                    if ($terminado && $fecha_terminado) {
                        $st2 = $conn->prepare(
                            "SELECT fecha_fin FROM plan_obra WHERE obra_id=? AND concepto_id=?");
                        $st2->bind_param("ii",$obra_id,$concepto_id);
                        $st2->execute();
                        $row = $st2->get_result()->fetch_assoc();
                        if ($row && $row['fecha_fin']) {
                            $fin  = new DateTime($row['fecha_fin']);
                            $real = new DateTime($fecha_terminado);
                            $dias_diff = (int)$fin->diff($real)->days * ($real > $fin ? 1 : -1);
                            if      ($dias_diff < 0) $mensaje = abs($dias_diff).' dia(s) ANTES de lo planeado';
                            elseif  ($dias_diff > 0) $mensaje = $dias_diff.' dia(s) DESPUES de lo planeado';
                            else                     $mensaje = 'Terminado exactamente en la fecha planeada';
                        }
                    }
                    echo json_encode(['success'=>true,'dias_diff'=>$dias_diff,'mensaje'=>$mensaje]);
                } else {
                    echo json_encode(['success'=>false,'error'=>$stmt->error]);
                }
                break;

            case 'toggle_referencia':
                $obra_id     = (int)($_POST['obra_id']     ?? 0);
                $concepto_id = (int)($_POST['concepto_id'] ?? 0);
                $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
                $valor       = (int)($_POST['valor']       ?? 0);
                if ($obra_id <= 0 || $concepto_id <= 0) {
                    echo json_encode(['success'=>false,'error'=>'Parametros invalidos']); exit;
                }
                $sql = "INSERT INTO plan_obra (obra_id,catalogo_id,concepto_id,es_referencia)
                        VALUES (?,?,?,?)
                        ON DUPLICATE KEY UPDATE es_referencia = VALUES(es_referencia)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiii",$obra_id,$catalogo_id,$concepto_id,$valor);
                echo json_encode(['success'=>$stmt->execute()]);
                break;

            default:
                echo json_encode(['success'=>false,'error'=>'Accion no valida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'Error: '.$e->getMessage()]);
    }
    $conn->close();
    exit;
}

$obra_id     = (int)($_GET['obra_id'] ?? $_SESSION['obra_id'] ?? 0);
$obra_nombre = '';
if ($obra_id > 0) {
    $stmt = $conn->prepare("SELECT nombre FROM obras WHERE id=?");
    $stmt->bind_param("i",$obra_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $obra_nombre = $r['nombre'] ?? "Obra #".$obra_id;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plan de Obra</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/plan_obra.css">
<link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">

<style>
.prov-sec { margin-bottom: 28px; }
.prov-hdr {
  display:flex; align-items:center; gap:12px; padding:13px 18px;
  background:linear-gradient(90deg,rgba(63,117,85,.11),transparent);
  border-left:4px solid var(--secondary-color);
  border-radius:0 var(--r) var(--r) 0; margin-bottom:10px;
  cursor:pointer; user-select:none; transition:background var(--transition);
}
.prov-hdr:hover { background:linear-gradient(90deg,rgba(63,117,85,.18),transparent); }
.prov-hdr.sin {
  background:linear-gradient(90deg,rgba(244,185,66,.09),transparent);
  border-left-color:#f4b942;
}
.prov-hdr.sin:hover { background:linear-gradient(90deg,rgba(244,185,66,.16),transparent); }
.prov-avatar {
  width:38px; height:38px; border-radius:9px; background:rgba(63,117,85,.13);
  display:flex; align-items:center; justify-content:center;
  color:var(--secondary-color); font-size:17px; flex-shrink:0;
}
.prov-avatar.sin { background:rgba(244,185,66,.13); color:#b47800; }
.prov-info  { flex:1; min-width:0; }
.prov-name  { font-size:.92rem; font-weight:700; color:var(--primary-color); }
.prov-meta  { display:flex; flex-wrap:wrap; gap:14px; margin-top:3px; }
.prov-meta-item { font-size:.72rem; color:var(--ink2); display:flex; align-items:center; gap:4px; }
.prov-meta-item strong { color:var(--primary-color); font-weight:600; }
.prov-right { display:flex; align-items:center; gap:10px; flex-shrink:0; }
.badge-prov {
  font-size:.66rem; font-weight:700; padding:2px 9px; border-radius:20px;
  background:rgba(63,117,85,.1); color:var(--secondary-color);
  border:1px solid rgba(63,117,85,.2); white-space:nowrap;
}
.badge-prov.sin { background:rgba(244,185,66,.1); color:#b47800; border-color:rgba(244,185,66,.25); }
.prov-prog-wrap { width:72px; height:5px; background:rgba(0,0,0,.08); border-radius:99px; overflow:hidden; }
.prov-prog-fill {
  height:100%; border-radius:99px;
  background:linear-gradient(90deg,var(--secondary-color),#5fbe8a);
  transition:width .6s cubic-bezier(.16,1,.3,1);
}
.prov-pct { font-size:.72rem; font-weight:700; color:var(--secondary-color); font-family:'Outfit',sans-serif; min-width:32px; text-align:right; }
.prov-chevron { color:var(--ink2); font-size:.8rem; transition:transform .25s ease; flex-shrink:0; }
.prov-hdr.collapsed .prov-chevron { transform:rotate(-90deg); }
.prov-body { padding-left:12px; }
</style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . "". BASE_URL ."/includes/navbar.php"; ?>

<div class="hero-section">
  <div class="hero-content">
    <div class="breadcrumb-custom">
      <a href="#"><i class="bi bi-house-door"></i> Inicio</a>
      <i class="bi bi-chevron-right" style="font-size:.7rem"></i>
      <span>Plan de Obra</span>
    </div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div class="hero-title">Plan de Obra</div>
        <div class="hero-sub" id="obra-sub">
          <?= $obra_id > 0 ? htmlspecialchars($obra_nombre) : 'Selecciona proyecto y obra para comenzar' ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="content-wrapper">
<div class="main" style="padding:0;max-width:100%">

  <div class="ctrl-bar">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
      <div style="display:flex;flex-direction:column;gap:5px;min-width:180px;flex:1;">
        <label class="lbl">Proyecto</label>
        <select id="sel-proyecto" class="inp" onchange="cargarObras()">
          <option value="">Selecciona proyecto</option>
        </select>
      </div>
      <div style="display:flex;flex-direction:column;gap:5px;min-width:180px;flex:1;">
        <label class="lbl">Obra</label>
        <select id="sel-obra" class="inp" onchange="cargarCatalogo()" disabled>
          <option value="">Primero selecciona proyecto</option>
        </select>
      </div>
      <div style="display:flex;flex-direction:column;gap:5px;min-width:200px;flex:2;">
        <label class="lbl">Catalogo</label>
        <div style="position:relative;">
          <select id="sel-cat" class="inp" disabled>
            <option value="">Se carga automaticamente</option>
          </select>
          <span id="cat-loader" style="display:none;position:absolute;right:32px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--ink2);">
            <i class="bi bi-arrow-clockwise" style="animation:spin 1s linear infinite;display:inline-block"></i>
          </span>
        </div>
      </div>
      <div style="padding-bottom:1px;">
        <button id="btn-cargar" class="btn-p" onclick="cargarPlan()" disabled style="opacity:.5">
          <i class="bi bi-play-fill"></i> Cargar plan
        </button>
      </div>
    </div>
    <div style="border-top:1px solid rgba(0,0,0,.07);padding-top:.9rem;display:flex;gap:1.4rem;flex-wrap:wrap;align-items:center;">
      <span class="lbl" style="margin:0;flex-shrink:0;">Filtros</span>
      <label class="sw-label" id="lbl-ref">
        <span class="sw-track" id="track-ref"><span class="sw-thumb" id="thumb-ref"></span></span>
        Solo referencias <strong>80%</strong>
      </label>
      <label class="sw-label" id="lbl-pend">
        <span class="sw-track" id="track-pend"><span class="sw-thumb" id="thumb-pend"></span></span>
        Solo <strong>pendientes</strong>
      </label>
      <input type="checkbox" id="chk-ref"  style="display:none">
      <input type="checkbox" id="chk-pend" style="display:none">
    </div>
  </div>

  <div class="stats fade-up" id="stats" style="display:none">
    <div class="sc c-sky">   <div class="sc-lbl">Total conceptos</div>   <div class="sc-val sky"    id="s-tot">0</div>  <i class="bi bi-stack sc-icon"></i></div>
    <div class="sc c-gold">  <div class="sc-lbl">Importe total</div>     <div class="sc-val gold"   id="s-imp">$0</div> <i class="bi bi-currency-dollar sc-icon"></i></div>
    <div class="sc c-green"> <div class="sc-lbl">Importe 80% (ref.)</div><div class="sc-val green"  id="s-imp-r">$0</div><i class="bi bi-stars sc-icon"></i></div>
    <div class="sc c-purple"><div class="sc-lbl">Conceptos 80%</div>     <div class="sc-val purple" id="s-cnt-r">0</div><i class="bi bi-pin-angle sc-icon"></i></div>
    <div class="sc c-green"> <div class="sc-lbl">Terminados</div>        <div class="sc-val green"  id="s-done">0</div> <i class="bi bi-check-circle sc-icon"></i></div>
    <div class="sc c-gold">  <div class="sc-lbl">Pendientes</div>        <div class="sc-val gold"   id="s-pend">0</div> <i class="bi bi-hourglass-split sc-icon"></i></div>
  </div>

  <div id="body">
    <div class="es">
      <i class="bi bi-layers"></i>
      <div style="font-size:1rem;font-weight:600;color:var(--primary-color)">Sin datos cargados</div>
      <div style="font-size:.84rem;margin-top:6px">Selecciona proyecto, obra y presiona <strong>Cargar Plan</strong></div>
    </div>
  </div>

</div>
</div>

<div class="mo" id="modal">
  <div class="mb">
    <button class="mc" onclick="cerrarModal()"><i class="bi bi-x-lg"></i></button>
    <div class="mb-title" id="m-title">Editar concepto</div>
    <input type="hidden" id="m-cid">
    <input type="hidden" id="m-oid">
    <input type="hidden" id="m-catid">
    <div class="fg">
      <div class="fgi">
        <div class="lbl">Precio Unitario</div>
        <input type="number" id="m-pu" class="inp" placeholder="0.00" step="0.01" min="0">
      </div>
      <div class="fgi">
        <div class="lbl">Monto de contrato</div>
        <input type="number" id="m-imp" class="inp" placeholder="0.00" step="0.01" min="0">
      </div>
      <div class="fgi">
        <div class="lbl">Fecha Inicio</div>
        <input type="date" id="m-fi" class="inp">
      </div>
      <div class="fgi">
        <div class="lbl">Fecha Fin Planeada</div>
        <input type="date" id="m-ff" class="inp">
      </div>
      <div class="fgi full">
        <div class="lbl">Notas</div>
        <textarea id="m-notas" class="inp" rows="2" placeholder="Observaciones..."></textarea>
      </div>
      <div class="fgi full term-block">
        <div style="display:flex;align-items:center;gap:9px;margin-bottom:9px">
          <input type="checkbox" id="m-done" class="chk-d" onchange="toggleFechaTerm()">
          <label for="m-done" style="font-weight:700;cursor:pointer;font-size:.88rem;color:var(--secondary-color)">
            <i class="bi bi-check2-circle me-1"></i>Marcar como terminado
          </label>
        </div>
        <div id="m-ft-wrap" style="display:none">
          <div class="lbl">Fecha real de terminacion</div>
          <input type="date" id="m-ft" class="inp" style="max-width:200px">
          <div id="m-diff" style="margin-top:8px;font-size:.82rem"></div>
        </div>
      </div>
    </div>
    <div class="fa">
      <button class="btn-s" onclick="cerrarModal()">Cancelar</button>
      <button class="btn-p" onclick="guardar()"><i class="bi bi-check2"></i> Guardar</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
var CS     = [];
var obraId = 0;
var catId  = 0;

var fmt  = function(n){ return new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(n||0); };
var fmtD = function(d){ return d ? new Date(d+'T00:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '-'; };
var esc  = function(s){ return (s||'').replace(/[^a-z0-9]/gi,'_'); };
var $    = function(id){ return document.getElementById(id); };

function toast(msg, type) {
  type = type || 'info';
  var t = $('toast');
  t.textContent = msg;
  t.className = 'show ' + type;
  clearTimeout(t._t);
  t._t = setTimeout(function(){ t.classList.remove('show'); }, 3500);
}

function ajax(data, cb) {
  var fd = new FormData();
  Object.keys(data).forEach(function(k){ fd.append(k, data[k] != null ? data[k] : ''); });
  fetch('plan_obra.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(r){ cb(null, r); })
    .catch(function(e){ cb({success:false, error:'Error de red: '+e.message}); });
}

document.addEventListener('DOMContentLoaded', function(){
  inicializarToggles();
  cargarProyectos();
});

function cargarProyectos() {
  ajax({action:'obtener_proyectos'}, function(err, r){
    if (!r || !r.success || !r.proyectos || !r.proyectos.length){
      toast('No se encontraron proyectos','error'); return;
    }
    var sel = $('sel-proyecto');
    r.proyectos.forEach(function(p){
      var o = document.createElement('option');
      o.value = p.id;
      o.textContent = p.nombre + (p.numero_contrato ? ' - '+p.numero_contrato : '');
      sel.appendChild(o);
    });
  });
}

function inicializarToggles() {
  ['ref','pend'].forEach(function(id){
    var chk   = $('chk-'+id);
    var track = $('track-'+id);
    $('lbl-'+id).addEventListener('click', function(){
      chk.checked = !chk.checked;
      track.classList.toggle('on', chk.checked);
      if (CS.length) renderPlan();
    });
  });
}

function cargarObras() {
  var pid     = $('sel-proyecto').value;
  var selObra = $('sel-obra');
  selObra.innerHTML = '<option value="">Cargando...</option>';
  selObra.disabled  = true;
  resetCatalogo();
  if (!pid) {
    selObra.innerHTML = '<option value="">Primero selecciona proyecto</option>';
    return;
  }
  ajax({action:'obtener_obras', proyecto_id:pid}, function(err, r){
    selObra.innerHTML = '<option value="">Selecciona obra</option>';
    if (r && r.success && r.obras && r.obras.length) {
      r.obras.forEach(function(o){
        var opt = document.createElement('option');
        opt.value = o.id; opt.textContent = o.nombre;
        selObra.appendChild(opt);
      });
      selObra.disabled = false;
    } else {
      selObra.innerHTML = '<option value="">Sin obras disponibles</option>';
      toast('No se encontraron obras','error');
    }
  });
}

function cargarCatalogo() {
  var oId    = parseInt($('sel-obra').value) || 0;
  var selCat = $('sel-cat');
  var loader = $('cat-loader');
  var btn    = $('btn-cargar');
  resetCatalogo();
  if (!oId) return;
  selCat.innerHTML     = '<option value="">Cargando...</option>';
  loader.style.display = 'inline';
  ajax({action:'obtener_catalogos', obra_id:oId}, function(err, r){
    loader.style.display = 'none';
    if (r && r.success && r.catalogos && r.catalogos.length) {
      selCat.innerHTML = '';
      r.catalogos.forEach(function(c){
        var opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.nombre_catalogo + ' (' + c.total_conceptos + ' conceptos)';
        selCat.appendChild(opt);
      });
      selCat.disabled   = false;
      btn.disabled      = false;
      btn.style.opacity = '1';
      obraId = oId;
      $('obra-sub').textContent = $('sel-obra').options[$('sel-obra').selectedIndex].text;
    } else {
      selCat.innerHTML = '<option value="">Sin catalogos para esta obra</option>';
      toast('No se encontraron catalogos','error');
    }
  });
}

function resetCatalogo() {
  $('sel-cat').innerHTML    = '<option value="">Se carga automaticamente</option>';
  $('sel-cat').disabled     = true;
  $('btn-cargar').disabled  = true;
  $('btn-cargar').style.opacity = '.5';
  obraId = 0; catId = 0;
}

function cargarPlan() {
  obraId = parseInt($('sel-obra').value) || 0;
  catId  = parseInt($('sel-cat').value)  || 0;
  if (!obraId || !catId) { toast('Selecciona obra y catalogo','error'); return; }
  $('body').innerHTML = '<div class="ld"><i class="bi bi-arrow-clockwise"></i>Cargando conceptos...</div>';
  ajax({action:'obtener_conceptos_plan', obra_id:obraId, catalogo_id:catId}, function(err, r){
    if (!r || !r.success) { toast((r && r.error)||'Error al cargar','error'); return; }
    CS = r.conceptos;
    renderPlan();
    actualizarStats();
  });
}

function renderPlan() {
  var soloRef  = $('chk-ref').checked;
  var soloPend = $('chk-pend').checked;
  var lista    = CS.filter(function(c){
    return (!soloRef  || +c.es_referencia) && (!soloPend || !+c.terminado);
  });

  if (!lista.length) {
    $('body').innerHTML = '<div class="es"><i class="bi bi-inbox"></i>Sin conceptos que mostrar</div>';
    return;
  }

  /* Agrupar por proveedor */
  var provMap = {};
  lista.forEach(function(c){
    var key = c.proveedor_id ? String(c.proveedor_id) : '__sin__';
    if (!provMap[key]) {
      provMap[key] = {
        key:               key,
        nombre:            c.proveedor_nombre || null,
        proveedor_id:      c.proveedor_id     || null,
        sc_total_estimado: parseFloat(c.sc_total_estimado || 0),
        sc_monto_real:     parseFloat(c.sc_monto_real     || 0),
        conceptos:         []
      };
    }
    provMap[key].conceptos.push(c);
  });

  /* Ordenar: con nombre primero A-Z, sin asignar al final */
  var provList = Object.values(provMap).sort(function(a, b){
    if (!a.nombre && b.nombre)  return  1;
    if (a.nombre  && !b.nombre) return -1;
    return (a.nombre||'').localeCompare(b.nombre||'', 'es');
  });

  var html = '';

  provList.forEach(function(prov){
    var esSin   = !prov.nombre;
    var provKey = 'prov-' + esc(prov.key);
    var pct     = prov.sc_total_estimado > 0
      ? Math.min(Math.round(prov.sc_monto_real / prov.sc_total_estimado * 100), 100)
      : 0;

    /* Cabecera proveedor */
    html += '<div class="prov-sec fade-up">';
    html += '<div class="prov-hdr' + (esSin ? ' sin' : '') + '" onclick="togProv(\'' + provKey + '\',this)">';
    html += '<div class="prov-avatar' + (esSin ? ' sin' : '') + '">';
    html += '<i class="bi bi-' + (esSin ? 'exclamation-triangle' : 'person-gear') + '"></i>';
    html += '</div>';
    html += '<div class="prov-info">';
    html += '<div class="prov-name">' + (esSin ? 'Sin proveedor asignado' : prov.nombre) + '</div>';
    html += '<div class="prov-meta">';

    if (!esSin) {
      html += '<span class="prov-meta-item">';
      html += '<i class="bi bi-currency-dollar"></i>';
      html += 'Monto de contrato:&nbsp;<strong>' + fmt(prov.sc_total_estimado) + '</strong>';
      html += '</span>';
      html += '<span class="prov-meta-item">';
      html += '<i class="bi bi-check2-circle"></i>';
      html += 'Real pagado:&nbsp;<strong>' + fmt(prov.sc_monto_real) + '</strong>';
      html += '</span>';
    } else {
      html += '<span class="prov-meta-item" style="color:#b47800">';
      html += '<i class="bi bi-info-circle"></i>';
      html += prov.conceptos.length + ' conceptos pendientes de asignar a un subcontrato';
      html += '</span>';
    }

    html += '</div>';
    html += '</div>';
    html += '<div class="prov-right">';
    html += '<span class="badge-prov' + (esSin ? ' sin' : '') + '">' + prov.conceptos.length + ' conceptos</span>';

    html += '<i class="bi bi-chevron-down prov-chevron"></i>';
    html += '</div>';
    html += '</div>';

    /* Cuerpo colapsable del proveedor */
    html += '<div class="coll prov-body" id="' + provKey + '" style="max-height:9999px">';

    /* Agrupar por categoria → subcategoria */
    var cats = {};
    prov.conceptos.forEach(function(c){
      var cat = c.categoria    || 'Sin categoria';
      var sub = c.subcategoria || 'Sin subcategoria';
      if (!cats[cat])      cats[cat]      = {};
      if (!cats[cat][sub]) cats[cat][sub] = [];
      cats[cat][sub].push(c);
    });

    Object.keys(cats).forEach(function(cat){
      var subs   = cats[cat];
      var totCat = 0;
      Object.keys(subs).forEach(function(sub){
        subs[sub].forEach(function(c){ totCat += parseFloat(c.importe||0); });
      });
      var catKey = 'cat-' + esc(prov.key) + '-' + esc(cat);

      html += '<div class="cat-sec">';
      html += '<div class="cat-hdr" onclick="tog(\'' + catKey + '\')">';
      html += '<span class="cat-badge">' + cat + '</span>';
      html += '<span class="cat-name">' + cat + '</span>';
      html += '<span class="cat-total">' + fmt(totCat) + '&nbsp;<i class="bi bi-chevron-down"></i></span>';
      html += '</div>';
      html += '<div class="coll" id="' + catKey + '" style="max-height:9999px">';

      Object.keys(subs).forEach(function(sub){
        var items  = subs[sub];
        items.sort(function(a,b){ return parseFloat(b.importe||0) - parseFloat(a.importe||0); });
        var totSub = items.reduce(function(s,c){ return s + parseFloat(c.importe||0); }, 0);
        var subKey = 'sub-' + esc(prov.key) + '-' + esc(cat) + '-' + esc(sub);

        html += '<div class="sub-sec">';
        html += '<div class="sub-hdr" onclick="tog(\'' + subKey + '\')">';
        html += '<span class="sub-badge">' + sub + '</span>';
        html += '<span class="sub-name">' + sub + '</span>';
        html += '<span class="sub-total">' + fmt(totSub) + ' &middot; ' + items.length + ' conceptos&nbsp;<i class="bi bi-chevron-down"></i></span>';
        html += '</div>';
        html += '<div class="coll" id="' + subKey + '" style="max-height:9999px">';
        html += '<div class="tbl-wrap"><table class="pt">';
        html += '<thead><tr>';
        html += '<th style="width:38px" title="Referencia 80%">80%</th>';
        html += '<th>Codigo</th><th>Concepto</th><th>U.M.</th>';
        html += '<th class="tr">P. Unitario</th>';
        html += '<th class="tr">Importe</th>';
        html += '<th>Inicio</th><th>Fin Plan.</th>';
        html += '<th style="width:34px" title="Terminado">&#10004;</th>';
        html += '<th>Fecha Term.</th><th>Diferencia</th>';
        html += '<th style="width:34px"></th>';
        html += '</tr></thead><tbody>';

        items.forEach(function(c){
          var ref  = +c.es_referencia;
          var done = +c.terminado;
          var cls  = done ? 'is-done' : (ref ? 'is-ref' : '');

          html += '<tr class="' + cls + '" id="r-' + c.id + '">';

          html += '<td class="tc ns">';
          html += '<input type="checkbox" class="chk-r" title="Referencia 80%"';
          html += ref ? ' checked' : '';
          html += ' onchange="tglRef(' + c.id + ',this.checked)">';
          html += ref ? '<span class="b80">80</span>' : '';
          html += '</td>';

          html += '<td><span style="font-size:.72rem;font-weight:600;color:var(--ink2);font-family:Outfit,sans-serif">';
          html += c.codigo_concepto;
          html += '</span></td>';

          html += '<td style="max-width:240px">';
          html += '<div style="font-weight:600;color:var(--primary-color)">' + c.nombre_concepto + '</div>';
          if (c.notas) {
            html += '<div style="font-size:.72rem;color:var(--ink2);margin-top:2px">' + c.notas + '</div>';
          }
          html += '</td>';

          html += '<td style="color:var(--ink2)">' + (c.unidad_medida||'-') + '</td>';

          html += '<td class="tr" style="font-family:Outfit,sans-serif">';
          html += c.precio_unitario > 0 ? fmt(c.precio_unitario) : '-';
          html += '</td>';

          html += '<td class="tr" style="font-weight:800;color:var(--gold);font-family:Outfit,sans-serif">';
          html += c.importe > 0 ? fmt(c.importe) : '-';
          html += '</td>';

          html += '<td style="color:var(--ink2);white-space:nowrap;font-size:.78rem">' + fmtD(c.fecha_inicio) + '</td>';
          html += '<td style="color:var(--ink2);white-space:nowrap;font-size:.78rem">' + fmtD(c.fecha_fin) + '</td>';

          html += '<td class="tc ns">';
          html += '<input type="checkbox" class="chk-d" title="Terminado"';
          html += done ? ' checked' : '';
          html += ' onchange="tglDone(' + c.id + ',this.checked)">';
          html += '</td>';

          html += '<td style="white-space:nowrap" class="ns">';
          html += c.fecha_terminado ? fmtD(c.fecha_terminado) : '-';
          html += '</td>';

          html += '<td class="ns">' + diffBadge(c) + '</td>';

          html += '<td class="ns">';
          html += '<button class="btn-ed" onclick="abrirModal(' + c.id + ')" title="Editar">';
          html += '<i class="bi bi-pencil"></i></button>';
          html += '</td>';

          html += '</tr>';
        });

        html += '</tbody></table></div>';
        html += '</div>';
        html += '</div>';
      });

      html += '</div>';
      html += '</div>';
    });

    html += '</div>';
    html += '</div>';
  });

  $('body').innerHTML = html;
}

function diffBadge(c) {
  if (!+c.terminado || !c.fecha_terminado || !c.fecha_fin) return '-';
  var fin  = new Date(c.fecha_fin       + 'T00:00:00');
  var real = new Date(c.fecha_terminado + 'T00:00:00');
  var d    = Math.round((real - fin) / 86400000);
  if (d < 0) return '<span class="b-ok"><i class="bi bi-arrow-up-circle"></i> ' + Math.abs(d) + ' dias antes</span>';
  if (d > 0) return '<span class="b-late"><i class="bi bi-exclamation-circle"></i> ' + d + ' dias despues</span>';
  return '<span class="b-exact"><i class="bi bi-check-circle"></i> En fecha</span>';
}

function actualizarStats() {
  var imp  = CS.reduce(function(s,c){ return s + parseFloat(c.importe||0); }, 0);
  var refs = CS.filter(function(c){ return +c.es_referencia; });
  var impR = refs.reduce(function(s,c){ return s + parseFloat(c.importe||0); }, 0);
  var done = CS.filter(function(c){ return +c.terminado; }).length;
  var pct  = imp > 0 ? (impR / imp * 100) : 0;
  $('s-tot').textContent   = CS.length;
  $('s-imp').textContent   = fmt(imp);
  $('s-imp-r').textContent = fmt(impR);
  $('s-cnt-r').textContent = refs.length;
  $('s-done').textContent  = done;
  $('s-pend').textContent  = CS.length - done;
  $('p-pct').textContent   = pct.toFixed(1) + '%';
  $('p-fill').style.width  = Math.min(pct, 100) + '%';
  $('stats').style.display  = '';
}

function tglRef(id, val) {
  var c = CS.find(function(x){ return x.id == id; });
  if (!c) return;
  var prev = c.es_referencia;
  c.es_referencia = val ? 1 : 0;
  ajax({action:'toggle_referencia', obra_id:obraId, catalogo_id:catId, concepto_id:id, valor:val?1:0},
    function(err, r){
      if (!r || !r.success) {
        c.es_referencia = prev;
        toast('Error al guardar referencia','error');
      } else {
        toast(val ? 'Marcado como referencia 80%' : 'Desmarcado de referencia','success');
        actualizarStats();
      }
      var row = $('r-'+id);
      if (row) { if (val) row.classList.add('is-ref'); else row.classList.remove('is-ref'); }
    });
}

function tglDone(id, val) {
  if (!val) { guardarTerminado(id, 0, null); return; }
  var c = CS.find(function(x){ return x.id == id; });
  if (!c) return;
  llenarModal(c);
  $('m-done').checked = true;
  toggleFechaTerm();
  if (!$('m-ft').value) $('m-ft').value = new Date().toISOString().split('T')[0];
  $('modal').classList.add('open');
}

function guardarTerminado(id, val, fecha) {
  ajax({action:'marcar_terminado', obra_id:obraId, concepto_id:id, terminado:val, fecha_terminado:fecha||''},
    function(err, r){
      if (r && r.success) {
        var c = CS.find(function(x){ return x.id == id; });
        if (c) { c.terminado = val; c.fecha_terminado = fecha; }
        if (r.mensaje) toast(r.mensaje, val ? (r.dias_diff < 0 ? 'success' : 'info') : 'info');
        else toast(val ? 'Marcado como terminado' : 'Desmarcado','success');
        actualizarStats();
        renderPlan();
      } else {
        toast((r && r.error)||'Error','error');
        var chk = document.querySelector('#r-'+id+' .chk-d');
        if (chk) chk.checked = !val;
      }
    });
}

function llenarModal(c) {
  $('m-cid').value        = c.id;
  $('m-oid').value        = obraId;
  $('m-catid').value      = catId;
  $('m-title').textContent = c.nombre_concepto;
  $('m-pu').value         = c.precio_unitario || '';
  $('m-imp').value        = c.importe         || '';
  $('m-fi').value         = c.fecha_inicio    || '';
  $('m-ff').value         = c.fecha_fin       || '';
  $('m-notas').value      = c.notas           || '';
  $('m-done').checked     = !!+c.terminado;
  $('m-ft').value         = c.fecha_terminado || '';
  $('m-diff').textContent = '';
  toggleFechaTerm();
}

function abrirModal(id) {
  var c = CS.find(function(x){ return x.id == id; });
  if (!c) return;
  llenarModal(c);
  $('modal').classList.add('open');
}

function cerrarModal()     { $('modal').classList.remove('open'); }
function toggleFechaTerm() { $('m-ft-wrap').style.display = $('m-done').checked ? '' : 'none'; }

function guardar() {
  var id    = $('m-cid').value;
  var done  = $('m-done').checked;
  var fecha = done ? $('m-ft').value : null;
  var c     = CS.find(function(x){ return x.id == id; });
  var datos = {
    action:          'guardar_plan_concepto',
    obra_id:         $('m-oid').value,
    catalogo_id:     $('m-catid').value,
    concepto_id:     id,
    precio_unitario: $('m-pu').value  || 0,
    importe:         $('m-imp').value || 0,
    fecha_inicio:    $('m-fi').value  || '',
    fecha_fin:       $('m-ff').value  || '',
    notas:           $('m-notas').value,
    es_referencia:   c ? c.es_referencia : 0
  };
  ajax(datos, function(err, r){
    if (!r || !r.success) { toast((r && r.error)||'Error','error'); return; }
    ajax({action:'marcar_terminado', obra_id:datos.obra_id, concepto_id:id,
          terminado:done?1:0, fecha_terminado:fecha||''},
      function(err2, r2){
        if (c) {
          c.precio_unitario = parseFloat(datos.precio_unitario);
          c.importe         = parseFloat(datos.importe);
          c.fecha_inicio    = datos.fecha_inicio || null;
          c.fecha_fin       = datos.fecha_fin    || null;
          c.notas           = datos.notas;
          c.terminado       = done ? 1 : 0;
          c.fecha_terminado = fecha;
        }
        if (r2 && r2.success && r2.mensaje) toast(r2.mensaje, r2.dias_diff < 0 ? 'success' : 'info');
        else toast('Concepto guardado correctamente','success');
        cerrarModal();
        actualizarStats();
        renderPlan();
      });
  });
}

function tog(id) {
  var el = $(id);
  if (!el) return;
  if (el.classList.contains('cls')) {
    el.style.maxHeight = el.scrollHeight + 'px';
    el.classList.remove('cls');
  } else {
    el.style.maxHeight = el.scrollHeight + 'px';
    requestAnimationFrame(function(){ el.classList.add('cls'); });
  }
}

function togProv(id, hdrEl) {
  var el = $(id);
  if (!el) return;
  if (el.classList.contains('cls')) {
    el.style.maxHeight = el.scrollHeight + 'px';
    el.classList.remove('cls');
    hdrEl.classList.remove('collapsed');
  } else {
    el.style.maxHeight = el.scrollHeight + 'px';
    requestAnimationFrame(function(){
      el.classList.add('cls');
      hdrEl.classList.add('collapsed');
    });
  }
}
</script>

</body>
</html>

