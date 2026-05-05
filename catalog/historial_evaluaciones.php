<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

$proveedor_id = $_GET['proveedor_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_evaluacion'])) {
    $evaluacion_id = $_POST['evaluacion_id'] ?? 0;
    if ($evaluacion_id > 0) {
        $sql_eliminar = "DELETE FROM evaluaciones_proveedores WHERE id = ? AND proveedor_id = ?";
        $stmt_eliminar = $conn->prepare($sql_eliminar);
        $stmt_eliminar->bind_param("ii", $evaluacion_id, $proveedor_id);
        if ($stmt_eliminar->execute()) $_SESSION['mensaje_exito'] = "Evaluación eliminada";
        else $_SESSION['mensaje_error'] = "Error al eliminar";
        header("Location: historial_evaluaciones.php?proveedor_id=" . $proveedor_id);
        exit();
    }
}

$sql_proveedor = "SELECT razon_social FROM proveedores WHERE id = ?";
$stmt_proveedor = $conn->prepare($sql_proveedor);
$stmt_proveedor->bind_param("i", $proveedor_id);
$stmt_proveedor->execute();
$proveedor = $stmt_proveedor->get_result()->fetch_assoc();

$sql_evaluaciones = "SELECT ep.*, u.nombres, u.apellidos FROM evaluaciones_proveedores ep LEFT JOIN usuarios u ON ep.usuario_creador_id = u.id WHERE ep.proveedor_id = ? ORDER BY ep.fecha_creacion DESC";
$stmt_evaluaciones = $conn->prepare($sql_evaluaciones);
$stmt_evaluaciones->bind_param("i", $proveedor_id);
$stmt_evaluaciones->execute();
$evaluaciones = $stmt_evaluaciones->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Evaluaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="hero-section">
    <div class="container hero-content">
        <div class="breadcrumb-custom">
            <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
            <span>/</span><a href="list_catalog.php?entidad=proveedores">Proveedores</a>
            <span>/</span><span>Historial de Evaluaciones</span>
        </div>
        <h1 class="hero-title">Historial: <?= htmlspecialchars($proveedor['razon_social'] ?? 'Proveedor') ?></h1>
    </div>
</div>

<div class="content-wrapper">
    <div class="container">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr><th>Fecha</th><th>Evaluador</th><th>Contrato</th><th>Puntuación</th><th>Resultado</th><th class="text-center">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($evaluaciones->num_rows > 0): ?>
                                <?php while ($eval = $evaluaciones->fetch_assoc()): 
                                    $res = $eval['resultado_final'];
                                    $badge = $res === 'excelente' ? 'bg-success' : ($res === 'bueno' ? 'bg-info' : ($res === 'regular' ? 'bg-warning text-dark' : 'bg-danger'));
                                ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($eval['fecha_creacion'])) ?></td>
                                        <td><?= htmlspecialchars($eval['nombres'] . ' ' . $eval['apellidos']) ?></td>
                                        <td><?= htmlspecialchars($eval['contrato_numero']) ?></td>
                                        <td><span class="fw-bold"><?= number_format($eval['total_puntuacion'], 1) ?></span></td>
                                        <td><span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $res) ?></span></td>
                                        <td class="text-center">
                                            <div class="btn-group gap-1">
                                                <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(<?= $eval['id'] ?>)"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="confirmarEliminacion(<?= $eval['id'] ?>)"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No hay evaluaciones registradas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function verDetalle(id) {
        UI.loading("Cargando...");
        fetch(`obtener_detalle_evaluacion.php?id=${id}`).then(r => r.json()).then(data => {
            UI.loading.hide();
            if (!data.success) { UI.toast.error("Error al cargar"); return; }
            const e = data.data;
            const html = `<div class="p-2">
                <div class="row g-2 mb-3">
                    <div class="col-6"><small class="text-muted d-block">Proveedor</small><b>${e.razon_social}</b></div>
                    <div class="col-6"><small class="text-muted d-block">RFC</small><b>${e.rfc || 'N/A'}</b></div>
                    <div class="col-6"><small class="text-muted d-block">Contrato</small><b>${e.contrato_numero}</b></div>
                    <div class="col-6"><small class="text-muted d-block">Fecha</small><b>${e.lugar_fecha}</b></div>
                </div>
                <hr>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1"><span>Calidad (30%)</span><span>${e.calidad_calificacion} → <b>${e.calidad_resultado}</b></span></div>
                    <div class="d-flex justify-content-between mb-1"><span>Entregas (25%)</span><span>${e.cumplimiento_entregas_calificacion} → <b>${e.cumplimiento_entregas_resultado}</b></span></div>
                    <div class="d-flex justify-content-between mb-1"><span>Precio/Cond. (20%)</span><span>${e.precio_condiciones_calificacion} → <b>${e.precio_condiciones_resultado}</b></span></div>
                    <div class="d-flex justify-content-between mb-1"><span>Legal (15%)</span><span>${e.cumplimiento_legal_calificacion} → <b>${e.cumplimiento_legal_resultado}</b></span></div>
                    <div class="d-flex justify-content-between mb-1"><span>Atención (10%)</span><span>${e.atencion_servicio_calificacion} → <b>${e.atencion_servicio_resultado}</b></span></div>
                </div>
                <div class="bg-light p-3 rounded text-center"><div class="small text-muted">PUNTUACIÓN TOTAL</div><div class="h2 mb-0 text-primary fw-bold">${e.total_puntuacion} pts</div></div>
                ${e.observaciones ? `<div class="mt-3 small"><b>Observaciones:</b><p class="mb-0 text-muted">${e.observaciones}</p></div>` : ''}
            </div>`;
            UI.modal({ title: "Detalle de Evaluación", html: html });
        });
    }

    function confirmarEliminacion(id) {
        UI.confirm({ title: "¿Eliminar evaluación?", message: "Esta acción no se puede deshacer.", danger: true }).then(conf => {
            if (!conf) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="evaluacion_id" value="${id}"><input type="hidden" name="eliminar_evaluacion" value="1">`;
            document.body.appendChild(form);
            form.submit();
        });
    }
</script>
</body>
</html>
