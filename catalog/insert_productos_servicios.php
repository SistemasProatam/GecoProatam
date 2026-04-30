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
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$tipo = trim($_POST['tipo'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

// Validaciones
if (empty($nombre) || empty($tipo)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nombre y tipo son obligatorios']);
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
    // ✅ CORREGIDO: Solo 3 parámetros en lugar de 4
    $sql = "INSERT INTO productos_servicios (nombre, descripcion, tipo) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    // ✅ CORREGIDO: Solo 3 bind params "sss" en lugar de "ssis"
    $stmt->bind_param("sss", $nombre, $descripcion, $tipo);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => ucfirst($tipo) . ' creado exitosamente',
            'id' => $conn->insert_id
        ]);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al crear el ' . $tipo . ': ' . $e->getMessage()
    ]);
}

$conn->close();
?>

