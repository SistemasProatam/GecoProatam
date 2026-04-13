<?php
// delete_cotizacion.php — Elimina FÍSICAMENTE una cotización de la BD
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

header('Content-Type: application/json; charset=UTF-8');

$dep_id_sesion  = $_SESSION['departamento_id'] ?? null;
$es_super_admin = ($_SESSION['departamento']   ?? '') === 'SUPER_ADMIN';
if (!$es_super_admin && !in_array($dep_id_sesion, [1, 2, 10, 16])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID no proporcionado']);
    exit;
}

include_once __DIR__ . '/../conexion.php';

// Verificar que existe antes de borrar
$check = $conn->prepare("SELECT id, folio FROM cotizaciones WHERE id = ? LIMIT 1");
$check->bind_param("i", $id);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Cotización no encontrada']);
    exit;
}

// Borrado físico real (DELETE, no UPDATE activo=0)
$stmt = $conn->prepare("DELETE FROM cotizaciones WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        'status'  => 'success',
        'message' => 'Cotización ' . $row['folio'] . ' eliminada permanentemente.',
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar: ' . $conn->error]);
}

$stmt->close();
$conn->close();