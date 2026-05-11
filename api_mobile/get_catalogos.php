<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';
require_once __DIR__ . '/_core/auth.php';

// Protegemos el endpoint
require_auth();

try {
    $obra_id = post_int('obra_id', true);

    $stmt = $conn->prepare("SELECT id, nombre_catalogo FROM catalogos WHERE obra_id = ? ORDER BY nombre_catalogo ASC");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    json_response(200, 'success', 'Catálogos obtenidos', $data);
} catch (InvalidArgumentException $e) {
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_mobile/get_catalogos] ' . $e->getMessage());
    json_response(500, 'error', 'Error al obtener catálogos');
}
