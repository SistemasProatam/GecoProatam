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
    $proyecto_id = post_int('proyecto_id', false);
    
    $where = [];
    $params = [];
    $types = "";

    if ($proyecto_id !== null && $proyecto_id > 0) {
        $where[] = "proyecto_id = ?";
        $params[] = $proyecto_id;
        $types .= "i";
    }

    $where_sql = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT id, nombre_obra, numero_obra, proyecto_id FROM obras $where_sql ORDER BY nombre_obra ASC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    json_response(200, 'success', 'Obras obtenidas', $data);
} catch (InvalidArgumentException $e) {
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_mobile/get_obras] ' . $e->getMessage());
    json_response(500, 'error', 'Error al obtener obras');
}
