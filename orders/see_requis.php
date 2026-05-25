<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// IMPORTANTE: Incluir EmailHandler
require_once __DIR__ . '/../EmailHandler.php';

if (!isset($_GET['id'])) {
  die("ID no proporcionado");
}

$id = intval($_GET['id']);

// Función para traducir estados
function traducirEstado($estado)
{
  $estados = [
    'pendiente' => 'Pendiente',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado'
  ];
  return $estados[$estado] ?? ucfirst($estado);
}

// Procesar cambio de estado si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
  $nuevo_estado = $_POST['nuevo_estado'];
  $comentario = $_POST['comentario'] ?? '';

  // Validar estado
  $estados_permitidos = ['aprobado', 'rechazado'];
  if (in_array($nuevo_estado, $estados_permitidos)) {

    // ========================================
    // OBTENER DATOS COMPLETOS ANTES DE ACTUALIZAR
    // ========================================
    $sql_datos = "SELECT r.*, 
                      u.correo_corporativo, 
                      CONCAT(u.nombres, ' ', u.apellidos) as nombre_solicitante,
                      e.nombre as entidad_nombre, 
                      c.nombre as categoria_nombre,
                      p.nombre_proyecto, o.nombre_obra, cat.nombre_catalogo
                      FROM requisiciones r 
                      LEFT JOIN usuarios u ON r.solicitante_id = u.id 
                      LEFT JOIN entidades e ON r.entidad_id = e.id
                      LEFT JOIN categorias c ON r.categoria_id = c.id
                      LEFT JOIN proyectos p ON r.proyecto_id = p.id
                      LEFT JOIN obras o ON r.obra_id = o.id
                      LEFT JOIN catalogos cat ON r.catalogo_id = cat.id
                      WHERE r.id = ?";
    $stmt_datos = $conn->prepare($sql_datos);
    $stmt_datos->bind_param("i", $id);
    $stmt_datos->execute();
    $requisicion_data = $stmt_datos->get_result()->fetch_assoc();

    if (!$requisicion_data) {
      die("Error: No se pudieron obtener los datos de la requisición");
    }

    // DEBUG
    error_log("=== CAMBIO DE ESTADO DESDE see_requis.php ===");
    error_log("Requisición ID: " . $id);
    error_log("Folio: " . $requisicion_data['folio']);
    error_log("Nuevo estado: " . $nuevo_estado);
    error_log("Solicitante: " . $requisicion_data['nombre_solicitante']);
    error_log("Correo: " . $requisicion_data['correo_corporativo']);

    // ========================================
    // ACTUALIZAR ESTADO
    // ========================================
    $sql_update = "UPDATE requisiciones SET estado = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("si", $nuevo_estado, $id);

    if ($stmt_update->execute()) {
      error_log("Estado actualizado en BD");

      // ========================================
      // REGISTRAR EN HISTORIAL
      // ========================================
      try {
        $sql_historial = "INSERT INTO requisicion_historial (requisicion_id, usuario_id, accion, comentario) VALUES (?, ?, ?, ?)";
        $stmt_historial = $conn->prepare($sql_historial);
        $accion = $nuevo_estado === 'aprobado' ? 'Aprobó requisición' : 'Rechazó requisición';
        $stmt_historial->bind_param("iiss", $id, $_SESSION['user_id'], $accion, $comentario);
        $stmt_historial->execute();
        error_log("Historial registrado");
      } catch (Exception $e) {
        error_log(" Error al insertar en historial: " . $e->getMessage());
      }

      // ========================================
      // ENVIAR NOTIFICACIÓN POR CORREO
      // ========================================

      // Validar que existe correo del solicitante
      if (empty($requisicion_data['correo_corporativo'])) {
        error_log("ADVERTENCIA: El solicitante no tiene correo corporativo registrado");
        header("Location: see_requis.php?id=$id&success=1&email=no_correo");
        exit;
      }

      error_log("Iniciando envío de notificación...");

      try {
        $emailHandler = new EmailHandler();
        error_log("EmailHandler instanciado");

        // Preparar datos para la notificación
        $datosRequisicion = [
          'id' => $id,
          'folio' => $requisicion_data['folio'],
          'estado' => traducirEstado($nuevo_estado),
          'comentarios' => $comentario,
          'solicitante' => $requisicion_data['nombre_solicitante'],
          'entidad' => $requisicion_data['entidad_nombre'] ?? 'Sin especificar',
          'categoria' => $requisicion_data['categoria_nombre'] ?? 'Sin especificar',
          'fecha_solicitud' => date('d/m/Y H:i', strtotime($requisicion_data['fecha_solicitud'])),
          'ubicacion' => generarTextoUbicacion($requisicion_data)
        ];

        error_log("Enviando correo a: " . $requisicion_data['correo_corporativo']);

        // Enviar la notificación
        $resultado = $emailHandler->enviarNotificacionCambioEstado(
          $requisicion_data['correo_corporativo'],
          $requisicion_data['nombre_solicitante'],
          $datosRequisicion
        );

        if ($resultado) {
          error_log("✅ CORREO ENVIADO EXITOSAMENTE");
          header("Location: see_requis.php?id=$id&success=1&email=enviado");
        } else {
          error_log("FALLÓ EL ENVÍO DEL CORREO");
          header("Location: see_requis.php?id=$id&success=1&email=error");
        }
        exit;
      } catch (Exception $e) {
        error_log("ERROR EN EXCEPCIÓN AL ENVIAR CORREO: " . $e->getMessage());
        error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        header("Location: see_requis.php?id=$id&success=1&email=excepcion");
        exit;
      }
    } else {
      $mensaje_error = "Error al actualizar el estado: " . $stmt_update->error;
      error_log("Error al actualizar estado: " . $stmt_update->error);
    }
  } else {
    $mensaje_error = "Estado no válido";
  }
}

