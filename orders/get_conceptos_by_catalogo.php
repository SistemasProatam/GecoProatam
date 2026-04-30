<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../conexion.php";

checkSession();
header('Content-Type: application/json');

$catalogo_id = $_GET['catalogo_id'] ?? 0;

if ($catalogo_id <= 0) {
    echo json_encode(['error' => 'ID de catálogo inválido']);
    exit;
}

$sql = "SELECT id, codigo_concepto, numero_original FROM conceptos 
        WHERE catalogo_id = ? 
        ORDER BY 
          CASE 
            WHEN categoria IS NULL THEN 1
            ELSE 0 
          END,
          categoria DESC,
          CASE 
            WHEN subcategoria IS NULL THEN 1
            ELSE 0 
          END,
          subcategoria ASC,
          CAST(numero_original AS UNSIGNED) ASC,
          codigo_concepto DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $catalogo_id);
$stmt->execute();
$result = $stmt->get_result();

$conceptos = [];
while ($row = $result->fetch_assoc()) {
    $conceptos[] = $row;
}

echo json_encode($conceptos);
$conn->close();
?>

