<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$obra_id = $_GET['id'] ?? 0;

if ($obra_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID de obra invÃ¡lido']);
    exit;
}

// Verificar si la obra existe y obtener informaciÃ³n
$sql_info = "SELECT o.id, o.proyecto_id, o.nombre_obra,
             (SELECT COUNT(*) FROM ordenes_compra WHERE obra_id = o.id) as ordenes_asociadas
             FROM obras o WHERE o.id = ?";
$stmt_info = $conn->prepare($sql_info);
$stmt_info->bind_param("i", $obra_id);
$stmt_info->execute();
$obra_info = $stmt_info->get_result()->fetch_assoc();

if (!$obra_info) {
    echo json_encode(['status' => 'error', 'message' => 'Obra no encontrada']);
    exit;
}

// Verificar si hay Ã³rdenes de compra asociadas
if ($obra_info['ordenes_asociadas'] > 0) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'No se puede eliminar la obra porque tiene ' . $obra_info['ordenes_asociadas'] . ' orden(es) de compra asociada(s). Primero elimine o reasigne las Ã³rdenes de compra.'
    ]);
    exit;
}

// Iniciar transacciÃ³n
$conn->begin_transaction();

try {
    // Eliminar registro de presupuesto_control
    $sql_presupuesto = "DELETE FROM presupuesto_control WHERE obra_id = ?";
    $stmt_presupuesto = $conn->prepare($sql_presupuesto);
    $stmt_presupuesto->bind_param("i", $obra_id);
    $stmt_presupuesto->execute();

    // Eliminar historial de la obra
    $sql_historial = "DELETE FROM obra_historial WHERE obra_id = ?";
    $stmt_historial = $conn->prepare($sql_historial);
    $stmt_historial->bind_param("i", $obra_id);
    $stmt_historial->execute();

    // Eliminar la obra
    $sql_obra = "DELETE FROM obras WHERE id = ?";
    $stmt_obra = $conn->prepare($sql_obra);
    $stmt_obra->bind_param("i", $obra_id);
    $stmt_obra->execute();

    if ($stmt_obra->affected_rows > 0) {
        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Obra "' . $obra_info['nombre_obra'] . '" eliminada correctamente'
        ]);
    } else {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la obra']);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar la obra: ' . $e->getMessage()]);
}
?>


