<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

$id = intval($_GET['id'] ?? 0);
$sql = "SELECT u.*, d.nombre AS departamento FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: list_users.php?error=Usuario no encontrado"); exit; }

$departamentos = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario - <?= htmlspecialchars($user['nombres']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
    <style>
        .form-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .section-title { color: #1a3a5c; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px; }
        .doc-item { background: #f8fafc; border-radius: 12px; padding: 15px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .current-file { font-size: 0.75rem; color: #64748b; margin-top: 5px; }
    </style>
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="hero-section">
    <div class="container hero-content">
        <div class="breadcrumb-custom">
            <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
            <span>/</span><a href="list_users.php">Usuarios</a>
            <span>/</span><span>Editar</span>
        </div>
        <h1 class="hero-title">Editar Usuario</h1>
    </div>
</div>

<div class="content-wrapper">
    <div class="container">
        <div class="form-card">
            <form id="formUser" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                
                <h5 class="section-title"><i class="bi bi-person-badge me-2"></i>Información Personal</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label small fw-bold">Nombres</label><input type="text" name="nombres" class="form-control" value="<?= htmlspecialchars($user['nombres']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Apellidos</label><input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($user['apellidos']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Email Corporativo</label><input type="email" name="correo_corporativo" class="form-control" value="<?= htmlspecialchars($user['correo_corporativo']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Email Personal</label><input type="email" name="correo_personal" class="form-control" value="<?= htmlspecialchars($user['correo_personal'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Departamento</label>
                        <select name="departamento_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php while ($dep = $departamentos->fetch_assoc()): ?>
                                <option value="<?= $dep['id'] ?>" <?= $user['departamento_id']==$dep['id']?'selected':'' ?>><?= htmlspecialchars($dep['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <h5 class="section-title"><i class="bi bi-folder2-open me-2"></i>Documentación</h5>
                <div class="row g-3 mb-4">
                    <?php 
                    $docs = ['curriculum_pdf'=>'CV','identificacion_pdf'=>'Identificación','acta_nacimiento_pdf'=>'Acta Nac.','curp_pdf'=>'CURP','situacion_fiscal_pdf'=>'Sit. Fiscal','nss_pdf'=>'NSS','comprobante_domicilio_pdf'=>'Comp. Domicilio'];
                    foreach ($docs as $key => $label): ?>
                        <div class="col-md-4">
                            <div class="doc-item">
                                <label class="form-label small fw-bold"><?= $label ?></label>
                                <input type="file" name="<?= $key ?>" class="form-control form-control-sm" accept=".pdf">
                                <?php if ($user[$key]): ?><div class="current-file text-truncate"><i class="bi bi-check-circle-fill text-success"></i> <?= $user[$key] ?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center gap-3 d-flex justify-content-center mt-5">
                    <a href="details_user.php?id=<?= $user['id'] ?>" class="btn btn-lg btn-outline-secondary px-5">Cancelar</a>
                    <button type="submit" class="btn btn-lg btn-primary px-5"><i class="bi bi-save me-2"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('formUser').addEventListener('submit', function(e) {
        e.preventDefault();
        UI.loading("Actualizando usuario...");
        fetch('update_user.php', { method: 'POST', body: new FormData(this) }).then(r => r.json()).then(r => {
            UI.loading.hide();
            if (r.status === 'success') {
                UI.toast.success("Usuario actualizado");
                setTimeout(() => location.href = 'details_user.php?id=<?= $user['id'] ?>', 1500);
            } else UI.toast.error(r.message);
        }).catch(() => { UI.loading.hide(); UI.toast.error("Error de conexión"); });
    });
</script>
</body>
</html>
