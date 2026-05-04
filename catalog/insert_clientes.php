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

$nombre = $_POST['nombre'] ?? '';
$nombre_abreviado = $_POST['nombre_abreviado'] ?? '';
$email = $_POST['email'] ?? '';
$rfc = $_POST['rfc'] ?? '';
$direccion = $_POST['direccion'] ?? '';

if (empty($nombre)) {
    echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio']);
    exit;
}

try {
    $sql = "INSERT INTO clientes (nombre, nombre_abreviado, email, rfc, direccion) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }

    $stmt->bind_param("sssss", $nombre, $nombre_abreviado, $email, $rfc, $direccion);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Cliente creado exitosamente',
            'id' => $conn->insert_id
        ]);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al crear el cliente: ' . $e->getMessage()
    ]);
}
