<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// IMPORTANTE: Incluir EmailHandler al inicio
require_once __DIR__ . '/../EmailHandler.php';

// Solo Supervisor de Proyecto puede cambiar estado
$rol_actual = $_SESSION['departamento_id'] ?? '';
if($rol_actual != '9') {
    die("No tiene permisos para cambiar el estado.");
}

$id = $_GET['id'] ?? null;
if(!$id) {
    die("ID de requisición no proporcionado.");
}

// Función para traducir estados
function traducirEstado($estado) {
    $estados = [
        'espera' => 'Pendiente',
        'aprobada' => 'Aprobado', 
        'rechazada' => 'Rechazado',
        'pendiente' => 'Pendiente'
    ];
    return $estados[$estado] ?? ucfirst($estado);
}

// Procesar cambio de estado
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $nuevo_estado = $_POST['estado'] ?? '';
    $comentarios = $_POST['comentarios'] ?? '';
    
    if(!in_array($nuevo_estado, ['espera','aprobada','rechazada','pendiente'])) {
        die("Estado inválido.");
    }

    // Obtener datos completos de la requisición antes de actualizar
    $sql_requisicion = "SELECT r.*, 
                        u.correo_corporativo, 
                        CONCAT(u.nombres, ' ', u.apellidos) as nombre_solicitante,
                        e.nombre as entidad_nombre, 
                        c.nombre as categoria_nombre
                        FROM requisiciones r 
                        LEFT JOIN usuarios u ON r.solicitante_id = u.id 
                        LEFT JOIN entidades e ON r.entidad_id = e.id
                        LEFT JOIN categorias c ON r.categoria_id = c.id
                        WHERE r.id = ?";
    $stmt_req = $conn->prepare($sql_requisicion);
    $stmt_req->bind_param("i", $id);
    $stmt_req->execute();
    $requisicion_data = $stmt_req->get_result()->fetch_assoc();

    if(!$requisicion_data) {
        die("Requisición no encontrada.");
    }

    // DEBUG INICIAL
    error_log("=== INICIANDO CAMBIO DE ESTADO ===");
    error_log("Requisición: " . $requisicion_data['folio']);
    error_log("Solicitante: " . $requisicion_data['nombre_solicitante']);
    error_log("Correo: " . $requisicion_data['correo_corporativo']);
    error_log("Nuevo estado: " . $nuevo_estado);

    // Actualizar estado en la base de datos
    $stmt = $conn->prepare("UPDATE requisiciones SET estado = ?, comentarios_operaciones = ? WHERE id = ?");
    $stmt->bind_param("ssi", $nuevo_estado, $comentarios, $id);
    
    if($stmt->execute()){
        error_log("✅ Estado actualizado en BD correctamente");
        
        // ==========================================
        // NOTIFICACIÓN POR CORREO
        // ==========================================
        
        // Validar que existe correo del solicitante
        if(empty($requisicion_data['correo_corporativo'])) {
            error_log("⚠ï¸ ADVERTENCIA: El solicitante no tiene correo corporativo registrado");
            header("Location: list_requis.php?msg=estado_actualizado_sin_email&folio=" . $requisicion_data['folio']);
            exit;
        }
        
        error_log("ðŸ“§ Iniciando proceso de notificación...");
        
        try {
            $emailHandler = new EmailHandler();
            error_log("✅ Instancia de EmailHandler creada");
            
            // Preparar datos para la notificación
            $datosRequisicion = [
                'folio' => $requisicion_data['folio'],
                'estado' => traducirEstado($nuevo_estado),
                'comentarios' => $comentarios,
                'solicitante' => $requisicion_data['nombre_solicitante'],
                'entidad' => $requisicion_data['entidad_nombre'] ?? 'Sin especificar',
                'categoria' => $requisicion_data['categoria_nombre'] ?? 'Sin especificar',
                'fecha_solicitud' => date('d/m/Y H:i', strtotime($requisicion_data['fecha_solicitud'])),
                'url_sistema' => 'http://localhost/requisiciones/list_requis.php' // AJUSTA ESTA URL
            ];
            
            error_log("ðŸ“¨ Enviando notificación a: " . $requisicion_data['correo_corporativo']);
            
            // Intentar enviar la notificación
            $resultado = $emailHandler->enviarNotificacionCambioEstado(
                $requisicion_data['correo_corporativo'],
                $requisicion_data['nombre_solicitante'],
                $datosRequisicion
            );
            
            if($resultado) {
                error_log("✅ NOTIFICACIÓN ENVIADA EXITOSAMENTE");
                header("Location: list_requis.php?msg=estado_actualizado_con_email&folio=" . $requisicion_data['folio']);
            } else {
                error_log("❌ FALLÓ EL ENVÍO DE LA NOTIFICACIÓN (pero estado actualizado)");
                header("Location: list_requis.php?msg=estado_actualizado_sin_email&folio=" . $requisicion_data['folio']);
            }
            exit;
            
        } catch (Exception $e) {
            error_log("❌ EXCEPCIÓN AL ENVIAR EMAIL: " . $e->getMessage());
            error_log("❌ Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
            
            // El estado sí se actualizó, solo falló el email
            header("Location: list_requis.php?msg=estado_actualizado_error_email&folio=" . $requisicion_data['folio']);
            exit;
        }
        
    } else {
        error_log("❌ Error al actualizar estado en BD: " . $stmt->error);
        die("Error al actualizar estado: " . $stmt->error);
    }
}

