<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$id = intval($_GET['id'] ?? 0);

// Consulta con JOIN para obtener nombre del departamento
$sql = "SELECT u.*, d.nombre AS departamento
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    header("Location: list_users.php?error=Usuario no encontrado");
    exit;
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="list_users.php">Registro de Usuarios</a>
        <span class="separator">›</span>
        <span>Detalles del Usuario</span>
      </nav>
      <h1 class="orders-page-title">Perfil de <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></h1>
    </div>
    <div class="d-flex gap-2">
      <a href="list_users.php" class="btn-geco-outline">
        <i class="bi bi-arrow-left"></i> Volver al Listado
      </a>
      <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-geco-primary">
        <i class="bi bi-pencil"></i> Editar Usuario
      </a>
    </div>
  </div>

  <!-- Profile Card Header -->
  <div class="orders-card mb-4" style="padding: 24px;">
    <div class="d-flex flex-column flex-md-row align-items-center gap-4">
      <div class="user-avatar">
        <?php if ($user['foto_jpg']): ?>
          <img src="../uploads/usuarios/<?= htmlspecialchars($user['foto_jpg']) ?>"
               alt="Foto de <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>"
               class="rounded-circle shadow-sm" style="width: 110px; height: 110px; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1) !important;">
        <?php else: ?>
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 110px; height: 110px; border: 4px solid #fff; background: var(--gray-600, #4b5563); font-size: 2.2rem; letter-spacing: 1px; box-shadow: 0 4px 10px rgba(0,0,0,0.1) !important;">
            <?= getInitials($user['nombres'], $user['apellidos']) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="text-center text-md-start">
        <h2 class="fw-bold mb-1" style="color: var(--s-800,#0f172a); font-size: 1.25rem;"><?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></h2>
        <span class="badge bg-light text-dark fw-semibold px-3 py-2 border" style="font-size: 0.8rem; border-radius: 20px;">
          <i class="bi bi-building me-1" style="color: var(--p-500,#407656);"></i> <?= htmlspecialchars($user['departamento'] ?? 'Sin departamento') ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Info Grid -->
  <div class="row g-4 mb-4">
    <!-- Información Personal -->
    <div class="col-lg-7">
      <div class="oc-card h-100">
        <div class="oc-card-header">
          <span class="oc-card-header__title"><i class="bi bi-person-vcard"></i> Información Personal</span>
        </div>
        <div class="oc-card-body">
          <div class="info-item">
            <strong>Nombres</strong>
            <span><?= htmlspecialchars($user['nombres'] ?? '-') ?></span>
          </div>
          <div class="info-item">
            <strong>Apellidos</strong>
            <span><?= htmlspecialchars($user['apellidos'] ?? '-') ?></span>
          </div>
          <div class="info-item">
            <strong>Correo Corporativo</strong>
            <a href="mailto:<?= htmlspecialchars($user['correo_corporativo'] ?? '') ?>" class="text-decoration-none fw-bold" style="color: var(--p-600,#2d5a40);"><?= htmlspecialchars($user['correo_corporativo'] ?? '-') ?></a>
          </div>
          <div class="info-item">
            <strong>Correo Personal</strong>
            <a href="mailto:<?= htmlspecialchars($user['correo_personal'] ?? '') ?>" class="text-decoration-none" style="color: var(--s-800,#0f172a);"><?= htmlspecialchars($user['correo_personal'] ?? '-') ?></a>
          </div>
          <div class="info-item">
            <strong>Teléfono Personal</strong>
            <span><?= htmlspecialchars($user['telefono_personal'] ?? '-') ?></span>
          </div>
          <div class="info-item">
            <strong>Fecha de Ingreso</strong>
            <span><?= htmlspecialchars($user['fecha_ingreso'] ?? '-') ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Contacto de Emergencia -->
    <div class="col-lg-5">
      <div class="oc-card h-100">
        <div class="oc-card-header">
          <span class="oc-card-header__title"><i class="bi bi-telephone"></i> Contacto de Emergencia</span>
        </div>
        <div class="oc-card-body">
          <div class="info-item">
            <strong>Nombre Completo</strong>
            <span><?= htmlspecialchars($user['contacto_emergencia_nombre'] ?? '-') ?></span>
          </div>
          <div class="info-item">
            <strong>Parentesco</strong>
            <span><?= htmlspecialchars($user['contacto_emergencia_parentesco'] ?? '-') ?></span>
          </div>
          <div class="info-item">
            <strong>Número de Celular</strong>
            <span class="fw-semibold text-dark"><?= htmlspecialchars($user['contacto_emergencia_telefono'] ?? '-') ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Funciones y Actividades -->
  <div class="oc-card mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title"><i class="bi bi-list-task"></i> Funciones y Actividades a Cargo</span>
    </div>
    <div class="oc-card-body">
      <?php if (!empty($user['funciones_actividades'])): ?>
        <p class="text-dark mb-0" style="line-height: 1.7; font-size: 0.92rem; white-space: pre-line;"><?= htmlspecialchars($user['funciones_actividades']) ?></p>
      <?php else: ?>
        <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i> No se han especificado funciones y actividades para este usuario.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Documentos del Expediente -->
  <div class="oc-card mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title"><i class="bi bi-folder"></i> Documentos del Expediente</span>
    </div>
    <div class="oc-card-body">
      <div class="documents-grid">
        <?php
        $documentos = [
            'curriculum_pdf' => ['icon' => 'bi-file-earmark-person', 'title' => 'Curriculum Vitae'],
            'identificacion_pdf' => ['icon' => 'bi-card-checklist', 'title' => 'Identificación Oficial'],
            'acta_nacimiento_pdf' => ['icon' => 'bi-file-earmark-text', 'title' => 'Acta de Nacimiento'],
            'curp_pdf' => ['icon' => 'bi-file-earmark-richtext', 'title' => 'CURP'],
            'situacion_fiscal_pdf' => ['icon' => 'bi-cash-coin', 'title' => 'Constancia Fiscal'],
            'nss_pdf' => ['icon' => 'bi-shield-check', 'title' => 'Seguro Social (NSS)'],
            'comprobante_domicilio_pdf' => ['icon' => 'bi-house', 'title' => 'Comprobante Domicilio'],
            'foto_jpg' => ['icon' => 'bi-camera', 'title' => 'Foto Infantil JPG'],
            'comprobante_estudios_pdf' => ['icon' => 'bi-mortarboard', 'title' => 'Comprobante Estudios'],
            'credencial_pdf' => ['icon' => 'bi-person-badge', 'title' => 'Credencial Corp.'],
            'acuerdo_confidencialidad_pdf' => ['icon' => 'bi-file-lock', 'title' => 'Acuerdo Confidencialidad']
        ];

        foreach ($documentos as $campo => $info):
            $archivo = $user[$campo] ?? null;
        ?>
          <div class="document-card">
            <div>
              <i class="bi <?= $info['icon'] ?> document-icon"></i>
              <h6><?= $info['title'] ?></h6>
            </div>
            <div class="mt-2">
              <?php if ($archivo): ?>
                <?php if ($campo === 'foto_jpg'): ?>
                  <a href="../uploads/usuarios/<?= htmlspecialchars($archivo) ?>" target="_blank" class="btn btn-sm btn-view w-100" style="background: rgba(64,118,86,0.08); color: var(--p-700,#2a523a); border: 1px solid rgba(64,118,86,0.2);">
                    <i class="bi bi-eye me-1"></i> Ver Foto
                  </a>
                <?php else: ?>
                  <a href="../uploads/usuarios/<?= htmlspecialchars($archivo) ?>" target="_blank" class="btn btn-sm btn-download w-100" style="background: rgba(64,118,86,0.06); color: var(--p-600,#2d5a40); border: 1px solid rgba(64,118,86,0.15);">
                    <i class="bi bi-download me-1"></i> Descargar
                  </a>
                <?php endif; ?>
              <?php else: ?>
                <span class="no-document w-100 text-center"><i class="bi bi-x-circle me-1"></i> No cargado</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Contratos -->
  <div class="oc-card mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title"><i class="bi bi-file-earmark-text"></i> Contratos Laborales</span>
    </div>
    <div class="oc-card-body">
      <div class="documents-grid">
        <?php
        $sql_contratos = "SELECT * FROM contratos_usuario WHERE usuario_id = ? ORDER BY tipo_contrato DESC";
        $stmt_contratos = $conn->prepare($sql_contratos);
        $stmt_contratos->bind_param("i", $id);
        $stmt_contratos->execute();
        $contratos = $stmt_contratos->get_result();

        if ($contratos->num_rows > 0):
            while ($contrato = $contratos->fetch_assoc()):
        ?>
              <div class="document-card">
                <div>
                  <i class="bi bi-file-earmark-pdf document-icon text-danger"></i>
                  <h6>Contrato - <?= htmlspecialchars($contrato['tipo_contrato'] ?? 'Sin tipo') ?></h6>
                </div>
                <div class="mt-2">
                  <a href="<?= htmlspecialchars($contrato['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-download w-100" style="background: rgba(64,118,86,0.06); color: var(--p-600,#2d5a40); border: 1px solid rgba(64,118,86,0.15);">
                    <i class="bi bi-download me-1"></i> Descargar
                  </a>
                </div>
              </div>
            <?php
            endwhile;
        else: ?>
          <div class="col-12 text-center py-3">
            <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i> No hay contratos registrados para este usuario.</p>
          </div>
        <?php endif; ?>
        <?php $stmt_contratos->close(); ?>
      </div>
    </div>
  </div>

