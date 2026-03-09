<?php
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
$razon_social = $_POST['razon_social'] ?? '';
$nombre = $_POST['nombre'] ?? '';
$rfc = $_POST['rfc'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$email = $_POST['email'] ?? '';
$direccion = $_POST['direccion'] ?? '';
$contacto = $_POST['contacto'] ?? '';

if (empty($id) || empty($razon_social)) {
    echo json_encode(['status' => 'error', 'message' => 'La razón social es obligatoria']);
    exit;
}

try {
    $sql = "UPDATE proveedores SET razon_social = ?, nombre = ?, rfc = ?, telefono = ?, 
            email = ?, direccion = ?, contacto = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    $stmt->bind_param("sssssssi", $razon_social, $nombre, $rfc, $telefono, $email, $direccion, $contacto, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Proveedor actualizado exitosamente']);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al actualizar el proveedor: ' . $e->getMessage()
    ]);
}
?>