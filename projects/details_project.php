<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$proyecto_id = $_GET['id'] ?? 0;

// Obtener información del proyecto
$sql = "SELECT p.*, c.nombre as cliente_nombre, c.nombre_abreviado,
        (SELECT COUNT(*) FROM obras WHERE proyecto_id = p.id) as total_obras,
        (SELECT COALESCE(SUM(costo_directo_utilizado), 0) FROM presupuesto_control 
         WHERE proyecto_id = p.id AND obra_id IS NULL) as costo_directo_utilizado
        FROM proyectos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $proyecto_id);
$stmt->execute();
$result = $stmt->get_result();
$proyecto = $result->fetch_assoc();

if (!$proyecto) {
  header("Location: list_project.php");
  exit;
}

// Obtener obras del proyecto
$sql_obras = "SELECT o.*, 
             (SELECT COALESCE(SUM(costo_directo_utilizado), 0) FROM presupuesto_control 
              WHERE obra_id = o.id) as costo_directo_utilizado
             FROM obras o 
             WHERE o.proyecto_id = ? 
             ORDER BY o.numero_obra";
$stmt_obras = $conn->prepare($sql_obras);
$stmt_obras->bind_param("i", $proyecto_id);
$stmt_obras->execute();
$obras = $stmt_obras->get_result();

$costo_disponible_proyecto = $proyecto['costo_directo'] - $proyecto['costo_directo_utilizado'];
$porcentaje_utilizado_proyecto = $proyecto['costo_directo'] > 0 ?
  ($proyecto['costo_directo_utilizado'] / $proyecto['costo_directo']) * 100 : 0;
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>



