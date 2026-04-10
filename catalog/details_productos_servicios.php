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

// Preparar y ejecutar la consulta
$sql = "SELECT nombre, tipo, descripcion, fecha_creacion 
        FROM productos_servicios 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();

if (!$producto) {
    echo "<p class='text-danger'>Producto/Servicio no encontrado</p>";
    exit;
}
?>

<div class="text-start">
    <div class="mb-3">
        <strong>Nombre:</strong><br>
        <?= htmlspecialchars($producto['nombre']) ?>
    </div>
    
    <div class="mb-3">
        <strong>Tipo:</strong><br>
        <?= ucfirst($producto['tipo']) ?>
    </div>
    
    <?php
 if (!empty($producto['descripcion'])): ?>
    <div class="mb-3">
        <strong>Descripción:</strong><br>
        <?= nl2br(htmlspecialchars($producto['descripcion'])) ?>
    </div>
    <?php endif; ?>
    
    <div class="mb-3">
        <strong>Fecha de creación:</strong><br>
        <?= date('d/m/Y H:i', strtotime($producto['fecha_creacion'])) ?>
    </div>
</div>

<?php
// Cerrar conexión
$stmt->close();
$conn->close();
?>