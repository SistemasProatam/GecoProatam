<?php
// catalogos_manager.php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../conexion.php";

checkSession();
preventCaching();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'obtener_catalogos':
            $obra_id = $_POST['obra_id'] ?? 0;
            if ($obra_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de obra inválido']);
                exit;
            }
            
            $sql = "SELECT c.*, 
                   (SELECT COUNT(*) FROM conceptos WHERE catalogo_id = c.id) as total_conceptos
                   FROM catalogos c 
                   WHERE c.obra_id = ? 
                   ORDER BY c.fecha_creacion DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $obra_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $catalogos = [];
            while ($row = $result->fetch_assoc()) {
                $catalogos[] = $row;
            }
            
            echo json_encode($catalogos);
            break;
            
        case 'crear_catalogo':
            $obra_id = $_POST['obra_id'] ?? 0;
            $nombre_catalogo = $_POST['nombre_catalogo'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            
            if ($obra_id <= 0 || empty($nombre_catalogo)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit;
            }
            
            $sql = "INSERT INTO catalogos (obra_id, nombre_catalogo, descripcion, fecha_creacion) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $obra_id, $nombre_catalogo, $descripcion);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Catálogo creado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al crear catálogo: ' . $stmt->error]);
            }
            break;
            
        case 'obtener_conceptos':
            $catalogo_id = $_POST['catalogo_id'] ?? 0;
            if ($catalogo_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de catálogo inválido']);
                exit;
            }
            
            // CORREGIDO: Ordenamiento que respeta la estructura jerárquica del Excel
                        // Contar y sumar items desde orden_compra_items (solo órdenes pagadas)
                        $sql = "SELECT c.*, 
                                     (SELECT COUNT(*) FROM orden_compra_items oci JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') as total_items,
                                     (SELECT COALESCE(SUM(oci.subtotal), 0) 
                                        FROM orden_compra_items oci 
                                        JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                                        WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') as monto_total
                                     FROM conceptos c 
                                     WHERE c.catalogo_id = ? 
                                     ORDER BY 
                                         -- 1. Ordenar por categoría (I, II, III, etc.)
                                         CASE 
                                             WHEN c.categoria = 'I' THEN 1
                                             WHEN c.categoria = 'II' THEN 2
                                             WHEN c.categoria = 'III' THEN 3
                                             WHEN c.categoria = 'IV' THEN 4
                                             WHEN c.categoria = 'V' THEN 5
                                             WHEN c.categoria = 'VI' THEN 6
                                             WHEN c.categoria = 'VII' THEN 7
                                             WHEN c.categoria = 'VIII' THEN 8
                                             WHEN c.categoria = 'IX' THEN 9
                                             WHEN c.categoria = 'X' THEN 10
                                             WHEN c.categoria IS NULL OR c.categoria = '' THEN 999
                                             ELSE 100
                                         END ASC,
                                         -- 2. Ordenar por subcategoría (I.1, I.2, etc.)
                                         CASE 
                                             WHEN c.subcategoria REGEXP '^[IVX]+\.[0-9]+$' THEN 
                                                 CAST(SUBSTRING_INDEX(c.subcategoria, '.', -1) AS UNSIGNED)
                                             WHEN c.subcategoria IS NULL OR c.subcategoria = '' THEN 999
                                             ELSE 100
                                         END ASC,
                                         -- 3. Ordenar por número original (1, 2, 3, etc.)
                                         CASE 
                                             WHEN c.numero_original REGEXP '^[0-9]+$' THEN  
                                                 CAST(c.numero_original AS UNSIGNED)
                                             ELSE 9999
                                         END ASC,
                                         -- 4. Como respaldo, ordenar por código
                                         c.codigo_concepto ASC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $catalogo_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $conceptos = [];
            while ($row = $result->fetch_assoc()) {
                $conceptos[] = $row;
            }
            
            echo json_encode($conceptos);
            break;
            
        case 'obtener_conceptos_agrupados':
            $catalogo_id = $_POST['catalogo_id'] ?? 0;
            if ($catalogo_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de catálogo inválido']);
                exit;
            }
            
            $sql = "SELECT 
                    c.categoria,
                    c.subcategoria,
                    COUNT(*) as total_conceptos,
                    GROUP_CONCAT(c.id) as conceptos_ids
                   FROM conceptos c 
                   WHERE c.catalogo_id = ? 
                   GROUP BY c.categoria, c.subcategoria
                   ORDER BY 
                     CASE 
                       WHEN c.categoria = 'I' THEN 1
                       WHEN c.categoria = 'II' THEN 2
                       WHEN c.categoria = 'III' THEN 3
                       WHEN c.categoria = 'IV' THEN 4
                       WHEN c.categoria = 'V' THEN 5
                       WHEN c.categoria = 'VI' THEN 6
                       WHEN c.categoria = 'VII' THEN 7
                       WHEN c.categoria = 'VIII' THEN 8
                       WHEN c.categoria = 'IX' THEN 9
                       WHEN c.categoria = 'X' THEN 10
                       WHEN c.categoria IS NULL OR c.categoria = '' THEN 999
                       ELSE 100
                     END ASC,
                     CASE 
                       WHEN c.subcategoria REGEXP '^[IVX]+\\.[0-9]+$' THEN 
                         CAST(SUBSTRING_INDEX(c.subcategoria, '.', -1) AS UNSIGNED)
                       WHEN c.subcategoria IS NULL OR c.subcategoria = '' THEN 999
                       ELSE 100
                     END ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $catalogo_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $agrupados = [];
            while ($row = $result->fetch_assoc()) {
                $agrupados[] = $row;
            }
            
            echo json_encode($agrupados);
            break;
            
        case 'crear_concepto':
            $catalogo_id     = $_POST['catalogo_id']     ?? 0;
            $codigo_concepto = $_POST['codigo_concepto'] ?? '';
            $nombre_concepto = $_POST['nombre_concepto'] ?? '';
            $descripcion     = $_POST['descripcion']     ?? '';
            $unidad_medida   = $_POST['unidad_medida']   ?? '';
            $categoria       = $_POST['categoria']       ?? '';
            $subcategoria    = $_POST['subcategoria']    ?? '';
            $numero_original = $_POST['numero_original'] ?? '';
            $cantidad        = isset($_POST['cantidad']) && $_POST['cantidad'] !== '' ? floatval($_POST['cantidad']) : null;
            $precio_unitario = $_POST['precio_unitario'] !== '' ? $_POST['precio_unitario'] : null;
            $importe         = $_POST['importe']         !== '' ? $_POST['importe']         : null;
            $fecha_inicio    = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio']       : null;
            $fecha_fin       = !empty($_POST['fecha_fin'])    ? $_POST['fecha_fin']          : null;
            $permitir_duplicados = ($_POST['permitir_duplicados'] ?? 'false') === 'true';
            
            if ($catalogo_id <= 0 || empty($codigo_concepto) || empty($nombre_concepto)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit;
            }
            
            // Verificación de duplicados
            if ($permitir_duplicados) {
                $sql_check = "SELECT id FROM conceptos 
                             WHERE catalogo_id = ? AND codigo_concepto = ? AND categoria = ? AND subcategoria = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("isss", $catalogo_id, $codigo_concepto, $categoria, $subcategoria);
            } else {
                $sql_check = "SELECT id FROM conceptos WHERE catalogo_id = ? AND codigo_concepto = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("is", $catalogo_id, $codigo_concepto);
            }
            
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $mensaje_error = "El código '$codigo_concepto' ya existe";
                if ($permitir_duplicados) {
                    $mensaje_error .= " con la misma categoría y subcategoría";
                }
                echo json_encode(['success' => false, 'error' => $mensaje_error]);
                exit;
            }
            
            // Insertar concepto con los nuevos campos
            $sql_insert = "INSERT INTO conceptos 
                          (catalogo_id, codigo_concepto, nombre_concepto, descripcion, 
                           unidad_medida, categoria, subcategoria, numero_original,
                           cantidad, precio_unitario, importe, fecha_inicio, fecha_fin,
                           fecha_creacion) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("isssssssdddss", 
                $catalogo_id, 
                $codigo_concepto, 
                $nombre_concepto, 
                $descripcion, 
                $unidad_medida, 
                $categoria, 
                $subcategoria, 
                $numero_original,
                $cantidad,
                $precio_unitario,
                $importe,
                $fecha_inicio,
                $fecha_fin
            );
            if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Concepto creado correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al crear concepto: ' . $stmt_insert->error]);
    }
    break;
            
        case 'importar_conceptos_excel':
            $catalogo_id = $_POST['catalogo_id'] ?? 0;
            $datos_excel = json_decode($_POST['datos_excel'] ?? '[]', true);
            $permitir_duplicados = ($_POST['permitir_duplicados'] ?? 'false') === 'true';
            
            if ($catalogo_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de catálogo inválido']);
                exit;
            }
            
            if (empty($datos_excel)) {
                echo json_encode(['success' => false, 'error' => 'No hay datos para importar']);
                exit;
            }
            
            $conceptos_importados = 0;
            $errores = [];
            
            $conn->begin_transaction();
            
            try {
                foreach ($datos_excel as $index => $concepto_data) {
                    try {
                        $codigo_concepto = $concepto_data['codigo_concepto'] ?? '';
                        $nombre_concepto = $concepto_data['nombre_concepto'] ?? '';
                        $descripcion     = $concepto_data['descripcion']     ?? '';
                        $unidad_medida   = $concepto_data['unidad_medida']   ?? '';
                        $categoria       = $concepto_data['categoria']       ?? '';
                        $subcategoria    = $concepto_data['subcategoria']    ?? '';
                        $numero_original = $concepto_data['numero_original'] ?? '';
                        $cantidad        = isset($concepto_data['cantidad']) && $concepto_data['cantidad'] !== '' ? floatval($concepto_data['cantidad']) : null;
                        $precio_unitario = isset($concepto_data['precio_unitario']) && $concepto_data['precio_unitario'] !== '' ? $concepto_data['precio_unitario'] : null;
                        $importe         = isset($concepto_data['importe'])         && $concepto_data['importe']         !== '' ? $concepto_data['importe']         : null;
                        $fecha_inicio    = !empty($concepto_data['fecha_inicio'])   ? $concepto_data['fecha_inicio']    : null;
                        $fecha_fin       = !empty($concepto_data['fecha_fin'])      ? $concepto_data['fecha_fin']       : null;
                        if (empty($codigo_concepto) || empty($nombre_concepto)) {
                            $errores[] = "Fila " . ($index + 1) . ": código o nombre vacío";
                            continue;
                        }
                        
                        // Insertar concepto con los nuevos campos
                        $sql_insert = "INSERT INTO conceptos 
                                      (catalogo_id, codigo_concepto, nombre_concepto, descripcion, 
                                       unidad_medida, categoria, subcategoria, numero_original,
                                       cantidad, precio_unitario, importe, fecha_inicio, fecha_fin,
                                       fecha_creacion) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt_insert = $conn->prepare($sql_insert);
                        $stmt_insert->bind_param("isssssssdddss", 
                            $catalogo_id, 
                            $codigo_concepto, 
                            $nombre_concepto, 
                            $descripcion, 
                            $unidad_medida, 
                            $categoria, 
                            $subcategoria, 
                            $numero_original,
                            $cantidad,
                            $precio_unitario,
                            $importe,
                            $fecha_inicio,
                            $fecha_fin
                        );
                        
                        if ($stmt_insert->execute()) {
                            $conceptos_importados++;
                        } else {
                            $errores[] = "Fila " . ($index + 1) . ": error al insertar - " . $stmt_insert->error;
                        }
                        
                    } catch (Exception $e) {
                        $errores[] = "Fila " . ($index + 1) . ": " . $e->getMessage();
                    }
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'conceptos_importados' => $conceptos_importados,
                    'errores' => $errores,
                    'total_procesados' => count($datos_excel)
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode([
                    'success' => false, 
                    'error' => 'Error en la transacción: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'obtener_detalle_concepto':
            $concepto_id = $_POST['concepto_id'] ?? 0;
            if ($concepto_id <= 0) {
                echo json_encode(['error' => 'ID de concepto inválido']);
                exit;
            }
            
            // Obtener detalles del concepto
            // Los items se cuentan desde orden_compra_items cuando oc.estado = 'pagado'
            $sql = "SELECT c.*, 
                   (SELECT COUNT(*) FROM orden_compra_items oci JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') as total_items,
                   (SELECT COALESCE(SUM(oci.subtotal), 0) 
                    FROM orden_compra_items oci 
                    JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                    WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') as monto_total
                   FROM conceptos c 
                   WHERE c.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $concepto_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => true, 'concepto' => $result->fetch_assoc()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Concepto no encontrado']);
            }
            break;
            
        case 'obtener_items_concepto':
    $concepto_id = $_POST['concepto_id'] ?? 0;
    
    // Obtener items asociados al concepto desde orden_compra_items (solo órdenes pagadas)
    $sql = "SELECT 
                oci.id,
                oci.descripcion,
                oci.cantidad,
                IFNULL(u.nombre, oci.unidad_medida) as unidad_medida,
                oci.precio_unitario,
                oci.subtotal,
                oci.fecha_creacion,
                oc.folio as orden_folio,
                oc.fecha_solicitud as orden_fecha,
                oci.orden_compra_id
            FROM orden_compra_items oci
            LEFT JOIN unidades u ON oci.unidad_id = u.id
            JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
            WHERE oci.concepto_id = ? AND oc.estado = 'pagado'
            ORDER BY oci.fecha_creacion DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $concepto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode($items);
    break;
            
        case 'eliminar_catalogo':
            $catalogo_id = $_POST['catalogo_id'] ?? 0;
            if ($catalogo_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de catálogo inválido']);
                exit;
            }
            
            // Iniciar transacción
            $conn->begin_transaction();
            
            try {
                // Nota: la tabla `concepto_items` no existe en esta instalación;
                // no se requiere eliminar items previos. Proceder a eliminar conceptos.
                
                // Eliminar conceptos
                $sql_delete_conceptos = "DELETE FROM conceptos WHERE catalogo_id = ?";
                $stmt_conceptos = $conn->prepare($sql_delete_conceptos);
                $stmt_conceptos->bind_param("i", $catalogo_id);
                $stmt_conceptos->execute();
                
                // Eliminar catálogo
                $sql_delete_catalogo = "DELETE FROM catalogos WHERE id = ?";
                $stmt_catalogo = $conn->prepare($sql_delete_catalogo);
                $stmt_catalogo->bind_param("i", $catalogo_id);
                $stmt_catalogo->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Catálogo eliminado correctamente']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => 'Error al eliminar catálogo: ' . $e->getMessage()]);
            }
            break;
            
        case 'eliminar_concepto':
            $concepto_id = $_POST['concepto_id'] ?? 0;
            if ($concepto_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de concepto inválido']);
                exit;
            }
            
            // Iniciar transacción
            $conn->begin_transaction();
            
            try {
                // Eliminar concepto (no hay tabla concepto_items en esta BD)
                $sql_delete_concepto = "DELETE FROM conceptos WHERE id = ?";
                $stmt_concepto = $conn->prepare($sql_delete_concepto);
                $stmt_concepto->bind_param("i", $concepto_id);
                $stmt_concepto->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Concepto eliminado correctamente']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => 'Error al eliminar concepto: ' . $e->getMessage()]);
            }
            break;
            
        case 'actualizar_concepto':
            $concepto_id     = $_POST['concepto_id']     ?? 0;
            $codigo_concepto = $_POST['codigo_concepto'] ?? '';
            $nombre_concepto = $_POST['nombre_concepto'] ?? '';
            $descripcion     = $_POST['descripcion']     ?? '';
            $unidad_medida   = $_POST['unidad_medida']   ?? '';
            $categoria       = $_POST['categoria']       ?? '';
            $subcategoria    = $_POST['subcategoria']    ?? '';
            $numero_original = $_POST['numero_original'] ?? '';
            $cantidad        = isset($_POST['cantidad']) && $_POST['cantidad'] !== '' ? floatval($_POST['cantidad']) : null;
            $precio_unitario = isset($_POST['precio_unitario']) && $_POST['precio_unitario'] !== '' ? $_POST['precio_unitario'] : null;
            $importe         = isset($_POST['importe'])         && $_POST['importe']         !== '' ? $_POST['importe']         : null;
            $fecha_inicio    = !empty($_POST['fecha_inicio'])   ? $_POST['fecha_inicio']    : null;
            $fecha_fin       = !empty($_POST['fecha_fin'])      ? $_POST['fecha_fin']       : null;
            if ($concepto_id <= 0 || empty($codigo_concepto) || empty($nombre_concepto)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit;
            }
            
            // Verificar si el código ya existe en otro concepto del mismo catálogo
            $sql_check = "SELECT id FROM conceptos 
                         WHERE id != ? AND codigo_concepto = ? AND catalogo_id = (SELECT catalogo_id FROM conceptos WHERE id = ?)";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("isi", $concepto_id, $codigo_concepto, $concepto_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => "El código '$codigo_concepto' ya existe en otro concepto del catálogo"]);
                exit;
            }
            
            // Actualizar concepto con los nuevos campos
            $sql_update = "UPDATE conceptos 
                          SET codigo_concepto = ?, nombre_concepto = ?, descripcion = ?, 
                              unidad_medida = ?, categoria = ?, subcategoria = ?, numero_original = ?,
                              cantidad = ?, precio_unitario = ?, importe = ?, fecha_inicio = ?, fecha_fin = ?,
                              fecha_actualizacion = NOW()
                          WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sssssssdddssi", 
                $codigo_concepto, 
                $nombre_concepto, 
                $descripcion, 
                $unidad_medida, 
                $categoria, 
                $subcategoria, 
                $numero_original,
                $cantidad,
                $precio_unitario,
                $importe,
                $fecha_inicio,
                $fecha_fin,
                $concepto_id
            );
            
            if ($stmt_update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Concepto actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar concepto: ' . $stmt_update->error]);
            }
            break;
            
        case 'actualizar_catalogo':
            $catalogo_id = $_POST['catalogo_id'] ?? 0;
            $nombre_catalogo = $_POST['nombre_catalogo'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            
            if ($catalogo_id <= 0 || empty($nombre_catalogo)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit;
            }
            
            $sql = "UPDATE catalogos 
                    SET nombre_catalogo = ?, descripcion = ?, fecha_actualizacion = NOW()
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nombre_catalogo, $descripcion, $catalogo_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Catálogo actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar catálogo: ' . $stmt->error]);
            }
            break;
            
        case 'eliminar_concepto':
            $concepto_id = $_POST['concepto_id'] ?? 0;
            
            if ($concepto_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de concepto inválido']);
                exit;
            }
            
            // Iniciar transacción para eliminar el concepto y sus relaciones
            $conn->begin_transaction();
            
            try {
                // Eliminar el concepto
                $sql_delete = "DELETE FROM conceptos WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param("i", $concepto_id);
                
                if (!$stmt_delete->execute()) {
                    throw new Exception("Error al eliminar concepto: " . $stmt_delete->error);
                }
                
                // Confirmar transacción
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Concepto eliminado correctamente']);
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

$conn->close();
?>