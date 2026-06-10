<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';
  try {
    switch ($action) {
      case 'obtener_proyectos':
        $stmt = $conn->prepare("SELECT id, nombre_proyecto AS nombre FROM proyectos ORDER BY nombre_proyecto ASC");
        $stmt->execute();
        echo json_encode(['success' => true, 'proyectos' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
      case 'obtener_obras':
        $pid = (int)$_POST['proyecto_id'];
        $stmt = $conn->prepare("SELECT id, nombre_obra AS nombre FROM obras WHERE proyecto_id=? ORDER BY nombre_obra ASC");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        echo json_encode(['success' => true, 'obras' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
      case 'obtener_catalogos':
        $oid = (int)$_POST['obra_id'];
        $stmt = $conn->prepare("SELECT id, nombre_catalogo FROM catalogos WHERE obra_id=? ORDER BY fecha_creacion DESC");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        echo json_encode(['success' => true, 'catalogos' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
      case 'obtener_conceptos_plan':
        $cat_id = (int)$_POST['catalogo_id'];
        $oid    = (int)$_POST['obra_id'];
        $sql = "SELECT
                            c.id, c.codigo_concepto, c.nombre_concepto, c.unidad_medida,
                            n.clave AS nodo_clave, n.titulo AS nodo_titulo,
                            (SELECT nx.clave FROM concepto_nodos nx WHERE nx.catalogo_id=c.catalogo_id AND nx.nivel=1 AND (n.sort_path LIKE CONCAT(nx.sort_path, '.%') OR n.id=nx.id) LIMIT 1) AS cat_clave,
                            (SELECT nx.titulo FROM concepto_nodos nx WHERE nx.catalogo_id=c.catalogo_id AND nx.nivel=1 AND (n.sort_path LIKE CONCAT(nx.sort_path, '.%') OR n.id=nx.id) LIMIT 1) AS cat_titulo,
                            COALESCE(po.es_referencia, 0) AS es_referencia,
                            COALESCE(po.precio_unitario, c.precio_unitario, 0) AS precio_unitario,
                            COALESCE(po.importe, c.importe, 0) AS importe,
                            COALESCE(po.fecha_inicio, c.fecha_inicio) AS fecha_inicio,
                            COALESCE(po.fecha_fin, c.fecha_fin) AS fecha_fin,
                            COALESCE(po.terminado, 0) AS terminado,
                            po.fecha_terminado, po.notas, po.dias_diferencia,
                            COALESCE(p.nombre, '') AS proveedor_nombre
                        FROM conceptos c
                        LEFT JOIN concepto_nodos n ON c.nodo_id = n.id
                        LEFT JOIN plan_obra po ON po.concepto_id = c.id AND po.obra_id = ?
                        LEFT JOIN subcontrato_conceptos scc ON scc.concepto_id = c.id
                        LEFT JOIN subcontratos sc ON sc.id = scc.subcontrato_id AND sc.obra_id = ?
                        LEFT JOIN proveedores p ON p.id = sc.proveedor_id
                        WHERE c.catalogo_id = ?
                        ORDER BY COALESCE(p.nombre, 'ZZZZ') ASC, n.sort_path ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $oid, $oid, $cat_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'conceptos' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
      case 'guardar_plan_concepto':
        $oid = (int)$_POST['obra_id'];
        $cat = (int)$_POST['catalogo_id'];
        $cid = (int)$_POST['concepto_id'];
        $pu = (float)$_POST['precio_unitario'];
        $imp = (float)$_POST['importe'];
        $fi = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $ff = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $not = $_POST['notas'] ?? '';
        $sql = "INSERT INTO plan_obra (obra_id, catalogo_id, concepto_id, precio_unitario, importe, fecha_inicio, fecha_fin, notas, fecha_creacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE
                        precio_unitario=VALUES(precio_unitario), importe=VALUES(importe),
                        fecha_inicio=VALUES(fecha_inicio), fecha_fin=VALUES(fecha_fin),
                        notas=VALUES(notas), fecha_actualizacion=NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiddss", $oid, $cat, $cid, $pu, $imp, $fi, $ff, $not);
        echo json_encode(['success' => $stmt->execute()]);
        break;
      case 'marcar_terminado':
        $oid = (int)$_POST['oid'];
        $cid = (int)$_POST['cid'];
        $done = (int)$_POST['val'];
        $ft = !empty($_POST['fecha']) ? $_POST['fecha'] : null;

        // Buscar fecha_fin para diferencia
        $st_p = $conn->prepare("SELECT c.catalogo_id, COALESCE(po.fecha_fin, c.fecha_fin) as f_fin FROM conceptos c LEFT JOIN plan_obra po ON po.concepto_id=c.id AND po.obra_id=? WHERE c.id=?");
        $st_p->bind_param("ii", $oid, $cid);
        $st_p->execute();
        $data = $st_p->get_result()->fetch_assoc();
        $diff = null;
        if ($done && $ft && $data['f_fin']) {
          $d1 = new DateTime($data['f_fin']);
          $d2 = new DateTime($ft);
          $diff = (int)$d1->diff($d2)->days * ($d2 > $d1 ? 1 : -1);
        }
        $sql = "INSERT INTO plan_obra (obra_id, catalogo_id, concepto_id, terminado, fecha_terminado, dias_diferencia, fecha_creacion)
                        VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE
                        terminado=VALUES(terminado), fecha_terminado=VALUES(fecha_terminado),
                        dias_diferencia=VALUES(dias_diferencia), fecha_actualizacion=NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiisi", $oid, $data['catalogo_id'], $cid, $done, $ft, $diff);
        echo json_encode(['success' => $stmt->execute(), 'diff' => $diff]);
        break;
      case 'toggle_referencia':
        $oid = (int)$_POST['oid'];
        $cid = (int)$_POST['cid'];
        $val = (int)$_POST['val'];
        $st = $conn->prepare("SELECT catalogo_id FROM conceptos WHERE id=?");
        $st->bind_param("i", $cid);
        $st->execute();
        $cat = $st->get_result()->fetch_assoc()['catalogo_id'];
        $sql = "INSERT INTO plan_obra (obra_id, catalogo_id, concepto_id, es_referencia, fecha_creacion)
                        VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE es_referencia=VALUES(es_referencia)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $oid, $cat, $cid, $val);
        echo json_encode(['success' => $stmt->execute()]);
        break;
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

$obra_id = (int)($_GET['obra_id'] ?? $_SESSION['obra_id'] ?? 0);
$obra_nombre = '';
if ($obra_id > 0) {
  $stmt = $conn->prepare("SELECT nombre FROM obras WHERE id=?");
  $stmt->bind_param("i", $obra_id);
  $stmt->execute();
  $obra_nombre = $stmt->get_result()->fetch_assoc()['nombre'] ?? "";
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/plan_obra.css?v=1.1">
<title>Plan de Obra | GECO PROATAM</title>


<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <span class="text-muted">Proyectos</span>
        <span class="separator">›</span>
        <span>Plan de Obra</span>
      </nav>
      <h1 class="orders-page-title">Plan de Obra<?= !empty($obra_nombre) ? ' — ' . htmlspecialchars($obra_nombre) : '' ?></h1>
    </div>
  </div>

  <!-- Filters Card -->
  <div class="orders-card mb-4">
    <div class="orders-filter-bar">
      <div class="row g-3 w-100 align-items-end">
        <div class="col-md-3">
          <label class="lbl mb-2" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase;">Proyecto</label>
          <select id="sel-pro" class="form-select" onchange="getObras()"></select>
        </div>
        <div class="col-md-3">
          <label class="lbl mb-2" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase;">Obra</label>
          <select id="sel-obra" class="form-select" disabled onchange="getCats()"></select>
        </div>
        <div class="col-md-4">
          <label class="lbl mb-2" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase;">Catálogo</label>
          <select id="sel-cat" class="form-select" disabled></select>
        </div>
        <div class="col-md-2">
          <button class="btn-geco-primary w-100 justify-content-center" onclick="cargarPlan()">
            <i class="fa-solid fa-magnifying-glass"></i> Cargar Plan
          </button>
        </div>
      </div>

      <div class="w-100 border-top mt-3 pt-3">
        <div class="d-flex gap-4">
          <div class="form-check d-flex align-items-center gap-2">
            <input class="form-check-input cursor-pointer" type="checkbox" id="f-ref" onchange="render()" style="width: 1.05rem; height: 1.05rem;">
            <label class="form-check-label lbl mb-0 cursor-pointer text-dark" for="f-ref" style="text-transform: none; font-size: 0.82rem; font-weight: 600;">
              Solo 80% (Referencia)
            </label>
          </div>
          <div class="form-check d-flex align-items-center gap-2">
            <input class="form-check-input cursor-pointer" type="checkbox" id="f-pend" onchange="render()" style="width: 1.05rem; height: 1.05rem;">
            <label class="form-check-label lbl mb-0 cursor-pointer text-dark" for="f-pend" style="text-transform: none; font-size: 0.82rem; font-weight: 600;">
              Solo Pendientes
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Dashboard Grid -->
  <div class="orders-card mb-4 p-4" id="stats" style="display:none">
    <div class="kpi-grid kpi-grid--5">
      <div class="kpi-card kpi-card--blue">
        <i class="fa-solid fa-clipboard-list kpi-icon"></i>
        <div class="kpi-card__label">Conceptos</div>
        <div id="s-tot" class="kpi-card__value">0</div>
      </div>
      <div class="kpi-card kpi-card--green">
        <i class="fa-solid fa-money-bill-wave kpi-icon"></i>
        <div class="kpi-card__label">Importe</div>
        <div id="s-imp" class="kpi-card__value">$0.00</div>
      </div>
      <div class="kpi-card kpi-card--amber">
        <i class="fa-solid fa-percent kpi-icon"></i>
        <div class="kpi-card__label">Referencia (80%)</div>
        <div id="s-ref" class="kpi-card__value">$0.00</div>
      </div>
      <div class="kpi-card kpi-card--purple">
        <i class="fa-solid fa-circle-check kpi-icon"></i>
        <div class="kpi-card__label">Terminados</div>
        <div id="s-done" class="kpi-card__value">0</div>
      </div>
      <div class="kpi-card kpi-card--slate">
        <i class="fa-solid fa-chart-line kpi-icon"></i>
        <div class="kpi-card__label">Avance Real</div>
        <div id="s-pct" class="kpi-card__value">0%</div>
      </div>
    </div>
  </div>

  <!-- Plan Body -->
  <div id="plan-body">
    <div class="orders-card">
      <div class="orders-empty-state">
        <i class="fa-solid fa-magnifying-glass"></i>
        <p>Selecciona los filtros de búsqueda para comenzar a visualizar el plan.</p>
      </div>
    </div>
  </div>

</div>

<!-- Custom Glassmorphism Modal -->
<div class="mo" id="modal">
  <div class="mb border-0">
    <button class="mc" onclick="closeM()"><i class="fa-solid fa-xmark" style="font-size: 1.1rem; color: var(--gray-400);"></i></button>

    <h4 id="m-name" class="fw-bold mb-4 text-dark" style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; line-height: 1.4; padding-right: 2rem;"></h4>

    <input type="hidden" id="m-id">

    <div class="row g-3">
      <div class="col-md-6">
        <label class="lbl" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">P. Unitario</label>
        <input type="number" id="m-pu" class="form-control" style="border: 1.5px solid var(--gray-200); border-radius: 8px; font-size: 0.85rem; padding: 0.55rem 0.75rem;">
      </div>
      <div class="col-md-6">
        <label class="lbl" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Importe</label>
        <input type="number" id="m-imp" class="form-control" style="border: 1.5px solid var(--gray-200); border-radius: 8px; font-size: 0.85rem; padding: 0.55rem 0.75rem;">
      </div>
      <div class="col-md-6">
        <label class="lbl" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Inicio</label>
        <input type="date" id="m-fi" class="form-control" style="border: 1.5px solid var(--gray-200); border-radius: 8px; font-size: 0.85rem; padding: 0.55rem 0.75rem;">
      </div>
      <div class="col-md-6">
        <label class="lbl" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Fin Plan</label>
        <input type="date" id="m-ff" class="form-control" style="border: 1.5px solid var(--gray-200); border-radius: 8px; font-size: 0.85rem; padding: 0.55rem 0.75rem;">
      </div>
      <div class="col-12">
        <label class="lbl" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Notas</label>
        <textarea id="m-not" class="form-control" rows="2" style="border: 1.5px solid var(--gray-200); border-radius: 8px; font-size: 0.85rem; padding: 0.55rem 0.75rem;"></textarea>
      </div>

      <div class="col-12 mt-4 p-3 rounded" style="background: rgba(248, 250, 252, 0.8); border: 1.5px solid var(--gray-100); border-radius: 10px;">
        <div class="form-check d-flex align-items-center gap-2 m-0">
          <input class="form-check-input cursor-pointer" type="checkbox" id="m-done" onchange="toggleFT()" style="width: 1.1rem; height: 1.1rem;">
          <label class="form-check-label fw-bold text-dark cursor-pointer mb-0" for="m-done" style="font-size: 0.85rem; user-select: none;">Marcar como terminado</label>
        </div>
        <div id="m-ft-box" class="mt-3" style="display:none">
          <label class="lbl" style="font-size: 0.72rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Fecha Real de Término</label>
          <input type="date" id="m-ft" class="form-control" style="border: 1.5px solid var(--gray-200); border-radius: 8px; font-size: 0.85rem; padding: 0.55rem 0.75rem;">
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4 pt-2">
      <button class="btn btn-light" style="border-radius: 10px; font-size: 0.85rem; font-weight: 600; padding: 0.6rem 1.25rem; color: var(--gray-600); border: 1px solid var(--gray-200);" onclick="closeM()">Cerrar</button>
      <button class="btn-geco-primary" style="padding: 0.6rem 1.5rem;" onclick="save()">Guardar Cambios</button>
    </div>
  </div>
</div>

<script>
  let CS = [];
  const $ = id => document.getElementById(id);
  const fmt = n => new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN'
  }).format(n || 0);
  const fmtD = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  }) : '-';

  async function api(data) {
    const fd = new FormData();
    for (let k in data) fd.append(k, data[k]);
    const r = await fetch('plan_obra.php', {
      method: 'POST',
      body: fd
    });
    return await r.json();
  }

  async function getObras() {
    const r = await api({
      action: 'obtener_obras',
      proyecto_id: $('sel-pro').value
    });
    $('sel-obra').innerHTML = '<option value="">Obra...</option>' + r.obras.map(o => `<option value="${o.id}">${o.nombre}</option>`).join('');
    $('sel-obra').disabled = false;
  }

  async function getCats() {
    const r = await api({
      action: 'obtener_catalogos',
      obra_id: $('sel-obra').value
    });
    $('sel-cat').innerHTML = r.catalogos.map(c => `<option value="${c.id}">${c.nombre_catalogo}</option>`).join('');
    $('sel-cat').disabled = false;
  }

  async function cargarPlan() {
    const r = await api({
      action: 'obtener_conceptos_plan',
      obra_id: $('sel-obra').value,
      catalogo_id: $('sel-cat').value
    });
    if (r.success) {
      CS = r.conceptos;
      render();
      updateStats();
      $('stats').style.display = 'grid';
    }
  }

  function render() {
    const fRef = $('f-ref').checked,
      fPend = $('f-pend').checked;
    const list = CS.filter(c => (!fRef || +c.es_referencia) && (!fPend || !+c.terminado));

    const tree = {};
    list.forEach(c => {
      const p = c.proveedor_nombre || 'Sin Proveedor';
      const ct = (c.cat_clave && c.cat_titulo) ? (c.cat_clave + ' ' + c.cat_titulo) : 'General';
      const sub = (c.nodo_clave && c.nodo_titulo) ? (c.nodo_clave + ' ' + c.nodo_titulo) : 'Sin Subcategoría';

      if (!tree[p]) tree[p] = {};
      if (!tree[p][ct]) tree[p][ct] = {};
      if (!tree[p][ct][sub]) tree[p][ct][sub] = [];
      tree[p][ct][sub].push(c);
    });

    let h = '';
    for (let p in tree) {
      let catCount = Object.keys(tree[p]).length;

      h += `<div class="oc-card">
              <div class="oc-card-header cursor-pointer" onclick="this.nextElementSibling.classList.toggle('d-none')">
                <div class="oc-card-header__title">
                  <i class="fa-solid fa-box"></i>
                  <span>${p}</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="text-muted small fw-semibold">${catCount} categorías</span>
                  <i class="fa-solid fa-chevron-down opacity-50 ms-1"></i>
                </div>
              </div>
              <div class="oc-card-body">`;

      for (let ct in tree[p]) {
        h += `<div class="oc-form-subsection mt-2 mb-3">
                <h5 class="oc-form-subsection__title" style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                  <i class="fa-solid fa-folder-open me-2"></i> ${ct}
                </h5>
              </div>`;

        for (let sub in tree[p][ct]) {
          h += `<div class="d-flex align-items-center gap-2 mb-2 ms-3">
                  <i class="fa-solid fa-arrow-right-long text-muted opacity-50"></i>
                  <span style="font-size: 0.8rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.03em;">${sub}</span>
                </div>
                <div class="orders-table-wrap mb-4" style="border: 1px solid var(--gray-200); border-radius: 10px; overflow: hidden; margin-left: 1rem;">
                  <table class="orders-table">
                    <thead>
                      <tr>
                        <th width="50" class="text-center">Ref</th>
                        <th width="120">Código</th>
                        <th>Descripción</th>
                        <th width="140" class="text-end">Importe</th>
                        <th width="240">Programación</th>
                        <th width="60" class="text-center">OK</th>
                        <th width="100" class="text-center">Dif.</th>
                        <th width="60" class="text-end"></th>
                      </tr>
                    </thead>
                    <tbody>`;

          tree[p][ct][sub].forEach(c => {
            const rowClass = (+c.terminado ? 'is-done' : '') + (+c.es_referencia ? ' is-ref' : '');
            h += `<tr class="${rowClass}">
                    <td class="text-center">
                      <div class="form-check d-flex justify-content-center m-0">
                        <input class="form-check-input cursor-pointer" type="checkbox" ${+c.es_referencia ? 'checked' : ''} onchange="toggleRef(${c.id}, this.checked)">
                      </div>
                    </td>
                    <td class="cell-folio">${c.codigo_concepto}</td>
                    <td style="max-width: 320px; white-space: normal; line-height: 1.4;">${c.nombre_concepto}</td>
                    <td class="text-end fw-bold text-success">${fmt(c.importe)}</td>
                    <td class="cell-date">${fmtD(c.fecha_inicio)} <span class="text-muted opacity-50">al</span> ${fmtD(c.fecha_fin)}</td>
                    <td class="text-center">
                      <div class="form-check d-flex justify-content-center m-0">
                        <input class="form-check-input cursor-pointer" type="checkbox" ${+c.terminado ? 'checked' : ''} onchange="quickOK(${c.id}, this.checked)">
                      </div>
                    </td>
                    <td class="text-center">${getDiff(c)}</td>
                    <td class="text-end">
                      <button class="btn-action" style="color: var(--gray-400);" title="Editar Programación" onclick="openM(${c.id})" onmouseover="this.style.color='var(--p-500)'" onmouseout="this.style.color='var(--gray-400)'">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>
                    </td>
                  </tr>`;
          });

          h += `      </tbody>
                  </table>
                </div>`;
        }
      }

      h += `  </div>
            </div>`;
    }
    $('plan-body').innerHTML = h || `<div class="orders-card p-5 text-center"><div class="orders-empty-state"><i class="fa-solid fa-inbox" style="font-size: 3rem; color: var(--gray-300);"></i><p class="mt-3 text-muted fw-bold" style="font-size: 0.95rem;">Sin resultados</p></div></div>`;
  }

  function getDiff(c) {
    if (!+c.terminado || c.dias_diferencia === null) return '-';
    const d = c.dias_diferencia;
    if (d < 0) return `<span class="badge-diff diff-antes"><i class="fa-solid fa-circle-check"></i> ${Math.abs(d)}d Antes</span>`;
    if (d > 0) return `<span class="badge-diff diff-despues"><i class="fa-solid fa-triangle-exclamation"></i> ${d}d Atraso</span>`;
    return `<span class="badge-diff diff-ok"><i class="fa-solid fa-clock"></i> A Tiempo</span>`;
  }

  async function toggleRef(id, val) {
    await api({
      action: 'toggle_referencia',
      oid: $('sel-obra').value,
      cid: id,
      val: val ? 1 : 0
    });
    CS.find(x => x.id == id).es_referencia = val ? 1 : 0;
    updateStats();
    render();
  }

  async function quickOK(id, val) {
    if (val) {
      openM(id);
      return;
    }
    await api({
      action: 'marcar_terminado',
      oid: $('sel-obra').value,
      cid: id,
      val: 0
    });
    const c = CS.find(x => x.id == id);
    c.terminado = 0;
    c.fecha_terminado = null;
    c.dias_diferencia = null;
    updateStats();
    render();
  }

  function openM(id) {
    const c = CS.find(x => x.id == id);
    $('m-id').value = c.id;
    $('m-name').textContent = c.nombre_concepto;
    $('m-pu').value = c.precio_unitario;
    $('m-imp').value = c.importe;
    $('m-fi').value = c.fecha_inicio;
    $('m-ff').value = c.fecha_fin;
    $('m-not').value = c.notas || '';
    $('m-done').checked = !!+c.terminado;
    $('m-ft').value = c.fecha_terminado || '';
    toggleFT();
    $('modal').classList.add('open');
  }

  async function save() {
    const id = $('m-id').value;
    const dPlan = {
      action: 'guardar_plan_concepto',
      obra_id: $('sel-obra').value,
      catalogo_id: $('sel-cat').value,
      concepto_id: id,
      precio_unitario: $('m-pu').value,
      importe: $('m-imp').value,
      fecha_inicio: $('m-fi').value,
      fecha_fin: $('m-ff').value,
      notas: $('m-not').value
    };
    const r1 = await api(dPlan);
    if (r1.success) {
      const valDone = $('m-done').checked;
      const r2 = await api({
        action: 'marcar_terminado',
        oid: $('sel-obra').value,
        cid: id,
        val: valDone ? 1 : 0,
        fecha: $('m-ft').value
      });
      const c = CS.find(x => x.id == id);
      Object.assign(c, {
        ...dPlan,
        terminado: valDone ? 1 : 0,
        fecha_terminado: $('m-ft').value,
        dias_diferencia: r2.diff
      });
      closeM();
      updateStats();
      render();
    }
  }

  function updateStats() {
    const t = CS.length,
      imp = CS.reduce((a, b) => a + parseFloat(b.importe || 0), 0);
    const ref = CS.filter(c => +c.es_referencia).reduce((a, b) => a + parseFloat(b.importe || 0), 0);
    const done = CS.filter(c => +c.terminado).length;
    $('s-tot').textContent = t;
    $('s-imp').textContent = fmt(imp);
    $('s-ref').textContent = fmt(ref);
    $('s-done').textContent = done;
    $('s-pct').textContent = Math.round((done / t) * 100) + '%';
  }

  function toggleFT() {
    $('m-ft-box').style.display = $('m-done').checked ? 'block' : 'none';
  }

  function closeM() {
    $('modal').classList.remove('open');
  }

  (async () => {
    const r = await api({
      action: 'obtener_proyectos'
    });
    $('sel-pro').innerHTML = '<option value="">Proyecto...</option>' + r.proyectos.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('');
  })();
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>