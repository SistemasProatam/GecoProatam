<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

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

// Obtener subcontrato asociado
$subcontrato_id = null;
$subcontrato_nombre = null;
if ($orden_compra['subcontrato_id']) {
    $sql_subcontrato = "SELECT s.id, s.proveedor_id, p.nombre as proveedor_nombre 
                        FROM subcontratos s
                        LEFT JOIN proveedores p ON s.proveedor_id = p.id
                        WHERE s.id = ?";
    $stmt_sub = $conn->prepare($sql_subcontrato);
    $stmt_sub->bind_param("i", $orden_compra['subcontrato_id']);
    $stmt_sub->execute();
    $sub_data = $stmt_sub->get_result()->fetch_assoc();
    if ($sub_data) {
        $subcontrato_id = $sub_data['id'];
        $subcontrato_nombre = $sub_data['proveedor_nombre'];
    }
    $stmt_sub->close();
}

if (!$orden_compra) {
    die("Orden de compra no encontrada");
}

// Verificar permisos: el solicitante si está devuelta, o personal de Sistemas
$es_sistemas = ($_SESSION['departamento'] ?? '') === 'Tecnico de Sistemas';

if (!$es_sistemas && ($orden_compra['solicitante_id'] != $_SESSION['user_id'] || $orden_compra['estado'] != 'devuelto')) {
    die("No tiene permisos para editar esta orden de compra");
}

