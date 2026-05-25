<?php
require_once __DIR__ . "/../conexion.php";

header('Content-Type: application/json');

const SATISFACCION_WEBHOOK_SECRET = 'Proatamsgi_05-05-20-26-satisfaccion';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit();
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$secret = trim($payload['secret'] ?? '');
if (!hash_equals(SATISFACCION_WEBHOOK_SECRET, $secret)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$historial_id = intval($payload['historial_id'] ?? 0);
$p1 = intval($payload['p1'] ?? 0);
$p2 = intval($payload['p2'] ?? 0);
$p3 = intval($payload['p3'] ?? 0);
$p4 = intval($payload['p4'] ?? 0);
$p5 = intval($payload['p5'] ?? 0);
$observaciones = trim($payload['observaciones'] ?? '');

$calificaciones = [$p1, $p2, $p3, $p4, $p5];

if ($historial_id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'historial_id invalido']);
    exit();
}

foreach ($calificaciones as $calificacion) {
    if ($calificacion < 1 || $calificacion > 5) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Las calificaciones deben estar entre 1 y 5']);
        exit();
    }
}

$stmt_check = $conn->prepare("SELECT id FROM historial_proyectos_cliente WHERE id = ?");
$stmt_check->bind_param("i", $historial_id);
$stmt_check->execute();
$registro = $stmt_check->get_result()->fetch_assoc();

if (!$registro) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Registro de historial no encontrado']);
    exit();
}

$promedio = round(array_sum($calificaciones) / count($calificaciones), 2);

if ($promedio >= 4.5) {
    $resultado_final = 'excelente';
} elseif ($promedio >= 4) {
    $resultado_final = 'bueno';
} elseif ($promedio >= 3) {
    $resultado_final = 'regular';
} else {
    $resultado_final = 'no_aprobado';
}

$sql_update = "
    UPDATE historial_proyectos_cliente
    SET
        estado = 'contestado',
        fecha_respuesta = NOW(),
        p1 = ?,
        p2 = ?,
        p3 = ?,
        p4 = ?,
        p5 = ?,
        observaciones = ?,
        promedio_puntuacion = ?,
        resultado_final = ?
    WHERE id = ?
";

$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param(
    "iiiiisdsi",
    $p1,
    $p2,
    $p3,
    $p4,
    $p5,
    $observaciones,
    $promedio,
    $resultado_final,
    $historial_id
);
$stmt_update->execute();

echo json_encode([
    'success' => true,
    'message' => 'Respuesta registrada correctamente',
    'historial_id' => $historial_id,
    'promedio' => $promedio,
    'resultado_final' => $resultado_final,
]);
