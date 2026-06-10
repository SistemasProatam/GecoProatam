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
    $estados_permitidos = ['pagado', 'devuelto'];
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

          // 3. Obtener Gerente de Operaciones
          $sql_gerente_op = "SELECT u.id, u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo 
                                      FROM usuarios u
                                      JOIN departamentos d ON u.departamento_id = d.id
                                      WHERE d.nombre LIKE '%Gerente de Operaciones%'
                                      AND u.activo = 1
                                      AND u.correo_corporativo IS NOT NULL";
          $result_gerente_op = $conn->query($sql_gerente_op);
          if ($result_gerente_op && $result_gerente_op->num_rows > 0) {
            while ($gerente_op = $result_gerente_op->fetch_assoc()) {
              $destinatarios[] = $gerente_op;
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

          // 3. Obtener Gerente de Operaciones
          $sql_gerente_op = "SELECT u.id, u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo 
                                      FROM usuarios u
                                      JOIN departamentos d ON u.departamento_id = d.id
                                      WHERE d.nombre LIKE '%Gerente de Operaciones%'
                                      AND u.activo = 1
                                      AND u.correo_corporativo IS NOT NULL";
          $result_gerente_op = $conn->query($sql_gerente_op);
          if ($result_gerente_op && $result_gerente_op->num_rows > 0) {
            while ($gerente_op = $result_gerente_op->fetch_assoc()) {
              $destinatarios[] = $gerente_op;
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
// PROCESAR SUBIR COMPROBANTE Y PAGAR (ACCIÓN UNIFICADA)
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_comprobante_y_pagar'])) {
  $departamento = $_SESSION['departamento'] ?? '';

  if ($departamento !== 'Gerente de Recursos Humanos') {
    $mensaje_error = "No tiene permisos para realizar esta acción";
  } else {

    // --- 1. Validar y guardar el archivo ---
    $ruta_comprobante = null;
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
      $extension = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
      $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

      if (!in_array($extension, $extensiones_permitidas)) {
        $mensaje_error = "Formato de archivo no permitido. Use PDF, JPG o PNG.";
      } elseif ($_FILES['comprobante']['size'] > 10 * 1024 * 1024) {
        $mensaje_error = "El archivo excede el tamaño máximo de 10MB.";
      } else {
        $directorio_comprobantes = __DIR__ . '/comprobantes/';
        if (!is_dir($directorio_comprobantes)) {
          mkdir($directorio_comprobantes, 0755, true);
        }
        $nombre_archivo = 'comprobante_' . $id . '_' . time() . '.' . $extension;
        $ruta_destino = $directorio_comprobantes . $nombre_archivo;

        if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta_destino)) {
          $mensaje_error = "Error al guardar el archivo. Verifique los permisos del directorio.";
        } else {
          $ruta_comprobante = 'comprobantes/' . $nombre_archivo;
        }
      }
    } else {
      $mensaje_error = "Debe adjuntar un archivo de comprobante.";
    }

    // --- 2. Si el archivo se guardó bien, actualizar la BD ---
    if (isset($ruta_comprobante) && !isset($mensaje_error)) {
      $comentario = $_POST['comentario'] ?? '';

      $sql_update = "UPDATE ordenes_compra SET estado = 'pagado', comprobante_pago = ?, fecha_actualizacion = NOW() WHERE id = ?";
      $stmt_update = $conn->prepare($sql_update);

      if (!$stmt_update) {
        error_log("Error prepare: " . $conn->error);
        $mensaje_error = "Error interno al preparar la consulta.";
      } else {
        $stmt_update->bind_param("si", $ruta_comprobante, $id);

        if (!$stmt_update->execute()) {
          error_log("Error execute: " . $stmt_update->error);
          $mensaje_error = "Error al actualizar la base de datos: " . $stmt_update->error;
        }
      }

      if ($stmt_update->execute()) {
        // Registrar en historial
        try {
          $accion_texto = 'Subió comprobante y marcó como pagado';
          $sql_historial = "INSERT INTO orden_compra_historial (orden_compra_id, usuario_id, accion, comentario) VALUES (?, ?, ?, ?)";
          $stmt_historial = $conn->prepare($sql_historial);
          $stmt_historial->bind_param("iiss", $id, $_SESSION['user_id'], $accion_texto, $comentario);
          $stmt_historial->execute();
        } catch (Exception $e) {
          error_log("Error en historial: " . $e->getMessage());
        }

        // Actualizar montos disponibles
        $sql_total = "SELECT total FROM ordenes_compra WHERE id = ?";
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->bind_param("i", $id);
        $stmt_total->execute();
        $total_oc = $stmt_total->get_result()->fetch_assoc()['total'];
        actualizarMontosDisponibles($conn, $id, $total_oc);

        // Enviar notificaciones (reutiliza misma lógica que cambiar_estado=pagado)
        // Obtener datos de la orden para el correo
        $sql_datos = "SELECT oc.*, CONCAT(u.nombres, ' ', u.apellidos) as nombre_solicitante,
                      e.nombre as entidad_nombre, c.nombre as categoria_nombre,
                      p.nombre as proveedor_nombre, pro.nombre_proyecto, ob.nombre_obra,
                      cat.nombre_catalogo, con.codigo_concepto, con.nombre_concepto
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

        try {
          $emailHandler = new EmailHandler();
          $datosOrdenCompra = [
            'id'              => $id,
            'folio'           => $oc_data['folio'],
            'estado'          => 'Pagado y Completado',
            'comentarios'     => $comentario,
            'solicitante'     => $oc_data['nombre_solicitante'],
            'entidad'         => $oc_data['entidad_nombre'] ?? 'Sin especificar',
            'categoria'       => $oc_data['categoria_nombre'] ?? 'Sin especificar',
            'proveedor'       => $oc_data['proveedor_nombre'] ?? 'Sin especificar',
            'proyecto'        => $oc_data['nombre_proyecto'] ?? 'Sin especificar',
            'obra'            => $oc_data['nombre_obra'] ?? 'N/A',
            'catalogo'        => $oc_data['nombre_catalogo'] ?? 'N/A',
            'concepto'        => ($oc_data['codigo_concepto'] ?? '') . ($oc_data['nombre_concepto'] ? ' - ' . $oc_data['nombre_concepto'] : 'N/A'),
            'total'           => '$' . number_format($oc_data['total'], 2),
            'fecha_solicitud' => date('d/m/Y H:i', strtotime($oc_data['fecha_solicitud']))
          ];

          $destinatarios = [];
          // Solicitante
          $stmt_s = $conn->prepare("SELECT id, correo_corporativo, CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id = ? AND activo = 1 AND correo_corporativo IS NOT NULL");
          $stmt_s->bind_param("i", $oc_data['solicitante_id']);
          $stmt_s->execute();
          $res_s = $stmt_s->get_result();
          if ($res_s && $res_s->num_rows > 0) {
            $destinatarios[] = $res_s->fetch_assoc();
          }
          // Subdirector
          $res_sub = $conn->query("SELECT u.id, u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo FROM usuarios u JOIN departamentos d ON u.departamento_id = d.id WHERE d.nombre LIKE '%Subdirector General%' AND u.activo = 1 AND u.correo_corporativo IS NOT NULL");
          if ($res_sub) while ($row = $res_sub->fetch_assoc()) $destinatarios[] = $row;

          $correos_enviados = 0;
          foreach ($destinatarios as $destinatario) {
            if ($destinatario['id'] == $oc_data['solicitante_id']) {
              // Al solicitante se usa función específica
              $resultado = $emailHandler->enviarNotificacionSolicitanteOC(
                $destinatario['correo_corporativo'],
                $destinatario['nombre_completo'],
                $datosOrdenCompra
              );
            } else {
              // Al resto (subdirector, etc.) se usa función general
              $resultado = $emailHandler->enviarNotificacionOrdenCompra(
                $destinatario['correo_corporativo'],
                $destinatario['nombre_completo'],
                $datosOrdenCompra
              );
            }

            if ($resultado) {
              $correos_enviados++;
            }
          }
        } catch (Exception $e) {
          error_log("Error enviando correos: " . $e->getMessage());
        }

        header("Location: see_oc.php?id={$id}&success=1&email=" . ($correos_enviados > 0 ? 'enviado' : 'error') . "&comprobante=subido");
        exit;
      } else {
        $mensaje_error = "Error al actualizar la base de datos: " . $stmt_update->error;
      }
    }
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
$sql_archivos = "SELECT id, nombre_archivo, ruta_archivo,tamaño_archivo, tipo_mime, fecha_subida
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

// Verificar permisos del usuario segun su departamento
$departamento = $_SESSION['departamento'] ?? '';
$puede_revisar = ($departamento === 'Gerente de Operaciones');                    // Pendiente -> Revisado
$puede_aprobar_rechazar = ($departamento === 'Subdirector General');              // Revisado -> Aprobado/Rechazado/Devuelto
$puede_subir_comprobante = ($departamento === 'Gerente de Recursos Humanos');    // Aprobado -> Comprobante Subido
$puede_marcar_pagado = ($departamento === 'Gerente de Recursos Humanos');         // Comprobante Subido -> Pagado
?>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
  <title>Detalles de Orden de Compra | GECO PROATAM</title>




  <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->

  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="<?= BASE_URL ?>/orders/list_oc.php">Órdenes de Compra</a>
        <span class="separator">›</span>
        <span>Detalles de Orden de Compra</span>
      </nav>
      <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
        <h1 class="orders-page-title"><?= htmlspecialchars($orden_compra['folio']) ?></h1>
        <?php
        $badge_map = [
          'pendiente'  => ['status-badge--pendiente', 'fa-regular fa-clock',                   'Pendiente'],
          'revisado'   => ['status-badge--revisado',  'fa-regular fa-circle-check',            'Revisado'],
          'aprobado'   => ['status-badge--aprobado',  'fa-solid fa-circle-check',             'Aprobado'],
          'rechazado'  => ['status-badge--rechazado', 'fa-solid fa-circle-xmark',                'Rechazado'],
          'pagado'     => ['status-badge--pagado',    'fa-solid fa-dollar-sign',          'Pagado'],
          'devuelto'   => ['status-badge--devuelto',  'fa-solid fa-rotate-left',  'Devuelto'],
          'comprobante_subido' => ['status-badge--revisado', 'fa-solid fa-file-circle-check', 'Comprobante Subido'],
        ];
        $b = $badge_map[$orden_compra['estado']] ?? ['status-badge--revisado', 'fa-regular fa-circle', ucfirst($orden_compra['estado'])];
        echo '<span class="status-badge ' . $b[0] . '"><i class="' . $b[1] . '"></i> ' . $b[2] . '</span>';
        ?>
      </div>
    </div>
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-start;">
      <?php if (($orden_compra['estado'] == 'devuelto' && $_SESSION['user_id'] == $orden_compra['solicitante_id']) || (($_SESSION['departamento'] ?? '') === 'Tecnico de Sistemas')): ?>
        <a href="edit_oc.php?id=<?= $orden_compra['id'] ?>" class="btn-geco-secondary">
          <i class="fa-solid fa-pen-to-square"></i> Editar
        </a>
      <?php endif; ?>
      <?php if (in_array($orden_compra['estado'], ['devuelto', 'pagado'])): ?>
        <a href="<?= BASE_URL ?>/orders/download_pdf_oc.php?id=<?= $orden_compra['id'] ?>" class="btn-geco-outline" target="_blank">
          <i class="fa-solid fa-download"></i> PDF
        </a>
      <?php endif; ?>
      <a href="list_oc.php" class="btn-geco-outline">
        <i class="fa-solid fa-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <!-- ─── ALERTS ────────────────────────────────────────────────── -->
  <?php if (isset($_GET['success'])): ?>
    <?php
    $email_status      = $_GET['email'] ?? '';
    $comprobante_status = $_GET['comprobante'] ?? '';
    $transferidos      = $_GET['transferidos'] ?? 0;
    $msg_class = 'orders-alert--success';
    $msg_icon  = 'fa-solid fa-circle-check';
    $msg_text  = 'Estado actualizado correctamente';
    if ($comprobante_status === 'subido') {
      $msg_text = 'Comprobante de pago subido exitosamente';
    } elseif ($email_status === 'enviado') {
      $count = $_GET['count'] ?? 0;
      $msg_text .= " y se enviaron {$count} notificaciones por correo.";
    } elseif ($email_status === 'error') {
      $msg_class = 'orders-alert--warning';
      $msg_icon  = 'fa-solid fa-circle-exclamation';
      $msg_text .= ', pero hubo un problema al enviar las notificaciones.';
    }
    ?>
    <div class="orders-alert <?= $msg_class ?> alert-dismissible fade show" role="alert">
      <i class="<?= $msg_icon ?>"></i>
      <span><?= $msg_text ?></span>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($mensaje_error)): ?>
    <div class="orders-alert" style="background:rgba(232,68,90,.06);border-color:rgba(232,68,90,.3);color:#b91c1c;">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span><?= $mensaje_error ?></span>
    </div>
  <?php endif; ?>

  <!-- ─── 2-COLUMN DETAIL GRID ──────────────────────────────────── -->
  <div class="oc-detail-grid">

    <!-- LEFT COLUMN -->
    <div>

      <!-- ─── INFORMACIÓN GENERAL ───────────────────────────────── -->
      <div class="oc-card">
        <div class="oc-card-header">
          <span class="oc-card-header__title"><i class="fa-solid fa-circle-info"></i> Información General</span>
        </div>
        <div class="oc-card-body">
          <div class="oc-fields">
            <div class="oc-field">
              <label>Folio OC</label>
              <div class="val"><?= htmlspecialchars($orden_compra['folio']) ?></div>
            </div>
            <div class="oc-field">
              <label>Fecha de Solicitud</label>
              <div class="val"><?= date('d/m/Y H:i', strtotime($orden_compra['fecha_solicitud'])) ?></div>
            </div>
            <div class="oc-field">
              <label>Solicitante</label>
              <div class="val"><?= htmlspecialchars($orden_compra['nombres'] . ' ' . $orden_compra['apellidos']) ?></div>
            </div>
            <div class="oc-field">
              <label>Entidad</label>
              <div class="val"><?= htmlspecialchars($orden_compra['entidad']) ?></div>
            </div>
            <div class="oc-field">
              <label>Categoría</label>
              <div class="val"><?= htmlspecialchars($orden_compra['categoria']) ?></div>
            </div>
            <div class="oc-field">
              <label>Proveedor</label>
              <div class="val"><?= htmlspecialchars($orden_compra['proveedor']) ?></div>
            </div>
            <div class="oc-field">
              <label>Requisición Relacionada</label>
              <div class="val"><?= $orden_compra['folio_requisicion'] ? htmlspecialchars($orden_compra['folio_requisicion']) : 'N/A' ?></div>
            </div>
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
        <div class="oc-card" style="border-left: 3px solid var(--s-700, #113557);">
          <div class="oc-card-header"><span class="oc-card-header__title"><i class="fa-regular fa-comment-dots"></i>
              Comentario del <?= $accion_decision === 'Revisó orden de compra' ? 'Revisor' : 'Aprobador' ?></span>
          </div>
          <div class="oc-card-body">
            <p style="font-size:.82rem;color:var(--gray-600,#4b5563);margin:0;font-style:italic;">
              "<?= nl2br(htmlspecialchars($comentario_decision)) ?>"
            </p>
            <small style="font-size:.7rem;color:var(--gray-400,#9ca3af);margin-top:.4rem;display:block;">
              — <?= $accion_decision === 'Revisó orden de compra' ? 'Gerente de Operaciones' : 'Subdirector General' ?>
            </small>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($orden_compra['estado'] === 'rechazado' && !empty($comentario_rechazo)): ?>
        <div class="oc-card" style="border-left: 3px solid #e8445a;">
          <div class="oc-card-header" style="color:#b91c1c;"><span class="oc-card-header__title"><i class="fa-solid fa-circle-xmark"></i> Motivo del Rechazo</span></div>
          <div class="oc-card-body">
            <p style="font-size:.82rem;color:#7f1d1d;margin:0;font-style:italic;">
              "<?= nl2br(htmlspecialchars($comentario_rechazo)) ?>"
            </p>
            <small style="font-size:.7rem;color:var(--gray-400,#9ca3af);margin-top:.4rem;display:block;">— Subdirector General</small>
          </div>
        </div>
      <?php endif; ?>

      <!-- ─── ITEMS DE LA ORDEN ───────────────────────────────── -->
      <div class="oc-card">
        <div class="oc-card-header"><span class="oc-card-header__title"><i class="fa-solid fa-list"></i> Items de la Orden</span></div>
        <div class="orders-table-wrap">


          <table class="orders-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Tipo</th>
                <th>Producto / Servicio</th>
                <th>Cant.</th>
                <th>Unidad</th>
                <th>P. Unitario</th>
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
                  <td class="cell-muted"><?= $i++ ?></td>
                  <td>
                    <span class="type-badge <?= ($item['tipo'] === 'producto') ? 'producto' : 'servicio' ?>">
                      <?= ucfirst(htmlspecialchars($item['tipo'] ?? 'producto')) ?>
                    </span>
                  </td>
                  <td><?= !empty($item['producto']) ? htmlspecialchars($item['producto']) : htmlspecialchars($item['descripcion']) ?></td>
                  <td><?= htmlspecialchars($item['cantidad']) ?></td>
                  <td class="cell-muted"><?= htmlspecialchars($item['unidad'] ?? 'PZA') ?></td>
                  <td>$<?= number_format($item['precio_unitario'], 2) ?></td>
                  <td class="cell-folio">$<?= number_format($subtotal, 2) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div><!-- /.orders-table-wrap -->
      </div><!-- /.oc-card items -->

      <?php if (!empty($orden_compra['descripcion'])): ?>
        <div class="oc-card">
          <div class="oc-card-header"><span class="oc-card-header__title"><i class="fa-regular fa-file-lines"></i> Descripción</span></div>
          <div class="oc-card-body">
            <p style="font-size:.85rem;color:var(--gray-700,#374151);margin:0;white-space:pre-wrap;"><?= htmlspecialchars($orden_compra['descripcion']) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($orden_compra['observaciones'])): ?>
        <div class="oc-card">
          <div class="oc-card-header"><span class="oc-card-header__title"><i class="fa-regular fa-comments"></i> Observaciones</span></div>
          <div class="oc-card-body">
            <p style="font-size:.85rem;color:var(--gray-700,#374151);margin:0;white-space:pre-wrap;"><?= htmlspecialchars($orden_compra['observaciones']) ?></p>
          </div>
        </div>
      <?php endif; ?>

    </div><!-- END LEFT COLUMN -->

    <!-- ─── RIGHT COLUMN ──────────────────────────────────────── -->
    <div>

      <!-- Finance summary -->
      <div class="oc-finance">
        <div class="oc-finance-title"><i class="fa-solid fa-calculator"></i> Resumen Financiero</div>
        <div class="oc-finance-row">
          <span>Subtotal</span>
          <span>$<?= number_format($orden_compra['subtotal'] ?: $total_general, 2) ?></span>
        </div>
        <div class="oc-finance-row">
          <span>IVA <?php
                    if ($orden_compra['subtotal'] > 0 && $orden_compra['iva'] > 0) {
                      $pct = ($orden_compra['iva'] / $orden_compra['subtotal']) * 100;
                      echo $pct >= 15 ? '(16%)' : ($pct >= 7 ? '(8%)' : '(0%)');
                    } else {
                      echo '(0%)';
                    }
                    ?></span>
          <span>$<?= number_format($orden_compra['iva'], 2) ?></span>
        </div>
        <div class="oc-finance-total">
          <span class="lbl">Total</span>
          <span class="amt">$<?= number_format($orden_compra['total'], 2) ?></span>
        </div>
      </div>

      <!-- Ubicación Presupuesto -->
      <div class="oc-side-card">
        <div class="oc-side-header"><i class="fa-solid fa-sitemap"></i> Ubicación Presupuesto</div>
        <div class="oc-side-body">
          <?php if ($orden_compra['nombre_proyecto']): ?>
            <div class="oc-side-field">
              <label>Proyecto</label>
              <div class="val"><?= htmlspecialchars($orden_compra['nombre_proyecto']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($orden_compra['nombre_obra']): ?>
            <div class="oc-side-field">
              <label>Obra</label>
              <div class="val"><?= htmlspecialchars($orden_compra['nombre_obra']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($orden_compra['nombre_catalogo']): ?>
            <div class="oc-side-field">
              <label>Catálogo</label>
              <div class="val"><?= htmlspecialchars($orden_compra['nombre_catalogo']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($orden_compra['subcontrato_id']): ?>
            <div class="oc-side-field">
              <label>Subcontrato Asociado</label>
              <div class="val"><?= htmlspecialchars($orden_compra['subcontrato_proveedor_nombre'] ?? 'Subcontrato #' . $orden_compra['subcontrato_id']) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($orden_compra['codigo_concepto'] || $orden_compra['nombre_concepto']): ?>
            <div class="oc-side-field">
              <label>Concepto</label>
              <div class="val"><?php
                                if ($orden_compra['codigo_concepto'] && $orden_compra['nombre_concepto'])
                                  echo htmlspecialchars($orden_compra['codigo_concepto'] . ' - ' . $orden_compra['nombre_concepto']);
                                elseif ($orden_compra['codigo_concepto'])
                                  echo htmlspecialchars($orden_compra['codigo_concepto']);
                                elseif ($orden_compra['nombre_concepto'])
                                  echo htmlspecialchars($orden_compra['nombre_concepto']);
                                else echo 'N/A';
                                ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Archivos Adjuntos sidebar card -->
      <div class="oc-side-card">
        <div class="oc-side-header"><i class="fa-solid fa-paperclip"></i> Archivos Adjuntos</div>
        <div class="oc-side-body" style="padding:0;">
          <?php if ($archivos->num_rows > 0): ?>
            <?php while ($archivo = $archivos->fetch_assoc()):
              $ext = strtolower(pathinfo($archivo['nombre_archivo'], PATHINFO_EXTENSION));
              $ficon = match (true) {
                in_array($ext, ['pdf'])           => 'fa-regular fa-file-pdf',
                in_array($ext, ['doc', 'docx'])    => 'fa-regular fa-file-word',
                in_array($ext, ['xls', 'xlsx'])    => 'fa-regular fa-file-excel',
                in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) => 'fa-regular fa-file-image',
                in_array($ext, ['zip', 'rar'])     => 'fa-regular fa-file-zipper',
                default                          => 'fa-regular fa-file'
              };
            ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 1rem;border-bottom:1px solid var(--gray-100,#f3f4f6);">
                <div style="display:flex;align-items:center;gap:.5rem;min-width:0;">
                  <i class="<?= $ficon ?>" style="font-size:1rem;color:var(--gray-500,#6b7280);flex-shrink:0;"></i>
                  <span style="font-size:.75rem;font-weight:600;color:var(--s-800,#0f172a);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($archivo['nombre_archivo']) ?></span>
                </div>
                <div style="display:flex;gap:.35rem;flex-shrink:0;">
                  <button type="button" class="btn-action" onclick="verArchivo(<?= $archivo['id'] ?>, '<?= htmlspecialchars($archivo['tipo_mime']) ?>')" title="Ver">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                  <a href="<?= BASE_URL ?>/orders/download_archivo_oc.php?id=<?= $archivo['id'] ?>" class="btn-action" title="Descargar">
                    <i class="fa-solid fa-download"></i>
                  </a>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="oc-empty" style="padding:1.5rem;">
              <i class="fa-solid fa-inbox"></i>
              <p>No hay archivos adjuntos</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Comprobante de Pago -->
      <?php if (!empty($orden_compra['comprobante_pago'])): ?>
        <div class="oc-side-card" style="border-left:3px solid #6d28d9;">
          <div class="oc-side-header" style="color:#6d28d9;"><i class="fa-solid fa-receipt"></i> Comprobante de Pago</div>
          <div class="oc-side-body">
            <div style="display:flex;gap:.5rem;">
              <button type="button" class="btn-geco-primary" style="font-size:.78rem;padding:.4rem .8rem;" onclick="verComprobante(<?= $id ?>)">
                <i class="fa-regular fa-eye"></i> Ver
              </button>
              <a href="<?= BASE_URL ?>/orders/download_comprobante.php?id=<?= $id ?>&download=1" class="btn-geco-outline" style="font-size:.78rem;padding:.4rem .8rem;">
                <i class="fa-solid fa-download"></i> Descargar
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div><!-- END RIGHT COLUMN -->
  </div><!-- END .oc-detail-grid -->




  <!-- ─── SECCIÓN ACCIONES FLUJO DE ESTADO ─────────────────────────── -->




  <!-- ─── ACCIONES: Gerente de Operaciones (Pendiente) ─────────── -->
  <?php if ($orden_compra['estado'] === 'pendiente' && $departamento === 'Gerente de Operaciones'): ?>
    <div class="oc-card" style="border-left:3px solid #3b82f6;">
      <div class="oc-card-header" style="color:#1d4ed8;"><span class="oc-card-header__title"><i class="fa-solid fa-gears"></i> Acciones — Gerente de Operaciones</span></div>
      <div class="oc-card-body">
        <p style="font-size:.8rem;color:var(--gray-500,#6b7280);margin:0 0 1rem;"><i class="fa-solid fa-envelope"></i> <strong>Notificación automática:</strong> Al cambiar el estado se notificará al solicitante y al Subdirector General.</p>
        <form method="POST">
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
            <button type="button" class="btn-geco-primary" onclick="seleccionarEstado('revisado')" style="background:#1d4ed8;"><i class="fa-regular fa-circle-check"></i> Marcar como Revisado</button>
            <button type="button" class="btn-geco-secondary" onclick="seleccionarEstado('devuelto')"><i class="fa-solid fa-rotate-left"></i> Devolver para Editar</button>
          </div>
          <input type="hidden" name="nuevo_estado" id="nuevo_estado" required>
          <label style="font-size:.78rem;font-weight:600;color:var(--gray-500,#6b7280);text-transform:uppercase;letter-spacing:.05em;">Comentario (Opcional)</label>
          <textarea class="form-control mt-1 mb-2" name="comentario" rows="2" placeholder="Comentario sobre la decisión..."></textarea>
          <button type="submit" name="cambiar_estado" class="btn-geco-primary" id="btnConfirmar" disabled><i class="fa-solid fa-check"></i> Confirmar Decisión</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- ─── ACCIONES: Subdirector General (Revisado) ──────────────── -->
  <?php if ($orden_compra['estado'] === 'revisado' && $departamento === 'Subdirector General'): ?>
    <div class="oc-card" style="border-left:3px solid #407656;">
      <div class="oc-card-header" style="color:#407656;"><span class="oc-card-header__title"><i class="fa-solid fa-gears"></i> Acciones — Subdirector General</span></div>
      <div class="oc-card-body">
        <p style="font-size:.8rem;color:var(--gray-500,#6b7280);margin:0 0 1rem;"><i class="fa-solid fa-envelope"></i> <strong>Notificación automática:</strong> Al cambiar el estado se notificará al solicitante y al Gerente de Recursos Humanos.</p>
        <form method="POST">
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
            <button type="button" class="btn-geco-primary" onclick="seleccionarEstado('aprobado')"><i class="fa-solid fa-circle-check"></i> Aprobar</button>
            <button type="button" class="btn-geco-secondary" onclick="seleccionarEstado('rechazado')" style="background:#e8445a;color:#fff;border-color:transparent;"><i class="fa-solid fa-circle-xmark"></i> Rechazar</button>
            <button type="button" class="btn-geco-secondary" onclick="seleccionarEstado('devuelto')"><i class="fa-solid fa-rotate-left"></i> Devolver para Editar</button>
          </div>
          <input type="hidden" name="nuevo_estado" id="nuevo_estado" required>
          <label style="font-size:.78rem;font-weight:600;color:var(--gray-500,#6b7280);text-transform:uppercase;letter-spacing:.05em;">Comentario (Opcional)</label>
          <textarea class="form-control mt-1 mb-2" name="comentario" rows="2" placeholder="Comentario sobre la decisión..."></textarea>
          <button type="submit" name="cambiar_estado" class="btn-geco-primary" id="btnConfirmar" disabled><i class="fa-solid fa-check"></i> Confirmar Decisión</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- ─── COMPROBANTE: Gerente RRHH (Aprobado) ─────────────────── -->
  <?php if ($orden_compra['estado'] === 'aprobado' && $puede_subir_comprobante): ?>
    <div class="oc-card" style="border-left:3px solid #6d28d9;">
      <div class="oc-card-header" style="color:#6d28d9;"><span class="oc-card-header__title"><i class="fa-solid fa-receipt"></i> Subir Comprobante de Pago</span></div>
      <div class="oc-card-body">
        <p style="font-size:.8rem;color:var(--gray-500,#6b7280);margin:0 0 1rem;"><i class="fa-solid fa-circle-info"></i> Adjunte el comprobante (PDF, JPG, PNG — máx. 10MB). Al subir, la orden quedará marcada como <strong>Pagada</strong>.</p>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="nuevo_estado" value="pagado">
          <label style="font-size:.78rem;font-weight:600;color:var(--gray-500,#6b7280);text-transform:uppercase;letter-spacing:.05em;">Archivo del Comprobante <span style="color:#e8445a;">*</span></label>
          <input type="file" name="comprobante" class="form-control mt-1 mb-1" accept=".pdf,.jpg,.jpeg,.png" required>
          <small class="text-muted">PDF, JPG, PNG | Máx. 10MB</small>
          <label style="font-size:.78rem;font-weight:600;color:var(--gray-500,#6b7280);text-transform:uppercase;letter-spacing:.05em;display:block;margin-top:1rem;">Comentario (Opcional)</label>
          <textarea class="form-control mt-1 mb-2" name="comentario" rows="2" placeholder="Observaciones sobre el pago..."></textarea>
          <button type="submit" name="subir_comprobante_y_pagar" class="btn-geco-primary"><i class="fa-solid fa-circle-check"></i> Subir Comprobante y Confirmar Pago</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- ─── DEVOLVER (RRHH/Sistemas en estados posteriores) ───────── -->
  <?php
  $es_sistemas = (($_SESSION['departamento'] ?? '') === 'Tecnico de Sistemas');
  $puede_devolver = $puede_subir_comprobante || $es_sistemas;
  $estados_devolver = ['aprobado', 'comprobante_subido', 'pagado'];
  $estado_permite_devolver = in_array($orden_compra['estado'], $estados_devolver);
  if ($estado_permite_devolver && $puede_devolver): ?>
    <div class="oc-card">
      <div class="oc-card-header" style="color:#92400e;"><span class="oc-card-header__title"><i class="fa-solid fa-rotate-left"></i> Devolver Orden</span></div>
      <div class="oc-card-body">
        <form method="POST" class="d-inline" onsubmit="handleDevolver(event)">
          <input type="hidden" name="nuevo_estado" value="devuelto">
          <textarea name="comentario" class="d-none" id="comentario_devolver"></textarea>
          <button type="submit" name="cambiar_estado" class="btn-geco-secondary"><i class="fa-solid fa-rotate-left"></i> Devolver para Editar</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Loading overlay -->
  <div id="loadingOverlay">
    <div class="loading-box">
      <div class="spinner-border text-primary" role="status"></div>
      <div class="mt-3">Procesando, por favor espere...</div>
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
        btnConfirmar.innerHTML = '<i class="fa-solid fa-check"></i> Confirmar Aprobación';
        btnConfirmar.className = 'btn-geco-primary';
      } else if (estado === 'revisado') {
        btnConfirmar.innerHTML = '<i class="fa-regular fa-circle-check"></i> Confirmar Revisión';
        btnConfirmar.className = 'btn-geco-primary';
      } else if (estado === 'rechazado') {
        btnConfirmar.innerHTML = '<i class="fa-solid fa-xmark"></i> Confirmar Rechazo';
        btnConfirmar.className = 'btn-geco-danger';
      } else if (estado === 'devuelto') {
        btnConfirmar.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Confirmar Devolución';
        btnConfirmar.className = 'btn-geco-secondary';
      } else if (estado === 'pagado') {
        btnConfirmar.innerHTML = '<i class="fa-solid fa-check"></i> Confirmar Pago';
        btnConfirmar.className = 'btn-geco-primary';
      }
    }

    // Función para manejar la devolución con confirmación
    function handleDevolver(e) {
      e.preventDefault();
      const form = e.target;
      UI.confirm({
        title: '¿Devolver Orden?',
        message: '¿Está seguro de devolver esta orden al solicitante para que la edite? El estado volverá a devuelto.',
        danger: true
      }).then(confirmed => {
        if (confirmed) {
          document.getElementById("loadingOverlay").style.display = "flex";
          form.submit();
        }
      });
    }

    // Función para ver archivo
    function verArchivo(archivoId, tipoMime) {
      const tiposVisualizables = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

      if (tiposVisualizables.includes(tipoMime)) {
        window.open('/orders/view_archivo_oc.php?id=' + archivoId, '_blank');
      } else {
        UI.toast.info('Este tipo de archivo no se puede visualizar en el navegador. Se descargará automáticamente.');
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
      if (form.querySelector('[name="cambiar_estado"]') || form.querySelector('[name="subir_comprobante_y_pagar"]')) {
        document.getElementById("loadingOverlay").style.display = "flex";
      }
    });
  </script>


</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>