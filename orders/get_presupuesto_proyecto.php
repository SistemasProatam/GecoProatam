<?php

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$proyecto_id = $_GET['proyecto_id'] ?? 0;

if ($proyecto_id <= 0) {
    echo json_encode(['error' => 'ID de proyecto inválido']);
    exit;
}

// Obtener información básica del proyecto (solo para mostrar)
$sql_proyecto = "SELECT p.id, p.nombre_proyecto, p.costo_directo as total_proyecto
                 FROM proyectos p 
                 WHERE p.id = ?";
$stmt_proyecto = $conn->prepare($sql_proyecto);
$stmt_proyecto->bind_param("i", $proyecto_id);
$stmt_proyecto->execute();
$proyecto = $stmt_proyecto->get_result()->fetch_assoc();

if (!$proyecto) {
    echo json_encode(['error' => 'Proyecto no encontrado']);
    exit;
}

// Obtener obras del proyecto con su COSTO DIRECTO y gastos utilizados
$sql_obras = "SELECT 
                o.id, 
                o.numero_obra, 
                o.nombre_obra, 
                o.costo_directo as total_obra,
                (SELECT COALESCE(SUM(total), 0) FROM ordenes_compra 
                 WHERE obra_id = o.id AND estado NOT IN ('rechazado', 'devuelto')) as utilizado_obra
              FROM obras o 
              WHERE o.proyecto_id = ? 
              ORDER BY o.numero_obra";
$stmt_obras = $conn->prepare($sql_obras);
$stmt_obras->bind_param("i", $proyecto_id);
$stmt_obras->execute();
$obras_result = $stmt_obras->get_result();

$obras = [];
while ($obra = $obras_result->fetch_assoc()) {
    $total_obra = floatval($obra['total_obra']);
    $utilizado_obra = floatval($obra['utilizado_obra']);
    $disponible_obra = $total_obra - $utilizado_obra;
    
    $obras[] = [
        'id' => $obra['id'],
        'numero_obra' => $obra['numero_obra'],
        'nombre_obra' => $obra['nombre_obra'],
        'total' => $total_obra,           
        'utilizado' => $utilizado_obra,
        'disponible' => $disponible_obra
    ];
}

echo json_encode([
    'proyecto' => [
        'id' => $proyecto['id'],
        'nombre' => $proyecto['nombre_proyecto'],
        'total' => floatval($proyecto['total_proyecto'])
    ],
    'obras' => $obras
]);
?>