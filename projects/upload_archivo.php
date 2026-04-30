<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// HEADER JSON debe ser lo PRIMERO
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'MÃ©todo no permitido']);
    exit;
}

// Obtener proyecto_id
$proyecto_id = $_POST['proyecto_id'] ?? 0;

if (empty($proyecto_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID de proyecto no especificado']);
    exit;
}

try {
    // Verificar nÃºmero mÃ¡ximo de archivos (5)
    $sqlCount = "SELECT COUNT(*) as total FROM proyecto_adjuntos WHERE proyecto_id = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("i", $proyecto_id);
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $count = $resultCount->fetch_assoc()['total'];
    
    if ($count >= 5) {
        throw new Exception('MÃ¡ximo 5 archivos permitidos por proyecto');
    }
    
    // Verificar que se enviÃ³ un archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibiÃ³ ningÃºn archivo o hay un error en la subida');
    }
    
    $archivo = $_FILES['archivo'];
    
    // Validar tipo de archivo
    $tipoArchivo = mime_content_type($archivo['tmp_name']);
    if ($tipoArchivo !== 'application/pdf') {
        throw new Exception('Solo se permiten archivos PDF');
    }
    
    // Validar tamaÃ±o (10MB)
    if ($archivo['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo no puede ser mayor a 10MB');
    }
    
    // Crear directorio si no existe
    $directorio = "uploads/proyectos/{$proyecto_id}/";
    if (!is_dir($directorio)) {
        if (!mkdir($directorio, 0777, true)) {
            throw new Exception('No se pudo crear el directorio de destino');
        }
    }
    
    // Generar nombre Ãºnico
    $nombreArchivo = uniqid() . '_' . basename($archivo['name']);
    $rutaArchivo = $directorio . $nombreArchivo;
    
    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $rutaArchivo)) {
        throw new Exception('Error al mover el archivo al servidor');
    }
    
    // Guardar en base de datos
    $sql = "INSERT INTO proyecto_adjuntos (proyecto_id, nombre_archivo, ruta_archivo) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("iss", $proyecto_id, $archivo['name'], $rutaArchivo);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Archivo subido correctamente'
        ]);
    } else {
        // Si falla la BD, eliminar el archivo fÃ­sico
        unlink($rutaArchivo);
        throw new Exception('Error al guardar en base de datos: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    // Log del error (opcional)
    error_log("Error en upload_archive.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}

// Cerrar conexiÃ³n
if (isset($conn)) {
    $conn->close();
}
?>


