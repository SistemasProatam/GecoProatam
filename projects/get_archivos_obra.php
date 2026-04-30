<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$obra_id = $_GET['obra_id'] ?? 0;

$sql = "SELECT *, DATE_FORMAT(fecha_subida, '%d/%m/%Y %H:%i') as fecha_subida 
        FROM obra_adjuntos 
        WHERE obra_id = ? 
        ORDER BY fecha_subida DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $obra_id);
$stmt->execute();
$result = $stmt->get_result();

$archivos = [];
while ($row = $result->fetch_assoc()) {
    $archivos[] = $row;
}

echo json_encode(['archivos' => $archivos]);
?>

