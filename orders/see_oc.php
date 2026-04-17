<?php
require_once __DIR__ . '/../config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

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
    'revisado' => 'Revisado',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado',
    'devuelto' => 'Devuelto para Editar',
    'comprobante_subido' => 'Comprobante Subido',
    'pagado' => 'Pagado y Completado'
  ];
  return $estados[$estado] ?? ucfirst($estado);
}

// ==============================================================================
// FUNCIÓN PARA ACTUALIZAR MONTOS DISPONIBLES (SOLO CUANDO ES "PAGADO")
// ==============================================================================
function actualizarMontosDisponibles($conn, $orden_id, $total_orden)
{
  error_log(" ACTUALIZANDO MONTOS DISPONIBLES PARA ORDEN PAGADA");
  error_log("   Orden ID: {$orden_id}");
  error_log("   Total Orden: {$total_orden}");

  // Obtener información de ubicación de la orden
  $sql_info = "SELECT proyecto_id, obra_id, catalogo_id, concepto_id 
                 FROM ordenes_compra 
                 WHERE id = ?";
  $stmt_info = $conn->prepare($sql_info);
  $stmt_info->bind_param("i", $orden_id);
  $stmt_info->execute();
  $info = $stmt_info->get_result()->fetch_assoc();
  $stmt_info->close();

  $proyecto_id = $info['proyecto_id'];
  $obra_id = $info['obra_id'];
  $catalogo_id = $info['catalogo_id'];

  error_log("   Proyecto ID: " . ($proyecto_id ?: 'NULL'));
  error_log("   Obra ID: " . ($obra_id ?: 'NULL'));
  error_log("   Catálogo ID: " . ($catalogo_id ?: 'NULL'));

  // ACTUALIZAR PROYECTO (SIEMPRE)
  if ($proyecto_id) {
    $sql_proyecto = "SELECT id, costo_directo_utilizado FROM presupuesto_control 
                        WHERE proyecto_id = ? AND obra_id IS NULL AND tipo = 'proyecto'";
    $stmt_proyecto = $conn->prepare($sql_proyecto);
    $stmt_proyecto->bind_param("i", $proyecto_id);
    $stmt_proyecto->execute();
    $result_proyecto = $stmt_proyecto->get_result();

    if ($result_proyecto->num_rows > 0) {
      $proyecto_data = $result_proyecto->fetch_assoc();
      $nuevo_utilizado_proyecto = $proyecto_data['costo_directo_utilizado'] + $total_orden;

      $sql_update_proyecto = "UPDATE presupuesto_control 
                                   SET costo_directo_utilizado = ?, updated_at = NOW() 
                                   WHERE id = ?";
      $stmt_update_proyecto = $conn->prepare($sql_update_proyecto);
      $stmt_update_proyecto->bind_param("di", $nuevo_utilizado_proyecto, $proyecto_data['id']);

      if ($stmt_update_proyecto->execute()) {
        error_log(" Proyecto actualizado: {$nuevo_utilizado_proyecto}");
      } else {
        error_log(" Error actualizando proyecto: " . $stmt_update_proyecto->error);
      }
      $stmt_update_proyecto->close();
    } else {
      // Obtener costo_directo del proyecto
      $sql_get_proyecto = "SELECT costo_directo FROM proyectos WHERE id = ?";
      $stmt_get = $conn->prepare($sql_get_proyecto);
      $stmt_get->bind_param("i", $proyecto_id);
      $stmt_get->execute();
      $proyecto_info = $stmt_get->get_result()->fetch_assoc();
      $stmt_get->close();

      $sql_insert_proyecto = "INSERT INTO presupuesto_control 
                                   (proyecto_id, tipo, costo_directo, costo_directo_utilizado) 
                                   VALUES (?, 'proyecto', ?, ?)";
      $stmt_insert_proyecto = $conn->prepare($sql_insert_proyecto);
      $stmt_insert_proyecto->bind_param(
        "idd",
        $proyecto_id,
        $proyecto_info['costo_directo'],
        $total_orden
      );

      if ($stmt_insert_proyecto->execute()) {
        error_log(" Registro creado para proyecto");
      } else {
        error_log(" Error creando registro proyecto: " . $stmt_insert_proyecto->error);
      }
      $stmt_insert_proyecto->close();
    }
    $stmt_proyecto->close();
  }

  // ACTUALIZAR OBRA (SI EXISTE)
  if ($obra_id) {
    $sql_obra = "SELECT id, costo_directo_utilizado FROM presupuesto_control 
                    WHERE obra_id = ? AND tipo = 'obra'";
    $stmt_obra = $conn->prepare($sql_obra);
    $stmt_obra->bind_param("i", $obra_id);
    $stmt_obra->execute();
    $result_obra = $stmt_obra->get_result();

    if ($result_obra->num_rows > 0) {
      $obra_data = $result_obra->fetch_assoc();
      $nuevo_utilizado_obra = $obra_data['costo_directo_utilizado'] + $total_orden;

      $sql_update_obra = "UPDATE presupuesto_control 
                               SET costo_directo_utilizado = ?, updated_at = NOW() 
                               WHERE id = ?";
      $stmt_update_obra = $conn->prepare($sql_update_obra);
      $stmt_update_obra->bind_param("di", $nuevo_utilizado_obra, $obra_data['id']);

      if ($stmt_update_obra->execute()) {
        error_log(" Obra actualizada: {$nuevo_utilizado_obra}");
      } else {
        error_log(" Error actualizando obra: " . $stmt_update_obra->error);
      }
      $stmt_update_obra->close();
    } else {
      // Obtener costo_directo de la obra
      $sql_get_obra = "SELECT costo_directo FROM obras WHERE id = ?";
      $stmt_get = $conn->prepare($sql_get_obra);
      $stmt_get->bind_param("i", $obra_id);
      $stmt_get->execute();
      $obra_info = $stmt_get->get_result()->fetch_assoc();
      $stmt_get->close();

      $sql_insert_obra = "INSERT INTO presupuesto_control 
                               (proyecto_id, obra_id, tipo, costo_directo, costo_directo_utilizado) 
                               VALUES (?, ?, 'obra', ?, ?)";
      $stmt_insert_obra = $conn->prepare($sql_insert_obra);
      $stmt_insert_obra->bind_param(
        "iidd",
        $proyecto_id,
        $obra_id,
        $obra_info['costo_directo'],
        $total_orden
      );

      if ($stmt_insert_obra->execute()) {
        error_log(" Registro creado para obra");
      } else {
        error_log(" Error creando registro obra: " . $stmt_insert_obra->error);
      }
      $stmt_insert_obra->close();
    }
    $stmt_obra->close();
  }

  error_log(" FIN ACTUALIZACIÓN MONTOS DISPONIBLES");
}