// Obtener items de la orden de compra
$sql_items = "SELECT oci.*, ps.nombre AS producto, ps.tipo, un.nombre AS unidad, c.codigo_concepto AS concepto
              FROM orden_compra_items oci
              LEFT JOIN productos_servicios ps ON oci.producto_id = ps.id
              LEFT JOIN unidades un ON oci.unidad_id = un.id
              LEFT JOIN conceptos c ON oci.concepto_id = c.id
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
$entidades  = $conn->query("SELECT * FROM entidades WHERE activo = 1 ORDER BY nombre");
$categorias = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
$proveedores = $conn->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre");
$unidades   = $conn->query("SELECT * FROM unidades WHERE activo = 1 ORDER BY nombre");
$productos  = $conn->query("SELECT ps.*, p.nombre as proveedor_nombre 
                            FROM productos_servicios ps 
                            LEFT JOIN proveedores p ON ps.proveedor_id = p.id 
                            WHERE ps.activo = 1 
                            ORDER BY ps.nombre");
$proyectos  = $conn->query("SELECT * FROM proyectos ORDER BY nombre_proyecto");
$obras      = $conn->query("SELECT o.*, p.nombre_proyecto 
                            FROM obras o 
                            LEFT JOIN proyectos p ON o.proyecto_id = p.id 
                            ORDER BY o.nombre_obra");
$catalogo_id_oc = $orden_compra['catalogo_id'] ?? 0;
$oc_id = $orden_compra['id'];
// Solo traer conceptos del catálogo actual o que ya estén en la OC para evitar duplicados masivos de otros catálogos
$conceptos  = $conn->query("SELECT c.id, c.codigo_concepto AS nombre_concepto 
                            FROM conceptos c 
                            LEFT JOIN concepto_nodos n ON c.nodo_id = n.id
                            WHERE (c.catalogo_id = '$catalogo_id_oc' AND c.catalogo_id > 0)
                               OR c.id IN (SELECT concepto_id FROM orden_compra_items WHERE orden_compra_id = '$oc_id' AND concepto_id IS NOT NULL)
                            ORDER BY 
                                COALESCE(n.sort_path, '9999') ASC,
                                CAST(NULLIF(c.numero_original, '') AS UNSIGNED) ASC,
                                c.codigo_concepto ASC");

// Determinar el porcentaje de IVA basado en el monto de IVA y subtotal
$iva_porcentaje = 0;
if ($orden_compra['subtotal'] > 0 && $orden_compra['iva'] > 0) {
    $iva_porcentaje = round(($orden_compra['iva'] / $orden_compra['subtotal']) * 100, 2);
    if ($iva_porcentaje >= 15)      $iva_porcentaje = 16;
    elseif ($iva_porcentaje >= 7)   $iva_porcentaje = 8;
    else                             $iva_porcentaje = 0;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_oc'])) {
    $entidad_id   = intval($_POST['entidad_id']);
    $categoria_id = intval($_POST['categoria_id']);
    $proveedor_id = intval($_POST['proveedor_id']);
    $proyecto_id  = !empty($_POST['proyecto_id']) ? intval($_POST['proyecto_id']) : NULL;
    $obra_id      = !empty($_POST['obra_id'])     ? intval($_POST['obra_id'])     : NULL;
    $descripcion  = $conn->real_escape_string($_POST['descripcion']  ?? '');
    $observaciones = $conn->real_escape_string($_POST['observaciones'] ?? '');
    $iva_porcentaje = floatval($_POST['iva_porcentaje'] ?? 0);

    $conn->begin_transaction();

    try {
        // 1. Actualizar la orden de compra
        $sql_update = "UPDATE ordenes_compra 
                       SET entidad_id = ?, categoria_id = ?, proveedor_id = ?, 
                           proyecto_id = ?, obra_id = ?, subcontrato_id = ?,
                           descripcion = ?, observaciones = ?,
                           estado = 'pendiente', fecha_actualizacion = CURRENT_TIMESTAMP
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param(
            "iiiiisssi",
            $entidad_id,
            $categoria_id,
            $proveedor_id,
            $proyecto_id,
            $obra_id,
            $subcontrato_id,
            $descripcion,
            $observaciones,
            $id
        );
        $stmt_update->execute();

        // 2. Eliminar items antiguos
        $stmt_delete = $conn->prepare("DELETE FROM orden_compra_items WHERE orden_compra_id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();

        // 3. Insertar nuevos items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $sql_insert_item = "INSERT INTO orden_compra_items 
                                (orden_compra_id, producto_id, tipo, descripcion, cantidad, unidad_id, concepto_id, precio_unitario, subtotal) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_item);

            foreach ($_POST['items'] as $item) {
                $producto_id      = !empty($item['producto_id']) ? intval($item['producto_id']) : NULL;
                $tipo             = !empty($item['tipo']) ? $item['tipo'] : 'producto';
                $descripcion_item = $item['descripcion'] ?? '';
                $cantidad         = floatval($item['cantidad'] ?? 1);
                $unidad_id        = !empty($item['unidad_id'])  ? intval($item['unidad_id'])  : NULL;
                $concepto_id      = !empty($item['concepto_id']) ? intval($item['concepto_id']) : NULL;
                $precio_unitario  = floatval($item['precio_unitario'] ?? 0);
                $subtotal_item    = round($cantidad * $precio_unitario, 2);

                $stmt_insert->bind_param(
                    "iissdiidd",
                    $id,
                    $producto_id,
                    $tipo,
                    $descripcion_item,
                    $cantidad,
                    $unidad_id,
                    $concepto_id,
                    $precio_unitario,
                    $subtotal_item
                );
                $stmt_insert->execute();
            }
        }

        // 4. Recalcular totales
        $stmt_calcular = $conn->prepare("SELECT SUM(subtotal) AS subtotal FROM orden_compra_items WHERE orden_compra_id = ?");
        $stmt_calcular->bind_param("i", $id);
        $stmt_calcular->execute();
        $result_totales = $stmt_calcular->get_result()->fetch_assoc();

        $subtotal_calc = round($result_totales['subtotal'] ?? 0, 2);
        $iva_calc      = round($subtotal_calc * ($iva_porcentaje / 100), 2);
        $total_calc    = round($subtotal_calc + $iva_calc, 2);

        $stmt_totales = $conn->prepare("UPDATE ordenes_compra SET subtotal = ?, iva = ?, total = ? WHERE id = ?");
        $stmt_totales->bind_param("dddi", $subtotal_calc, $iva_calc, $total_calc, $id);
        $stmt_totales->execute();

        // 5. Manejar archivos eliminados
        if (isset($_POST['archivos_eliminados']) && !empty($_POST['archivos_eliminados'])) {
            $archivos_eliminados = json_decode($_POST['archivos_eliminados'], true);
            if (is_array($archivos_eliminados)) {
                $stmt_delete_arch = $conn->prepare("DELETE FROM orden_compra_archivos WHERE id = ?");
                foreach ($archivos_eliminados as $archivo_id) {
                    $stmt_get = $conn->prepare("SELECT ruta_archivo FROM orden_compra_archivos WHERE id = ?");
                    $stmt_get->bind_param("i", $archivo_id);
                    $stmt_get->execute();
                    $archivo_data = $stmt_get->get_result()->fetch_assoc();
                    if ($archivo_data && file_exists($archivo_data['ruta_archivo'])) {
                        unlink($archivo_data['ruta_archivo']);
                    }
                    $stmt_delete_arch->bind_param("i", $archivo_id);
                    $stmt_delete_arch->execute();
                }
            }
        }

        // 6. Subir nuevos archivos
        if (isset($_FILES['nuevos_archivos'])) {
            $uploadDir = __DIR__ . "/../uploads/ordenes_compra/";
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

            $stmt_insert_arch = $conn->prepare(
                "INSERT INTO orden_compra_archivos (orden_compra_id, nombre_archivo, ruta_archivo, tipo_mime, tamaño_archivo) VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($_FILES['nuevos_archivos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['nuevos_archivos']['error'][$key] === UPLOAD_ERR_OK) {
                    $nombre_original = basename($_FILES['nuevos_archivos']['name'][$key]);
                    $tipo_mime       = $_FILES['nuevos_archivos']['type'][$key];
                    $tamano          = $_FILES['nuevos_archivos']['size'][$key];
                    $extension       = pathinfo($nombre_original, PATHINFO_EXTENSION);
                    $nombre_unico    = uniqid() . '_' . time() . '.' . $extension;
                    $ruta_destino    = $uploadDir . $nombre_unico;
                    if (move_uploaded_file($tmp_name, $ruta_destino)) {
                        $stmt_insert_arch->bind_param("isssi", $id, $nombre_original, $ruta_destino, $tipo_mime, $tamano);
                        $stmt_insert_arch->execute();
                    }
                }
            }
        }

        // 7. Registrar en historial
        $accion_hist = ($orden_compra['estado'] == 'devuelto') ? 'Editó orden de compra devuelta' : 'Editó orden de compra';
        $comentario_hist = ($orden_compra['estado'] == 'devuelto')
            ? 'Orden de compra editada después de ser devuelta'
            : 'Orden de compra editada por personal de Sistemas/Administrador';

        $stmt_historial = $conn->prepare(
            "INSERT INTO orden_compra_historial (orden_compra_id, usuario_id, accion, comentario) VALUES (?, ?, ?, ?)"
        );
        $stmt_historial->bind_param("iiss", $id, $_SESSION['user_id'], $accion_hist, $comentario_hist);
        $stmt_historial->execute();

        $conn->commit();
        header("Location: see_oc.php?id=$id&success=1&action=edited");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje_error = "Error al actualizar la orden de compra: " . $e->getMessage();
    }
}

// ── Preparar datos para JS (se pasan como JSON para no mezclar PHP en JS) ──

// Recolectar unidades
$unidades_arr = [];
$unidades->data_seek(0);
while ($u = $unidades->fetch_assoc()) {
    $unidades_arr[] = ['id' => $u['id'], 'nombre' => $u['nombre']];
}

// Recolectar conceptos
$conceptos_arr = [];
$conceptos->data_seek(0);
while ($c = $conceptos->fetch_assoc()) {
    $conceptos_arr[] = ['id' => $c['id'], 'nombre' => $c['nombre_concepto']];
}

// Recolectar productos/servicios del catálogo para el modal
$productos_arr = [];
$productos->data_seek(0);
while ($p = $productos->fetch_assoc()) {
    $productos_arr[] = [
        'id'               => $p['id'],
        'nombre'           => $p['nombre'],
        'tipo'             => $p['tipo'],
        'precio'           => isset($p['precio']) ? (float)$p['precio'] : 0,
        'proveedor_nombre' => $p['proveedor_nombre'] ?? '',
    ];
}

// Recolectar archivos para JS
$archivos_array = [];
$archivos->data_seek(0);
while ($archivo = $archivos->fetch_assoc()) {
    $archivos_array[] = $archivo;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
    <title>Editar Orden de Compra | GECO Proatam</title>



<body>
    <?php include __DIR__ . "/../includes/navbar.php"; ?>


    <div class="orders-page-container">

        <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
        <div class="orders-page-header mb-4">
            <div class="orders-page-header-info">
                <nav class="orders-breadcrumb">
                    <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                    <span class="separator">›</span>
                    <a href="<?= BASE_URL ?>/orders/list_oc.php">Órdenes de Compra</a>
                    <span class="separator">›</span>
                    <span>Editar Orden de Compra</span>
                </nav>
                <div style="display:flex; align-items:center;">
                    <h1 class="orders-page-title">Editar <?= htmlspecialchars($orden_compra['folio']) ?></h1>
                </div>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-start;">
                <a href="see_oc.php?id=<?= $id ?>" class="btn-geco-secondary" style="background:var(--white, #ffffff); color:var(--s-700, #113557); border:1.5px solid var(--gray-200, #e5e7eb);">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <div class="orders-page-content">

            <?php if (isset($mensaje_error)): ?>
                <div class="orders-alert orders-alert--danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= $mensaje_error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="formEditarOC" enctype="multipart/form-data">

                <!-- ── Información General ── -->
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-circle-info"></i> Información General</span>
                    </div>
                    <div class="oc-card-body">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="oc-form-label">Folio OC <span class="text-muted">(No editable)</span></label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($orden_compra['folio']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="oc-form-label">Fecha de Solicitud <span class="text-muted">(No editable)</span></label>
                                <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($orden_compra['fecha_solicitud'])) ?>" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="oc-form-label">Entidad <span class="text-danger">*</span></label>
                                <select class="form-select" name="entidad_id" required>
                                    <option value="">Seleccionar entidad</option>
                                    <?php while ($entidad = $entidades->fetch_assoc()): ?>
                                        <option value="<?= $entidad['id'] ?>" <?= $entidad['id'] == $orden_compra['entidad_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($entidad['nombre']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="oc-form-label">Solicitante <span class="text-muted">(No editable)</span></label>
                                <input type="text" class="form-control"
                                    value="<?= htmlspecialchars($orden_compra['nombres'] . ' ' . $orden_compra['apellidos']) ?>" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="oc-form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" name="categoria_id" id="categoria_id" required>
                                    <option value="">Seleccionar categoría</option>
                                    <?php
                                    $categorias->data_seek(0);
                                    while ($categoria = $categorias->fetch_assoc()): ?>
                                        <option value="<?= $categoria['id'] ?>"
                                            <?= $categoria['id'] == $orden_compra['categoria_id'] ? 'selected' : '' ?>
                                            data-es-subcontrato="<?= in_array($categoria['id'], [2, 5]) ? '1' : '0' ?>">
                                            <?= htmlspecialchars($categoria['nombre']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="oc-form-label">Proveedor <span class="text-danger">*</span></label>
                                <select class="form-select" name="proveedor_id" required>
                                    <option value="">Seleccionar proveedor</option>
                                    <?php while ($proveedor = $proveedores->fetch_assoc()): ?>
                                        <option value="<?= $proveedor['id'] ?>"
                                            <?= $proveedor['id'] == $orden_compra['proveedor_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($proveedor['nombre'] ?: $proveedor['razon_social']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="oc-form-label">Proyecto</label>
                                <select class="form-select" name="proyecto_id" id="proyecto_id">
                                    <option value="">Sin proyecto</option>
                                    <?php while ($proyecto = $proyectos->fetch_assoc()): ?>
                                        <option value="<?= $proyecto['id'] ?>"
                                            <?= $proyecto['id'] == $orden_compra['proyecto_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="oc-form-label">Obra</label>
                                <select class="form-select" name="obra_id" id="obra_id">
                                    <option value="">Sin obra</option>
                                    <?php while ($obra = $obras->fetch_assoc()): ?>
                                        <option value="<?= $obra['id'] ?>"
                                            data-proyecto="<?= $obra['proyecto_id'] ?>"
                                            <?= $obra['id'] == $orden_compra['obra_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($obra['nombre_obra']) ?> (<?= htmlspecialchars($obra['nombre_proyecto']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="oc-form-label">Subcontrato <span class="text-muted" id="subcontrato_requerido_edit"></span></label>
                            <select class="form-select" name="subcontrato_id" id="subcontrato_id_edit" <?= !$subcontrato_id ? 'disabled' : '' ?>>
                                <option value="">-- Seleccionar subcontrato --</option>
                                <?php if ($subcontrato_id): ?>
                                    <option value="<?= $subcontrato_id ?>" selected><?= htmlspecialchars($subcontrato_nombre) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Items ── -->
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-list"></i> Items de la Orden de Compra</span>
                    </div>
                    <div class="oc-card-body oc-card-body--items">

                        <div class="orders-table-wrap orders-table-wrap--items mt-2">
                            <table class="orders-table orders-table--items" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 30%">Descripción</th>
                                        <th style="width: 10%">Cantidad</th>
                                        <th style="width: 12%">Unidad</th>
                                        <th style="width: 15%">Concepto</th>
                                        <th style="width: 14%">Precio Unit.</th>
                                        <th style="width: 15%">Subtotal</th>
                                        <th style="width: 10%" class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <?php
                                    $item_index = 0;
                                    $items->data_seek(0);
                                    while ($item = $items->fetch_assoc()):
                                    ?>
                                        <tr class="item-row" data-index="<?= $item_index ?>">
                                            <td><?= $item_index + 1 ?></td>
                                            <td>
                                                <input type="hidden"
                                                    name="items[<?= $item_index ?>][producto_id]"
                                                    value="<?= htmlspecialchars($item['producto_id'] ?? '') ?>">
                                                <input type="hidden"
                                                    name="items[<?= $item_index ?>][tipo]"
                                                    value="<?= htmlspecialchars($item['tipo'] ?? 'producto') ?>">
                                                <input type="text"
                                                    class="form-control item-descripcion"
                                                    name="items[<?= $item_index ?>][descripcion]"
                                                    value="<?= htmlspecialchars($item['descripcion']) ?>"
                                                    placeholder="Descripción del artículo"
                                                    required>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    class="form-control item-cantidad"
                                                    name="items[<?= $item_index ?>][cantidad]"
                                                    value="<?= htmlspecialchars($item['cantidad']) ?>"
                                                    step="0.001" min="0.001"
                                                    required>
                                            </td>
                                            <td>
                                                <select class="form-select item-unidad"
                                                    name="items[<?= $item_index ?>][unidad_id]">
                                                    <option value="">— Unidad —</option>
                                                    <?php
                                                    $unidades->data_seek(0);
                                                    while ($unidad = $unidades->fetch_assoc()):
                                                    ?>
                                                        <option value="<?= $unidad['id'] ?>"
                                                            <?= $unidad['id'] == $item['unidad_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($unidad['nombre']) ?>
                                                        </option>
                                                    <?php
                                                    endwhile; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select item-concepto"
                                                    name="items[<?= $item_index ?>][concepto_id]">
                                                    <option value="">— Concepto —</option>
                                                    <?php
                                                    $conceptos->data_seek(0);
                                                    while ($concepto = $conceptos->fetch_assoc()):
                                                    ?>
                                                        <option value="<?= $concepto['id'] ?>"
                                                            <?= (isset($item['concepto_id']) && $item['concepto_id'] == $concepto['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                                        </option>
                                                    <?php
                                                    endwhile; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    class="form-control item-precio"
                                                    name="items[<?= $item_index ?>][precio_unitario]"
                                                    value="<?= htmlspecialchars($item['precio_unitario']) ?>"
                                                    step="0.01" min="0"
                                                    required>
                                            </td>
                                            <td>
                                                <input type="text"
                                                    class="form-control item-subtotal"
                                                    value="$<?= number_format($item['subtotal'], 2) ?>"
                                                    readonly>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn-action btn-action--delete remove-item-btn btn-remove-item" title="Eliminar ítem">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php
                                        $item_index++;
                                    endwhile;
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-3 mb-4">
                            <div class="col-md-12 d-flex gap-2 flex-wrap">
                                <button type="button" class="btn-geco-primary btn-add-item" id="btnAgregarItem">
                                    <i class="fa-solid fa-circle-plus"></i> Agregar Item Vacío
                                </button>
                                <button type="button" class="btn-geco-secondary" id="btnCatalogo">
                                    <i class="fa-solid fa-table-cells"></i> Agregar desde Catálogo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>





                <div class="oc-form-layout">
                    <div class="oc-form-layout-main">
                        <!-- ── Descripción y Observaciones ── -->
                        <div class="oc-card mb-4">
                            <div class="oc-card-header">
                                <span class="oc-card-header__title"><i class="fa-regular fa-file-lines"></i> Descripción y Observaciones</span>
                            </div>
                            <div class="oc-card-body">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="oc-form-label">Descripción</label>
                                        <textarea class="form-control" name="descripcion" rows="4" placeholder="Descripción general de la orden de compra..."><?= htmlspecialchars($orden_compra['descripcion'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="oc-form-label">Observaciones</label>
                                        <textarea class="form-control" name="observaciones" rows="4" placeholder="Observaciones adicionales..."><?= htmlspecialchars($orden_compra['observaciones'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="oc-form-layout-side">
                        <!-- ── Archivos Adjuntos ── -->
                        <div class="oc-card mb-4">
                            <div class="oc-card-header">
                                <span class="oc-card-header__title"><i class="fa-solid fa-paperclip"></i> Archivos Adjuntos</span>
                            </div>
                            <div class="oc-card-body">
                                <div class="orders-alert orders-alert--info mb-3">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span>Puede gestionar los archivos existentes o subir nuevos documentos (Máx. 10MB).</span>
                                </div>

                                <h6 class="oc-form-label mb-2"><i class="fa-regular fa-folder-open"></i> Archivos Actuales</h6>
                                <div class="list-group list-group-flush mb-4" id="lista-archivos" style="border: 1px solid var(--gray-100,#e5e7eb); border-radius: 10px; overflow: hidden;">
                                    <?php if (count($archivos_array) > 0): ?>
                                        <?php foreach ($archivos_array as $archivo): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center archivo-item p-2" data-id="<?= $archivo['id'] ?>">
                                                <div class="text-truncate me-2" style="font-size: 0.85rem;">
                                                    <i class="fa-regular fa-file-lines text-primary me-2"></i>
                                                    <span title="<?= htmlspecialchars($archivo['nombre_archivo']) ?>"><?= htmlspecialchars($archivo['nombre_archivo']) ?></span>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <a href="<?= BASE_URL ?>/orders/download_archivo.php?id=<?= $archivo['id'] ?>&tipo=oc" class="btn btn-sm btn-outline-info p-1" style="line-height:1" target="_blank" title="Descargar">
                                                        <i class="fa-solid fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn-action btn-action--delete btn-eliminar-archivo p-1" style="height:28px; width:28px; line-height:1" data-archivo-id="<?= $archivo['id'] ?>" title="Eliminar">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center text-muted p-4">
                                            <i class="fa-solid fa-inbox fs-4 d-block mb-1 opacity-50"></i>
                                            <span class="small">Sin archivos adjuntos</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="oc-files-dropzone">
                                    <h6 class="oc-files-dropzone__title"><i class="fa-solid fa-cloud-arrow-up"></i> Subir Nuevos Documentos</h6>
                                    <div class="oc-files-input-group mb-2">
                                        <input type="file" class="form-control form-control-sm" id="nuevosArchivos" name="nuevos_archivos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
                                    </div>
                                    <div class="oc-form-hint" style="font-size: 11px;">
                                        <i class="fa-solid fa-circle-info"></i> PDF, Excel, Word o Imagen (Máx. 10MB c/u).
                                    </div>
                                </div>

                                <div class="mt-3" id="preview-nuevos-archivos" style="display:none;">
                                    <h6 class="oc-form-label small">Por subir:</h6>
                                    <div class="list-group list-group-flush" id="lista-nuevos-archivos"></div>
                                </div>
                            </div>
                        </div>

                        <div class="oc-finance">
                            <div class="oc-finance-title"><i class="fa-solid fa-calculator"></i> Resumen Financiero</div>
                            <div class="oc-finance-row">
                                <span>Subtotal:</span>
                                <span id="display-subtotal">$<?= number_format($orden_compra['subtotal'], 3) ?></span>
                            </div>
                            <div class="oc-finance-row text-white">
                                <span class="d-flex align-items-center gap-2">
                                    IVA:
                                    <select class="form-select form-select-sm oc-finance-iva-select text-white"
                                        name="iva_porcentaje"
                                        id="iva_porcentaje">
                                        <option value="0" <?= $iva_porcentaje == 0  ? 'selected' : '' ?>>0 %</option>
                                        <option value="8" <?= $iva_porcentaje == 8  ? 'selected' : '' ?>>8 %</option>
                                        <option value="16" <?= $iva_porcentaje == 16 ? 'selected' : '' ?>>16 %</option>
                                    </select>
                                </span>
                                <span id="display-iva">$<?= number_format($orden_compra['iva'], 3) ?></span>
                            </div>
                            <div class="oc-finance-total">
                                <span class="lbl">TOTAL:</span>
                                <span class="amt" id="display-total">$<?= number_format($orden_compra['total'], 2) ?></span>
                            </div>
                        </div>

                        <p class="oc-form-submit-note">
                            <i class="fa-solid fa-circle-info"></i>
                            Al guardar, esta orden será enviada nuevamente para su revisión.
                        </p>

                        <div class="oc-form-submit-actions">
                            <button type="button" id="btnGuardar" class="btn-geco-primary">
                                <i class="fa-solid fa-circle-check"></i> Guardar y Reenviar
                            </button>
                        </div>

                    </div> <!-- /side -->
                </div> <!-- /oc-form-layout -->

                <!-- Campo oculto archivos eliminados -->
                <input type="hidden" name="archivos_eliminados" id="archivos_eliminados" value="[]">



            </form>

        </div><!-- /orders-page-content -->
    </div><!-- /orders-page-container -->

    <!-- ══════════════════════════════════════════════════════════
     MODAL CATÁLOGO — fuera de todo contenedor para evitar
     stacking context que bloquea el backdrop de Bootstrap
     ══════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalCatalogo" tabindex="-1" aria-labelledby="modalCatalogoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background-color:var(--s-700, #113557); color:var(--white, #ffffff);">
                    <h5 class="modal-title" id="modalCatalogoLabel">
                        <i class="fa-solid fa-table-cells me-2"></i> Catálogo de Productos y Servicios
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Cerrar" style="filter:invert(1);"></button>
                </div>
                <div class="modal-header border-top-0 pt-0 pb-2 flex-column align-items-start gap-2">
                    <!-- Buscador -->
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text"
                            id="catalogoBuscador"
                            class="form-control"
                            placeholder="Buscar por nombre o proveedor..."
                            style="border-left:3px solid var(--s-700, #113557);">
                        <button class="btn btn-outline-secondary" type="button"
                            onclick="document.getElementById('catalogoBuscador').value=''; filtrarCatalogo();">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <!-- Filtros tipo -->
                    <div class="filtros-tipo d-flex gap-2 flex-wrap">
                        <input type="radio" class="btn-check" name="filtroCatTipo" id="filtroCatTodos" value="" checked>
                        <label class="btn btn-sm btn-outline-secondary" for="filtroCatTodos">
                            <i class="fa-solid fa-table-cells"></i> Todos
                        </label>
                        <input type="radio" class="btn-check" name="filtroCatTipo" id="filtroCatProducto" value="producto">
                        <label class="btn btn-sm btn-outline-secondary" for="filtroCatProducto">
                            <i class="fa-solid fa-box"></i> Productos
                        </label>
                        <input type="radio" class="btn-check" name="filtroCatTipo" id="filtroCatServicio" value="servicio">
                        <label class="btn btn-sm btn-outline-secondary" for="filtroCatServicio">
                            <i class="fa-solid fa-screwdriver-wrench"></i> Servicios
                        </label>
                        <span class="ms-auto text-muted small align-self-center" id="catalogoContador"></span>
                    </div>
                </div>
                <div class="modal-body py-2">
                    <div id="catalogoGrid" class="row g-2">
                        <!-- Cards generadas por JS -->
                    </div>
                    <div id="catalogoVacio" class="text-center text-muted py-5" style="display:none;">
                        <i class="fa-solid fa-inbox display-4"></i>
                        <p class="mt-2">No se encontraron productos con ese criterio.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-circle-xmark"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* ══════════════════════════════════════════════════════════════
   DATOS DESDE PHP  →  JS  (sin mezclar PHP dentro de funciones)
   ══════════════════════════════════════════════════════════════ */
        const UNIDADES = <?= json_encode($unidades_arr,   JSON_UNESCAPED_UNICODE) ?>;
        const CONCEPTOS = <?= json_encode($conceptos_arr,  JSON_UNESCAPED_UNICODE) ?>;
        const PRODUCTOS_CATALOGO = <?= json_encode($productos_arr, JSON_UNESCAPED_UNICODE) ?>;

        /* ══════════════════════════════════════════════════════════════
           ESTADO GLOBAL
           ══════════════════════════════════════════════════════════════ */
        let itemCount = <?= $item_index ?>; // ya existen N filas
        let archivosEliminados = []; // IDs a borrar

        /* ══════════════════════════════════════════════════════════════
           HELPERS: construir <options> desde arrays JS
           ══════════════════════════════════════════════════════════════ */
        function buildOptions(arr, selectedId = '') {
            return arr.map(item =>
                `<option value="${item.id}" ${String(item.id) === String(selectedId) ? 'selected' : ''}>${item.nombre}</option>`
            ).join('');
        }

        /* ══════════════════════════════════════════════════════════════
           CREAR FILA NUEVA
           ══════════════════════════════════════════════════════════════ */
        function buildItemRow(index, descripcion = '', productoId = '', tipo = '') {
            const unidadOpts = `<option value="">— Unidad —</option>` + buildOptions(UNIDADES);
            const conceptoOpts = `<option value="">— Concepto —</option>` + buildOptions(CONCEPTOS);
            const descEsc = descripcion.replace(/"/g, '&quot;');

            return `
    <tr class="item-row item-row-new" data-index="${index}">
        <td>${index + 1}</td>
        <td>
            <input type="hidden" name="items[${index}][producto_id]" value="${productoId}">
            <input type="hidden" name="items[${index}][tipo]"        value="${tipo}">
            <input type="text"
                   class="form-control item-descripcion"
                   name="items[${index}][descripcion]"
                   value="${descEsc}"
                   placeholder="Descripción del artículo"
                   required>
        </td>
        <td>
            <input type="number"
                   class="form-control item-cantidad"
                   name="items[${index}][cantidad]"
                   value="1"
                   step="0.001" min="0.001"
                   required>
        </td>
        <td>
            <select class="form-select item-unidad" name="items[${index}][unidad_id]">
                ${unidadOpts}
            </select>
        </td>
        <td>
            <select class="form-select item-concepto" name="items[${index}][concepto_id]">
                ${conceptoOpts}
            </select>
        </td>
        <td>
            <input type="number"
                   class="form-control item-precio"
                   name="items[${index}][precio_unitario]"
                   value="0"
                   step="0.01" min="0"
                   required>
        </td>
        <td>
            <input type="text"
                   class="form-control item-subtotal"
                   value="$0.00"
                   readonly>
        </td>
        <td class="text-center">
            <button type="button" class="btn-action btn-action--delete remove-item-btn btn-remove-item" title="Eliminar ítem">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    </tr>`;
        }

        /* ══════════════════════════════════════════════════════════════
           AGREGAR ITEM VACÍO
           ══════════════════════════════════════════════════════════════ */
        document.getElementById('btnAgregarItem').addEventListener('click', function() {
            insertarFilaVacia();
        });

        function insertarFilaVacia(descripcion = '', productoId = '', tipo = '') {
            const tbody = document.getElementById('itemsTableBody');

            // Si había el placeholder "sin items", borrarlo
            const placeholder = tbody.querySelector('td[colspan]');
            if (placeholder) placeholder.closest('tr').remove();

            tbody.insertAdjacentHTML('beforeend', buildItemRow(itemCount, descripcion, productoId, tipo));

            const newRow = tbody.lastElementChild;
            attachRowListeners(newRow);
            itemCount++;

            newRow.querySelector('.item-descripcion').focus();
            return newRow;
        }

        /* ══════════════════════════════════════════════════════════════
           MODAL CATÁLOGO
           ══════════════════════════════════════════════════════════════ */
        const modalCatalogo = new bootstrap.Modal(document.getElementById('modalCatalogo'));

        document.getElementById('btnCatalogo').addEventListener('click', function() {
            renderCatalogo(PRODUCTOS_CATALOGO);
            document.getElementById('catalogoBuscador').value = '';
            document.querySelectorAll('input[name="filtroCatTipo"]').forEach(r => {
                r.checked = r.value === '';
            });
            modalCatalogo.show();
        });

        function filtrarCatalogo() {
            const texto = document.getElementById('catalogoBuscador').value.toLowerCase().trim();
            const tipo = document.querySelector('input[name="filtroCatTipo"]:checked').value;

            const filtrados = PRODUCTOS_CATALOGO.filter(p => {
                const coincideTexto = !texto ||
                    p.nombre.toLowerCase().includes(texto) ||
                    p.proveedor_nombre.toLowerCase().includes(texto);
                const coincideTipo = !tipo || p.tipo === tipo;
                return coincideTexto && coincideTipo;
            });

            renderCatalogo(filtrados);
        }

        function renderCatalogo(lista) {
            const grid = document.getElementById('catalogoGrid');
            const vacio = document.getElementById('catalogoVacio');
            const contador = document.getElementById('catalogoContador');

            grid.innerHTML = '';
            contador.textContent = `${lista.length} resultado${lista.length !== 1 ? 's' : ''}`;

            if (lista.length === 0) {
                vacio.style.display = 'block';
                return;
            }
            vacio.style.display = 'none';

            lista.forEach(p => {
                const badgeColor = p.tipo === 'servicio' ? 'bg-info text-dark' : 'bg-primary';
                const badgeLabel = p.tipo === 'servicio' ? 'Servicio' : 'Producto';
                const iconTipo = p.tipo === 'servicio' ? 'fa-screwdriver-wrench' : 'fa-box';
                const precioStr = p.precio > 0 ?
                    '$' + parseFloat(p.precio).toLocaleString('es-MX', {
                        minimumFractionDigits: 2
                    }) :
                    '—';

                const col = document.createElement('div');
                col.className = 'col-md-6';
                col.innerHTML = `
            <div class="catalogo-card"
                 data-id="${p.id}"
                 data-nombre="${p.nombre.replace(/"/g, '&quot;')}"
                 data-tipo="${p.tipo}"
                 data-precio="${p.precio}"
                 tabindex="0"
                 title="Agregar: ${p.nombre.replace(/"/g, '&quot;')}">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <span class="nombre-producto">
                        <i class="fa-solid ${iconTipo} me-1 opacity-75"></i>${p.nombre}
                    </span>
                    <span class="badge ${badgeColor} badge-tipo ms-2 flex-shrink-0">${badgeLabel}</span>
                </div>
                ${p.proveedor_nombre ? `<div class="proveedor-producto"><i class="fa-solid fa-building me-1"></i>${p.proveedor_nombre}</div>` : ''}
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="precio-producto">${precioStr}</span>
                    <span class="text-muted small"><i class="fa-regular fa-hand-pointer me-1"></i>Click para agregar</span>
                </div>
            </div>`;
                grid.appendChild(col);
            });
        }

        // Click en card del catálogo — delegación desde document para evitar
        // problemas si el grid se vacía/recrea o el listener se registra antes del DOM
        document.addEventListener('click', function(e) {
            const card = e.target.closest('#catalogoGrid .catalogo-card');
            if (!card) return;
            e.stopPropagation();
            seleccionarProductoCatalogo(card);
        });

        // También con teclado (Enter / Space)
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const card = e.target.closest('#catalogoGrid .catalogo-card');
            if (card) {
                e.preventDefault();
                seleccionarProductoCatalogo(card);
            }
        });

        function seleccionarProductoCatalogo(card) {
            const productoId = card.dataset.id;
            const nombre = card.dataset.nombre;
            const tipo = card.dataset.tipo;
            const precio = parseFloat(card.dataset.precio) || 0;

            // Buscar primera fila vacía (descripción en blanco y precio 0)
            let filaDestino = null;
            document.querySelectorAll('#itemsTableBody .item-row').forEach(row => {
                if (filaDestino) return;
                const desc = row.querySelector('.item-descripcion').value.trim();
                const prec = parseFloat(row.querySelector('.item-precio').value) || 0;
                if (!desc && prec === 0) filaDestino = row;
            });

            if (!filaDestino) {
                // No hay fila vacía → crear una nueva
                filaDestino = insertarFilaVacia();
            }

            // Rellenar la fila
            filaDestino.querySelector('.item-descripcion').value = nombre;
            filaDestino.querySelector('.item-precio').value = precio > 0 ? precio : '';
            filaDestino.querySelector('input[name$="[producto_id]"]').value = productoId;
            filaDestino.querySelector('input[name$="[tipo]"]').value = tipo;

            // Disparar cálculo de subtotal
            filaDestino.querySelector('.item-cantidad').dispatchEvent(new Event('input'));

            // Animación de confirmación visual en la fila
            filaDestino.style.transition = 'background-color .4s';
            filaDestino.style.backgroundColor = '#d1f5d3';
            setTimeout(() => {
                filaDestino.style.backgroundColor = '';
            }, 800);

            // Toast de confirmación
            showToast(`"${nombre}" agregado a la lista`, 'success');

            modalCatalogo.hide();
        }

        /* ══════════════════════════════════════════════════════════════
           ELIMINAR ITEM  (delegación desde tbody)
           ══════════════════════════════════════════════════════════════ */
        document.getElementById('itemsTableBody').addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;

            const rows = document.querySelectorAll('#itemsTableBody .item-row');
            if (rows.length <= 1) {
                showAlert('Debe haber al menos un item en la orden de compra.', 'warning');
                return;
            }

            const row = btn.closest('.item-row');
            UI.confirm({
                title: '¿Eliminar item?',
                message: '¿Eliminar este item de la orden de compra?',
                danger: true,
                confirmText: 'Eliminar',
            }).then(ok => {
                if (ok) {
                    row.remove();
                    recalcularIndices();
                    calculateTotals();
                }
            });
        });

        /* ══════════════════════════════════════════════════════════════
           RECALCULAR ÍNDICES (name="items[N][...]") tras eliminación
           ══════════════════════════════════════════════════════════════ */
        function recalcularIndices() {
            document.querySelectorAll('#itemsTableBody .item-row').forEach((row, idx) => {
                row.dataset.index = idx;
                row.cells[0].textContent = idx + 1; // Actualizar número de fila
                row.querySelectorAll('[name]').forEach(el => {
                    el.name = el.name.replace(/items\[\d+\]/, `items[${idx}]`);
                });
            });
            itemCount = Math.max(
                itemCount,
                document.querySelectorAll('#itemsTableBody .item-row').length
            );
        }

        /* ══════════════════════════════════════════════════════════════
           EVENT LISTENERS POR FILA (cantidad / precio → subtotal)
           ══════════════════════════════════════════════════════════════ */
        function attachRowListeners(row) {
            const cantidadEl = row.querySelector('.item-cantidad');
            const precioEl = row.querySelector('.item-precio');
            const subtotalEl = row.querySelector('.item-subtotal');

            function updateSubtotal() {
                const cant = parseFloat(cantidadEl.value) || 0;
                const precio = parseFloat(precioEl.value) || 0;
                const sub = Math.round(cant * precio * 1000) / 1000;
                subtotalEl.value = '$' + sub.toLocaleString('es-MX', {
                    minimumFractionDigits: 3,
                    maximumFractionDigits: 3
                });
                calculateTotals();
            }

            cantidadEl.addEventListener('input', updateSubtotal);
            precioEl.addEventListener('input', updateSubtotal);
        }

        /* ══════════════════════════════════════════════════════════════
           CALCULAR TOTALES GLOBALES
           ══════════════════════════════════════════════════════════════ */
        function calculateTotals() {
            let subtotal = 0;
            document.querySelectorAll('#itemsTableBody .item-row').forEach(row => {
                const cant = parseFloat(row.querySelector('.item-cantidad').value) || 0;
                const precio = parseFloat(row.querySelector('.item-precio').value) || 0;
                subtotal += cant * precio;
            });

            subtotal = Math.round(subtotal * 1000) / 1000;

            const ivaPct = parseFloat(document.getElementById('iva_porcentaje').value) || 0;
            const iva = Math.round(subtotal * (ivaPct / 100) * 1000) / 1000;
            const total = Math.round((subtotal + iva) * 100) / 100;

            const fmt3 = n => '$' + n.toLocaleString('es-MX', {
                minimumFractionDigits: 3,
                maximumFractionDigits: 3
            });
            const fmt2 = n => '$' + n.toLocaleString('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('display-subtotal').textContent = fmt3(subtotal);
            document.getElementById('display-iva').textContent = fmt3(iva);
            document.getElementById('display-total').textContent = fmt2(total);
        }

        document.getElementById('iva_porcentaje').addEventListener('change', calculateTotals);

        /* ══════════════════════════════════════════════════════════════
           ARCHIVOS: MARCAR COMO ELIMINADO
           ══════════════════════════════════════════════════════════════ */
        document.getElementById('lista-archivos').addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-eliminar-archivo');
            if (!btn) return;

            const archivoId = parseInt(btn.dataset.archivoId, 10);
            const archivoItem = btn.closest('.archivo-item');

            if (btn.disabled) {
                // Deshacer eliminación
                archivosEliminados = archivosEliminados.filter(id => id !== archivoId);
                archivoItem.classList.remove('marcado-eliminar');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Eliminar';
                btn.classList.replace('btn-secondary', 'btn-danger');
            } else {
                // Marcar para eliminar
                if (!archivosEliminados.includes(archivoId)) {
                    archivosEliminados.push(archivoId);
                }
                archivoItem.classList.add('marcado-eliminar');
                btn.disabled = false; // dejarlo clickeable para deshacer
                btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Deshacer';
                btn.classList.replace('btn-danger', 'btn-secondary');
            }

            document.getElementById('archivos_eliminados').value = JSON.stringify(archivosEliminados);
        });

        /* ══════════════════════════════════════════════════════════════
           ARCHIVOS: VISTA PREVIA NUEVOS
           ══════════════════════════════════════════════════════════════ */
        document.getElementById('nuevosArchivos').addEventListener('change', function() {
            const files = this.files;
            const preview = document.getElementById('preview-nuevos-archivos');
            const lista = document.getElementById('lista-nuevos-archivos');
            lista.innerHTML = '';

            if (files.length === 0) {
                preview.style.display = 'none';
                return;
            }

            preview.style.display = 'block';
            Array.from(files).forEach(file => {
                const kb = (file.size / 1024).toFixed(2);
                lista.insertAdjacentHTML('beforeend', `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fa-regular fa-file-lines me-2 text-primary"></i>
                    <span>${file.name}</span>
                    <small class="file-size ms-2">(${kb} KB)</small>
                </div>
                ${file.size > 10 * 1024 * 1024
                    ? '<span class="badge bg-danger">Excede 10 MB</span>'
                    : '<span class="badge bg-success">OK</span>'}
            </div>`);
            });
        });

        function ocultarPreview() {
            document.getElementById('preview-nuevos-archivos').style.display = 'none';
            document.getElementById('lista-nuevos-archivos').innerHTML = '';
        }

        /* ══════════════════════════════════════════════════════════════
           FILTRAR OBRAS POR PROYECTO
           ══════════════════════════════════════════════════════════════ */
        document.getElementById('proyecto_id').addEventListener('change', function() {
            const pid = this.value;
            const obraSelect = document.getElementById('obra_id');

            Array.from(obraSelect.options).forEach(opt => {
                if (!opt.value) return;
                opt.style.display = (!pid || opt.dataset.proyecto === pid) ? '' : 'none';
            });

            // Resetear si la obra seleccionada no pertenece al proyecto
            const selOpt = obraSelect.options[obraSelect.selectedIndex];
            if (pid && selOpt.value && selOpt.dataset.proyecto !== pid) {
                obraSelect.value = '';
            }
        });

        /* ══════════════════════════════════════════════════════════════
           VALIDACIÓN + CONFIRMACIÓN AL ENVIAR
           ══════════════════════════════════════════════════════════════ */
        document.getElementById('btnGuardar').addEventListener('click', function() {

            // 1. Al menos un item
            if (document.querySelectorAll('#itemsTableBody .item-row').length === 0) {
                showAlert('Debe agregar al menos un item a la orden de compra.', 'warning');
                return;
            }

            // 2. Máximo 5 archivos nuevos
            const nuevos = document.getElementById('nuevosArchivos').files;
            if (nuevos.length > 5) {
                showAlert('Solo puede agregar máximo 5 archivos nuevos.', 'warning');
                return;
            }

            // 3. Tamaño máximo 10 MB por archivo
            for (const file of nuevos) {
                if (file.size > 10 * 1024 * 1024) {
                    showAlert(`El archivo "${file.name}" supera los 10 MB permitidos.`, 'danger');
                    return;
                }
            }

            // 4. Confirmación
            UI.confirm({
                title: '¿Guardar cambios y reenviar?',
                message: 'La orden de compra será enviada nuevamente para revisión.',
                confirmText: 'Sí, guardar y reenviar',
            }).then(ok => {
                if (ok) {
                    UI.loading('Guardando cambios...');
                    document.getElementById('input_actualizar_oc').value = '1';
                    document.getElementById('formEditarOC').submit();
                }
            });
        });

        function showToast(msg, type = 'info') {
            if (type === 'danger') type = 'error';
            UI.toast[type] ? UI.toast[type](msg) : UI.toast.info(msg);
        }

        function showAlert(msg, type = 'info') {
            return UI.modal({
                title: type === 'danger' ? 'Error' : type === 'warning' ? 'Atención' : 'Información',
                html: `<div class="p-3">${msg}</div>`
            });
        }

        function showConfirm(msg, title = '¿Confirmar acción?') {
            return UI.confirm({
                title: title,
                message: msg,
                danger: msg.toLowerCase().includes('eliminar')
            });
        }

        /* ══════════════════════════════════════════════════════════════
           INICIALIZACIÓN
           ══════════════════════════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', function() {
            // Adjuntar listeners a filas ya cargadas desde la BD
            document.querySelectorAll('#itemsTableBody .item-row').forEach(attachRowListeners);

            // Calcular totales iniciales
            calculateTotals();

            // Buscador del catálogo
            document.getElementById('catalogoBuscador').addEventListener('input', filtrarCatalogo);

            // Filtros de tipo del catálogo
            document.querySelectorAll('input[name="filtroCatTipo"]').forEach(r => {
                r.addEventListener('change', filtrarCatalogo);
            });
        });
    </script>

    <?php include __DIR__ . "/../includes/footer.php"; ?>

</body>

</html>