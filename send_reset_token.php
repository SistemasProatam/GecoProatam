<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/conexion.php";

// Incluir EmailHandler
require_once __DIR__ . '/EmailHandler.php';

try {
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'El correo electrónico es requerido.']);
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'El formato del correo no es válido.']);
        exit;
    }

    // Verificar si el correo existe en la base de datos
    $stmt = $conn->prepare("SELECT id, nombres, apellidos FROM usuarios WHERE correo_corporativo = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'El correo electrónico no está registrado en nuestro sistema.']);
        exit;
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $nombres = $user['nombres'];
    $apellidos = $user['apellidos'];

    // Generar token único (6 dígitos)
    $token = sprintf("%06d", mt_rand(1, 999999));

    // Hash del token para almacenar en BD
    $token_hash = password_hash($token, PASSWORD_DEFAULT);

    // Fecha de expiración (15 minutos desde ahora)
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Eliminar tokens previos del usuario
    $delete_stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();

    // Insertar nuevo token
    $insert_stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("iss", $user_id, $token_hash, $expires_at);

    if (!$insert_stmt->execute()) {
        throw new Exception("Error al guardar el token en la base de datos.");
    }

    // Enviar correo con el token
    $emailHandler = new EmailHandler();
    $mail_sent = $emailHandler->enviarCorreoRecuperacion($email, $nombres, $apellidos, $token);

    if ($mail_sent) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Se ha enviado un código de verificación a tu correo electrónico. El código expira en 15 minutos.'
        ]);
    } else {
        throw new Exception("No se pudo enviar el correo electrónico. Intenta nuevamente.");
    }
} catch (Exception $e) {
    error_log("Error en send_reset_token.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
