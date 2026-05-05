<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// ==== Filtros ====
$busqueda = $_GET['q'] ?? '';
$pagina = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// ====== Query base ======
$sqlBase = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($busqueda)) {
    $sqlBase .= " AND (p.numero_licitacion LIKE ? OR p.numero_contrato LIKE ? OR p.nombre_proyecto LIKE ?)";
    $like = "%$busqueda%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

// ====== Total registros ======
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM proyectos p $sqlBase");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

// ====== Datos paginados ======
$stmt = $conn->prepare("SELECT p.id, p.numero_licitacion, p.numero_contrato, p.nombre_proyecto,
                        p.fecha_inicio, p.fecha_fin, p.monto_designado, p.monto_con_iva, p.costo_directo,
                        (SELECT COUNT(*) FROM obras WHERE proyecto_id = p.id) as total_obras,
                        (SELECT COALESCE(SUM(costo_directo_utilizado), 0) FROM presupuesto_control
                         WHERE proyecto_id = p.id AND obra_id IS NULL) as costo_directo_utilizado
                        FROM proyectos p
                        $sqlBase
                        ORDER BY p.fecha_inicio DESC
                        LIMIT ? OFFSET ?");
$paramsPag = $params;
$typesPag = $types . "ii";
$paramsPag[] = $por_pagina;
$paramsPag[] = $offset;
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();

$totalPaginas = ceil($totalRegistros / $por_pagina);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Proyectos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <style>
    .badge-presupuesto { font-size: 0.75em; padding: 0.25em 0.5em; }
    .presupuesto-info  { font-size: 0.85em; line-height: 1.4; }
    .progress          { height: 6px; margin-top: 5px; }
    .progress-bar      { transition: width 0.3s ease; }
  </style>
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Página de inicio</a>
      <span>/</span>
      <span>Registro de Proyectos</span>
    </div>
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Registro de Proyectos</h1>
      </div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">
      <!-- Buscador -->
      <form id="search-form" class="form-search d-flex justify-content-center w-100 mb-4" method="GET">
        <input class="form-control w-100" type="search" name="q" placeholder="Buscar proyecto..." value="<?= htmlspecialchars($busqueda) ?>">
        <button class="btn btn-outline-success" type="submit"><i class="bi bi-search"></i></button>
      </form>

      <div id="table-container-wrapper">
        <div class="d-flex justify-content-between mb-3">
          <span class="badge-num"><?= $totalRegistros ?> proyectos</span>
          <button class="button-56" type="button" onclick="agregarProyecto()">
            <i class="bi bi-plus-circle"></i> Nuevo Proyecto
          </button>
        </div>

        <?php if($result && $result->num_rows > 0): ?>
        <ul class="list-group">
          <?php while($row = $result->fetch_assoc()):
            $costo_disponible = $row['costo_directo'] - $row['costo_directo_utilizado'];
            $porcentaje_utilizado = $row['costo_directo'] > 0 ? ($row['costo_directo_utilizado'] / $row['costo_directo']) * 100 : 0;
            $progress_class = $porcentaje_utilizado > 90 ? 'bg-danger' : ($porcentaje_utilizado > 70 ? 'bg-warning' : 'bg-success');
          ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                  <strong><?= htmlspecialchars($row['nombre_proyecto']) ?></strong>
                  <div class="presupuesto-info mt-1">
                    <?php if($row['total_obras'] > 0): ?>
                    <span class="badge bg-info badge-presupuesto"><i class="bi bi-tools"></i> <?= $row['total_obras'] ?> obra(s)</span>
                    <?php else: ?>
                    <span class="badge bg-secondary badge-presupuesto"><i class="bi bi-building"></i> Sin obras</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="btn-group" style="gap:5px;">
              <a href="list_obras.php?proyecto_id=<?= $row['id'] ?>" class="btn-add-oc"
                 data-bs-toggle="tooltip" data-bs-placement="top" title="Gestionar Obras">
                <i class="bi bi-cone-striped"></i>
              </a>
              <a href="details_project.php?id=<?= $row['id'] ?>" class="btn-inf"
                 data-bs-toggle="tooltip" data-bs-placement="top" title="Ver Detalles del Proyecto">
                <i class="bi bi-info-circle"></i>
              </a>
              <button class="btn-del" onclick="eliminarProyecto(<?= $row['id'] ?>)"
                data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar Proyecto">
                <i class="bi bi-trash3"></i>
              </button>
            </div>
          </li>
          <?php endwhile; ?>
        </ul>

        <?php if($totalPaginas > 1): ?>
        <nav aria-label="Paginación">
          <ul class="pagination justify-content-center mt-3">
            <?php for($i=1; $i<=$totalPaginas; $i++): ?>
            <li class="page-item <?= $i==$pagina?'active':'' ?>">
              <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center text-muted py-4">
          <i class="bi bi-inbox" style="font-size:3rem;"></i>
          <p class="mt-2">No hay proyectos registrados</p>
        </div>
        <?php endif; ?>
      </div><!-- /table-container-wrapper -->
    </div>
  </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});