</div>

<style>
/* oc-card-header y oc-card-body heredados directamente de orders-common.css */
.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--gray-100,#f3f4f6);
  font-size: 0.82rem;
}
.info-item:last-child {
  border-bottom: none;
}
.info-item strong {
  color: var(--gray-500,#6b7280);
  font-weight: 600;
}
.info-item span {
  color: var(--s-800,#0f172a);
  font-weight: 500;
}
.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}
.document-card {
  background: var(--gray-50,#f9fafb);
  border: 1px solid var(--gray-200,#e5e7eb);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  transition: all 0.2s ease-in-out;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 155px;
}
.document-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border-color: var(--p-300,#86efac);
  background: #fff;
}
.document-icon {
  font-size: 1.8rem;
  color: var(--p-500,#407656);
  margin-bottom: 8px;
  display: inline-block;
}
.document-card h6 {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--gray-700,#374151);
  margin-bottom: 8px;
  line-height: 1.3;
}
.btn-view, .btn-download {
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 8px;
  transition: all 0.2s;
}
.btn-view:hover, .btn-download:hover {
  opacity: 0.9;
}
.no-document {
  font-size: 0.75rem;
  background: var(--gray-100,#f3f4f6);
  color: var(--gray-400,#9ca3af);
  border: 1px solid var(--gray-200,#e5e7eb);
  padding: 6px 12px;
  border-radius: 8px;
  display: inline-block;
  font-weight: 600;
}
</style>

<?php include __DIR__ . "/../includes/footer.php"; ?>
