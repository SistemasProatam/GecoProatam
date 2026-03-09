<?php
// details_obra.php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$obra_id = $_GET['id'] ?? 0;

if ($obra_id <= 0) {
    header("Location: list_obras_view.php");
    exit;
}

// Obtener información completa de la obra
$sql_obra = "SELECT o.*, p.nombre_proyecto, p.numero_licitacion, p.numero_contrato,
             (SELECT COALESCE(SUM(costo_directo_utilizado), 0) FROM presupuesto_control 
              WHERE obra_id = o.id) as costo_directo_utilizado,
             (SELECT COUNT(*) FROM catalogos WHERE obra_id = o.id) as total_catalogos,
             (SELECT COUNT(*) FROM conceptos c 
              JOIN catalogos cat ON c.catalogo_id = cat.id 
              WHERE cat.obra_id = o.id) as total_conceptos
             FROM obras o 
             LEFT JOIN proyectos p ON o.proyecto_id = p.id 
             WHERE o.id = ?";
$stmt = $conn->prepare($sql_obra);
$stmt->bind_param("i", $obra_id);
$stmt->execute();
$obra = $stmt->get_result()->fetch_assoc();

if (!$obra) {
    header("Location: list_obras_view.php");
    exit;
}

// Calcular valores
$costo_disponible = $obra['costo_directo'] - $obra['costo_directo_utilizado'];
$porcentaje_utilizado = $obra['costo_directo'] > 0 ? 
    ($obra['costo_directo_utilizado'] / $obra['costo_directo']) * 100 : 0;
$progress_class = $porcentaje_utilizado > 90 ? 'bg-danger' : 
                 ($porcentaje_utilizado > 70 ? 'bg-warning' : 'bg-success');