// ── Agregar Proyecto ──────────────────────────────────────────────────────────
function agregarProyecto() {
    fetch('get_clientes.php')
        .then(res => res.json())
        .then(clientes => {
            let opts = '<option value="">-- Seleccionar Cliente --</option>';
            clientes.forEach(c => { opts += `<option value="${c.id}">${c.nombre_abreviado||c.nombre}</option>`; });

            UI.modal({
                title: 'Nuevo Proyecto',
                size: 'lg',
                html: `<form id="fmProyecto">
                  <div class="mb-2"><label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select">${opts}</select></div>
                  <div class="mb-2"><label class="form-label">Nº Licitación *</label>
                    <input name="numero_licitacion" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Nº Contrato *</label>
                    <input name="numero_contrato" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Nombre del Proyecto *</label>
                    <input name="nombre_proyecto" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2"></textarea></div>
                  <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">Fecha Inicio *</label>
                      <input type="date" name="fecha_inicio" class="form-control" required></div>
                    <div class="col-6 mb-2"><label class="form-label">Fecha Fin *</label>
                      <input type="date" name="fecha_fin" class="form-control" required></div>
                  </div>
                  <div class="mb-2"><label class="form-label">Monto Designado *</label>
                    <input type="number" step="0.01" name="monto_designado" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Monto Anticipo *</label>
                    <input type="number" step="0.01" name="monto_anticipo" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Monto con IVA *</label>
                    <input type="number" step="0.01" name="monto_con_iva" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Costo Directo *</label>
                    <input type="number" step="0.01" name="costo_directo" class="form-control" required>
                    <small class="text-muted">Presupuesto disponible para OCs</small></div>
                  <button type="submit" class="btn btn-success w-100 mt-2">
                    <i class="bi bi-floppy me-1"></i>Guardar Proyecto</button>
                </form>`,
            });
            document.getElementById('fmProyecto').addEventListener('submit', async function(e) {
                e.preventDefault();
                try {
                    const r = await fetch('insert_project.php', { method:'POST', body: new FormData(this) });
                    const d = await r.json();
                    UI.modal.close();
                    if (d.status === 'success') { UI.toast.success(d.message||'Proyecto creado.'); setTimeout(()=>location.reload(),1200); }
                    else UI.toast.error(d.message||'Error al guardar.');
                } catch(err) { UI.toast.error('Error de conexión: '+err.message); }
            });
        })
        .catch(() => UI.toast.error('No se pudieron cargar los clientes.'));
}

// ── Agregar Obra ──────────────────────────────────────────────────────────────
function agregarObra(proyectoId) {
    fetch(`get_info_proyecto.php?id=${proyectoId}`)
        .then(res => res.json())
        .then(proyecto => {
            UI.modal({
                title: 'Nueva Obra',
                size: 'lg',
                html: `<form id="fmObra">
                  <input type="hidden" name="proyecto_id" value="${proyectoId}">
                  <div class="mb-2"><label class="form-label">Nº Obra *</label>
                    <input name="numero_obra" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Nombre Obra *</label>
                    <input name="nombre_obra" class="form-control" required></div>
                  <div class="mb-2"><label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Detalles de la obra..."></textarea></div>
                  <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">Fecha Inicio *</label>
                      <input type="date" name="fecha_inicio" class="form-control" required></div>
                    <div class="col-6 mb-2"><label class="form-label">Fecha Fin *</label>
                      <input type="date" name="fecha_fin" class="form-control" required></div>
                  </div>
                  <div class="mb-2"><label class="form-label">Monto Designado *</label>
                    <input type="number" step="0.01" name="monto_designado" class="form-control" required>
                    <small class="text-muted">Del total del proyecto: $${parseFloat(proyecto.monto_designado||0).toLocaleString('es-MX',{minimumFractionDigits:2})}</small></div>
                  <div class="mb-2"><label class="form-label">Costo Directo *</label>
                    <input type="number" step="0.01" name="costo_directo" class="form-control" required>
                    <small class="text-muted">Presupuesto para OCs de esta obra</small></div>
                  <button type="submit" class="btn btn-success w-100 mt-2">
                    <i class="bi bi-floppy me-1"></i>Guardar Obra</button>
                </form>`,
            });
            document.getElementById('fmObra').addEventListener('submit', async function(e) {
                e.preventDefault();
                try {
                    const r = await fetch('insert_obra.php', { method:'POST', body: new FormData(this) });
                    const d = await r.json();
                    UI.modal.close();
                    if (d.status === 'success') { UI.toast.success('Obra creada correctamente.'); setTimeout(()=>location.reload(),1200); }
                    else UI.toast.error(d.message||'Error al guardar la obra.');
                } catch(err) { UI.toast.error('Error de conexión.'); }
            });
        });
}

