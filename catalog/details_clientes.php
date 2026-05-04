<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    echo "<p class='text-danger'>Cliente no encontrado</p>";
    exit;
}

function mostrarValor($valor) {
    return trim($valor) !== ''
        ? nl2br(htmlspecialchars($valor))
        : '<em>No especificado</em>';
}
?>

<div class="text-start">
    <div class="mb-3">
        <strong>Nombre:</strong><br>
        <?= htmlspecialchars($cliente['nombre']) ?>
    </div>

    <div class="mb-3">
        <strong>Nombre abreviado:</strong><br>
        <?= mostrarValor($cliente['nombre_abreviado'] ?? '') ?>
    </div>

    <div class="mb-3">
        <strong>RFC:</strong><br>
        <?= mostrarValor($cliente['rfc'] ?? '') ?>
    </div>

    <div class="mb-3">
        <strong>Dirección:</strong><br>
        <?= mostrarValor($cliente['direccion'] ?? '') ?>
    </div>

    <div class="mb-3">
        <strong>Fecha de creación:</strong><br>
        <?= date('d/m/Y H:i', strtotime($cliente['fecha_creacion'])) ?>
    </div>
</div>



