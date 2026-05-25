<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/includes/session_manager.php";
require_once __DIR__ . "/includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/conexion.php";

$user_id = $_SESSION['user_id'] ?? null;

$user_data = [
    'nombres'            => '',
    'apellidos'          => '',
    'correo_corporativo' => '',
    'departamento'       => 'Sin departamento'
];

if ($user_id) {
    $sql  = "SELECT u.nombres, u.apellidos, u.correo_corporativo, d.nombre as departamento
             FROM usuarios u
             LEFT JOIN departamentos d ON u.departamento_id = d.id
             WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $_SESSION['user_email'] = $user_data['correo_corporativo'];
    }
    $stmt->close();
}

// Activo pre-seleccionado desde detalle_activo.php (opcional)
$activo_pre = null;
$activo_pre_id = intval($_GET['activo_id'] ?? 0);
if ($activo_pre_id) {
    $stmt_a = $conn->prepare(
        "SELECT a.id, a.codigo, a.nombre, t.nombre AS tipo
         FROM activos a JOIN activo_tipos t ON a.tipo_id = t.id
         WHERE a.id = ?"
    );
    $stmt_a->bind_param("i", $activo_pre_id);
    $stmt_a->execute();
    $activo_pre = $stmt_a->get_result()->fetch_assoc();
}

