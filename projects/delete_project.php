<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$proyecto_id = $_GET['id'] ?? 0;

if ($proyecto_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID de proyecto invÃ¡lido']);
    exit;
}

// Verificar si el proyecto existe y obtener informaciÃ³n
$sql_info = "SELECT p.id, p.nombre_proyecto,
             (SELECT COUNT(*) FROM obras WHERE proyecto_id = p.id) as total_obras,
             (SELECT COUNT(*) FROM ordenes_compra WHERE proyecto_id = p.id) as ordenes_asociadas
             FROM proyectos p WHERE p.id = ?";
$stmt_info = $conn->prepare($sql_info);
$stmt_info->bind_param("i", $proyecto_id);
$stmt_info->execute();
$proyecto_info = $stmt_info->get_result()->fetch_assoc();

if (!$proyecto_info) {
    echo json_encode(['status' => 'error', 'message' => 'Proyecto no encontrado']);
    exit;
}

// Verificar si hay Ã³rdenes de compra asociadas
if ($proyecto_info['ordenes_asociadas'] > 0) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'No se puede eliminar el proyecto porque tiene ' . $proyecto_info['ordenes_asociadas'] . ' orden(es) de compra asociada(s). Primero elimine o reasigne las Ã³rdenes de compra.'
    ]);
    exit;
}

// Iniciar transacciÃ³n
$conn->begin_transaction();

try {
    // Eliminar archivos adjuntos del proyecto
    $sql_archivos = "DELETE FROM proyecto_adjuntos WHERE proyecto_id = ?";
    $stmt_archivos = $conn->prepare($sql_archivos);
    $stmt_archivos->bind_param("i", $proyecto_id);
    $stmt_archivos->execute();

    // Eliminar presupuesto_control de obras
    $sql_presupuesto_obras = "DELETE FROM presupuesto_control WHERE proyecto_id = ? AND obra_id IS NOT NULL";
    $stmt_presupuesto_obras = $conn->prepare($sql_presupuesto_obras);
    $stmt_presupuesto_obras->bind_param("i", $proyecto_id);
    $stmt_presupuesto_obras->execute();

    // Eliminar presupuesto_control del proyecto
    $sql_presupuesto_proyecto = "DELETE FROM presupuesto_control WHERE proyecto_id = ? AND obra_id IS NULL";
    $stmt_presupuesto_proyecto = $conn->prepare($sql_presupuesto_proyecto);
    $stmt_presupuesto_proyecto->bind_param("i", $proyecto_id);
    $stmt_presupuesto_proyecto->execute();

    // Eliminar historial de obras
    $sql_historial_obras = "DELETE oh FROM obra_historial oh 
                           INNER JOIN obras o ON oh.obra_id = o.id 
                           WHERE o.proyecto_id = ?";
    $stmt_historial_obras = $conn->prepare($sql_historial_obras);
    $stmt_historial_obras->bind_param("i", $proyecto_id);
    $stmt_historial_obras->execute();

    // Eliminar obras
    $sql_obras = "DELETE FROM obras WHERE proyecto_id = ?";
    $stmt_obras = $conn->prepare($sql_obras);
    $stmt_obras->bind_param("i", $proyecto_id);
    $stmt_obras->execute();

    // Eliminar historial del proyecto
    $sql_historial = "DELETE FROM proyecto_historial WHERE proyecto_id = ?";
    $stmt_historial = $conn->prepare($sql_historial);
    $stmt_historial->bind_param("i", $proyecto_id);
    $stmt_historial->execute();

    // Eliminar el proyecto
    $sql_proyecto = "DELETE FROM proyectos WHERE id = ?";
    $stmt_proyecto = $conn->prepare($sql_proyecto);
    $stmt_proyecto->bind_param("i", $proyecto_id);
    $stmt_proyecto->execute();

    if ($stmt_proyecto->affected_rows > 0) {
        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Proyecto "' . $proyecto_info['nombre_proyecto'] . '" y sus ' . $proyecto_info['total_obras'] . ' obra(s) eliminados correctamente'
        ]);
    } else {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el proyecto']);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el proyecto: ' . $e->getMessage()]);
}
?>


