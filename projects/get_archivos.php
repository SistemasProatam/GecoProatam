<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$proyecto_id = $_GET['proyecto_id'] ?? 0;

$sql = "SELECT *, DATE_FORMAT(fecha_subida, '%d/%m/%Y %H:%i') as fecha_subida FROM proyecto_adjuntos WHERE proyecto_id = ? ORDER BY fecha_subida DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $proyecto_id);
$stmt->execute();
$result = $stmt->get_result();

$archivos = [];
while ($row = $result->fetch_assoc()) {
    $archivos[] = $row;
}

echo json_encode(['archivos' => $archivos]);
?>


