<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM proveedores WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$proveedor = $stmt->get_result()->fetch_assoc();

if (!$proveedor) {
    echo "<p class='text-danger'>Proveedor no encontrado</p>";
    exit;
}
?>

<div class="text-start">
    <div class="mb-3">
        <strong>Razón Social:</strong><br>
        <?= htmlspecialchars($proveedor['razon_social'] ?? '') ?>
    </div>

    <div class="mb-3">
        <strong>Nombre Comercial:</strong><br>
        <?= htmlspecialchars($proveedor['nombre'] ?? 'No especificado') ?>
    </div>

    <div class="mb-3">
        <strong>RFC:</strong><br>
        <?= htmlspecialchars($proveedor['rfc'] ?? 'No especificado') ?>
    </div>

    <div class="mb-3">
        <strong>Teléfono:</strong><br>
        <?= htmlspecialchars($proveedor['telefono'] ?? 'No especificado') ?>
    </div>

    <div class="mb-3">
        <strong>Email:</strong><br>
        <?= htmlspecialchars($proveedor['email'] ?? 'No especificado') ?>
    </div>

    <div class="mb-3">
        <strong>Dirección:</strong><br>
        <?php
$direccion = trim($proveedor['direccion'] ?? '');
            echo $direccion !== ''
                ? nl2br(htmlspecialchars($direccion))
                : '<em>No especificada</em>';
        ?>
    </div>

    <div class="mb-3">
        <strong>Contacto:</strong><br>
        <?php
$contacto = trim($proveedor['contacto'] ?? '');
            echo $contacto !== ''
                ? htmlspecialchars($contacto)
                : '<em>No especificado</em>';
        ?>
    </div>

    <div class="mb-3">
        <strong>Fecha de creación:</strong><br>
        <?= !empty($proveedor['fecha_creacion'])
            ? date('d/m/Y H:i', strtotime($proveedor['fecha_creacion']))
            : '-' ?>
    </div>
</div>



