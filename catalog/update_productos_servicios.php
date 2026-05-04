<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$id = $_POST['id'] ?? 0;
$tipo = trim($_POST['tipo'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

// Validaciones
if (empty($id) || empty($nombre) || empty($tipo)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID, nombre y tipo son obligatorios']);
    exit;
}

// Validar que el tipo sea válido
$tiposPermitidos = ['producto', 'servicio'];
if (!in_array(strtolower($tipo), $tiposPermitidos)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tipo no válido. Debe ser "producto" o "servicio"']);
    exit;
}

try {
    // ✅ CORREGIDO: Sin campo proveedor_id
    $sql = "UPDATE productos_servicios SET nombre = ?, descripcion = ?, tipo = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    // ✅ CORREGIDO: Solo 4 parámetros "sssi" en lugar de "ssisi"
    $stmt->bind_param("sssi", $nombre, $descripcion, $tipo, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'status' => 'success', 
                'message' => ucfirst($tipo) . ' actualizado exitosamente'
            ]);
        } else {
            echo json_encode([
                'status' => 'warning', 
                'message' => 'No se realizaron cambios en el ' . $tipo
            ]);
        }
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al actualizar el ' . $tipo . ': ' . $e->getMessage()
    ]);
}

$conn->close();
?>