// Obtener requisición CON LOS NUEVOS CAMPOS DE UBICACIÓN
$sql = "SELECT r.*, e.nombre AS entidad, u.nombres, u.apellidos, u.correo_corporativo, c.nombre AS categoria,
               p.nombre_proyecto, o.nombre_obra, cat.nombre_catalogo
        FROM requisiciones r
        JOIN entidades e ON r.entidad_id = e.id
        LEFT JOIN usuarios u ON r.solicitante_id = u.id
        JOIN categorias c ON r.categoria_id = c.id
        LEFT JOIN proyectos p ON r.proyecto_id = p.id
        LEFT JOIN obras o ON r.obra_id = o.id
        LEFT JOIN catalogos cat ON r.catalogo_id = cat.id
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$requisicion = $stmt->get_result()->fetch_assoc();

if (!$requisicion) {
  die("Requisición no encontrada");
}

// Obtener items CON CONCEPTOS
$sql_items = "SELECT ri.*, 
                     ps.nombre AS producto, 
                     ps.tipo, 
                     un.nombre AS unidad,
                     con.codigo_concepto, 
                     con.nombre_concepto,
                     con.numero_original
              FROM requisicion_items ri
              JOIN productos_servicios ps ON ri.producto_id = ps.id
              JOIN unidades un ON ri.unidad_id = un.id
              LEFT JOIN conceptos con ON ri.concepto_id = con.id
              WHERE ri.requisicion_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $id);
$stmt_items->execute();
$items = $stmt_items->get_result();

// Obtener el comentario de rechazo si está rechazada
$comentario_rechazo = '';
if ($requisicion['estado'] === 'rechazado') {
  try {
    $sql_comentario = "SELECT comentario FROM requisicion_historial 
                          WHERE requisicion_id = ? AND accion = 'Rechazó requisición' 
                          ORDER BY fecha_cambio DESC LIMIT 1";
    $stmt_comentario = $conn->prepare($sql_comentario);
    $stmt_comentario->bind_param("i", $id);
    $stmt_comentario->execute();
    $result_comentario = $stmt_comentario->get_result();

    if ($result_comentario && $result_comentario->num_rows > 0) {
      $comentario_rechazo = $result_comentario->fetch_assoc()['comentario'];
    }
  } catch (Exception $e) {
    error_log("Error al obtener comentario de rechazo: " . $e->getMessage());
  }
}

// Obtener archivos adjuntos
$sql_archivos = "SELECT id, nombre_archivo, ruta_archivo, tamaño_archivo, tipo_mime, fecha_subida 
                 FROM requisicion_archivos 
                 WHERE requisicion_id = ?
                 ORDER BY fecha_subida DESC";
