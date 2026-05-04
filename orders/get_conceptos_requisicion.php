<?php
require_once __DIR__ . "/../conexion.php";

$requisicion_id = $_GET['requisicion_id'] ?? null;
if (!$requisicion_id) {
    echo json_encode(["error" => "Falta ID de requisición"]);
    exit;
}

$sql = "SELECT id, concepto_id 
        FROM requisicion_items 
        WHERE requisicion_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $requisicion_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        "id" => $row['id'],
        "concepto_id" => $row['concepto_id']
    ];
}

echo json_encode($items);



