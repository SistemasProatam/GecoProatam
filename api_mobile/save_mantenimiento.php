<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/auth.php';
require_once __DIR__ . '/../EmailHandler.php';

$auth = require_auth();
$userId = (int)$auth['uid'];

try {
    // Capturar datos del POST (FormData)
    $nombres            = $_POST['nombres']            ?? '';
    $correo_corporativo = $_POST['correo_corporativo'] ?? '';
    $departamento       = $_POST['departamento']       ?? '';
    $asunto             = $_POST['asunto']             ?? '';
    $sistema_afectado   = $_POST['sistema_afectado']   ?? '';
    $urgencia           = $_POST['urgencia']           ?? '';
    $descripcion        = $_POST['descripcion']        ?? '';

    // Activo (opcionales)
    $activo_id     = intval($_POST['activo_id']    ?? 0);
    $activo_codigo = $_POST['activo_codigo']  ?? '';
    $activo_nombre = $_POST['activo_nombre']  ?? '';
    $activo_tipo   = $_POST['activo_tipo']    ?? '';

    // Validaciones básicas
    if (empty($nombres) || empty($correo_corporativo) || empty($asunto) || empty($descripcion)) {
        json_response(422, 'error', 'Faltan campos obligatorios');
    }

    // Preparar datos para EmailHandler
    $datosSoporte = [
        'nombres' => $nombres,
        'correo_corporativo' => $correo_corporativo,
        'departamento' => $departamento,
        'asunto' => $asunto,
        'sistema_afectado' => $sistema_afectado,
        'urgencia' => $urgencia,
        'descripcion' => $descripcion,
        'user_id' => $userId,
        'adjuntos' => [], // El móvil por ahora no manda adjuntos en este módulo según el ViewModel actual
        'activo_id' => $activo_id,
        'activo_codigo' => $activo_codigo,
        'activo_nombre' => $activo_nombre,
        'activo_tipo' => $activo_tipo
    ];

    // Procesar archivos adjuntos si los hubiera (enviados como archivos[])
    if (!empty($_FILES['archivos']['name'][0])) {
        $totalArchivos = count($_FILES['archivos']['name']);
        $archivosProcesados = [];
        for ($i = 0; $i < $totalArchivos; $i++) {
            if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_OK) {
                $archivosProcesados[] = [
                    'name' => $_FILES['archivos']['name'][$i],
                    'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
                    'type' => $_FILES['archivos']['type'][$i]
                ];
            }
        }
        $datosSoporte['adjuntos'] = $archivosProcesados;
    }

    // Enviar email usando la clase EmailHandler
    $emailHandler = new EmailHandler();
    
    // Generar número de ticket
    $ticketId = 'TKT-' . date('Ymd') . '-' . rand(1000, 9999);
    
    // Enviar email a soporte
    $emailHandler->enviarSolicitudSoporte($datosSoporte);
    
    // Enviar confirmación al usuario
    $emailHandler->enviarConfirmacionUsuario(
        $datosSoporte['correo_corporativo'], 
        $datosSoporte['nombres'], 
        $ticketId
    );

    json_response(200, 'success', 'Solicitud enviada correctamente', [
        'ticket' => $ticketId
    ]);

} catch (Throwable $e) {
    error_log('[api_mobile/save_mantenimiento] ' . $e->getMessage());
    json_response(500, 'error', 'No se pudo procesar la solicitud: ' . $e->getMessage());
}
