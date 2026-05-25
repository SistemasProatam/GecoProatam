<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

$id = intval($_GET['id'] ?? 0);
$sql = "SELECT u.*, d.nombre AS departamento 
        FROM usuarios u 
        LEFT JOIN departamentos d ON u.departamento_id = d.id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { 
    header("Location: list_users.php?error=Usuario no encontrado"); 
    exit; 
}

// Obtener departamentos para el select
$departamentos = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");

// Obtener contratos existentes
$contratos = [];
$sql_contratos = "SELECT * FROM contratos_usuario WHERE usuario_id = ? ORDER BY id ASC";
$stmt_contratos = $conn->prepare($sql_contratos);
$stmt_contratos->bind_param("i", $id);
$stmt_contratos->execute();
$res_contratos = $stmt_contratos->get_result();
while ($c = $res_contratos->fetch_assoc()) {
    $contratos[] = $c;
}
$stmt_contratos->close();
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
        <a href="details_user.php?id=<?= $user['id'] ?>">Detalles del Usuario</a>
        <span class="separator">›</span>
        <span>Editar Usuario</span>
      </nav>
      <h1 class="orders-page-title">Editar Perfil — <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></h1>
    </div>
    <a href="details_user.php?id=<?= $user['id'] ?>" class="btn-geco-outline">
      <i class="bi bi-arrow-left"></i> Volver al Perfil
    </a>
  </div>

  <!-- Form -->
  <form id="formUser" enctype="multipart/form-data" class="w-100">
    <input type="hidden" name="id" value="<?= $user['id'] ?>">

    <!-- Sección: Información Básica -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-person"></i> Información Básica</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Nombres <span class="text-danger">*</span></label>
            <input type="text" name="nombres" class="form-control" value="<?= htmlspecialchars($user['nombres']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Apellidos <span class="text-danger">*</span></label>
            <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($user['apellidos']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Correo Corporativo <span class="text-danger">*</span></label>
            <input type="email" name="correo_corporativo" class="form-control" value="<?= htmlspecialchars($user['correo_corporativo']) ?>" required>
            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i class="bi bi-info-circle"></i> Debe terminar en @proatam.com</small>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Correo Personal</label>
            <input type="email" name="correo_personal" class="form-control" value="<?= htmlspecialchars($user['correo_personal'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Número de Celular Particular</label>
            <input type="text" name="telefono_personal" class="form-control" value="<?= htmlspecialchars($user['telefono_personal'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Departamento <span class="text-danger">*</span></label>
            <select name="departamento_id" class="form-select" required>
              <option value="">-- Seleccionar Departamento --</option>
              <?php while ($dep = $departamentos->fetch_assoc()): ?>
                <option value="<?= $dep['id'] ?>" <?= $user['departamento_id'] == $dep['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($dep['nombre']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Fecha de Ingreso <span class="text-danger">*</span></label>
            <input type="date" name="fecha_ingreso" class="form-control" value="<?= htmlspecialchars($user['fecha_ingreso'] ?? '') ?>" required>
          </div>
        </div>
      </div>
    </div>

    <!-- Sección: Funciones y Actividades -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-list-task"></i> Funciones y Actividades</span>
      </div>
      <div class="oc-card-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Lista de Funciones y Actividades a Cargo</label>
          <textarea name="funciones_actividades" class="form-control" rows="4"><?= htmlspecialchars($user['funciones_actividades'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Sección: Contacto de Emergencia -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-telephone"></i> Contacto de Emergencia</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold">Nombre Completo</label>
            <input type="text" name="contacto_emergencia_nombre" class="form-control" value="<?= htmlspecialchars($user['contacto_emergencia_nombre'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Parentesco</label>
            <input type="text" name="contacto_emergencia_parentesco" class="form-control" value="<?= htmlspecialchars($user['contacto_emergencia_parentesco'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Número de Celular</label>
            <input type="text" name="contacto_emergencia_telefono" class="form-control" value="<?= htmlspecialchars($user['contacto_emergencia_telefono'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Sección: Documentos del Expediente -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-folder"></i> Documentos del Expediente</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <?php 
          $docs = [
            'curriculum_pdf' => ['label' => 'Curriculum Vitae', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'identificacion_pdf' => ['label' => 'Identificación Oficial', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'acta_nacimiento_pdf' => ['label' => 'Acta de Nacimiento', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'curp_pdf' => ['label' => 'CURP', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'situacion_fiscal_pdf' => ['label' => 'Constancia de Situación Fiscal', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'nss_pdf' => ['label' => 'Número de Seguro Social', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'comprobante_domicilio_pdf' => ['label' => 'Comprobante de Domicilio', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'foto_jpg' => ['label' => 'Foto Infantil', 'accept' => '.jpg,.jpeg', 'hint' => 'Formato JPG con fondo blanco'],
            'comprobante_estudios_pdf' => ['label' => 'Último Comprobante de Estudios', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'credencial_pdf' => ['label' => 'Credencial Corporativa', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'acuerdo_confidencialidad_pdf' => ['label' => 'Acuerdo de Confidencialidad', 'accept' => '.pdf', 'hint' => 'Formato PDF']
          ];
          foreach ($docs as $campo => $info):
              $existe = !empty($user[$campo]);
          ?>
            <div class="col-md-6 col-lg-4">
              <div class="doc-item-card p-3 border rounded bg-light">
                <div class="d-flex flex-column justify-content-between h-100">
                  <div>
                    <label class="form-label small fw-bold mb-1 d-block text-dark"><?= $info['label'] ?></label>
                    <input type="file" name="<?= $campo ?>" class="form-control form-control-sm" accept="<?= $info['accept'] ?>">
                  </div>
                  <div class="mt-2 d-flex align-items-center justify-content-between">
                    <span class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> <?= $info['hint'] ?></span>
                    <?php if ($existe): ?>
                      <a href="../uploads/usuarios/<?= htmlspecialchars($user[$campo]) ?>" target="_blank" class="badge text-success border border-success bg-white py-1 px-2 text-decoration-none text-truncate" style="max-width: 140px; font-size: 0.72rem;">
                        <i class="bi bi-check-circle-fill"></i> Existente
                      </a>
                    <?php else: ?>
                      <span class="badge text-secondary border border-secondary bg-white py-1 px-2" style="font-size: 0.72rem;">
                        <i class="bi bi-x-circle"></i> Sin cargar
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Contratos Laborales -->
        <div class="border-top pt-4 mt-4">
          <label class="form-label small fw-bold text-dark"><i class="bi bi-file-earmark-text me-1" style="color: var(--p-500,#407656);"></i> Contratos Laborales</label>
          
          <div id="contratos-container">
            <?php if (!empty($contratos)): ?>
              <?php foreach ($contratos as $c): ?>
                <div class="contrato-existente-item p-3 border rounded bg-light mb-3" id="contrato_row_<?= $c['id'] ?>">
                  <input type="hidden" name="contrato_id[]" value="<?= $c['id'] ?>">
                  <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                      <label class="form-label small fw-bold text-muted mb-1">Reemplazar archivo (PDF)</label>
                      <input type="file" name="contratos_existentes_<?= $c['id'] ?>" class="form-control form-control-sm" accept=".pdf">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label small fw-bold text-muted mb-1">Tipo de Contrato</label>
                      <select name="tipos_contrato_existentes[]" class="form-select form-select-sm" required>
                        <option value="Indeterminado" <?= $c['tipo_contrato'] === 'Indeterminado' ? 'selected' : '' ?>>Tiempo Indeterminado</option>
                        <option value="Determinado" <?= $c['tipo_contrato'] === 'Determinado' ? 'selected' : '' ?>>Tiempo Determinado</option>
                        <option value="Prueba" <?= $c['tipo_contrato'] === 'Prueba' ? 'selected' : '' ?>>Periodo de Prueba</option>
                        <option value="Obra" <?= $c['tipo_contrato'] === 'Obra' ? 'selected' : '' ?>>Obra Determinada</option>
                        <option value="Otro" <?= $c['tipo_contrato'] === 'Otro' ? 'selected' : '' ?>>Otro</option>
                      </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end justify-content-md-end h-100" style="padding-top: 24px;">
                      <div class="form-check d-flex align-items-center gap-1 border rounded px-3 bg-white" style="font-size:0.8rem; height: 38px; cursor: pointer;">
                        <input class="form-check-input mt-0 cursor-pointer" type="checkbox" name="eliminar_contrato[]" value="<?= $c['id'] ?>" id="del_<?= $c['id'] ?>">
                        <label class="form-check-label text-danger fw-semibold cursor-pointer mb-0" for="del_<?= $c['id'] ?>">
                          <i class="bi bi-trash"></i> Eliminar
                        </label>
                      </div>
                    </div>
                    <div class="col-12 mt-1">
                      <a href="<?= htmlspecialchars($c['ruta_archivo']) ?>" target="_blank" class="d-inline-block text-truncate text-success fw-semibold" style="font-size: 0.72rem; max-width: 100%;">
                        <i class="bi bi-file-earmark-check-fill"></i> <?= htmlspecialchars($c['nombre_archivo']) ?>
                      </a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <button type="button" class="btn btn-sm btn-outline-secondary mt-2 px-3 fw-semibold" id="agregar-contrato" style="border-radius: 8px;">
            <i class="bi bi-plus-lg me-1"></i> Agregar otro contrato
          </button>
          <span class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i class="bi bi-info-circle"></i> Puedes subir múltiples contratos en formato PDF</span>
        </div>
      </div>
    </div>

    <!-- Submit Actions -->
    <div class="oc-card mb-4">
      <div class="oc-card-body bg-light" style="padding: 1.5rem 2rem;">
        <div class="d-flex justify-content-center gap-3">
          <a href="details_user.php?id=<?= $user['id'] ?>" class="btn-geco-outline" style="min-width: 140px; justify-content:center;">
            Cancelar
          </a>
          <button type="submit" class="btn-geco-primary" style="min-width: 180px; justify-content:center;">
            <i class="bi bi-save me-2"></i> Guardar Cambios
          </button>
        </div>
      </div>
    </div>

  </form>

</div>

<style>
/* oc-card-header y oc-card-body heredados directamente de orders-common.css */
.doc-item-card {
  transition: all 0.2s;
  background: var(--gray-50,#f9fafb) !important;
  border: 1px solid var(--gray-200,#e5e7eb) !important;
  min-height: 120px;
}
.doc-item-card:hover {
  border-color: var(--p-300,#86efac) !important;
  background: #fff !important;
}
.contrato-existente-item {
  transition: all 0.2s;
}
.contrato-existente-item:hover {
  border-color: var(--gray-300,#d1d5db) !important;
}
.form-check-input:checked {
  background-color: var(--danger,#dc3545);
  border-color: var(--danger,#dc3545);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formUser');
    const container = document.getElementById('contratos-container');
    const btnAgregar = document.getElementById('agregar-contrato');

    // Submit handler con AJAX
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            UI.loading("Guardando cambios del usuario...");
            
            fetch('update_user.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(res => {
                if (!res.ok) throw new Error("Error en la respuesta del servidor: " + res.status);
                return res.json();
            })
            .then(data => {
                UI.loading.hide();
                if (data.status === 'success') {
                    UI.toast.success("Usuario actualizado correctamente");
                    setTimeout(() => {
                        location.href = 'details_user.php?id=<?= $user['id'] ?>';
                    }, 1300);
                } else {
                    UI.toast.error(data.message || "Error al actualizar usuario");
                }
            })
            .catch(err => {
                UI.loading.hide();
                console.error("Error:", err);
                UI.toast.error("Error de conexión con el servidor");
            });
        });
    }

    // Agregar nuevo contrato
    if (btnAgregar && container) {
        btnAgregar.addEventListener('click', function() {
            const newItem = document.createElement('div');
            newItem.className = 'contrato-item p-3 border rounded bg-light mb-3';
            newItem.innerHTML = `
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1">Archivo de Contrato (PDF) <span class="text-danger">*</span></label>
                        <input type="file" name="nuevos_contratos[]" class="form-control form-control-sm" accept=".pdf" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo de Contrato <span class="text-danger">*</span></label>
                        <select name="nuevos_tipos_contrato[]" class="form-select form-select-sm" required>
                            <option value="">-- Seleccionar Tipo --</option>
                            <option value="Indeterminado">Tiempo Indeterminado</option>
                            <option value="Determinado">Tiempo Determinado</option>
                            <option value="Prueba">Periodo de Prueba</option>
                            <option value="Obra">Obra Determinada</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end justify-content-md-end h-100" style="padding-top: 24px;">
                        <button type="button" class="btn btn-danger btn-sm btn-remove-contrato w-100" style="height: 38px;">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(newItem);
        });
    }

    // Delegar evento para eliminar nuevos contratos
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-contrato')) {
            const item = e.target.closest('.contrato-item');
            if (item) {
                item.remove();
            }
        }
    });
});
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