// ── Editar Obra ───────────────────────────────────────────────────────────────
function editarObra(obraId) {
    fetch(`edit_obra.php?id=${obraId}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) { UI.toast.error(data.error); return; }
            UI.modal({
                title: 'Editar Obra',
                size: 'lg',
                html: `<form id="fmEditObra">
                  <input type="hidden" name="id" value="${data.id}">
                  <div class="mb-2"><label class="form-label">Nº Obra</label>
                    <input name="numero_obra" class="form-control" value="${data.numero_obra}" required></div>
                  <div class="mb-2"><label class="form-label">Nombre Obra</label>
                    <input name="nombre_obra" class="form-control" value="${data.nombre_obra}" required></div>
                  <div class="mb-2"><label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2">${data.descripcion||''}</textarea></div>
                  <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">Fecha Inicio</label>
                      <input type="date" name="fecha_inicio" class="form-control" value="${data.fecha_inicio}" required></div>
                    <div class="col-6 mb-2"><label class="form-label">Fecha Fin</label>
                      <input type="date" name="fecha_fin" class="form-control" value="${data.fecha_fin}" required></div>
                  </div>
                  <div class="mb-2"><label class="form-label">Monto Designado</label>
                    <input type="number" step="0.01" name="monto_designado" class="form-control" value="${data.monto_designado}" required></div>
                  <div class="mb-2"><label class="form-label">Costo Directo</label>
                    <input type="number" step="0.01" name="costo_directo" class="form-control" value="${data.costo_directo}" required></div>
                  <button type="submit" class="btn btn-warning w-100 mt-2">
                    <i class="bi bi-pencil me-1"></i>Actualizar Obra</button>
                </form>`,
            });
            document.getElementById('fmEditObra').addEventListener('submit', async function(e) {
                e.preventDefault();
                try {
                    const r = await fetch('update_obra.php', { method:'POST', body: new FormData(this) });
                    const resp = await r.json();
                    UI.modal.close();
                    if (resp.status === 'success') { UI.toast.success('Obra actualizada.'); setTimeout(()=>location.reload(),1200); }
                    else UI.toast.error(resp.message||'Error al actualizar.');
                } catch(err) { UI.toast.error('Error de conexión.'); }
            });
        });
}

// ── Eliminar Obra ─────────────────────────────────────────────────────────────
function eliminarObra(obraId, proyectoId) {
    UI.confirm({
        title: '¿Eliminar esta obra?',
        message: 'Esta acción no se puede deshacer. Las OCs asociadas quedarán sin obra.',
        danger: true, confirmText: 'Sí, eliminar'
    }).then(ok => {
        if (!ok) return;
        fetch(`delete_obra.php?id=${obraId}`)
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') { UI.toast.success('Obra eliminada.'); setTimeout(()=>location.reload(),1200); }
                else UI.toast.error(resp.message||'No se pudo eliminar la obra.');
            });
    });
}

// ── Eliminar Proyecto ─────────────────────────────────────────────────────────
function eliminarProyecto(id) {
    UI.confirm({
        title: '¿Eliminar este proyecto?',
        message: 'Esto eliminará también todas las obras asociadas y su historial.',
        danger: true, confirmText: 'Sí, eliminar'
    }).then(ok => {
        if (!ok) return;
        fetch(`delete_project.php?id=${id}`)
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') { UI.toast.success('Proyecto eliminado.'); setTimeout(()=>location.reload(),1200); }
                else UI.toast.error(resp.message||'No se pudo eliminar el proyecto.');
            });
    });
}

// ── AJAX Search ───────────────────────────────────────────────────────────────
function initAJAX() {
    const searchForm = document.getElementById('search-form');
    const container  = document.getElementById('table-container-wrapper');
    if (!searchForm || !container) return;

    function updateList(url, pushState = true) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newContent = doc.getElementById('table-container-wrapper');
                if (newContent) container.innerHTML = newContent.innerHTML;
                const src = doc.getElementById('search-form');
                if (src) {
                    const ti = searchForm.querySelector('input[name="q"]');
                    const si = src.querySelector('input[name="q"]');
                    if (ti && si) ti.value = si.value;
                }
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
                if (pushState) window.history.pushState({}, '', url);
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
            })
            .catch(() => { container.style.opacity='1'; container.style.pointerEvents='auto'; });
    }

    document.addEventListener('click', function(e) {
        const pl = e.target.closest('.page-link');
        if (pl) { e.preventDefault(); updateList(pl.href); window.scrollTo({top:0,behavior:'smooth'}); }
    });
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const p = new URLSearchParams(new FormData(searchForm));
        p.set('page','1');
        updateList('?'+p.toString());
    });
}
document.addEventListener('DOMContentLoaded', initAJAX);
</script>

</body>
</html>
