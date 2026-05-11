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
    $catalogo_id = post_int('catalogo_id', true);
    
    // Buscamos conceptos asociados al catálogo usando la tabla de unidades
    $query = "SELECT c.id, c.codigo_concepto as codigo, c.descripcion as nombre, u.nombre as unidad_nombre, 'producto' as tipo
              FROM conceptos c
              LEFT JOIN unidades u ON c.unidad_medida = u.id
              WHERE c.catalogo_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $catalogo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    json_response(200, 'success', 'Items del catálogo obtenidos', $data);
} catch (InvalidArgumentException $e) {
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_mobile/get_items_catalogo] ' . $e->getMessage());
    json_response(500, 'error', 'Error al obtener items del catálogo');
}