// Listado completo de activos para el buscador
$result_activos = $conn->query(
    "SELECT a.id, a.codigo, a.nombre, t.nombre AS tipo
     FROM activos a
     JOIN activo_tipos t ON a.tipo_id = t.id
     WHERE a.estatus = 'activo'
     ORDER BY a.nombre ASC"
);
$activos_array = [];
while ($row = $result_activos->fetch_assoc()) {
    $activos_array[] = $row;
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">

<style>
/* Estilos personalizados de Autocompletado de Activos apegados a GECO */
.activo-autocomplete {
  position: relative;
}
.autocomplete-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid var(--gray-200,#e5e7eb);
  border-top: none;
  max-height: 220px;
  overflow-y: auto;
  z-index: 1050;
  display: none;
  border-radius: 0 0 12px 12px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
}
.autocomplete-item {
  padding: 10px 16px;
  cursor: pointer;
  border-bottom: 1px solid var(--gray-100,#f3f4f6);
  font-size: 0.82rem;
  transition: all 0.2s;
  color: var(--gray-700,#374151);
}
.autocomplete-item strong {
  color: var(--s-700,#113557);
}
.autocomplete-item:hover {
  background: rgba(64,118,86,0.04);
  color: var(--p-800,#1b4332);
}
.activo-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: rgba(64,118,86,0.06);
  border: 1.5px dashed var(--p-300,#86efac);
  border-radius: 10px;
  padding: 8px 16px;
  font-size: 0.85rem;
  color: var(--p-800,#1b4332);
  margin-top: 6px;
  font-weight: 500;
  transition: all 0.2s;
}
.activo-badge .remove-activo {
  cursor: pointer;
  color: var(--gray-400,#9ca3af);
  display: flex;
  align-items: center;
  transition: color 0.2s;
}
.activo-badge .remove-activo:hover {
  color: #dc3545;
}
</style>

<?php include __DIR__ . "/includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <span>Solicitud de Mantenimiento</span>
      </nav>
      <h1 class="orders-page-title">Solicitud de Mantenimiento</h1>
    </div>
  </div>

  <!-- Ayuda Banner -->
  <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4" style="background: rgba(64,118,86,0.06); border-radius: 12px; padding: 16px;">
    <i class="bi bi-info-circle-fill" style="font-size: 1.5rem; color: var(--p-500,#407656) !important;"></i>
    <div style="font-size: 0.88rem; color: var(--s-800,#0f172a);">
      <strong>¿Necesitas ayuda?</strong> Estamos para apoyarte. Completa el formulario y nos pondremos en contacto contigo a través de tu correo corporativo.
    </div>
  </div>

  <!-- Formulario -->
  <form id="supportForm" enctype="multipart/form-data">

    <!-- Card 1: Información del Solicitante -->
    <div class="oc-card mb-4">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-person-circle"></i> Información del Solicitante</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-6 col-lg-4">
            <div class="form-group">
              <label class="form-label small fw-bold">Nombre completo <span class="text-danger">*</span></label>
              <input type="text" name="nombres" id="nombres" class="form-control" required
                  value="<?= htmlspecialchars($user_data['nombres'] . ' ' . $user_data['apellidos']) ?>">
            </div>
          </div>
          <div class="col-md-6 col-lg-4">
            <div class="form-group">
              <label class="form-label small fw-bold">Correo Corporativo <span class="text-danger">*</span></label>
              <input type="email" name="correo_corporativo" id="correo_corporativo" class="form-control" required
                  value="<?= htmlspecialchars($user_data['correo_corporativo']) ?>">
            </div>
          </div>
          <div class="col-md-6 col-lg-4">
            <div class="form-group">
              <label class="form-label small fw-bold">Departamento / Área <span class="text-danger">*</span></label>
              <input type="text" name="departamento" id="departamento" class="form-control" required
                  value="<?= htmlspecialchars($user_data['departamento']) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 2: Activo Relacionado (Opcional) -->
    <div class="oc-card mb-4">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-box-seam"></i> Activo Relacionado <small class="text-muted fw-normal ms-1" style="font-size:0.75rem; text-transform:none; letter-spacing:0;">(opcional)</small></span>
      </div>
      <div class="oc-card-body">
        <div class="form-group">
          <!-- Badge para el activo seleccionado -->
          <div id="activoBadge" class="activo-badge mb-3" style="<?= $activo_pre ? 'display: inline-flex;' : 'display: none;' ?>">
            <i class="bi bi-box-seam me-1"></i>
            <span id="activoBadgeText">
              <?php if ($activo_pre): ?>
                <strong><?= htmlspecialchars($activo_pre['codigo']) ?></strong> – <?= htmlspecialchars($activo_pre['nombre']) ?> <small class="text-muted">(<?= htmlspecialchars($activo_pre['tipo']) ?>)</small>
              <?php endif; ?>
            </span>
            <span class="remove-activo ms-3" onclick="limpiarActivo()" title="Quitar activo">
              <i class="bi bi-x-circle-fill"></i>
            </span>
          </div>

          <!-- Campos ocultos para el activo -->
          <input type="hidden" name="activo_id" id="activoId" value="<?= $activo_pre['id'] ?? '' ?>">
          <input type="hidden" name="activo_codigo" id="activoCodigo" value="<?= htmlspecialchars($activo_pre['codigo'] ?? '') ?>">
          <input type="hidden" name="activo_nombre" id="activoNombre" value="<?= htmlspecialchars($activo_pre['nombre'] ?? '') ?>">
          <input type="hidden" name="activo_tipo" id="activoTipo" value="<?= htmlspecialchars($activo_pre['tipo'] ?? '') ?>">

          <!-- Buscador (se oculta si hay un activo seleccionado) -->
          <div id="activoSearchWrap" style="<?= $activo_pre ? 'display: none;' : 'display: block;' ?>">
            <div class="activo-autocomplete position-relative">
              <input type="text" id="activoBusqueda" class="form-control" placeholder="Escriba código o nombre del activo para buscar...">
              <div class="autocomplete-list" id="autocompleteList"></div>
            </div>
            <div class="form-text text-muted mt-2">
              <i class="bi bi-info-circle me-1"></i> Si el problema está relacionado con un activo registrado, selecciónelo aquí.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 3: Detalles del Problema -->
    <div class="oc-card mb-4">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-exclamation-triangle"></i> Detalles del Problema</span>
      </div>
      <div class="oc-card-body">
        <div class="form-group mb-3">
          <label class="form-label small fw-bold">Asunto <span class="text-danger">*</span></label>
          <input type="text" name="asunto" id="asunto" class="form-control" required placeholder="Breve descripción o resumen del problema">
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label small fw-bold">Sistema / Área Afectada <span class="text-danger">*</span></label>
              <select name="sistema_afectado" id="sistema_afectado" class="form-select" required>
                <option value="">Selecciona el sistema afectado</option>
                <option value="Activo / Equipo">Activo / Equipo</option>
                <option value="Sistema PROATAM">Sistema Integral PROATAM</option>
                <option value="Correo Electrónico">Correo Electrónico</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label small fw-bold">Nivel de Urgencia <span class="text-danger">*</span></label>
              <select name="urgencia" id="urgencia" class="form-select" required>
                <option value="">Selecciona el nivel de urgencia</option>
                <option value="Baja">Baja – No afecta operaciones</option>
                <option value="Media">Media – Afecta parcialmente</option>
                <option value="Alta">Alta – Afecta significativamente</option>
                <option value="Urgente">Urgente – Bloquea operaciones críticas</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label small fw-bold">Descripción Detallada <span class="text-danger">*</span></label>
          <textarea name="descripcion" id="descripcion" class="form-control" rows="4" required placeholder="Describe el problema con el mayor detalle posible, incluyendo qué estabas haciendo y cualquier mensaje de error que aparezca..."></textarea>
        </div>
      </div>
    </div>

    <!-- Card 4: Archivos Adjuntos -->
    <div class="oc-card mb-4">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-paperclip"></i> Archivos Adjuntos <small class="text-muted fw-normal ms-1" style="font-size:0.75rem; text-transform:none; letter-spacing:0;">(opcional)</small></span>
      </div>
      <div class="oc-card-body">
        <div class="form-text text-muted mb-3">
          <i class="bi bi-info-circle me-1"></i> Puedes adjuntar imágenes, documentos o capturas de pantalla de error. Máximo 5 archivos, hasta 5 MB cada uno.
        </div>

        <div class="row g-2 align-items-center mb-4">
          <div class="col">
            <input type="file" class="form-control" id="singleFileInput" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
          </div>
          <div class="col-auto">
            <button class="btn btn-geco-primary px-4" type="button" onclick="agregarAdjunto()">
              <i class="bi bi-plus-circle me-1"></i> Agregar
            </button>
          </div>
        </div>

        <div id="adjuntosContainer" class="p-3 rounded bg-light border">
          <h6 class="fw-bold mb-3" style="font-size: 0.85rem; color: var(--s-700,#113557);">Archivos seleccionados: <span id="contadorAdjuntos" class="badge bg-secondary rounded-pill">0</span></h6>
          <ul id="adjuntosList" class="list-group list-group-flush rounded border" style="background:#fff;">
            <li class="list-group-item text-center text-muted py-3">
              <i class="bi bi-inbox me-1" style="font-size: 1.2rem;"></i> No hay archivos agregados
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Acciones -->
    <div class="d-flex flex-column align-items-center gap-3 mt-4 mb-5">
      <div class="text-muted text-center" style="font-size: 0.8rem;">
        <i class="bi bi-envelope-check me-1"></i> La respuesta y actualizaciones de esta solicitud se enviarán a su correo corporativo registrado.
      </div>
      <div class="d-flex justify-content-center w-100">
        <button type="submit" class="btn-geco-primary px-5 py-2" id="submitBtn" style="min-width: 220px;">
          <i class="bi bi-send me-1"></i> Enviar Solicitud
        </button>
      </div>
    </div>

  </form>

</div>



<?php include __DIR__ . "/includes/footer.php"; ?>

<script>
    // Catálogo de activos desde PHP
    const ACTIVOS = <?= json_encode($activos_array) ?>;

    // Autocomplete de activos
    const inputBusqueda = document.getElementById('activoBusqueda');
    const listaAuto = document.getElementById('autocompleteList');

    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            listaAuto.innerHTML = '';

            if (q.length < 2) {
                listaAuto.style.display = 'none';
                return;
            }

            const filtrados = ACTIVOS.filter(a =>
                a.codigo.toLowerCase().includes(q) ||
                a.nombre.toLowerCase().includes(q) ||
                a.tipo.toLowerCase().includes(q)
            ).slice(0, 8);

            if (!filtrados.length) {
                listaAuto.style.display = 'none';
                return;
            }

            filtrados.forEach(a => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = `<strong>${a.codigo}</strong> – ${a.nombre}
                         <small class="text-muted ms-1">(${a.tipo})</small>`;
                div.addEventListener('click', () => seleccionarActivo(a));
                listaAuto.appendChild(div);
            });
            listaAuto.style.display = 'block';
        });

        // Cerrar lista al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.activo-autocomplete')) {
                listaAuto.style.display = 'none';
            }
        });
    }

    function seleccionarActivo(activo) {
        document.getElementById('activoId').value = activo.id;
        document.getElementById('activoCodigo').value = activo.codigo;
        document.getElementById('activoNombre').value = activo.nombre;
        document.getElementById('activoTipo').value = activo.tipo;

        document.getElementById('activoBadgeText').innerHTML =
            `<strong>${activo.codigo}</strong> – ${activo.nombre}
     <small class="text-muted">(${activo.tipo})</small>`;

        document.getElementById('activoBadge').style.display = 'inline-flex';
        document.getElementById('activoSearchWrap').style.display = 'none';
        listaAuto.style.display = 'none';
        if (inputBusqueda) inputBusqueda.value = '';

        // Si el sistema afectado está vacío, preseleccionar "Activo / Equipo"
        const sysSelect = document.getElementById('sistema_afectado');
        if (!sysSelect.value) sysSelect.value = 'Activo / Equipo';
    }

    function limpiarActivo() {
        document.getElementById('activoId').value = '';
        document.getElementById('activoCodigo').value = '';
        document.getElementById('activoNombre').value = '';
        document.getElementById('activoTipo').value = '';

        document.getElementById('activoBadge').style.display = 'none';
        document.getElementById('activoSearchWrap').style.display = 'block';
        if (inputBusqueda) inputBusqueda.value = '';
    }

    // Adjuntos uno a uno
    let adjuntosSeleccionados = [];
    const MAX_ADJ = 5;
    const MAX_SIZE = 5; // MB

    function agregarAdjunto() {
        const input = document.getElementById('singleFileInput');
        if (!input.files.length) {
            UI.toast.warning('Seleccione un archivo primero.');
            return;
        }

        const file = input.files[0];
        if (adjuntosSeleccionados.length >= MAX_ADJ) {
            UI.toast.warning(`Solo puedes agregar hasta ${MAX_ADJ} archivos.`);
            return;
        }
        if (file.size > MAX_SIZE * 1024 * 1024) {
            UI.toast.warning(`"${file.name}" supera el límite de ${MAX_SIZE} MB.`);
            return;
        }
        adjuntosSeleccionados.push(file);
        renderAdjuntos();
        input.value = '';
    }

    function quitarAdjunto(i) {
        adjuntosSeleccionados.splice(i, 1);
        renderAdjuntos();
    }

    function renderAdjuntos() {
        const lista = document.getElementById('adjuntosList');
        const contador = document.getElementById('contadorAdjuntos');
        contador.textContent = adjuntosSeleccionados.length;

        if (!adjuntosSeleccionados.length) {
            lista.innerHTML = '<li class="list-group-item text-center text-muted py-3">' +
                '<i class="bi bi-inbox me-1"></i> No hay archivos agregados</li>';
            return;
        }
        lista.innerHTML = adjuntosSeleccionados.map((f, i) =>
            `<li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                <span><i class="bi bi-file-earmark-text text-muted me-2"></i> ${f.name}
                    <small class="text-muted ms-2">(${(f.size/1024/1024).toFixed(2)} MB)</small>
                </span>
            </li>`
        ).join('');
    }

    // Envío del formulario
    document.getElementById('supportForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submitBtn');

        // Validar campos requeridos
        const requeridos = ['nombres', 'correo_corporativo', 'departamento',
            'asunto', 'sistema_afectado', 'urgencia', 'descripcion'
        ];
        let valido = true;
        requeridos.forEach(id => {
            const el = document.getElementById(id);
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                valido = false;
            } else {
                el.classList.remove('is-invalid');
            }
        });

        if (!valido) {
            UI.toast.warning('Por favor, completa todos los campos obligatorios.');
            return;
        }

        // Validar email
        const email = document.getElementById('correo_corporativo').value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            document.getElementById('correo_corporativo').classList.add('is-invalid');
            UI.toast.error('Ingresa un correo electrónico válido.');
            return;
        }

        // Construir FormData
        const formData = new FormData(this);

        // Inyectar archivos acumulados
        formData.delete('adjuntos[]');
        adjuntosSeleccionados.forEach(f => formData.append('adjuntos[]', f));

        // Estado del botón
        const textoOriginal = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';

        try {
            const response = await fetch('enviar_soporte.php', {
                method: 'POST',
                body: formData
            });
            const text = await response.text();

            let result;
            try {
                result = JSON.parse(text);
            } catch {
                throw new Error('Respuesta inválida del servidor');
            }

            if (result.success) {
                UI.modal({
                    title: '¡Solicitud Enviada!',
                    icon: 'success',
                    html: `<p><strong>${result.message}</strong></p>
                           ${result.ticket ? `<p><strong>Ticket:</strong> ${result.ticket}</p>` : ''}
                           <p>Hemos enviado una confirmación a tu correo corporativo.</p>`,
                });
                setTimeout(() => window.location.href = 'index.php', 3500);
            } else {
                UI.toast.error(result.message);
            }
        } catch (err) {
            UI.toast.error('No se pudo conectar con el servidor.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = textoOriginal;
        }
    });

    // Quitar clase is-invalid al escribir
    document.querySelectorAll('input, textarea, select').forEach(el => {
        el.addEventListener('input', function() {
            if (this.value.trim()) this.classList.remove('is-invalid');
        });
    });
</script>
