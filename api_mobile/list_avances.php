<?php
error_log('[DEBUG] list_avances.php ejecutado');
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

// --- LÓGICA DE PERMISOS DE BITÁCORA ---
$user_dept = (int) $auth['departamento_id'];
$user_id = (int) $auth['uid'];

// Si no es ninguno de los departamentos autorizados, bloquear.
$allowed_departments = [1, 2, 5, 13, 16];
if (!in_array($user_dept, $allowed_departments)) {
    json_response(403, 'error', 'No tienes permisos para acceder a esta sección.');
}

$es_residente = ($user_dept === 13);
// --------------------------------------

try {
    $proyecto_id = post_int('proyecto_id', false) ?? 0;
    $obra_id = post_int('obra_id', false) ?? 0;
    $tipo = post_string('tipo', false, 50) ?? '';
    $estado = post_string('estado', false, 50) ?? '';

    $page = max(1, post_int('page', false) ?? 1);
    $limit = 6;
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    $types = '';

    // APLICAR FILTRO DE RESIDENTE: Solo ve sus propios reportes
    if ($es_residente) {
        $where[] = "a.usuario_id = ?";
        $params[] = $user_id;
        $types .= 'i';
    }

    if ($proyecto_id > 0) {
        $where[] = "o.proyecto_id = ?";
        $params[] = $proyecto_id;
        $types .= 'i';
    }
    if ($obra_id > 0) {
        $where[] = "a.obra_id = ?";
        $params[] = $obra_id;
        $types .= 'i';
    }
    if ($tipo !== '') {
        $where[] = "a.tipo = ?";
        $params[] = $tipo;
        $types .= 's';
    }
    if ($estado !== '') {
        $where[] = "a.estado = ?";
        $params[] = $estado;
        $types .= 's';
    }

    $whereClause = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

    // Contar total
    $countSql = "SELECT COUNT(*) AS total FROM obra_avances a 
                 LEFT JOIN obras ob ON a.obra_id = ob.id
                 LEFT JOIN proyectos p ON ob.proyecto_id = p.id
                 $whereClause";
    $stmtCount = $conn->prepare($countSql);
    if ($types) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $total = $stmtCount->get_result()->fetch_assoc()['total'];

    // Consulta principal con datos relacionados
    $dataSql = "SELECT a.id, a.obra_id, a.catalogo_id, a.concepto_id, a.usuario_id, a.tipo, a.descripcion, a.foto_ruta, a.estado, a.fecha_reporte,
                       ob.nombre_obra, p.nombre_proyecto, u.nombres, u.apellidos,
                       cn.descripcion as nombre_concepto, cn.codigo_concepto as codigo_concepto

                FROM obra_avances a
                LEFT JOIN obras ob ON a.obra_id = ob.id
                LEFT JOIN proyectos p ON ob.proyecto_id = p.id
                LEFT JOIN usuarios u ON a.usuario_id = u.id
                LEFT JOIN conceptos cn ON a.concepto_id = cn.id
                $whereClause

                ORDER BY a.fecha_reporte DESC

                LIMIT ? OFFSET ?";

    $stmtData = $conn->prepare($dataSql);
    $paramTypes = $types . 'ii';
    $allParams = array_merge($params, [$limit, $offset]);
    $stmtData->bind_param($paramTypes, ...$allParams);
    $stmtData->execute();
    $result = $stmtData->get_result();

    $avances = [];
    while ($row = $result->fetch_assoc()) {
        $avances[] = $row;
    }

    json_response(200, 'success', 'OK', [
        'avances' => $avances,
        'total' => (int) $total,
        'page' => $page,
        'limit' => $limit
    ]);

} catch (Throwable $e) {
    error_log('[api_mobile/list_avances] ' . $e->getMessage());
    json_response(500, 'error', 'Error al listar avances' . $e->getMessage());
}

