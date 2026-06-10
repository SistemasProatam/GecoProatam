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
$proyecto_id = $_GET['proyecto_id'] ?? '';
$proyecto_id_js = !empty($proyecto_id) ? intval($proyecto_id) : 'null';
$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// ====== Query base ======
$sqlBase = "FROM obras o 
            LEFT JOIN proyectos p ON o.proyecto_id = p.id 
            WHERE 1=1";
$params = [];
$types = "";

// Búsqueda
if (!empty($busqueda)) {
  $sqlBase .= " AND (o.numero_obra LIKE ? OR o.nombre_obra LIKE ? OR o.descripcion LIKE ? OR p.nombre_proyecto LIKE ?)";
  $like = "%$busqueda%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= "ssss";
}

// Filtro por proyecto
if (!empty($proyecto_id)) {
  $sqlBase .= " AND o.proyecto_id = ?";
  $params[] = $proyecto_id;
  $types .= "i";
}

// ====== Total registros ======
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total $sqlBase");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

// ====== Datos paginados ======
$sqlDatos = "SELECT o.*, p.nombre_proyecto, p.numero_licitacion,
             (SELECT COALESCE(SUM(costo_directo_utilizado), 0) FROM presupuesto_control 
              WHERE obra_id = o.id) as costo_directo_utilizado,
             (SELECT COUNT(*) FROM catalogos WHERE obra_id = o.id) as total_catalogos
             $sqlBase
             ORDER BY o.fecha_inicio DESC
             LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sqlDatos);
$paramsPag = $params;
$typesPag = $types . "ii";
$paramsPag[] = $por_pagina;
$paramsPag[] = $offset;

