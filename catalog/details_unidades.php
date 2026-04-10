<?php
require_once __DIR__ . '/../config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM unidades WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$unidad = $stmt->get_result()->fetch_assoc();

if (!$unidad) {
    echo "<p class='text-danger'>Unidad no encontrada</p>";
    exit;
}
?>

<div class="text-start">
    <div class="mb-3">
        <strong>Nombre:</strong><br>
        <?= htmlspecialchars($unidad['nombre']) ?>
    </div>
    
    <div class="mb-3">
        <strong>Fecha de creación:</strong><br>
        <?= date('d/m/Y H:i', strtotime($unidad['fecha_creacion'])) ?>
    </div>
</div>

