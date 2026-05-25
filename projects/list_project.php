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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
  .badge-presupuesto { font-size: 0.75em; padding: 0.25em 0.5em; }
  .presupuesto-info  { font-size: 0.85em; line-height: 1.4; }
  .progress          { height: 6px; margin-top: 5px; }
  .progress-bar      { transition: width 0.3s ease; }
</style>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <span>Proyectos</span>
      </nav>
      <h1 class="orders-page-title">Registro de Proyectos</h1>
    </div>
    <button class="btn-geco-primary" type="button" onclick="agregarProyecto()">
      <i class="bi bi-plus-circle"></i> Nuevo Proyecto
    </button>
  </div>

  <!-- Search and Stats Card -->
  <div class="orders-card mb-4">
    <div class="p-3">
      <form id="search-form" class="orders-filter-bar d-flex gap-2" method="GET">
        <div class="search-input-wrap flex-grow-1">
          <i class="bi bi-search search-icon"></i>
          <input class="form-control" type="search" name="q" placeholder="Buscar proyecto por nombre, licitación o contrato..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <button class="btn-geco-primary" type="submit">Buscar</button>
      </form>
    </div>
  </div>

  <!-- Table Card -->
  <div class="orders-card">
    <div class="p-3 d-flex justify-content-between align-items-center border-bottom">
      <span class="fw-bold text-muted"><?= $totalRegistros ?> proyectos registrados</span>
    </div>

    <div id="table-container-wrapper">
      <?php if($result && $result->num_rows > 0): ?>
      <div class="orders-table-wrap">
        <table class="orders-table">
          <thead>
            <tr>
              <th>Proyecto</th>
              <th>Licitación / Contrato</th>
              <th>Periodo</th>
              <th>Obras</th>
              <th>Presupuesto Disponible</th>
              <th style="width: 120px; text-align: right;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()):
              $costo_directo = (float)$row['costo_directo'];
              $costo_directo_utilizado = (float)$row['costo_directo_utilizado'];
              $costo_disponible = $costo_directo - $costo_directo_utilizado;
              $porcentaje_utilizado = $costo_directo > 0 ? ($costo_directo_utilizado / $costo_directo) * 100 : 0;
              $progress_class = $porcentaje_utilizado > 90 ? 'bg-danger' : ($porcentaje_utilizado > 70 ? 'bg-warning' : 'bg-success');
            ?>
            <tr>
              <td>
                <div class="d-flex flex-column">
                  <span class="fw-bold text-dark"><?= htmlspecialchars($row['nombre_proyecto']) ?></span>
                </div>
              </td>
              <td>
                <div class="d-flex flex-column">
                  <span class="fw-semibold">Licitación: <?= htmlspecialchars($row['numero_licitacion']) ?></span>
                  <span class="text-muted small">Contrato: <?= htmlspecialchars($row['numero_contrato']) ?></span>
                </div>
              </td>
              <td>
                <span class="small text-muted">
                  <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?>
                </span>
              </td>
              <td>
                <?php if($row['total_obras'] > 0): ?>
                <span class="status-badge" style="color:#0284c7; background:rgba(14,165,233,0.08); border-color:rgba(14,165,233,0.3);">
                  <i class="bi bi-tools me-1"></i> <?= $row['total_obras'] ?> obra(s)
                </span>
                <?php else: ?>
                <span class="status-badge" style="color:#64748b; background:rgba(148,163,184,0.08); border-color:rgba(148,163,184,0.3);">
                  Sin obras
                </span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex flex-column" style="min-width: 150px;">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">CD Asignado: $<?= number_format($costo_directo, 2) ?></span>
                    <span class="fw-semibold <?= $costo_disponible < 0 ? 'text-danger' : 'text-success' ?>">
                      $<?= number_format($costo_disponible, 2) ?> disp.
                    </span>
                  </div>
                  <div class="progress" style="height:6px;">
                    <div class="progress-bar <?= $progress_class ?>" role="progressbar" style="width: <?= min($porcentaje_utilizado, 100) ?>%"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="actions-group justify-content-end">
                  <a href="list_obras.php?proyecto_id=<?= $row['id'] ?>" class="btn-action" style="color: var(--p-600);" title="Gestionar Obras">
                    <i class="bi bi-cone-striped"></i>
                  </a>
                  <a href="details_project.php?id=<?= $row['id'] ?>" class="btn-action btn-action--view" title="Ver Detalles del Proyecto">
                    <i class="bi bi-info-circle"></i>
                  </a>
                  <button class="btn-action" style="color: #ef4444;" onclick="eliminarProyecto(<?= $row['id'] ?>)" title="Eliminar Proyecto">
                    <i class="bi bi-trash3"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <?php if($totalPaginas > 1): ?>
      <div class="orders-pagination-bar mt-3 p-3 border-top d-flex justify-content-center">
        <nav aria-label="Paginación">
          <ul class="pagination mb-0">
            <?php for($i=1; $i<=$totalPaginas; $i++): ?>
            <li class="page-item <?= $i==$pagina?'active':'' ?>">
              <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="orders-empty-state">
        <i class="bi bi-inbox" style="font-size:3rem;"></i>
        <p class="mt-2">No hay proyectos registrados</p>
      </div>
      <?php endif; ?>
    </div><!-- /table-container-wrapper -->
  </div>