if ($typesPag) {
  $stmt->bind_param($typesPag, ...$paramsPag);
} else {
  $stmt->bind_param("ii", $por_pagina, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// Obtener lista de proyectos para el filtro
$sqlProyectos = "SELECT id, nombre_proyecto FROM proyectos ORDER BY nombre_proyecto";
$proyectosResult = $conn->query($sqlProyectos);
$proyectos = [];
while ($proyecto = $proyectosResult->fetch_assoc()) {
  $proyectos[] = $proyecto;
}

// Total páginas
$totalPaginas = ceil($totalRegistros / $por_pagina);
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Obras | GECO Proatam</title>


<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="list_project.php">Proyectos</a>
        <span class="separator">›</span>
        <span>Obras</span>
      </nav>
      <h1 class="orders-page-title">Obras</h1>
    </div>
    <div class="d-flex gap-2">
      <a href="list_project.php" class="btn-geco-outline">
        <i class="fa-solid fa-arrow-left"></i> Volver a Proyectos
      </a>
      <button class="btn-geco-primary" type="button" onclick="agregarObra(<?= $proyecto_id_js ?>)">
        <i class="fa-solid fa-plus"></i> Agregar
      </button>
    </div>
  </div>

  <!-- Filters Card -->
  <div class="orders-card mb-4">
    <form id="search-form" method="GET">
      <input type="hidden" name="proyecto_id" id="hidden-proyecto-id" value="<?= htmlspecialchars($proyecto_id) ?>">
      <div class="orders-filter-bar">
        <div class="orders-filter-search">
          <div class="search-input-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" placeholder="Buscar obra por nombre, número o proyecto..." value="<?= htmlspecialchars($busqueda) ?>">
          </div>
        </div>
        <div class="orders-filter-selects">
          <select name="proyecto_id" class="form-select" id="select-proyecto-id">
            <option value="">Todos los proyectos</option>
            <?php foreach ($proyectos as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $proyecto_id == $p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['nombre_proyecto']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </form>
  </div>

  <!-- Table Card -->
  <div class="orders-card">
    <div id="table-container-wrapper" class="p-0">
      <?php if ($result && $result->num_rows > 0): ?>
        <div class="orders-table-wrap">
          <table class="orders-table">
            <thead>
              <tr>
                <th>Obra</th>
                <th>Proyecto</th>
                <th>Periodo</th>
                <th>Presupuesto CD</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()):
                $costo_directo = (float)$row['costo_directo'];
                $costo_directo_utilizado = (float)$row['costo_directo_utilizado'];
                $costo_disponible = $costo_directo - $costo_directo_utilizado;
                $porcentaje_utilizado = $costo_directo > 0 ? ($costo_directo_utilizado / $costo_directo) * 100 : 0;
                $progress_class = $porcentaje_utilizado > 90 ? 'bg-danger' : ($porcentaje_utilizado > 70 ? 'bg-warning' : 'bg-success');
              ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <span class="fw-bold text-dark"><?= htmlspecialchars($row['nombre_obra']) ?></span>
                      <span class="text-muted small">Número: <?= htmlspecialchars($row['numero_obra']) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="fw-semibold text-secondary"><?= htmlspecialchars($row['nombre_proyecto'] ?? 'Sin Proyecto') ?></span>
                  </td>
                  <td>
                    <span class="small text-muted">
                      <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <div class="d-flex justify-content-between small mb-1">
                        <span class="cell-muted">CD: $<?= number_format($costo_directo, 2) ?></span>
                        <span class="fw-semibold <?= $costo_disponible < 0 ? 'text-danger' : '' ?>">
                          $<?= number_format($costo_disponible, 2) ?> disp.
                        </span>
                      </div>
                      <div class="progress" style="height:5px;border-radius:4px;background:var(--gray-100);">
                        <div class="progress-bar <?= $progress_class ?>" role="progressbar" style="width: <?= min($porcentaje_utilizado, 100) ?>%; border-radius:4px; transition:width 0.3s ease;"></div>
                      </div>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="actions-group">
                      <!-- Editar Obra -->
                      <button class="btn-action btn-action--edit" onclick="editarObra(<?= $row['id'] ?>)" title="Editar Obra">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>
                      <!-- Ver Detalles -->
                      <a href="details_obra.php?id=<?= $row['id'] ?>" class="btn-action btn-action--view" title="Ver Detalles de la Obra">
                        <i class="fa-solid fa-circle-info"></i>
                      </a>
                      <!-- Eliminar Obra -->
                      <button class="btn-action btn-action--delete" onclick="eliminarObra(<?= $row['id'] ?>)" title="Eliminar Obra">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div class="orders-pagination-bar">
          <div class="orders-pagination-left">
            <span class="orders-pagination-info">
              <?php
              $inicio_reg = $totalRegistros > 0 ? $offset + 1 : 0;
              $fin_reg    = min($offset + $por_pagina, $totalRegistros);
              ?>
              Mostrando <strong><?= $inicio_reg ?>-<?= $fin_reg ?></strong> de <strong><?= $totalRegistros ?></strong> obras
            </span>
          </div>
          <?php if ($totalPaginas > 1): ?>
            <div class="orders-pagination-controls">
              <nav class="orders-pagination-nav">
                <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>" href="?q=<?= urlencode($busqueda) ?>&proyecto_id=<?= urlencode($proyecto_id) ?>&page=1">&laquo;</a>
                <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>" href="?q=<?= urlencode($busqueda) ?>&proyecto_id=<?= urlencode($proyecto_id) ?>&page=<?= max(1, $pagina - 1) ?>">&lsaquo;</a>
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                  <a class="page-btn page-link <?= $i == $pagina ? 'active' : '' ?>" href="?q=<?= urlencode($busqueda) ?>&proyecto_id=<?= urlencode($proyecto_id) ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>" href="?q=<?= urlencode($busqueda) ?>&proyecto_id=<?= urlencode($proyecto_id) ?>&page=<?= min($totalPaginas, $pagina + 1) ?>">&rsaquo;</a>
                <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>" href="?q=<?= urlencode($busqueda) ?>&proyecto_id=<?= urlencode($proyecto_id) ?>&page=<?= $totalPaginas ?>">&raquo;</a>
              </nav>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <div class="orders-empty-state">
          <i class="fa-solid fa-inbox"></i>
          <p>No hay obras registradas</p>
          <button class="btn-geco-primary" onclick="agregarObra(<?= $proyecto_id_js ?>)">
            <i class="fa-solid fa-circle-plus"></i> Crear primera obra
          </button>
        </div>
      <?php endif; ?>
    </div><!-- /table-container-wrapper -->
  </div>

</div>



<script>
  // Inicializar tooltips de Bootstrap
  document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });

  // Función para agregar obra
  function agregarObra(proyectoId) {
    UI.loading("Cargando proyectos...");
    fetch('get_project.php')
      .then(res => res.json())
      .then(proyectos => {
        UI.loading.hide();
        if (!proyectos || proyectos.error) {
          UI.toast.error("Error al cargar proyectos");
          return;
        }

        let selectHtml = '';
        if (proyectoId) {
          const p = proyectos.find(x => x.id == proyectoId);
          const pNombre = p ? p.nombre_proyecto : 'Proyecto Seleccionado';
          selectHtml = `
                    <input type="hidden" name="proyecto_id" value="${proyectoId}">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proyecto</label>
                        <input type="text" class="form-control" value="${pNombre}" readonly>
                    </div>
                `;
        } else {
          let options = '<option value="">-- Seleccionar Proyecto --</option>';
          proyectos.forEach(p => {
            options += `<option value="${p.id}">${p.nombre_proyecto}</option>`;
          });
          selectHtml = `
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proyecto <span class="text-danger">*</span></label>
                        <select name="proyecto_id" class="form-select" required>${options}</select>
                    </div>
                `;
        }

        UI.modal({
          title: "Nueva Obra",
          size: "lg",
          html: `
                    <form id="formAgregarObra" class="p-2">
                        ${selectHtml}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Número de Obra <span class="text-danger">*</span></label>
                            <input type="text" name="numero_obra" class="form-control" placeholder="Ej: OBRA-2026-01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre de Obra <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_obra" class="form-control" placeholder="Nombre descriptivo de la obra" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Descripción de la Obra</label>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe los detalles de la obra..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Fecha Inicio <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_inicio" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Fecha Fin <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_fin" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Monto Designado <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="monto_designado" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Costo Directo <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="costo_directo" class="form-control" required>
                                <small class="text-muted d-block mt-1">Presupuesto para órdenes de compra</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                            <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Obra</button>
                        </div>
                    </form>
                `
        });

        document.getElementById("formAgregarObra").addEventListener("submit", function(e) {
          e.preventDefault();
          UI.loading("Guardando...");
          fetch("insert_obra.php", {
              method: "POST",
              body: new FormData(this)
            })
            .then(res => res.json())
            .then(data => {
              UI.loading.hide();
              if (data.status === 'success') {
                UI.modal.close();
                UI.toast.success("Obra creada correctamente");
                setTimeout(() => location.reload(), 1200);
              } else {
                UI.toast.error(data.message || "Error al guardar la obra");
              }
            })
            .catch(() => {
              UI.loading.hide();
              UI.toast.error("Error de conexión");
            });
        });
      })
      .catch(err => {
        UI.loading.hide();
        UI.toast.error("No se pudieron cargar los proyectos");
      });
  }


  // Función para editar obra
  function editarObra(obraId) {
    UI.loading("Cargando datos...");
    fetch(`edit_obra.php?id=${obraId}`)
      .then(res => res.json())
      .then(data => {
        if (data.error) {
          UI.loading.hide();
          UI.toast.error(data.error);
          return;
        }

        fetch('get_project.php')
          .then(res => res.json())
          .then(proyectos => {
            UI.loading.hide();
            if (!proyectos || proyectos.error || !Array.isArray(proyectos)) {
              UI.toast.error("Error al cargar la lista de proyectos");
              return;
            }
            let proyectosOptions = '';
            proyectos.forEach(proyecto => {
              proyectosOptions += `<option value="${proyecto.id}" ${proyecto.id == data.proyecto_id ? 'selected' : ''}>${proyecto.nombre_proyecto}</option>`;
            });

            UI.modal({
              title: "Editar Obra",
              size: "lg",
              html: `
                            <form id="formEditarObra" class="p-2">
                                <input type="hidden" name="id" value="${data.id}">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Proyecto <span class="text-danger">*</span></label>
                                    <select name="proyecto_id" class="form-select" required>${proyectosOptions}</select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Número de Obra <span class="text-danger">*</span></label>
                                        <input type="text" name="numero_obra" class="form-control" value="${data.numero_obra}" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Nombre de Obra <span class="text-danger">*</span></label>
                                        <input type="text" name="nombre_obra" class="form-control" value="${data.nombre_obra}" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Descripción de la Obra</label>
                                    <textarea name="descripcion" class="form-control" rows="3">${data.descripcion || ''}</textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Fecha Inicio <span class="text-danger">*</span></label>
                                        <input type="date" name="fecha_inicio" class="form-control" value="${data.fecha_inicio}" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Fecha Fin <span class="text-danger">*</span></label>
                                        <input type="date" name="fecha_fin" class="form-control" value="${data.fecha_fin}" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Monto Designado <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" name="monto_designado" class="form-control" value="${data.monto_designado}" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Costo Directo <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" name="costo_directo" class="form-control" value="${data.costo_directo}" required>
                                        <small class="text-muted d-block mt-1">Presupuesto para órdenes de compra</small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                                    <button type="submit" class="btn btn-warning text-white"><i class="fa-solid fa-floppy-disk me-1"></i>Actualizar Obra</button>
                                </div>
                            </form>
                        `
            });

            document.getElementById("formEditarObra").addEventListener("submit", function(e) {
              e.preventDefault();
              UI.loading("Actualizando...");
              fetch("update_obra.php", {
                  method: "POST",
                  body: new FormData(this)
                })
                .then(res => res.json())
                .then(resp => {
                  UI.loading.hide();
                  if (resp.status === "success") {
                    UI.modal.close();
                    UI.toast.success("Obra actualizada correctamente");
                    setTimeout(() => location.reload(), 1200);
                  } else {
                    UI.toast.error(resp.message || "Error al actualizar la obra");
                  }
                })
                .catch(() => {
                  UI.loading.hide();
                  UI.toast.error("Error de conexión");
                });
            });
          })
          .catch(() => {
            UI.loading.hide();
            UI.toast.error("Error al cargar la lista de proyectos");
          });
      })
      .catch(() => {
        UI.loading.hide();
        UI.toast.error("Error al cargar los datos de la obra");
      });
  }

  // Función para eliminar obra
  function eliminarObra(obraId) {
    UI.confirm({
      title: '¿Eliminar esta obra?',
      message: 'Esta acción no se puede deshacer. Las órdenes de compra asociadas quedarán sin obra asignada.',
      danger: true,
      confirmText: 'Sí, eliminar',
      cancelText: 'Cancelar'
    }).then((confirmed) => {
      if (confirmed) {
        UI.loading("Eliminando...");
        fetch(`delete_obra.php?id=${obraId}`, {
            method: "GET"
          })
          .then(res => res.json())
          .then(resp => {
            UI.loading.hide();
            if (resp.status === "success") {
              UI.toast.success("Obra eliminada correctamente");
              setTimeout(() => location.reload(), 1200);
            } else {
              UI.toast.error(resp.message || "No se pudo eliminar la obra");
            }
          })
          .catch(() => {
            UI.loading.hide();
            UI.toast.error("Error de conexión");
          });
      }
    });
  }

  // Función para ver proyecto
  function verProyecto(proyectoId) {
    window.location.href = `details_project.php?id=${proyectoId}`;
  }

  // Función para gestionar archivos de obra
  function gestionarArchivosObra(obraId) {
    UI.toast.info("La gestión de archivos para obras estará disponible pronto.");
  }

  function verObras(proyectoId) {
    window.location.href = `list_obras.php?proyecto_id=${proyectoId}`;
  }

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

          // Sync search form values from response
          const newSearch = doc.getElementById('search-form');
          if (newSearch) {
            newSearch.querySelectorAll('input, select').forEach(input => {
              const target = searchForm.querySelector(`[name="${input.name}"]`);
              if (target) target.value = input.value;
            });
          }

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

    document.addEventListener('click', function(e) {
      const pageBtn = e.target.closest('.page-btn');
      if (pageBtn) {
        if (pageBtn.classList.contains('disabled')) return;
        e.preventDefault();
        updateList(pageBtn.href);
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
    });

    searchForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const params = new URLSearchParams(new FormData(searchForm));
      params.set('page', '1');
      updateList('?' + params.toString());
    });

    // Autosubmit al cambiar el select de proyecto
    const selectProyecto = document.getElementById('select-proyecto-id');
    if (selectProyecto) {
      selectProyecto.addEventListener('change', () => searchForm.requestSubmit());
    }
  }

  document.addEventListener('DOMContentLoaded', initAJAX);
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>