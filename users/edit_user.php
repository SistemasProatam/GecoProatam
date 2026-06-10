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

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Editar Usuario | GECO PROATAM</title>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="list_users.php">Usuarios</a>
        <span class="separator">›</span>
        <a href="details_user.php?id=<?= $user['id'] ?>">Detalles del Usuario</a>
        <span class="separator">›</span>
        <span>Editar Usuario</span>
      </nav>
      <h1 class="orders-page-title">Editar <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></h1>
    </div>
    <a href="details_user.php?id=<?= $user['id'] ?>" class="btn-geco-outline">
      <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
  </div>

  <!-- Form -->
  <form id="formUser" enctype="multipart/form-data" class="w-100">
    <input type="hidden" name="id" value="<?= $user['id'] ?>">

    <!-- Sección: Información Básica -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-user"></i> Información Básica</span>
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
            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i class="fa-solid fa-circle-info"></i> Debe terminar en @proatam.com</small>
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
        <span class="oc-card-header__title"><i class="fa-solid fa-list-check"></i> Funciones y Actividades</span>
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
        <span class="oc-card-header__title"><i class="fa-solid fa-phone"></i> Contacto de Emergencia</span>
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
    <div class="oc-card mb-4">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-regular fa-folder-open"></i> Documentos del Expediente</span>
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
            'foto_jpg' => ['label' => 'Foto de Usuario', 'accept' => '.jpg,.jpeg', 'hint' => 'Formato JPG'],
            'comprobante_estudios_pdf' => ['label' => 'Último Comprobante de Estudios', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'credencial_pdf' => ['label' => 'Credencial Corporativa', 'accept' => '.pdf', 'hint' => 'Formato PDF'],
            'acuerdo_confidencialidad_pdf' => ['label' => 'Acuerdo de Confidencialidad', 'accept' => '.pdf', 'hint' => 'Formato PDF']
          ];
          foreach ($docs as $campo => $info):
            $existe = !empty($user[$campo]);
          ?>
            <div class="col-md-6 col-lg-4 mb-3">
              <div class="doc-item-card p-3 h-100" style="border: 1px solid <?= $existe ? 'rgba(34, 197, 94, 0.3)' : 'var(--gray-200, #e5e7eb)' ?>; border-radius: var(--radius-md, 8px); background: <?= $existe ? 'rgba(34, 197, 94, 0.01)' : 'none' ?>;">
                <div class="d-flex flex-column justify-content-between h-100">
                  <div>
                    <label class="form-label small fw-bold mb-2 d-block text-dark"><?= $info['label'] ?></label>
                    <div class="file-drop-zone">
                      <input type="file" name="<?= $campo ?>" id="file_<?= $campo ?>" accept="<?= $info['accept'] ?>">
                      <div class="file-drop-label">
                        <i class="material-symbols-rounded"><?= $existe ? 'cloud_done' : 'cloud_upload' ?></i>
                        <span><?= $info['hint'] ?></span>
                      </div>
                    </div>
                    <div class="file-chips" id="chips_<?= $campo ?>"></div>
                    <?php if ($existe): ?>
                      <div class="mt-2 text-end">
                        <a href="../uploads/usuarios/<?= htmlspecialchars($user[$campo]) ?>" target="_blank" class="d-inline-flex align-items-center gap-1 text-success fw-semibold" style="font-size: 0.72rem; text-decoration: none;">
                          <i class="material-symbols-rounded" style="font-size:0.9rem;">check_circle</i> Ver archivo actual
                        </a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Sección: Contratos Laborales -->
    <div class="oc-card mb-4">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-regular fa-file-lines"></i> Contratos Laborales</span>
      </div>
      <div class="oc-card-body">
        <div id="contratos-container">
          <?php if (!empty($contratos)): ?>
            <?php foreach ($contratos as $c): ?>
              <div class="contrato-existente-item p-3 border rounded bg-light mb-3" id="contrato_row_<?= $c['id'] ?>">
                <input type="hidden" name="contrato_id[]" value="<?= $c['id'] ?>">
                <div class="row g-2 align-items-center">
                  <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">Reemplazar archivo (PDF)</label>
                    <div class="horizontal-file-zone">
                      <input type="file" name="contratos_existentes_<?= $c['id'] ?>" class="horizontal-file-input" accept=".pdf">
                      <div class="horizontal-file-label">
                        <i class="material-symbols-rounded">upload_file</i>
                        <span>Reemplazar archivo...</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Tipo de Contrato</label>
                    <select name="tipos_contrato_existentes[]" class="form-select form-select-sm" style="height: 44px;" required>
                      <option value="Indeterminado" <?= $c['tipo_contrato'] === 'Indeterminado' ? 'selected' : '' ?>>Tiempo Indeterminado</option>
                      <option value="Determinado" <?= $c['tipo_contrato'] === 'Determinado' ? 'selected' : '' ?>>Tiempo Determinado</option>
                      <option value="Prueba" <?= $c['tipo_contrato'] === 'Prueba' ? 'selected' : '' ?>>Periodo de Prueba</option>
                      <option value="Obra" <?= $c['tipo_contrato'] === 'Obra' ? 'selected' : '' ?>>Obra Determinada</option>
                      <option value="Otro" <?= $c['tipo_contrato'] === 'Otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                  </div>
                  <div class="col-md-3 d-flex align-items-end justify-content-md-end h-100" style="padding-top: 24px;">
                    <div class="form-check d-flex align-items-center justify-content-center gap-1 border rounded px-3 bg-white" style="font-size:0.8rem; height: 44px; cursor: pointer; border-color: #fca5a5 !important;">
                      <input class="form-check-input mt-0 cursor-pointer" type="checkbox" name="eliminar_contrato[]" value="<?= $c['id'] ?>" id="del_<?= $c['id'] ?>">
                      <label class="form-check-label text-danger fw-semibold cursor-pointer mb-0" for="del_<?= $c['id'] ?>">
                        <i class="material-symbols-rounded" style="font-size: 1.1rem; vertical-align: middle;">delete</i> Eliminar
                      </label>
                    </div>
                  </div>
                  <div class="col-12 mt-1">
                    <a href="<?= htmlspecialchars($c['ruta_archivo']) ?>" target="_blank" class="d-inline-flex align-items-center gap-1 text-success fw-semibold" style="font-size: 0.72rem; max-width: 100%; text-decoration: none;">
                      <i class="material-symbols-rounded" style="font-size:0.95rem;">check_circle</i> <?= htmlspecialchars($c['nombre_archivo']) ?>
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary mt-2 px-3 fw-semibold" id="agregar-contrato" style="border-radius: 8px;">
          <i class="fa-solid fa-plus me-1"></i> Agregar otro contrato
        </button>
        <span class="text-muted d-block mt-2" style="font-size: 0.75rem;"><i class="fa-solid fa-circle-info"></i> Puedes subir múltiples contratos en formato PDF</span>
      </div>
    </div>

    <!-- Submit Actions -->
    <div class="mt-4 mb-5">
      <p class="text-muted small mb-2"><i class="fa-solid fa-circle-info"></i> Verifique que toda la información del expediente digital sea correcta antes de guardar.</p>
      <div class="d-flex justify-content-end gap-2">
        <a href="details_user.php?id=<?= $user['id'] ?>" class="btn-geco-outline">Cancelar</a>
        <button type="submit" class="btn-geco-primary">
          <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
        </button>
      </div>
    </div>

  </form>