// ================================
// PROCESAR CAMBIO DE ESTADO
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
  $nuevo_estado = $_POST['nuevo_estado'];
  $comentario = $_POST['comentario'] ?? '';

  // Validar estado según el rol
  $departamento = $_SESSION['departamento'] ?? '';
  $estados_permitidos = [];

  if ($departamento === 'Subdirector General' || $departamento === 'Director General') {
    $estados_permitidos = ['aprobado', 'rechazado', 'devuelto'];
  } elseif ($departamento === 'Gerente de Operaciones') {
    $estados_permitidos = ['devuelto', 'revisado'];
  } elseif ($departamento === 'Gerente de Recursos Humanos') {
    $estados_permitidos = ['pagado'];
  }

  if (in_array($nuevo_estado, $estados_permitidos)) {

    // OBTENER DATOS ANTES DE ACTUALIZAR
    $sql_datos = "SELECT oc.*, 
                      u.correo_corporativo, 
                      CONCAT(u.nombres, ' ', u.apellidos) as nombre_solicitante,
                      e.nombre as entidad_nombre, 
                      c.nombre as categoria_nombre,
                      p.nombre as proveedor_nombre,
                      pro.nombre_proyecto,
                      ob.nombre_obra,
                      cat.nombre_catalogo,
                      con.codigo_concepto,
                      con.nombre_concepto
                      FROM ordenes_compra oc 
                      LEFT JOIN usuarios u ON oc.solicitante_id = u.id 
                      LEFT JOIN entidades e ON oc.entidad_id = e.id
                      LEFT JOIN categorias c ON oc.categoria_id = c.id
                      LEFT JOIN proveedores p ON oc.proveedor_id = p.id
                      LEFT JOIN proyectos pro ON oc.proyecto_id = pro.id
                      LEFT JOIN obras ob ON oc.obra_id = ob.id
                      LEFT JOIN catalogos cat ON oc.catalogo_id = cat.id
                      LEFT JOIN conceptos con ON oc.concepto_id = con.id
                      WHERE oc.id = ?";
    $stmt_datos = $conn->prepare($sql_datos);
    $stmt_datos->bind_param("i", $id);
    $stmt_datos->execute();
    $oc_data = $stmt_datos->get_result()->fetch_assoc();

    if (!$oc_data) {
      die("Error: No se pudieron obtener los datos de la orden de compra");
    }

    // ========================================
    // ACTUALIZAR ESTADO
    // ========================================
    $sql_update = "UPDATE ordenes_compra SET estado = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("si", $nuevo_estado, $id);

    if ($stmt_update->execute()) {
      error_log(" Estado actualizado en BD");

      // ========================================
      // REGISTRAR EN HISTORIAL
      // ========================================
      try {
        $sql_historial = "INSERT INTO orden_compra_historial (orden_compra_id, usuario_id, accion, comentario) 
                                 VALUES (?, ?, ?, ?)";
        $stmt_historial = $conn->prepare($sql_historial);
        $accion_texto = '';

        switch ($nuevo_estado) {
          case 'revisado':
            $accion_texto = 'Revisó orden de compra';
            break;
          case 'aprobado':
            $accion_texto = 'Aprobó orden de compra';
            break;
          case 'rechazado':
            $accion_texto = 'Rechazó orden de compra';
            break;
          case 'devuelto':
            $accion_texto = 'Devolvió orden de compra para editar';
            break;
          case 'pagado':
            $accion_texto = 'Marcó como pagado y completado';
            break;
        }

        $stmt_historial->bind_param("iiss", $id, $_SESSION['user_id'], $accion_texto, $comentario);
        $stmt_historial->execute();
        error_log(" Historial registrado");
      } catch (Exception $e) {
        error_log(" Error en historial: " . $e->getMessage());
      }

      // ========================================
      // ACCIONES ESPECIALES SOLO PARA "PAGADO"
      // ========================================
      $items_transferidos = 0;

      // Al marcar como pagada, se actualizan montos y se transfieren los items a concepto_items
      if ($nuevo_estado === 'pagado') {
        error_log("=== PROCESANDO ORDEN COMO PAGADA ===");
        // 1. Actualizar montos disponibles
        actualizarMontosDisponibles($conn, $id, $oc_data['total']);
        // 2. Transferir items a concepto_items si la orden tiene catálogo
        if (!empty($oc_data['catalogo_id'])) {
          // No se realiza transferencia automática a `concepto_items` porque
          // dicha tabla no existe en esta BD. Los items quedan vinculados
          // en `orden_compra_items` y se consultan directamente desde allí.
          error_log(" Transferencia a tabla 'concepto_items' omitida (tabla inexistente). Items quedan en orden_compra_items.");
        } else {
          error_log(" No se puede transferir: falta catálogo");
        }
      }

      // ========================================
      // PREPARAR DATOS PARA NOTIFICACIÓN
      // ========================================
      try {
        $emailHandler = new EmailHandler();

        $datosOrdenCompra = [
          'id' => $id,
          'folio' => $oc_data['folio'],
          'estado' => traducirEstado($nuevo_estado),
          'comentarios' => $comentario,
          'solicitante' => $oc_data['nombre_solicitante'],
          'entidad' => $oc_data['entidad_nombre'] ?? 'Sin especificar',
          'categoria' => $oc_data['categoria_nombre'] ?? 'Sin especificar',
          'proveedor' => $oc_data['proveedor_nombre'] ?? 'Sin especificar',
          'proyecto' => $oc_data['nombre_proyecto'] ?? 'Sin especificar',
          'obra' => $oc_data['nombre_obra'] ?? 'N/A',
          'catalogo' => $oc_data['nombre_catalogo'] ?? 'N/A',
          'concepto' => ($oc_data['codigo_concepto'] ?? '') . ($oc_data['nombre_concepto'] ? ' - ' . $oc_data['nombre_concepto'] : 'N/A'),
          'total' => '$' . number_format($oc_data['total'], 2),
          'fecha_solicitud' => date('d/m/Y H:i', strtotime($oc_data['fecha_solicitud']))
        ];

        // Agregar info de transferencia si aplica
        if ($items_transferidos > 0) {
          $datosOrdenCompra['items_transferidos'] = $items_transferidos;
          $datosOrdenCompra['catalogo_nombre'] = $oc_data['nombre_catalogo'] ?? 'Catálogo';
        }

        // ========================================
        // DETERMINAR DESTINATARIOS
        // ========================================
        $destinatarios = [];

        if ($nuevo_estado === 'aprobado' || $nuevo_estado === 'rechazado' || $nuevo_estado === 'devuelto') {
          // Notificar a SOLICITANTE y GERENTE DE RH

          // 1. Obtener solicitante
          $sql_solicitante = "SELECT id, correo_corporativo, CONCAT(nombres, ' ', apellidos) as nombre_completo 
                                       FROM usuarios 
                                       WHERE id = ?
                                       AND activo = 1
                                       AND correo_corporativo IS NOT NULL";

          $stmt_solicitante = $conn->prepare($sql_solicitante);
          $stmt_solicitante->bind_param("i", $oc_data['solicitante_id']);
          $stmt_solicitante->execute();
          $result_solicitante = $stmt_solicitante->get_result();

          if ($result_solicitante && $result_solicitante->num_rows > 0) {
            $solicitante = $result_solicitante->fetch_assoc();
            $solicitante['id'] = $oc_data['solicitante_id'];
            $destinatarios[] = $solicitante;
          }

          // 2. Obtener Gerente de Recursos Humanos
          $sql_gerente_rh = "SELECT u.id, u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo 
                                      FROM usuarios u
                                      JOIN departamentos d ON u.departamento_id = d.id
                                      WHERE d.nombre LIKE '%Gerente de Recursos Humanos%'
                                      AND u.activo = 1
                                      AND u.correo_corporativo IS NOT NULL";

          $result_gerente_rh = $conn->query($sql_gerente_rh);
          if ($result_gerente_rh && $result_gerente_rh->num_rows > 0) {
            while ($gerente = $result_gerente_rh->fetch_assoc()) {
              $destinatarios[] = $gerente;
            }
          }
        } elseif ($nuevo_estado === 'pagado') {
          // Notificar al SOLICITANTE y SUBDIRECTOR GENERAL

          // 1. Obtener solicitante
          $sql_solicitante = "SELECT id, correo_corporativo, CONCAT(nombres, ' ', apellidos) as nombre_completo 
                                       FROM usuarios 
                                       WHERE id = ?
                                       AND activo = 1
                                       AND correo_corporativo IS NOT NULL";

          $stmt_solicitante = $conn->prepare($sql_solicitante);
          $stmt_solicitante->bind_param("i", $oc_data['solicitante_id']);
          $stmt_solicitante->execute();
          $result_solicitante = $stmt_solicitante->get_result();

          if ($result_solicitante && $result_solicitante->num_rows > 0) {
            $solicitante = $result_solicitante->fetch_assoc();
            $solicitante['id'] = $oc_data['solicitante_id'];
            $destinatarios[] = $solicitante;
          }

          // 2. Obtener Subdirector General
          $sql_subdirector = "SELECT u.id, u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo 
                                       FROM usuarios u
                                       JOIN departamentos d ON u.departamento_id = d.id
                                       WHERE d.nombre LIKE '%Subdirector General%'
                                       AND u.activo = 1
                                       AND u.correo_corporativo IS NOT NULL";

          $result_subdirector = $conn->query($sql_subdirector);
          if ($result_subdirector && $result_subdirector->num_rows > 0) {
            while ($subdirector = $result_subdirector->fetch_assoc()) {
              $destinatarios[] = $subdirector;
            }
          }
        } elseif ($nuevo_estado === 'revisado') {
          // Cuando se marca como 'revisado' notificamos al SOLICITANTE y al SUBDIRECTOR GENERAL
          // 1. Obtener solicitante
          $sql_solicitante = "SELECT id, correo_corporativo, CONCAT(nombres, ' ', apellidos) as nombre_completo 
                               FROM usuarios 
                               WHERE id = ?
                               AND activo = 1
                               AND correo_corporativo IS NOT NULL";
          $stmt_solicitante = $conn->prepare($sql_solicitante);
          $stmt_solicitante->bind_param("i", $oc_data['solicitante_id']);
          $stmt_solicitante->execute();
          $result_solicitante = $stmt_solicitante->get_result();
          if ($result_solicitante && $result_solicitante->num_rows > 0) {
            $solicitante = $result_solicitante->fetch_assoc();
            $solicitante['id'] = $oc_data['solicitante_id'];
            $destinatarios[] = $solicitante;
          }

          // 2. Obtener Subdirector General
          $sql_subdirector = "SELECT u.id, u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo 
                               FROM usuarios u
                               JOIN departamentos d ON u.departamento_id = d.id
                               WHERE d.nombre LIKE '%Subdirector General%'
                               AND u.activo = 1
                               AND u.correo_corporativo IS NOT NULL";

          $result_subdirector = $conn->query($sql_subdirector);
          if ($result_subdirector && $result_subdirector->num_rows > 0) {
            while ($subdirector = $result_subdirector->fetch_assoc()) {
              $destinatarios[] = $subdirector;
            }
          }
        }

        // ========================================
        // ENVIAR CORREOS
        // ========================================
        $correos_enviados = 0;
        $correos_fallidos = 0;

        foreach ($destinatarios as $destinatario) {
          // Para el caso 'revisado' usamos la notificación general para todos (subdirector + solicitante)
          if ($nuevo_estado === 'revisado') {
            $resultado = $emailHandler->enviarNotificacionOrdenCompra(
              $destinatario['correo_corporativo'],
              $destinatario['nombre_completo'],
              $datosOrdenCompra
            );
          } else {
            // Determinar qué función usar según el destinatario
            if ($destinatario['id'] == $oc_data['solicitante_id']) {
              // Es el solicitante - usar función específica
              $resultado = $emailHandler->enviarNotificacionSolicitanteOC(
                $destinatario['correo_corporativo'],
                $destinatario['nombre_completo'],
                $datosOrdenCompra
              );
            } else {
              // Es otro destinatario (Gerente RH, etc.) - usar función general
              $resultado = $emailHandler->enviarNotificacionOrdenCompra(
                $destinatario['correo_corporativo'],
                $destinatario['nombre_completo'],
                $datosOrdenCompra
              );
            }
          }

          if ($resultado) {
            $correos_enviados++;
          } else {
            $correos_fallidos++;
          }
        }

        error_log(" NOTIFICACIONES: {$correos_enviados} enviados, {$correos_fallidos} fallidos");

        // Redirect con parámetros
        $params = "id={$id}&success=1";
        if ($correos_enviados > 0) {
          $params .= "&email=enviado&count={$correos_enviados}";
        } else {
          $params .= "&email=error";
        }
        if ($items_transferidos > 0) {
          $params .= "&transferidos={$items_transferidos}";
        }

        header("Location: see_oc.php?{$params}");
        exit;
      } catch (Exception $e) {
        error_log(" EXCEPCIÓN AL ENVIAR CORREO: " . $e->getMessage());
        header("Location: see_oc.php?id=$id&success=1&email=excepcion");
        exit;
      }
    } else {
      $mensaje_error = "Error al actualizar el estado: " . $stmt_update->error;
      error_log(" Error al actualizar estado: " . $stmt_update->error);
    }
  } else {
    $mensaje_error = "No tiene permisos para realizar esta acción";
  }
}

