<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

if (!isset($_GET['id'])) {
    die("ID no proporcionado");
}

$id = intval($_GET['id']);

// Obtener orden de compra
$sql = "SELECT oc.*, e.nombre AS entidad, u.nombres, u.apellidos, c.nombre AS categoria, 
        p.nombre AS proveedor, r.folio AS folio_requisicion, pro.nombre_proyecto, ob.nombre_obra,
        pro.id as proyecto_id, ob.id as obra_id, p.razon_social
        FROM ordenes_compra oc
        JOIN entidades e ON oc.entidad_id = e.id
        JOIN usuarios u ON oc.solicitante_id = u.id
        JOIN categorias c ON oc.categoria_id = c.id
        JOIN proveedores p ON oc.proveedor_id = p.id
        LEFT JOIN requisiciones r ON oc.requisicion_id = r.id
        LEFT JOIN proyectos pro ON oc.proyecto_id = pro.id
        LEFT JOIN obras ob ON oc.obra_id = ob.id
        WHERE oc.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$orden_compra = $stmt->get_result()->fetch_assoc();

if (!$orden_compra) {
    die("Orden de compra no encontrada");
}

// Verificar que el usuario actual es el solicitante y el estado es "devuelto"
if ($orden_compra['solicitante_id'] != $_SESSION['user_id'] || $orden_compra['estado'] != 'devuelto') {
    die("No tiene permisos para editar esta orden de compra");
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
$sql_archivos = "SELECT * FROM orden_compra_archivos WHERE orden_compra_id = ? ORDER BY fecha_subida DESC";
$stmt_archivos = $conn->prepare($sql_archivos);
$stmt_archivos->bind_param("i", $id);
$stmt_archivos->execute();
$archivos = $stmt_archivos->get_result();

// Obtener datos para los selects
$entidades = $conn->query("SELECT * FROM entidades WHERE activo = 1 ORDER BY nombre");
$categorias = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
$proveedores = $conn->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre");
$unidades = $conn->query("SELECT * FROM unidades WHERE activo = 1 ORDER BY nombre");
$productos = $conn->query("SELECT ps.*, p.nombre as proveedor_nombre 
                          FROM productos_servicios ps 
                          LEFT JOIN proveedores p ON ps.proveedor_id = p.id 
                          WHERE ps.activo = 1 
                          ORDER BY ps.nombre");
$proyectos = $conn->query("SELECT * FROM proyectos ORDER BY nombre_proyecto");
$obras = $conn->query("SELECT o.*, p.nombre_proyecto 
                      FROM obras o 
                      LEFT JOIN proyectos p ON o.proyecto_id = p.id 
                      ORDER BY o.nombre_obra");

// Determinar el porcentaje de IVA basado en el monto de IVA y subtotal
$iva_porcentaje = 0;
if ($orden_compra['subtotal'] > 0 && $orden_compra['iva'] > 0) {
    $iva_porcentaje = round(($orden_compra['iva'] / $orden_compra['subtotal']) * 100, 2);
    // Redondear a los valores estándar (0, 8, 16)
    if ($iva_porcentaje >= 15) {
        $iva_porcentaje = 16;
    } elseif ($iva_porcentaje >= 7) {
        $iva_porcentaje = 8;
    } else {
        $iva_porcentaje = 0;
    }
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_oc'])) {
    // Recoger datos del formulario
    $entidad_id = intval($_POST['entidad_id']);
    $categoria_id = intval($_POST['categoria_id']);
    $proveedor_id = intval($_POST['proveedor_id']);
    $proyecto_id = !empty($_POST['proyecto_id']) ? intval($_POST['proyecto_id']) : NULL;
    $obra_id = !empty($_POST['obra_id']) ? intval($_POST['obra_id']) : NULL;
    $descripcion = $conn->real_escape_string($_POST['descripcion'] ?? '');
    $observaciones = $conn->real_escape_string($_POST['observaciones'] ?? '');
    $iva_porcentaje = floatval($_POST['iva_porcentaje'] ?? 0);
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // 1. Actualizar la orden de compra
        $sql_update = "UPDATE ordenes_compra 
                      SET entidad_id = ?, categoria_id = ?, proveedor_id = ?, 
                          proyecto_id = ?, obra_id = ?, descripcion = ?, observaciones = ?,
                          estado = 'pendiente', fecha_actualizacion = CURRENT_TIMESTAMP
                      WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("iiissssi", $entidad_id, $categoria_id, $proveedor_id, 
                                $proyecto_id, $obra_id, $descripcion, $observaciones, $id);
        $stmt_update->execute();
        
        // 2. Eliminar items antiguos
        $sql_delete_items = "DELETE FROM orden_compra_items WHERE orden_compra_id = ?";
        $stmt_delete = $conn->prepare($sql_delete_items);
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();
        
        // 3. Insertar nuevos items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $sql_insert_item = "INSERT INTO orden_compra_items 
                               (orden_compra_id, producto_id, tipo, descripcion, cantidad, unidad_id, precio_unitario, subtotal) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_item);
            
            foreach ($_POST['items'] as $item) {
                $producto_id = !empty($item['producto_id']) ? intval($item['producto_id']) : NULL;
                $tipo = $conn->real_escape_string($item['tipo'] ?? 'producto');
                $descripcion_item = $conn->real_escape_string($item['descripcion'] ?? '');
                $cantidad = floatval($item['cantidad'] ?? 1);
                $unidad_id = !empty($item['unidad_id']) ? intval($item['unidad_id']) : NULL;
                $precio_unitario = floatval($item['precio_unitario'] ?? 0);
                $subtotal = $cantidad * $precio_unitario;
                
                $stmt_insert->bind_param("iissiddd", $id, $producto_id, $tipo, $descripcion_item, 
                                       $cantidad, $unidad_id, $precio_unitario, $subtotal);
                $stmt_insert->execute();
            }
        }
        
        // 4. Recalcular totales
        $sql_calcular_totales = "SELECT SUM(subtotal) as subtotal FROM orden_compra_items WHERE orden_compra_id = ?";
        $stmt_calcular = $conn->prepare($sql_calcular_totales);
        $stmt_calcular->bind_param("i", $id);
        $stmt_calcular->execute();
        $result_totales = $stmt_calcular->get_result()->fetch_assoc();
        
        $subtotal = $result_totales['subtotal'] ?? 0;
        $iva = $subtotal * ($iva_porcentaje / 100);
        $total = $subtotal + $iva;
        
        $sql_update_totales = "UPDATE ordenes_compra SET subtotal = ?, iva = ?, total = ? WHERE id = ?";
        $stmt_totales = $conn->prepare($sql_update_totales);
        $stmt_totales->bind_param("dddi", $subtotal, $iva, $total, $id);
        $stmt_totales->execute();
        
        // 5. Manejar archivos eliminados
        if (isset($_POST['archivos_eliminados']) && !empty($_POST['archivos_eliminados'])) {
            $archivos_eliminados = json_decode($_POST['archivos_eliminados'], true);
            if (is_array($archivos_eliminados)) {
                $sql_delete_archivos = "DELETE FROM orden_compra_archivos WHERE id = ?";
                $stmt_delete_arch = $conn->prepare($sql_delete_archivos);
                
                foreach ($archivos_eliminados as $archivo_id) {
                    // Obtener ruta del archivo
                    $sql_get_archivo = "SELECT ruta_archivo FROM orden_compra_archivos WHERE id = ?";
                    $stmt_get = $conn->prepare($sql_get_archivo);
                    $stmt_get->bind_param("i", $archivo_id);
                    $stmt_get->execute();
                    $archivo_data = $stmt_get->get_result()->fetch_assoc();
                    
                    // Eliminar archivo físico
                    if ($archivo_data && file_exists($archivo_data['ruta_archivo'])) {
                        unlink($archivo_data['ruta_archivo']);
                    }
                    
                    // Eliminar registro de la base de datos
                    $stmt_delete_arch->bind_param("i", $archivo_id);
                    $stmt_delete_arch->execute();
                }
            }
        }
        
        // 6. Subir nuevos archivos
        if (isset($_FILES['nuevos_archivos'])) {
            $uploadDir = __DIR__ . "/../uploads/ordenes_compra/";
            
            // Crear directorio si no existe
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $sql_insert_archivo = "INSERT INTO orden_compra_archivos 
                                  (orden_compra_id, nombre_archivo, ruta_archivo, tipo_mime, tamaño_archivo) 
                                  VALUES (?, ?, ?, ?, ?)";
            $stmt_insert_arch = $conn->prepare($sql_insert_archivo);
            
            foreach ($_FILES['nuevos_archivos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['nuevos_archivos']['error'][$key] === UPLOAD_ERR_OK) {
                    $nombre_original = basename($_FILES['nuevos_archivos']['name'][$key]);
                    $tipo_mime = $_FILES['nuevos_archivos']['type'][$key];
                    $tamano = $_FILES['nuevos_archivos']['size'][$key];
                    
                    // Generar nombre único
                    $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                    $nombre_unico = uniqid() . '_' . time() . '.' . $extension;
                    $ruta_destino = $uploadDir . $nombre_unico;
                    
                    if (move_uploaded_file($tmp_name, $ruta_destino)) {
                        $stmt_insert_arch->bind_param("isssi", $id, $nombre_original, $ruta_destino, $tipo_mime, $tamano);
                        $stmt_insert_arch->execute();
                    }
                }
            }
        }
        
        // 7. Registrar en historial
        $sql_historial = "INSERT INTO orden_compra_historial (orden_compra_id, usuario_id, accion, comentario) 
                         VALUES (?, ?, 'Editó orden de compra devuelta', 'Orden de compra editada después de ser devuelta')";
        $stmt_historial = $conn->prepare($sql_historial);
        $stmt_historial->bind_param("ii", $id, $_SESSION['user_id']);
        $stmt_historial->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        header("Location: see_oc.php?id=$id&success=1&action=edited");
        exit;
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        $mensaje_error = "Error al actualizar la orden de compra: " . $e->getMessage();
    }
}

