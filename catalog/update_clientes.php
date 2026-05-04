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
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$id = $_POST['id'] ?? 0;
$nombre = $_POST['nombre'] ?? '';
$nombre_abreviado = $_POST['nombre_abreviado'] ?? '';
$rfc = $_POST['rfc'] ?? '';
$direccion = $_POST['direccion'] ?? '';

if (empty($id) || empty($nombre)) {
    echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio']);
    exit;
}

try {
    $sql = "UPDATE clientes SET nombre = ?, nombre_abreviado = ?, rfc = ?, direccion = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("ssssi", $nombre, $nombre_abreviado, $rfc, $direccion, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Cliente actualizado exitosamente']);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al actualizar el cliente: ' . $e->getMessage()
    ]);
}
?>


