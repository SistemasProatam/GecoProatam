<?php
// Incluir el gestor de sesiones
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$proveedor_id = $_GET['proveedor_id'] ?? 0;

// Procesar eliminación si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_evaluacion'])) {
    $evaluacion_id = $_POST['evaluacion_id'] ?? 0;
    
    if ($evaluacion_id > 0) {
        // Usar eliminación suave (cambiar activo a 0) o eliminación permanente
        $sql_eliminar = "DELETE FROM evaluaciones_proveedores WHERE id = ? AND proveedor_id = ?";
        $stmt_eliminar = $conn->prepare($sql_eliminar);
        $stmt_eliminar->bind_param("ii", $evaluacion_id, $proveedor_id);
        
        if ($stmt_eliminar->execute()) {
            $_SESSION['mensaje_exito'] = "Evaluación eliminada correctamente";
        } else {
            $_SESSION['mensaje_error'] = "Error al eliminar la evaluación";
        }
        
        // Redirigir para evitar reenvío del formulario
        header("Location: historial_evaluaciones.php?proveedor_id=" . $proveedor_id);
        exit();
    }
}

// Obtener información del proveedor
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

// Verificar mensajes de sesión
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}

if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  .btn-geco-outline {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border: 1.5px solid var(--gray-200, #e5e7eb);
    border-radius: 10px;
    background: #fff;
    color: var(--s-700, #113557);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
  }
  .btn-geco-outline:hover {
    background: #f1f5f9;
    border-color: #c7d2dc;
    color: var(--s-700, #113557);
    text-decoration: none;
  }
</style>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="<?= BASE_URL ?>/catalog/list_catalog.php?entidad=proveedores">Proveedores</a>
        <span class="separator">›</span>
        <span>Historial de Evaluaciones</span>
      </nav>
      <h1 class="orders-page-title">Historial — <?= htmlspecialchars($proveedor['razon_social'] ?? 'Proveedor') ?></h1>
    </div>
    <a href="list_catalog.php?entidad=proveedores" class="btn-geco-outline">← Volver</a>
  </div>

  <!-- Alerts -->
  <?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
      <?= htmlspecialchars($mensaje_exito) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($mensaje_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
      <?= htmlspecialchars($mensaje_error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Table Card -->
  <div class="orders-card mt-4">
    <div class="orders-table-wrap">
      <table class="orders-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Evaluador</th>
            <th>Contrato</th>
            <th>Puntuación</th>
            <th>Resultado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($evaluaciones->num_rows > 0): ?>
            <?php while ($eval = $evaluaciones->fetch_assoc()): ?>
              <?php
                $resultado = $eval['resultado_final'] ?? '';
                $badge_styles = [
                  'excelente'   => 'color:#15803d; background:rgba(34,197,94,0.06); border-color:rgba(34,197,94,0.3);',
                  'bueno'       => 'color:#1d4ed8; background:rgba(59,130,246,0.08); border-color:rgba(59,130,246,0.3);',
                  'regular'     => 'color:#92400e; background:rgba(217,119,6,0.06); border-color:rgba(217,119,6,0.3);',
                  'no_aprobado' => 'color:#b91c1c; background:rgba(239,68,68,0.06); border-color:rgba(239,68,68,0.3);',
                ];
                $badge_style = $badge_styles[$resultado] ?? '';
              ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($eval['fecha_creacion'])) ?></td>
                <td><?= htmlspecialchars($eval['nombres'] . ' ' . $eval['apellidos']) ?></td>
                <td><?= htmlspecialchars($eval['contrato_numero']) ?></td>
                <td><strong><?= number_format($eval['total_puntuacion'], 1) ?></strong></td>
                <td>
                  <span class="status-badge" style="<?= $badge_style ?>">
                    <?= ucfirst(str_replace('_', ' ', $resultado)) ?>
                  </span>
                </td>
                <td>
                  <div class="actions-group">
                    <button class="btn-action btn-action--view"
                            onclick="verDetalle(<?= $eval['id'] ?>)"
                            title="Ver detalles de evaluación">
                      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirmarEliminacion(this)">
                      <input type="hidden" name="evaluacion_id" value="<?= $eval['id'] ?>">
                      <input type="hidden" name="eliminar_evaluacion" value="1">
                      <button type="submit" class="btn-action"
                              style="color:#b91c1c;"
                              title="Eliminar evaluación">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6">
                <div class="orders-empty-state">
                  <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                  <p>No hay evaluaciones registradas para este proveedor.</p>
                  <a href="evaluacion_proveedor.php?id=<?= $proveedor_id ?>" class="btn-geco-primary">
                    + Crear primera evaluación
                  </a>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
// Función para ver detalle con SweetAlert
function verDetalle(evaluacionId) {
    // Hacer petición AJAX para obtener los detalles
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
                            <div class="col-4">${eval.calidad_calificacion} → ${eval.calidad_resultado} pts</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Cumplimiento entregas (25%):</div>
                            <div class="col-4">${eval.cumplimiento_entregas_calificacion} → ${eval.cumplimiento_entregas_resultado} pts</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Precio y condiciones (20%):</div>
                            <div class="col-4">${eval.precio_condiciones_calificacion} → ${eval.precio_condiciones_resultado} pts</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Cumplimiento legal (15%):</div>
                            <div class="col-4">${eval.cumplimiento_legal_calificacion} → ${eval.cumplimiento_legal_resultado} pts</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-8">Atención y servicio (10%):</div>
                            <div class="col-4">${eval.atencion_servicio_calificacion} → ${eval.atencion_servicio_resultado} pts</div>
                        </div>
                        
                        <div class="row mb-2 bg-light py-2 rounded">
                            <div class="col-6"><strong>TOTAL PUNTUACIÓN:</strong></div>
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
                    title: 'Detalle de Evaluación',
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
                Swal.fire('Error', 'No se pudo cargar la información de la evaluación', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Error al cargar los detalles', 'error');
        });
}

// Función para obtener clase del badge según resultado
function getBadgeClass(resultado) {
    const clases = {
        'excelente': 'bg-success',
        'bueno': 'bg-info', 
        'regular': 'bg-warning text-dark',
        'no_aprobado': 'bg-danger'
    };
    return clases[resultado] || 'bg-secondary';
}

// Función para confirmar eliminación
function confirmarEliminacion(form) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción no se puede deshacer",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
