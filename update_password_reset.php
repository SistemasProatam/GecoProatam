<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/conexion.php";

try {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $reset_token = $_POST['reset_token'] ?? '';

    // Validaciones
    if (empty($new_password) || empty($confirm_password) || empty($reset_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
        exit;
    }

    // Validar longitud de contraseÃ±a (6-12 caracteres)
    $password_length = strlen($new_password);
    if ($password_length < 6 || $password_length > 12) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseÃ±a debe tener entre 6 y 12 caracteres.']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseÃ±as no coinciden.']);
        exit;
    }

    // Verificar token de sesiÃ³n
    if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $reset_token) {
        echo json_encode(['status' => 'error', 'message' => 'Token invÃ¡lido o expirado.']);
        exit;
    }

    if (!isset($_SESSION['reset_token_expiry']) || time() > $_SESSION['reset_token_expiry']) {
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_token_expiry']);
        echo json_encode(['status' => 'error', 'message' => 'El token ha expirado. Solicita un nuevo cÃ³digo.']);
        exit;
    }

    $user_id = $_SESSION['reset_user_id'];

    // Hashear nueva contraseÃ±a
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Actualizar contraseÃ±a en la base de datos
    $update_stmt = $conn->prepare("UPDATE usuarios SET password = ?, password_temporal = 0 WHERE id = ?");
    $update_stmt->bind_param("si", $password_hash, $user_id);
    
    if ($update_stmt->execute()) {
        // Limpiar sesiÃ³n
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_token_expiry']);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'ContraseÃ±a actualizada correctamente. Ahora puedes iniciar sesiÃ³n con tu nueva contraseÃ±a.'
        ]);
    } else {
        throw new Exception("Error al actualizar la contraseÃ±a.");
    }

} catch (Exception $e) {
    error_log("Error en update_password_reset.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor al actualizar la contraseÃ±a.']);
}
?>


