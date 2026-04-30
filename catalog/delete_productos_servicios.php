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

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID no especificado']);
    exit;
}

try {
    $in_use = false;
    $message = '';
    
    // Verificar en requisicion_items
    $sql_check_requisiciones = "SELECT COUNT(*) as total FROM requisicion_items WHERE producto_id = ?";
    $stmt_check = $conn->prepare($sql_check_requisiciones);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $count_requisiciones = $result_check->fetch_assoc()['total'];
    
    if ($count_requisiciones > 0) {
        $in_use = true;
        $message = 'No se puede eliminar el producto/servicio porque está siendo utilizado en requisiciones';
    }
    
    // Si no está en uso, proceder con la eliminación
    if (!$in_use) {
        // Soft delete - marcar como inactivo
        $sql = "UPDATE productos_servicios SET activo = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Error al preparar consulta: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Producto/Servicio eliminado exitosamente'
            ]);
        } else {
            throw new Exception("Error al ejecutar: " . $stmt->error);
        }
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => $message
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al eliminar el producto/servicio: ' . $e->getMessage()
    ]);
}
?>

