<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
include(__DIR__ . "/../conexion.php");

header('Content-Type: application/json');

$proyecto_id = (int)($_GET['proyecto_id'] ?? 0);

if ($proyecto_id <= 0) {
    echo json_encode(['error' => 'ID de proyecto inválido']);
    exit;
}

// Información básica del proyecto
$stmt_proyecto = $conn->prepare(
    "SELECT id, nombre_proyecto, costo_directo AS total_proyecto
     FROM proyectos WHERE id = ?"
);
$stmt_proyecto->bind_param("i", $proyecto_id);
$stmt_proyecto->execute();
$proyecto = $stmt_proyecto->get_result()->fetch_assoc();

if (!$proyecto) {
    echo json_encode(['error' => 'Proyecto no encontrado']);
    exit;
}

// Obras del proyecto con datos de presupuesto desde las vistas
$stmt_obras = $conn->prepare(
    "SELECT o.id, o.numero_obra, o.nombre_obra,
            o.costo_directo                                    AS total,
            COALESCE(vpo.utilizado_pagado, 0)                 AS utilizado,
            COALESCE(vpo.comprometido_tentativo, 0)           AS comprometido,
            COALESCE(vpo.total_monto_contratos, 0)            AS total_contratos,
            (o.costo_directo - COALESCE(vpo.total_monto_contratos, 0)) AS disponible
     FROM obras o
     LEFT JOIN vista_presupuesto_obra vpo ON vpo.obra_id = o.id
     WHERE o.proyecto_id = ?
     ORDER BY o.numero_obra ASC"
);
$stmt_obras->bind_param("i", $proyecto_id);
$stmt_obras->execute();
$obras_result = $stmt_obras->get_result();

$obras = [];
while ($obra = $obras_result->fetch_assoc()) {
    $obras[] = [
        'id'             => $obra['id'],
        'numero_obra'    => $obra['numero_obra'],
        'nombre_obra'    => $obra['nombre_obra'],
        'total'          => floatval($obra['total']),           // costo_directo
        'utilizado'      => floatval($obra['utilizado']),       // pagado real
        'comprometido'   => floatval($obra['comprometido']),    // OC activas no pagadas
        'total_contratos'=> floatval($obra['total_contratos']), // suma subcontratos
        'disponible'     => floatval($obra['disponible']),      // costo_directo - contratos
    ];
}

echo json_encode([
    'proyecto' => [
        'id'     => $proyecto['id'],
        'nombre' => $proyecto['nombre_proyecto'],
        'total'  => floatval($proyecto['total_proyecto']),
    ],
    'obras' => $obras,
]);
?>

