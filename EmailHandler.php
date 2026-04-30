<?php
require_once "config.php";

require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailHandler
{
    private $mail;
    private $soporteEmail = 'sistemas@proatam.com';
    private $soporteNombre = 'Soporte Técnico Proatam';
    private $soporteEmail_activos = ['edgaror@proatam.com', 'negrete@proatam.com'];
    private $soporteNombre_activos = ['Edgar Ochoa', 'Jose Negrete'];
    private $baseUrl = "https://proatamgoc.duckdns.org/";


    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->configurarPHPMailer();
    }

    private function configurarPHPMailer()
    {
        try {
            // Configuración del servidor SMTP
            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.gmail.com';
            $this->mail->SMTPAuth = true;
            $this->mail->Username = 'sistemas@proatam.com';
            $this->mail->Password = 'ebhonbpvhlrfjapx';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = 587;
            $this->mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // AGREGAR TIMEOUTS
            $this->mail->Timeout = 30; // 30 segundos máximo
            $this->mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Configuración general
            $this->mail->setFrom($this->soporteEmail, $this->soporteNombre);
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
        } catch (Exception $e) {
            error_log("Error configurando PHPMailer: " . $e->getMessage());
            throw new Exception("Error al configurar el servicio de email");
        }
    }

    /**
     * Enviar notificación de nueva orden de compra al Subdirector General
     */
    public function enviarNotificacionNuevaOrdenCompra($destinatario, $nombreDestinatario, $datosOrdenCompra)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($destinatario, $nombreDestinatario);

            $this->mail->Subject = "Nueva Orden de Compra Pendiente de Aprobación - {$datosOrdenCompra['folio']}";
            $url = $this->baseUrl . "orders/see_oc.php?id=" . $datosOrdenCompra['id'];

            $cuerpoHTML = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #ffc107; color: #000; padding: 20px; text-align: center;'>
                <h2>Nueva Orden de Compra Requiere Aprobación</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f8f9fa;'>
                <p>Hola <strong>{$nombreDestinatario}</strong>,</p>
                
                <p>Se ha generado una nueva orden de compra que requiere su revisión y aprobación:</p>
                
                <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; width: 40%;'>Folio:</td>
                            <td style='padding: 8px;'><strong style='color: #0d6efd;'>{$datosOrdenCompra['folio']}</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #f8f9fa;'>Estado:</td>
                            <td style='padding: 8px; background-color: #f8f9fa;'>
                                <span style='background-color: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px; font-size: 0.9em;'>
                                    {$datosOrdenCompra['estado']}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Solicitante:</td>
                            <td style='padding: 8px;'>{$datosOrdenCompra['solicitante']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #f8f9fa;'>Entidad:</td>
                            <td style='padding: 8px; background-color: #f8f9fa;'>{$datosOrdenCompra['entidad']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Proveedor:</td>
                            <td style='padding: 8px;'>{$datosOrdenCompra['proveedor']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #f8f9fa;'>Proyecto:</td>
                            <td style='padding: 8px; background-color: #f8f9fa;'>{$datosOrdenCompra['proyecto']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Obra:</td>
                            <td style='padding: 8px;'>{$datosOrdenCompra['obra']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #fffbea;'>Monto Total:</td>
                            <td style='padding: 8px; background-color: #fffbea;'>
                                <strong style='color: #d63384; font-size: 1.1em;'>{$datosOrdenCompra['total']}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #f8f9fa;'>Fecha de Solicitud:</td>
                            <td style='padding: 8px; background-color: #f8f9fa;'>{$datosOrdenCompra['fecha_solicitud']}</td>
                        </tr>
                    </table>
                </div>
                
                <div style='background-color: #d1ecf1; border-left: 4px solid #0dcaf0; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <strong>Acción requerida:</strong><br>
                    Por favor, revise esta orden de compra y proceda con su aprobación o rechazo.
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <a href='{$url}' 
                        style='background-color: #198754; color: white; padding: 12px 30px; 
                        text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
                        Revisar y Aprobar Orden
                    </a>

                </p>
                
                <p style='text-align: center; margin-top: 10px;'>
                    <a href='https://proatamgoc.duckdns.org/orders/list_oc.php' 
                       style='color: white; text-decoration: none; font-size: 0.9em;'>
                        Ver todas las órdenes de compra
                    </a>
                </p>
            </div>
            
            <div style='background-color: #e9ecef; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;'>
                <p>Este es un correo automático del Sistema PROATAM. Por favor no responder.</p>
                <p style='margin-top: 5px;'>
                    <strong>Importante:</strong> Su aprobación es necesaria para continuar con el proceso de compra.
                </p>
            </div>
        </div>
        ";

            $this->mail->Body = $cuerpoHTML;
            $this->mail->AltBody = strip_tags($cuerpoHTML);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo de nueva orden de compra: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Enviar notificación de cambio de estado de orden de compra
     */
    public function enviarNotificacionOrdenCompra($destinatario, $nombreDestinatario, $datosOrdenCompra)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($destinatario, $nombreDestinatario);

            // Determinar color y emoji según el estado
            $colorEstado = '#6c757d';
            $emoji = '📋';
            $tituloAccion = 'Actualización de Orden de Compra';

            switch (strtolower($datosOrdenCompra['estado'])) {
                case 'aprobado':
                    $colorEstado = '#198754';
                    $emoji = '✅';
                    $tituloAccion = 'Orden de Compra Aprobada';
                    break;
                case 'rechazado':
                    $colorEstado = '#dc3545';
                    $emoji = '❌';
                    $tituloAccion = 'Orden de Compra Rechazada';
                    break;
                case 'comprobante subido':
                    $colorEstado = '#0dcaf0';
                    $emoji = '📎';
                    $tituloAccion = 'Comprobante de Pago Adjuntado';
                    break;
                case 'pagado y completado':
                case 'pagado':
                    $colorEstado = '#0d6efd';
                    $emoji = '💰';
                    $tituloAccion = 'Orden de Compra Pagada';
                    break;
            }

            $this->mail->Subject = "{$emoji} {$tituloAccion} - {$datosOrdenCompra['folio']}";
            $url = $this->baseUrl . "orders/see_oc.php?id=" . $datosOrdenCompra['id'];

            $cuerpoHTML = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: {$colorEstado}; color: white; padding: 20px; text-align: center;'>
                <h2>{$emoji} {$tituloAccion}</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f8f9fa;'>
                <p>Hola <strong>{$nombreDestinatario}</strong>,</p>
                
                <p>Te informamos sobre un cambio en el estado de una orden de compra:</p>
                
                <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid {$colorEstado};'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; width: 40%;'>Folio:</td>
                            <td style='padding: 8px;'><strong style='color: #0d6efd;'>{$datosOrdenCompra['folio']}</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #f8f9fa;'>Nuevo Estado:</td>
                            <td style='padding: 8px; background-color: #f8f9fa;'>
                                <span style='background-color: {$colorEstado}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em;'>
                                    <strong>{$datosOrdenCompra['estado']}</strong>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Solicitante:</td>
                            <td style='padding: 8px;'>{$datosOrdenCompra['solicitante']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #f8f9fa;'>Proveedor:</td>
                            <td style='padding: 8px; background-color: #f8f9fa;'>{$datosOrdenCompra['proveedor']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Proyecto:</td>
                            <td style='padding: 8px;'>{$datosOrdenCompra['proyecto']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #fffbea;'>Total:</td>
                            <td style='padding: 8px; background-color: #fffbea;'>
                                <strong style='color: #d63384;'>{$datosOrdenCompra['total']}</strong>
                            </td>
                        </tr>
                    </table>
                </div>
                
                " . (!empty($datosOrdenCompra['comentarios']) ? "
                <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <strong>💬 Comentarios:</strong><br>
                    <p style='margin: 10px 0 0 0;'>{$datosOrdenCompra['comentarios']}</p>
                </div>
                " : "") . "
                
                <p style='text-align: center; margin-top: 30px;'>
                <a href='{$url}' 
                    style='background-color: {$colorEstado}; color: white; padding: 12px 30px; 
                    text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
                    Ver Orden de Compra
                </a>

                </p>
            </div>
            
            <div style='background-color: #e9ecef; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;'>
                <p>Este es un correo automático del Sistema PROATAM. Por favor no responder.</p>
            </div>
        </div>
        ";

            $this->mail->Body = $cuerpoHTML;
            $this->mail->AltBody = strip_tags($cuerpoHTML);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo de notificación OC: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación al solicitante cuando su orden es aprobada/rechazada/pagada
     */
    public function enviarNotificacionSolicitanteOC($destinatario, $nombreDestinatario, $datosOrdenCompra)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($destinatario, $nombreDestinatario);

            // Determinar asunto según estado
            $asunto = "";
            $colorEstado = '#6c757d';
            $emoji = '📋';

            switch (strtolower($datosOrdenCompra['estado'])) {
                case 'aprobado':
                    $asunto = "✅ Tu Orden de Compra ha sido Aprobada - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#198754';
                    break;
                case 'rechazado':
                    $asunto = "❌ Tu Orden de Compra ha sido Rechazada - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#dc3545';
                    break;
                case 'pagado':
                    $asunto = "Tu Orden de Compra ha sido Pagada - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#0d6efd';
                    break;
            }

            $this->mail->Subject = $asunto;
            $url = $this->baseUrl . "orders/see_oc.php?id=" . $datosOrdenCompra['id'];

            $cuerpoHTML = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: {$colorEstado}; color: white; padding: 20px; text-align: center;'>
                <h2>{$emoji} {$datosOrdenCompra['estado']}</h2>
                <h3>Orden de Compra {$datosOrdenCompra['folio']}</h3>
            </div>
            
            <div style='padding: 20px; background-color: #f8f9fa;'>
                <p>Hola <strong>{$nombreDestinatario}</strong>,</p>
                
                <p>Tu orden de compra ha sido actualizada:</p>
                
                <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid {$colorEstado};'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; width: 40%;'>Folio:</td>
                            <td style='padding: 8px;'><strong style='color: #0d6efd;'>{$datosOrdenCompra['folio']}</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #f8f9fa;'>Estado:</td>
                            <td style='padding: 8px; background-color: #f8f9fa;'>
                                <span style='background-color: {$colorEstado}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.9em;'>
                                    <strong>{$datosOrdenCompra['estado']}</strong>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Proveedor:</td>
                            <td style='padding: 8px;'>{$datosOrdenCompra['proveedor']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; background-color: #fffbea;'>Total:</td>
                            <td style='padding: 8px; background-color: #fffbea;'>
                                <strong style='color: #d63384;'>{$datosOrdenCompra['total']}</strong>
                            </td>
                        </tr>
                    </table>
                </div>
                
                " . (!empty($datosOrdenCompra['comentarios']) ? "
                <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <strong>💬 Comentarios:</strong><br>
                    <p style='margin: 10px 0 0 0;'>{$datosOrdenCompra['comentarios']}</p>
                </div>
                " : "") . "
                
                <div style='background-color: #e7f3ff; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                <strong>Información:</strong><br>
                " . (
                $datosOrdenCompra['estado'] === 'aprobado' 
                    ? "Tu orden de compra ha sido aprobada y ha sido enviada al Gerente de Recursos Humanos para proceder con el pago."
                : ($datosOrdenCompra['estado'] === 'pagado' 
                    ? "El pago de tu orden de compra ha sido completado exitosamente."
                : ($datosOrdenCompra['estado'] === 'rechazado'
                    ? "Tu orden de compra ha sido rechazada. Por favor, contacta al Subdirector General para más información."
                : "La orden de compra ha sido actualizada."
                    )
                )) . "
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <a href='{$url}' 
                        style='background-color: {$colorEstado}; color: white; padding: 12px 30px; 
                        text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
                        Ver Orden de Compra
                    </a>
                </p>
            </div>
            
            <div style='background-color: #e9ecef; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;'>
                <p>Este es un correo automático del Sistema PROATAM. Por favor no responder.</p>
            </div>
        </div>
        ";

            $this->mail->Body = $cuerpoHTML;
            $this->mail->AltBody = strip_tags($cuerpoHTML);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo al solicitante OC: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía una solicitud de soporte técnico
     */
    public function enviarSolicitudSoporte($datos)
    {
        try {
            $this->mail->clearAddresses();
            
            // Determinar si notificar a soporte de activos o soporte general
            $tieneActivo = !empty($datos['activo_id']);
            $sistemaEsActivo = isset($datos['sistema_afectado']) && strtolower($datos['sistema_afectado']) === 'Activo / Equipo';
            
            if ($tieneActivo || $sistemaEsActivo) {
                // Notificar a soporte de activos
                foreach ($this->soporteEmail_activos as $email) {
                    $this->mail->addAddress($email, $this->soporteNombre_activos);
                }
            } else {
                // Notificar solo a soporte general
                $this->mail->addAddress($this->soporteEmail, $this->soporteNombre);
            }

            // Incluye código del activo en el asunto si fue seleccionado
            $asunto = isset($datos['urgencia'])
                ? "[MANTENIMIENTO - {$datos['urgencia']}] {$datos['asunto']}"
                : "[MANTENIMIENTO] {$datos['asunto']}";
            if (!empty($datos['activo_codigo'])) {
                $asunto .= " | Activo: {$datos['activo_codigo']}";
            }

            $this->mail->Subject = $asunto;
            $this->mail->Body    = $this->crearTemplateSolicitud($datos);
            $this->mail->AltBody = $this->crearTextoPlano($datos);

            if (isset($datos['adjuntos']) && is_array($datos['adjuntos'])) {
                foreach ($datos['adjuntos'] as $adjunto) {
                    if (file_exists($adjunto['tmp_name'])) {
                        $this->mail->addAttachment($adjunto['tmp_name'], $adjunto['name']);
                    }
                }
            }

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando email de soporte: " . $this->mail->ErrorInfo);
            throw new Exception("No se pudo enviar el email: " . $this->mail->ErrorInfo);
        }
    }

    /**
     * Crea el template HTML para la solicitud de soporte
     */
    private function crearTemplateSolicitud($datos)
    {
        $urgenciaClass = $this->getClaseUrgencia($datos['urgencia'] ?? 'normal');

        // ── Bloque del activo relacionado (solo se renderiza si viene un activo) ──
        $bloqueActivo = '';
        if (!empty($datos['activo_id'])) {
            $bloqueActivo = "
                    <tr>
                        <td colspan='2' style='padding: 4px 0;'>
                            <div style='background-color: #e8f0fe; border-left: 4px solid #1a73e8;
                                        padding: 12px 16px; margin: 10px 0; border-radius: 4px;'>
                                <div style='font-weight: bold; color: #1a73e8; margin-bottom: 8px;'>
                                    Activo Relacionado
                                </div>
                                <table style='width: 100%; border-collapse: collapse; font-size: 0.9em;'>
                                    <tr>
                                        <td style='padding: 4px 8px; font-weight: bold; width: 30%; color: #555;'>Código:</td>
                                        <td style='padding: 4px 8px;'>
                                            <strong style='font-family: monospace; letter-spacing: 1px; color: #113456;'>
                                                {$datos['activo_codigo']}
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr style='background: rgba(0,0,0,.04);'>
                                        <td style='padding: 4px 8px; font-weight: bold; color: #555;'>Nombre:</td>
                                        <td style='padding: 4px 8px;'>{$datos['activo_nombre']}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 4px 8px; font-weight: bold; color: #555;'>Tipo:</td>
                                        <td style='padding: 4px 8px;'>{$datos['activo_tipo']}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body   { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header  { background: #f8f9fa; padding: 15px; border-radius: 5px; }
                .urgent  { background: #fff3cd; border-left: 4px solid #ffc107; }
                .high    { background: #f8d7da; border-left: 4px solid #dc3545; }
                .normal  { background: #d1ecf1; border-left: 4px solid #17a2b8; }
                .content { background: white; padding: 20px; border-radius: 5px; margin-top: 15px; }
                .field   { margin-bottom: 10px; }
                .label   { font-weight: bold; color: #555; }
                table.inf { width: 100%; border-collapse: collapse; }
                table.inf td { padding: 8px; vertical-align: top; }
                table.inf tr:nth-child(even) td { background: #f8f9fa; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header {$urgenciaClass}'>
                    <h2>Nueva Solicitud de Mantenimiento</h2>
                    <p><strong>Prioridad:</strong> " . ($datos['urgencia'] ?? 'Normal') . "</p>
                </div>

                <div class='content'>
                    <table class='inf'>
                        <tr>
                            <td style='font-weight: bold; width: 35%;'>Solicitante:</td>
                            <td>
                                {$datos['nombres']}
                                <br><small style='color:#666;'>{$datos['correo_corporativo']}</small>
                            </td>
                        </tr>
                        " . (!empty($datos['departamento']) ? "
                        <tr>
                            <td style='font-weight: bold;'>Departamento:</td>
                            <td>{$datos['departamento']}</td>
                        </tr>" : "") . "
                        " . (!empty($datos['sistema_afectado']) ? "
                        <tr>
                            <td style='font-weight: bold;'>Sistema / Área:</td>
                            <td>{$datos['sistema_afectado']}</td>
                        </tr>" : "") . "
                        <tr>
                            <td style='font-weight: bold;'>Fecha:</td>
                            <td>" . date('d/m/Y') . "</td>
                        </tr>
                        {$bloqueActivo}
                    </table>

                    <div class='field' style='margin-top: 16px;'>
                        <span class='label'>Asunto:</span>
                        <p style='margin: 4px 0;'>{$datos['asunto']}</p>
                    </div>

                    <div class='field'>
                        <span class='label'>Descripción del problema:</span>
                        <p style='white-space: pre-line;'>" . nl2br(htmlspecialchars($datos['descripcion'])) . "</p>
                    </div>

                    " . (!empty($datos['pasos_reproducir']) ? "
                    <div class='field'>
                        <span class='label'>Pasos para reproducir:</span>
                        <p>{$datos['pasos_reproducir']}</p>
                    </div>" : "") . "
                </div>

                <div style='text-align:center; margin-top:16px; font-size:12px; color:#999;'>
                    Correo automático del Sistema PROATAM · No responder a este mensaje.
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Crea la versión en texto plano del email
     */
     private function crearTextoPlano($datos)
    {
        $texto  = "NUEVA SOLICITUD DE MANTENIMIENTO - PROATAM\n";
        $texto .= "==========================================\n\n";
        $texto .= "Asunto:      {$datos['asunto']}\n";
        $texto .= "Solicitante: {$datos['nombres']} ({$datos['correo_corporativo']})\n";
        $texto .= "Prioridad:   " . ($datos['urgencia'] ?? 'Normal') . "\n";
        if (!empty($datos['departamento']))     $texto .= "Departamento: {$datos['departamento']}\n";
        if (!empty($datos['sistema_afectado'])) $texto .= "Sistema:      {$datos['sistema_afectado']}\n";
        $texto .= "Fecha:       " . date('d/m/Y') . "\n";

        // Activo relacionado (solo si fue seleccionado)
        if (!empty($datos['activo_id'])) {
            $texto .= "\nACTIVO RELACIONADO\n";
            $texto .= "------------------\n";
            $texto .= "Código: {$datos['activo_codigo']}\n";
            $texto .= "Nombre: {$datos['activo_nombre']}\n";
            $texto .= "Tipo:   {$datos['activo_tipo']}\n";
        }

        $texto .= "\nDESCRIPCIÓN DEL PROBLEMA:\n{$datos['descripcion']}\n\n";
        if (!empty($datos['pasos_reproducir'])) {
            $texto .= "PASOS PARA REPRODUCIR:\n{$datos['pasos_reproducir']}\n\n";
        }
        $texto .= "Enviado el: " . date('d/m/Y') . "\n";
        return $texto;
    }

    /**
     * Obtiene la clase CSS según la urgencia
     */
    private function getClaseUrgencia($urgencia)
    {
        switch (strtolower($urgencia)) {
            case 'urgente':
                return 'urgent';
            case 'alta':
                return 'high';
            default:
                return 'normal';
        }
    }

    /**
     * Método para enviar confirmación al usuario
     */
    public function enviarConfirmacionUsuario($emailUsuario, $nombreUsuario, $ticketId)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($emailUsuario, $nombreUsuario);
            $this->mail->clearAttachments();

            $this->mail->Subject = "Confirmación de Solicitud de Mantenimiento - Ticket #{$ticketId}";
            $this->mail->Body = $this->crearTemplateConfirmacion($nombreUsuario, $ticketId);
            $this->mail->AltBody = "Hola {$nombreUsuario},\n\nHemos recibido tu solicitud de mantenimiento (Ticket #{$ticketId}).\nNos pondremos en contacto contigo pronto.";

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando confirmación: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    private function crearTemplateConfirmacion($nombre, $ticketId)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #d4edda; padding: 15px; border-radius: 5px; text-align: center; }
                .content { background: white; padding: 20px; border-radius: 5px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>¡Solicitud Recibida!</h2>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>{$nombre}</strong>,</p>
                    
                    <p>Hemos recibido tu solicitud de mantenimiento y hemos creado el ticket <strong>#{$ticketId}</strong>.</p>
                    
                    <p>El equipo revisará tu solicitud y se pondrá en contacto contigo a la brevedad.</p>
                    
                    <p>Si necesitas agregar información adicional, puedes hacerlo respondiendo directamente a este mensaje de confirmación, 
                    de modo que la información quede registrada en el mismo hilo de seguimiento.</p>
                    
                    <p>Saludos cordiales,<br>
                    <strong>Equipo de Soporte</strong><br>
                    Proatam</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Envía notificación de nueva requisición a supervisores
     */
    public function enviarNotificacionRequisicion($emailDestinatario, $nombreDestinatario, $datosRequisicion)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($emailDestinatario, $nombreDestinatario);

            $this->mail->Subject = "Nueva Requisición Pendiente - " . $datosRequisicion['folio'];

            $this->mail->Body = $this->crearTemplateNotificacionRequisicion($datosRequisicion);
            $this->mail->AltBody = $this->crearTextoPlanoNotificacionRequisicion($datosRequisicion);

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando notificación de requisición: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Template HTML para notificación de requisición
     */
    private function crearTemplateNotificacionRequisicion($datos)
    {
        $url = $this->baseUrl . "orders/see_requis.php?id=" . $datos['id'];

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; text-align: center; }
                .content { background: white; padding: 20px; border-radius: 5px; margin-top: 15px; border: 1px solid #ddd; }
                .info-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .badge { background: #007bff; color: white; padding: 5px 10px; border-radius: 3px; }
                .btn-primary { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>PROATAM</h1>
                    <h2>Nueva Requisición Pendiente</h2>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>{$datos['nombre']}</strong>,</p>
                    <p>Se ha creado una nueva requisición que requiere tu revisión:</p>
                    
                    <div class='info-box'>
                        <h3>Información de la Requisición</h3>
                        <p><strong>Folio:</strong> {$datos['folio']}</p>
                        <p><strong>Solicitante:</strong> {$datos['solicitante']}</p>
                        <p><strong>Fecha:</strong> {$datos['fecha']}</p>
                        <p><strong>Entidad:</strong> {$datos['entidad']}</p>
                        <p><strong>Categoría:</strong> {$datos['categoria']}</p>
                        <p><strong>Estado:</strong> <span class='badge'>Pendiente</span></p>
                    </div>

                    " . (!empty($datos['descripcion']) ? "
                    <div class='info-box'>
                        <h4>Descripción:</h4>
                        <p>{$datos['descripcion']}</p>
                    </div>" : "") . "

                    " . (!empty($datos['observaciones']) ? "
                    <div class='info-box'>
                        <h4>Observaciones:</h4>
                        <p>{$datos['observaciones']}</p>
                    </div>" : "") . "

                    <p>Por favor, accede al sistema para revisar y aprobar esta requisición.</p>
                    
                    <a href='{$url}' class='btn-primary'>
                        Revisar Requisición
                    </a>

                </div>
                
                <div class='footer'>
                    <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                    <p>&copy; " . date('Y') . " PROATAM. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Versión texto plano para notificación de requisición
     */
    private function crearTextoPlanoNotificacionRequisicion($datos)
    {
        return "NUEVA REQUISICIÓN PENDIENTE - PROATAM\n\n" .
            "Hola {$datos['nombre']},\n\n" .
            "Se ha creado una nueva requisición que requiere tu revisión:\n\n" .
            "Folio: {$datos['folio']}\n" .
            "Solicitante: {$datos['solicitante']}\n" .
            "Fecha: {$datos['fecha']}\n" .
            "Entidad: {$datos['entidad']}\n" .
            "Categoría: {$datos['categoria']}\n" .
            "Estado: Pendiente\n\n" .
            (!empty($datos['descripcion']) ? "Descripción: {$datos['descripcion']}\n\n" : "") .
            (!empty($datos['observaciones']) ? "Observaciones: {$datos['observaciones']}\n\n" : "") .
            "Accede al sistema para revisar esta requisición.\n\n" .
            "URL: " . $this->baseUrl . "orders/see_requis.php?id=" . $datos['id'] .
            "Este es un correo automático.";
    }

    /**
     * Envía notificación de cambio de estado al solicitante
     */
    public function enviarNotificacionCambioEstado($emailSolicitante, $nombreSolicitante, $datosRequisicion)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($emailSolicitante, $nombreSolicitante);

            $this->mail->Subject = "Actualización de Requisición - " . $datosRequisicion['folio'];

            $this->mail->Body = $this->crearTemplateCambioEstado($datosRequisicion);
            $this->mail->AltBody = $this->crearTextoPlanoCambioEstado($datosRequisicion);

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando notificación de cambio de estado: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Template HTML para cambio de estado
     */
    private function crearTemplateCambioEstado($datos)
    {
        $estadoColor = $this->getColorEstado($datos['estado']);
        $url = $this->baseUrl . "orders/see_requis.php?id=" . $datos['id'];

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; text-align: center; }
                .content { background: white; padding: 20px; border-radius: 5px; margin-top: 15px; border: 1px solid #ddd; }
                .status-box { background: {$estadoColor['background']}; color: {$estadoColor['color']}; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid {$estadoColor['border']}; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .btn-primary { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>PROATAM</h1>
                    <h2>Actualización de Requisición</h2>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>{$datos['solicitante']}</strong>,</p>
                    <p>El estado de tu requisición ha sido actualizado:</p>
                    
                    <div class='status-box'>
                        <h3>Estado Actual: {$datos['estado']}</h3>
                        <p><strong>Folio:</strong> {$datos['folio']}</p>
                        " . (!empty($datos['comentarios']) ? "
                        <p><strong>Comentarios:</strong> {$datos['comentarios']}</p>" : "") . "
                    </div>

                    <p>Puedes ver los detalles de tu requisición en el sistema.</p>
                    
                    <a href='{$url}' class='btn-primary'>
                        Ver Requisición
                    </a>

                </div>
                
                <div class='footer'>
                    <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                    <p>&copy; " . date('Y') . " PROATAM S.A. DE C.V. - Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Versión texto plano para cambio de estado
     */
    private function crearTextoPlanoCambioEstado($datos)
    {
        return "ACTUALIZACIÓN DE REQUISICIÓN - PROATAM\n\n" .
            "Hola {$datos['solicitante']},\n\n" .
            "El estado de tu requisición ha sido actualizado:\n\n" .
            "Folio: {$datos['folio']}\n" .
            "Nuevo Estado: {$datos['estado']}\n" .
            (!empty($datos['comentarios']) ? "Comentarios: {$datos['comentarios']}\n" : "") .
            "\nPuedes ver los detalles en el sistema.\n\n" .
            "URL: " . $this->baseUrl . "orders/see_requis.php?id=" . $datos['id'] .
            "Este es un correo automático.";
    }

    /**
     * Obtiene colores según el estado
     */
    private function getColorEstado($estado)
    {
        $colores = [
            'Pendiente' => ['background' => '#fff3cd', 'color' => '#856404', 'border' => '#ffeaa7'],
            'Aprobado' => ['background' => '#d4edda', 'color' => '#155724', 'border' => '#c3e6cb'],
            'Rechazado' => ['background' => '#f8d7da', 'color' => '#721c24', 'border' => '#f5c6cb']
        ];

        return $colores[$estado] ?? $colores['Pendiente'];
    }

    /**
 * Enviar alerta a Subdirección cuando subcontratos superan el costo directo de la obra
 */
public function enviarAlertaExcesoSubcontratos($destinatario, $nombreDestinatario, $datos)
{
    try {
        $this->mail->clearAddresses();
        $this->mail->addAddress($destinatario, $nombreDestinatario);

        $this->mail->Subject = "Alerta: Subcontratos superan costo directo - {$datos['obra_nombre']}";

        $cuerpoHTML = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #dc3545; color: white; padding: 20px; text-align: center;'>
                <h2>Alerta de Subcontratos</h2>
                <p style='margin:0; font-size: 0.95em;'>Los subcontratos superan el costo directo de la obra</p>
            </div>

            <div style='padding: 20px; background-color: #f8f9fa;'>
                <p>Hola <strong>{$nombreDestinatario}</strong>,</p>
                <p>Se le notifica que el valor total de los subcontratos registrados para la siguiente obra
                   ha superado su costo directo autorizado:</p>

                <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px; font-weight: bold; width: 45%;'>Obra:</td>
                            <td style='padding: 8px;'><strong>{$datos['obra_nombre']}</strong></td>
                        </tr>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; font-weight: bold;'>Costo directo autorizado:</td>
                            <td style='padding: 8px;'>
                                <strong style='color: #198754;'>\${$datos['costo_directo']}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Total subcontratos (incl. extraordinarios):</td>
                            <td style='padding: 8px;'>
                                <strong style='color: #dc3545;'>\${$datos['suma_subcontratos']}</strong>
                            </td>
                        </tr>
                        <tr style='background-color: #fff3cd;'>
                            <td style='padding: 8px; font-weight: bold;'>Exceso:</td>
                            <td style='padding: 8px;'>
                                <strong style='color: #856404; font-size: 1.05em;'>\${$datos['exceso']}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; font-weight: bold;'>Registrado por:</td>
                            <td style='padding: 8px;'>{$datos['usuario']}</td>
                        </tr>
                        <tr style='background-color: #f8f9fa;'>
                            <td style='padding: 8px; font-weight: bold;'>Fecha:</td>
                            <td style='padding: 8px;'>" . date('d/m/Y H:i') . "</td>
                        </tr>
                    </table>
                </div>

                <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <strong>Importante:</strong><br>
                    El monto de la obra <strong>NO ha sido modificado</strong>. 
                    Se requiere su revisión para determinar si se autoriza un ajuste al costo directo.
                </div>

                <p style='text-align: center; margin-top: 30px;'>
                    <a href='{$this->baseUrl}projects/details_obra.php?id={$datos['obra_id']}'
                       style='background-color: #dc3545; color: white; padding: 12px 30px;
                              text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
                        Ver Detalles de la Obra
                    </a>
                </p>
            </div>

            <div style='background-color: #e9ecef; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;'>
                <p>Este es un correo automático del Sistema PROATAM. Por favor no responder.</p>
            </div>
        </div>
        ";

        $this->mail->Body    = $cuerpoHTML;
        $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $cuerpoHTML));

        return $this->mail->send();
    } catch (Exception $e) {
        error_log("Error enviando alerta de exceso de subcontratos: " . $e->getMessage());
        return false;
    }
}
}

