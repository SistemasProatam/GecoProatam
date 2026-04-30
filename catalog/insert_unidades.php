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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$nombre = $_POST['nombre'] ?? '';

if (empty($nombre)) {
    echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio']);
    exit;
}

try {
    $sql = "INSERT INTO unidades (nombre) VALUES (?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $nombre);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Unidad creada exitosamente',
            'id' => $conn->insert_id
        ]);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al crear la unidad: ' . $e->getMessage()
    ]);
}
?>

