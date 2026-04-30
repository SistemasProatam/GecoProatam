<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php"); 

$archivo_id = $_GET['id'] ?? 0;

if (empty($archivo_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID no especificado']);
    exit;
}

try {
    // Obtener información del archivo
    $sql = "SELECT ruta_archivo FROM proyecto_adjuntos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $archivo_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Eliminar archivo físico
        if (file_exists($row['ruta_archivo'])) {
            if (!unlink($row['ruta_archivo'])) {
                throw new Exception('No se pudo eliminar el archivo físico');
            }
        }
        
        // Eliminar registro de la base de datos
        $sqlDelete = "DELETE FROM proyecto_adjuntos WHERE id = ?";
        $stmtDelete = $conn->prepare($sqlDelete);
        
        if (!$stmtDelete) {
            throw new Exception('Error al preparar la consulta de eliminación: ' . $conn->error);
        }
        
        $stmtDelete->bind_param("i", $archivo_id);
        
        if ($stmtDelete->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Archivo eliminado correctamente'
            ]);
        } else {
            throw new Exception('Error al eliminar de la base de datos: ' . $stmtDelete->error);
        }
    } else {
        throw new Exception('Archivo no encontrado en la base de datos');
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en delete_archivo.php: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}

// Cerrar conexiones
if (isset($stmt)) $stmt->close();
if (isset($stmtDelete)) $stmtDelete->close();
if (isset($conn)) $conn->close();
?>