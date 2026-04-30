<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../conexion.php";

checkSession();
header('Content-Type: application/json');

$obra_id = $_GET['obra_id'] ?? 0;

if ($obra_id <= 0) {
    echo json_encode(['error' => 'ID de obra inválido']);
    exit;
}

$sql = "SELECT id, nombre_catalogo FROM catalogos WHERE obra_id = ? ORDER BY nombre_catalogo ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $obra_id);
$stmt->execute();
$result = $stmt->get_result();

$catalogos = [];
while ($row = $result->fetch_assoc()) {
    $catalogos[] = $row;
}

echo json_encode($catalogos);
$conn->close();
?>

