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
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Plan de Obra Premium</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/plan_obra.css">
</head>

<body>

  <?php
include __DIR__ . "/../includes/navbar.php"; ?>

  <div class="hero-section">
    <div class="container hero-content">
      <h1 class="hero-title">Plan de Obra</h1>
      <p class="hero-sub"><?= htmlspecialchars($obra_nombre ?? '') ?: 'Control de avances y programaciÃ³n' ?></p>
    </div>
  </div>

  <div class="content-wrapper">
    <div class="ctrl-bar mb-4">
      <div class="row g-3">
        <div class="col-md-3"><label class="lbl">Proyecto</label><select id="sel-pro" class="inp" onchange="getObras()"></select></div>
        <div class="col-md-3"><label class="lbl">Obra</label><select id="sel-obra" class="inp" disabled onchange="getCats()"></select></div>
        <div class="col-md-4"><label class="lbl">CatÃ¡logo</label><select id="sel-cat" class="inp" disabled></select></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn-p w-100" onclick="cargarPlan()">Cargar Plan</button></div>
      </div>
      <div class="mt-3 d-flex gap-4">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="f-ref" onchange="render()"><label class="lbl mb-0">Solo 80%</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="f-pend" onchange="render()"><label class="lbl mb-0">Solo Pendientes</label></div>
      </div>
    </div>

    <div class="row g-3 mb-4" id="stats" style="display:none">
      <div class="col-md-2">
        <div class="sc">
          <div class="sc-lbl">Conceptos</div>
          <div id="s-tot" class="sc-val">0</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="sc">
          <div class="sc-lbl">Importe</div>
          <div id="s-imp" class="sc-val">$0</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="sc">
          <div class="sc-lbl">Referencia</div>
          <div id="s-ref" class="sc-val">$0</div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="sc">
          <div class="sc-lbl">Terminados</div>
          <div id="s-done" class="sc-val">0</div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="sc">
          <div class="sc-lbl">Avance</div>
          <div id="s-pct" class="sc-val">0%</div>
        </div>
      </div>
    </div>

    <div id="plan-body">
      <div class="text-center p-5 text-muted"><i class="bi bi-search" style="font-size:3rem;opacity:0.2"></i>
        <p class="mt-3">Selecciona los filtros para comenzar</p>
      </div>
    </div>
  </div>

  <div class="mo" id="modal">
    <div class="mb">
      <button class="mc" onclick="closeM()">&times;</button>
      <h4 id="m-name" class="fw-bold mb-4"></h4>
      <input type="hidden" id="m-id">
      <div class="row g-3">
        <div class="col-md-6"><label class="lbl">P. Unitario</label><input type="number" id="m-pu" class="inp"></div>
        <div class="col-md-6"><label class="lbl">Importe</label><input type="number" id="m-imp" class="inp"></div>
        <div class="col-md-6"><label class="lbl">Inicio</label><input type="date" id="m-fi" class="inp"></div>
        <div class="col-md-6"><label class="lbl">Fin Plan</label><input type="date" id="m-ff" class="inp"></div>
        <div class="col-12"><label class="lbl">Notas</label><textarea id="m-not" class="inp" rows="2"></textarea></div>
        <div class="col-12 p-3 rounded" style="background:#f8fafc">
          <div class="form-check"><input class="form-check-input" type="checkbox" id="m-done" onchange="toggleFT()"><label class="fw-bold">Marcar como terminado</label></div>
          <div id="m-ft-box" class="mt-2" style="display:none"><label class="lbl">Fecha Real</label><input type="date" id="m-ft" class="inp"></div>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-light rounded-3 px-4" onclick="closeM()">Cerrar</button><button class="btn-p" onclick="save()">Guardar Cambios</button></div>
    </div>
  </div>

  <div id="toast"></div>

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
        $('stats').style.display = 'flex';
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
        const sub = (c.nodo_clave && c.nodo_titulo) ? (c.nodo_clave + ' ' + c.nodo_titulo) : 'Sin SubcategorÃ­a';

        if (!tree[p]) tree[p] = {};
        if (!tree[p][ct]) tree[p][ct] = {};
        if (!tree[p][ct][sub]) tree[p][ct][sub] = [];
        tree[p][ct][sub].push(c);
      });

      let h = '';
      for (let p in tree) {
        h += `<div class="prov-sec">
                <div class="prov-hdr" onclick="this.nextElementSibling.classList.toggle('d-none')">
                    <div class="prov-name"><i class="bi bi-box-seam me-2"></i>${p}</div>
                    <span class="badge bg-light text-dark border">${Object.keys(tree[p]).length} categorÃ­as</span>
                </div>
                <div class="prov-body">`;
        for (let ct in tree[p]) {
          h += `<div class="cat-hdr"><span><i class="bi bi-folder2-open me-2"></i>${ct}</span></div>`;
          for (let sub in tree[p][ct]) {
            h += `<div class="sub-hdr ms-3"><span><i class="bi bi-arrow-return-right me-2 opacity-50"></i>${sub}</span></div>
                  <table class="table table-hover mb-3">
                    <thead><tr><th width="40">Ref</th><th>CÃ³digo</th><th>DescripciÃ³n</th><th width="120" class="text-end">Importe</th><th>ProgramaciÃ³n</th><th width="40">OK</th><th width="80">Dif.</th><th></th></tr></thead>
                    <tbody>`;
            tree[p][ct][sub].forEach(c => {
              h += `<tr class="${+c.terminado?'is-done':''} ${+c.es_referencia?'is-ref':''}">
                        <td><input type="checkbox" ${+c.es_referencia?'checked':''} onchange="toggleRef(${c.id}, this.checked)"></td>
                        <td class="fw-bold">${c.codigo_concepto}</td>
                        <td style="max-width:350px">${c.nombre_concepto}</td>
                        <td class="text-end fw-bold text-success">${fmt(c.importe)}</td>
                        <td class="small">${fmtD(c.fecha_inicio)} al ${fmtD(c.fecha_fin)}</td>
                        <td><input type="checkbox" ${+c.terminado?'checked':''} onchange="quickOK(${c.id}, this.checked)"></td>
                        <td>${getDiff(c)}</td>
                        <td class="text-end"><button class="btn btn-sm btn-light border" onclick="openM(${c.id})"><i class="bi bi-pencil"></i></button></td>
                    </tr>`;
            });
            h += `</tbody></table>`;
          }
        }
        h += `</div></div>`;
      }
      $('plan-body').innerHTML = h || '<div class="text-center p-5">Sin resultados</div>';
    }

    function getDiff(c) {
      if (!+c.terminado || c.dias_diferencia === null) return '-';
      const d = c.dias_diferencia;
      if (d < 0) return `<span class="badge-diff diff-antes">${Math.abs(d)}d Antes</span>`;
      if (d > 0) return `<span class="badge-diff diff-despues">${d}d Atraso</span>`;
      return `<span class="badge-diff diff-ok">A Tiempo</span>`;
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
</body>

</html>



