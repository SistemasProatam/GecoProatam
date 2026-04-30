<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . '/../config.php';

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

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

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($proyecto['nombre_proyecto']) ?> - Detalles</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/details.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>

<body>
  <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
include __DIR__ . "/../includes/navbar.php"; ?>

  <!-- HERO SECTION -->
  <div class="hero-section">
    <div class="container hero-content">
      <div class="breadcrumb-custom">
        <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
        <span>/</span>
        <a href="list_project.php"> Registro de Proyectos</a>
        <span>/</span>
        <span>Detalles del Proyecto</span>
      </div>

      <div class="row align-items-end">
        <div class="col-lg-8">
          <h3 class="hero-title" style="font-size: 18px"><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></h3>
          <div style="color: #ddd; font-size: 14px; margin-top: -5px;">
            <p>Periodo:
              <?= date('d/m/Y', strtotime($proyecto['fecha_inicio'])) ?>
              -
              <?= date('d/m/Y', strtotime($proyecto['fecha_fin'])) ?>
            </p>
          </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="btn-group" style="gap:5px;">
          <button class="btn-ed" onclick="editarProyecto(<?= $proyecto_id ?>)"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Editar Proyecto">
            <i class="bi bi-pencil"></i>
          </button>

          <button class="btn-inf" onclick="gestionarArchivos(<?= $proyecto_id ?>)"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Archivos PDF">
            <i class="bi bi-paperclip"></i>
          </button>

          <button class="btn-add-oc" onclick="verObras(<?= $proyecto_id ?>)"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Gestionar Obras">
            <i class="bi bi-cone-striped"></i>
          </button>

          <button class="btn-add-oc" onclick="exportarExcelProyecto()"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Generar Reporte Excel">
            <i class="bi bi-filetype-xlsx"></i>
          </button>
        </div>

      </div>
    </div>

  </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content-wrapper">

    <!-- BUDGET DASHBOARD -->
    <div class="budget-dashboard">
      <div class="dashboard-header">
        <div class="dashboard-title">
          <div class="title-icon">
            <i class="bi bi-pie-chart"></i>
          </div>
          <h3>Control de Presupuesto</h3>
        </div>
      </div>

      <div class="budget-stats">
        <div class="budget-stat">
          <div class="budget-stat-label">Costo Directo</div>
          <div class="budget-stat-value">$<?= number_format($proyecto['costo_directo'], 2) ?></div>
        </div>

        <div class="budget-stat">
          <div class="budget-stat-label">Utilizado</div>
          <div class="budget-stat-value">$<?= number_format($proyecto['costo_directo_utilizado'], 2) ?></div>
        </div>

        <div class="budget-stat">
          <div class="budget-stat-label">Disponible</div>
          <div class="budget-stat-value <?= $costo_disponible_proyecto < 0 ? 'danger' : 'success' ?>">
            $<?= number_format($costo_disponible_proyecto, 2) ?>
          </div>
        </div>
      </div>
    </div>

    <!-- INFO PANELS -->
    <div class="info-grid gap-4">

      <!-- Información General -->
      <div class="info-panel">
        <div class="panel-header">
          <div class="panel-icon">
            <i class="bi bi-info-circle"></i>
          </div>
          <h4>Información General</h4>
        </div>

        <ul class="info-list">
          <li class="info-item">
            <span class="info-label">Cliente</span>
            <span class="info-value"><?= htmlspecialchars($proyecto['cliente_nombre'] ?? 'No asignado') ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Licitación</span>
            <span class="info-value"><?= htmlspecialchars($proyecto['numero_licitacion']) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Contrato</span>
            <span class="info-value"><?= htmlspecialchars($proyecto['numero_contrato']) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Descripción</span>
            <span class="info-value"><?= htmlspecialchars($proyecto['descripcion'] ?? 'Sin descripción') ?></span>
          </li>
        </ul>
      </div>

      <!-- Información Financiera -->
      <div class="info-panel">
        <div class="panel-header">
          <div class="panel-icon">
            <i class="bi bi-cash-stack"></i>
          </div>
          <h4>Información Financiera</h4>
        </div>
        <ul class="info-list">
          <li class="info-item">
            <span class="info-label">Monto Desginado</span>
            <span class="info-value">$<?= number_format($proyecto['monto_designado'], 2) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Monto con IVA</span>
            <span class="info-value">$<?= number_format($proyecto['monto_con_iva'], 2) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Anticipo</span>
            <span class="info-value">$<?= number_format($proyecto['monto_anticipo'], 2) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Costo Directo</span>
            <span class="info-value">$<?= number_format($proyecto['costo_directo'], 2) ?></span>
          </li>
        </ul>
      </div>
    </div>

    <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
