<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Obtener el ID del archivo
$archivo_id = $_GET['id'] ?? null;

if (!$archivo_id) {
    die("Error: ID de archivo no proporcionado.");
}

// Obtener informaciÃ³n del archivo
$sql = "SELECT nombre_archivo, ruta_archivo, tipo_mime 
        FROM requisicion_archivos 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $archivo_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Archivo no encontrado.");
}

$archivo = $result->fetch_assoc();
$ruta_completa = $archivo['ruta_archivo'];

// Verificar que el archivo existe
if (!file_exists($ruta_completa)) {
    die("Error: El archivo no existe en el servidor.");
}

// Configurar headers para visualizaciÃ³n en el navegador
header('Content-Type: ' . $archivo['tipo_mime']);
header('Content-Disposition: inline; filename="' . basename($archivo['nombre_archivo']) . '"');
header('Content-Length: ' . filesize($ruta_completa));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Limpiar el buffer de salida
ob_clean();
flush();

// Leer y enviar el archivo
readfile($ruta_completa);
exit;
?>


