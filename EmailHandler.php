<?php
$envFile = __DIR__ . '/../.env.smtp';
if (file_exists($envFile)) {
    foreach (parse_ini_file($envFile) as $key => $value) {
        putenv("$key=$value");
    }
}

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
    private $baseUrl = "https://gecoproatam.com/";


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
            $this->mail->Username = "sistemas@proatam.com";
            $this->mail->Password = "cfhr kncw rpyi pcoh";
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
     * Envuelve el contenido en el template general del sistema
     */
    private function getEmailWrapper($title, $contentHtml, $footerNote = null)
    {
        $time = date('d/m/Y H:i:s');
        $footerText = $footerNote ?: "Este mensaje fue generado automáticamente desde el sistema <strong>GECO PROATAM</strong>.";

        return '
<div style="background-color: #f0f4f8; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #0d2535;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
    <div style="background-color: #ffffff; padding: 20px 10px 20px; text-align: center; border-bottom: 4px solid #407656;">
      <!-- Logo pendiente -->
      <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABwAAAAcCAMAAABF0y+mAAAAV1BMVEVHcEx/r7l/r7l/r7l/r7l3q7h/r7l/r7l/r7l/r7l/r7l/r7l/r7l/r7l/r7mAsLl/r7mCsboAeasAeasAeasAeasAeasAeasAdqocf60Ad6uHtLsAeasNuOmfAAAAHXRSTlMATt7qfTn/sPYKccZiIIqmF5gTEHD/senaZVMiGMMn9xMAAAC3SURBVHgBldBFYkMxDATQMWg+M8P9z1mVG1CTvK0tGrzO+RBhECrrNaGAqf2YmY9ClZuPaVHCEFjBUjKBicxgqVnDkpEwNdYRrXNiXJ8VpHK4J6WP0TPcH1Z/BtsB6Adc+B7W6OM4TePFMh+5zMuyrssyqcvKAoBs+7btu76ufwsbsoU66IFznj+HlkmNIyFTpgLIxS0dP4QSkYoXyTJox+CgpEhDIhc5FyitOOEZPlcxEg2C170By88HYxTgQGQAAAAASUVORK5CYII=" alt="GECO PROATAM" width="280" style="display: block; margin: 0 auto 10px auto; border: 0; max-width: 10%;">
      <p style="color: #113557; margin: 10px 0 0 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">' . $title . '</p>
    </div>
    <div style="padding: 30px;">
      ' . $contentHtml . '
    </div>
    <div style="background-color: #f8fafc; padding: 25px 20px; text-align: center; border-top: 1px solid #e2e8f0;">
      <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.6;">
        ' . $footerText . '<br>
        Fecha de envío: ' . $time . '
      </p>
    </div>
  </div>
</div>';
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

            $innerHtml = "
            <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
                Hola <strong>{$nombreDestinatario}</strong>, se ha generado una nueva orden de compra que requiere su revisión y aprobación.
            </p>

            <h2 style='font-size: 18px; color: #0d2535; border-bottom: 2px solid #f0f4f8; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;'>Detalles de la Orden</h2>
            
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 15px; line-height: 1.6;'>
                <tr>
                    <td width='35%' style='padding: 8px 0; font-weight: 600; color: #113557;'>Folio:</td>
                    <td width='65%' style='padding: 8px 0; color: #334155;'><strong>{$datosOrdenCompra['folio']}</strong></td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Estado:</td>
                    <td style='padding: 8px 0;'>
                        <span style='background-color: #ffc107; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold;'>
                            {$datosOrdenCompra['estado']}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Solicitante:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['solicitante']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Entidad:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['entidad']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Proveedor:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['proveedor']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Proyecto:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['proyecto']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Obra:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['obra']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Monto Total:</td>
                    <td style='padding: 8px 0; color: #d63384; font-weight: bold; font-size: 1.1em;'>{$datosOrdenCompra['total']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Fecha Solicitud:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['fecha_solicitud']}</td>
                </tr>
            </table>

            <div style='margin-top: 30px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #113557; padding: 20px; border-radius: 4px;'>
                <p style='margin: 0; font-weight: 600; color: #0d2535; font-size: 15px;'>Acción requerida:</p>
                <p style='margin: 5px 0 0 0; font-size: 15px; line-height: 1.6; color: #4a5568;'>Por favor, revise esta orden de compra y proceda con su aprobación o rechazo.</p>
            </div>

            <p style='text-align: center; margin-top: 35px;'>
                <a href='{$url}' style='background-color: #113557; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                    Revisar Orden
                </a>
            </p>
            ";

            $this->mail->Body = $this->getEmailWrapper("Nueva Orden de Compra", $innerHtml);
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $innerHtml));

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
            $tituloAccion = 'Actualización de Orden de Compra';

            switch (strtolower($datosOrdenCompra['estado'])) {
                case 'pendiente':
                    $colorEstado = '#ffc107';
                    $tituloAccion = 'Orden de Compra Pendiente';
                    break;
                case 'revisado':
                    $colorEstado = '#0dcaf0';
                    $tituloAccion = 'Orden de Compra Revisada';
                    break;
                case 'aprobado':
                    $colorEstado = '#407656';
                    $tituloAccion = 'Orden de Compra Aprobada';
                    break;
                case 'rechazado':
                    $colorEstado = '#dc3545';
                    $tituloAccion = 'Orden de Compra Rechazada';
                    break;
                case 'pagado':
                case 'pagado y completado':
                    $colorEstado = '#113557';
                    $tituloAccion = 'Orden de Compra Pagada';
                    break;
                case 'devuelto':
                case 'devuelto para editar':
                    $colorEstado = '#fd7e14';
                    $tituloAccion = 'Orden de Compra Devuelta para Editar';
                    break;
                case 'comprobante subido':
                    $colorEstado = '#0dcaf0';
                    $tituloAccion = 'Comprobante de Pago Adjuntado';
                    break;
                default:
                    $colorEstado = '#6c757d';
                    $tituloAccion = 'Actualización de Orden de Compra';
                    break;
            }

            $this->mail->Subject = "{$tituloAccion} - {$datosOrdenCompra['folio']}";
            $url = $this->baseUrl . "orders/see_oc.php?id=" . $datosOrdenCompra['id'];

            $innerHtml = "
            <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
                Hola <strong>{$nombreDestinatario}</strong>, te informamos sobre un cambio en el estado de una orden de compra.
            </p>

            <h2 style='font-size: 18px; color: #0d2535; border-bottom: 2px solid #f0f4f8; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;'>Actualización de Orden</h2>
            
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 15px; line-height: 1.6;'>
                <tr>
                    <td width='35%' style='padding: 8px 0; font-weight: 600; color: #113557;'>Folio:</td>
                    <td width='65%' style='padding: 8px 0; color: #334155;'><strong>{$datosOrdenCompra['folio']}</strong></td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Nuevo Estado:</td>
                    <td style='padding: 8px 0;'>
                        <span style='background-color: {$colorEstado}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold;'>
                            {$datosOrdenCompra['estado']}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Solicitante:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['solicitante']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Proveedor:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['proveedor']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Proyecto:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['proyecto']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Total:</td>
                    <td style='padding: 8px 0; color: #d63384; font-weight: bold;'>{$datosOrdenCompra['total']}</td>
                </tr>
            </table>

            " . (!empty($datosOrdenCompra['comentarios']) ? "
            <div style='margin-top: 30px; background-color: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #ffc107; padding: 20px; border-radius: 4px;'>
                <p style='margin: 0 0 10px 0; font-weight: 600; color: #0d2535; font-size: 15px;'>Comentarios:</p>
                <p style='margin: 0; font-size: 15px; line-height: 1.6; color: #4a5568;'>{$datosOrdenCompra['comentarios']}</p>
            </div>
            " : "") . "

            <p style='text-align: center; margin-top: 35px;'>
                <a href='{$url}' style='background-color: {$colorEstado}; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                    Ver Orden de Compra
                </a>
            </p>
            ";

            $this->mail->Body = $this->getEmailWrapper($tituloAccion, $innerHtml);
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $innerHtml));

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

            switch (strtolower($datosOrdenCompra['estado'])) {
                case 'pendiente':
                    $asunto = "Tu Orden de Compra está Pendiente - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#ffc107';
                    break;
                case 'revisado':
                    $asunto = "Tu Orden de Compra ha sido Revisada - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#0dcaf0';
                    break;
                case 'aprobado':
                    $asunto = "Tu Orden de Compra ha sido Aprobada - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#407656';
                    break;
                case 'rechazado':
                    $asunto = "Tu Orden de Compra ha sido Rechazada - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#dc3545';
                    break;
                case 'pagado':
                case 'pagado y completado':
                    $asunto = "Tu Orden de Compra ha sido Pagada - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#113557';
                    break;
                case 'devuelto':
                case 'devuelto para editar':
                    $asunto = "Tu Orden de Compra ha sido Devuelta para Editar - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#fd7e14';
                    break;
                default:
                    $asunto = "Actualización de tu Orden de Compra - {$datosOrdenCompra['folio']}";
                    $colorEstado = '#6c757d';
                    break;
            }

            $this->mail->Subject = $asunto;
            $url = $this->baseUrl . "orders/see_oc.php?id=" . $datosOrdenCompra['id'];

            $innerHtml = "
            <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
                Hola <strong>{$nombreDestinatario}</strong>, tu orden de compra ha sido actualizada.
            </p>

            <h2 style='font-size: 18px; color: #0d2535; border-bottom: 2px solid #f0f4f8; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;'>Actualización de tu Orden</h2>
            
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 15px; line-height: 1.6;'>
                <tr>
                    <td width='35%' style='padding: 8px 0; font-weight: 600; color: #113557;'>Folio:</td>
                    <td width='65%' style='padding: 8px 0; color: #334155;'><strong>{$datosOrdenCompra['folio']}</strong></td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Estado:</td>
                    <td style='padding: 8px 0;'>
                        <span style='background-color: {$colorEstado}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold;'>
                            {$datosOrdenCompra['estado']}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Proveedor:</td>
                    <td style='padding: 8px 0; color: #334155;'>{$datosOrdenCompra['proveedor']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Total:</td>
                    <td style='padding: 8px 0; color: #d63384; font-weight: bold;'>{$datosOrdenCompra['total']}</td>
                </tr>
            </table>";

            if (!empty($datosOrdenCompra['comentarios'])) {
                $innerHtml .= "
                <div style='margin-top: 30px; background-color: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #ffc107; padding: 20px; border-radius: 4px;'>
                    <p style='margin: 0 0 10px 0; font-weight: 600; color: #0d2535; font-size: 15px;'>Comentarios:</p>
                    <p style='margin: 0; font-size: 15px; line-height: 1.6; color: #4a5568;'>{$datosOrdenCompra['comentarios']}</p>
                </div>";
            }

            $mensajeDescriptivo = "La orden de compra ha sido actualizada.";
            switch (strtolower($datosOrdenCompra['estado'])) {
                case 'pendiente':
                    $mensajeDescriptivo = "Tu orden de compra se encuentra en estado pendiente de revisión.";
                    break;
                case 'revisado':
                    $mensajeDescriptivo = "Tu orden de compra ha sido revisada por el área correspondiente.";
                    break;
                case 'aprobado':
                    $mensajeDescriptivo = "Tu orden de compra ha sido aprobada y ha sido enviada al área administrativa para proceder con el pago.";
                    break;
                case 'rechazado':
                    $mensajeDescriptivo = "Tu orden de compra ha sido rechazada. Por favor, revisa los comentarios o contacta al administrador para más información.";
                    break;
                case 'pagado':
                case 'pagado y completado':
                    $mensajeDescriptivo = "El pago de tu orden de compra ha sido completado exitosamente.";
                    break;
                case 'devuelto':
                case 'devuelto para editar':
                    $mensajeDescriptivo = "Tu orden de compra ha sido devuelta para que realices las correcciones necesarias.";
                    break;
            }

            $innerHtml .= "
            <div style='margin-top: 30px; background-color: #e3f2fd; border: 1px solid #bbdefb; border-left: 4px solid #113557; padding: 20px; border-radius: 4px;'>
                <p style='margin: 0; font-size: 15px; line-height: 1.6; color: #0d2535;'>
                    {$mensajeDescriptivo}
                </p>
            </div>

            <p style='text-align: center; margin-top: 35px;'>
                <a href='{$url}' style='background-color: #113557; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                    Ver Orden de Compra
                </a>
            </p>
            ";

            $this->mail->Body = $this->getEmailWrapper($datosOrdenCompra['estado'], $innerHtml);
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $innerHtml));

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
        $prioridadColor = $datos['urgencia'] === 'Urgente' ? '#dc3545' : ($datos['urgencia'] === 'Alta' ? '#fd7e14' : '#113557');

        // ── Bloque del activo relacionado (solo se renderiza si viene un activo) ──
        $bloqueActivo = '';
        if (!empty($datos['activo_id'])) {
            $bloqueActivo = "
            <div style='margin-top: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #1a73e8; padding: 20px; border-radius: 4px;'>
                <p style='margin: 0 0 10px 0; font-weight: 600; color: #1a73e8; font-size: 15px;'>Activo Relacionado:</p>
                <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 14px; line-height: 1.6;'>
                    <tr>
                        <td width='35%' style='padding: 4px 0; font-weight: 600; color: #555;'>Código:</td>
                        <td width='65%' style='padding: 4px 0; color: #334155;'><strong>{$datos['activo_codigo']}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 4px 0; font-weight: 600; color: #555;'>Nombre:</td>
                        <td style='padding: 4px 0; color: #334155;'>{$datos['activo_nombre']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 4px 0; font-weight: 600; color: #555;'>Tipo:</td>
                        <td style='padding: 4px 0; color: #334155;'>{$datos['activo_tipo']}</td>
                    </tr>
                </table>
            </div>";
        }

        $innerHtml = "
        <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
            Se ha recibido una nueva solicitud de mantenimiento desde el sistema.
        </p>

        <h2 style='font-size: 18px; color: #0d2535; border-bottom: 2px solid #f0f4f8; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;'>Información del Solicitante</h2>
        
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 15px; line-height: 1.6;'>
            <tr>
                <td width='35%' style='padding: 8px 0; font-weight: 600; color: #113557;'>Nombre:</td>
                <td width='65%' style='padding: 8px 0; color: #334155;'>{$datos['nombres']}</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Email:</td>
                <td style='padding: 8px 0; color: #334155;'>{$datos['correo_corporativo']}</td>
            </tr>
            " . (!empty($datos['departamento']) ? "
            <tr>
                <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Departamento:</td>
                <td style='padding: 8px 0; color: #334155;'>{$datos['departamento']}</td>
            </tr>" : "") . "
            <tr>
                <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Prioridad:</td>
                <td style='padding: 8px 0;'>
                    <span style='background-color: {$prioridadColor}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold;'>
                        " . ($datos['urgencia'] ?? 'Normal') . "
                    </span>
                </td>
            </tr>
        </table>

        {$bloqueActivo}

        <div style='margin-top: 30px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #113557; padding: 20px; border-radius: 4px;'>
            <p style='margin: 0 0 10px 0; font-weight: 600; color: #0d2535; font-size: 15px;'>Asunto: {$datos['asunto']}</p>
            <p style='margin: 0; font-size: 15px; line-height: 1.6; color: #4a5568; white-space: pre-wrap;'>" . htmlspecialchars($datos['descripcion']) . "</p>
        </div>

        " . (!empty($datos['pasos_reproducir']) ? "
        <div style='margin-top: 20px; padding: 15px; border: 1px dashed #cbd5e1; border-radius: 4px;'>
            <p style='margin: 0 0 5px 0; font-weight: 600; color: #64748b; font-size: 14px;'>Pasos para reproducir:</p>
            <p style='margin: 0; font-size: 14px; color: #4a5568;'>{$datos['pasos_reproducir']}</p>
        </div>" : "") . "
        ";

        return $this->getEmailWrapper("Nueva Solicitud de Mantenimiento", $innerHtml);
    }

    /**
     * Crea la versión en texto plano del email
     */
    private function crearTextoPlano($datos)
    {
        $texto  = "NUEVA SOLICITUD DE MANTENIMIENTO - GECO PROATAM\n";
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
        $innerHtml = "
        <div style='text-align: center; margin-bottom: 30px;'>
            <div style='background-color: #dcfce7; color: #15803d; width: 60px; height: 60px; line-height: 60px; border-radius: 50%; font-size: 30px; margin: 0 auto 20px;'>
            </div>
            <p style='font-size: 18px; font-weight: 600; color: #0d2535; margin: 0;'>¡Solicitud Recibida Correctamente!</p>
            <p style='font-size: 15px; color: #64748b; margin-top: 5px;'>Hemos registrado tu ticket <strong>#{$ticketId}</strong></p>
        </div>

        <p style='font-size: 15px; line-height: 1.6; color: #4a5568;'>
            Hola <strong>{$nombre}</strong>, hemos recibido tu solicitud de mantenimiento. El equipo revisará los detalles y se pondrá en contacto contigo a la brevedad posible.
        </p>

        <div style='margin-top: 30px; background-color: #f0f9ff; border: 1px solid #e0f2fe; border-left: 4px solid #113557; padding: 20px; border-radius: 4px;'>
            <p style='margin: 0; font-size: 14px; line-height: 1.6; color: #0369a1;'>
                <strong>Información importante:</strong><br>
                Si necesitas agregar más información o capturas adicionales, puedes responder directamente a este mensaje para mantener el seguimiento en el mismo hilo.
            </p>
        </div>

        <p style='font-size: 14px; color: #64748b; margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;'>
            Saludos cordiales,<br>
            <strong>Equipo de Soporte GECO PROATAM</strong>
        </p>
        ";

        return $this->getEmailWrapper("Confirmación de Ticket #{$ticketId}", $innerHtml);
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

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando notificación de requisición: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    private function crearTemplateNotificacionRequisicion($datos)
    {
        $id = $datos['id'] ?? 0;
        $url = $this->baseUrl . "orders/see_requis.php?id=" . $id;

        $innerHtml = "
        <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
            Hola <strong>" . ($datos['nombre'] ?? 'Supervisor') . "</strong>, se ha generado una nueva requisición que requiere tu revisión.
        </p>

        <h2 style='font-size: 18px; color: #0d2535; border-bottom: 2px solid #f0f4f8; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;'>Detalles de la Requisición</h2>
        
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 15px; line-height: 1.6;'>
            <tr>
                <td width='35%' style='padding: 8px 0; font-weight: 600; color: #113557;'>Folio:</td>
                <td width='65%' style='padding: 8px 0; color: #334155;'><strong>" . ($datos['folio'] ?? '-') . "</strong></td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Solicitante:</td>
                <td style='padding: 8px 0; color: #334155;'>" . ($datos['solicitante'] ?? '-') . "</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Fecha:</td>
                <td style='padding: 8px 0; color: #334155;'>" . ($datos['fecha'] ?? '-') . "</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Entidad:</td>
                <td style='padding: 8px 0; color: #334155;'>" . ($datos['entidad'] ?? '-') . "</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Categoría:</td>
                <td style='padding: 8px 0; color: #334155;'>" . ($datos['categoria'] ?? '-') . "</td>
            </tr>
        </table>

        " . (!empty($datos['descripcion']) ? "
        <div style='margin-top: 25px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #113557; padding: 15px; border-radius: 4px;'>
            <p style='margin: 0 0 5px 0; font-weight: 600; color: #0d2535; font-size: 15px;'>Descripción:</p>
            <p style='margin: 0; font-size: 14px; color: #4a5568;'>{$datos['descripcion']}</p>
        </div>" : "") . "

        " . (!empty($datos['observaciones']) ? "
        <div style='margin-top: 15px; background-color: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px;'>
            <p style='margin: 0 0 5px 0; font-weight: 600; color: #0d2535; font-size: 15px;'>Observaciones:</p>
            <p style='margin: 0; font-size: 14px; color: #4a5568;'>{$datos['observaciones']}</p>
        </div>" : "") . "

        <p style='text-align: center; margin-top: 35px;'>
            <a href='{$url}' style='background-color: #113557; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                Revisar Requisición
            </a>
        </p>
        ";

        return $this->getEmailWrapper("Nueva Requisición Pendiente", $innerHtml);
    }

    /**
     * Versión texto plano para notificación de requisición
     */
    private function crearTextoPlanoNotificacionRequisicion($datos)
    {
        $nombre = $datos['nombre'] ?? 'Supervisor';
        $folio = $datos['folio'] ?? '-';
        $solicitante = $datos['solicitante'] ?? '-';
        $fecha = $datos['fecha'] ?? '-';
        $entidad = $datos['entidad'] ?? '-';
        $categoria = $datos['categoria'] ?? '-';
        $id = $datos['id'] ?? 0;

        return "NUEVA REQUISICIÓN PENDIENTE - GECO PROATAM\n\n" .
            "Hola {$nombre},\n\n" .
            "Se ha creado una nueva requisición que requiere tu revisión:\n\n" .
            "Folio: {$folio}\n" .
            "Solicitante: {$solicitante}\n" .
            "Fecha: {$fecha}\n" .
            "Entidad: {$entidad}\n" .
            "Categoría: {$categoria}\n" .
            "Estado: Pendiente\n\n" .
            (!empty($datos['descripcion']) ? "Descripción: {$datos['descripcion']}\n\n" : "") .
            (!empty($datos['observaciones']) ? "Observaciones: {$datos['observaciones']}\n\n" : "") .
            "Accede al sistema para revisar esta requisición.\n\n" .
            "URL: " . $this->baseUrl . "orders/see_requis.php?id=" . $id . "\n\n" .
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

    private function crearTemplateCambioEstado($datos)
    {
        $estadoColor = $this->getColorEstado($datos['estado']);
        $url = $this->baseUrl . "orders/see_requis.php?id=" . $datos['id'];

        $innerHtml = "
        <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
            Hola <strong>{$datos['solicitante']}</strong>, el estado de tu requisición ha sido actualizado.
        </p>

        <h2 style='font-size: 18px; color: #0d2535; border-bottom: 2px solid #f0f4f8; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;'>Actualización de Estado</h2>
        
        <div style='background-color: {$estadoColor['background']}; color: {$estadoColor['color']}; padding: 20px; border-radius: 6px; border-left: 4px solid {$estadoColor['border']};'>
            <p style='margin: 0; font-size: 18px; font-weight: bold;'>Nuevo Estado: {$datos['estado']}</p>
            <p style='margin: 5px 0 0 0; font-size: 15px;'>Folio: <strong>{$datos['folio']}</strong></p>
        </div>

        " . (!empty($datos['comentarios']) ? "
        <div style='margin-top: 25px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #64748b; padding: 15px; border-radius: 4px;'>
            <p style='margin: 0 0 5px 0; font-weight: 600; color: #0d2535; font-size: 15px;'>Comentarios:</p>
            <p style='margin: 0; font-size: 14px; color: #4a5568;'>{$datos['comentarios']}</p>
        </div>" : "") . "

        <p style='text-align: center; margin-top: 35px;'>
            <a href='{$url}' style='background-color: #113557; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                Ver Requisición
            </a>
        </p>
        ";

        return $this->getEmailWrapper("Actualización de Requisición", $innerHtml);
    }

    /**
     * Versión texto plano para cambio de estado
     */
    private function crearTextoPlanoCambioEstado($datos)
    {
        return "ACTUALIZACIÓN DE REQUISICIÓN - GECO PROATAM\n\n" .
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

            $innerHtml = "
            <div style='text-align: center; margin-bottom: 25px;'>
                <div style='background-color: #fee2e2; color: #dc2626; width: 50px; height: 50px; line-height: 50px; border-radius: 50%; font-size: 24px; margin: 0 auto 15px;'>
                </div>
                <h2 style='margin:0; color: #dc2626; font-size: 20px;'>Alerta de Exceso de Presupuesto</h2>
                <p style='margin: 5px 0 0 0; color: #64748b; font-size: 14px;'>Subcontratos superan el costo directo autorizado</p>
            </div>

            <p style='font-size: 15px; line-height: 1.6; color: #4a5568;'>
                Hola <strong>{$nombreDestinatario}</strong>, se ha detectado un exceso en los subcontratos registrados para la siguiente obra:
            </p>

            <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; margin: 20px 0;'>
                <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 14px; line-height: 1.6;'>
                    <tr>
                        <td width='50%' style='padding: 10px 15px; font-weight: 600; color: #475569; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;'>Obra:</td>
                        <td width='50%' style='padding: 10px 15px; color: #0d2535; border-bottom: 1px solid #e2e8f0;'><strong>{$datos['obra_nombre']}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 15px; font-weight: 600; color: #475569; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;'>Costo Directo Autorizado:</td>
                        <td style='padding: 10px 15px; color: #407656; border-bottom: 1px solid #e2e8f0;'><strong>\${$datos['costo_directo']}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 15px; font-weight: 600; color: #475569; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;'>Total Subcontratos:</td>
                        <td style='padding: 10px 15px; color: #dc2626; border-bottom: 1px solid #e2e8f0;'><strong>\${$datos['suma_subcontratos']}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 15px; font-weight: 600; color: #475569; background-color: #f8fafc;'>Monto de Exceso:</td>
                        <td style='padding: 10px 15px; color: #b91c1c; font-size: 16px;'><strong>\${$datos['exceso']}</strong></td>
                    </tr>
                </table>
            </div>

            <div style='background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 4px; margin-bottom: 25px;'>
                <p style='margin: 0; font-size: 14px; color: #92400e;'>
                    <strong>Nota:</strong> El monto de la obra no ha sido modificado. Se requiere su revisión para determinar si se autoriza un ajuste.
                </p>
            </div>

            <p style='text-align: center;'>
                <a href='{$this->baseUrl}projects/details_obra.php?id={$datos['obra_id']}' style='background-color: #dc2626; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                    Ver Detalles de la Obra
                </a>
            </p>
            ";

            $this->mail->Body = $this->getEmailWrapper("Alerta de Presupuesto", $innerHtml);
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $innerHtml));

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando alerta de exceso de subcontratos: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar formulario de satisfacción al cliente al término de un proyecto
     */
    public function enviarFormularioSatisfaccion($emailCliente, $nombreCliente, $datosProyecto)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($emailCliente, $nombreCliente);

            $this->mail->Subject = "Encuesta de Satisfacción - {$datosProyecto['nombre_proyecto']}";

            $innerHtml = "
            <div style='text-align: center; margin-bottom: 25px;'>
                <div style='background-color: #ecfdf5; color: #059669; width: 60px; height: 60px; line-height: 60px; border-radius: 50%; font-size: 28px; margin: 0 auto 15px;'>
                </div>
                <h2 style='margin:0; color: #407656; font-size: 22px;'>Encuesta de Satisfacción</h2>
                <p style='margin: 5px 0 0 0; color: #64748b; font-size: 15px;'>Tu opinión es vital para nosotros</p>
            </div>

            <p style='font-size: 16px; line-height: 1.6; color: #4a5568;'>
                Estimado/a <strong>{$nombreCliente}</strong>, el proyecto <strong>{$datosProyecto['nombre_proyecto']}</strong> ha concluido satisfactoriamente.
            </p>

            <p style='font-size: 15px; line-height: 1.6; color: #4a5568;'>
                Nos gustaría conocer tu experiencia trabajando con nosotros para seguir mejorando la calidad de nuestros servicios.
            </p>

            <div style='margin: 25px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;'>
                <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 14px;'>
                    <tr>
                        <td width='40%' style='padding: 5px 0; font-weight: 600; color: #64748b;'>No. Contrato:</td>
                        <td width='60%' style='padding: 5px 0; color: #0d2535;'>{$datosProyecto['numero_contrato']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 5px 0; font-weight: 600; color: #64748b;'>Fecha término:</td>
                        <td style='padding: 5px 0; color: #0d2535;'>{$datosProyecto['fecha_fin']}</td>
                    </tr>
                </table>
            </div>

            <div style='background-color: #f0fdf4; border-left: 4px solid #407656; padding: 15px; border-radius: 4px; margin-bottom: 30px;'>
                <p style='margin: 0; font-size: 14px; color: #065f46;'>
                    <strong>¿Sabías que?</strong> Responder esta encuesta toma menos de 2 minutos y nos ayuda enormemente.
                </p>
            </div>

            <p style='text-align: center;'>
                <a href='{$datosProyecto['link_formulario']}' style='background-color: #407656; color: #ffffff; padding: 14px 35px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block; font-size: 16px;'>
                    Responder Encuesta
                </a>
            </p>

            <p style='text-align: center; margin-top: 15px;'>
                <small style='color: #94a3b8;'>Si el botón no funciona, copia este enlace:<br>{$datosProyecto['link_formulario']}</small>
            </p>
            ";

            $this->mail->Body = $this->getEmailWrapper("Valoramos tu opinión", $innerHtml, "Este mensaje es una invitación para mejorar nuestro servicio en <strong>GECO PROATAM</strong>.");
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $innerHtml));

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando formulario de satisfacción: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Envía un correo de bienvenida a un nuevo usuario con su contraseña temporal
     */
    public function enviarCorreoBienvenida($destinatario, $nombres, $apellidos, $contraseña_temporal)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($destinatario, $nombres . ' ' . $apellidos);

            $this->mail->Subject = 'Bienvenido a GECO PROATAM - Tu cuenta ha sido creada';

            $innerHtml = "
            <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
                Hola <strong>{$nombres} {$apellidos}</strong>, tu cuenta ha sido creada exitosamente en nuestro sistema.
            </p>

            <h2 style='font-size: 18px; color: #0d2535; border-bottom: 2px solid #f0f4f8; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;'>Credenciales de acceso</h2>
            
            <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #113557; padding: 20px; border-radius: 4px; margin-bottom: 25px;'>
                <table width='100%' cellpadding='0' cellspacing='0' border='0' style='font-size: 15px; line-height: 1.6;'>
                    <tr>
                        <td width='35%' style='padding: 8px 0; font-weight: 600; color: #113557;'>Correo:</td>
                        <td width='65%' style='padding: 8px 0; color: #334155;'><strong>{$destinatario}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: 600; color: #113557;'>Contraseña temporal:</td>
                        <td style='padding: 8px 0; color: #d63384; font-weight: bold; font-size: 1.2em; letter-spacing: 2px;'>{$contraseña_temporal}</td>
                    </tr>
                </table>
            </div>

            <div style='background-color: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 25px;'>
                <p style='margin: 0 0 5px 0; font-weight: 600; color: #856404; font-size: 14px;'>Importante:</p>
                <ul style='margin: 0; padding-left: 20px; font-size: 14px; color: #856404; line-height: 1.6;'>
                    <li>Esta contraseña es temporal y debe ser cambiada en tu primer acceso</li>
                    <li>Guarda esta información de manera segura</li>
                    <li>No compartas tus credenciales con nadie</li>
                </ul>
            </div>

            <p style='text-align: center; margin-top: 35px;'>
                <a href='{$this->baseUrl}login.php' style='background-color: #113557; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                    Acceder al Sistema
                </a>
            </p>
            <p style='margin-top: 25px; font-size: 14px; color: #64748b; text-align: center;'>
                Si tienes algún problema para acceder, contacta al departamento de sistemas.
            </p>
            ";

            $this->mail->Body = $this->getEmailWrapper("¡Bienvenido a GECO PROATAM!", $innerHtml);
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], ["\n", "\n", "\n\n"], $innerHtml));

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo de bienvenida: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Envía un correo con el código de recuperación de contraseña
     */
    public function enviarCorreoRecuperacion($email, $nombres, $apellidos, $token)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $nombres . ' ' . $apellidos);

            $this->mail->Subject = 'Código de Recuperación - GECO PROATAM';

            $innerHtml = "
            <p style='font-size: 16px; line-height: 1.6; color: #4a5568; margin-top: 0;'>
                Hola <strong>{$nombres} {$apellidos}</strong>, has solicitado restablecer tu contraseña. Utiliza el siguiente código de verificación:
            </p>

            <div style='text-align: center; margin: 30px 0;'>
                <div style='background-color: #f8fafc; border: 2px dashed #113557; padding: 25px; border-radius: 8px; display: inline-block; min-width: 200px;'>
                    <h3 style='color: #64748b; margin-top: 0; margin-bottom: 15px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>Código de Verificación</h3>
                    <div style='font-size: 36px; font-weight: bold; color: #113557; letter-spacing: 8px; font-family: monospace;'>{$token}</div>
                    <p style='margin: 15px 0 0 0; font-size: 13px; color: #dc3545; font-weight: 600;'>
                        Este código expira en 15 minutos
                    </p>
                </div>
            </div>

            <div style='background-color: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 25px;'>
                <p style='margin: 0 0 5px 0; font-weight: 600; color: #856404; font-size: 14px;'>Importante:</p>
                <ul style='margin: 0; padding-left: 20px; font-size: 14px; color: #856404; line-height: 1.6;'>
                    <li>No compartas este código con nadie</li>
                    <li>Si no solicitaste este código, ignora este mensaje</li>
                </ul>
            </div>

            <p style='margin-top: 25px; font-size: 14px; color: #64748b; text-align: center;'>
                Si tienes problemas para verificar tu cuenta, contacta al departamento de sistemas.
            </p>
            ";

            $this->mail->Body = $this->getEmailWrapper("Recuperación de Contraseña", $innerHtml);
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], ["\n", "\n", "\n\n"], $innerHtml));

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo de recuperación: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}
