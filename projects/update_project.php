<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proyecto_id = intval($_POST['id']);
    $cliente_id = $_POST['cliente_id'] ?? null;
    $numero_licitacion = trim($_POST['numero_licitacion']);
    $numero_contrato = trim($_POST['numero_contrato']);
    $nombre_proyecto = trim($_POST['nombre_proyecto']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $monto_designado = floatval($_POST['monto_designado']);
    $monto_anticipo = floatval($_POST['monto_anticipo']);
    $monto_con_iva = floatval($_POST['monto_con_iva']);
    $costo_directo = floatval($_POST['costo_directo']);

    // Validaciones
    if (empty($numero_licitacion) || empty($numero_contrato) || empty($nombre_proyecto) || 
        empty($fecha_inicio) || empty($fecha_fin)) {
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

    // Verificar si el proyecto existe
    $sql_check = "SELECT id FROM proyectos WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $proyecto_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Proyecto no encontrado']);
        exit;
    }

    // Verificar si el número de contrato ya existe en otro proyecto
    $sql_check_contrato = "SELECT id FROM proyectos WHERE numero_contrato = ? AND id != ?";
    $stmt_check_contrato = $conn->prepare($sql_check_contrato);
    $stmt_check_contrato->bind_param("si", $numero_contrato, $proyecto_id);
    $stmt_check_contrato->execute();
    $result_check_contrato = $stmt_check_contrato->get_result();

    if ($result_check_contrato->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe otro proyecto con este número de contrato']);
        exit;
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener valores anteriores para el historial
        $sql_old = "SELECT * FROM proyectos WHERE id = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $proyecto_id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();

        // Actualizar proyecto
        $sql = "UPDATE proyectos SET 
                cliente_id = ?, numero_licitacion = ?, numero_contrato = ?, 
                nombre_proyecto = ?, descripcion = ?, fecha_inicio = ?, 
                fecha_fin = ?, monto_designado = ?, monto_anticipo = ?, 
                monto_con_iva = ?, costo_directo = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssddddi", 
            $cliente_id, $numero_licitacion, $numero_contrato, $nombre_proyecto,
            $descripcion, $fecha_inicio, $fecha_fin, $monto_designado, 
            $monto_anticipo, $monto_con_iva, $costo_directo, $proyecto_id
        );

        if ($stmt->execute()) {
            // Actualizar presupuesto_control del proyecto
            $sql_presupuesto = "UPDATE presupuesto_control 
                               SET monto_designado = ?, costo_directo = ?
                               WHERE proyecto_id = ? AND obra_id IS NULL";
            $stmt_presupuesto = $conn->prepare($sql_presupuesto);
            $stmt_presupuesto->bind_param("ddi", $monto_designado, $costo_directo, $proyecto_id);
            $stmt_presupuesto->execute();

            // Registrar cambios en el historial
            $campos = [
                'cliente_id', 'numero_licitacion', 'numero_contrato', 'nombre_proyecto',
                'descripcion', 'fecha_inicio', 'fecha_fin', 'monto_designado',
                'monto_anticipo', 'monto_con_iva', 'costo_directo'
            ];

            foreach ($campos as $campo) {
                if ($old_data[$campo] != $_POST[$campo]) {
                    $sql_historial = "INSERT INTO proyecto_historial 
                                     (proyecto_id, campo_modificado, valor_anterior, valor_nuevo, usuario_id) 
                                     VALUES (?, ?, ?, ?, ?)";
                    $stmt_historial = $conn->prepare($sql_historial);
                    $stmt_historial->bind_param("isssi", 
                        $proyecto_id, $campo, $old_data[$campo], $_POST[$campo], $_SESSION['user_id']
                    );
                    $stmt_historial->execute();
                }
            }

            $conn->commit();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Proyecto actualizado correctamente'
            ]);
        } else {
            $conn->rollback();
            echo json_encode([
                'status' => 'error', 
                'message' => 'Error al actualizar el proyecto: ' . $conn->error
            ]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>