<?php include __DIR__ . "/../includes/navbar.php"; ?>
<title>Detalles del Proyecto | GECO PROATAM</title>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="list_project.php">Proyectos</a>
        <span class="separator">›</span>
        <span>Detalles del Proyecto</span>
      </nav>
      <h1 class="orders-page-title"><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></h1>
      <div class="text-muted small mt-1">
        Periodo: <?= date('d/m/Y', strtotime($proyecto['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($proyecto['fecha_fin'])) ?>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="actions-group">
      <a href="list_project.php" class="btn-geco-outline">
        <i class="fa-solid fa-arrow-left"></i> Volver
      </a>
      <button class="btn-geco-secondary" onclick="editarProyecto(<?= $proyecto_id ?>)" title="Editar Proyecto">
        <i class="fa-solid fa-pen-to-square"></i> Editar
      </button>
      <button class="btn-geco-secondary" onclick="gestionarArchivos(<?= $proyecto_id ?>)" title="Archivos PDF">
        <i class="fa-solid fa-paperclip"></i> Archivos
      </button>
      <button class="btn-geco-secondary" onclick="verObras(<?= $proyecto_id ?>)" title="Gestionar Obras">
        <i class="fa-solid fa-helmet-safety"></i> Obras
      </button>
      <button class="btn-geco-primary" onclick="exportarExcelProyecto()" title="Generar Reporte Excel">
        <i class="fa-solid fa-file-excel"></i> Reporte Excel
      </button>
    </div>
  </div>

  <!-- KPI Budget Dashboard -->
  <div class="kpi-grid kpi-grid--3 mb-4">
    <div class="kpi-card kpi-card--green">
      <p class="kpi-card__label">Costo Directo</p>
      <p class="kpi-card__value">$<?= number_format($proyecto['costo_directo'], 2) ?></p>
      <p class="kpi-card__sub">Presupuesto inicial contratado</p>
    </div>

    <div class="kpi-card kpi-card--amber">
      <p class="kpi-card__label">Monto Utilizado</p>
      <p class="kpi-card__value">$<?= number_format($proyecto['costo_directo_utilizado'], 2) ?></p>
      <p class="kpi-card__sub">Asignado a obras</p>
    </div>

    <div class="kpi-card <?= $costo_disponible_proyecto < 0 ? 'kpi-card--red' : 'kpi-card--green' ?>">
      <p class="kpi-card__label">Monto Disponible</p>
      <p class="kpi-card__value">$<?= number_format($costo_disponible_proyecto, 2) ?></p>
      <p class="kpi-card__sub">Presupuesto libre</p>
    </div>
  </div>

  <!-- Info Panels (General & Financial) -->
  <div class="row g-4 mb-4">
    <!-- Información General -->
    <div class="col-md-6">
      <div class="orders-card p-4 h-100">
        <h6 class="orders-card-section-title">
          <i class="fa-solid fa-circle-info"></i> Información General
        </h6>
        <div class="d-flex flex-column gap-3">
          <div class="geco-field">
            <span class="geco-field__label">Cliente</span>
            <span class="geco-field__value"><?= htmlspecialchars($proyecto['cliente_nombre'] ?? 'No asignado') ?></span>
          </div>
          <div class="geco-field">
            <span class="geco-field__label">Licitación</span>
            <span class="geco-field__value"><?= htmlspecialchars($proyecto['numero_licitacion']) ?></span>
          </div>
          <div class="geco-field">
            <span class="geco-field__label">Contrato</span>
            <span class="geco-field__value"><?= htmlspecialchars($proyecto['numero_contrato']) ?></span>
          </div>
          <div class="geco-field">
            <span class="geco-field__label">Descripción</span>
            <span class="geco-field__value"><?= htmlspecialchars($proyecto['descripcion'] ?? 'Sin descripción') ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Información Financiera -->
    <div class="col-md-6">
      <div class="orders-card p-4 h-100">
        <h6 class="orders-card-section-title">
          <i class="fa-solid fa-money-bill-trend-up"></i> Información Financiera
        </h6>
        <div class="d-flex flex-column gap-3">
          <div class="row g-3">
            <div class="col-6">
              <div class="geco-field">
                <span class="geco-field__label">Monto Designado</span>
                <span class="geco-field__value fw-bold">$<?= number_format($proyecto['monto_designado'], 2) ?></span>
              </div>
            </div>
            <div class="col-6">
              <div class="geco-field">
                <span class="geco-field__label">Monto con IVA</span>
                <span class="geco-field__value fw-bold">$<?= number_format($proyecto['monto_con_iva'], 2) ?></span>
              </div>
            </div>
            <div class="col-6">
              <div class="geco-field">
                <span class="geco-field__label">Anticipo</span>
                <span class="geco-field__value fw-bold">$<?= number_format($proyecto['monto_anticipo'], 2) ?></span>
              </div>
            </div>
            <div class="col-6">
              <div class="geco-field">
                <span class="geco-field__label">Costo Directo</span>
                <span class="geco-field__value fw-bold" style="color: var(--p-600);">$<?= number_format($proyecto['costo_directo'], 2) ?></span>
              </div>
            </div>
          </div>
          <div class="border-top pt-3">
            <span class="geco-field__label d-block mb-2">Progreso de Presupuesto Directo</span>
            <div class="d-flex align-items-center gap-3">
              <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px;">
                <div class="progress-bar <?= $porcentaje_utilizado_proyecto > 90 ? 'bg-danger' : ($porcentaje_utilizado_proyecto > 70 ? 'bg-warning' : 'bg-success') ?>"
                  role="progressbar" style="width: <?= min($porcentaje_utilizado_proyecto, 100) ?>%"></div>
              </div>
              <span class="fw-bold small text-dark"><?= number_format($porcentaje_utilizado_proyecto, 1) ?>%</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sub-table of Works -->
  <?php if ($proyecto['total_obras'] > 0): ?>
    <div class="orders-card mb-4">
      <div class="p-3 border-bottom">
        <h6 class="orders-card-section-title mb-0"><i class="fa-solid fa-helmet-safety"></i> Obras del Proyecto</h6>
      </div>
      <div class="p-3">
        <div class="orders-table-wrap">
          <table class="orders-table">
            <thead>
              <tr>
                <th>Obra</th>
                <th>Periodo</th>
                <th>Costo Directo Disp.</th>
                <th>Progreso</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($obra = $obras->fetch_assoc()):
                $costo_disponible_obra = $obra['costo_directo'] - $obra['costo_directo_utilizado'];
                $porcentaje_utilizado_obra = $obra['costo_directo'] > 0 ?
                  ($obra['costo_directo_utilizado'] / $obra['costo_directo']) * 100 : 0;
                $progress_class = $porcentaje_utilizado_obra > 90 ? 'bg-danger' : ($porcentaje_utilizado_obra > 70 ? 'bg-warning' : 'bg-success');
              ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <span class="fw-bold text-dark"><?= htmlspecialchars($obra['nombre_obra']) ?></span>
                      <span class="text-muted small">#<?= htmlspecialchars($obra['numero_obra']) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <span class="small text-muted">Inicio: <?= date('d/m/Y', strtotime($obra['fecha_inicio'])) ?></span>
                      <span class="small text-muted">Fin: <?= date('d/m/Y', strtotime($obra['fecha_fin'])) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="fw-bold <?= $costo_disponible_obra < 0 ? 'text-danger' : 'text-success' ?>">
                      $<?= number_format($costo_disponible_obra, 2) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <div class="progress flex-grow-1" style="height: 6px;">
                        <div class="progress-bar <?= $progress_class ?>" role="progressbar" style="width: <?= min($porcentaje_utilizado_obra, 100) ?>%"></div>
                      </div>
                      <span class="small fw-semibold text-dark"><?= number_format($porcentaje_utilizado_obra, 1) ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="orders-pagination-bar">
        <div class="orders-pagination-left">
          <span class="orders-pagination-info">
            Mostrando <strong>1-<?= $proyecto['total_obras'] ?></strong> de <strong><?= $proyecto['total_obras'] ?></strong> obras
          </span>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>



<script>
  // Inicializar tooltips de Bootstrap
  document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });

  function editarProyecto(id) {
    UI.loading("Cargando clientes...");
    fetch('get_clientes.php')
      .then(res => res.json())
      .then(clientes => {
        let clientesOptions = '<option value="">-- Seleccionar Cliente --</option>';
        clientes.forEach(cliente => {
          clientesOptions += `<option value="${cliente.id}">${cliente.nombre_abreviado || cliente.nombre}</option>`;
        });

        fetch(`edit_project.php?id=${id}`)
          .then(res => res.json())
          .then(data => {
            UI.loading.hide();
            if (data.error) {
              UI.toast.error(data.error);
              return;
            }

            UI.modal({
              title: "Editar Proyecto",
              size: "lg",
              html: `
                <form id="formEditarProyecto" class="p-2">
                  <input type="hidden" name="id" value="${data.id}">
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente</label>
                    <select name="cliente_id" class="form-select" id="selectCliente">${clientesOptions}</select>
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Número de Licitación *</label>
                      <input type="text" name="numero_licitacion" class="form-control" value="${data.numero_licitacion}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Número de Contrato *</label>
                      <input type="text" name="numero_contrato" class="form-control" value="${data.numero_contrato}" required>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre del Proyecto *</label>
                    <input type="text" name="nombre_proyecto" class="form-control" value="${data.nombre_proyecto}" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción del Proyecto</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe los detalles del proyecto...">${data.descripcion || ''}</textarea>
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Fecha Inicio *</label>
                      <input type="date" name="fecha_inicio" class="form-control" value="${data.fecha_inicio}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Fecha Fin *</label>
                      <input type="date" name="fecha_fin" class="form-control" value="${data.fecha_fin}" required>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Monto Designado *</label>
                      <input type="number" step="0.01" name="monto_designado" class="form-control" value="${data.monto_designado}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Monto de Anticipo *</label>
                      <input type="number" step="0.01" name="monto_anticipo" class="form-control" value="${data.monto_anticipo}" required>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Monto con IVA *</label>
                      <input type="number" step="0.01" name="monto_con_iva" class="form-control" value="${data.monto_con_iva}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-semibold">Costo Directo *</label>
                      <input type="number" step="0.01" name="costo_directo" class="form-control" value="${data.costo_directo}" required>
                      <small class="text-muted d-block mt-1">Presupuesto disponible para órdenes de compra</small>
                    </div>
                  </div>
                  <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Cambios</button>
                  </div>
                </form>
              `
            });

            if (data.cliente_id) {
              document.getElementById('selectCliente').value = data.cliente_id;
            }

            document.getElementById("formEditarProyecto").addEventListener("submit", function(e) {
              e.preventDefault();
              UI.loading("Actualizando...");
              fetch("update_project.php", {
                  method: "POST",
                  body: new FormData(this)
                })
                .then(res => res.json())
                .then(resp => {
                  UI.loading.hide();
                  if (resp.status === "success") {
                    UI.modal.close();
                    UI.toast.success("Proyecto actualizado correctamente");
                    setTimeout(() => location.reload(), 1200);
                  } else {
                    UI.toast.error(resp.message || "Error al actualizar");
                  }
                })
                .catch(() => {
                  UI.loading.hide();
                  UI.toast.error("Error de conexión");
                });
            });
          });
      })
      .catch(() => {
        UI.loading.hide();
        UI.toast.error("No se pudieron cargar los clientes");
      });
  }

  function gestionarArchivos(proyectoId) {
    UI.loading("Obteniendo archivos...");
    fetch(`get_archivos.php?proyecto_id=${proyectoId}`)
      .then(res => res.json())
      .then(data => {
        UI.loading.hide();
        let archivosHtml = `
          <div class="mb-4 p-2">
            <form id="formSubirArchivo" enctype="multipart/form-data">
              <input type="hidden" name="proyecto_id" value="${proyectoId}">
              <div class="mb-3">
                <label class="form-label fw-semibold">Subir archivo PDF (Máximo 5 archivos)</label>
                <input type="file" name="archivo" class="form-control" accept=".pdf" required>
                <small class="text-muted d-block mt-1">Tamaño máximo: 10MB</small>
              </div>
              <button type="button" class="btn btn-primary w-100" onclick="subirArchivo()">
                <i class="fa-solid fa-upload me-1"></i> Subir PDF
              </button>
            </form>
          </div>
          <hr>
        `;

        if (data.archivos && data.archivos.length > 0) {
          archivosHtml += '<div class="list-group mt-3 p-2">';
          data.archivos.forEach(archivo => {
            archivosHtml += `
              <div class="list-group-item d-flex justify-content-between align-items-center rounded mb-2 border">
                <div>
                  <i class="fa-solid fa-file-pdf text-danger me-2" style="font-size:1.2rem;"></i>
                  <strong>${archivo.nombre_archivo}</strong>
                  <br>
                  <small class="text-muted">Subido: ${archivo.fecha_subida}</small>
                </div>
                <div class="btn-group gap-1">
                  <button class="btn btn-sm btn-outline-info" onclick="verPDF('${archivo.ruta_archivo}')" title="Ver PDF">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="eliminarArchivo(${archivo.id}, ${proyectoId})" title="Eliminar">
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
                </div>
              </div>
            `;
          });
          archivosHtml += '</div>';
        } else {
          archivosHtml += '<div class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open d-block mb-2" style="font-size: 2rem;"></i> No hay archivos adjuntos</div>';
        }

        UI.modal({
          title: 'Gestión de Archivos PDF',
          size: 'lg',
          html: archivosHtml
        });
      });
  }

  function verObras(proyectoId) {
    window.location.href = `list_obras.php?proyecto_id=${proyectoId}`;
  }

  function subirArchivo() {
    const form = document.getElementById('formSubirArchivo');
    const formData = new FormData(form);

    UI.loading("Subiendo...");
    fetch('upload_archivo.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        UI.loading.hide();
        if (data.status === 'success') {
          UI.toast.success('Archivo subido correctamente');
          gestionarArchivos(formData.get('proyecto_id'));
        } else {
          UI.toast.error(data.message);
        }
      })
      .catch(error => {
        UI.loading.hide();
        UI.toast.error('Error de conexión');
      });
  }

  function eliminarArchivo(archivoId, proyectoId) {
    UI.confirm({
      title: '¿Eliminar archivo?',
      message: 'Esta acción no se puede deshacer',
      danger: true
    }).then((confirmed) => {
      if (confirmed) {
        UI.loading("Eliminando...");
        fetch(`delete_archivo.php?id=${archivoId}`)
          .then(res => res.json())
          .then(data => {
            UI.loading.hide();
            if (data.status === 'success') {
              UI.toast.success('Archivo eliminado');
              gestionarArchivos(proyectoId);
            } else {
              UI.toast.error(data.message);
            }
          })
          .catch(() => {
            UI.loading.hide();
            UI.toast.error('Error de conexión');
          });
      }
    });
  }

  function verPDF(ruta) {
    if (ruta && ruta.startsWith('uploads/')) {
      window.open(ruta, '_blank');
    } else {
      UI.toast.error('Ruta de archivo no válida');
    }
  }

  // --- EXPORTACIÓN A EXCEL POR PROYECTO ---
  function exportarExcelProyecto() {
    const wb = XLSX.utils.book_new();

    // 1. Hoja: Información General y Financiera
    const genHeader = [
      ["RESUMEN DEL PROYECTO"]
    ];
    const genData = [
      [""],
      ["DATOS GENERALES", ""],
      ["Proyecto", <?= json_encode($proyecto['nombre_proyecto']) ?>],
      ["Cliente", <?= json_encode($proyecto['cliente_nombre'] ?? 'No asignado') ?>],
      ["Licitación", <?= json_encode($proyecto['numero_licitacion']) ?>],
      ["Contrato", <?= json_encode($proyecto['numero_contrato']) ?>],
      ["Descripción", <?= json_encode($proyecto['descripcion'] ?? '') ?>],
      ["Fecha Inicio", <?= json_encode($proyecto['fecha_inicio']) ?>],
      ["Fecha Fin", <?= json_encode($proyecto['fecha_fin']) ?>],
      [""],
      ["RESUMEN FINANCIERO", ""],
      ["Monto Designado", <?= (float)$proyecto['monto_designado'] ?>],
      ["Monto con IVA", <?= (float)$proyecto['monto_con_iva'] ?>],
      ["Anticipo", <?= (float)$proyecto['monto_anticipo'] ?>],
      ["Costo Directo Total", <?= (float)$proyecto['costo_directo'] ?>],
      ["Costo Utilizado", <?= (float)$proyecto['costo_directo_utilizado'] ?>],
      ["Costo Disponible", <?= (float)$costo_disponible_proyecto ?>]
    ];
    const wsGen = XLSX.utils.aoa_to_sheet(genHeader.concat(genData));
    wsGen['!cols'] = [{
      wch: 25
    }, {
      wch: 60
    }];
    XLSX.utils.book_append_sheet(wb, wsGen, "Gral y Finanzas");

    // 2. Preparar datos agrupados por Obra (PHP -> JS)
    <?php
    $obras_data = [];
    $stmt_obras_exp = $conn->prepare("SELECT id, nombre_obra, numero_obra, costo_directo FROM obras WHERE proyecto_id = ? ORDER BY numero_obra");
    $stmt_obras_exp->bind_param("i", $proyecto_id);
    $stmt_obras_exp->execute();
    $obras_list = $stmt_obras_exp->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($obras_list as $obra) {
      $oid = $obra['id'];

      // Conceptos de esta obra
      $catalogo_id = 0;
      $st_cat = $conn->prepare("SELECT id FROM catalogos WHERE obra_id = ?");
      $st_cat->bind_param("i", $oid);
      $st_cat->execute();
      $res_cat = $st_cat->get_result()->fetch_assoc();
      if ($res_cat) {
        $catalogo_id = $res_cat['id'];
      }

      $conceptos_raw = [];

      if ($catalogo_id > 0) {
        $sn = $conn->prepare("SELECT id, parent_id, clave, titulo as descripcion, nivel, sort_path FROM concepto_nodos WHERE catalogo_id = ? ORDER BY sort_path ASC");
        $sn->bind_param("i", $catalogo_id);
        $sn->execute();
        $res_nodos = $sn->get_result();
        $nodos_por_id = [];
        while ($n = $res_nodos->fetch_assoc()) {
          $nodos_por_id[(int)$n['id']] = $n + ['hijos' => [], 'conceptos' => []];
        }

        $sc = $conn->prepare("SELECT id as id_obj, codigo_concepto as clave, descripcion, numero_original as numero, unidad_medida as unidad, cantidad as cant, precio_unitario as pu, importe as imp, nodo_id FROM conceptos WHERE catalogo_id = ? ORDER BY CAST(NULLIF(numero_original, '') AS UNSIGNED) ASC, clave ASC");
        $sc->bind_param("i", $catalogo_id);
        $sc->execute();
        $res_c = $sc->get_result();
        $conceptos_sin_nodo = [];
        while ($c = $res_c->fetch_assoc()) {
          $c['tipo'] = 'CONCEPTO';
          $nid = $c['nodo_id'] ? (int)$c['nodo_id'] : null;
          if ($nid && isset($nodos_por_id[$nid])) {
            $nodos_por_id[$nid]['conceptos'][] = $c;
          } else {
            $conceptos_sin_nodo[] = $c;
          }
        }

        $raices = [];
        foreach ($nodos_por_id as $id => &$nodo) {
          $pid = $nodo['parent_id'] ? (int)$nodo['parent_id'] : null;
          if ($pid && isset($nodos_por_id[$pid])) {
            $nodos_por_id[$pid]['hijos'][] = &$nodo;
          } else {
            $raices[] = &$nodo;
          }
        }
        unset($nodo);

        $flattenTree = function ($nodo) use (&$flattenTree, &$conceptos_raw) {
          $conceptos_raw[] = [
            'tipo' => 'NODO',
            'clave' => $nodo['clave'],
            'descripcion' => $nodo['descripcion'],
            'numero' => '',
            'unidad' => '',
            'cant' => 0,
            'pu' => 0,
            'imp' => 0,
            'nivel' => $nodo['nivel'],
            'id_obj' => 0
          ];
          foreach ($nodo['conceptos'] as $c) {
            $c['nivel'] = $nodo['nivel'] + 1;
            $conceptos_raw[] = $c;
          }
          foreach ($nodo['hijos'] as $hijo) {
            $flattenTree($hijo);
          }
        };

        foreach ($raices as $raiz) {
          $flattenTree($raiz);
        }

        foreach ($conceptos_sin_nodo as $c) {
          $c['nivel'] = 1;
          $conceptos_raw[] = $c;
        }
      }

      $conceptos_procesados = [];
      foreach ($conceptos_raw as $c) {
        $compras = [];
        if ($c['tipo'] === 'CONCEPTO') {
          $sql_it = "SELECT oci.descripcion, u.nombre as unidad, oci.cantidad, oci.precio_unitario, oci.subtotal, oc.folio, p.nombre as proveedor, oc.estado
                         FROM orden_compra_items oci 
                         JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                         LEFT JOIN proveedores p ON oc.proveedor_id = p.id
                         LEFT JOIN unidades u ON oci.unidad_id = u.id
                         WHERE oci.concepto_id = ?";
          $st_it = $conn->prepare($sql_it);
          $st_it->bind_param("i", $c['id_obj']);
          $st_it->execute();
          $compras = $st_it->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $c['compras'] = $compras;
        $conceptos_procesados[] = $c;
      }

      $obras_data[] = [
        'info' => $obra,
        'data' => $conceptos_procesados
      ];
    }
    ?>

    const dataObras = <?= json_encode($obras_data) ?>;

    dataObras.forEach(obra => {
      const sheetName = (obra.info.nombre_obra.substring(0, 25) || "Obra") + " (" + obra.info.numero_obra + ")";

      const rows = [
        ["DETALLE INTEGRAL: " + obra.info.nombre_obra.toUpperCase() + " (#" + obra.info.numero_obra + ")"],
        [""],
        ["PRESUPUESTO ASIGNADO", "", "", "", "", "", "", "|", "DETALLE DE COMPRAS VINCULADAS (ORDENES DE COMPRA)"],
        ["NÚM.", "CLAVE", "DESCRIPCIÓN", "UNIDAD", "CANT.", "P.U.", "IMPORTE", "|", "FOLIO OC", "PROVEEDOR", "ITEM COMPRA", "CANT.", "P.U.", "SUBTOTAL", "ESTADO"]
      ];

      obra.data.forEach(c => {
        let indent = "";
        if (c.tipo === 'NODO') {
          indent = " ".repeat((c.nivel - 1) * 2) + " ";
        } else {
          indent = " ".repeat((c.nivel - 1) * 2) + "   ";
        }

        const baseRow = [
          c.numero || "",
          c.clave || "",
          indent + (c.descripcion || ""),
          c.unidad || "",
          c.tipo === 'NODO' ? "" : (parseFloat(c.cant) || 0),
          c.tipo === 'NODO' ? "" : (parseFloat(c.pu) || 0),
          c.tipo === 'NODO' ? "" : (parseFloat(c.imp) || 0),
          "|"
        ];

        if (c.compras.length === 0) {
          rows.push(baseRow.concat(["---", "---", "Sin compras registradas", "", "", "", ""]));
        } else {
          c.compras.forEach((compra, idx) => {
            const compraData = [
              compra.folio || "",
              compra.proveedor || "Desconocido",
              compra.descripcion || "",
              compra.unidad || "",
              parseFloat(compra.cantidad) || 0,
              parseFloat(compra.precio_unitario) || 0,
              parseFloat(compra.subtotal) || 0,
              compra.estado ? compra.estado.toUpperCase() : ""
            ];

            if (idx === 0) {
              rows.push(baseRow.concat(compraData));
            } else {
              const emptyBase = ["", "", "", "", "", "", "", "|"];
              rows.push(emptyBase.concat(compraData));
            }
          });
        }

        if (c.tipo === 'NODO') rows.push([""]);
      });

      const ws = XLSX.utils.aoa_to_sheet(rows);

      ws['!cols'] = [{
          wch: 8
        }, {
          wch: 15
        }, {
          wch: 50
        }, {
          wch: 10
        }, {
          wch: 10
        }, {
          wch: 12
        }, {
          wch: 15
        }, {
          wch: 3
        },
        {
          wch: 12
        }, {
          wch: 25
        }, {
          wch: 40
        }, {
          wch: 10
        }, {
          wch: 12
        }, {
          wch: 15
        }, {
          wch: 12
        }
      ];
      let safeSheetName = sheetName.replace(/[\[\]\*\?\/\\]/g, '').replace(/:/g, '-');
      safeSheetName = safeSheetName.substring(0, 31);
      XLSX.utils.book_append_sheet(wb, ws, safeSheetName);
    });

    const pName = <?= json_encode($proyecto['nombre_proyecto']) ?>.substring(0, 30).replace(/[^a-z0-9]/gi, '_');
    const dStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `Reporte_Proyecto_${pName}_${dStr}.xlsx`);
  }
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>