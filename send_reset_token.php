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
        echo json_encode(['status' => 'error', 'message' => 'El correo electrÃ³nico es requerido.']);
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'El formato del correo no es vÃ¡lido.']);
        exit;
    }

    // Verificar si el correo existe en la base de datos
    $stmt = $conn->prepare("SELECT id, nombres, apellidos FROM usuarios WHERE correo_corporativo = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'El correo electrÃ³nico no estÃ¡ registrado en nuestro sistema.']);
        exit;
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $nombres = $user['nombres'];
    $apellidos = $user['apellidos'];

    // Generar token Ãºnico (6 dÃ­gitos)
    $token = sprintf("%06d", mt_rand(1, 999999));
    
    // Hash del token para almacenar en BD
    $token_hash = password_hash($token, PASSWORD_DEFAULT);
    
    // Fecha de expiraciÃ³n (15 minutos desde ahora)
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
    $mail_sent = sendResetTokenEmail($email, $nombres, $apellidos, $token);

    if ($mail_sent) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Se ha enviado un cÃ³digo de verificaciÃ³n a tu correo electrÃ³nico. El cÃ³digo expira en 15 minutos.'
        ]);
    } else {
        throw new Exception("No se pudo enviar el correo electrÃ³nico. Intenta nuevamente.");
    }

} catch (Exception $e) {
    error_log("Error en send_reset_token.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()]);
}

function sendResetTokenEmail($email, $nombres, $apellidos, $token) {
    try {
        $mail = new PHPMailer(true);
        
        // ConfiguraciÃ³n SMTP (usando la misma de tu sistema)
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

        // ConfiguraciÃ³n del correo
        $mail->setFrom('sistemas@proatam.com', 'Sistemas Proatam');
        $mail->addAddress($email, $nombres . ' ' . $apellidos);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        // Asunto
        $mail->Subject = 'CÃ³digo de RecuperaciÃ³n - PROATAM';
        
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
                    <h2>RecuperaciÃ³n de ContraseÃ±a</h2>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>$nombres $apellidos</strong>,</p>
                    <p>Has solicitado restablecer tu contraseÃ±a. Utiliza el siguiente cÃ³digo de verificaciÃ³n:</p>
                    
                    <div class='token-box'>
                        <h3 style='color: #007bff; margin-top: 0;'>CÃ³digo de VerificaciÃ³n</h3>
                        <div class='token'>$token</div>
                        <p style='margin-bottom: 0; margin-top: 10px;'><small>Este cÃ³digo expira en 15 minutos</small></p>
                    </div>
                    
                    <div class='warning'>
                        <p><strong>âš ï¸ Importante:</strong></p>
                        <ul style='margin-bottom: 0;'>
                            <li>No compartas este cÃ³digo con nadie</li>
                            <li>Si no solicitaste este cÃ³digo, ignora este mensaje</li>
                            <li>El cÃ³digo es vÃ¡lido por 15 minutos</li>
                        </ul>
                    </div>
                    
                    <p>Si tienes problemas para verificar tu cuenta, contacta al departamento de sistemas.</p>
                </div>
                
                <div class='footer'>
                    <p>Este es un correo automÃ¡tico, por favor no respondas a este mensaje.</p>
                    <p>&copy; " . date('Y') . " PROATAM S.A. DE C.V. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // VersiÃ³n texto plano
        $mail->AltBody = "RecuperaciÃ³n de ContraseÃ±a - PROATAM\n\n" .
                        "Hola $nombres $apellidos,\n\n" .
                        "Has solicitado restablecer tu contraseÃ±a.\n\n" .
                        "Tu cÃ³digo de verificaciÃ³n es: $token\n\n" .
                        "Este cÃ³digo expira en 15 minutos.\n\n" .
                        "Si no solicitaste este cÃ³digo, ignora este mensaje.\n\n" .
                        "Este es un correo automÃ¡tico, no respondas.";

        // Enviar correo
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Error enviando correo de recuperaciÃ³n: " . $mail->ErrorInfo);
        return false;
    }
}
?>


