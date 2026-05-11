<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/auth.php';

$auth = require_auth();
$userId = $auth['uid'];

// Asegurar que la tabla tenga las columnas necesarias (Solo una vez)
$conn->query("ALTER TABLE obra_avances ADD COLUMN IF NOT EXISTS catalogo_id INT DEFAULT NULL AFTER obra_id");
$conn->query("ALTER TABLE obra_avances ADD COLUMN IF NOT EXISTS concepto_id INT DEFAULT NULL AFTER catalogo_id");

// Actualizar los estados permitidos en el ENUM
$conn->query("ALTER TABLE obra_avances MODIFY COLUMN estado ENUM('pendiente', 'en proceso', 'pausado', 'terminado') NOT NULL DEFAULT 'pendiente'");



try {

    error_log("DEBUG obra_id: " . ($_POST['obra_id'] ?? 'NO ENVIADO'));
    error_log("DEBUG catalogo_id: " . ($_POST['catalogo_id'] ?? 'NO ENVIADO'));
    error_log("DEBUG concepto_id: " . ($_POST['concepto_id'] ?? 'NO ENVIADO'));
    error_log("DEBUG POST completo: " . print_r($_POST, true));

    $obra_id = isset($_POST['obra_id']) ? intval($_POST['obra_id']) : 0;
    $catalogo_id = isset($_POST['catalogo_id']) ? intval($_POST['catalogo_id']) : null;
    $concepto_id = isset($_POST['concepto_id']) ? intval($_POST['concepto_id']) : null;
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'avance';


    $descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';

    if ($obra_id <= 0) {
        json_response(422, 'error', 'Obra ID inválida');
    }

    $fotos_rutas = [];
    if (isset($_FILES['foto'])) {
        $files = $_FILES['foto'];
        $uploadDir = __DIR__ . '/../uploads/avances/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        // Si es un solo archivo o múltiples (PHP lo estructura diferente si se usa [])
        $file_count = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $file_count; $i++) {
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            if ($error === UPLOAD_ERR_OK) {
                $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (empty($ext)) $ext = 'jpg';
                
                $fileName = "avance_" . time() . "_" . uniqid() . "." . $ext;
                $targetFile = $uploadDir . $fileName;

                if ($size > 0) {
                    if (move_uploaded_file($tmpName, $targetFile)) {
                        $fotos_rutas[] = 'uploads/avances/' . $fileName;
                    } else {
                        error_log("[api_mobile/save_avance] Error al mover archivo: " . $name);
                    }
                }
            }
        }
    }



    $foto_json = count($fotos_rutas) > 0 ? json_encode($fotos_rutas) : null;

    $estado = isset($_POST['estado']) ? $_POST['estado'] : 'pendiente';
    $permitidos_estados = ['pendiente', 'en proceso', 'pausado', 'terminado'];
    if (!in_array($estado, $permitidos_estados)) {
        $estado = 'pendiente';
    }

    $stmt = $conn->prepare("
        INSERT INTO obra_avances (obra_id, catalogo_id, concepto_id, usuario_id, tipo, descripcion, foto_ruta, estado) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiiissss", $obra_id, $catalogo_id, $concepto_id, $userId, $tipo, $descripcion,  $foto_json, $estado);





    
    if ($stmt->execute()) {
        json_response(200, 'success', 'Avance registrado correctamente', [
            'fotos_procesadas' => count($fotos_rutas),
            'fotos_json' => $foto_json
        ]);
    } else {
        throw new Exception("Error DB: " . $stmt->error);
    }
} catch (Throwable $e) {
    json_response(500, 'error', $e->getMessage());
}
