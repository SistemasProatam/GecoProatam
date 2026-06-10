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

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Detalles del Usuario | GECO PROATAM</title>

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
        <i class="fa-solid fa-arrow-left"></i> Volver al Listado
      </a>
      <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-geco-secondary">
        <i class="fa-solid fa-pen-to-square"></i> Editar
      </a>
    </div>
  </div>

  <!-- Profile Card Header -->
  <div class="orders-card profile-header-card mb-4">
    <div class="d-flex flex-column flex-md-row align-items-center gap-4">
      <div class="user-avatar">
        <?php if ($user['foto_jpg']): ?>
          <img src="../uploads/usuarios/<?= htmlspecialchars($user['foto_jpg']) ?>"
            alt="Foto de <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>"
            class="rounded-circle profile-avatar">
        <?php else: ?>
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold profile-avatar-placeholder">
            <?= getInitials($user['nombres'], $user['apellidos']) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="text-center text-md-start">
        <h2 class="fw-bold mb-1 profile-name"><?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></h2>
        <span class="badge bg-light text-dark fw-semibold px-3 py-2 border profile-dept-badge">
          <i class="fa-solid fa-building me-1"></i> <?= htmlspecialchars($user['departamento'] ?? 'Sin departamento') ?>
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
          <span class="oc-card-header__title"><i class="fa-solid fa-address-card"></i> Información Personal</span>
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
            <a href="mailto:<?= htmlspecialchars($user['correo_corporativo'] ?? '') ?>" class="text-decoration-none fw-bold info-link-corp"><?= htmlspecialchars($user['correo_corporativo'] ?? '-') ?></a>
          </div>
          <div class="info-item">
            <strong>Correo Personal</strong>
            <a href="mailto:<?= htmlspecialchars($user['correo_personal'] ?? '') ?>" class="text-decoration-none info-link-personal"><?= htmlspecialchars($user['correo_personal'] ?? '-') ?></a>
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
          <span class="oc-card-header__title"><i class="fa-solid fa-phone"></i> Contacto de Emergencia</span>
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
      <span class="oc-card-header__title"><i class="fa-solid fa-list-check"></i> Funciones y Actividades a Cargo</span>
    </div>
    <div class="oc-card-body">
      <?php if (!empty($user['funciones_actividades'])): ?>
        <p class="text-dark mb-0 profile-activities-text"><?= htmlspecialchars($user['funciones_actividades']) ?></p>
      <?php else: ?>
        <p class="text-muted mb-0"><i class="fa-solid fa-circle-info me-1"></i> No se han especificado funciones y actividades para este usuario.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Documentos del Expediente -->
  <div class="oc-card mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title"><i class="fa-regular fa-folder-open"></i> Documentos del Expediente</span>
    </div>
    <div class="oc-card-body">
      <div class="documents-grid">
        <?php
        $documentos = [
          'curriculum_pdf' => ['icon' => 'fa-regular fa-file-pdf', 'title' => 'Curriculum Vitae'],
          'identificacion_pdf' => ['icon' => 'fa-regular fa-id-card', 'title' => 'Identificación Oficial'],
          'acta_nacimiento_pdf' => ['icon' => 'fa-regular fa-file-lines', 'title' => 'Acta de Nacimiento'],
          'curp_pdf' => ['icon' => 'fa-regular fa-file', 'title' => 'CURP'],
          'situacion_fiscal_pdf' => ['icon' => 'fa-solid fa-file-invoice-dollar', 'title' => 'Constancia Fiscal'],
          'nss_pdf' => ['icon' => 'fa-solid fa-shield-halved', 'title' => 'Seguro Social (NSS)'],
          'comprobante_domicilio_pdf' => ['icon' => 'fa-solid fa-house', 'title' => 'Comprobante Domicilio'],
          'foto_jpg' => ['icon' => 'fa-regular fa-image', 'title' => 'Foto Infantil JPG'],
          'comprobante_estudios_pdf' => ['icon' => 'fa-solid fa-graduation-cap', 'title' => 'Comprobante Estudios'],
          'credencial_pdf' => ['icon' => 'fa-solid fa-id-badge', 'title' => 'Credencial Corp.'],
          'acuerdo_confidencialidad_pdf' => ['icon' => 'fa-solid fa-lock', 'title' => 'Acuerdo Confidencialidad']
        ];

        foreach ($documentos as $campo => $info):
          $archivo = $user[$campo] ?? null;
        ?>
          <div class="document-card">
            <div>
              <i class="<?= $info['icon'] ?> document-icon"></i>
              <h6><?= $info['title'] ?></h6>
            </div>
            <div class="mt-2">
              <?php if ($archivo): ?>
                <?php if ($campo === 'foto_jpg'): ?>
                  <a href="../uploads/usuarios/<?= htmlspecialchars($archivo) ?>" target="_blank" class="btn btn-sm btn-view w-100">
                    <i class="fa-regular fa-eye me-1"></i> Ver Foto
                  </a>
                <?php else: ?>
                  <a href="../uploads/usuarios/<?= htmlspecialchars($archivo) ?>" target="_blank" class="btn btn-sm btn-download w-100">
                    <i class="fa-solid fa-download me-1"></i> Descargar
                  </a>
                <?php endif; ?>
              <?php else: ?>
                <span class="no-document w-100 text-center"><i class="fa-solid fa-circle-xmark me-1"></i> No cargado</span>
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
      <span class="oc-card-header__title"><i class="fa-regular fa-file-lines"></i> Contratos Laborales</span>
    </div>
    <div class="oc-card-body">
      <div class="list-group list-group-flush">
        <?php
        $sql_contratos = "SELECT * FROM contratos_usuario WHERE usuario_id = ? ORDER BY tipo_contrato DESC";
        $stmt_contratos = $conn->prepare($sql_contratos);
        $stmt_contratos->bind_param("i", $id);
        $stmt_contratos->execute();
        $contratos = $stmt_contratos->get_result();

        if ($contratos->num_rows > 0):
          while ($contrato = $contratos->fetch_assoc()):
        ?>
            <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-3 border rounded mb-2 bg-light">
              <div class="d-flex align-items-center">
                <i class="fa-regular fa-file-pdf text-danger fs-4 me-3"></i>
                <div>
                  <h6 class="mb-0 fw-bold contrato-title">Contrato - <?= htmlspecialchars($contrato['tipo_contrato'] ?? 'Sin tipo') ?></h6>
                  <small class="text-muted">Documento Legal</small>
                </div>
              </div>
              <div>
                <a href="<?= htmlspecialchars($contrato['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-download">
                  <i class="fa-solid fa-download me-1"></i> Descargar
                </a>
              </div>
            </div>
          <?php
          endwhile;
        else:
          ?>
          <div class="orders-empty-state">
            <i class="fa-regular fa-folder-open"></i>
            <p>No hay contratos registrados para este usuario.</p>
          </div>
        <?php endif; ?>
        <?php $stmt_contratos->close(); ?>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>