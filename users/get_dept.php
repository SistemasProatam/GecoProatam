<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/../conexion.php";

$result = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
$departamentos = [];
while($row = $result->fetch_assoc()){
    $departamentos[] = $row;
}
echo json_encode($departamentos);
$conn->close();



