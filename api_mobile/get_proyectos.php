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
$auth = require_auth();

try {
    $user_dept = (int)$auth['departamento_id'];
    $user_id = (int)$auth['uid'];

    // En desarrollo/local, permitimos ver proyectos aunque la fecha haya pasado si no hay activos
    $date_filter = "WHERE fecha_fin >= CURDATE()";
    
    // Verificamos si hay proyectos activos primero
    $check_active = $conn->query("SELECT id FROM proyectos WHERE fecha_fin >= CURDATE() LIMIT 1");
    if ($check_active->num_rows === 0) {
        $date_filter = "WHERE 1=1"; // Mostrar todos si no hay activos (útil para pruebas locales)
    }

    if ($user_dept === 13) {
        // Residentes: Solo proyectos donde tengan obras asignadas
        $sql = "SELECT DISTINCT p.id, p.nombre_proyecto, p.numero_contrato 
                FROM proyectos p
                INNER JOIN obras o ON o.proyecto_id = p.id
                $date_filter AND (o.residente_id_1 = ? OR o.residente_id_2 = ? OR o.residente_id_3 = ?)
                ORDER BY p.nombre_proyecto ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // Otros roles autorizados: Ver proyectos según el filtro de fecha
        // Quitamos el alias 'p' para evitar errores si no hay JOIN
        $sql = "SELECT id, nombre_proyecto, numero_contrato FROM proyectos $date_filter ORDER BY nombre_proyecto ASC";
        $result = $conn->query($sql);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    json_response(200, 'success', 'Proyectos obtenidos', $data);
} catch (Throwable $e) {
    error_log('[api_mobile/get_proyectos] ' . $e->getMessage());
    json_response(500, 'error', 'Error al obtener proyectos');
}
