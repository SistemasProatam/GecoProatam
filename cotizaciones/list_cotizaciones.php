<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

$dep_id_sesion  = $_SESSION['departamento_id'] ?? null;
$es_super_admin = ($_SESSION['departamento']   ?? '') === 'SUPER_ADMIN';
if (!$es_super_admin && !in_array($dep_id_sesion, [1, 2, 10, 16])) {
  header("Location: " . BASE_URL . "/index.php?acceso=denegado");
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

// ====== Total registros ======
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM cotizaciones c $where");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>

<body>
  <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/navbar.php"; ?>

  <!-- HERO SECTION -->
  <div class="hero-section">
    <div class="container hero-content">
      <div class="breadcrumb-custom">
        <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
        <span>/</span><span>Cotizaciones</span>
      </div>
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h1 class="hero-title">Historial de Cotizaciones</h1>
        </div>
      </div>
    </div>
  </div>

  <div class="content-wrapper">
    <div class="form-container">
      <div class="form-body">

        <!-- Buscador -->
        <form id="search-form" class="form-search d-flex justify-content-center w-100 mb-4" method="GET">
          <input class="form-control w-100" type="search" name="q" placeholder="Buscar cotización..."
            value="<?= htmlspecialchars($busqueda) ?>">
          <button class="btn btn-outline-success" type="submit"> <i class="bi bi-search"></i> </button>
        </form>

        <div id="table-container-wrapper">
        <!-- Botón de agregar proyecto -->
        <div class="d-flex justify-content-between mb-3">
          <span class="badge-num"><?= $totalRegistros  ?> cotizaciones</span>
          <button class="button-56" type="button" onclick="location.href='<?= BASE_URL ?>/cotizaciones/cotizacion.php'">
            <i class="bi bi-plus-circle"></i> Nueva Cotización
          </button>
        </div>

        <div class="list-group">
          <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
if ($result && $result->num_rows > 0): ?>
            <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
while ($row = $result->fetch_assoc()):
              $id    = $row['id'];
              $folio = $row['folio'];
              $ent   = strtoupper($row['entidad'] ?? 'PROATAM');
              $cli   = $row['atencion'];
              $comp  = $row['compania'];
              $fec   = date('d/m/Y', strtotime($row['fecha_emision']));
              $tot   = number_format($row['total'], 2);

              $badgeClass = 'badge-proatam';
              if (strpos($ent, 'INGETAM') !== false) $badgeClass = 'badge-ingetam';
              if (strpos($ent, 'LUBYCOMP') !== false) $badgeClass = 'badge-lubycomp';
              if (strpos($ent, 'DAVID') !== false) $badgeClass = 'badge-david';

              $entColor = $entidadColores[$ent] ?? '#1a3a5c';
            ?>
              <div class="list-group-item">
                <div class="d-flex align-items-center gap-4">
                  <div class="badge-entidad" style="background: <?= $entColor ?>;"><?= htmlspecialchars($ent) ?></div>
                  <div>
                    <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($cli) ?></div>
                    <div class="text-muted small">
                      <i class="bi bi-building"></i> <?= htmlspecialchars($comp ?: 'Sin empresa') ?> |
                      <i class="bi bi-hash"></i> <?= htmlspecialchars($folio) ?> |
                      <i class="bi bi-calendar3"></i> <?= $fec ?>
                    </div>
                  </div>
                </div>

                <div class="d-flex align-items-center gap-4">
                  <div class="text-end">
                    <div class="text-muted small text-uppercase fw-bold ls-1" style="font-size: 0.65rem;">Total</div>
                    <div class="fw-bold text-primary fs-4">$<?= $tot ?></div>
                  </div>

                  <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/cotizaciones/descargar_cotizacion.php?id=<?= $id ?>" class="btn-accion btn-dl" title="Descargar">
                      <i class="bi bi-file-earmark-pdf"></i>
                    </a>
                    <button class="btn-accion btn-ver" onclick="verCotizacion(<?= $id ?>, '<?= $folio ?>')" title="Ver">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn-accion btn-edit" onclick="editarCotizacion(<?= $id ?>)" title="Editar">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn-accion btn-del" onclick="eliminarCotizacion(<?= $id ?>, '<?= $folio ?>')" title="Eliminar">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                </div>
              </div>
            <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
endwhile; ?>
        </div>

        <!-- Paginación -->
        <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
if ($totalPags > 1): ?>
          <nav class="mt-4">
            <ul class="pagination pagination-sm justify-content-center mb-0">
              <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $pagina - 1 ?>">‹</a>
              </li>
              <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
foreach (range(max(1, $pagina - 2), min($totalPags, $pagina + 2)) as $i): ?>
                <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                  <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
endforeach; ?>
              <li class="page-item <?= $pagina >= $totalPags ? 'disabled' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $pagina + 1 ?>">›</a>
              </li>
            </ul>
          </nav>
        <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
endif; ?>

      <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
else: ?>
        <div class="text-center text-muted py-5">
          <i class="bi bi-file-earmark-x" style="font-size:3rem;"></i>
          <p class="mt-2">
            <?= $busqueda ? 'No hay resultados para "' . htmlspecialchars($busqueda) . '"' : 'No hay cotizaciones registradas' ?>
          </p>
          <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
if (!$busqueda): ?>
            <button type="button" onclick="location.href='<?= BASE_URL ?>/cotizaciones/cotizacion.php'" class="button-56">
              <i class="bi bi-plus-circle"></i> Crear primera cotización
            </button>
          <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
else: ?>
            <button type="button" onclick="location.href='<?= BASE_URL ?>/cotizaciones/list_cotizaciones.php'" class="button-56">
              <i class="bi bi-plus-circle"></i> Limpiar búsqueda
            </button>
          <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
endif; ?>
        </div>
      <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
endif; ?>
        </div> <!-- /table-container-wrapper -->

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
          document.getElementById('editId').value = c.id || '';
          document.getElementById('editFolio').value = c.folio || '';
          document.getElementById('editEntidad').value = c.entidad || 'PROATAM';
          document.getElementById('editMoneda').value = c.moneda || 'MXN';
          document.getElementById('editAtencion').value = c.atencion || '';
          document.getElementById('editCompania').value = c.compania || '';
          document.getElementById('editFecha').value = c.fecha_emision || '';
          document.getElementById('editLugar').value = c.lugar || '';
          document.getElementById('editVigencia').value = c.vigencia || '';
          document.getElementById('editTiempo').value = c.tiempo_ejecucion || '';
          document.getElementById('editFormaPago').value = c.forma_pago || '';
          document.getElementById('editEmisorNombre').value = c.emisor_nombre || '';
          document.getElementById('editEmisorDepto').value = c.emisor_depto || '';
          document.getElementById('editNotas').value = c.notas || '';
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

      fetch('update_cotizacion.php', {
          method: 'POST',
          body: data
        })
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
                icon: 'success',
                title: 'Eliminada',
                text: data.message || 'Cotización eliminada.',
                timer: 2000,
                showConfirmButton: false
              });
            } else {
              Swal.fire('Error', data.message || 'No se pudo eliminar.', 'error');
            }
          })
          .catch(() => Swal.fire('Error', 'Fallo de conexión al eliminar.', 'error'));
      });
    }
  </script>

  <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
