<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

header('Content-Type: application/json');

// Obtener clientes activos ordenados por nombre_abreviado
$sql = "SELECT id, nombre, nombre_abreviado 
        FROM clientes 
        WHERE activo = 1 
        ORDER BY COALESCE(NULLIF(nombre_abreviado, ''), nombre) ASC";
        
$result = $conn->query($sql);

$clientes = [];
while ($row = $result->fetch_assoc()) {
    $clientes[] = $row;
}

echo json_encode($clientes);
?>