// Obtener estado actual y datos de la requisición para mostrar el formulario
$sql = "SELECT r.*, u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) as nombre_solicitante,
               e.nombre as entidad_nombre, c.nombre as categoria_nombre
        FROM requisiciones r 
        LEFT JOIN usuarios u ON r.solicitante_id = u.id 
        LEFT JOIN entidades e ON r.entidad_id = e.id
        LEFT JOIN categorias c ON r.categoria_id = c.id
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows == 0) {
    die("Requisición no encontrada.");
}
$requisicion = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cambiar Estado Requisición #<?= $id ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
    .card-header {
        background: linear-gradient(135deg, #2c3e50, #34495e);
        color: white;
    }
</style>
</head>
<body>
<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header">
            <h1 class="h4 mb-0">
                <i class="bi bi-pencil-square"></i> Cambiar Estado Requisición
            </h1>
        </div>
        <div class="card-body">
            
            <!-- Información de la requisición -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="text-primary">Información de la Requisición</h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Folio:</strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($requisicion['folio']) ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Solicitante:</strong></td>
                            <td><?= htmlspecialchars($requisicion['nombre_solicitante']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Correo:</strong></td>
                            <td>
                                <?php
if(!empty($requisicion['correo_corporativo'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($requisicion['correo_corporativo']) ?></small>
                                <?php
else: ?>
                                    <span class="badge bg-warning text-dark">Sin correo registrado</span>
                                <?php
endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Entidad:</strong></td>
                            <td><?= htmlspecialchars($requisicion['entidad_nombre']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Categoría:</strong></td>
                            <td><?= htmlspecialchars($requisicion['categoria_nombre']) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="text-primary">Estado Actual</h5>
                    <div class="d-flex align-items-center mb-3">
                        <?php
$badge_class = [
                            'espera' => 'bg-warning',
                            'pendiente' => 'bg-warning',
                            'aprobada' => 'bg-success',
                            'rechazada' => 'bg-danger'
                        ][$requisicion['estado']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $badge_class ?> fs-6">
                            <?= traducirEstado($requisicion['estado']) ?>
                        </span>
                    </div>
                    
                    <?php
if(!empty($requisicion['comentarios_operaciones'])): ?>
                    <div class="mt-3">
                        <h6>Comentarios anteriores:</h6>
                        <div class="alert alert-info py-2">
                            <small><?= htmlspecialchars($requisicion['comentarios_operaciones']) ?></small>
                        </div>
                    </div>
                    <?php
endif; ?>
                </div>
            </div>

            <hr>

            <!-- Formulario de cambio de estado -->
            <form method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="estado" class="form-label">Nuevo Estado <span class="text-danger">*</span></label>
                            <select name="estado" id="estado" class="form-select" required>
                                <option value="">Seleccionar estado...</option>
                                <option value="espera" <?= $requisicion['estado']=='espera'?'selected':'' ?>>En Espera</option>
                                <option value="aprobada" <?= $requisicion['estado']=='aprobada'?'selected':'' ?>>Aprobada</option>
                                <option value="rechazada" <?= $requisicion['estado']=='rechazada'?'selected':'' ?>>Rechazada</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="comentarios" class="form-label">Comentarios (Opcional)</label>
                    <textarea name="comentarios" id="comentarios" class="form-control" rows="3" 
                              placeholder="Agregue comentarios sobre el cambio de estado..."><?= htmlspecialchars($requisicion['comentarios_operaciones'] ?? '') ?></textarea>
                </div>

                <?php
if(!empty($requisicion['correo_corporativo'])): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Notificación por correo:</strong> Al cambiar el estado, se enviará automáticamente 
                    un correo a <strong><?= htmlspecialchars($requisicion['nombre_solicitante']) ?></strong> 
                    (<?= htmlspecialchars($requisicion['correo_corporativo']) ?>)
                </div>
                <?php
else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>Advertencia:</strong> El solicitante no tiene correo corporativo registrado. 
                    No se enviará notificación automática.
                </div>
                <?php
endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Actualizar Estado
                        <?= !empty($requisicion['correo_corporativo']) ? 'y Enviar Notificación' : '' ?>
                    </button>
                    <a href="list_requis.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver al Listado
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


