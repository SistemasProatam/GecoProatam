<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

header('Content-Type: application/json');

$evaluacion_id = $_GET['id'] ?? 0;

if ($evaluacion_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit();
}

// Obtener detalles completos de la evaluación
$sql = "SELECT 
            ep.*,
            u.nombres,
            u.apellidos,
            CONCAT(u.nombres, ' ', u.apellidos) as evaluador_nombre
        FROM evaluaciones_proveedores ep
        LEFT JOIN usuarios u ON ep.usuario_creador_id = u.id
        WHERE ep.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $evaluacion_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Evaluación no encontrada']);
    exit();
}

$evaluacion = $result->fetch_assoc();

// Formatear los datos para la respuesta
$datos_formateados = [
    'razon_social' => $evaluacion['razon_social'],
    'rfc' => $evaluacion['rfc'],
    'lugar_fecha' => $evaluacion['lugar_fecha'],
    'contrato_numero' => $evaluacion['contrato_numero'],
    
    // Calificaciones
    'calidad_calificacion' => $evaluacion['calidad_calificacion'],
    'cumplimiento_entregas_calificacion' => $evaluacion['cumplimiento_entregas_calificacion'],
    'precio_condiciones_calificacion' => $evaluacion['precio_condiciones_calificacion'],
    'cumplimiento_legal_calificacion' => $evaluacion['cumplimiento_legal_calificacion'],
    'atencion_servicio_calificacion' => $evaluacion['atencion_servicio_calificacion'],
    
    // Resultados
    'calidad_resultado' => number_format($evaluacion['calidad_resultado'], 1),
    'cumplimiento_entregas_resultado' => number_format($evaluacion['cumplimiento_entregas_resultado'], 1),
    'precio_condiciones_resultado' => number_format($evaluacion['precio_condiciones_resultado'], 1),
    'cumplimiento_legal_resultado' => number_format($evaluacion['cumplimiento_legal_resultado'], 1),
    'atencion_servicio_resultado' => number_format($evaluacion['atencion_servicio_resultado'], 1),
    
    'total_puntuacion' => number_format($evaluacion['total_puntuacion'], 2),
    'resultado_final' => $evaluacion['resultado_final'],
    'observaciones' => $evaluacion['observaciones'],
    'responsables' => $evaluacion['responsables'],
    'fecha_creacion' => date('d/m/Y H:i', strtotime($evaluacion['fecha_creacion']))
];

echo json_encode(['success' => true, 'data' => $datos_formateados]);
?>

