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

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Detalles Requisición <?= htmlspecialchars($requisicion['folio']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/new_order.css">
  <style>
    .estado-badge {
      font-size: 1rem;
      padding: 8px 16px;
    }

    .btn-estado {
      margin: 5px;
    }

    .comentario-rechazo {
      background-color: #f8f9fa;
      border-left: 4px solid #dc3545;
      padding: 15px;
      border-radius: 4px;
      margin-top: 10px;
    }

    .comentario-header {
      font-weight: bold;
      color: #dc3545;
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
    }

    .form-body {
      padding-top: 0;
    }

    /* Overlay de carga pantalla completa */
    #loadingOverlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.55);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }

    /* Contenedor del spinner */
    .loading-box {
      background: #ffffff;
      padding: 25px 40px;
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
      text-align: center;
      font-size: 17px;
      font-weight: bold;
    }

    .spinner-border {
      width: 3rem;
      height: 3rem;
    }
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
        <a href="<?= BASE_URL ?>/orders/list_requis.php">Registro de Requisiciones</a>
        <span>/</span>
        <span>Detalles de Requisición</span>
      </div>

      <div class="row align-items-end">
        <div class="col-lg-8">
          <h1 class="hero-title">Requisición - <?= htmlspecialchars($requisicion['folio']) ?></h1>
        </div>
      </div>

    </div>
  </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content-wrapper">

    <div class="form-container">

      <div class="form-body">
        <!-- Mostrar mensajes -->
        <?php
        if (isset($_GET['success'])): ?>
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
        <?php
        endif; ?>

        <?php
        if (isset($mensaje_error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= $mensaje_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php
        endif; ?>

        <!-- Información General -->
        <div class="section-title">
          <i class="bi bi-info-circle"></i>
          Información General
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Folio</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['folio']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Fecha de Solicitud</label>
            <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($requisicion['fecha_solicitud'])) ?>" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Entidad</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['entidad']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Solicitante</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombres'] . ' ' . $requisicion['apellidos']) ?>" readonly>
            <?php
            if (!empty($requisicion['correo_corporativo'])): ?>
              <small class="text-muted">
                <i class="bi bi-envelope"></i> <?= htmlspecialchars($requisicion['correo_corporativo']) ?>
              </small>
            <?php
            else: ?>
              <small class="text-warning">
                <i class="bi bi-exclamation-triangle"></i> Sin correo registrado
              </small>
            <?php
            endif; ?>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Categoría</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['categoria']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Estado Actual</label>
            <div class="mt-1">
              <?php
              switch ($requisicion['estado']) {
                case 'pendiente':
                  echo '<span class="badge bg-warning text-dark estado-badge"><i class="bi bi-clock"></i> Pendiente</span>';
                  break;
                case 'aprobado':
                  echo '<span class="badge bg-success estado-badge"><i class="bi bi-check-circle"></i> Aprobado</span>';
                  break;
                case 'rechazado':
                  echo '<span class="badge bg-danger estado-badge"><i class="bi bi-x-circle"></i> Rechazado</span>';
                  break;
              }
              ?>
            </div>
          </div>
        </div>

        <!-- Ubicación del Presupuesto -->
        <?php
        if (
          !empty($requisicion['nombre_proyecto']) || !empty($requisicion['nombre_obra']) ||
          !empty($requisicion['nombre_catalogo'])
        ): ?>
          <div class="section-title mt-4">
            <i class="bi bi-diagram-3"></i>
            Ubicación del Presupuesto
          </div>

          <div class="row mb-3">
            <?php
            if (!empty($requisicion['nombre_proyecto'])): ?>
              <div class="col-md-6">
                <label class="form-label">Proyecto</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombre_proyecto']) ?>" readonly>
              </div>
            <?php
            endif; ?>

            <?php
            if (!empty($requisicion['nombre_obra'])): ?>
              <div class="col-md-6">
                <label class="form-label">Obra</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombre_obra']) ?>" readonly>
              </div>
            <?php
            endif; ?>
          </div>

          <div class="row mb-3">
            <?php
            if (!empty($requisicion['nombre_catalogo'])): ?>
              <div class="col-md-6">
                <label class="form-label">Catálogo</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($requisicion['nombre_catalogo']) ?>" readonly>
              </div>
            <?php
            endif; ?>
          </div>
        <?php
        endif; ?>

        <!-- Mostrar comentario de rechazo si está rechazada -->
        <?php
        if ($requisicion['estado'] === 'rechazado' && !empty($comentario_rechazo)): ?>
          <div class="section-title mt-4">
            <i class="bi bi-chat-dots"></i>
            Motivo del Rechazo
          </div>
          <div class="comentario-rechazo">
            <div class="comentario-header">
              <i class="bi bi-info-circle"></i> Comentario del supervisor:
            </div>
            <p class="mb-0"><?= nl2br(htmlspecialchars($comentario_rechazo)) ?></p>
          </div>
        <?php
        endif; ?>

        <!-- Items -->
        <div class="section-title mt-4">
          <i class="bi bi-list-ul"></i>
          Items Solicitados
        </div>

        <table class="table table-bordered">
          <thead>
            <tr>
              <th>#</th>
              <th>Tipo</th>
              <th>Producto/Servicio</th>
              <th>Cantidad</th>
              <th>Unidad</th>
              <th>Concepto</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $i = 1;
            while ($item = $items->fetch_assoc()): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= ucfirst(htmlspecialchars($item['tipo'])) ?></td>
                <td><?= htmlspecialchars($item['producto']) ?></td>
                <td><?= htmlspecialchars($item['cantidad']) ?></td>
                <td><?= htmlspecialchars($item['unidad']) ?></td>
                <td>
                  <?php
                  if (!empty($item['codigo_concepto'])): ?>
                    <div class="concepto-info">
                      <?php
                      if (!empty($item['numero_original'])): ?>
                        <span class="concepto-badge">
                          <i class="bi bi-hash"></i> <?= htmlspecialchars($item['numero_original']) ?>
                        </span>
                      <?php
                      endif; ?>
                      <span class="concepto-badge">
                        <i class="bi bi-tag"></i> <?= htmlspecialchars($item['codigo_concepto']) ?>
                      </span>
                      <?php
                      if (!empty($item['nombre_concepto'])): ?>
                        <br>
                        <small><?= htmlspecialchars($item['nombre_concepto']) ?></small>
                      <?php
                      endif; ?>
                    </div>
                  <?php
                  else: ?>
                    <span class="text-muted">-</span>
                  <?php
                  endif; ?>
                </td>
              </tr>
            <?php
            endwhile; ?>
          </tbody>
        </table>

        <!-- Extra -->
        <?php
        if (!empty($requisicion['extra'])): ?>
          <div class="section-title mt-4">
            <i class="bi bi-plus-circle"></i>
            Producto/servicio no listado
          </div>
          <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($requisicion['extra']) ?></textarea>
        <?php
        endif; ?>

        <!-- Descripción -->
        <div class="section-title mt-4">
          <i class="bi bi-file-text"></i>
          Descripción
        </div>
        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($requisicion['descripcion']) ?></textarea>

        <!-- Observaciones -->
        <?php
        if (!empty($requisicion['observaciones'])): ?>
          <div class="section-title mt-4">
            <i class="bi bi-chat-text"></i>
            Observaciones
          </div>
          <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($requisicion['observaciones']) ?></textarea>
        <?php
        endif; ?>

        <!-- Archivos Adjuntos -->
        <div class="section-title mt-4">
          <i class="bi bi-paperclip"></i>
          Archivos Adjuntos
        </div>

        <?php
        if ($archivos->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 45%">Nombre del Archivo</th>
                  <th style="width: 15%">Tamaño</th>
                  <th style="width: 15%">Tipo</th>
                  <th style="width: 20%">Acciones</th>
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
                  <tr class="archivo-item">
                    <td><?= $i++ ?></td>
                    <td>
                      <i class="bi bi-<?= $icono ?> text-<?= $color ?> me-2"></i>
                      <strong><?= htmlspecialchars($archivo['nombre_archivo']) ?></strong>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark">
                        <?= formatBytes($archivo['tamaño_archivo']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-<?= $color ?>">
                        <?= strtoupper($extension) ?>
                      </span>
                    </td>
                    <td>
                      <button type="button"
                        class="btn btn-sm btn-outline-info"
                        onclick="verArchivo(<?= $archivo['id'] ?>, '<?= htmlspecialchars($archivo['tipo_mime']) ?>')"
                        title="Ver archivo">
                        <i class="bi bi-eye"></i> Ver
                      </button>
                    </td>
                  </tr>
                <?php
                endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php
        else: ?>
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No hay archivos adjuntos en esta requisición.
          </div>
        <?php
        endif; ?>

        <!-- Cambiar Estado (solo para requisiciones pendientes y si es supervisor) -->
        <?php
        $esEncargado = isset($_SESSION['departamento']) && in_array($_SESSION['departamento'], ['Gerente de Operaciones', 'Procura']);
        if ($requisicion['estado'] === 'pendiente' && $esEncargado):
        ?>
          <div class="section-title mt-4">
            <i class="bi bi-gear"></i>
            Cambiar Estado
          </div>

          <?php
          if (!empty($requisicion['correo_corporativo'])): ?>
            <div class="alert alert-info mb-3">
              <i class="bi bi-envelope"></i>
              <strong>Notificación automática:</strong> Al cambiar el estado, se enviará un correo a
              <strong><?= htmlspecialchars($requisicion['nombres'] . ' ' . $requisicion['apellidos']) ?></strong>
              (<?= htmlspecialchars($requisicion['correo_corporativo']) ?>)
            </div>
          <?php
          else: ?>
            <div class="alert alert-warning mb-3">
              <i class="bi bi-exclamation-triangle"></i>
              <strong>Advertencia:</strong> El solicitante no tiene correo registrado.
              No se enviará notificación automática.
            </div>
          <?php
          endif; ?>

          <form method="POST" class="mb-4">
            <div class="row">
              <div class="col-md-6">
                <label class="form-label">Nuevo Estado</label>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-success btn-estado" onclick="seleccionarEstado('aprobado')">
                    <i class="bi bi-check-circle"></i> Aprobar
                  </button>
                  <button type="button" class="btn btn-danger btn-estado" onclick="seleccionarEstado('rechazado')">
                    <i class="bi bi-x-circle"></i> Rechazar
                  </button>
                </div>
                <input type="hidden" name="nuevo_estado" id="nuevo_estado" required>
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-12">
                <label class="form-label">Comentario (Opcional)</label>
                <textarea class="form-control" name="comentario" rows="3" placeholder="Agregue un comentario sobre la decisión..."></textarea>
                <small class="text-muted">
                  <i class="bi bi-info-circle"></i>
                  Si rechaza la requisición, este comentario se mostrará como motivo del rechazo
                  <?= !empty($requisicion['correo_corporativo']) ? 'y se incluirá en el correo de notificación' : '' ?>.
                </small>
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-12">
                <button type="submit" name="cambiar_estado" class="btn btn-primary" id="btnConfirmar" disabled>
                  <i class="bi bi-check-lg"></i> Confirmar Cambio de Estado
                </button>
              </div>
            </div>
          </form>
        <?php
        endif; ?>
      </div>
    </div>
  </div>

  <!-- Boton de regreso -->
  <div class="fab-container-backbtn">
    <a onclick="history.back()" class="fab-button-backbtn gray">
      <i class="bi bi-arrow-left"></i>
      <span class="fab-tooltip-backbtn">Volver</span>
    </a>
  </div>

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
        alert('Este tipo de archivo no se puede visualizar en el navegador. Se descargará automáticamente.');
        window.open('/orders/download_archivo.php?id=' + archivoId, '_blank');
      }
    }
  </script>

  <script>
    document.querySelector("form").addEventListener("submit", function(e) {

      // Mostrar overlay
      document.getElementById("loadingOverlay").style.display = "flex";

      // Deshabilitar todos los botones del formulario
      const buttons = this.querySelectorAll("btnConfirmar, input[type='submit']");
      buttons.forEach(btn => btn.disabled = true);

    });
  </script>

  <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
  <?php
  include __DIR__ . "/../includes/footer.php"; ?>


</body>

</html>