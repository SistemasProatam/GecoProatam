<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

include(__DIR__ . "/../conexion.php");

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID de orden de compra no proporcionado");
}

$id = intval($_GET['id']);

// Obtener la ruta del comprobante
$sql = "SELECT comprobante_pago FROM ordenes_compra WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$orden = $result->fetch_assoc();

if (!$orden || empty($orden['comprobante_pago'])) {
    die("Comprobante no encontrado");
}

$rutaArchivo = $orden['comprobante_pago'];

// Validar que el archivo existe
if (!file_exists($rutaArchivo)) {
    die("Archivo no encontrado en el servidor");
}

// Obtener información del archivo
$nombreArchivo = basename($rutaArchivo);
$extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));
$tamaño = filesize($rutaArchivo);

// Determinar MIME type
$tiposMime = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$mimetype = $tiposMime[$extension] ?? 'application/octet-stream';

// Determinar si visualizar o descargar
$visualizables = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
$modoDescarga = isset($_GET['download']) && $_GET['download'] == 1;

if (!$modoDescarga && in_array($extension, $visualizables)) {
    // Visualizar en navegador
    header('Content-Type: ' . $mimetype);
    header('Content-Length: ' . $tamaño);
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    if (ob_get_level()) ob_end_clean();
    readfile($rutaArchivo);
} else {
    // Descargar como archivo
    header('Content-Type: ' . $mimetype);
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . $tamaño);
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    if (ob_get_level()) ob_end_clean();
    readfile($rutaArchivo);
}

$conn->close();
?>