// ================================
// PROCESAR SUBIDA DE COMPROBANTE
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_comprobante'])) {
  $departamento = $_SESSION['departamento'] ?? '';

  if ($departamento === 'Gerente de Recursos Humanos') {
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {

      $uploadDir = __DIR__ . '/../uploads/comprobantes_pago/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }

      $nombreArchivo = basename($_FILES['comprobante']['name']);
      $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
      $nombreSinExtension = pathinfo($nombreArchivo, PATHINFO_FILENAME);
      $nombreUnico = $nombreSinExtension . '_' . uniqid() . '.' . $extension;
      $rutaArchivo = $uploadDir . $nombreUnico;

      if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $rutaArchivo)) {
        // Actualizar estado y guardar ruta del comprobante
        $sql_update = "UPDATE ordenes_compra 
                              SET estado = 'comprobante_subido', 
                                  comprobante_pago = ? 
                              WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $rutaArchivo, $id);

        if ($stmt_update->execute()) {
          // Registrar en historial
          $sql_historial = "INSERT INTO orden_compra_historial (orden_compra_id, usuario_id, accion, comentario) 
                                     VALUES (?, ?, 'Subió comprobante de pago', '')";
          $stmt_historial = $conn->prepare($sql_historial);
          $stmt_historial->bind_param("ii", $id, $_SESSION['user_id']);
          $stmt_historial->execute();

          header("Location: see_oc.php?id=$id&success=1&comprobante=subido");
          exit;
        } else {
          $mensaje_error = "Error al actualizar el estado";
        }
      } else {
        $mensaje_error = "Error al subir el archivo";
      }
    } else {
      $mensaje_error = "No se recibió ningún archivo o hubo un error";
    }
  } else {
    $mensaje_error = "No tiene permisos para subir comprobantes";
  }
}

