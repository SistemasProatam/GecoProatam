<?php
/**
 * API para obtener items de un concepto desde orden_compra_items
 * Items mostrados son aquellos cuya orden tiene estado 'pagado'
 */

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

header('Content-Type: application/json');

// Verificar sesiÃ³n
try {
    checkSession();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . "/../conexion.php";

$concepto_id = isset($_GET['concepto_id']) ? (int)$_GET['concepto_id'] : 0;

if ($concepto_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Concepto ID invÃ¡lido', 'items' => []]);
    exit;
}

// Verificar que las tablas existan
$sql_check_orden_items = "SHOW TABLES LIKE 'orden_compra_items'";
$result_check = $conn->query($sql_check_orden_items);

if (!$result_check || $result_check->num_rows === 0) {
    // Si no existe la tabla, retornar items vacÃ­os
    echo json_encode(['success' => true, 'message' => 'Tabla orden_compra_items no existe', 'items' => []]);
    exit;
}

// Obtener items del concepto desde orden_compra_items
// Filtramos solo los items cuya orden tiene estado 'pagado'
$sql_items = "SELECT 
    oci.id,
    oci.orden_compra_id,
    oci.descripcion,
    oci.cantidad,
    oci.unidad_id,
    u.nombre as unidad_medida,
    oci.precio_unitario,
    oci.subtotal,
    oci.fecha_creacion,
    oc.folio as folio_oc,
    oc.estado
FROM orden_compra_items oci
LEFT JOIN unidades u ON oci.unidad_id = u.id
JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
WHERE oci.concepto_id = ? AND oc.estado = 'pagado'
ORDER BY oc.folio DESC, oci.fecha_creacion DESC";

$stmt = $conn->prepare($sql_items);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar consulta: ' . $conn->error, 'items' => []]);
    exit;
}

$stmt->bind_param("i", $concepto_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Error al ejecutar consulta: ' . $stmt->error, 'items' => []]);
    exit;
}

$result = $stmt->get_result();
$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['id'],
        'orden_compra_id' => $row['orden_compra_id'],
        'descripcion' => $row['descripcion'],
        'cantidad' => $row['cantidad'],
        'unidad_medida' => $row['unidad_medida'] ?: 'N/A',
        'precio_unitario' => $row['precio_unitario'],
        'subtotal' => $row['subtotal'],
        'fecha_creacion' => $row['fecha_creacion'],
        'folio_oc' => $row['folio_oc'],
        'estado' => $row['estado']
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'items' => $items]);
?>



