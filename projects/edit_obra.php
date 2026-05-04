<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$id = $_GET['id'] ?? 0;

// Consulta corregida con JOIN
$stmt = $conn->prepare("SELECT 
        o.id, 
        o.proyecto_id, 
        p.nombre_proyecto, 
        o.numero_obra, 
        o.nombre_obra, 
        o.descripcion, 
        o.fecha_inicio, 
        o.fecha_fin, 
        o.monto_designado, 
        o.costo_directo 
    FROM obras o 
    LEFT JOIN proyectos p ON o.proyecto_id = p.id 
    WHERE o.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$obra = $stmt->get_result()->fetch_assoc();

if (!$obra) {
    echo json_encode(['error' => 'Obra no encontrada']);
    exit;
}

// Formatear fechas para el input date
$obra['fecha_inicio'] = date('Y-m-d', strtotime($obra['fecha_inicio']));
$obra['fecha_fin'] = date('Y-m-d', strtotime($obra['fecha_fin']));

echo json_encode($obra);
?>


