<?php
session_start();
header('Content-Type: application/json');
include(__DIR__ . "/conexion.php");

try {
    $email = $_POST['email'] ?? '';
    $token = $_POST['token'] ?? '';

    if (empty($email) || empty($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
        exit;
    }

    // Verificar si el correo existe
    $user_stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo_corporativo = ?");
    $user_stmt->bind_param("s", $email);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado.']);
        exit;
    }

    $user = $user_result->fetch_assoc();
    $user_id = $user['id'];

    // Buscar token válido
    $token_stmt = $conn->prepare("SELECT id, token_hash FROM password_reset_tokens WHERE user_id = ? AND expires_at > NOW() AND used = 0");
    $token_stmt->bind_param("i", $user_id);
    $token_stmt->execute();
    $token_result = $token_stmt->get_result();
    
    if ($token_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'El código ha expirado o no es válido. Solicita un nuevo código.']);
        exit;
    }

    $token_data = $token_result->fetch_assoc();
    $stored_hash = $token_data['token_hash'];
    $token_id = $token_data['id'];

    // Verificar el token
    if (!password_verify($token, $stored_hash)) {
        echo json_encode(['status' => 'error', 'message' => 'Código incorrecto. Intenta nuevamente.']);
        exit;
    }

    // Marcar token como usado
    $update_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $token_id);
    $update_stmt->execute();

    // Generar token seguro para el cambio de contraseña
    $reset_token = bin2hex(random_bytes(32));
    
    // Guardar reset token en sesión (alternativamente podrías usar la base de datos)
    $_SESSION['reset_token'] = $reset_token;
    $_SESSION['reset_user_id'] = $user_id;
    $_SESSION['reset_token_expiry'] = time() + 900; // 15 minutos

    echo json_encode([
        'status' => 'success', 
        'message' => 'Código verificado correctamente. Ahora puedes cambiar tu contraseña.',
        'reset_token' => $reset_token
    ]);

} catch (Exception $e) {
    error_log("Error en verify_reset_token.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}
?>