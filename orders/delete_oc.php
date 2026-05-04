<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Obtener ID
$id = $_GET['id'] ?? null;

if (!$id) {
    die("Error: ID no proporcionado.");
}

// Eliminar (CASCADE eliminará automáticamente items y archivos)
$sql = "DELETE FROM ordenes_compra WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: list_oc.php?msg=deleted");
} else {
    die("Error al eliminar: " . $stmt->error);
}

exit;
?>


