<?php
require_once __DIR__ . '/config.php';

session_start();
header('Content-Type: application/json');
include(__DIR__ . "/conexion.php");

try {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $reset_token = $_POST['reset_token'] ?? '';

    // Validaciones
    if (empty($new_password) || empty($confirm_password) || empty($reset_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
        exit;
    }

    // Validar longitud de contraseña (6-12 caracteres)
    $password_length = strlen($new_password);
    if ($password_length < 6 || $password_length > 12) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener entre 6 y 12 caracteres.']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden.']);
        exit;
    }

    // Verificar token de sesión
    if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $reset_token) {
        echo json_encode(['status' => 'error', 'message' => 'Token inválido o expirado.']);
        exit;
    }

    if (!isset($_SESSION['reset_token_expiry']) || time() > $_SESSION['reset_token_expiry']) {
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_token_expiry']);
        echo json_encode(['status' => 'error', 'message' => 'El token ha expirado. Solicita un nuevo código.']);
        exit;
    }

    $user_id = $_SESSION['reset_user_id'];

    // Hashear nueva contraseña
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Actualizar contraseña en la base de datos
    $update_stmt = $conn->prepare("UPDATE usuarios SET password = ?, password_temporal = 0 WHERE id = ?");
    $update_stmt->bind_param("si", $password_hash, $user_id);
    
    if ($update_stmt->execute()) {
        // Limpiar sesión
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_token_expiry']);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.'
        ]);
    } else {
        throw new Exception("Error al actualizar la contraseña.");
    }

} catch (Exception $e) {
    error_log("Error en update_password_reset.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor al actualizar la contraseña.']);
}
?>

