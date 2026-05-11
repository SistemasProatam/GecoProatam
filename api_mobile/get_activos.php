header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/auth.php';

require_auth();

try {
    $sql = "SELECT a.id, a.codigo, a.nombre, t.nombre AS tipo
            FROM activos a
            JOIN activo_tipos t ON a.tipo_id = t.id
            WHERE a.estatus = 'activo'
            ORDER BY a.nombre ASC";

    $result = $conn->query($sql);
    $activos = [];

    while ($row = $result->fetch_assoc()) {
        $activos[] = $row;
    }

    json_response(200, 'success', 'Activos recuperados', ['activos' => $activos]);
} catch (Throwable $e) {
    error_log('[api_mobile/get_activos] ' . $e->getMessage());
    json_response(500, 'error', 'Error al cargar activos');
}