if ($proyecto['total_obras'] > 0): ?>
      <!-- WORKS SECTION -->
      <div class="works-section">
        <div class="section-header">
          <div class="section-title-group">
            <h4>Obras del Proyecto</h4>
            <span class="count-badge"><?= $proyecto['total_obras'] ?></span>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="modern-table">
            <thead>
              <tr>
                <th style="width: 15%;">Obra</th>
                <th style="width: 15%;">Periodo</th>
                <th style="width: 15%;">Disponible</th>
                <th style="width: 55%; min-width: 180px;">Progreso</th>
              </tr>
            </thead>
            <tbody>
              <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
while ($obra = $obras->fetch_assoc()):
                $costo_disponible_obra = $obra['costo_directo'] - $obra['costo_directo_utilizado'];
                $porcentaje_utilizado_obra = $obra['costo_directo'] > 0 ?
                  ($obra['costo_directo_utilizado'] / $obra['costo_directo']) * 100 : 0;
              ?>
                <tr>
                  <td>
                    <div class="work-cell">
                      <span class="work-title"><?= htmlspecialchars($obra['nombre_obra']) ?></span>
                      <span class="work-subtitle">#<?= htmlspecialchars($obra['numero_obra']) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="work-cell">
                      <span class="work-subtitle"><?= date('d/m/Y', strtotime($obra['fecha_inicio'])) ?></span>
                      <span class="work-subtitle"><?= date('d/m/Y', strtotime($obra['fecha_fin'])) ?></span>
                    </div>
                  </td>
                  <td class="amount-cell">
                    <span style="color: <?= $costo_disponible_obra < 0 ? 'var(--danger)' : 'var(--success)' ?>">
                      $<?= number_format($costo_disponible_obra, 2) ?>
                    </span>
                  </td>
                  <td>
                    <div class="progress-cell">
                      <div class="mini-progress">
                        <div class="mini-progress-fill <?= $porcentaje_utilizado_obra > 90 ? 'danger' : ($porcentaje_utilizado_obra > 70 ? 'warning' : 'success') ?>"
                          style="width: <?= min($porcentaje_utilizado_obra, 100) ?>%">
                        </div>
                      </div>
                      <span class="progress-text" style="color: var(--primary);"><?= number_format($porcentaje_utilizado_obra, 1) ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
