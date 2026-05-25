<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM proyectos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $proyecto = $stmt->get_result()->fetch_assoc();

    if (!$proyecto) {
        echo json_encode(['error' => 'Proyecto no encontrado']);
        exit;
    }

    echo json_encode($proyecto);
} else {
    $result = $conn->query("SELECT id, nombre_proyecto FROM proyectos ORDER BY nombre_proyecto");
    $proyectos = [];
    while ($row = $result->fetch_assoc()) {
        $proyectos[] = $row;
    }
    echo json_encode($proyectos);
}
?>


