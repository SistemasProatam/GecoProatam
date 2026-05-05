<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/conexion.php";

// Incluir PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $mail_sent = true;

    if ($mail_sent) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'MODO PRUEBA: Simulación de correo. Tu código es: <b>' . $token . '</b> (Cópialo)'
        ]);
    } else {
        throw new Exception("No se pudo enviar el correo electrónico. Intenta nuevamente.");
    }

} catch (Exception $e) {
    error_log("Error en send_reset_token.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()]);
}

function sendResetTokenEmail($email, $nombres, $apellidos, $token) {
    try {
        $mail = new PHPMailer(true);
        
        // Configuración SMTP (usando la misma de tu sistema)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true;
        $mail->Username = 'sistemas@proatam.com';
        $mail->Password = 'ebho nbpv hlrf japx'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Configuración del correo
        $mail->setFrom('sistemas@proatam.com', 'Sistemas Proatam');
        $mail->addAddress($email, $nombres . ' ' . $apellidos);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        // Asunto
        $mail->Subject = 'Código de Recuperación - PROATAM';
        
        // Cuerpo del mensaje HTML
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    background-color: #f4f4f4; 
                    margin: 0; 
                    padding: 20px; 
                }
                .container { 
                    background-color: white; 
                    padding: 30px; 
                    border-radius: 10px; 
                    max-width: 600px; 
                    margin: 0 auto; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header { 
                    background-color: #2c3e50; 
                    color: white; 
                    padding: 20px; 
                    text-align: center; 
                    border-radius: 5px; 
                    margin-bottom: 20px;
                }
                .content { 
                    padding: 20px 0; 
                }
                .token-box { 
                    background-color: #f8f9fa; 
                    padding: 20px; 
                    border-radius: 5px; 
                    border: 2px dashed #007bff; 
                    margin: 20px 0; 
                    text-align: center;
                }
                .token { 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #e74c3c; 
                    letter-spacing: 5px; 
                    font-family: 'Courier New', monospace;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 30px; 
                    color: #666; 
                    font-size: 12px; 
                    border-top: 1px solid #ddd;
                    padding-top: 15px;
                }
                .warning { 
                    background-color: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    padding: 10px; 
                    border-radius: 5px; 
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>PROATAM</h1>
                    <h2>Recuperación de Contraseña</h2>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>$nombres $apellidos</strong>,</p>
                    <p>Has solicitado restablecer tu contraseña. Utiliza el siguiente código de verificación:</p>
                    
                    <div class='token-box'>
                        <h3 style='color: #007bff; margin-top: 0;'>Código de Verificación</h3>
                        <div class='token'>$token</div>
                        <p style='margin-bottom: 0; margin-top: 10px;'><small>Este código expira en 15 minutos</small></p>
                    </div>
                    
                    <div class='warning'>
                        <p><strong>⚠ï¸ Importante:</strong></p>
                        <ul style='margin-bottom: 0;'>
                            <li>No compartas este código con nadie</li>
                            <li>Si no solicitaste este código, ignora este mensaje</li>
                            <li>El código es válido por 15 minutos</li>
                        </ul>
                    </div>
                    
                    <p>Si tienes problemas para verificar tu cuenta, contacta al departamento de sistemas.</p>
                </div>
                
                <div class='footer'>
                    <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                    <p>&copy; " . date('Y') . " PROATAM S.A. DE C.V. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Versión texto plano
        $mail->AltBody = "Recuperación de Contraseña - PROATAM\n\n" .
                        "Hola $nombres $apellidos,\n\n" .
                        "Has solicitado restablecer tu contraseña.\n\n" .
                        "Tu código de verificación es: $token\n\n" .
                        "Este código expira en 15 minutos.\n\n" .
                        "Si no solicitaste este código, ignora este mensaje.\n\n" .
                        "Este es un correo automático, no respondas.";

        // Enviar correo
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Error enviando correo de recuperación: " . $mail->ErrorInfo);
        return false;
    }
}
?>


