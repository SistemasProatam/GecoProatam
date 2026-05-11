<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


include __DIR__ . '/../conexion.php';

require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';

require_method('POST');

try {
    $email = post_string('email', true, 120);
    $token = post_string('token', true, 6);
    $new_password = post_string('new_password', true, 120);

    // 1. Validar longitud de contraseña (6-12 caracteres según tu lógica web)
    $password_length = strlen($new_password);
    if ($password_length < 6 || $password_length > 12) {
        json_response(422, 'error', 'La contraseña debe tener entre 6 y 12 caracteres.');
    }

    // 2. Buscar al usuario
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo_corporativo = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $u_res = $stmt->get_result();
    
    if ($u_res->num_rows === 0) {
        json_response(404, 'error', 'Usuario no encontrado.');
    }
    $user = $u_res->fetch_assoc();
    $user_id = $user['id'];

    // 3. Verificar el token en la tabla password_reset_tokens
    $t_stmt = $conn->prepare("SELECT token_hash, expires_at FROM password_reset_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $t_stmt->bind_param("i", $user_id);
    $t_stmt->execute();
    $t_res = $t_stmt->get_result();

    if ($t_res->num_rows === 0) {
        json_response(401, 'error', 'No hay un código de recuperación activo.');
    }

    $token_row = $t_res->fetch_assoc();

    // Validar expiración
    if (strtotime($token_row['expires_at']) < time()) {
        json_response(401, 'error', 'El código ha expirado. Solicita uno nuevo.');
    }

    // Validar token (comparar el código de 6 dígitos con el hash guardado)
    if (!password_verify($token, $token_row['token_hash'])) {
        json_response(401, 'error', 'Código de verificación incorrecto.');
    }

    // 4. Todo OK -> Actualizar contraseña
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE usuarios SET password = ?, password_temporal = 0 WHERE id = ?");
    $upd->bind_param("si", $new_hash, $user_id);

    if ($upd->execute()) {
        // Limpiar tokens usados
        $conn->query("DELETE FROM password_reset_tokens WHERE user_id = $user_id");
        
        json_response(200, 'success', 'Contraseña actualizada correctamente.');
    } else {
        json_response(500, 'error', 'No se pudo actualizar la contraseña.');
    }

} catch (Throwable $e) {
    error_log('[api_mobile/reset_password] ' . $e->getMessage());
    json_response(500, 'error', 'Error: ' . $e->getMessage());
}
