<?php
// list_cotizaciones.php — Lista y CRUD de cotizaciones
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

$dep_id_sesion  = $_SESSION['departamento_id'] ?? null;
$es_super_admin = ($_SESSION['departamento']   ?? '') === 'SUPER_ADMIN';
if (!$es_super_admin && !in_array($dep_id_sesion, [1, 2, 10, 16])) {
    header("Location: /PROATAM/index.php?acceso=denegado");
    exit;
}

include_once __DIR__ . '/../conexion.php';

$busqueda  = trim($_GET['q']    ?? '');
$pagina    = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 10;
$offset    = ($pagina - 1) * $porPagina;

// Construcción de WHERE dinámica
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($busqueda) {
    $where  .= " AND (c.folio LIKE ? OR c.atencion LIKE ? OR c.compania LIKE ?)";
    $like    = "%$busqueda%";
    $params  = [$like, $like, $like];
    $types   = "sss";
}

// Total para paginación
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM cotizaciones c $where");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$total     = (int)$stmtTotal->get_result()->fetch_assoc()['total'];
$totalPags = (int)ceil($total / $porPagina);
$stmtTotal->close();

// Registros de la página actual
$paramsPag = array_merge($params, [$porPagina, $offset]);
$typesPag  = $types . "ii";
$stmt = $conn->prepare("
    SELECT c.id, c.folio, c.fecha_emision, c.atencion, c.compania,
           c.total, c.moneda, c.emisor_nombre, c.emisor_depto, c.tasa_iva,
           e.nombre AS entidad
    FROM cotizaciones c
    LEFT JOIN entidades e ON c.entidades_id = e.id
    $where
    ORDER BY c.fecha_creacion DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$entidadColores = [
    'PROATAM'     => '#113456',
    'INGETAM'     => '#efa336',
    'LUBYCOMP'    => '#243944',
    'DAVID GOMEZ' => '#fbae17',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cotizaciones</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/PROATAM/assets/styles/navbar.css">
  <link rel="stylesheet" href="/PROATAM/assets/styles/list.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root { --azul:#1a3a5c; --verde:#2e7d5e; --borde:#e5e7eb; }

    /* Botones de acción */
    .btn-accion {
      width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center;
      border-radius:6px; border:none; cursor:pointer; font-size:.85rem;
      transition:background .15s, transform .1s; text-decoration:none; flex-shrink:0;
    }
    .btn-accion:active { transform:scale(.93); }
    .btn-ver  { background:#e8f4fd; color:#1565c0; }
    .btn-ver:hover  { background:#c5e3f7; color:#1565c0; }
    .btn-edit { background:#fff8e1; color:#f59e0b; }
    .btn-edit:hover { background:#fde68a; color:#b45309; }
    .btn-dl   { background:#e8f5e9; color:#2e7d5e; }
    .btn-dl:hover   { background:#c8e6c9; color:#1b5e20; }
    .btn-del  { background:#fef2f2; color:#dc2626; }
    .btn-del:hover  { background:#fee2e2; color:#991b1b; }
    .acciones-group { display:flex; gap:4px; flex-shrink:0; }

    /* Badge entidad */
    .badge-entidad {
      display:inline-block; padding:2px 7px; border-radius:20px;
      font-size:.65rem; font-weight:700; letter-spacing:.5px;
      color:#fff; text-transform:uppercase; white-space:nowrap;
    }

    /* Ítem de lista compacto */
    .cot-item {
      padding:9px 14px; border-bottom:1px solid var(--borde);
      display:flex; align-items:center; gap:10px;
    }
    .cot-item:last-child { border-bottom:none; }
    .cot-item:hover { background:#f9fafb; }
    .cot-main { flex:1; min-width:0; }
    .cot-top  { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:3px; }
    .cot-meta { font-size:.78rem; color:#6b7280; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .cot-total { font-weight:700; color:var(--azul); white-space:nowrap; }

    /* Modales */
    .modal-cot .modal-header { background:var(--azul); color:#fff; border-bottom:none; }
    .modal-cot .modal-title  { font-size:.95rem; font-weight:700; }
    .modal-cot .btn-close    { filter:invert(1); }
    .modal-cot .modal-body   { padding:0; background:#f5f5f5; }
    .modal-cot .modal-footer { border-top:1px solid var(--borde); background:#f9fafb; }

    /* Modal editar — header */
    .modal-edit .modal-header { background:var(--azul); color:#fff; border-bottom:none; }
    .modal-edit .btn-close    { filter:invert(1); }

    /* Campos modal editar */
    .field-edit { display:flex; flex-direction:column; gap:3px; margin-bottom:10px; }
    .field-edit label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#6b7280; }
    .field-edit input, .field-edit select, .field-edit textarea {
      border:1.5px solid var(--borde); border-radius:6px;
      padding:6px 10px; font-size:.875rem; width:100%;
      outline:none; background:#fafafa; transition:border-color .2s;
    }
    .field-edit input:focus, .field-edit select:focus, .field-edit textarea:focus { border-color:var(--verde); }
    .field-readonly { background:#f0f0f0 !important; cursor:not-allowed !important; color:#888 !important; }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../includes/navbar.php"; ?>

<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="/PROATAM/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span><span>Cotizaciones</span>
    </div>
    <h1 class="hero-title">Registro de Cotizaciones</h1>
  </div>
</div>

<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">

      <!-- Buscador + nueva cotización -->
      <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
        <form class="d-flex flex-grow-1 gap-1" method="GET" style="min-width:200px;">
          <input class="form-control" type="search" name="q"
                 placeholder="Buscar por folio, cliente o empresa..."
                 value="<?= htmlspecialchars($busqueda) ?>">
          <button class="btn btn-outline-success" type="submit">
            <i class="bi bi-search"></i>
          </button>
          <?php if ($busqueda): ?>
            <a href="list_cotizaciones.php" class="btn btn-outline-secondary" title="Limpiar">
              <i class="bi bi-x-lg"></i>
            </a>
          <?php endif; ?>
        </form>
        <a href="/PROATAM/cotizaciones/cotizacion.php" class="button-56 text-decoration-none">
          <i class="bi bi-plus-circle"></i> Nueva Cotización
        </a>
      </div>

      <div class="mb-2">
        <span class="badge-num">
          <?= $total ?> cotizaci<?= $total === 1 ? 'ón' : 'ones' ?>
        </span>
      </div>

      <!-- Lista -->
      <?php if ($result && $result->num_rows > 0): ?>
      <div class="list-group list-group-flush border rounded-3 overflow-hidden">
        <?php while ($row = $result->fetch_assoc()):
          $entColor = $entidadColores[strtoupper($row['entidad'] ?? '')] ?? '#1a3a5c';
        ?>
        <div class="cot-item" id="item-<?= $row['id'] ?>">
          <div class="cot-main">
            <div class="cot-top">
              <span class="badge bg-success" style="font-size:.65rem;letter-spacing:.8px;">
                <?= htmlspecialchars($row['folio']) ?>
              </span>
              <?php if (!empty($row['entidad'])): ?>
                <span class="badge-entidad" style="background:<?= $entColor ?>;">
                  <?= htmlspecialchars($row['entidad']) ?>
                </span>
              <?php endif; ?>
              <strong class="text-truncate" style="max-width:200px;">
                <?= htmlspecialchars($row['atencion']) ?>
              </strong>
              <?php if ($row['compania']): ?>
                <span class="text-muted" style="font-size:.82rem;">
                  — <?= htmlspecialchars($row['compania']) ?>
                </span>
              <?php endif; ?>
            </div>
            <div class="cot-meta">
              <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($row['fecha_emision'])) ?></span>
              <span>
                <i class="bi bi-person"></i> <?= htmlspecialchars($row['emisor_nombre']) ?>
                <span class="text-muted">(<?= htmlspecialchars($row['emisor_depto']) ?>)</span>
              </span>
              <span class="cot-total">
                $<?= number_format($row['total'], 2, '.', ',') ?> <?= htmlspecialchars($row['moneda']) ?>
                <span class="fw-normal text-muted">+ IVA <?= (int)$row['tasa_iva'] ?>%</span>
              </span>
            </div>
          </div>

          <div class="acciones-group">
            <button class="btn-accion btn-ver"
                    onclick="verCotizacion(<?= $row['id'] ?>, '<?= htmlspecialchars($row['folio'], ENT_QUOTES) ?>')"
                    title="Ver PDF"><i class="bi bi-eye"></i></button>
            <button class="btn-accion btn-edit"
                    onclick="editarCotizacion(<?= $row['id'] ?>)"
                    title="Editar"><i class="bi bi-pencil"></i></button>
            <a href="descargar_cotizacion.php?id=<?= $row['id'] ?>"
               class="btn-accion btn-dl" title="Descargar PDF">
              <i class="bi bi-file-earmark-arrow-down"></i>
            </a>
            <button class="btn-accion btn-del"
                    onclick="eliminarCotizacion(<?= $row['id'] ?>, '<?= htmlspecialchars($row['folio'], ENT_QUOTES) ?>')"
                    title="Eliminar permanentemente"><i class="bi bi-trash3"></i></button>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

      <!-- Paginación -->
      <?php if ($totalPags > 1): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
          <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $pagina-1 ?>">‹</a>
          </li>
          <?php foreach (range(max(1,$pagina-2), min($totalPags,$pagina+2)) as $i): ?>
          <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
            <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
          <?php endforeach; ?>
          <li class="page-item <?= $pagina >= $totalPags ? 'disabled' : '' ?>">
            <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $pagina+1 ?>">›</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>

      <?php else: ?>
      <div class="text-center text-muted py-5">
        <i class="bi bi-file-earmark-x" style="font-size:3rem;"></i>
        <p class="mt-2">
          <?= $busqueda ? 'No hay resultados para "' . htmlspecialchars($busqueda) . '"' : 'No hay cotizaciones registradas' ?>
        </p>
        <?php if (!$busqueda): ?>
          <a href="/PROATAM/cotizaciones/cotizacion.php" class="button-56">
            <i class="bi bi-plus-circle"></i> Crear primera cotización
          </a>
        <?php else: ?>
          <a href="list_cotizaciones.php" class="btn btn-outline-secondary mt-2">Limpiar búsqueda</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: VER PDF (iframe)
══════════════════════════════════════════ -->
<div class="modal fade modal-cot" id="modalVer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-pdf me-2"></i>
          <span id="verFolio">Cotización</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="verContenido">
        <div class="text-center py-5">
          <div class="spinner-border text-success" style="width:3rem;height:3rem;"></div>
          <p class="mt-3 text-muted">Cargando PDF...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cerrar
        </button>
        <a id="verDescargar" href="#" class="btn btn-success" download>
          <i class="bi bi-download"></i> Descargar PDF
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: EDITAR COTIZACIÓN
══════════════════════════════════════════ -->
<div class="modal fade modal-edit" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-white">
          <i class="bi bi-pencil-square me-2"></i>Editar Cotización
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#fff;">
        <form id="formEditar">
          <input type="hidden" id="editId" name="id">
          <div class="row g-2">
            <!-- Fila 1: Folio (readonly), Entidad, Moneda -->
            <div class="col-md-4">
              <div class="field-edit">
                <label>Folio</label>
                <input type="text" id="editFolio" class="field-readonly" readonly tabindex="-1">
              </div>
            </div>
            <div class="col-md-4">
              <div class="field-edit">
                <label>Entidad Emisora</label>
                <select id="editEntidad" name="entidad">
                  <option value="PROATAM">PROATAM S.A. DE C.V.</option>
                  <option value="INGETAM">INGETAM S.A. DE C.V.</option>
                  <option value="LUBYCOMP">LUBYCOMP</option>
                  <option value="DAVID GOMEZ">DAVID GOMEZ</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="field-edit">
                <label>Moneda</label>
                <select id="editMoneda" name="moneda">
                  <option value="MXN">MXN — Pesos Mexicanos</option>
                  <option value="USD">USD — Dólares Americanos</option>
                </select>
              </div>
            </div>
            <!-- Fila 2: Atención, Compañía -->
            <div class="col-md-6">
              <div class="field-edit">
                <label>Atención a <span class="text-danger">*</span></label>
                <input type="text" id="editAtencion" name="atencion" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="field-edit">
                <label>Compañía</label>
                <input type="text" id="editCompania" name="compania">
              </div>
            </div>
            <!-- Fila 3: Fecha, Lugar, Vigencia -->
            <div class="col-md-4">
              <div class="field-edit">
                <label>Fecha</label>
                <input type="date" id="editFecha" name="fecha_emision">
              </div>
            </div>
            <div class="col-md-4">
              <div class="field-edit">
                <label>Lugar</label>
                <input type="text" id="editLugar" name="lugar">
              </div>
            </div>
            <div class="col-md-4">
              <div class="field-edit">
                <label>Vigencia</label>
                <input type="text" id="editVigencia" name="vigencia">
              </div>
            </div>
            <!-- Fila 4: Tiempo, Forma de pago -->
            <div class="col-md-6">
              <div class="field-edit">
                <label>Tiempo de ejecución</label>
                <input type="text" id="editTiempo" name="tiempo_ejecucion">
              </div>
            </div>
            <div class="col-md-6">
              <div class="field-edit">
                <label>Forma de pago</label>
                <input type="text" id="editFormaPago" name="forma_pago">
              </div>
            </div>
            <!-- Fila 5: Emisor -->
            <div class="col-md-6">
              <div class="field-edit">
                <label>Nombre del emisor</label>
                <input type="text" id="editEmisorNombre" name="emisor_nombre">
              </div>
            </div>
            <div class="col-md-6">
              <div class="field-edit">
                <label>Departamento del emisor</label>
                <input type="text" id="editEmisorDepto" name="emisor_depto">
              </div>
            </div>
            <!-- Notas -->
            <div class="col-12">
              <div class="field-edit">
                <label>Notas / Observaciones</label>
                <textarea id="editNotas" name="notas" rows="3" style="resize:vertical;"></textarea>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-warning text-dark fw-bold" onclick="guardarEdicion()">
          <i class="bi bi-save me-1"></i> Guardar Cambios
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
// ── VER COTIZACIÓN (modal PDF) ─────────────────────────────────
function verCotizacion(id, folio) {
  document.getElementById('verFolio').textContent = folio || 'Cotización';
  document.getElementById('verContenido').innerHTML = `
    <iframe src="descargar_cotizacion.php?id=${id}&inline=1"
            style="width:100%;height:75vh;border:none;background:#fff;"
            title="Vista previa cotización"></iframe>`;
  document.getElementById('verDescargar').href = 'descargar_cotizacion.php?id=' + id;
  new bootstrap.Modal(document.getElementById('modalVer')).show();
}

// ── EDITAR COTIZACIÓN ──────────────────────────────────────────
function editarCotizacion(id) {
  fetch('get_cotizacion.php?id=' + id)
    .then(r => r.json())
    .then(d => {
      if (!d.success) {
        Swal.fire('Error', d.message || 'No se pudieron cargar los datos.', 'error');
        return;
      }
      const c = d.cotizacion;
      document.getElementById('editId').value           = c.id            || '';
      document.getElementById('editFolio').value        = c.folio         || '';
      document.getElementById('editEntidad').value      = c.entidad       || 'PROATAM';
      document.getElementById('editMoneda').value       = c.moneda        || 'MXN';
      document.getElementById('editAtencion').value     = c.atencion      || '';
      document.getElementById('editCompania').value     = c.compania      || '';
      document.getElementById('editFecha').value        = c.fecha_emision || '';
      document.getElementById('editLugar').value        = c.lugar         || '';
      document.getElementById('editVigencia').value     = c.vigencia      || '';
      document.getElementById('editTiempo').value       = c.tiempo_ejecucion || '';
      document.getElementById('editFormaPago').value    = c.forma_pago    || '';
      document.getElementById('editEmisorNombre').value = c.emisor_nombre || '';
      document.getElementById('editEmisorDepto').value  = c.emisor_depto  || '';
      document.getElementById('editNotas').value        = c.notas         || '';
      new bootstrap.Modal(document.getElementById('modalEditar')).show();
    })
    .catch(() => Swal.fire('Error', 'No se pudo conectar al servidor.', 'error'));
}

function guardarEdicion() {
    const atencion = document.getElementById('editAtencion').value.trim();
    if (!atencion) {
        Swal.fire('Campo requerido', 'El campo "Atención a" es obligatorio.', 'warning');
        return;
    }

    const data = new FormData(document.getElementById('formEditar'));

    fetch('update_cotizacion.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modalEditar')).hide();
                
                Swal.fire({
                    icon: 'success', 
                    title: 'Guardado',
                    text: resp.message || 'Cotización actualizada.',
                    timer: 2000, 
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', resp.message || 'No se pudo guardar.', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Fallo de conexión.', 'error'));
}

// ── ELIMINAR COTIZACIÓN (borrado físico de BD) ─────────────────
function eliminarCotizacion(id, folio) {
  Swal.fire({
    title: '¿Eliminar permanentemente?',
    html: `La cotización <strong>${folio}</strong> será eliminada de la base de datos. Esta acción no se puede deshacer.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '<i class="bi bi-trash3"></i> Sí, eliminar',
    cancelButtonText: 'Cancelar',
    focusCancel: true,
  }).then(result => {
    if (!result.isConfirmed) return;

    fetch('delete_cotizacion.php?id=' + id)
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          // Eliminar el elemento del DOM sin recargar
          const item = document.getElementById('item-' + id);
          if (item) {
            item.style.transition = 'opacity .3s';
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 300);
          }
          Swal.fire({
            icon: 'success', title: 'Eliminada',
            text: data.message || 'Cotización eliminada.',
            timer: 2000, showConfirmButton: false
          });
        } else {
          Swal.fire('Error', data.message || 'No se pudo eliminar.', 'error');
        }
      })
      .catch(() => Swal.fire('Error', 'Fallo de conexión al eliminar.', 'error'));
  });
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>