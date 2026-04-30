<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obra_id = intval($_POST['id']);
    $numero_obra = trim($_POST['numero_obra']);
    $nombre_obra = trim($_POST['nombre_obra']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $monto_designado = floatval($_POST['monto_designado']);
    $costo_directo = floatval($_POST['costo_directo']);

    // Validaciones
    if (empty($numero_obra) || empty($nombre_obra) || empty($fecha_inicio) || empty($fecha_fin)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos obligatorios deben ser completados']);
        exit;
    }

    if ($fecha_fin < $fecha_inicio) {
        echo json_encode(['status' => 'error', 'message' => 'La fecha fin no puede ser anterior a la fecha inicio']);
        exit;
    }

    if ($monto_designado <= 0 || $costo_directo <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Los montos deben ser mayores a cero']);
        exit;
    }

    // Verificar si la obra existe y obtener informaciÃ³n
    $sql_check = "SELECT id, proyecto_id FROM obras WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $obra_id);
    $stmt_check->execute();
    $obra_info = $stmt_check->get_result()->fetch_assoc();

    if (!$obra_info) {
        echo json_encode(['status' => 'error', 'message' => 'Obra no encontrada']);
        exit;
    }

    // Verificar si el nÃºmero de obra ya existe en otro obra del mismo proyecto
    $sql_check_numero = "SELECT id FROM obras WHERE proyecto_id = ? AND numero_obra = ? AND id != ?";
    $stmt_check_numero = $conn->prepare($sql_check_numero);
    $stmt_check_numero->bind_param("isi", $obra_info['proyecto_id'], $numero_obra, $obra_id);
    $stmt_check_numero->execute();
    $result_check_numero = $stmt_check_numero->get_result();

    if ($result_check_numero->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe otra obra con este nÃºmero en el proyecto']);
        exit;
    }

    // Iniciar transacciÃ³n
    $conn->begin_transaction();

    try {
        // Obtener valores anteriores para el historial
        $sql_old = "SELECT * FROM obras WHERE id = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $obra_id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();

        // Actualizar obra
        $sql = "UPDATE obras SET 
                numero_obra = ?, nombre_obra = ?, descripcion = ?, 
                fecha_inicio = ?, fecha_fin = ?, monto_designado = ?, 
                costo_directo = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssddi", 
            $numero_obra, $nombre_obra, $descripcion,
            $fecha_inicio, $fecha_fin, $monto_designado, 
            $costo_directo, $obra_id
        );

        if ($stmt->execute()) {
            // Actualizar presupuesto_control de la obra
            $sql_presupuesto = "UPDATE presupuesto_control 
                               SET monto_designado = ?, costo_directo = ?
                               WHERE obra_id = ?";
            $stmt_presupuesto = $conn->prepare($sql_presupuesto);
            $stmt_presupuesto->bind_param("ddi", $monto_designado, $costo_directo, $obra_id);
            $stmt_presupuesto->execute();

            // Registrar cambios en el historial
            $campos = [
                'numero_obra', 'nombre_obra', 'descripcion', 'fecha_inicio',
                'fecha_fin', 'monto_designado', 'costo_directo'
            ];

            foreach ($campos as $campo) {
                if ($old_data[$campo] != $_POST[$campo]) {
                    $sql_historial = "INSERT INTO obra_historial 
                                     (obra_id, campo_modificado, valor_anterior, valor_nuevo, usuario_id) 
                                     VALUES (?, ?, ?, ?, ?)";
                    $stmt_historial = $conn->prepare($sql_historial);
                    $stmt_historial->bind_param("isssi", 
                        $obra_id, $campo, $old_data[$campo], $_POST[$campo], $_SESSION['user_id']
                    );
                    $stmt_historial->execute();
                }
            }

            $conn->commit();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Obra actualizada correctamente'
            ]);
        } else {
            $conn->rollback();
            echo json_encode([
                'status' => 'error', 
                'message' => 'Error al actualizar la obra: ' . $conn->error
            ]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'MÃ©todo no permitido']);
}
?>


