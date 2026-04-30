<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$id = $_GET['id'] ?? 0;

$sql = "SELECT * FROM entidades WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $entidad = $result->fetch_assoc();
    echo json_encode($entidad);
} else {
    echo json_encode(['error' => 'Entidad no encontrada']);
}
?>