endif; ?>

  </div>

  <!-- FLOATING BACK BUTTON -->
  <div class="fab-container-backbtn">
    <a href="list_project.php" class="fab-button-backbtn gray">
      <i class="bi bi-arrow-left"></i>
      <span class="fab-tooltip-backbtn">Volver</span>
    </a>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    // Inicializar tooltips de Bootstrap
    document.addEventListener('DOMContentLoaded', function() {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });

    function editarProyecto(id) {
      // Obtener lista de clientes activos
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
              if (data.error) {
                Swal.fire("Error", data.error, "error");
                return;
              }

              Swal.fire({
                title: "Editar Proyecto",
                html: `
          <form id="formEditarProyecto" class="swal-form">
            <input type="hidden" name="id" value="${data.id}">

            <div class="mb-2">
              <label class="form-label">Cliente</label>
              <select name="cliente_id" class="form-select" id="selectCliente">${clientesOptions}</select>
            </div>

            <div class="mb-2">
              <label class="form-label">Número de Licitación</label>
              <input type="text" name="numero_licitacion" class="form-control" value="${data.numero_licitacion}" required>
            </div>

            <div class="mb-2">
              <label class="form-label">Número de Contrato</label>
              <input type="text" name="numero_contrato" class="form-control" value="${data.numero_contrato}" required>
            </div>

            <div class="mb-2">
              <label class="form-label">Nombre del Proyecto</label>
              <input type="text" name="nombre_proyecto" class="form-control" value="${data.nombre_proyecto}" required>
            </div>

            <!-- Descripción del Proyecto -->
            <div class="mb-2">
              <label class="form-label">Descripción del Proyecto</label>
              <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe los objetivos, alcance y características principales del proyecto...">${data.descripcion || ''}</textarea>
            </div>

            <div class="row">
              <div class="col-6 mb-2">
                <label class="form-label">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control" value="${data.fecha_inicio}" required>
              </div>
              <div class="col-6 mb-2">
                <label class="form-label">Fecha Fin</label>
                <input type="date" name="fecha_fin" class="form-control" value="${data.fecha_fin}" required>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Monto Designado</label>
              <input type="number" step="0.01" name="monto_designado" class="form-control" value="${data.monto_designado}" required>
            </div>

            <div class="mb-2">
              <label class="form-label">Monto de Anticipo</label>
              <input type="number" step="0.01" name="monto_anticipo" class="form-control" value="${data.monto_anticipo}" required>
            </div>

            <div class="mb-2">
              <label class="form-label">Monto con IVA</label>
              <input type="number" step="0.01" name="monto_con_iva" class="form-control" value="${data.monto_con_iva}" required>
            </div>

            <div class="mb-2">
              <label class="form-label">Costo Directo</label>
              <input type="number" step="0.01" name="costo_directo" class="form-control" value="${data.costo_directo}" required>
              <small class="text-muted">Presupuesto disponible para órdenes de compra cuando no hay obras</small>
            </div>
          </form>
        `,
                width: 600,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: "Actualizar",
                cancelButtonText: "Cancelar",
                didOpen: () => {
                  // Seleccionar el cliente actual después de que el modal se abra
                  if (data.cliente_id) {
                    document.getElementById('selectCliente').value = data.cliente_id;
                  }
                },
                preConfirm: () => {
                  const form = document.getElementById("formEditarProyecto");
                  const formData = new FormData(form);

                  return fetch("update_project.php", {
                      method: "POST",
                      body: formData
                    })
                    .then(res => res.json())
                    .then(resp => {
                      if (resp.status === "success") {
                        Swal.fire("¡Éxito!", "Proyecto actualizado correctamente", "success")
                          .then(() => location.reload());
                      } else {
                        Swal.showValidationMessage(resp.message || "Error al actualizar el proyecto");
                      }
                    })
                    .catch(() => Swal.showValidationMessage("Error de conexión"));
                }
              });
            });
        })
        .catch(error => {
          console.error('Error al cargar clientes:', error);
          Swal.fire('Error', 'No se pudieron cargar los clientes', 'error');
        });
    }

    function gestionarArchivos(proyectoId) {
      fetch(`get_archivos.php?proyecto_id=${proyectoId}`)
        .then(res => res.json())
        .then(data => {
          let archivosHtml = `
        <div class="mb-3">
          <form id="formSubirArchivo" enctype="multipart/form-data">
            <input type="hidden" name="proyecto_id" value="${proyectoId}">
            <div class="mb-2">
              <label class="form-label">Subir archivo PDF (Máximo 5 archivos)</label>
              <input type="file" name="archivo" class="form-control" accept=".pdf" required>
              <small class="text-muted">Tamaño máximo: 10MB</small>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="subirArchivo()">
              <i class="bi bi-upload"></i> Subir PDF
            </button>
          </form>
        </div>
        <hr>
      `;

          if (data.archivos.length > 0) {
            archivosHtml += '<div class="list-group">';
            data.archivos.forEach(archivo => {
              archivosHtml += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <i class="bi bi-file-pdf text-danger"></i>
                ${archivo.nombre_archivo}
                <br>
                <small class="text-muted">Subido: ${archivo.fecha_subida}</small>
              </div>
              <div>
                <button class="btn btn-sm btn-info" onclick="verPDF('${archivo.ruta_archivo}')">
                  <i class="bi bi-eye"></i> Ver
                </button>
                <button class="btn btn-sm btn-danger" onclick="eliminarArchivo(${archivo.id}, ${proyectoId})">
                  <i class="bi bi-trash"></i> Eliminar
                </button>
              </div>
            </div>
          `;
            });
            archivosHtml += '</div>';
          } else {
            archivosHtml += '<p class="text-muted">No hay archivos adjuntos</p>';
          }

          Swal.fire({
            title: 'Gestión de Archivos PDF',
            html: archivosHtml,
            width: 700,
            showCloseButton: true,
            showConfirmButton: false
          });
        });
    }

    function verObras(proyectoId) {
      window.location.href = `list_obras.php?proyecto_id=${proyectoId}`;
    }

    // Función para subir archivo 
    function subirArchivo() {
      const form = document.getElementById('formSubirArchivo');
      const formData = new FormData(form);

      fetch('upload_archivo.php', {
          method: 'POST',
          body: formData
        })
        .then(res => {
          if (!res.ok) throw new Error('Error HTTP: ' + res.status);
          return res.json();
        })
        .then(data => {
          if (data.status === 'success') {
            Swal.fire('Éxito', 'Archivo subido correctamente', 'success')
              .then(() => gestionarArchivos(formData.get('proyecto_id')));
          } else {
            Swal.fire('Error', data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'Error de conexión: ' + error.message, 'error');
        });
    }

    // Función para eliminar archivo
    function eliminarArchivo(archivoId, proyectoId) {
      Swal.fire({
        title: '¿Eliminar archivo?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          fetch(`delete_archivo.php?id=${archivoId}`)
            .then(res => {
              if (!res.ok) throw new Error('Error HTTP: ' + res.status);
              return res.json();
            })
            .then(data => {
              if (data.status === 'success') {
                Swal.fire('Éxito', 'Archivo eliminado', 'success')
                  .then(() => gestionarArchivos(proyectoId));
              } else {
                Swal.fire('Error', data.message, 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error', 'Error de conexión: ' + error.message, 'error');
            });
        }
      });
    }

    // Función para ver PDF
    function verPDF(ruta) {
      if (ruta && ruta.startsWith('uploads/')) {
        window.open(ruta, '_blank');
      } else {
        console.error('Ruta de archivo no válida:', ruta);
        Swal.fire('Error', 'Ruta de archivo no válida', 'error');
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
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
$obras_data = [];
      $stmt_obras_exp = $conn->prepare("SELECT id, nombre_obra, numero_obra, costo_directo FROM obras WHERE proyecto_id = ? ORDER BY numero_obra");
      $stmt_obras_exp->bind_param("i", $proyecto_id);
      $stmt_obras_exp->execute();
      $obras_list = $stmt_obras_exp->get_result()->fetch_all(MYSQLI_ASSOC);

      foreach ($obras_list as $obra) {
        $oid = $obra['id'];

        // Conceptos de esta obra (Jerárquicos - Construcción idéntica a la UI)
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
          // 1. Obtener nodos
          $sn = $conn->prepare("SELECT id, parent_id, clave, titulo as descripcion, nivel, sort_path FROM concepto_nodos WHERE catalogo_id = ? ORDER BY sort_path ASC");
          $sn->bind_param("i", $catalogo_id);
          $sn->execute();
          $res_nodos = $sn->get_result();
          $nodos_por_id = [];
          while ($n = $res_nodos->fetch_assoc()) {
            $nodos_por_id[(int)$n['id']] = $n + ['hijos' => [], 'conceptos' => []];
          }

          // 2. Obtener conceptos
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

          // 3. Construir árbol
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

          // 4. Aplanar recursivamente
          $flattenTree = function ($nodo) use (&$flattenTree, &$conceptos_raw) {
            // Agregar el nodo
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
            // Agregar sus conceptos
            foreach ($nodo['conceptos'] as $c) {
              $c['nivel'] = $nodo['nivel'] + 1; // Para indentación visual
              $conceptos_raw[] = $c;
            }
            // Recorrer hijos
            foreach ($nodo['hijos'] as $hijo) {
              $flattenTree($hijo);
            }
          };

          foreach ($raices as $raiz) {
            $flattenTree($raiz);
          }

          // Conceptos huérfanos al final
          foreach ($conceptos_sin_nodo as $c) {
            $c['nivel'] = 1;
            $conceptos_raw[] = $c;
          }
        }

        // 5. Vincular compras
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

        // Cabeceras
        const rows = [
          ["DETALLE INTEGRAL: " + obra.info.nombre_obra.toUpperCase() + " (#" + obra.info.numero_obra + ")"],
          [""],
          ["PRESUPUESTO ASIGNADO", "", "", "", "", "", "", "|", "DETALLE DE COMPRAS VINCULADAS (ORDENES DE COMPRA)"],
          ["NÚM.", "CLAVE", "DESCRIPCIÓN", "UNIDAD", "CANT.", "P.U.", "IMPORTE", "|", "FOLIO OC", "PROVEEDOR", "ITEM COMPRA", "CANT.", "P.U.", "SUBTOTAL", "ESTADO"]
        ];

        obra.data.forEach(c => {
          let indent = "";
          if (c.tipo === 'NODO') {
            // Negritas para nodos (simulado con prefijo visual más fuerte)
            indent = " ".repeat((c.nivel - 1) * 2) + " ";
          } else {
            // Indentación para conceptos basada en el nivel de su nodo padre
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
                // Filas adicionales para el mismo concepto (columnas A-G vacías)
                const emptyBase = ["", "", "", "", "", "", "", "|"];
                rows.push(emptyBase.concat(compraData));
              }
            });
          }

          if (c.tipo === 'NODO') rows.push([""]); // Espacio después de categorías
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


      // Descargar
      const pName = <?= json_encode($proyecto['nombre_proyecto']) ?>.substring(0, 30).replace(/[^a-z0-9]/gi, '_');
      const dStr = new Date().toISOString().slice(0, 10);
      XLSX.writeFile(wb, `Reporte_Proyecto_${pName}_${dStr}.xlsx`);
    }
  </script>

  <?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
include __DIR__ . "/../includes/footer.php"; ?>

</body>

</html>

