<?php
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
  <link rel="icon" href="/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="/assets/styles/details.css">
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
        <a href="/index.php"><i class="bi bi-house-door"></i> Inicio</a>
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
              <i class="bi bi-tools"></i>
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

  <?php if($proyecto['total_obras'] > 0): ?>
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
          <?php while($obra = $obras->fetch_assoc()): 
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
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

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
</script>
<script>
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

                            return fetch("update_project.php", { method: "POST", body: formData })
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
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>