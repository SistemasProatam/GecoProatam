<?php
error_log('[DEBUG] list_requis.php ejecutado');
error_log("DEBUG: Token recibido -> " . ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'VACÍO'));

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


include __DIR__ . '/../conexion.php';

require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';
require_once __DIR__ . '/_core/auth.php';

require_method('POST');
$auth = require_auth();
$userId = (int) $auth['uid'];
$userDept = (int) $auth['departamento_id'];

try {
    $busqueda = post_string('q', false, 120) ?? '';
    $estado_filtro = post_string('estado', false, 30) ?? '';
    $entidad_filtro = post_int('entidad', false);
    $pagina = post_int('page', false, 1) ?? 1;

    $por_pagina = 10;
    $offset = ($pagina - 1) * $por_pagina;

    $where = [];
    $params = [];
    $types = '';

    // ─── FILTRO DE PERMISOS (RESIDENTES) ───
    // Si es residente (13), solo ve lo suyo o lo de su obra.
    // Si es Admin (1, 2, 5, 16), ve todo.
    if (!in_array($userDept, [1, 2, 5, 16])) {
        $where[] = "(r.solicitante_id = ? OR r.obra_id IN (SELECT id FROM obras WHERE residente_id_1 = ? OR residente_id_2 = ? OR residente_id_3 = ?))";
        $params[] = $userId;
        $params[] = $userId;
        $params[] = $userId;
        $params[] = $userId;
        $types .= 'iiii';
    }

    if ($busqueda !== '') {
        $where[] = "(r.folio LIKE ? OR e.nombre LIKE ?)";
        $like = "%{$busqueda}%";
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }

    if ($estado_filtro !== '') {
        $where[] = "r.estado = ?";
        $params[] = $estado_filtro;
        $types .= 's';
    }

    if ($entidad_filtro !== null) {
        $where[] = "r.entidad_id = ?";
        $params[] = $entidad_filtro;
        $types .= 'i';
    }

    $where_sql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : "";

    $count_sql = "SELECT COUNT(*) AS total FROM requisiciones r JOIN entidades e ON r.entidad_id = e.id {$where_sql}";
    $stmt_count = $conn->prepare($count_sql);
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total = (int) $stmt_count->get_result()->fetch_assoc()['total'];

    $sql = "SELECT r.id, r.folio, r.estado, r.fecha_solicitud, r.descripcion,
                   e.nombre AS entidad, r.entidad_id,
                   CONCAT(u.nombres, ' ', u.apellidos) AS solicitante
            FROM requisiciones r
            JOIN entidades e ON r.entidad_id = e.id
            LEFT JOIN usuarios u ON r.solicitante_id = u.id
            {$where_sql}
            ORDER BY r.id DESC
            LIMIT ? OFFSET ?";

    $params2 = $params;
    $types2 = $types . 'ii';
    $params2[] = $por_pagina;
    $params2[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $result = $stmt->get_result();

    $requisiciones = [];
    while ($row = $result->fetch_assoc()) {
        $requisiciones[] = $row;
    }

    json_response(200, 'success', 'Consulta exitosa', [
        'requisiciones' => $requisiciones,
        'pagination' => [
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total_paginas' => (int) ceil($total / $por_pagina),
        ]
    ]);
} catch (InvalidArgumentException $e) {
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_mobile/list_requis] ' . $e->getMessage());
    json_response(500, 'error', 'Error interno ' . $e->getMessage());
}
