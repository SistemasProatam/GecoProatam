<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . '/../EmailHandler.php';

// FunciÃ³n para limpiar valores monetarios
function limpiarValorMonetario($valor) {
    $valor = str_replace(',', '.', $valor);
    $valor = preg_replace('/[^\d.]/', '', $valor);
    $valor = floatval($valor);
    // Redondear a dos decimales
    return round($valor, 2);
}

// FunciÃ³n para enviar respuestas JSON
function sendJsonResponse($success, $message, $redirect = null)
{
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect
    ]);
    exit;
}

// FunciÃ³n para generar nuevo folio cuando hay duplicado
function generarNuevoFolio($conn, $entidad_id, $folio_original)
{
    $sql_entidad = "SELECT prefijo FROM entidades WHERE id = ?";
    $stmt_entidad = $conn->prepare($sql_entidad);
    $stmt_entidad->bind_param("i", $entidad_id);
    $stmt_entidad->execute();
    $result_entidad = $stmt_entidad->get_result();

    if ($result_entidad->num_rows === 0) {
        return $folio_original . '-DUP';
    }

    $entidad = $result_entidad->fetch_assoc();
    $prefijo = $entidad['prefijo'];
    $anio_actual = date('Y');

    $sql_ultimo = "SELECT folio FROM ordenes_compra 
                   WHERE folio LIKE ? 
                   ORDER BY CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED) DESC 
                   LIMIT 1";
    $like_pattern = "OC-{$prefijo}-{$anio_actual}-%";
    $stmt_ultimo = $conn->prepare($sql_ultimo);
    $stmt_ultimo->bind_param("s", $like_pattern);
    $stmt_ultimo->execute();
    $result_ultimo = $stmt_ultimo->get_result();

    $numero = 1;
    if ($result_ultimo->num_rows > 0) {
        $ultimo = $result_ultimo->fetch_assoc();
        if (preg_match('/OC-' . preg_quote($prefijo, '/') , $ultimo['folio'], $matches)) {
            $numero = intval($matches[2]) + 1;
        }
    }

    return sprintf("OC-%s-%s-%04d", $prefijo, $numero);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();

    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, "Usuario no autenticado.");
    }

    // Recoger datos del formulario 
    $requisicion_id = $_POST['requisicion_id'] ?? null;
    $folio          = $_POST['numero_orden'] ?? null;
    $entidad_id     = $_POST['entidad'] ?? null;
    $categoria_id   = $_POST['categoria_id'] ?? $_POST['categoria'] ?? null;
    $proyecto_id    = $_POST['proyecto_id'] ?? $_POST['proyecto'] ?? null;
    $subcontrato_id = !empty($_POST['subcontrato']) ? $_POST['subcontrato'] : null;
    $obra_id        = !empty($_POST['obra']) ? $_POST['obra'] : null;
    $catalogo_id    = !empty($_POST['catalogo']) ? $_POST['catalogo'] : null;
    $concepto_id    = null; // Para la orden principal, no usamos concepto_id
    $proveedor_id = $_POST['proveedor'] ?? $_POST['proveedor_from_subcontrato'] ?? null;
    $solicitante_id = $_SESSION['user_id'];
    $descripcion    = $_POST['descripcion_general'] ?? '';
    $observaciones  = $_POST['observaciones'] ?? '';

    // Valores monetarios
    $subtotal       = limpiarValorMonetario($_POST['subtotal'] ?? 0);
    $iva_porcentaje = limpiarValorMonetario($_POST['iva'] ?? 0);
    $iva_monto      = $subtotal * ($iva_porcentaje / 100);
    $total          = limpiarValorMonetario($_POST['total'] ?? 0);

    $fecha_solicitud = $_POST['fecha_solicitud'] ?? date('Y-m-d H:i:s');

    // Datos para items
    $descripciones  = $_POST['descripcion'] ?? [];
    $cantidades     = $_POST['cantidad'] ?? [];
    $unidades_ids   = $_POST['unidad_id'] ?? [];
    $precios_unit   = $_POST['precio_unitario'] ?? [];
    $productos_ids  = $_POST['producto_id'] ?? [];
    $tipos          = $_POST['tipo'] ?? [];
    $conceptos_ids  = $_POST['concepto_id'] ?? []; // Array de conceptos por item

    $archivos_eliminados = $_POST['archivos_eliminados'] ?? [];

    // ================================
    // VALIDACIONES
    // ================================
    $campos_faltantes = [];

    if (!$folio) $campos_faltantes[] = 'folio (nÃºmero de orden)';
    if (!$entidad_id) $campos_faltantes[] = 'entidad';
    if (!$proveedor_id) $campos_faltantes[] = 'proveedor';
    if (!$proyecto_id) $campos_faltantes[] = 'proyecto';
    if (!$categoria_id) $campos_faltantes[] = 'categorÃ­a';

    // Si la categorÃ­a es Destajo o Subcontrato, validar que tenga subcontrato
        $esCategoriaSubcontrato = ($categoria_id == '2' || $categoria_id == '5');
    if ($esCategoriaSubcontrato && !$subcontrato_id) {
        $campos_faltantes[] = 'subcontrato (obligatorio para categorÃ­a Destajo/Subcontrato)';
    }

    if (!empty($campos_faltantes)) {
        sendJsonResponse(false, "Faltan datos obligatorios: " . implode(', ', $campos_faltantes));
    }

    // Validar que la requisiciÃ³n existe si se proporciona
    if ($requisicion_id && !empty($requisicion_id)) {
        $sql_check_requisicion = "SELECT id FROM requisiciones WHERE id = ?";
        $stmt_check = $conn->prepare($sql_check_requisicion);
        $stmt_check->bind_param("i", $requisicion_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 0) {
            $requisicion_id = null;
        }
    } else {
        $requisicion_id = null;
    }

    // ================================
    // VERIFICAR SI EL FOLIO YA EXISTE
    // ================================
    $sql_check_folio = "SELECT id FROM ordenes_compra WHERE folio = ?";
    $stmt_check_folio = $conn->prepare($sql_check_folio);
    $stmt_check_folio->bind_param("s", $folio);
    $stmt_check_folio->execute();
    $result_check_folio = $stmt_check_folio->get_result();

    if ($result_check_folio->num_rows > 0) {
        $folio = generarNuevoFolio($conn, $entidad_id, $folio);
    }
    $stmt_check_folio->close();

    // ================================
    // INICIAR TRANSACCIÃ“N
    // ================================
    $conn->begin_transaction();

    try {
        // ================================
        // Insertar orden de compra (SIN concepto_id)
        // ================================
        $sql = "INSERT INTO ordenes_compra 
                    (folio, requisicion_id, entidad_id, categoria_id, proyecto_id, obra_id, catalogo_id, subcontrato_id, proveedor_id, 
                    solicitante_id, descripcion, observaciones, subtotal, iva, total, estado, fecha_solicitud) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error en la preparaciÃ³n: " . $conn->error);
        }

        // Asegurar valores nulos para campos opcionales
        $obra_id_sp = $obra_id; 
        $obra_id = empty($obra_id) ? null : $obra_id;
        $catalogo_id = empty($catalogo_id) ? null : $catalogo_id;
        $subcontrato_id = empty($subcontrato_id) ? null : $subcontrato_id;

        $stmt->bind_param(
            "siiiiiiiiisssdds",
            $folio,
            $requisicion_id,
            $entidad_id,
            $categoria_id,
            $proyecto_id,
            $obra_id,
            $catalogo_id,
            $subcontrato_id,
            $proveedor_id,
            $solicitante_id,
            $descripcion,
            $observaciones,
            $subtotal,
            $iva_monto,
            $total,
            $fecha_solicitud
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al guardar la orden de compra: " . $stmt->error);
        }

        $orden_compra_id = $stmt->insert_id;
        $stmt->close();

        // ================================
        // Insertar items de la orden (CON concepto_id por item)
        // ================================
        if (!empty($descripciones)) {
            $sql_item = "INSERT INTO orden_compra_items 
                            (orden_compra_id, producto_id, tipo, descripcion, cantidad, unidad_id, concepto_id, precio_unitario, subtotal) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            if (!$stmt_item) {
                throw new Exception("Error preparaciÃ³n items: " . $conn->error);
            }

            for ($i = 0; $i < count($descripciones); $i++) {
                if (!empty($descripciones[$i])) {
                    $producto_id = !empty($productos_ids[$i]) ? $productos_ids[$i] : null;
                    $tipo = $tipos[$i] ?? '';
                    $descripcion_item = $descripciones[$i] ?? '';
                    $cantidad = limpiarValorMonetario($cantidades[$i] ?? 1);
                    $unidad_id = !empty($unidades_ids[$i]) ? $unidades_ids[$i] : null;

                    // Obtener concepto_id para este item especÃ­fico
                    $concepto_item_id = !empty($conceptos_ids[$i]) ? $conceptos_ids[$i] : null;

                    $precio_unitario = limpiarValorMonetario($precios_unit[$i] ?? 0);
                    $subtotal_item = $cantidad * $precio_unitario;

                    $stmt_item->bind_param(
                        "iissdiidd",
                        $orden_compra_id,
                        $producto_id,
                        $tipo,
                        $descripcion_item,
                        $cantidad,
                        $unidad_id,
                        $concepto_item_id,
                        $precio_unitario,
                        $subtotal_item
                    );

                    if (!$stmt_item->execute()) {
                        error_log("Error insertando item: " . $stmt_item->error);
                    }
                }
            }
            $stmt_item->close();
        }

        // ================================
        // ACTUALIZAR PRESUPUESTO DEL SUBCONTRATO (si aplica)
        // ================================
        if ($esCategoriaSubcontrato && $subcontrato_id) {
            // Recalcular el monto real del subcontrato basado en las Ã³rdenes pagadas
            $sql_recalcular = "CALL sp_recalcular_monto_real(?, ?)";
            $stmt_recalc = $conn->prepare($sql_recalcular);
            $stmt_recalc->bind_param("ii", $obra_id_sp, $subcontrato_id);
            $stmt_recalc->execute();
            $stmt_recalc->close();
        }

        // ================================
        // MANEJO DE ARCHIVOS
        // ================================

        // 1. Copiar archivos de la requisiciÃ³n (excepto los eliminados)
        if ($requisicion_id) {
            $sql_archivos_requisicion = "SELECT id, nombre_archivo, ruta_archivo, tamaÃ±o_archivo, tipo_mime 
                                        FROM requisicion_archivos 
                                        WHERE requisicion_id = ?";

            if (!empty($archivos_eliminados)) {
                $placeholders = implode(',', array_fill(0, count($archivos_eliminados), '?'));
                $sql_archivos_requisicion .= " AND id NOT IN ($placeholders)";
            }

            $stmt_archivos = $conn->prepare($sql_archivos_requisicion);

            if (!empty($archivos_eliminados)) {
                $types = 'i' . str_repeat('i', count($archivos_eliminados));
                $params = array_merge([$requisicion_id], $archivos_eliminados);
                $stmt_archivos->bind_param($types, ...$params);
            } else {
                $stmt_archivos->bind_param("i", $requisicion_id);
            }

            $stmt_archivos->execute();
            $archivos = $stmt_archivos->get_result();

            if ($archivos->num_rows > 0) {
                $sql_insert_archivo = "INSERT INTO orden_compra_archivos 
                                          (orden_compra_id, requisicion_archivo_id, nombre_archivo, ruta_archivo, tamaÃ±o_archivo, tipo_mime)
                                       VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert_archivo);

                while ($archivo = $archivos->fetch_assoc()) {
                    $stmt_insert->bind_param(
                        "iissis",
                        $orden_compra_id,
                        $archivo['id'],
                        $archivo['nombre_archivo'],
                        $archivo['ruta_archivo'],
                        $archivo['tamaÃ±o_archivo'],
                        $archivo['tipo_mime']
                    );
                    if (!$stmt_insert->execute()) {
                        error_log("Error copiando archivo de requisiciÃ³n: " . $stmt_insert->error);
                    }
                }
                $stmt_insert->close();
            }
            $stmt_archivos->close();
        }

        // 2. Procesar archivos nuevos
        if (isset($_FILES['archivos_nuevos']) && !empty($_FILES['archivos_nuevos']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/ordenes_compra/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            foreach ($_FILES['archivos_nuevos']['name'] as $key => $nombre) {
                if ($_FILES['archivos_nuevos']['error'][$key] === UPLOAD_ERR_OK && !empty($nombre)) {
                    $tmpName = $_FILES['archivos_nuevos']['tmp_name'][$key];
                    $tamaÃ±o = $_FILES['archivos_nuevos']['size'][$key];
                    $tipo = $_FILES['archivos_nuevos']['type'][$key];

                    $nombreArchivo = basename($nombre);
                    $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
                    $nombreSinExtension = pathinfo($nombreArchivo, PATHINFO_FILENAME);
                    $nombreUnico = $nombreSinExtension . '_' . uniqid() . '.' . $extension;

                    $rutaArchivo = $uploadDir . $nombreUnico;

                    if (move_uploaded_file($tmpName, $rutaArchivo)) {
                        $sql_file = "INSERT INTO orden_compra_archivos 
                                        (orden_compra_id, nombre_archivo, ruta_archivo, tamaÃ±o_archivo, tipo_mime)
                                     VALUES (?, ?, ?, ?, ?)";
                        $stmt_file = $conn->prepare($sql_file);
                        $stmt_file->bind_param("issis", $orden_compra_id, $nombreArchivo, $rutaArchivo, $tamaÃ±o, $tipo);
                        if (!$stmt_file->execute()) {
                            error_log("Error guardando archivo nuevo: " . $stmt_file->error);
                        }
                        $stmt_file->close();
                    }
                }
            }
        }

        // ================================
        // CONFIRMAR TRANSACCIÃ“N
        // ================================
        $conn->commit();

        // ================================
        // NOTIFICACIONES POR CORREO 
        // ================================
        try {
            // Obtener datos completos de la orden de compra reciÃ©n creada
            $sql_oc_datos = "SELECT oc.*, 
                            u.correo_corporativo as solicitante_correo,
                            CONCAT(u.nombres, ' ', u.apellidos) as solicitante_nombre,
                            e.nombre as entidad_nombre,
                            c.nombre as categoria_nombre,
                            p.razon_social as proveedor_nombre,
                            pro.nombre_proyecto,
                            ob.nombre_obra,
                            cat.nombre_catalogo,
                            subc.proveedor_id as subcontrato_proveedor_id,
                            subc.total_estimado as subcontrato_total,
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
                            LEFT JOIN subcontratos subc ON oc.subcontrato_id = subc.id
                            LEFT JOIN conceptos con ON oc.concepto_id = con.id
                            WHERE oc.id = ?";
            $stmt_oc_datos = $conn->prepare($sql_oc_datos);
            $stmt_oc_datos->bind_param("i", $orden_compra_id);
            $stmt_oc_datos->execute();
            $oc_data = $stmt_oc_datos->get_result()->fetch_assoc();

            if ($oc_data) {
                // Preparar datos para notificaciÃ³n
                $datosOrdenCompra = [
                    'folio' => $oc_data['folio'],
                    'estado' => 'Pendiente de AprobaciÃ³n',
                    'solicitante' => $oc_data['solicitante_nombre'],
                    'entidad' => $oc_data['entidad_nombre'] ?? 'Sin especificar',
                    'categoria' => $oc_data['categoria_nombre'] ?? 'Sin especificar',
                    'proveedor' => $oc_data['proveedor_nombre'] ?? 'Sin especificar',
                    'proyecto' => $oc_data['nombre_proyecto'] ?? 'Sin especificar',
                    'obra' => $oc_data['nombre_obra'] ?? 'N/A',
                    'catalogo' => $oc_data['nombre_catalogo'] ?? 'N/A',
                    'subcontrato' => $subcontrato_id ? 'SÃ­' : 'No',
                    'concepto' => ($oc_data['codigo_concepto'] ?? '') . ' - ' . ($oc_data['nombre_concepto'] ?? 'N/A'),
                    'total' => '$' . number_format($oc_data['total'], 2),
                    'fecha_solicitud' => date('d/m/Y H:i', strtotime($oc_data['fecha_solicitud'])),
                    'url_sistema' => 'https://proatamgoc.duckdns.org/orders/see_oc.php?id=' . $orden_compra_id
                ];

                // Obtener Subdirector General 
                $sql_subdirector = "SELECT correo_corporativo, CONCAT(nombres, ' ', apellidos) as nombre_completo 
                       FROM usuarios 
                       WHERE departamento_id IN (
                           SELECT id FROM departamentos WHERE nombre LIKE '%Subdirector General%'
                       )
                       AND activo = 1
                       AND correo_corporativo IS NOT NULL";
                $result_subdirector = $conn->query($sql_subdirector);

                // Enviar notificaciÃ³n a Subdirector General usando EmailHandler
                if ($result_subdirector && $result_subdirector->num_rows > 0) {
                    $emailHandler = new EmailHandler();

                    while ($subdirector = $result_subdirector->fetch_assoc()) {
                        $emailHandler->enviarNotificacionNuevaOrdenCompra(
                            $subdirector['correo_corporativo'],
                            $subdirector['nombre_completo'],
                            $datosOrdenCompra
                        );
                    }

                    error_log("NotificaciÃ³n enviada a Subdirector General para orden de compra " . $datosOrdenCompra['folio']);
                }
            }
        } catch (Exception $e) {
            // Si falla el envÃ­o de correo, no interrumpir el flujo
            error_log("Error en notificaciÃ³n por correo: " . $e->getMessage());
        }

        sendJsonResponse(true, "Orden de compra guardada exitosamente", "list_oc.php?msg=success&folio=" . urlencode($folio));
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en save_orden.php: " . $e->getMessage());
        sendJsonResponse(false, "Error al guardar la orden: " . $e->getMessage());
    }
} else {
    header("Location: new_order.php");
    exit;
}

if (ob_get_length()) ob_end_clean();


