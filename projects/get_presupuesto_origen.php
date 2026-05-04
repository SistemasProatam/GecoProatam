<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

function obtenerPresupuestoOrigen($proyecto_id, $obra_id = null) {
    global $conn;
    
    if ($obra_id) {
        // Si hay obra específica, usar su presupuesto
        $sql = "SELECT * FROM presupuesto_control 
                WHERE obra_id = ? AND proyecto_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $obra_id, $proyecto_id);
    } else {
        // Verificar si el proyecto tiene obras
        $sql_check_obras = "SELECT COUNT(*) as total_obras FROM obras WHERE proyecto_id = ?";
        $stmt_check = $conn->prepare($sql_check_obras);
        $stmt_check->bind_param("i", $proyecto_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_assoc();
        
        if ($row_check['total_obras'] > 0) {
            // Proyecto tiene obras, no se puede usar presupuesto directo del proyecto
            return ['error' => 'Este proyecto tiene obras. Selecciona una obra específica.'];
        } else {
            // Usar presupuesto del proyecto
            $sql = "SELECT * FROM presupuesto_control 
                    WHERE proyecto_id = ? AND obra_id IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $proyecto_id);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return ['error' => 'No se encontró presupuesto para el origen especificado'];
    }
}

// Uso en órdenes de compra
if (isset($_GET['proyecto_id'])) {
    $proyecto_id = $_GET['proyecto_id'];
    $obra_id = $_GET['obra_id'] ?? null;
    
    $presupuesto = obtenerPresupuestoOrigen($proyecto_id, $obra_id);
    echo json_encode($presupuesto);
}
?>