// ================================
// OBTENER DATOS DE LA ORDEN
// ================================
$sql = "SELECT oc.*, e.nombre AS entidad, u.nombres, u.apellidos, c.nombre AS categoria, 
        p.nombre AS proveedor, r.folio AS folio_requisicion, 
        pro.nombre_proyecto, ob.nombre_obra, cat.nombre_catalogo, 
        con.codigo_concepto, con.nombre_concepto,
        sub.id as subcontrato_id, sub.proveedor_id as subcontrato_proveedor_id,
        prov_sub.nombre as subcontrato_proveedor_nombre
        FROM ordenes_compra oc
        JOIN entidades e ON oc.entidad_id = e.id
        JOIN usuarios u ON oc.solicitante_id = u.id
        JOIN categorias c ON oc.categoria_id = c.id
        JOIN proveedores p ON oc.proveedor_id = p.id
        LEFT JOIN requisiciones r ON oc.requisicion_id = r.id
        LEFT JOIN proyectos pro ON oc.proyecto_id = pro.id
        LEFT JOIN obras ob ON oc.obra_id = ob.id
        LEFT JOIN catalogos cat ON oc.catalogo_id = cat.id
        LEFT JOIN conceptos con ON oc.concepto_id = con.id
        LEFT JOIN subcontratos sub ON oc.subcontrato_id = sub.id
        LEFT JOIN proveedores prov_sub ON sub.proveedor_id = prov_sub.id
        WHERE oc.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$orden_compra = $stmt->get_result()->fetch_assoc();

if (!$orden_compra) {
  die("Orden de compra no encontrada");
}