// Obtener catálogos de la obra
$sql_catalogos = "SELECT * FROM catalogos WHERE obra_id = ? ORDER BY fecha_creacion DESC";
$stmt_catalogos = $conn->prepare($sql_catalogos);
$stmt_catalogos->bind_param("i", $obra_id);
$stmt_catalogos->execute();
$catalogos = $stmt_catalogos->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalles de Obra - <?= htmlspecialchars($obra['nombre_obra']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
      <a href="list_obras.php">Registro de Obras</a>
      <span>/</span>
      <span>Detalles de Obra</span>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h3 class="hero-title" style="font-size: 18px"><?= htmlspecialchars($obra['nombre_obra']) ?></h3>
        <div style="color: #ddd; font-size: 14px; margin-top: -5px;">
        <p>Periodo:
          <?= date('d/m/Y', strtotime($obra['fecha_inicio'])) ?> 
          - 
          <?= date('d/m/Y', strtotime($obra['fecha_fin'])) ?>
        </p>
        <p class="hero-subtitle">#<?= htmlspecialchars($obra['numero_obra']) ?> 
        -
        <?= htmlspecialchars($obra['nombre_proyecto']) ?>
        </p>
        </div>
      </div>
      <!-- ACTION BUTTONS -->
          <div class="btn-group" style="gap:5px;">
            <button class="btn-ed" onclick="editarObra(<?= $obra_id ?>)"
              data-bs-toggle="tooltip" data-bs-placement="top" title="Editar Obra">
              <i class="bi bi-pencil"></i>
            </button>

            <button class="btn-inf" onclick="gestionarArchivos(<?= $obra_id ?>)"
              data-bs-toggle="tooltip" data-bs-placement="top" title="Archivos PDF">
              <i class="bi bi-paperclip"></i>
            </button>
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

<!-- Estadísticas de Presupuesto -->
    <div class="budget-stats">
      <div class="budget-stat">
        <div class="budget-stat-label">Costo Directo</div>
        <div class="budget-stat-value">$<?= number_format($obra['costo_directo'], 2) ?></div>
      </div>

      <div class="budget-stat">
        <div class="budget-stat-label">Utilizado</div>
        <div class="budget-stat-value">$<?= number_format($obra['costo_directo_utilizado'], 2) ?></div>
      </div>

      <div class="budget-stat">
        <div class="budget-stat-label">Disponible</div>
          <div class="budget-stat-value" style="color: <?= $costo_disponible < 0 ? '#dc3545' : '#198754' ?>">
            $<?= number_format($costo_disponible, 2) ?>
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
            <span class="info-label">Número de Obra</span>
            <span class="info-value"><?= htmlspecialchars($obra['numero_obra']) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Proyecto</span>
            <span class="info-value"><?= htmlspecialchars($obra['nombre_proyecto']) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Licitación</span>
            <span class="info-value"><?= htmlspecialchars($obra['numero_licitacion']) ?></span>
            </li>
          <li class="info-item">
            <span class="info-label">Contrato</span>
            <span class="info-value"><?= htmlspecialchars($obra['numero_contrato']) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Periodo</span>
            <span class="info-value"><?= date('d/m/Y', strtotime($obra['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($obra['fecha_fin'])) ?></span>
          </li>
          <li class="info-item">
            <span class="info-label">Descripción</span>
            <span class="info-value"><?= htmlspecialchars($obra['descripcion']) ?></span>
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
          <span class="info-value">$<?= number_format($obra['monto_designado'], 2) ?></span>
        </li>
        <li class="info-item">
          <span class="info-label">Costo Directo</span>
          <span class="info-value">$<?= number_format($obra['costo_directo'], 2) ?></span>
        </li>
      </ul>
    </div>
  </div>

    <!-- Catálogos -->
<div class="works-section">

<div class="section-header">
      <div class="section-title-group">
        <h4>Catálogo</h4>
      </div>
    </div>

      <div class="card-body">
        <?php if($catalogos->num_rows > 0): ?>
          <div class="list-group">
            <?php while($catalogo = $catalogos->fetch_assoc()): ?>
              <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="flex-grow-1">
                    <strong><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></strong>
                    <?php if($catalogo['descripcion']): ?>
                      <br><small class="text-muted"><?= htmlspecialchars($catalogo['descripcion']) ?></small>
                    <?php endif; ?>
                    <br><small class="text-muted">Creado: <?= date('d/m/Y', strtotime($catalogo['fecha_creacion'])) ?></small>
                  </div>
                  <div class="btn-group" style="gap:5px;">
                    <a href="conceptos_view.php?catalogo_id=<?= $catalogo['id'] ?>&catalogo_nombre=<?= urlencode($catalogo['nombre_catalogo']) ?>&obra_id=<?= $obra_id ?>&obra_nombre=<?= urlencode($obra['nombre_obra']) ?>" 
                       class="btn-inf" 
                       data-bs-toggle="tooltip" data-bs-placement="top" title="Gestionar Conceptos">
                      <i class="bi bi-folder2-open"></i>
                    </a>
                    <button class="btn-ed" 
                            onclick="editarCatalogo(<?= $catalogo['id'] ?>)"
                            data-bs-toggle="tooltip" data-bs-placement="top" title="Editar Catálogo">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-del" 
                            onclick="eliminarCatalogo(<?= $catalogo['id'] ?>, <?= $obra_id ?>, '<?= htmlspecialchars(addslashes($obra['nombre_obra'])) ?>')"
                            data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar Catálogo">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="text-center text-muted py-4">
            <i class="bi bi-folder" style="font-size: 3rem;"></i>
            <p class="mt-2">No hay catálogos registrados</p>
            <button class="btn btn-success" 
                    onclick="mostrarFormularioCatalogo(<?= $obra_id ?>, '<?= htmlspecialchars(addslashes($obra['nombre_obra'])) ?>')">
              <i class="bi bi-plus-circle"></i> Crear Primer Catálogo
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- FLOATING ACTION BUTTONS -->
<div class="fab-container-backbtn">  
  <a onclick="history.back()" class="fab-button-backbtn">
    <i class="bi bi-arrow-left"></i>
    <span class="fab-tooltip-backbtn">Volver</span>
  </a>
</div>

<script>
// Inicializar tooltips de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Función para editar obra
function editarObra(id) {
    fetch(`edit_obra.php?id=${id}`)
        .then(res => {
            if (!res.ok) throw new Error('Error al cargar datos de la obra');
            return res.json();
        })
        .then(data => {
            if (data.error) {
                Swal.fire("Error", data.error, "error");
                return;
            }

            Swal.fire({
                title: "Editar Obra",
                html: `
                  <form id="formEditarObra" class="swal-form">
                    <input type="hidden" name="id" value="${data.id}">

                    <div class="mb-2">
                      <label class="form-label">Proyecto</label>
                      <textarea class="form-control" style="height: auto; min-height: 38px; resize: none;" readonly 
                      >${data.nombre_proyecto || 'Proyecto no disponible'}</textarea>
                      <input type="hidden" name="proyecto_id" value="${data.proyecto_id}">
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Número de Obra</label>
                      <input type="text" name="numero_obra" class="form-control" value="${data.numero_obra}" required>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Nombre de la Obra</label>
                      <input type="text" name="nombre_obra" class="form-control" value="${data.nombre_obra}" required>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Descripción</label>
                      <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe los objetivos y características de la obra...">${data.descripcion || ''}</textarea>
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
                      <label class="form-label">Costo Directo</label>
                      <input type="number" step="0.01" name="costo_directo" class="form-control" value="${data.costo_directo}" required>
                      <small class="text-muted">Presupuesto disponible para esta obra</small>
                    </div>
                  </form>
                `,
                width: 600,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: "Actualizar",
                cancelButtonText: "Cancelar",
                preConfirm: () => {
                    const form = document.getElementById("formEditarObra");
                    const formData = new FormData(form);

                    return fetch("update_obra.php", { method: "POST", body: formData })
                        .then(res => res.json())
                        .then(resp => {
                            if (resp.status === "success") {
                                Swal.fire("¡Éxito!", "Obra actualizada correctamente", "success")
                                    .then(() => location.reload());
                            } else {
                                Swal.showValidationMessage(resp.message || "Error al actualizar la obra");
                            }
                        })
                        .catch(() => Swal.showValidationMessage("Error de conexión"));
                }
            });
        })
        .catch(error => {
            console.error('Error al cargar datos de la obra:', error);
            Swal.fire('Error', 'No se pudieron cargar los datos de la obra', 'error');
        });
}

// Función para gestionar archivos de obra
function gestionarArchivos(obraId) {
    fetch(`get_archivos_obra.php?obra_id=${obraId}`)
        .then(res => res.json())
        .then(data => {
            let archivosHtml = `
                <div class="mb-3">
                  <form id="formSubirArchivo" enctype="multipart/form-data">
                    <input type="hidden" name="obra_id" value="${obraId}">
                    <div class="mb-2">
                      <label class="form-label">Subir archivo PDF (Máximo 5 archivos)</label>
                      <input type="file" name="archivo" class="form-control" accept=".pdf" required>
                      <small class="text-muted">Tamaño máximo: 10MB</small>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="subirArchivoObra()">
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
                            <button class="btn btn-sm btn-danger" onclick="eliminarArchivoObra(${archivo.id}, ${obraId})">
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
        })
        .catch(error => {
            console.error('Error al cargar archivos:', error);
            Swal.fire('Error', 'No se pudieron cargar los archivos', 'error');
        });
}

// Función para subir archivo de obra
function subirArchivoObra() {
    const form = document.getElementById('formSubirArchivo');
    const formData = new FormData(form);

    fetch('upload_archivo_obra.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('¡Éxito!', data.message, 'success')
                .then(() => {
                    const obraId = formData.get('obra_id');
                    gestionarArchivos(obraId);
                });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Error al subir el archivo', 'error');
    });
}

// Función para eliminar archivo de obra
function eliminarArchivoObra(archivoId, obraId) {
    Swal.fire({
        title: '¿Eliminar archivo?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('delete_archivo_obra.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: archivoId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('¡Eliminado!', data.message, 'success')
                        .then(() => gestionarArchivos(obraId));
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Error al eliminar el archivo', 'error');
            });
        }
    });
}

// Función para ver PDF
function verPDF(rutaArchivo) {
    window.open(rutaArchivo, '_blank');
}

// Función para gestionar catálogos (necesaria para el botón)
function gestionarCatalogos(obraId, obraNombre) {
    // Esta función debe estar definida en catalogo-obras.js
    if (typeof gestionarCatalogos === 'function') {
        gestionarCatalogos(obraId, obraNombre);
    } else {
        console.error('Función gestionarCatalogos no disponible');
        // Recargar la página como fallback
        location.reload();
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="/assets/scripts/catalogo-obras.js"></script>

<?php include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>