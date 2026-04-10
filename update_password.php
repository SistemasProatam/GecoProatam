<?php
require_once __DIR__ . '/config.php';

// Incluir el gestor de sesiones para consistencia
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

// Verificar sesión
checkSession();
preventCaching();

include __DIR__ . "/conexion.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$user_id = SessionManager::get('user_id');
$new_pass = $_POST['new_password'] ?? '';
$confirm_pass = $_POST['confirm_password'] ?? '';

// Validación de campos vacíos
if (empty($new_pass) || empty($confirm_pass)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

// Validación de longitud (6-12 caracteres) - IMPORTANTE
if (strlen($new_pass) < 6) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'La contraseña debe tener al menos 6 caracteres.'
    ]);
    exit;
}

if (strlen($new_pass) > 12) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'La contraseña no puede tener más de 12 caracteres.'
    ]);
    exit;
}

// Validación de coincidencia
if ($new_pass !== $confirm_pass) {
    echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden.']);
    exit;
}

try {
    // Guardar hash de la nueva contraseña y limpiar temporal
    $hash = password_hash($new_pass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE usuarios SET password = ?, password_temporal = 0 WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("si", $hash, $user_id);

    if ($stmt->execute()) {
        // Limpiar flag de cambio de contraseña si existe
        SessionManager::remove('change_pass');
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión con tu nueva contraseña.'
        ]);
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al actualizar la contraseña: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