// Obtener el comentario de rechazo si está rechazada
$comentario_rechazo = '';
if ($orden_compra['estado'] === 'rechazado') {
  try {
    $sql_comentario = "SELECT comentario FROM orden_compra_historial 
                          WHERE orden_compra_id = ? AND accion = 'Rechazó orden de compra' 
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

// Obtener items de la orden de compra
$sql_items = "SELECT oci.*, ps.nombre AS producto, ps.tipo, un.nombre AS unidad
              FROM orden_compra_items oci
              LEFT JOIN productos_servicios ps ON oci.producto_id = ps.id
              LEFT JOIN unidades un ON oci.unidad_id = un.id
              WHERE oci.orden_compra_id = ?
              ORDER BY oci.id ASC";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $id);
$stmt_items->execute();
$items = $stmt_items->get_result();

// Obtener archivos adjuntos de la orden de compra
$sql_archivos = "SELECT id, nombre_archivo, ruta_archivo, tamaño_archivo, tipo_mime, fecha_subida
                 FROM orden_compra_archivos
                 WHERE orden_compra_id = ?
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

// Verificar permisos del usuario según su departamento
$departamento = $_SESSION['departamento'] ?? '';
$puede_revisar = ($departamento === 'Gerente de Operaciones');                    // Pendiente → Revisado
$puede_aprobar_rechazar = ($departamento === 'Subdirector General');              // Revisado → Aprobado/Rechazado/Devuelto
$puede_subir_comprobante = ($departamento === 'Gerente de Recursos Humanos');    // Aprobado → Comprobante Subido
$puede_marcar_pagado = ($departamento === 'Gerente de Recursos Humanos');         // Comprobante Subido → Pagado
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Ver Orden de Compra <?= htmlspecialchars($orden_compra['folio']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
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

    .totales-box {
      background-color: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 20px;
      margin-top: 20px;
    }

    .total-final {
      background-color: #e7f3ff;
      border: 2px solid #0d6efd;
      font-weight: bold;
    }

    .comprobante-box {
      background-color: #d1ecf1;
      border: 1px solid #bee5eb;
      border-radius: 8px;
      padding: 15px;
      margin-top: 15px;
    }

    .ubicacion-box {
      background-color: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 15px;
      margin-top: 15px;
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

  <?php include $_SERVER['DOCUMENT_ROOT'] . "". BASE_URL ."/includes/navbar.php"; ?>

  <!-- HERO SECTION -->
  <div class="hero-section">
    <div class="container hero-content">
      <div class="breadcrumb-custom">
        <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
        <span>/</span>
        <span>Ver Orden de Compra</span>
      </div>

      <div class="row align-items-end">
        <div class="col-lg-8">
          <h1 class="hero-title">Orden de Compra <?= htmlspecialchars($orden_compra['folio']) ?></h1>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content-wrapper">

    <div class="form-container">

      <div class="form-body">
        <!-- Mostrar mensajes -->
        <?php if (isset($_GET['success'])): ?>
          <?php
          $email_status = $_GET['email'] ?? '';
          $comprobante_status = $_GET['comprobante'] ?? '';
          $transferidos = $_GET['transferidos'] ?? 0;
          $mensaje_clase = 'success';
          $icono = 'check-circle';
          $mensaje = 'Estado actualizado correctamente';

          if ($comprobante_status === 'subido') {
            $mensaje = 'Comprobante de pago subido exitosamente';
          } else {
            switch ($email_status) {
              case 'enviado':
                $count = $_GET['count'] ?? 0;
                $mensaje .= " y se enviaron {$count} notificaciones por correo.";
                if ($transferidos > 0) {
                  $mensaje .= " Se transfirieron {$transferidos} items al catálogo.";
                }
                break;
              case 'error':
                $mensaje_clase = 'warning';
                $icono = 'exclamation-triangle';
                $mensaje .= ', pero hubo un problema al enviar las notificaciones por correo.';
                if ($transferidos > 0) {
                  $mensaje .= " Se transfirieron {$transferidos} items al catálogo.";
                }
                break;
              case 'excepcion':
                $mensaje_clase = 'warning';
                $icono = 'exclamation-triangle';
                $mensaje .= ', pero ocurrió un error al intentar enviar los correos.';
                break;
              default:
                if ($transferidos > 0) {
                  $mensaje .= " Se transfirieron {$transferidos} items al catálogo.";
                }
            }
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

        <!-- Información General -->
        <div class="section-title">
          <i class="bi bi-info-circle"></i>
          Información General
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Folio OC</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['folio']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Fecha de Solicitud</label>
            <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($orden_compra['fecha_solicitud'])) ?>" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Entidad</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['entidad']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Solicitante</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['nombres'] . ' ' . $orden_compra['apellidos']) ?>" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Categoría</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['categoria']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Proveedor</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['proveedor']) ?>" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Requisición Relacionada</label>
            <input type="text" class="form-control" value="<?= $orden_compra['folio_requisicion'] ? htmlspecialchars($orden_compra['folio_requisicion']) : 'N/A' ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Estado Actual</label>
            <div class="mt-1">
              <?php
              switch ($orden_compra['estado']) {
                case 'pendiente':
                  echo '<span class="badge bg-warning text-dark estado-badge"><i class="bi bi-clock"></i> Pendiente</span>';
                  break;
                case 'revisado':
                  echo '<span class="badge bg-light text-dark estado-badge"><i class="bi bi-check2-circle"></i> revisado</span>';
                  break;
                case 'aprobado':
                  echo '<span class="badge bg-success estado-badge"><i class="bi bi-check-circle"></i> Aprobado</span>';
                  break;
                case 'rechazado':
                  echo '<span class="badge bg-danger estado-badge"><i class="bi bi-x-circle"></i> Rechazado</span>';
                  break;
                case 'comprobante_subido':
                  echo '<span class="badge bg-info estado-badge"><i class="bi bi-file-earmark-check"></i> Comprobante Subido</span>';
                  break;
                case 'devuelto':
                  echo '<span class="badge bg-warning text-dark estado-badge"><i class="bi bi-arrow-counterclockwise"></i> Devuelto para Editar</span>';
                  break;
                case 'pagado':
                  echo '<span class="badge bg-primary estado-badge"><i class="bi bi-currency-dollar"></i> Pagado y Completado</span>';
                  break;
              }
              ?>
            </div>
          </div>
        </div>

        <?php
// Obtener comentario del revisor (Gerente de Operaciones) o aprobador (Subdirector General)
$comentario_decision = '';
if (in_array($orden_compra['estado'], ['revisado', 'aprobado', 'pagado', 'comprobante_subido'])) {
    // Buscar primero si hay un comentario de "revisado" o "aprobado"
    $sql_comentario_decision = "SELECT accion, comentario FROM orden_compra_historial 
                                WHERE orden_compra_id = ? 
                                AND (accion = 'Revisó orden de compra' OR accion = 'Aprobó orden de compra')
                                ORDER BY fecha_cambio DESC LIMIT 1";
    $stmt_comentario_decision = $conn->prepare($sql_comentario_decision);
    $stmt_comentario_decision->bind_param("i", $id);
    $stmt_comentario_decision->execute();
    $result_comentario_decision = $stmt_comentario_decision->get_result();
    
    if ($result_comentario_decision && $result_comentario_decision->num_rows > 0) {
        $row = $result_comentario_decision->fetch_assoc();
        $comentario_decision = $row['comentario'];
        $accion_decision = $row['accion'];
    }
}
?>

<?php if (!empty($comentario_decision)): ?>
<div class="section-title mt-4">
    <i class="bi bi-chat-dots"></i>
    Comentario del <?= $accion_decision === 'Revisó orden de compra' ? 'Revisor' : 'Aprobador' ?>
</div>
<div class="comentario" style="background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; border-radius: 4px; margin-top: 10px;">
    <div class="comentario-header">
        <i class="bi bi-info-circle"></i> 
        <?= $accion_decision === 'Revisó orden de compra' ? 'Comentario del Revisor (Gerente de Operaciones):' : 'Comentario del Aprobador (Subdirector General):' ?>
    </div>
    <p class="mb-0"><?= nl2br(htmlspecialchars($comentario_decision)) ?></p>
</div>
<?php endif; ?>

        <!-- Mostrar comentario de rechazo si está rechazada -->
        <?php if ($orden_compra['estado'] === 'rechazado' && !empty($comentario_rechazo)): ?>
          <div class="section-title mt-4">
            <i class="bi bi-chat-dots"></i>
            Motivo del Rechazo
          </div>
          <div class="comentario-rechazo">
            <div class="comentario-header">
              <i class="bi bi-info-circle"></i> Comentario del Subdirector General:
            </div>
            <p class="mb-0"><?= nl2br(htmlspecialchars($comentario_rechazo)) ?></p>
          </div>
        <?php endif; ?>

        <!-- Ubicación del Presupuesto -->
        <div class="section-title mt-4">
          <i class="bi bi-diagram-3"></i>
          Ubicación del Presupuesto
        </div>

        <div class="ubicacion-box">
          <div class="row">
            <?php if ($orden_compra['nombre_proyecto']): ?>
              <div class="col-md-6 mb-3">
                <label class="form-label"><strong>Proyecto</strong></label>
                <div class="form-control-plaintext"><?= htmlspecialchars($orden_compra['nombre_proyecto']) ?></div>
              </div>
            <?php endif; ?>

            <?php if ($orden_compra['nombre_obra']): ?>
              <div class="col-md-6 mb-3">
                <label class="form-label"><strong>Obra</strong></label>
                <div class="form-control-plaintext"><?= htmlspecialchars($orden_compra['nombre_obra']) ?></div>
              </div>
            <?php endif; ?>

            <?php if ($orden_compra['nombre_catalogo']): ?>
              <div class="col-md-6 mb-3">
                <label class="form-label"><strong>Catálogo</strong></label>
                <div class="form-control-plaintext"><?= htmlspecialchars($orden_compra['nombre_catalogo']) ?></div>
              </div>
            <?php endif; ?>

            <?php if($orden_compra['subcontrato_id']): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label"><strong>Subcontrato</strong></label>
              <div class="form-control-plaintext">
                <?= htmlspecialchars($orden_compra['subcontrato_proveedor_nombre'] ?? 'Subcontrato #' . $orden_compra['subcontrato_id']) ?>
              </div>
            </div>
          <?php endif; ?>

            <?php if ($orden_compra['codigo_concepto'] || $orden_compra['nombre_concepto']): ?>
              <div class="col-md-6 mb-3">
                <label class="form-label"><strong>Concepto</strong></label>
                <div class="form-control-plaintext">
                  <?php
                  if ($orden_compra['codigo_concepto'] && $orden_compra['nombre_concepto']) {
                    echo htmlspecialchars($orden_compra['codigo_concepto'] . ' - ' . $orden_compra['nombre_concepto']);
                  } elseif ($orden_compra['codigo_concepto']) {
                    echo htmlspecialchars($orden_compra['codigo_concepto']);
                  } elseif ($orden_compra['nombre_concepto']) {
                    echo htmlspecialchars($orden_compra['nombre_concepto']);
                  } else {
                    echo 'N/A';
                  }
                  ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Items de la Orden de Compra -->
        <div class="section-title mt-4">
          <i class="bi bi-list-ul"></i> Items de la Orden de Compra
        </div>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>#</th>
              <th>Tipo</th>
              <th>Producto/Servicio</th>
              <th>Cantidad</th>
              <th>Unidad</th>
              <th>Precio Unitario</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $i = 1;
            $total_general = 0;
            while ($item = $items->fetch_assoc()):
              $subtotal = $item['cantidad'] * $item['precio_unitario'];
              $total_general += $subtotal;
            ?>
              <tr>
                <td><?= $i++ ?></td>
                <td>
                  <span class="badge bg-<?= $item['tipo'] === 'producto' ? 'primary' : 'info' ?>">
                    <?= ucfirst(htmlspecialchars($item['tipo'] ?? 'producto')) ?>
                  </span>
                </td>
                <td>
                  <?= !empty($item['producto']) ? htmlspecialchars($item['producto']) : htmlspecialchars($item['descripcion']) ?>
                </td>
                <td><?= htmlspecialchars($item['cantidad']) ?></td>
                <td><?= htmlspecialchars($item['unidad'] ?? 'PZA') ?></td>
                <td>$<?= number_format($item['precio_unitario'], 2) ?></td>
                <td>$<?= number_format($subtotal, 2) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>

        <!-- Totales -->
        <div class="row justify-content-end">
          <div class="col-md-6">
            <div class="totales-box">
              <div class="row mb-2">
                <div class="col-6"><strong>Subtotal:</strong></div>
                <div class="col-6 text-end">$<?= number_format($orden_compra['subtotal'] ?: $total_general, 2) ?></div>
              </div>
              <div class="row mb-2">
                <div class="col-6"><strong>IVA <?php
                                                if ($orden_compra['subtotal'] > 0 && $orden_compra['iva'] > 0) {
                                                  $porcentaje_iva = ($orden_compra['iva'] / $orden_compra['subtotal']) * 100;
                                                  if ($porcentaje_iva >= 15) {
                                                    echo '(16%):';
                                                  } elseif ($porcentaje_iva >= 7) {
                                                    echo '(8%):';
                                                  } else {
                                                    echo '(0%):';
                                                  }
                                                } else {
                                                  echo '(0%):';
                                                }
                                                ?></strong></div>
                <div class="col-6 text-end">$<?= number_format($orden_compra['iva'], 2) ?></div>
              </div>
              <div class="row mb-2 total-final">
                <div class="col-6"><strong>TOTAL:</strong></div>
                <div class="col-6 text-end">$<?= number_format($orden_compra['total'], 2) ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Descripción -->
        <?php if (!empty($orden_compra['descripcion'])): ?>
          <div class="section-title mt-4">
            <i class="bi bi-file-text"></i>
            Descripción
          </div>
          <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($orden_compra['descripcion']) ?></textarea>
        <?php endif; ?>

        <!-- Observaciones -->
        <?php if (!empty($orden_compra['observaciones'])): ?>
          <div class="section-title mt-4">
            <i class="bi bi-chat-text"></i>
            Observaciones
          </div>
          <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($orden_compra['observaciones']) ?></textarea>
        <?php endif; ?>

        <!-- Archivos Adjuntos -->
        <div class="section-title mt-4">
          <i class="bi bi-paperclip"></i>
          Archivos Adjuntos
        </div>

        <?php if ($archivos->num_rows > 0): ?>
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
                      <a href="<?= BASE_URL ?>/orders/download_archivo_oc.php?id=<?= $archivo['id'] ?>"
                        class="btn btn-sm btn-outline-success"
                        title="Descargar archivo">
                        <i class="bi bi-download"></i> Descargar
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No hay archivos adjuntos en esta orden de compra.
          </div>
        <?php endif; ?>

        <!-- Comprobante de Pago -->
        <?php if (!empty($orden_compra['comprobante_pago'])): ?>
          <div class="section-title mt-4">
            <i class="bi bi-receipt"></i>
            Comprobante de Pago
          </div>
          <div class="comprobante-box">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <i class="bi bi-file-earmark-check-fill text-info fs-3 me-2"></i>
                <strong>Comprobante adjunto</strong>
              </div>
              <div>
                <button type="button"
                  class="btn btn-sm btn-info"
                  onclick="verComprobante(<?= $id ?>)"
                  title="Ver comprobante de pago">
                  <i class="bi bi-eye"></i> Ver Comprobante
                </button>
                <a href="<?= BASE_URL ?>/orders/download_comprobante.php?id=<?= $id ?>&download=1"
                  class="btn btn-sm btn-outline-success"
                  title="Descargar comprobante">
                  <i class="bi bi-download"></i> Descargar
                </a>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- SECCIÓN DE ACCIONES PARA Gerente de Operaciones (ESTADO PENDIENTE) -->
        <?php if ($orden_compra['estado'] === 'pendiente' && $departamento === 'Gerente de Operaciones'): ?>
          <div class="section-title mt-4">
            <i class="bi bi-gear"></i>
            Acciones Disponibles - Gerente de Operaciones
          </div>

          <div class="alert alert-info mb-3">
            <i class="bi bi-envelope"></i>
            <strong>Notificación automática:</strong> Al cambiar el estado se notificará al solicitante
            y al Subdirector General.
          </div>

          <form method="POST" class="mb-4">
            <div class="row">
              <div class="col-md-9">
                <label class="form-label">Acción</label>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="button" class="btn btn-info btn-estado" onclick="seleccionarEstado('revisado')">
                    <i class="bi bi-check2-circle"></i> Marcar como Revisado
                  </button>

                  <button type="button" class="btn btn-warning btn-estado" onclick="seleccionarEstado('devuelto')">
                    <i class="bi bi-arrow-counterclockwise"></i> Devolver para Editar
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
                  Si devuelve la orden de compra, este comentario se incluirá en las notificaciones.
                </small>
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-12">
                <button type="submit" name="cambiar_estado" class="btn btn-primary" id="btnConfirmar" disabled>
                  <i class="bi bi-check-lg"></i> Confirmar Decisión
                </button>
              </div>
            </div>
          </form>
        <?php endif; ?>

        <!-- SECCIÓN DE ACCIONES PARA SUBDIRECTOR GENERAL (ESTADO REVISADO) -->
        <?php if ($orden_compra['estado'] === 'revisado' && $departamento === 'Subdirector General'): ?>
          <div class="section-title mt-4">
            <i class="bi bi-gear"></i>
            Acciones Disponibles - Subdirector General
          </div>

          <div class="alert alert-info mb-3">
            <i class="bi bi-envelope"></i>
            <strong>Notificación automática:</strong> Al cambiar el estado se notificará al solicitante
            y al Gerente de Recursos Humanos.
          </div>

          <form method="POST" class="mb-4">
            <div class="row">
              <div class="col-md-9">
                <label class="form-label">Acción</label>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="button" class="btn btn-success btn-estado" onclick="seleccionarEstado('aprobado')">
                    <i class="bi bi-check-circle"></i> Aprobar
                  </button>

                  <button type="button" class="btn btn-danger btn-estado" onclick="seleccionarEstado('rechazado')">
                    <i class="bi bi-x-circle"></i> Rechazar
                  </button>

                  <button type="button" class="btn btn-warning btn-estado" onclick="seleccionarEstado('devuelto')">
                    <i class="bi bi-arrow-counterclockwise"></i> Devolver para Editar
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
                  Si rechaza o devuelve la orden de compra, este comentario se incluirá en las notificaciones.
                </small>
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-12">
                <button type="submit" name="cambiar_estado" class="btn btn-primary" id="btnConfirmar" disabled>
                  <i class="bi bi-check-lg"></i> Confirmar Decisión
                </button>
              </div>
            </div>
          </form>
        <?php endif; ?>

        <!-- SECCIÓN DE COMPROBANTE - Solo Gerente de RRHH cuando está aprobado -->
        <?php if ($orden_compra['estado'] === 'aprobado' && $puede_subir_comprobante): ?>
          <div class="section-title mt-4">
            <i class="bi bi-receipt"></i>
            Subir Comprobante de Pago
          </div>

          <div class="alert alert-warning mb-3">
            <i class="bi bi-info-circle"></i>
            <strong>Instrucciones:</strong> Adjunte el comprobante de pago en formato PDF, JPG o PNG (máximo 10MB).
          </div>

          <form method="POST" enctype="multipart/form-data" class="mb-4">
            <div class="row">
              <div class="col-md-8">
                <label class="form-label">Archivo del Comprobante <span class="text-danger">*</span></label>
                <input type="file"
                  name="comprobante"
                  class="form-control"
                  accept=".pdf,.jpg,.jpeg,.png"
                  required>
                <small class="text-muted">Formatos permitidos: PDF, JPG, PNG | Tamaño máximo: 10MB</small>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" name="subir_comprobante" class="btn btn-primary w-100">
                  <i class="bi bi-upload"></i> Subir Comprobante
                </button>
              </div>
            </div>
          </form>
        <?php endif; ?>

        <!-- SECCIÓN DE MARCAR COMO PAGADO - Solo Gerente de RRHH cuando hay comprobante -->
        <?php if ($orden_compra['estado'] === 'comprobante_subido' && $puede_marcar_pagado): ?>
          <div class="section-title mt-4">
            <i class="bi bi-currency-dollar"></i>
            Marcar como Pagado y Completado
          </div>

          <div class="alert alert-info mb-3">
            <i class="bi bi-envelope"></i>
            <strong>Notificación automática:</strong> Al marcar como pagado, se notificará al solicitante y subdirector.
            <?php if (!empty($orden_compra['nombre_catalogo'])): ?>
              <br><i class="bi bi-diagram-3"></i>
              <strong>Transferencia automática:</strong> Los items se transferirán al catálogo "<?= htmlspecialchars($orden_compra['nombre_catalogo']) ?>".
            <?php endif; ?>
          </div>

          <form method="POST" class="mb-4">
            <input type="hidden" name="nuevo_estado" value="pagado">

            <div class="row">
              <div class="col-md-12">
                <label class="form-label">Comentario (Opcional)</label>
                <textarea class="form-control" name="comentario" rows="3" placeholder="Agregue observaciones sobre el pago realizado..."></textarea>
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-12">
                <button type="submit" name="cambiar_estado" class="btn btn-success btn-lg">
                  <i class="bi bi-check-circle-fill"></i> Confirmar Pago y Completar Proceso
                </button>
              </div>
            </div>
          </form>
        <?php endif; ?>

        <!-- Botones de acción -->
        <div class="form-actions mt-4">
          <?php if ($orden_compra['estado'] == 'devuelto' && $_SESSION['user_id'] == $orden_compra['solicitante_id']): ?>
            <a href="edit_oc.php?id=<?= $orden_compra['id'] ?>" class="btn btn-warning">
              <i class="bi bi-pencil"></i> Editar Orden de Compra
            </a>
            <?php if($orden_compra['subcontrato_id']): ?>
            <small class="text-muted ms-2">
              <i class="bi bi-info-circle"></i> Esta orden está asociada a un subcontrato
            </small>
            <?php endif; ?>
          <?php endif; ?>
        </div>
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
    // Función para seleccionar estado (Aprobar/Rechazar/Devolver)
    function seleccionarEstado(estado) {
      document.getElementById('nuevo_estado').value = estado;
      document.getElementById('btnConfirmar').disabled = false;

      const btnConfirmar = document.getElementById('btnConfirmar');
      if (estado === 'aprobado') {
        btnConfirmar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Aprobación';
        btnConfirmar.className = 'btn btn-success';
      } else if (estado === 'revisado') {
        btnConfirmar.innerHTML = '<i class="bi bi-check2-circle"></i> Confirmar Revisión';
        btnConfirmar.className = 'btn btn-info';
      } else if (estado === 'rechazado') {
        btnConfirmar.innerHTML = '<i class="bi bi-x-lg"></i> Confirmar Rechazo';
        btnConfirmar.className = 'btn btn-danger';
      } else if (estado === 'devuelto') {
        btnConfirmar.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Confirmar Devolución';
        btnConfirmar.className = 'btn btn-warning';
      }
    }

    // Función para ver archivo
    function verArchivo(archivoId, tipoMime) {
      const tiposVisualizables = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

      if (tiposVisualizables.includes(tipoMime)) {
        window.open('/orders/view_archivo_oc.php?id=' + archivoId, '_blank');
      } else {
        alert('Este tipo de archivo no se puede visualizar en el navegador. Se descargará automáticamente.');
        window.open('/orders/download_archivo_oc.php?id=' + archivoId, '_blank');
      }
    }

    // Función para ver comprobante de pago
    function verComprobante(ordenId) {
      window.open('/orders/download_comprobante.php?id=' + ordenId, '_blank');
    }
  </script>

  <script>
    // Mostrar overlay al enviar cualquier formulario de cambio de estado
    document.addEventListener('submit', function(e) {
      const form = e.target;
      if (form.querySelector('[name="cambiar_estado"]') || form.querySelector('[name="subir_comprobante"]')) {
        document.getElementById("loadingOverlay").style.display = "flex";
      }
    });
  </script>

  <?php include __DIR__ . "/../includes/footer.php"; ?>

</body>

</html>