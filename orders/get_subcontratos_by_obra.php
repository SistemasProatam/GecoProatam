<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../conexion.php";

$obra_id = isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0;

if ($obra_id <= 0) {
    echo json_encode(['error' => 'ID de obra no válido']);
    exit;
}

$sql = "SELECT sc.id, sc.proveedor_id, p.nombre AS proveedor_nombre,
               sc.total_estimado, sc.anticipo_pct, sc.anticipo_monto,
               sc.descripcion,
               v.total_contrato,
               v.utilizado_pagado,
               v.comprometido_tentativo,
               v.disponible_subcontrato
        FROM subcontratos sc
        LEFT JOIN proveedores p ON p.id = sc.proveedor_id
        LEFT JOIN vista_presupuesto_maestro v ON v.subcontrato_id = sc.id
        WHERE sc.obra_id = ?
        ORDER BY p.nombre ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $obra_id);
$stmt->execute();
$result = $stmt->get_result();

$subcontratos = [];
while ($row = $result->fetch_assoc()) {
    $subcontratos[] = $row;
}

echo json_encode(['subcontratos' => $subcontratos]);
$conn->close();
?>

