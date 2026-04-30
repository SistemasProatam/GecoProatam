<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
// get_cotizacion.php — Devuelve datos de una cotización en JSON
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

header('Content-Type: application/json; charset=UTF-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

include_once __DIR__ . '/../conexion.php';

$stmt = $conn->prepare("
    SELECT c.id, c.folio, c.fecha_emision, c.atencion, c.compania, c.lugar,
           c.subtotal, c.tasa_iva, c.iva, c.total, c.moneda,
           c.vigencia, c.tiempo_ejecucion, c.forma_pago, c.notas,
           c.emisor_nombre, c.emisor_depto,
           e.nombre AS entidad
    FROM cotizaciones c
    LEFT JOIN entidades e ON c.entidades_id = e.id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
    exit;
}

// Formatear fecha para input type="date"
$row['fecha_emision'] = $row['fecha_emision']
    ? date('Y-m-d', strtotime($row['fecha_emision']))
    : '';

echo json_encode(['success' => true, 'cotizacion' => $row]);

