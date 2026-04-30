<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

include(__DIR__ . "/../conexion.php");

if (!isset($_GET['id'])) {
    die("ID no proporcionado");
}

$id = intval($_GET['id']);

$sql = "SELECT nombre_archivo, ruta_archivo, tipo_mime, tamaño_archivo 
        FROM orden_compra_archivos 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$archivo = $result->fetch_assoc();

if (!$archivo) {
    die("Archivo no encontrado");
}

// Verificar que el archivo existe físicamente
if (!file_exists($archivo['ruta_archivo'])) {
    die("El archivo físico no existe");
}

// Headers para descarga
header('Content-Type: ' . $archivo['tipo_mime']);
header('Content-Disposition: attachment; filename="' . $archivo['nombre_archivo'] . '"');
header('Content-Length: ' . $archivo['tamaño_archivo']);
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($archivo['ruta_archivo']);
exit;
?>

