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

    // Validaciones básicas
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

    // Verificar si ya existe un proyecto con el mismo número de contrato
    $sql_check = "SELECT id FROM proyectos WHERE numero_contrato = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $numero_contrato);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya existe un proyecto con este número de contrato']);
        exit;
    }

    // Insertar proyecto
    $sql = "INSERT INTO proyectos (cliente_id, numero_licitacion, numero_contrato, nombre_proyecto, 
            descripcion, fecha_inicio, fecha_fin, monto_designado, monto_anticipo, monto_con_iva, costo_directo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssdddd", 
        $cliente_id, $numero_licitacion, $numero_contrato, $nombre_proyecto,
        $descripcion, $fecha_inicio, $fecha_fin, $monto_designado, 
        $monto_anticipo, $monto_con_iva, $costo_directo
    );

    if ($stmt->execute()) {
        $proyecto_id = $conn->insert_id;
        
        // Crear registro en presupuesto_control para el proyecto
        $sql_presupuesto = "INSERT INTO presupuesto_control 
                           (proyecto_id, obra_id, tipo, monto_designado, costo_directo, costo_directo_utilizado) 
                           VALUES (?, NULL, 'proyecto', ?, ?, 0)";
        $stmt_presupuesto = $conn->prepare($sql_presupuesto);
        $stmt_presupuesto->bind_param("idd", $proyecto_id, $monto_designado, $costo_directo);
        $stmt_presupuesto->execute();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Proyecto creado correctamente',
            'proyecto_id' => $proyecto_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error al crear el proyecto: ' . $conn->error
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>

