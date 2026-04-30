<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$proyecto_id = $_GET['id'] ?? 0;

if ($proyecto_id <= 0) {
    echo json_encode(['error' => 'ID de proyecto inválido']);
    exit;
}

$sql = "SELECT id, nombre_proyecto, monto_designado, costo_directo 
        FROM proyectos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $proyecto_id);
$stmt->execute();
$result = $stmt->get_result();
$proyecto = $result->fetch_assoc();

if (!$proyecto) {
    echo json_encode(['error' => 'Proyecto no encontrado']);
    exit;
}

echo json_encode($proyecto);
?>