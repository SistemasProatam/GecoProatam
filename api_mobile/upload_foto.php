<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';
require_once __DIR__ . '/_core/auth.php';

require_method('POST');
$auth = require_auth();

try {
    // La App envía obraId (camelCase en TypeScript, lo recibimos vía POST)
    $obra_id = post_int('obraId', true);
    $comentario = post_string('comment', false) ?? '';
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        json_response(422, 'error', 'No se recibió ninguna foto o hubo un error en la subida');
    }

    // Directorio de destino
    $uploadDir = __DIR__ . '/../uploads/obras/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true)) {
            throw new Exception('No se pudo crear el directorio de destino en el servidor');
        }
    }

    $tmpName = $_FILES['photo']['tmp_name'];
    $originalName = $_FILES['photo']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'jpg');
    
    // Nombre único para evitar colisiones
    $uniqueName = 'avance_' . $obra_id . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $uniqueName;
    
    // Ruta que se guardará en BD (relativa a la raíz para compatibilidad con la web)
    $relativePath = 'uploads/obras/' . $uniqueName;

    if (move_uploaded_file($tmpName, $targetPath)) {
        // Guardar en la tabla obra_adjuntos del sistema PROATAM
        $sql = "INSERT INTO obra_adjuntos (obra_id, nombre_archivo, ruta_archivo, fecha_subida) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        
        // Usamos el comentario como nombre descriptivo si existe
        $displayName = !empty($comentario) ? mb_substr($comentario, 0, 200) : $originalName;
        
        $stmt->bind_param("iss", $obra_id, $displayName, $relativePath);
        
        if ($stmt->execute()) {
            json_response(200, 'success', 'Avance de obra subido correctamente', [
                'id' => $stmt->insert_id,
                'ruta' => $relativePath
            ]);
        } else {
            // Limpieza en caso de fallo en BD
            if (file_exists($targetPath)) unlink($targetPath);
            throw new Exception('Error al registrar el archivo en la base de datos: ' . $stmt->error);
        }
    } else {
        throw new RuntimeException('Error crítico: No se pudo mover el archivo al directorio final');
    }

} catch (InvalidArgumentException $e) {
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_mobile/upload_foto] ' . $e->getMessage());
    json_response(500, 'error', 'Error interno al procesar la foto: ' . $e->getMessage());
}
