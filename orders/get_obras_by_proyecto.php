<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../conexion.php";

checkSession();
header('Content-Type: application/json');

$proyecto_id = $_GET['proyecto_id'] ?? 0;

if ($proyecto_id <= 0) {
    echo json_encode(['error' => 'ID de proyecto inválido']);
    exit;
}

$sql = "SELECT id, nombre_obra FROM obras WHERE proyecto_id = ? ORDER BY nombre_obra ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $proyecto_id);
$stmt->execute();
$result = $stmt->get_result();

$obras = [];
while ($row = $result->fetch_assoc()) {
    $obras[] = $row;
}

echo json_encode($obras);
$conn->close();
?>