</div>

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
                html: `<form id="fmProyecto" class="p-2">
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente</label>
                    <select name="cliente_id" class="form-select">${opts}</select>
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Nº Licitación *</label>
                      <input name="numero_licitacion" class="form-control" required placeholder="Ej: LIC-2026-001">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Nº Contrato *</label>
                      <input name="numero_contrato" class="form-control" required placeholder="Ej: CONT-2026-092">
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre del Proyecto *</label>
                    <input name="nombre_proyecto" class="form-control" required placeholder="Nombre descriptivo del proyecto">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Detalles u observaciones del proyecto..."></textarea>
                  </div>
                  <div class="row">
                    <div class="col-6 mb-3">
                      <label class="form-label fw-semibold">Fecha Inicio *</label>
                      <input type="date" name="fecha_inicio" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                      <label class="form-label fw-semibold">Fecha Fin *</label>
                      <input type="date" name="fecha_fin" class="form-control" required>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Monto Designado *</label>
                      <input type="number" step="0.01" name="monto_designado" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Monto Anticipo *</label>
                      <input type="number" step="0.01" name="monto_anticipo" class="form-control" required placeholder="0.00">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Monto con IVA *</label>
                      <input type="number" step="0.01" name="monto_con_iva" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Costo Directo *</label>
                      <input type="number" step="0.01" name="costo_directo" class="form-control" required placeholder="0.00">
                      <small class="text-muted d-block mt-1">Presupuesto disponible para OCs</small>
                    </div>
                  </div>
                  <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-floppy me-1"></i>Guardar Proyecto</button>
                  </div>
                </form>`,
            });
            document.getElementById('fmProyecto').addEventListener('submit', async function(e) {
                e.preventDefault();
                try {
                    const r = await fetch('insert_project.php', { method:'POST', body: new FormData(this) });
                    const d = await r.json();
                    UI.modal.close();
                    if (d.status === 'success') { 
                        UI.toast.success(d.message||'Proyecto creado.'); 
                        setTimeout(()=>location.reload(),1200); 
                    } else {
                        UI.toast.error(d.message||'Error al guardar.');
                    }
                } catch(err) { 
                    UI.toast.error('Error de conexión: '+err.message); 
                }
            });
        })
        .catch(() => UI.toast.error('No se pudieron cargar los clientes.'));
}

// ── Eliminar Proyecto ─────────────────────────────────────────────────────────
function eliminarProyecto(id) {
    UI.confirm({
        title: '¿Eliminar este proyecto?',
        message: 'Esto eliminará también todas las obras asociadas y su historial.',
        danger: true, confirmText: 'Sí, eliminar', cancelText: 'Cancelar'
    }).then(ok => {
        if (!ok) return;
        fetch(`delete_project.php?id=${id}`)
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') { 
                    UI.toast.success('Proyecto eliminado.'); 
                    setTimeout(()=>location.reload(),1200); 
                } else {
                    UI.toast.error(resp.message||'No se pudo eliminar el proyecto.');
                }
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
<?php include __DIR__ . "/../includes/footer.php"; ?>
