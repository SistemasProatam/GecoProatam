<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM categorias WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$categoria = $stmt->get_result()->fetch_assoc();

if (!$categoria) {
    echo "<p class='text-danger'>CategorÃ­a no encontrada</p>";
    exit;
}
?>

<div class="text-start">
    <div class="mb-3">
        <strong>Nombre:</strong><br>
        <?= htmlspecialchars($categoria['nombre']) ?>
    </div>
    
    <div class="mb-3">
        <strong>DescripciÃ³n:</strong><br>
        <?php
$desc = trim($categoria['descripcion'] ?? '');
            echo $desc !== '' ? nl2br(htmlspecialchars($desc)) : '<em>Sin descripciÃ³n</em>';
        ?>
    </div>

    <div class="mb-3">
        <strong>Fecha de creaciÃ³n:</strong><br>
        <?= date('d/m/Y H:i', strtotime($categoria['fecha_creacion'])) ?>
    </div>
</div>


