<?php
require_once __DIR__ . '/../config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

// ==== Filtros ====
$busqueda = $_GET['q'] ?? '';
$proyecto_id = $_GET['proyecto_id'] ?? '';
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

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Obras</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .badge-presupuesto {
        font-size: 0.75em;
        padding: 0.25em 0.5em;
    }
    .presupuesto-info {
        font-size: 0.85em;
        line-height: 1.4;
    }
    .text-warning {
        color: #ffc107 !important;
    }
    .progress {
        height: 6px;
        margin-top: 5px;
    }
    .progress-bar {
        transition: width 0.3s ease;
    }
    .filter-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
  </style>
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="list_project.php">Registro de Proyectos</a>
      <span>/</span>
      <span>Registro de Obras</span>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Registro de Obras</h1>
      </div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">
      
        <!-- Buscador -->
      <form class="form-search d-flex justify-content-center w-100 mb-4" method="GET">
        <input class="form-control w-100" type="search" name="q" placeholder="Buscar obra..." value="<?= htmlspecialchars($busqueda) ?>">
        <button class="btn btn-outline-success" type="submit"> <i class="bi bi-search"></i> </button>
      </form>

      <!-- Botón de agregar obra -->
      <div class="d-flex justify-content-between mb-3">
        <span class="badge-num"><?= $totalRegistros ?> obras</span>
        <button class="button-56" type="button" onclick="agregarObra(<?= $proyecto_id ?>)">
          <i class="bi bi-plus-circle"></i> Nueva Obra
        </button>
      </div>

      <!-- Lista de obras -->
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
                <strong><?= htmlspecialchars($row['nombre_obra']) ?></strong>
                <div class="text-muted small">
                  <div><strong>Número:</strong> <?= htmlspecialchars($row['numero_obra']) ?></div>
                </div>
                
              </div>
            </div>
          </div>
          <div class="btn-group" style="gap:5px;">
            <a href="details_obra.php?id=<?= $row['id'] ?>" class="btn-inf" 
                data-bs-toggle="tooltip" data-bs-placement="top" title="Ver Detalles de la Obra">
                <i class="bi bi-info-circle"></i>
            </a>
            <button class="btn-del" onclick="eliminarObra(<?= $row['id'] ?>)">
              <i class="bi bi-trash3"></i>
            </button>
          </div>
        </li>
        <?php endwhile; ?>
      </ul>

      <!-- Paginación -->
      <?php if($totalPaginas > 1): ?>
      <nav aria-label="Paginación">
        <ul class="pagination justify-content-center mt-3">
          <?php for($i = 1; $i <= $totalPaginas; $i++): ?>
          <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
            <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&proyecto_id=<?= $proyecto_id ?>&page=<?= $i ?>">
              <?= $i ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
      
      <?php else: ?>
      <div class="text-center text-muted py-4">
        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
        <p class="mt-2">No hay obras registradas</p>
        <button class="btn btn-primary mt-2" onclick="agregarObra(<?= $proyecto_id ?>)">
            <i class="bi bi-plus-circle"></i> Crear primera obra
          </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- FLOATING ACTION BUTTONS -->
<div class="fab-container">  
  <a onclick="history.back()" class="fab-button gray">
    <i class="bi bi-arrow-left"></i>
    <span class="fab-tooltip">Volver</span>
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
</script>

