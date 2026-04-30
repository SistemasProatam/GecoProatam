<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

header('Content-Type: application/json');

include(__DIR__ . "/../conexion.php");

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID inválido'
    ]);
    exit;
}

$sql = "SELECT * FROM productos_servicios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($producto = $result->fetch_assoc()) {
    echo json_encode([
        'status' => 'success',
        'data' => $producto
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Producto o servicio no encontrado'
    ]);
}


