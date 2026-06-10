<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Obtener departamentos para el select
$departamentos = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
$departamentosOptions = "";
while ($dep = $departamentos->fetch_assoc()) {
  $departamentosOptions .= "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
}

$conn->close();
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Nuevo Usuario | GECO PROATAM</title>

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
        <span>Nuevo Usuario</span>
      </nav>
      <h1 class="orders-page-title">Nuevo Usuario</h1>
    </div>
    <a href="list_users.php" class="btn-geco-outline">
      <i class="fa-solid fa-arrow-left"></i> Volver al Listado
    </a>
  </div>

  <!-- Form Card -->
  <form id="formAgregarUsuario" method="POST" action="insert_user.php" enctype="multipart/form-data" class="w-100">

    <!-- Sección: Información Básica -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-user"></i> Información Básica</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Nombres <span class="text-danger">*</span></label>
            <input type="text" name="nombres" class="form-control" placeholder="Escribe los nombres..." required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Apellidos <span class="text-danger">*</span></label>
            <input type="text" name="apellidos" class="form-control" placeholder="Escribe los apellidos..." required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Correo Corporativo <span class="text-danger">*</span></label>
            <input type="email" name="correo_corporativo" class="form-control" placeholder="ejemplo@proatam.com" required>
            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i class="fa-solid fa-circle-info"></i> Debe terminar en @proatam.com</small>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Correo Personal</label>
            <input type="email" name="correo_personal" class="form-control" placeholder="ejemplo@correo.com">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Número de Celular Particular</label>
            <input type="text" name="telefono_personal" class="form-control" placeholder="Ej. 8341234567">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Departamento <span class="text-danger">*</span></label>
            <select name="departamento_id" class="form-select" required>
              <option value="">-- Seleccionar Departamento --</option>
              <?= $departamentosOptions ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Fecha de Ingreso <span class="text-danger">*</span></label>
            <input type="date" name="fecha_ingreso" class="form-control" required>
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
          <textarea name="funciones_actividades" class="form-control" rows="4" placeholder="Describa de manera detallada las funciones y actividades que tendrá a cargo el usuario..."></textarea>
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
            <input type="text" name="contacto_emergencia_nombre" class="form-control" placeholder="Nombre de contacto...">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Parentesco</label>
            <input type="text" name="contacto_emergencia_parentesco" class="form-control" placeholder="Ej. Esposa, Padre, etc.">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Número de Celular</label>
            <input type="text" name="contacto_emergencia_telefono" class="form-control" placeholder="Ej. 8341234567">
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
          ?>
            <div class="col-md-6 col-lg-4 mb-3">
              <div class="doc-item-card p-3 h-100" style="border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-md, 8px);">
                <div class="d-flex flex-column justify-content-between h-100">
                  <div>
                    <label class="form-label small fw-bold mb-2 d-block text-dark"><?= $info['label'] ?></label>
                    <div class="file-drop-zone">
                      <input type="file" name="<?= $campo ?>" id="file_<?= $campo ?>" accept="<?= $info['accept'] ?>">
                      <div class="file-drop-label">
                        <i class="material-symbols-rounded">cloud_upload</i>
                        <span><?= $info['hint'] ?></span>
                      </div>
                    </div>
                    <div class="file-chips" id="chips_<?= $campo ?>"></div>
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
          <div class="contrato-item mb-3 p-3 border rounded bg-light">
            <div class="row g-2 align-items-center">
              <div class="col-md-5">
                <label class="form-label small fw-bold text-muted mb-1">Archivo de Contrato (PDF) <span class="text-danger">*</span></label>
                <div class="horizontal-file-zone">
                  <input type="file" name="contratos[]" class="horizontal-file-input" accept=".pdf" required>
                  <div class="horizontal-file-label">
                    <i class="material-symbols-rounded">upload_file</i>
                    <span>Seleccionar archivo (PDF)...</span>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Tipo de Contrato <span class="text-danger">*</span></label>
                <select name="tipos_contrato[]" class="form-select form-select-sm" style="height: 44px;" required>
                  <option value="">-- Seleccionar Tipo --</option>
                  <option value="Indeterminado">Tiempo Indeterminado</option>
                  <option value="Determinado">Tiempo Determinado</option>
                  <option value="Prueba">Periodo de Prueba</option>
                  <option value="Obra">Obra Determinada</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>
              <div class="col-md-3 d-flex align-items-end justify-content-md-end h-100" style="padding-top: 24px;">
                <button type="button" class="btn btn-danger btn-remove-contrato w-100 w-md-auto" style="height: 44px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 8px;" disabled>
                  <i class="material-symbols-rounded" style="font-size: 1.2rem;">delete</i> Eliminar
                </button>
              </div>
            </div>
          </div>
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
        <a href="list_users.php" class="btn-geco-outline">Cancelar</a>
        <button type="submit" class="btn-geco-primary">
          <i class="fa-solid fa-floppy-disk"></i> Guardar Usuario
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
    const form = document.getElementById('formAgregarUsuario');
    const container = document.getElementById('contratos-container');
    const btnAgregar = document.getElementById('agregar-contrato');

    // Submit handler con AJAX
    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        UI.loading("Creando nuevo usuario...");

        fetch('insert_user.php', {
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
              UI.toast.success(data.message || "Usuario creado correctamente");
              setTimeout(() => {
                location.href = 'list_users.php';
              }, 1500);
            } else {
              UI.toast.error(data.message || "Error al crear usuario");
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
        newItem.className = 'contrato-item mb-3 p-3 border rounded bg-light';
        newItem.innerHTML = `
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1">Archivo de Contrato (PDF) <span class="text-danger">*</span></label>
                        <div class="horizontal-file-zone">
                            <input type="file" name="contratos[]" class="horizontal-file-input" accept=".pdf" required>
                            <div class="horizontal-file-label">
                                <i class="material-symbols-rounded">upload_file</i>
                                <span>Seleccionar archivo (PDF)...</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo de Contrato <span class="text-danger">*</span></label>
                        <select name="tipos_contrato[]" class="form-select form-select-sm" style="height: 44px;" required>
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

        // Habilitar botón de eliminar en todos menos el primero
        actualizarBotonesEliminar();
      });
    }

    // Delegar evento para eliminar contratos
    document.addEventListener('click', function(e) {
      if (e.target.closest('.btn-remove-contrato')) {
        const item = e.target.closest('.contrato-item');
        if (item) {
          item.remove();
          actualizarBotonesEliminar();
        }
      }
    });

    function actualizarBotonesEliminar() {
      const removeBtns = document.querySelectorAll('.btn-remove-contrato');
      if (removeBtns.length === 1) {
        removeBtns[0].disabled = true;
      } else {
        removeBtns.forEach(btn => btn.disabled = false);
      }
    }
  });
</script>
<?php include __DIR__ . "/../includes/footer.php"; ?>