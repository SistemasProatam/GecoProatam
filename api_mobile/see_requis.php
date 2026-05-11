<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


include __DIR__ . '/../conexion.php';

require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';
require_once __DIR__ . '/_core/auth.php';

require_method('POST');
require_auth();

try {
    // DIAGNÓSTICO: Vamos a ver qué llega exactamente
    error_log("DIAGNÓSTICO see_requis.php -> POST: " . json_encode($_POST));
    error_log("DIAGNÓSTICO see_requis.php -> GET: " . json_encode($_GET));
    error_log("DIAGNÓSTICO see_requis.php -> INPUT: " . file_get_contents('php://input'));

    // Intentar obtener el ID de cualquier lugar (POST o GET)
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        json_response(422, 'error', 'El campo id es obligatorio');
    }
    
    $id = (int)$id;

    $stmt = $conn->prepare("
        SELECT r.*, e.nombre AS entidad, c.nombre AS categoria,
               CONCAT(u.nombres, ' ', u.apellidos) AS solicitante,
               u.correo_corporativo,
               p.nombre_proyecto, o.nombre_obra, cat.nombre_catalogo
        FROM requisiciones r
        JOIN entidades e ON r.entidad_id = e.id
        JOIN categorias c ON r.categoria_id = c.id
        LEFT JOIN usuarios u ON r.solicitante_id = u.id
        LEFT JOIN proyectos p ON r.proyecto_id = p.id
        LEFT JOIN obras o ON r.obra_id = o.id
        LEFT JOIN catalogos cat ON r.catalogo_id = cat.id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $requisicion = $stmt->get_result()->fetch_assoc();

    if (!$requisicion) {
        json_response(404, 'error', 'Requisición no encontrada');
    }

    $stmt_items = $conn->prepare("
        SELECT ri.*, ps.nombre AS producto, ps.tipo,
               un.nombre AS unidad,
               con.codigo_concepto, con.nombre_concepto, con.numero_original
        FROM requisicion_items ri
        JOIN productos_servicios ps ON ri.producto_id = ps.id
        JOIN unidades un ON ri.unidad_id = un.id
        LEFT JOIN conceptos con ON ri.concepto_id = con.id
        WHERE ri.requisicion_id = ?
    ");
    $stmt_items->bind_param("i", $id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();

    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt_arch = $conn->prepare("
        SELECT id, nombre_archivo, ruta_archivo, tamaño_archivo, tipo_mime, fecha_subida
        FROM requisicion_archivos
        WHERE requisicion_id = ?
        ORDER BY fecha_subida DESC
    ");
    $stmt_arch->bind_param("i", $id);
    $stmt_arch->execute();
    $arch_result = $stmt_arch->get_result();

    $archivos = [];
    while ($row = $arch_result->fetch_assoc()) {
        $archivos[] = $row;
    }

    $comentario_rechazo = '';
    if (($requisicion['estado'] ?? '') === 'rechazado') {
        $stmt_com = $conn->prepare("
            SELECT comentario
            FROM requisicion_historial
            WHERE requisicion_id = ? AND accion = 'Rechazó requisición'
            ORDER BY fecha_cambio DESC
            LIMIT 1
        ");
        $stmt_com->bind_param("i", $id);
        $stmt_com->execute();
        $com_result = $stmt_com->get_result();
        if ($com_result->num_rows > 0) {
            $comentario_rechazo = $com_result->fetch_assoc()['comentario'] ?? '';
        }
    }

    $requisicion['items'] = $items;
    $requisicion['archivos'] = $archivos;
    $requisicion['comentario_rechazo'] = $comentario_rechazo;

    json_response(200, 'success', 'Consulta exitosa', $requisicion);
} catch (InvalidArgumentException $e) {
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_mobile/see_requis] ' . $e->getMessage());
    json_response(500, 'error', 'Error interno');
}