$stmt_archivos = $conn->prepare($sql_archivos);
$stmt_archivos->bind_param("i", $id);
$stmt_archivos->execute();
$archivos = $stmt_archivos->get_result();

// Función para formatear bytes
function formatBytes($bytes, $precision = 2)
{
  $units = array('B', 'KB', 'MB', 'GB', 'TB');
  $bytes = max($bytes, 0);
  $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
  $pow = min($pow, count($units) - 1);
  $bytes /= (1 << (10 * $pow));
  return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Genera texto descriptivo de la ubicación seleccionada
 */
function generarTextoUbicacion($requisicion_data)
{
  $ubicacion = [];

  if (!empty($requisicion_data['nombre_proyecto'])) {
    $ubicacion[] = "Proyecto: " . $requisicion_data['nombre_proyecto'];
  }

  if (!empty($requisicion_data['nombre_obra'])) {
    $ubicacion[] = "Obra: " . $requisicion_data['nombre_obra'];
  }

  if (!empty($requisicion_data['nombre_catalogo'])) {
    $ubicacion[] = "Catálogo: " . $requisicion_data['nombre_catalogo'];
  }

  return !empty($ubicacion) ? implode(" | ", $ubicacion) : "Sin ubicación específica";
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">
<style>
    .btn-estado {
      margin: 5px 5px 5px 0;
    }
    .comentario-rechazo {
      background-color: #fff5f5;
      border-left: 4px solid var(--danger-color, #dc3545);
      padding: 15px;
      border-radius: 4px;
      margin-top: 10px;
    }
    .comentario-header {
      font-weight: bold;
      color: var(--danger-color, #dc3545);
      margin-bottom: 8px;
    }
    .concepto-info {
      font-size: 0.85rem;
      color: #6c757d;
      margin-top: 4px;
    }
    .concepto-badge {
      background-color: #e9ecef;
      color: #495057;
      font-size: 0.75rem;
      padding: 2px 6px;
      border-radius: 3px;
      display: inline-block;
      margin-right: 4px;
    }
    #loadingOverlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.55);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    .loading-box {
      background: #ffffff;
      padding: 25px 40px;
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
      text-align: center;
      font-size: 17px;
      font-weight: bold;
    }
</style>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <a href="<?= BASE_URL ?>/orders/list_requis.php">Registro de Requisiciones</a>
                <span class="separator">›</span>
                <span>Detalles de Requisición</span>
            </nav>
            <h1 class="orders-page-title">Requisición - <?= htmlspecialchars($requisicion['folio']) ?></h1>
        </div>
        <a href="list_requis.php" class="btn-geco-outline">
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="orders-ajax-fade">

        <!-- Mostrar mensajes -->
        <?php if (isset($_GET['success'])): ?>
          <?php
          $email_status = $_GET['email'] ?? '';
          $mensaje_clase = 'success';
          $icono = 'check-circle';
          $mensaje = 'Estado actualizado correctamente';

          switch ($email_status) {
            case 'enviado':
              $mensaje .= ' y notificación enviada por correo al solicitante.';
              break;
            case 'error':
              $mensaje_clase = 'warning';
              $icono = 'exclamation-triangle';
              $mensaje .= ', pero hubo un problema al enviar la notificación por correo.';
              break;
            case 'excepcion':
              $mensaje_clase = 'warning';
              $icono = 'exclamation-triangle';
              $mensaje .= ', pero ocurrió un error al intentar enviar el correo.';
              break;
            case 'no_correo':
              $mensaje_clase = 'warning';
              $icono = 'exclamation-triangle';
              $mensaje .= ', pero el solicitante no tiene correo registrado.';
              break;
            default:
              $mensaje .= '.';
          }
          ?>
          <div class="alert alert-<?= $mensaje_clase ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $icono ?>"></i> <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($mensaje_error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= $mensaje_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Card 1: Información General -->
        <div class="oc-card mb-4">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="bi bi-info-circle"></i> Información General</span>
            </div>
            <div class="oc-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="oc-form-label">Folio</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['folio']) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="oc-form-label">Fecha de Solicitud</label>
                        <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($requisicion['fecha_solicitud'])) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="oc-form-label">Entidad</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['entidad']) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="oc-form-label">Solicitante</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombres'] . ' ' . $requisicion['apellidos']) ?>" readonly>
                        <?php if (!empty($requisicion['correo_corporativo'])): ?>
                            <div class="mt-1">
                                <small class="text-muted"><i class="bi bi-envelope"></i> <?= htmlspecialchars($requisicion['correo_corporativo']) ?></small>
                            </div>
                        <?php else: ?>
                            <div class="mt-1">
                                <small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Sin correo registrado</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="oc-form-label">Categoría</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['categoria']) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="oc-form-label">Estado Actual</label>
                        <div class="mt-1">
                            <?php
                            $badge_map = [
                                'pendiente' => ['status-badge--pendiente', 'bi-clock', 'Pendiente'],
                                'espera'    => ['status-badge--pendiente', 'bi-clock', 'En Espera'],
                                'aprobado'  => ['status-badge--aprobado', 'bi-check-circle', 'Aprobado'],
                                'aprobada'  => ['status-badge--aprobado', 'bi-check-circle', 'Aprobada'],
                                'rechazado' => ['status-badge--rechazado', 'bi-x-circle', 'Rechazado'],
                                'rechazada' => ['status-badge--rechazado', 'bi-x-circle', 'Rechazada']
                            ];
                            $b = $badge_map[$requisicion['estado']] ?? ['status-badge--pendiente', 'bi-circle', ucfirst($requisicion['estado'])];
                            echo '<span class="status-badge ' . $b[0] . '"><i class="bi ' . $b[1] . '"></i> ' . $b[2] . '</span>';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Ubicación del Presupuesto (Conditional) -->
        <?php if (!empty($requisicion['nombre_proyecto']) || !empty($requisicion['nombre_obra']) || !empty($requisicion['nombre_catalogo'])): ?>
            <div class="oc-card mb-4">
                <div class="oc-card-header">
                    <span class="oc-card-header__title"><i class="bi bi-diagram-3"></i> Ubicación del Presupuesto</span>
                </div>
                <div class="oc-card-body">
                    <div class="row g-3">
                        <?php if (!empty($requisicion['nombre_proyecto'])): ?>
                            <div class="col-md-6">
                                <label class="oc-form-label">Proyecto</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombre_proyecto']) ?>" readonly>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($requisicion['nombre_obra'])): ?>
                            <div class="col-md-6">
                                <label class="oc-form-label">Obra</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombre_obra']) ?>" readonly>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($requisicion['nombre_catalogo'])): ?>
                            <div class="col-md-6">
                                <label class="oc-form-label">Catálogo</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombre_catalogo']) ?>" readonly>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Card 3: Motivo del Rechazo (Conditional) -->
        <?php if ($requisicion['estado'] === 'rechazado' && !empty($comentario_rechazo)): ?>
            <div class="oc-card mb-4 border-danger">
                <div class="oc-card-header bg-danger-subtle text-danger" style="border-bottom: 1px solid rgba(220,53,69,0.15);">
                    <span class="oc-card-header__title text-danger"><i class="bi bi-chat-dots text-danger" style="color: #dc3545 !important;"></i> Motivo del Rechazo</span>
                </div>
                <div class="oc-card-body">
                    <div class="comentario-rechazo">
                        <div class="comentario-header">
                            <i class="bi bi-info-circle"></i> Comentario del supervisor:
                        </div>
                        <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($comentario_rechazo)) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Card 4: Items Solicitados -->
        <div class="oc-card mb-4">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="bi bi-list-ul"></i> Items Solicitados</span>
            </div>
            <div class="oc-card-body p-0">
                <div class="orders-table-wrap">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th style="width: 120px;">Tipo</th>
                                <th>Producto/Servicio</th>
                                <th style="width: 100px;">Cantidad</th>
                                <th style="width: 100px;">Unidad</th>
                                <th>Concepto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            while ($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $item['tipo'] == 'producto' ? '#e3f2fd' : '#e8f5e9' ?>; color: <?= $item['tipo'] == 'producto' ? '#0d47a1' : '#1b5e20' ?>; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                                            <?= ucfirst(htmlspecialchars($item['tipo'])) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($item['producto']) ?></strong></td>
                                    <td><?= htmlspecialchars($item['cantidad']) ?></td>
                                    <td><?= htmlspecialchars($item['unidad']) ?></td>
                                    <td>
                                        <?php if (!empty($item['codigo_concepto'])): ?>
                                            <div class="concepto-info">
                                                <?php if (!empty($item['numero_original'])): ?>
                                                    <span class="concepto-badge">
                                                        <i class="bi bi-hash"></i> <?= htmlspecialchars($item['numero_original']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="concepto-badge">
                                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($item['codigo_concepto']) ?>
                                                </span>
                                                <?php if (!empty($item['nombre_concepto'])): ?>
                                                    <br>
                                                    <small class="text-muted d-block mt-1"><?= htmlspecialchars($item['nombre_concepto']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Card 5: Detalles Adicionales (Conditional) -->
        <?php if (!empty($requisicion['extra']) || !empty($requisicion['descripcion']) || !empty($requisicion['observaciones'])): ?>
            <div class="oc-card mb-4">
                <div class="oc-card-header">
                    <span class="oc-card-header__title"><i class="bi bi-file-text"></i> Detalles Adicionales</span>
                </div>
                <div class="oc-card-body">
                    <div class="row g-3">
                        <?php if (!empty($requisicion['extra'])): ?>
                            <div class="col-md-12">
                                <label class="oc-form-label"><i class="bi bi-plus-circle me-1 text-primary"></i> Producto/servicio no listado</label>
                                <textarea class="form-control" rows="3" readonly style="background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057;"><?= htmlspecialchars($requisicion['extra']) ?></textarea>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($requisicion['descripcion'])): ?>
                            <div class="col-md-12">
                                <label class="oc-form-label"><i class="bi bi-info-circle me-1 text-primary"></i> Descripción General</label>
                                <textarea class="form-control" rows="3" readonly style="background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057;"><?= htmlspecialchars($requisicion['descripcion']) ?></textarea>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($requisicion['observaciones'])): ?>
                            <div class="col-md-12">
                                <label class="oc-form-label"><i class="bi bi-chat-text me-1 text-primary"></i> Observaciones</label>
                                <textarea class="form-control" rows="3" readonly style="background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057;"><?= htmlspecialchars($requisicion['observaciones']) ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Card 6: Archivos Adjuntos -->
        <div class="oc-card mb-4">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="bi bi-paperclip"></i> Archivos Adjuntos</span>
            </div>
            <div class="oc-card-body p-0">
                <?php if ($archivos->num_rows > 0): ?>
                    <div class="orders-table-wrap">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Nombre del Archivo</th>
                                    <th style="width: 120px;">Tamaño</th>
                                    <th style="width: 120px;">Tipo</th>
                                    <th style="width: 120px; text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                while ($archivo = $archivos->fetch_assoc()):
                                    $extension = strtolower(pathinfo($archivo['nombre_archivo'], PATHINFO_EXTENSION));
                                    $icono = 'file-earmark';
                                    $color = 'secondary';

                                    if (in_array($extension, ['pdf'])) {
                                        $icono = 'file-earmark-pdf';
                                        $color = 'danger';
                                    } elseif (in_array($extension, ['doc', 'docx'])) {
                                        $icono = 'file-earmark-word';
                                        $color = 'primary';
                                    } elseif (in_array($extension, ['xls', 'xlsx'])) {
                                        $icono = 'file-earmark-excel';
                                        $color = 'success';
                                    } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        $icono = 'file-earmark-image';
                                        $color = 'warning';
                                    } elseif (in_array($extension, ['zip', 'rar'])) {
                                        $icono = 'file-earmark-zip';
                                        $color = 'dark';
                                    }
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <i class="bi bi-<?= $icono ?> text-<?= $color ?> me-2 fs-5"></i>
                                            <strong><?= htmlspecialchars($archivo['nombre_archivo']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= formatBytes($archivo['tamaño_archivo']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $color === 'secondary' ? 'light text-dark' : $color ?> text-uppercase">
                                                <?= htmlspecialchars($extension) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions-group" style="justify-content: center;">
                                                <button type="button"
                                                        class="btn-action btn-action--view"
                                                        onclick="verArchivo(<?= $archivo['id'] ?>, '<?= htmlspecialchars($archivo['tipo_mime']) ?>')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-info-circle me-1"></i> No hay archivos adjuntos en esta requisición.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 7: Cambiar Estado (Supervisión) (Conditional) -->
        <?php
        $esEncargado = isset($_SESSION['departamento']) && in_array($_SESSION['departamento'], ['Gerente de Operaciones', 'Procura']);
        if ($requisicion['estado'] === 'pendiente' && $esEncargado):
        ?>
            <div class="oc-card mb-4 border-primary">
                <div class="oc-card-header bg-primary-subtle text-primary" style="border-bottom: 1px solid rgba(13,110,253,0.15);">
                    <span class="oc-card-header__title text-primary"><i class="bi bi-gear text-primary" style="color: #0d6efd !important;"></i> Cambiar Estado (Supervisión)</span>
                </div>
                <div class="oc-card-body">
                    <?php if (!empty($requisicion['correo_corporativo'])): ?>
                        <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-envelope-fill text-info fs-5"></i>
                            <div>
                                <strong>Notificación automática:</strong> Al cambiar el estado, se enviará un correo a 
                                <strong><?= htmlspecialchars($requisicion['nombres'] . ' ' . $requisicion['apellidos']) ?></strong> 
                                (<?= htmlspecialchars($requisicion['correo_corporativo']) ?>).
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                            <div>
                                <strong>Advertencia:</strong> El solicitante no tiene correo registrado. No se enviará notificación automática.
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="mt-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="oc-form-label">Nuevo Estado <span class="required">*</span></label>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success" onclick="seleccionarEstado('aprobado')">
                                        <i class="bi bi-check-circle"></i> Aprobar
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="seleccionarEstado('rechazado')">
                                        <i class="bi bi-x-circle"></i> Rechazar
                                    </button>
                                </div>
                                <input type="hidden" name="nuevo_estado" id="nuevo_estado" required>
                            </div>

                            <div class="col-md-12 mt-3">
                                <label class="oc-form-label">Comentario (Opcional)</label>
                                <textarea class="form-control" name="comentario" rows="3" placeholder="Agregue un comentario sobre la decisión..."></textarea>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-info-circle"></i>
                                    Si rechaza la requisición, este comentario se mostrará como motivo del rechazo
                                    <?= !empty($requisicion['correo_corporativo']) ? ' y se incluirá en el correo de notificación' : '' ?>.
                                </small>
                            </div>

                            <div class="col-md-12 mt-3">
                                <button type="submit" name="cambiar_estado" class="btn-geco-primary" id="btnConfirmar" disabled style="opacity:0.65;">
                                    <i class="bi bi-check-lg"></i> Seleccione una acción arriba
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div> <!-- /orders-ajax-fade -->
</div> <!-- /orders-page-container -->

<div id="loadingOverlay">
    <div class="loading-box">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-3">Procesando… por favor espere</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Función para seleccionar estado
    function seleccionarEstado(estado) {
        document.getElementById('nuevo_estado').value = estado;
        document.getElementById('btnConfirmar').disabled = false;
        document.getElementById('btnConfirmar').style.opacity = '1';

        const btnConfirmar = document.getElementById('btnConfirmar');
        if (estado === 'aprobado') {
            btnConfirmar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Aprobación';
            btnConfirmar.className = 'btn btn-success';
        } else {
            btnConfirmar.innerHTML = '<i class="bi bi-x-lg"></i> Confirmar Rechazo';
            btnConfirmar.className = 'btn btn-danger';
        }
    }

    // Función para ver archivo
    function verArchivo(archivoId, tipoMime) {
        const tiposVisualizables = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        if (tiposVisualizables.includes(tipoMime)) {
            window.open('/orders/view_archivo.php?id=' + archivoId, '_blank');
        } else {
            UI.toast.info('Este tipo de archivo no se puede visualizar en el navegador. Se descargará automáticamente.');
            window.open('/orders/download_archivo.php?id=' + archivoId, '_blank');
        }
    }
</script>

<script>
    document.querySelector("form")?.addEventListener("submit", function(e) {
        // Mostrar overlay
        document.getElementById("loadingOverlay").style.display = "flex";

        // Deshabilitar todos los botones del formulario
        const buttons = this.querySelectorAll("#btnConfirmar, input[type='submit']");
        buttons.forEach(btn => btn.disabled = true);
    });
</script>

<script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
<?php include __DIR__ . "/../includes/footer.php"; ?>