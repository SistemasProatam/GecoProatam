<?php
require_once __DIR__ . '/../config.php';

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

$id = $_POST['id'] ?? 0;
$nombre = $_POST['nombre'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';

if (empty($id) || empty($nombre)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

try {
    $sql = "UPDATE entidades 
        SET nombre = ?, descripcion = ? 
        WHERE id = ?";

        $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }

    $stmt->bind_param("ssi", $nombre, $descripcion, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Entidad actualizada exitosamente']);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al actualizar la entidad: ' . $e->getMessage()
    ]);
}
?>

