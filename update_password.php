<?php
// Incluir el gestor de sesiones para consistencia
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

// Verificar sesiÃ³n
checkSession();
preventCaching();

require_once __DIR__ . "/conexion.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'MÃ©todo no permitido']);
    exit;
}

$user_id = SessionManager::get('user_id');
$new_pass = $_POST['new_password'] ?? '';
$confirm_pass = $_POST['confirm_password'] ?? '';

// ValidaciÃ³n de campos vacÃ­os
if (empty($new_pass) || empty($confirm_pass)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

// ValidaciÃ³n de longitud (6-12 caracteres) - IMPORTANTE
if (strlen($new_pass) < 6) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'La contraseÃ±a debe tener al menos 6 caracteres.'
    ]);
    exit;
}

if (strlen($new_pass) > 12) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'La contraseÃ±a no puede tener mÃ¡s de 12 caracteres.'
    ]);
    exit;
}

// ValidaciÃ³n de coincidencia
if ($new_pass !== $confirm_pass) {
    echo json_encode(['status' => 'error', 'message' => 'Las contraseÃ±as no coinciden.']);
    exit;
}

try {
    // Guardar hash de la nueva contraseÃ±a y limpiar temporal
    $hash = password_hash($new_pass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE usuarios SET password = ?, password_temporal = 0 WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("si", $hash, $user_id);

    if ($stmt->execute()) {
        // Limpiar flag de cambio de contraseÃ±a si existe
        SessionManager::remove('change_pass');
        
        echo json_encode([
            'status' => 'success',
            'message' => 'ContraseÃ±a actualizada correctamente. Ya puedes iniciar sesiÃ³n con tu nueva contraseÃ±a.'
        ]);
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al actualizar la contraseÃ±a: ' . $e->getMessage()
    ]);
}

$conn->close();
?>


