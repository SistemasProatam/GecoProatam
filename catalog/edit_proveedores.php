<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID inválido'
    ]);
    exit;
}

$sql = "SELECT * FROM proveedores WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al preparar la consulta'
    ]);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        'status' => 'success',
        'data' => $result->fetch_assoc()
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Proveedor no encontrado'
    ]);
}