</div>


<script>
const GecoFileUploader = {
  LIMIT_MB: 10,
  store: {}, // Holds selected files: { fieldName: File }

  init() {
    // 1. Initialize Card Uploaders (Type A)
    document.querySelectorAll('.file-drop-zone input[type="file"]').forEach(input => {
      this.bindUploader(input, 'card');
    });

    // 2. Initialize Horizontal Uploaders (Type B)
    document.querySelectorAll('.horizontal-file-zone input[type="file"]').forEach(input => {
      this.bindUploader(input, 'horizontal');
    });
  },

  bindUploader(input, type) {
    const fieldName = input.name || input.id;
    if (!fieldName) return;

    input.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      if (!files.length) return;

      const file = files[0];
      const sizeMB = file.size / 1024 / 1024;
      const limit = parseFloat(input.getAttribute('data-max-size')) || this.LIMIT_MB;

      if (sizeMB > limit) {
        UI.toast.error(`"${file.name}" supera el límite de ${limit} MB (${sizeMB.toFixed(2)} MB).`);
        input.value = ''; // Reset input
        this.clearField(fieldName, type, input);
        return;
      }

      // Store in memory
      this.store[fieldName] = file;
      UI.toast.success(`"${file.name}" listo para subir.`);
      this.updateUI(fieldName, file, type, input);
    });
  },

  updateUI(fieldName, file, type, input) {
    const parentZone = input.closest(type === 'card' ? '.file-drop-zone' : '.horizontal-file-zone');
    if (type === 'card') {
      // Show in chips container
      const chipsContainer = document.getElementById('chips_' + fieldName);
      if (chipsContainer) {
        const sizeMB = (file.size / 1024 / 1024).toFixed(2);
        chipsContainer.innerHTML = `
          <div class="file-chip ok">
            <i class="material-symbols-rounded" style="font-size:0.95rem;flex-shrink:0;">check_circle</i>
            <span class="chip-name" title="${file.name}">${file.name}</span>
            <span class="chip-size">${sizeMB} MB</span>
            <button type="button" class="chip-remove" onclick="GecoFileUploader.removeFile('${fieldName}', 'card')" title="Quitar archivo">
              <i class="material-symbols-rounded" style="font-size: 1rem; line-height: 1;">close</i>
            </button>
          </div>
        `;
      }
    } else {
      // Horizontal mode
      if (parentZone) {
        parentZone.classList.add('has-file');
        const labelText = parentZone.querySelector('.horizontal-file-label span');
        if (labelText) {
          labelText.textContent = file.name;
        }
        const labelIcon = parentZone.querySelector('.horizontal-file-label i');
        if (labelIcon) {
          labelIcon.className = 'material-symbols-rounded';
          labelIcon.textContent = 'check_circle';
        }
      }
    }
  },

  removeFile(fieldName, type) {
    delete this.store[fieldName];
    const input = document.getElementById('file_' + fieldName) || document.querySelector(`input[name="${fieldName}"]`);
    if (input) input.value = '';

    this.clearField(fieldName, type, input);
    UI.toast.info('Archivo removido.');
  },

  clearField(fieldName, type, input) {
    if (type === 'card') {
      const chipsContainer = document.getElementById('chips_' + fieldName);
      if (chipsContainer) chipsContainer.innerHTML = '';
    } else {
      const parentZone = input ? input.closest('.horizontal-file-zone') : null;
      if (parentZone) {
        parentZone.classList.remove('has-file');
        const labelText = parentZone.querySelector('.horizontal-file-label span');
        const defaultText = (input && input.getAttribute('data-placeholder')) || 'Seleccionar archivo (PDF)...';
        if (labelText) labelText.textContent = defaultText;

        const labelIcon = parentZone.querySelector('.horizontal-file-label i');
        if (labelIcon) {
          labelIcon.className = 'material-symbols-rounded';
          labelIcon.textContent = 'upload_file';
        }
      }
    }
  }
};
window.GecoFileUploader = GecoFileUploader;
document.addEventListener('DOMContentLoaded', () => GecoFileUploader.init());

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
                        <div class="horizontal-file-zone">
                            <input type="file" name="nuevos_contratos[]" class="horizontal-file-input" accept=".pdf" required>
                            <div class="horizontal-file-label">
                                <i class="material-symbols-rounded">upload_file</i>
                                <span>Seleccionar archivo (PDF)...</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo de Contrato <span class="text-danger">*</span></label>
                        <select name="nuevos_tipos_contrato[]" class="form-select form-select-sm" style="height: 44px;" required>
                            <option value="">-- Seleccionar Tipo --</option>
                            <option value="Indeterminado">Tiempo Indeterminado</option>
                            <option value="Determinado">Tiempo Determinado</option>
                            <option value="Prueba">Periodo de Prueba</option>
                            <option value="Obra">Obra Determinada</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end justify-content-md-end h-100" style="padding-top: 24px;">
                        <button type="button" class="btn btn-danger btn-remove-contrato w-100 w-md-auto" style="height: 44px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 8px;">
                            <i class="material-symbols-rounded" style="font-size: 1.2rem;">delete</i> Eliminar
                        </button>
                    </div>
                </div>
            `;
        container.appendChild(newItem);

        // Bind the file uploader for the new element
        const newInput = newItem.querySelector('input[type="file"]');
        if (newInput && window.GecoFileUploader) {
            window.GecoFileUploader.bindUploader(newInput, 'horizontal');
        }
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