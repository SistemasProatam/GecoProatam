<?php
// Incluir el gestor de sesiones
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$proveedor_id = $_GET['proveedor_id'] ?? 0;

// Procesar eliminaciÃ³n si se enviÃ³
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_evaluacion'])) {
    $evaluacion_id = $_POST['evaluacion_id'] ?? 0;
    
    if ($evaluacion_id > 0) {
        // Usar eliminaciÃ³n suave (cambiar activo a 0) o eliminaciÃ³n permanente
        $sql_eliminar = "DELETE FROM evaluaciones_proveedores WHERE id = ? AND proveedor_id = ?";
        $stmt_eliminar = $conn->prepare($sql_eliminar);
        $stmt_eliminar->bind_param("ii", $evaluacion_id, $proveedor_id);
        
        if ($stmt_eliminar->execute()) {
            $_SESSION['mensaje_exito'] = "EvaluaciÃ³n eliminada correctamente";
        } else {
            $_SESSION['mensaje_error'] = "Error al eliminar la evaluaciÃ³n";
        }
        
        // Redirigir para evitar reenvÃ­o del formulario
        header("Location: historial_evaluaciones.php?proveedor_id=" . $proveedor_id);
        exit();
    }
}

// Obtener informaciÃ³n del proveedor
$sql_proveedor = "SELECT razon_social FROM proveedores WHERE id = ?";
$stmt_proveedor = $conn->prepare($sql_proveedor);
$stmt_proveedor->bind_param("i", $proveedor_id);
$stmt_proveedor->execute();
$proveedor = $stmt_proveedor->get_result()->fetch_assoc();

// Obtener historial de evaluaciones
$sql_evaluaciones = "SELECT ep.*, u.nombres, u.apellidos 
                     FROM evaluaciones_proveedores ep
                     LEFT JOIN usuarios u ON ep.usuario_creador_id = u.id
                     WHERE ep.proveedor_id = ?
                     ORDER BY ep.fecha_creacion DESC";
$stmt_evaluaciones = $conn->prepare($sql_evaluaciones);
$stmt_evaluaciones->bind_param("i", $proveedor_id);
$stmt_evaluaciones->execute();
$evaluaciones = $stmt_evaluaciones->get_result();

// Verificar mensajes de sesiÃ³n
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}

