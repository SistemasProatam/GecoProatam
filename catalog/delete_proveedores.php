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


$id = $_GET['id'] ?? 0;

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID no especificado']);
    exit;
}

try {
    // Verificar si el proveedor está en uso
    $sql_check = "SELECT COUNT(*) as total FROM productos_servicios WHERE proveedor_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $in_use = $result_check->fetch_assoc()['total'];
    
    if ($in_use > 0) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'No se puede eliminar el proveedor porque está siendo utilizado en productos/servicios'
        ]);
        exit;
    }
    
    $sql = "DELETE FROM proveedores WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Proveedor eliminado exitosamente']);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al eliminar el proveedor: ' . $e->getMessage()
    ]);
}
?>