// Preparar archivos para JavaScript
$archivos_array = [];
while($archivo = $archivos->fetch_assoc()) {
    $archivos_array[] = $archivo;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Orden de Compra <?= htmlspecialchars($orden_compra['folio']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/styles/new_order.css">
    <style>
        .form-body{
            padding-top: 0;
        }
        .archivo-item {
            transition: all 0.3s;
        }
        .archivo-item:hover {
            background-color: #f8f9fa;
        }
        .file-size {
            font-size: 0.85em;
            color: #6c757d;
        }
        .progress-bar {
            background-color: #113456;
        }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="/orders/list_oc.php">Registro de Órdenes de Compra</a>
      <span>/</span>
      <span>Editar Orden de Compra</span>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Editar Orden de Compra <?= htmlspecialchars($orden_compra['folio']) ?></h1>
        <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Estado: Devuelto para Editar</strong> - Puede modificar todos los campos excepto los automáticos (Folio, Fecha de Solicitud).
            </div>
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
            <?php if(isset($mensaje_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= $mensaje_error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="formEditarOC" enctype="multipart/form-data">
                <!-- Información General -->
                <div class="section-title">
                    <i class="bi bi-info-circle"></i>
                    Información General
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Folio OC <span class="text-muted">(No editable)</span></label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['folio']) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha de Solicitud <span class="text-muted">(No editable)</span></label>
                        <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($orden_compra['fecha_solicitud'])) ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Entidad <span class="text-danger">*</span></label>
                        <select class="form-select" name="entidad_id" required>
                            <option value="">Seleccionar entidad</option>
                            <?php while($entidad = $entidades->fetch_assoc()): ?>
                                <option value="<?= $entidad['id'] ?>" <?= $entidad['id'] == $orden_compra['entidad_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($entidad['nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Solicitante <span class="text-muted">(No editable)</span></label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['nombres'] . ' ' . $orden_compra['apellidos']) ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Categoría <span class="text-danger">*</span></label>
                        <select class="form-select" name="categoria_id" required>
                            <option value="">Seleccionar categoría</option>
                            <?php while($categoria = $categorias->fetch_assoc()): ?>
                                <option value="<?= $categoria['id'] ?>" <?= $categoria['id'] == $orden_compra['categoria_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria['nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                        <select class="form-select" name="proveedor_id" required>
                            <option value="">Seleccionar proveedor</option>
                            <?php while($proveedor = $proveedores->fetch_assoc()): ?>
                                <option value="<?= $proveedor['id'] ?>" <?= $proveedor['id'] == $orden_compra['proveedor_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proveedor['nombre'] ?: $proveedor['razon_social']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Proyecto</label>
                        <select class="form-select" name="proyecto_id" id="proyecto_id">
                            <option value="">Sin proyecto</option>
                            <?php while($proyecto = $proyectos->fetch_assoc()): ?>
                                <option value="<?= $proyecto['id'] ?>" <?= $proyecto['id'] == $orden_compra['proyecto_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Obra</label>
                        <select class="form-select" name="obra_id" id="obra_id">
                            <option value="">Sin obra</option>
                            <?php while($obra = $obras->fetch_assoc()): ?>
                                <option value="<?= $obra['id'] ?>" 
                                        data-proyecto="<?= $obra['proyecto_id'] ?>"
                                        <?= $obra['id'] == $orden_compra['obra_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($obra['nombre_obra']) ?> (<?= htmlspecialchars($obra['nombre_proyecto']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Items de la Orden de Compra -->
                <div class="section-title">
                    <i class="bi bi-list-ul"></i>
                    Items de la Orden de Compra
                </div>

                <div id="items-container">
                    <?php 
                    $item_index = 0;
                    if ($items->num_rows > 0):
                        while($item = $items->fetch_assoc()): 
                    ?>
                    <div class="item-row" data-index="<?= $item_index ?>">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select item-tipo" name="items[<?= $item_index ?>][tipo]" required>
                                    <option value="producto" <?= $item['tipo'] == 'producto' ? 'selected' : '' ?>>Producto</option>
                                    <option value="servicio" <?= $item['tipo'] == 'servicio' ? 'selected' : '' ?>>Servicio</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Descripción <span class="text-danger">*</span></label>
                                <input type="text" class="form-control item-descripcion" name="items[<?= $item_index ?>][descripcion]" 
                                       value="<?= htmlspecialchars($item['descripcion']) ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Cantidad</label>
                                <input type="number" class="form-control item-cantidad" name="items[<?= $item_index ?>][cantidad]" 
                                       value="<?= htmlspecialchars($item['cantidad']) ?>" step="0.001" min="0.001" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Unidad</label>
                                <select class="form-select item-unidad" name="items[<?= $item_index ?>][unidad_id]">
                                    <option value="">Seleccionar</option>
                                    <?php 
                                    $unidades->data_seek(0);
                                    while($unidad = $unidades->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $unidad['id'] ?>" <?= $unidad['id'] == $item['unidad_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unidad['nombre']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <label class="form-label">Precio Unitario</label>
                                <input type="number" class="form-control item-precio" name="items[<?= $item_index ?>][precio_unitario]" 
                                       value="<?= htmlspecialchars($item['precio_unitario']) ?>" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Subtotal</label>
                                <input type="text" class="form-control item-subtotal" value="$<?= number_format($item['subtotal'], 2) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-danger btn-remove-item w-100" onclick="removeItem(this)">
                                    <i class="bi bi-trash"></i> Eliminar Item
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php 
                            $item_index++;
                        endwhile;
                    else: 
                    ?>
                    <div class="alert alert-info">
                        No hay items en esta orden de compra. Agregue al menos un item.
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <button type="button" class="btn btn-success" onclick="addItem()">
                            <i class="bi bi-plus-circle"></i> Agregar Item
                        </button>
                    </div>
                </div>

                <!-- Totales -->
                <div class="row justify-content-end mt-4">
                    <div class="col-md-6">
                        <div class="totales-box">
                            <div class="row mb-2">
                                <div class="col-6"><strong>Subtotal:</strong></div>
                                <div class="col-6 text-end" id="display-subtotal">$<?= number_format($orden_compra['subtotal'], 2) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <strong>IVA (%):</strong>
                                    <select class="form-select form-select-sm d-inline-block w-auto ms-2" name="iva_porcentaje" id="iva_porcentaje" onchange="calculateTotals()">
                                        <option value="0" <?= $iva_porcentaje == 0 ? 'selected' : '' ?>>0%</option>
                                        <option value="8" <?= $iva_porcentaje == 8 ? 'selected' : '' ?>>8%</option>
                                        <option value="16" <?= $iva_porcentaje == 16 ? 'selected' : '' ?>>16%</option>
                                    </select>
                                </div>
                                <div class="col-6 text-end" id="display-iva">$<?= number_format($orden_compra['iva'], 2) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6"><strong>TOTAL:</strong></div>
                                <div class="col-6 text-end" id="display-total">$<?= number_format($orden_compra['total'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Archivos Adjuntos -->
                <div class="section-title">
                    <i class="bi bi-paperclip"></i>
                    Archivos Adjuntos
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Puede eliminar archivos existentes o agregar nuevos archivos.
                        </div>
                        
                        <!-- Lista de archivos existentes -->
                        <div id="archivos-existente">
                            <h6>Archivos actuales:</h6>
                            <div class="list-group" id="lista-archivos">
                                <?php if(count($archivos_array) > 0): ?>
                                    <?php foreach($archivos_array as $archivo): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center archivo-item" data-id="<?= $archivo['id'] ?>">
                                            <div>
                                                <i class="bi bi-file-earmark-text me-2"></i>
                                                <span><?= htmlspecialchars($archivo['nombre_archivo']) ?></span>
                                                <small class="file-size ms-2">(<?= round($archivo['tamaño_archivo'] / 1024, 2) ?> KB)</small>
                                            </div>
                                            <div>
                                                <a href="/orders/download_archivo.php?id=<?= $archivo['id'] ?>&tipo=oc" 
                                                   class="btn btn-sm btn-primary me-2" target="_blank">
                                                    <i class="bi bi-download"></i> Descargar
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger btn-eliminar-archivo" 
                                                        onclick="marcarArchivoEliminado(<?= $archivo['id'] ?>, this)">
                                                    <i class="bi bi-trash"></i> Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted p-3">
                                        <i class="bi bi-inbox display-4"></i>
                                        <p class="mt-2">No hay archivos adjuntos</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Agregar nuevos archivos -->
                        <div class="mt-4">
                            <h6>Agregar nuevos archivos:</h6>
                            <div class="input-group">
                                <input type="file" class="form-control" id="nuevosArchivos" name="nuevos_archivos[]" 
                                       multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('nuevosArchivos').value=''">
                                    <i class="bi bi-x-circle"></i> Limpiar
                                </button>
                            </div>
                            <small class="text-muted">Formatos permitidos: PDF, Word, Excel, imágenes. Máximo 5 archivos, 10MB cada uno.</small>
                        </div>

                        <!-- Vista previa de nuevos archivos -->
                        <div class="mt-3" id="preview-nuevos-archivos" style="display: none;">
                            <h6>Nuevos archivos a subir:</h6>
                            <div class="list-group" id="lista-nuevos-archivos"></div>
                        </div>
                    </div>
                </div>

                <!-- Campo oculto para archivos eliminados -->
                <input type="hidden" name="archivos_eliminados" id="archivos_eliminados" value="">

                <!-- Descripción y Observaciones -->
                <div class="section-title">
                    <i class="bi bi-file-text"></i>
                    Descripción y Observaciones
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3" placeholder="Descripción general de la orden de compra..."><?= htmlspecialchars($orden_compra['descripcion'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3" placeholder="Observaciones adicionales..."><?= htmlspecialchars($orden_compra['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="form-actions mt-4">
                    <button type="submit" name="actualizar_oc" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Guardar Cambios y Reenviar
                    </button>
                    <a href="see_oc.php?id=<?= $id ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script>
let itemCount = <?= $item_index ?>;
let archivosEliminados = [];

// Template para nuevo item
function getNewItemTemplate(index) {
    return `
    <div class="item-row" data-index="${index}">
        <div class="row">
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select class="form-select item-tipo" name="items[${index}][tipo]" required>
                    <option value="producto">Producto</option>
                    <option value="servicio">Servicio</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Descripción <span class="text-danger">*</span></label>
                <input type="text" class="form-control item-descripcion" name="items[${index}][descripcion]" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Cantidad</label>
                <input type="number" class="form-control item-cantidad" name="items[${index}][cantidad]" step="0.001" min="0.001" value="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Unidad</label>
                <select class="form-select item-unidad" name="items[${index}][unidad_id]">
                    <option value="">Seleccionar</option>
                    <?php 
                    $unidades->data_seek(0);
                    while($unidad = $unidades->fetch_assoc()): 
                    ?>
                    <option value="<?= $unidad['id'] ?>"><?= htmlspecialchars($unidad['nombre']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-4">
                <label class="form-label">Precio Unitario</label>
                <input type="number" class="form-control item-precio" name="items[${index}][precio_unitario]" step="0.01" min="0" value="0" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Subtotal</label>
                <input type="text" class="form-control item-subtotal" value="$0.00" readonly>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-danger btn-remove-item w-100" onclick="removeItem(this)">
                    <i class="bi bi-trash"></i> Eliminar Item
                </button>
            </div>
        </div>
    </div>
    `;
}

// Agregar nuevo item
function addItem() {
    const container = document.getElementById('items-container');
    container.insertAdjacentHTML('beforeend', getNewItemTemplate(itemCount));
    
    const newItem = container.lastElementChild;
    attachEventListeners(newItem);
    itemCount++;
}

// Eliminar item
function removeItem(button) {
    const itemRow = button.closest('.item-row');
    if (document.querySelectorAll('.item-row').length > 1) {
        itemRow.remove();
        calculateTotals();
    } else {
        alert('Debe haber al menos un item en la orden de compra.');
    }
}

// Adjuntar event listeners a un item
function attachEventListeners(itemElement) {
    const cantidadInput = itemElement.querySelector('.item-cantidad');
    const precioInput = itemElement.querySelector('.item-precio');
    const subtotalInput = itemElement.querySelector('.item-subtotal');
    
    const calculateSubtotal = () => {
        const cantidad = parseFloat(cantidadInput.value) || 0;
        const precio = parseFloat(precioInput.value) || 0;
        const subtotal = cantidad * precio;
        subtotalInput.value = '$' + subtotal.toFixed(2);
        calculateTotals();
    };
    
    cantidadInput.addEventListener('input', calculateSubtotal);
    precioInput.addEventListener('input', calculateSubtotal);
}

// Calcular totales
function calculateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll('.item-row').forEach(item => {
        const cantidad = parseFloat(item.querySelector('.item-cantidad').value) || 0;
        const precio = parseFloat(item.querySelector('.item-precio').value) || 0;
        subtotal += cantidad * precio;
    });
    
    const ivaPorcentaje = parseFloat(document.getElementById('iva_porcentaje').value) || 0;
    const iva = subtotal * (ivaPorcentaje / 100);
    const total = subtotal + iva;
    
    document.getElementById('display-subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('display-iva').textContent = '$' + iva.toFixed(2);
    document.getElementById('display-total').textContent = '$' + total.toFixed(2);
}

// Marcar archivo para eliminación
function marcarArchivoEliminado(archivoId, button) {
    if (!archivosEliminados.includes(archivoId)) {
        archivosEliminados.push(archivoId);
    }
    
    // Actualizar campo oculto
    document.getElementById('archivos_eliminados').value = JSON.stringify(archivosEliminados);
    
    // Marcar visualmente como eliminado
    const archivoItem = button.closest('.archivo-item');
    archivoItem.style.opacity = '0.5';
    archivoItem.style.textDecoration = 'line-through';
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-trash"></i> Eliminado';
    button.classList.remove('btn-danger');
    button.classList.add('btn-secondary');
}

// Vista previa de nuevos archivos
document.getElementById('nuevosArchivos').addEventListener('change', function() {
    const files = this.files;
    const previewContainer = document.getElementById('preview-nuevos-archivos');
    const listaNuevos = document.getElementById('lista-nuevos-archivos');
    
    if (files.length > 0) {
        previewContainer.style.display = 'block';
        listaNuevos.innerHTML = '';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileSize = (file.size / 1024).toFixed(2) + ' KB';
            
            const listItem = document.createElement('div');
            listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
            listItem.innerHTML = `
                <div>
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <span>${file.name}</span>
                    <small class="file-size ms-2">(${fileSize})</small>
                </div>
            `;
            listaNuevos.appendChild(listItem);
        }
    } else {
        previewContainer.style.display = 'none';
    }
});

// Validar formulario antes de enviar
document.getElementById('formEditarOC').addEventListener('submit', function(e) {
    // Validar que hay al menos un item
    const items = document.querySelectorAll('.item-row');
    if (items.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un item a la orden de compra.');
        return;
    }
    
    // Validar archivos (máximo 5 nuevos)
    const nuevosArchivos = document.getElementById('nuevosArchivos').files;
    if (nuevosArchivos.length > 5) {
        e.preventDefault();
        alert('Máximo 5 archivos nuevos pueden ser agregados.');
        return;
    }
    
    // Validar tamaño de archivos (10MB máximo cada uno)
    for (let file of nuevosArchivos) {
        if (file.size > 10 * 1024 * 1024) { // 10MB
            e.preventDefault();
            alert(`El archivo "${file.name}" excede el tamaño máximo de 10MB.`);
            return;
        }
    }
    
    // Mostrar confirmación
    if (!confirm('¿Está seguro de que desea guardar los cambios y reenviar la orden de compra?')) {
        e.preventDefault();
    }
});

// Filtrar obras por proyecto
document.getElementById('proyecto_id').addEventListener('change', function() {
    const proyectoId = this.value;
    const obraSelect = document.getElementById('obra_id');
    
    Array.from(obraSelect.options).forEach(option => {
        if (option.value === '') return;
        const show = !proyectoId || option.getAttribute('data-proyecto') === proyectoId;
        option.style.display = show ? '' : 'none';
    });
    
    // Resetear selección si la obra actual no pertenece al proyecto seleccionado
    if (proyectoId && obraSelect.value) {
        const selectedOption = obraSelect.options[obraSelect.selectedIndex];
        if (selectedOption.getAttribute('data-proyecto') !== proyectoId) {
            obraSelect.value = '';
        }
    }
});

// Adjuntar event listeners a items existentes al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-row').forEach(item => {
        attachEventListeners(item);
    });
    
    // Inicializar el campo de archivos eliminados
    document.getElementById('archivos_eliminados').value = JSON.stringify(archivosEliminados);
});
</script>

</body>
</html>