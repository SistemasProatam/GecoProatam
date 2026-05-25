<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../email_handler.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$historial_id = intval($_POST['historial_id'] ?? 0);

if ($historial_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de historial inválido']);
    exit();
}

// Obtener datos del historial, proyecto y cliente en una sola consulta
$sql = "
    SELECT 
        h.id AS historial_id,
        h.estado,
        p.nombre_proyecto,
        p.numero_contrato,
        DATE_FORMAT(p.fecha_fin, '%d/%m/%Y') AS fecha_fin,
        c.nombre AS nombre_cliente,
        c.email AS email_cliente
    FROM historial_proyectos_cliente h
    INNER JOIN proyectos p ON h.proyecto_id = p.id
    INNER JOIN clientes c  ON h.cliente_id  = c.id
    WHERE h.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $historial_id);
$stmt->execute();
$registro = $stmt->get_result()->fetch_assoc();

if (!$registro) {
    echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
    exit();
}

if ($registro['estado'] !== 'pendiente') {
    echo json_encode(['success' => false, 'message' => 'El formulario ya fue enviado anteriormente']);
    exit();
}

if (empty($registro['email_cliente'])) {
    echo json_encode(['success' => false, 'message' => 'El cliente no tiene email registrado']);
    exit();
}

// Construir link de Google Form con prefill.
// El campo "historial_id" debe existir en el Google Form como respuesta corta.
// Ese ID permite que Apps Script actualice el registro correcto cuando el cliente responda.
// Para obtener los entry.XXXX de tu form:
//   1. Abre el form en Chrome
//   2. Click derecho → Inspeccionar → Network
//   3. Llena el form y envía
//   4. Busca la petición POST y ve a "Payload" para ver todos los entry IDs
$FORM_BASE_URL = "https://docs.google.com/forms/d/e/1FAIpQLSf7-IhsaC6A7gJpkhLvhnhOd28YavcM0inGcUiKX6VXHG7rhA/viewform";
$FORM_ENTRY_HISTORIAL_ID = "entry.XXXXXXX";

$link_formulario = $FORM_BASE_URL . "?usp=pp_url"
    . "&{$FORM_ENTRY_HISTORIAL_ID}=" . urlencode($registro['historial_id']);

// ── Enviar correo ─────────────────────────────────────────────────────────────
$emailHandler = new EmailHandler();
$enviado = $emailHandler->enviarFormularioSatisfaccion(
    $registro['email_cliente'],
    $registro['nombre_cliente'],
    [
        'nombre_proyecto'  => $registro['nombre_proyecto'],
        'numero_contrato'  => $registro['numero_contrato'],
        'fecha_fin'        => $registro['fecha_fin'],
        'link_formulario'  => $link_formulario,
    ]
);

if (!$enviado) {
    echo json_encode(['success' => false, 'message' => 'Error al enviar el correo. Intenta nuevamente.']);
    exit();
}

// ── Actualizar estado en historial ────────────────────────────────────────────
$sql_update = "
    UPDATE historial_proyectos_cliente 
    SET estado = 'enviado', fecha_envio_formulario = NOW()
    WHERE id = ?
";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("i", $historial_id);
$stmt_update->execute();

echo json_encode([
    'success' => true,
    'message' => "Formulario enviado correctamente a {$registro['email_cliente']}"
]);
