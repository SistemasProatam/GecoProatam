<?php
// Deshabilitar completamente la salida de errores en este script
error_reporting(0);
ini_set('display_errors', '0');

// Limpiar cualquier salida anterior
if (ob_get_level()) ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar solicitud OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo no permitido']);
    exit();
}

include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';

// Verificar que response.php existe y tiene la funcion
if (!function_exists('json_response')) {
    function json_response($httpCode, $status, $message, $data = null, $errors = []) {
        http_response_code($httpCode);
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data ?? new stdClass(),
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    // Obtener el input crudo
    $input_raw = file_get_contents('php://input');

    // Decodificar el JSON
    $body = json_decode($input_raw, true);

    // Verificar si el JSON es valido
    if ($body === null) {
        json_response(400, 'error', 'JSON no valido: ' . json_last_error_msg());
    }

    // Obtener datos del body (corregido el typo)
    $id = isset($body['id']) ? (int)$body['id'] : (isset($body['avances_id']) ? (int)$body['avances_id'] : 0);
    $estado = isset($body['estado']) ? trim((string)$body['estado']) : '';

    // Validaciones
    if ($id <= 0) {
        json_response(422, 'error', 'ID de avance no válido');
    }

    if (empty($estado)) {
        json_response(422, 'error', 'Estado no puede estar vacío');
    }

    // CORREGIDO: Usar los estados correctos de la base de datos
    $permitidos = ['pendiente', 'en proceso', 'pausado', 'terminado'];
    if (!in_array($estado, $permitidos)) { 
        json_response(422, 'error', 'Estado no permitido. Valores permitidos: ' . implode(', ', $permitidos));
    }

    // Verificar autenticación (si existe)
    if (function_exists('require_auth')) {
        try {
            $auth = require_auth();
            $user_dept_id = (int)$auth['departamento_id'];
            $allowed_dpts = [1, 2, 5, 13, 16];

            if (!in_array($user_dept_id, $allowed_dpts)) {
                json_response(403, 'error', 'No tienes permiso para actualizar el estado de este avance');
            }
        } catch (Exception $e) {
            error_log('Auth warning: ' . $e->getMessage());
        }
    }

    // CORREGIDO: Usar el nombre correcto de la tabla 'obra_avances'
    $stmt = $conn->prepare("UPDATE obra_avances SET estado = ? WHERE id = ?");
    if (!$stmt) {
        json_response(500, 'error', 'Error en la preparación de la consulta: ' . $conn->error);
    }

    $stmt->bind_param("si", $estado, $id);

    // CORREGIDA LA LÓGICA: execute() retorna true en éxito
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            json_response(200, 'success', 'Estado del avance actualizado correctamente');
        } else {
            json_response(200, 'info', 'No se realizaron cambios (el estado ya era el mismo)');
        }
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    error_log('Error en update_avance_estado: ' . $e->getMessage());
    json_response(500, 'error', 'Error al actualizar estado: ' . $e->getMessage());  
} catch (Throwable $e) {
    error_log('Error inesperado en update_avance_estado: ' . $e->getMessage());
    json_response(500, 'error', 'Error inesperado al actualizar estado');
}