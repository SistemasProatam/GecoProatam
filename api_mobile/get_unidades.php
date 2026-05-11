<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';
require_once __DIR__ . '/_core/auth.php';

$auth = require_auth();

try {
    $result = $conn->query("SELECT id, nombre FROM unidades WHERE activo=1 ORDER BY nombre ASC");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    json_response(200, 'success', 'Unidades obtenidas', $data);
} catch (Throwable $e) {
    error_log('[api_mobile/get_unidades] ' . $e->getMessage());
    json_response(500, 'error', 'Error al obtener unidades');
}
