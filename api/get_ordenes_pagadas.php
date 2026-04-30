<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
include(__DIR__ . "/../conexion.php");

$catalogo_id = isset($_GET['catalogo_id']) ? intval($_GET['catalogo_id']) : 0;
$busqueda = trim($_GET['busqueda'] ?? '');

if ($catalogo_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'catalogo_id inválido']);
    exit;
}

try {
    // Buscar órdenes pagadas relacionadas con el catálogo directamente (oc.catalogo_id)
    // o indirectamente a través de items que fueron asignados al catálogo en concepto_items
    $like = '%' . $busqueda . '%';

    $sql = "SELECT oc.id, oc.folio, oc.descripcion, oc.subtotal, oc.total, oc.fecha_pago, oc.fecha_solicitud, oc.proveedor_id, p.nombre AS proveedor_nombre
            FROM ordenes_compra oc
            LEFT JOIN proveedores p ON oc.proveedor_id = p.id
            WHERE oc.estado = 'pagado'
            AND (
                oc.catalogo_id = ?
                OR EXISTS (
                    SELECT 1 FROM orden_compra_items oci
                    JOIN concepto_items ci ON ci.orden_compra_item_id = oci.id
                    WHERE oci.orden_compra_id = oc.id AND ci.catalogo_id = ?
                )
            )";

    $params = [$catalogo_id, $catalogo_id];

    if ($busqueda !== '') {
        $sql .= " AND (oc.folio LIKE ? OR p.nombre LIKE ? OR oc.descripcion LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Error prepare: ' . $conn->error);

    // Build types string
    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : 's';
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $ordenes = [];
    while ($row = $res->fetch_assoc()) {
        // Formatear fecha de pago si existe
        $fecha_pago_formatted = $row['fecha_pago'] ? date('d/m/Y', strtotime($row['fecha_pago'])) : ( $row['fecha_solicitud'] ? date('d/m/Y', strtotime($row['fecha_solicitud'])) : null );

        // Obtener items
        $sql_items = "SELECT oci.id, oci.descripcion, oci.cantidad, oci.precio_unitario, oci.subtotal, oci.unidad_id, un.nombre AS unidad_medida, oci.concepto_id
                      FROM orden_compra_items oci
                      LEFT JOIN unidades un ON un.id = oci.unidad_id
                      WHERE oci.orden_compra_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param('i', $row['id']);
        $stmt_items->execute();
        $res_items = $stmt_items->get_result();
        $items = [];
        while ($it = $res_items->fetch_assoc()) {
            $items[] = $it;
        }

        $ordenes[] = [
            'id' => $row['id'],
            'folio' => $row['folio'],
            'descripcion' => $row['descripcion'],
            'subtotal' => $row['subtotal'],
            'total' => $row['total'],
            'fecha_pago_formatted' => $fecha_pago_formatted,
            'proveedor_nombre' => $row['proveedor_nombre'],
            'items' => $items
        ];
    }

    echo json_encode(['success' => true, 'ordenes' => $ordenes]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();


