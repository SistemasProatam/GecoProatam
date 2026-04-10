<?php
require_once __DIR__ . '/../config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proyecto_id = intval($_POST['proyecto_id']);
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

    // Verificar si el proyecto existe
    $sql_check_proyecto = "SELECT id, monto_designado FROM proyectos WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check_proyecto);
    $stmt_check->bind_param("i", $proyecto_id);
    $stmt_check->execute();
    $proyecto = $stmt_check->get_result()->fetch_assoc();

    if (!$proyecto) {
        echo json_encode(['status' => 'error', 'message' => 'El proyecto no existe']);
        exit;
    }

    // Verificar si ya existe una obra con el mismo número en este proyecto
    $sql_check_obra = "SELECT id FROM obras WHERE proyecto_id = ? AND numero_obra = ?";
    $stmt_check_obra = $conn->prepare($sql_check_obra);
    $stmt_check_obra->bind_param("is", $proyecto_id, $numero_obra);
    $stmt_check_obra->execute();
    $result_check = $stmt_check_obra->get_result();

    if ($result_check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe una obra con este número en el proyecto']);
        exit;
    }

    // Insertar obra
    $sql = "INSERT INTO obras (proyecto_id, numero_obra, nombre_obra, descripcion, 
            fecha_inicio, fecha_fin, monto_designado, costo_directo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssdd", 
        $proyecto_id, $numero_obra, $nombre_obra, $descripcion,
        $fecha_inicio, $fecha_fin, $monto_designado, $costo_directo
    );

    if ($stmt->execute()) {
        $obra_id = $conn->insert_id;
        
        // Crear registro en presupuesto_control para la obra
        $sql_presupuesto = "INSERT INTO presupuesto_control 
                           (proyecto_id, obra_id, tipo, monto_designado, costo_directo, costo_directo_utilizado) 
                           VALUES (?, ?, 'obra', ?, ?, 0)";
        $stmt_presupuesto = $conn->prepare($sql_presupuesto);
        $stmt_presupuesto->bind_param("iidd", $proyecto_id, $obra_id, $monto_designado, $costo_directo);
        $stmt_presupuesto->execute();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Obra creada correctamente',
            'obra_id' => $obra_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error al crear la obra: ' . $conn->error
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>

