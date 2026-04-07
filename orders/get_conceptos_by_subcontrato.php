<?php
// get_conceptos_by_subcontrato.php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../conexion.php";

header('Content-Type: application/json');

$subcontrato_id = isset($_GET['subcontrato_id']) ? (int)$_GET['subcontrato_id'] : 0;
$catalogo_id = isset($_GET['catalogo_id']) ? (int)$_GET['catalogo_id'] : 0;

if ($subcontrato_id <= 0) {
    echo json_encode(['error' => 'ID de subcontrato no válido']);
    exit;
}

// Obtener conceptos asignados a este subcontrato con el mismo formato que get_conceptos_by_catalogo
$sql = "SELECT c.id, c.codigo_concepto, c.numero_original, c.nombre_concepto, 
               c.unidad_medida, c.categoria, c.subcategoria
        FROM conceptos c
        INNER JOIN subcontrato_conceptos scc ON scc.concepto_id = c.id
        WHERE scc.subcontrato_id = ?";

if ($catalogo_id > 0) {
    $sql .= " AND c.catalogo_id = ?";
}

// Mismo orden que en get_conceptos_by_catalogo.php
$sql .= " ORDER BY 
          CASE 
            WHEN c.categoria IS NULL THEN 1
            ELSE 0 
          END,
          c.categoria DESC,
          CASE 
            WHEN c.subcategoria IS NULL THEN 1
            ELSE 0 
          END,
          c.subcategoria ASC,
          CAST(c.numero_original AS UNSIGNED) ASC,
          c.codigo_concepto DESC";

$stmt = $conn->prepare($sql);
if ($catalogo_id > 0) {
    $stmt->bind_param("ii", $subcontrato_id, $catalogo_id);
} else {
    $stmt->bind_param("i", $subcontrato_id);
}
$stmt->execute();
$result = $stmt->get_result();

$conceptos = [];
while ($row = $result->fetch_assoc()) {
    $conceptos[] = $row;
}

echo json_encode($conceptos);
$conn->close();
?>