<script>
// Función para agregar obra
function agregarObra(proyectoId) {
    console.log("ID del proyecto:", proyectoId); // DEBUG
    
    // Primero obtener información del proyecto para validaciones
    fetch(`get_info_proyecto.php?id=${proyectoId}`)
        .then(res => {
            console.log("Respuesta HTTP:", res.status); // DEBUG
            return res.json();
        })
        .then(proyecto => {
            console.log("Datos del proyecto:", proyecto); // DEBUG
            
            if (proyecto.error) {
                console.error("Error del servidor:", proyecto.error);
                Swal.fire("Error", proyecto.error, "error");
                return;
            }
            
            // Resto del código...
            Swal.fire({
                title: "Nueva Obra",
                html: `
                    <form id="formAgregarObra" class="swal-form">
                        <input type="hidden" name="proyecto_id" value="${proyectoId}">
                        
                        <div class="mb-2">
                            <label class="form-label">Número de Obra <span class="required">*</span></label>
                            <input type="text" name="numero_obra" class="form-control" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Nombre de Obra <span class="required">*</span></label>
                            <input type="text" name="nombre_obra" class="form-control" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Descripción de la Obra</label>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe los detalles y características de la obra..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Fecha Inicio <span class="required">*</span></label>
                                <input type="date" name="fecha_inicio" class="form-control" required>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Fecha Fin <span class="required">*</span></label>
                                <input type="date" name="fecha_fin" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Monto Designado <span class="required">*</span></label>
                            <input type="number" step="0.01" name="monto_designado" class="form-control" required>
                            <small class="text-muted">Parte del monto total del proyecto: $${parseFloat(proyecto.monto_designado).toLocaleString('es-MX', {minimumFractionDigits: 2})}</small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Costo Directo <span class="required">*</span></label>
                            <input type="number" step="0.01" name="costo_directo" class="form-control" required>
                            <small class="text-muted">Presupuesto para órdenes de compra de esta obra</small>
                        </div>
                    </form>
                `,
                width: 600,
                showCancelButton: true,
                confirmButtonText: "Guardar",
                cancelButtonText: "Cancelar",
                focusConfirm: false,
                preConfirm: () => {
                    const form = document.getElementById("formAgregarObra");
                    const formData = new FormData(form);

                    return fetch("insert_obra.php", { method: "POST", body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if(data.status === 'success'){
                                Swal.fire("¡Éxito!", "Obra creada correctamente", "success")
                                    .then(() => verObras(proyectoId));
                            } else {
                                Swal.showValidationMessage(data.message || "Error al guardar la obra");
                            }
                        })
                        .catch(() => Swal.showValidationMessage("Error de conexión"));
                }
            });
        })
        .catch(error => {
            console.error("Error en fetch:", error);
            Swal.fire("Error", "No se pudo cargar la información del proyecto", "error");
        });
}

// Función para editar obra
function editarObra(obraId) {
    fetch(`edit_obra.php?id=${obraId}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                Swal.fire("Error", data.error, "error");
                return;
            }

            // Obtener lista de proyectos
            fetch('get_proyectos.php')
                .then(res => res.json())
                .then(proyectos => {
                    let proyectosOptions = '';
                    proyectos.forEach(proyecto => {
                        proyectosOptions += `<option value="${proyecto.id}" ${proyecto.id == data.proyecto_id ? 'selected' : ''}>${proyecto.nombre_proyecto}</option>`;
                    });

                    Swal.fire({
                        title: "Editar Obra",
                        html: `
                            <form id="formEditarObra" class="swal-form">
                                <input type="hidden" name="id" value="${data.id}">
                                
                                <div class="mb-2">
                                    <label class="form-label">Proyecto</label>
                                    <select name="proyecto_id" class="form-select" required>${proyectosOptions}</select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Número de Obra</label>
                                    <input type="text" name="numero_obra" class="form-control" value="${data.numero_obra}" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Nombre de Obra</label>
                                    <input type="text" name="nombre_obra" class="form-control" value="${data.nombre_obra}" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Descripción de la Obra</label>
                                    <textarea name="descripcion" class="form-control" rows="3">${data.descripcion || ''}</textarea>
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
                                    <small class="text-muted">Presupuesto para órdenes de compra</small>
                                </div>
                            </form>
                        `,
                        width: 600,
                        showCancelButton: true,
                        confirmButtonText: "Actualizar",
                        cancelButtonText: "Cancelar",
                        focusConfirm: false,
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
                });
        });
}

// Función para eliminar obra
function eliminarObra(obraId) {
    Swal.fire({
        title: '¿Seguro que deseas eliminar esta obra?',
        text: "Esta acción no se puede deshacer. Las órdenes de compra asociadas quedarán sin obra asignada.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#525252',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if(result.isConfirmed){
            fetch(`delete_obra.php?id=${obraId}`, { method: "GET" })
                .then(res => res.json())
                .then(resp => {
                    if(resp.status === "success"){
                        Swal.fire('¡Eliminada!', 'Obra eliminada correctamente', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo eliminar la obra', 'error');
                    }
                });
        }
    });
}

// Función para ver proyecto
function verProyecto(proyectoId) {
    window.location.href = `details_project.php?id=${proyectoId}`;
}

// Función para gestionar archivos de obra (similar a la de proyectos)
function gestionarArchivosObra(obraId) {
    // Implementar similar a gestionarArchivos() pero para obras
    Swal.fire('En desarrollo', 'La gestión de archivos para obras estará disponible pronto', 'info');
}
</script>

<script>
function verObras(proyectoId) {
    window.location.href = `list_obras.php?proyecto_id=${proyectoId}`;
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>

<script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>

</body>
</html>