if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Evaluaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .badge-excelente { background-color: #28a745; }
        .badge-bueno { background-color: #17a2b8; }
        .badge-regular { background-color: #ffc107; color: #000; }
        .badge-no_aprobado { background-color: #dc3545; }
    </style>
</head>
<body>
<?php
include __DIR__ . "/../includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <span>Historial de Proveedor</span>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Historial de Evaluaciones - <?= htmlspecialchars($proveedor['razon_social']) ?></h1>
        </div>
      </div>
      
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">
    
    <?php
if (isset($mensaje_exito)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= $mensaje_exito ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php
endif; ?>
    
    <?php
if (isset($mensaje_error)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i> <?= $mensaje_error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php
endif; ?>

    <!-- Lista de evaluaciones con scroll horizontal -->
    <div class="table-container">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Evaluador</th>
            <th>Contrato</th>
            <th>PuntuaciÃ³n</th>
            <th>Resultado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php
if($evaluaciones->num_rows > 0): ?>
            <?php
while($eval = $evaluaciones->fetch_assoc()): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($eval['fecha_creacion'])) ?></td>
                <td><?= htmlspecialchars($eval['nombres'] . ' ' . $eval['apellidos']) ?></td>
                <td><?= htmlspecialchars($eval['contrato_numero']) ?></td>
                <td><strong><?= number_format($eval['total_puntuacion'], 1) ?></strong></td>
                <td>
                  <?php
$badge_classes = [
                      'excelente' => 'badge-excelente',
                      'bueno' => 'badge-bueno', 
                      'regular' => 'badge-regular',
                      'no_aprobado' => 'badge-no_aprobado'
                    ];
                    $icon_classes = [
                      'excelente' => 'bi bi-star-fill',
                      'bueno' => 'bi bi-star-half', 
                      'regular' => 'bi bi-star',
                      'no_aprobado' => 'bi bi-x-circle'
                    ];
                  ?>
                  <span class="badge <?= $badge_classes[$eval['resultado_final']] ?>">
                    <i class="<?= $icon_classes[$eval['resultado_final']] ?>"></i>
                    <?= ucfirst(str_replace('_', ' ', $eval['resultado_final'])) ?>
                  </span>
                </td>
                <td>
                  <div class="btn-group" style="gap:5px;">
                    <button class="btn-inf" onclick="verDetalle(<?= $eval['id'] ?>)" 
                            title="Ver detalles de evaluaciÃ³n">
                      <i class="bi bi-eye"></i>
                    </button>
                    
                    <form method="POST" style="display: inline;" 
                          onsubmit="return confirmarEliminacion(this)">
                      <input type="hidden" name="evaluacion_id" value="<?= $eval['id'] ?>">
                      <input type="hidden" name="eliminar_evaluacion" value="1">
                      <button type="submit" class="btn-del" 
                              title="Eliminar evaluaciÃ³n">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php
endwhile; ?>
          <?php
else: ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">No hay evaluaciones registradas</p>
                <a href="evaluacion_proveedor.php?id=<?= $proveedor_id ?>" class="btn btn-primary mt-2">
                  <i class="bi bi-plus-circle"></i> Crear primera evaluaciÃ³n
                </a>
              </td>
            </tr>
          <?php
endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// FunciÃ³n para ver detalle con SweetAlert
function verDetalle(evaluacionId) {
    // Hacer peticiÃ³n AJAX para obtener los detalles
    fetch(`obtener_detalle_evaluacion.php?id=${evaluacionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const eval = data.data;
                
                // Formatear el contenido del modal
                const contenido = `
                    <div class="text-start">
                        <div class="row mb-2">
                            <div class="col-6"><strong>Proveedor:</strong></div>
                            <div class="col-6">${eval.razon_social}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6"><strong>RFC:</strong></div>
                            <div class="col-6">${eval.rfc || 'N/A'}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6"><strong>Contrato:</strong></div>
                            <div class="col-6">${eval.contrato_numero}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6"><strong>Fecha:</strong></div>
                            <div class="col-6">${eval.lugar_fecha}</div>
                        </div>
                        <hr>
                        
                        <h6 class="mt-3">Calificaciones Detalladas:</h6>
                        <div class="row mb-1">
                            <div class="col-8">Calidad (30%):</div>
                            <div class="col-4">${eval.calidad_calificacion} â†’ ${eval.calidad_resultado} pts</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Cumplimiento entregas (25%):</div>
                            <div class="col-4">${eval.cumplimiento_entregas_calificacion} â†’ ${eval.cumplimiento_entregas_resultado} pts</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Precio y condiciones (20%):</div>
                            <div class="col-4">${eval.precio_condiciones_calificacion} â†’ ${eval.precio_condiciones_resultado} pts</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Cumplimiento legal (15%):</div>
                            <div class="col-4">${eval.cumplimiento_legal_calificacion} â†’ ${eval.cumplimiento_legal_resultado} pts</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-8">AtenciÃ³n y servicio (10%):</div>
                            <div class="col-4">${eval.atencion_servicio_calificacion} â†’ ${eval.atencion_servicio_resultado} pts</div>
                        </div>
                        
                        <div class="row mb-2 bg-light py-2 rounded">
                            <div class="col-6"><strong>TOTAL PUNTUACIÃ“N:</strong></div>
                            <div class="col-6"><strong>${eval.total_puntuacion} pts</strong></div>
                        </div>
                        
                        <div class="row mb-2">
                            <div class="col-6"><strong>RESULTADO FINAL:</strong></div>
                            <div class="col-6">
                                <span class="badge ${getBadgeClass(eval.resultado_final)}">
                                    ${eval.resultado_final.toUpperCase().replace('_', ' ')}
                                </span>
                            </div>
                        </div>
                        
                        ${eval.observaciones ? `
                        <hr>
                        <div class="mt-2">
                            <strong>Observaciones:</strong><br>
                            ${eval.observaciones}
                        </div>
                        ` : ''}
                        
                        <div class="row mt-3">
                            <div class="col-6"><strong>Responsable:</strong></div>
                            <div class="col-6">${eval.responsables}</div>
                        </div>
                    </div>
                `;
                
                Swal.fire({
                    title: 'Detalle de EvaluaciÃ³n',
                    html: contenido,
                    width: 600,
                    padding: '1.5rem',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'border-rounded'
                    }
                });
            } else {
                Swal.fire('Error', 'No se pudo cargar la informaciÃ³n de la evaluaciÃ³n', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Error al cargar los detalles', 'error');
        });
}

// FunciÃ³n para obtener clase del badge segÃºn resultado
function getBadgeClass(resultado) {
    const clases = {
        'excelente': 'bg-success',
        'bueno': 'bg-info', 
        'regular': 'bg-warning text-dark',
        'no_aprobado': 'bg-danger'
    };
    return clases[resultado] || 'bg-secondary';
}

// FunciÃ³n para confirmar eliminaciÃ³n
function confirmarEliminacion(form) {
    Swal.fire({
        title: 'Â¿EstÃ¡s seguro?',
        text: "Esta acciÃ³n no se puede deshacer",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'SÃ­, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}

// Inicializar tooltips de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
</body>
</html>



