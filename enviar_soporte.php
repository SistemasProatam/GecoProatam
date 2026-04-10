<?php
require_once __DIR__ . '/config.php';

// Activar todos los errores para debug PERO evitar output no JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); 

// Iniciar sesión y verificar usuario
session_start();
header('Content-Type: application/json');

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Buffer de salida para capturar posibles outputs no deseados
ob_start();

try {
    // VERIFICAR QUE EL USUARIO ESTÉ AUTENTICADO
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
        throw new Exception('Usuario no autenticado. Por favor, inicia sesión nuevamente.');
    }

    // Verificar que EmailHandler existe
    if (!file_exists('EmailHandler.php')) {
        throw new Exception('No se encontró el archivo EmailHandler.php');
    }
    
    require_once 'EmailHandler.php';

    // ============================================================
    // Campos generales
    // ============================================================
    $nombres            = trim($_POST['nombres']            ?? '');
    $correo_corporativo = trim($_POST['correo_corporativo'] ?? '');
    $departamento       = trim($_POST['departamento']       ?? '');
    $asunto             = trim($_POST['asunto']             ?? '');
    $sistema_afectado   = trim($_POST['sistema_afectado']   ?? '');
    $urgencia           = trim($_POST['urgencia']           ?? '');
    $descripcion        = trim($_POST['descripcion']        ?? '');

    // ============================================================
    // Activo relacionado (opcionales)
    // ============================================================
    $activo_id     = intval($_POST['activo_id']    ?? 0);
    $activo_codigo = trim($_POST['activo_codigo']  ?? '');
    $activo_nombre = trim($_POST['activo_nombre']  ?? '');
    $activo_tipo   = trim($_POST['activo_tipo']    ?? '');

    // Verificar sesión
    if ($correo_corporativo !== $_SESSION['user_email']) {
        throw new Exception('El correo electrónico no coincide con el usuario en sesión.');
    }

    // Validaciones básicas
    $camposObligatorios = [
        'nombres' => $nombres,
        'correo_corporativo' => $correo_corporativo,
        'asunto' => $asunto,
        'sistema_afectado' => $sistema_afectado,
        'urgencia' => $urgencia,
        'descripcion' => $descripcion
    ];

    $camposFaltantes = [];
    foreach ($camposObligatorios as $campo => $valor) {
        if (empty($valor)) {
            $camposFaltantes[] = $campo;
        }
    }

    if (!empty($camposFaltantes)) {
        throw new Exception('Faltan campos obligatorios: ' . implode(', ', $camposFaltantes));
    }

    if (!filter_var($correo_corporativo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El formato del email no es válido');
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
        'user_id' => $_SESSION['user_id'], // Incluir ID de usuario en sesión
        'adjuntos' => [],
        'activo_id' => $activo_id,
        'activo_codigo' => $activo_codigo,
        'activo_nombre' => $activo_nombre,
        'activo_tipo' => $activo_tipo
    ];

    // Procesar archivos adjuntos
    $archivosProcesados = [];
    if (!empty($_FILES['adjuntos']['name'][0])) {
        $totalArchivos = count($_FILES['adjuntos']['name']);
        
        if ($totalArchivos > 5) {
            throw new Exception('Máximo 5 archivos permitidos');
        }
        
        for ($i = 0; $i < $totalArchivos; $i++) {
            if ($_FILES['adjuntos']['error'][$i] === UPLOAD_ERR_OK) {
                if ($_FILES['adjuntos']['size'][$i] > 5 * 1024 * 1024) {
                    throw new Exception('El archivo ' . $_FILES['adjuntos']['name'][$i] . ' excede el tamaño máximo permitido (5MB)');
                }
                
                $archivosProcesados[] = [
                    'name' => $_FILES['adjuntos']['name'][$i],
                    'tmp_name' => $_FILES['adjuntos']['tmp_name'][$i],
                    'type' => $_FILES['adjuntos']['type'][$i]
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

    // Limpiar cualquier output no deseado antes del JSON
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Tu solicitud de soporte ha sido enviada correctamente.',
        'ticket' => $ticketId
    ]);

} catch (Exception $e) {
    // Limpiar buffer y enviar error JSON
    ob_clean();
    error_log("Error en enviar_soporte.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

// Asegurar que no hay nada después
exit;
?>