include __DIR__ . "/../includes/footer.php"; ?>

  <script>
    // Función para actualizar la lista vía AJAX
    function initAJAX() {
      const searchForm = document.getElementById('search-form');
      const container = document.getElementById('table-container-wrapper');

      if (!searchForm || !container) return;

      function updateList(url, pushState = true) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';

        fetch(url)
          .then(response => response.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.getElementById('table-container-wrapper');
            
            if (newContent) {
              container.innerHTML = newContent.innerHTML;
            }

            const newSearch = doc.getElementById('search-form');
            if (newSearch) syncForm(searchForm, newSearch);

            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';

            if (pushState) window.history.pushState({}, '', url);
          })
          .catch(err => {
            console.error('Error:', err);
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
          });
      }

      function syncForm(current, source) {
        source.querySelectorAll('input, select').forEach(input => {
          const target = current.querySelector(`[name="${input.name}"]`);
          if (target) target.value = input.value;
        });
      }

      document.addEventListener('click', function(e) {
        const pageLink = e.target.closest('.page-link');
        if (pageLink) {
          e.preventDefault();
          updateList(pageLink.href);
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      });

      searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(searchForm);
        const params = new URLSearchParams(formData);
        params.set('page', '1');
        updateList('?' + params.toString());
      });
    }

    document.addEventListener('DOMContentLoaded', initAJAX);
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>

</body>

</html>

