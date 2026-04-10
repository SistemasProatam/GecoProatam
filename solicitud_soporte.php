<?php
require_once __DIR__ . '/config.php';

require_once __DIR__ . "/includes/session_manager.php";
require_once __DIR__ . "/includes/check_session.php";

checkSession();
preventCaching();

include __DIR__ . "/conexion.php";

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
        $_SESSION['user_id']    = $user_id;
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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/styles/new_order.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .activo-autocomplete { position: relative; }
        .autocomplete-list {
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1050;
            display: none;
            border-radius: 0 0 6px 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .autocomplete-item {
            padding: 9px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: .9rem;
        }
        .autocomplete-item:hover { background: #f0f4f8; }
        .autocomplete-item:last-child { border-bottom: none; }
        .activo-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e8f0fe;
            border: 1px solid #c5d8fd;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: .88rem;
            color: #113456;
            margin-top: 6px;
        }
        .activo-badge .remove-activo {
            cursor: pointer;
            color: #6c757d;
            font-size: 1rem;
            line-height: 1;
        }
        .activo-badge .remove-activo:hover { color: #dc3545; }
    </style>
</head>
<body>

<?php
 include $_SERVER['DOCUMENT_ROOT'] . "". BASE_URL ."/includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
    <div class="container hero-content">
        <div class="breadcrumb-custom">
            <a href="index.php"><i class="bi bi-house-door"></i> Inicio</a>
            <span>/</span>
            <span>Solicitud de Mantenimiento</span>
        </div>
        <div class="row align-items-end">
            <div class="col-lg-8">
                <h1 class="hero-title">Solicitud de Mantenimiento</h1>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
    <div class="form-container">
        <div class="form-body">

            <div>
                <p>
                    <b>¿Necesitas ayuda?</b> Estamos para apoyarte. <br>
                    Completa el formulario y nos pondremos en contacto contigo a través del correo corporativo.
                </p>
            </div>

            <form id="supportForm" enctype="multipart/form-data">

                <!-- ===== SOLICITANTE ===== -->
                <div class="section-title">
                    <i class="bi bi-person-circle"></i> Información del Solicitante
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Nombre completo <span class="required">*</span></label>
                            <input type="text" name="nombres" id="nombres" class="form-control" required
                                   value="<?= htmlspecialchars($user_data['nombres'] . ' ' . $user_data['apellidos']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Correo Corporativo <span class="required">*</span></label>
                            <input type="email" name="correo_corporativo" id="correo_corporativo" class="form-control" required
                                   value="<?= htmlspecialchars($user_data['correo_corporativo']) ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Departamento / Área <span class="required">*</span></label>
                            <input type="text" name="departamento" id="departamento" class="form-control" required
                                   value="<?= htmlspecialchars($user_data['departamento']) ?>">
                        </div>
                    </div>
                </div>

                <!-- ===== ACTIVO RELACIONADO ===== -->
                <div class="section-title">
                    <i class="bi bi-box-seam"></i> Activo Relacionado
                    <small class="text-muted fw-normal ms-2" style="font-size:.8rem;">(opcional)</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Buscar Activo</label>

                    <?php
 if ($activo_pre): ?>
                    <!-- Activo pre-seleccionado desde la vista de detalle -->
                    <div id="activoBadge" class="activo-badge">
                        <i class="bi bi-box-seam"></i>
                        <span id="activoBadgeText">
                            <strong><?= htmlspecialchars($activo_pre['codigo']) ?></strong>
                            – <?= htmlspecialchars($activo_pre['nombre']) ?>
                            <small class="text-muted">(<?= htmlspecialchars($activo_pre['tipo']) ?>)</small>
                        </span>
                        <span class="remove-activo" onclick="limpiarActivo()" title="Quitar activo">
                            <i class="bi bi-x-circle"></i>
                        </span>
                    </div>
                    <input type="hidden" name="activo_id"     id="activoId"     value="<?= $activo_pre['id'] ?>">
                    <input type="hidden" name="activo_codigo" id="activoCodigo" value="<?= htmlspecialchars($activo_pre['codigo']) ?>">
                    <input type="hidden" name="activo_nombre" id="activoNombre" value="<?= htmlspecialchars($activo_pre['nombre']) ?>">
                    <input type="hidden" name="activo_tipo"   id="activoTipo"   value="<?= htmlspecialchars($activo_pre['tipo']) ?>">
                    <div id="activoSearchWrap" style="display:none;">
                    <?php
 else: ?>
                    <div id="activoBadge" class="activo-badge" style="display:none;">
                        <i class="bi bi-box-seam"></i>
                        <span id="activoBadgeText"></span>
                        <span class="remove-activo" onclick="limpiarActivo()" title="Quitar activo">
                            <i class="bi bi-x-circle"></i>
                        </span>
                    </div>
                    <input type="hidden" name="activo_id"     id="activoId"     value="">
                    <input type="hidden" name="activo_codigo" id="activoCodigo" value="">
                    <input type="hidden" name="activo_nombre" id="activoNombre" value="">
                    <input type="hidden" name="activo_tipo"   id="activoTipo"   value="">
                    <div id="activoSearchWrap">
                    <?php
 endif; ?>

                        <div class="activo-autocomplete">
                            <input type="text" id="activoBusqueda" class="form-control"
                                   placeholder="Escriba código o nombre del activo...">
                            <div class="autocomplete-list" id="autocompleteList"></div>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Si el problema está relacionado con un activo registrado, selecciónelo aquí.
                        </small>
                    </div>
                </div>

                <!-- ===== DETALLES DEL PROBLEMA ===== -->
                <div class="section-title">
                    <i class="bi bi-exclamation-triangle"></i> Detalles del Problema
                </div>

                <div class="form-group">
                    <label class="form-label">Asunto <span class="required">*</span></label>
                    <input type="text" name="asunto" id="asunto" class="form-control" required
                           placeholder="Breve descripción del problema">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Sistema / Área Afectada <span class="required">*</span></label>
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
                            <label class="form-label">Nivel de Urgencia <span class="required">*</span></label>
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
                    <label class="form-label">Descripción Detallada <span class="required">*</span></label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="4" required
                              placeholder="Describe el problema con el mayor detalle posible..."></textarea>
                </div>

                <!-- ===== ARCHIVOS ADJUNTOS ===== -->
                <div class="section-title">
                    <i class="bi bi-paperclip"></i> Archivos Adjuntos
                </div>

                <div class="form-group">
                    <small class="text-muted d-block mb-3">
                        <i class="bi bi-info-circle"></i>
                        Puedes adjuntar imágenes, documentos o capturas de pantalla. Máx. 5 archivos, 5 MB c/u.
                    </small>
                    <div class="input-group">
                        <input type="file" class="form-control" id="singleFileInput"
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                        <button class="btn btn-primary" type="button" onclick="agregarAdjunto()"
                                style="background:#113456; transform:none;">
                            <i class="bi bi-plus-circle"></i> Agregar
                        </button>
                    </div>
                </div>

                <div id="adjuntosContainer" class="mt-3">
                    <h6 class="mb-2">Archivos seleccionados: <span id="contadorAdjuntos">0</span></h6>
                    <ul id="adjuntosList" class="list-group">
                        <li class="list-group-item text-center text-muted">
                            <i class="bi bi-inbox"></i> No hay archivos agregados
                        </li>
                    </ul>
                </div>

                <!-- ===== ENVIAR ===== -->
                <div class="form-actions mt-3">
                    <div class="send-otxt">
                        La respuesta a esta solicitud será enviada por medio del correo corporativo.
                    </div>
                    <div class="container overflow-hidden text-center">
                        <div class="row gx-5">
                            <div class="col">
                                <div class="p-3">
                                    <button type="submit" class="button-57" id="submitBtn">
                                        <i class="bi bi-floppy"></i> Enviar Solicitud
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Botón volver -->
<div class="fab-container-backbtn">
    <a onclick="history.back()" class="fab-button-backbtn gray">
        <i class="bi bi-arrow-left"></i>
        <span class="fab-tooltip-backbtn">Volver</span>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ----------------------------------------------------------------
// Catálogo de activos desde PHP
// ----------------------------------------------------------------
const ACTIVOS = <?= json_encode($activos_array) ?>;

// ----------------------------------------------------------------
// Autocomplete de activos
// ----------------------------------------------------------------
const inputBusqueda  = document.getElementById('activoBusqueda');
const listaAuto      = document.getElementById('autocompleteList');

if (inputBusqueda) {
    inputBusqueda.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        listaAuto.innerHTML = '';

        if (q.length < 2) { listaAuto.style.display = 'none'; return; }

        const filtrados = ACTIVOS.filter(a =>
            a.codigo.toLowerCase().includes(q) ||
            a.nombre.toLowerCase().includes(q) ||
            a.tipo.toLowerCase().includes(q)
        ).slice(0, 8);

        if (!filtrados.length) { listaAuto.style.display = 'none'; return; }

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
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.activo-autocomplete')) {
            listaAuto.style.display = 'none';
        }
    });
}

function seleccionarActivo(activo) {
    document.getElementById('activoId').value     = activo.id;
    document.getElementById('activoCodigo').value = activo.codigo;
    document.getElementById('activoNombre').value = activo.nombre;
    document.getElementById('activoTipo').value   = activo.tipo;

    document.getElementById('activoBadgeText').innerHTML =
        `<strong>${activo.codigo}</strong> – ${activo.nombre}
         <small class="text-muted">(${activo.tipo})</small>`;

    document.getElementById('activoBadge').style.display      = 'inline-flex';
    document.getElementById('activoSearchWrap').style.display = 'none';
    listaAuto.style.display = 'none';
    if (inputBusqueda) inputBusqueda.value = '';

    // Si el sistema afectado está vacío, preseleccionar "Activo / Equipo"
    const sysSelect = document.getElementById('sistema_afectado');
    if (!sysSelect.value) sysSelect.value = 'Activo / Equipo';
}

function limpiarActivo() {
    document.getElementById('activoId').value     = '';
    document.getElementById('activoCodigo').value = '';
    document.getElementById('activoNombre').value = '';
    document.getElementById('activoTipo').value   = '';

    document.getElementById('activoBadge').style.display      = 'none';
    document.getElementById('activoSearchWrap').style.display = 'block';
    if (inputBusqueda) inputBusqueda.value = '';
}

// ----------------------------------------------------------------
// Adjuntos uno a uno
// ----------------------------------------------------------------
let adjuntosSeleccionados = [];
const MAX_ADJ  = 5;
const MAX_SIZE = 5; // MB

function agregarAdjunto() {
    const input = document.getElementById('singleFileInput');
    if (!input.files.length) { alert('Seleccione un archivo primero.'); return; }

    const file = input.files[0];
    if (adjuntosSeleccionados.length >= MAX_ADJ) {
        Swal.fire('Límite alcanzado', 'Solo puedes agregar hasta ' + MAX_ADJ + ' archivos.', 'warning');
        return;
    }
    if (file.size > MAX_SIZE * 1024 * 1024) {
        Swal.fire('Archivo muy grande', `"${file.name}" supera el límite de ${MAX_SIZE} MB.`, 'warning');
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
    const lista    = document.getElementById('adjuntosList');
    const contador = document.getElementById('contadorAdjuntos');
    contador.textContent = adjuntosSeleccionados.length;

    if (!adjuntosSeleccionados.length) {
        lista.innerHTML = '<li class="list-group-item text-center text-muted">'
            + '<i class="bi bi-inbox"></i> No hay archivos agregados</li>';
        return;
    }
    lista.innerHTML = adjuntosSeleccionados.map((f, i) =>
        `<li class="list-group-item d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-earmark"></i> ${f.name}
                <small class="text-muted ms-2">(${(f.size/1024/1024).toFixed(2)} MB)</small>
            </span>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="quitarAdjunto(${i})">
                <i class="bi bi-trash"></i>
            </button>
        </li>`
    ).join('');
}

// ----------------------------------------------------------------
// Envío del formulario
// ----------------------------------------------------------------
document.getElementById('supportForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const submitBtn = document.getElementById('submitBtn');

    // Validar campos requeridos
    const requeridos = ['nombres','correo_corporativo','departamento',
                        'asunto','sistema_afectado','urgencia','descripcion'];
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
        Swal.fire({ icon:'warning', title:'Campos requeridos',
                    text:'Por favor, completa todos los campos obligatorios.' });
        return;
    }

    // Validar email
    const email = document.getElementById('correo_corporativo').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('correo_corporativo').classList.add('is-invalid');
        Swal.fire({ icon:'error', title:'Email inválido', text:'Ingresa un correo electrónico válido.' });
        return;
    }

    // Construir FormData
    const formData = new FormData(this);

    // Inyectar archivos acumulados
    formData.delete('adjuntos[]');
    adjuntosSeleccionados.forEach(f => formData.append('adjuntos[]', f));

    // Estado del botón
    const textoOriginal = submitBtn.innerHTML;
    submitBtn.disabled  = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';

    try {
        const response = await fetch('enviar_soporte.php', { method:'POST', body: formData });
        const text     = await response.text();

        let result;
        try   { result = JSON.parse(text); }
        catch { throw new Error('Respuesta inválida del servidor'); }

        if (result.success) {
            Swal.fire({
                icon : 'success',
                title: '¡Solicitud Enviada!',
                html : `<div style="text-align:left;">
                            <p><strong>${result.message}</strong></p>
                            ${result.ticket ? `<p><strong>Ticket:</strong> ${result.ticket}</p>` : ''}
                            <p>Hemos enviado una confirmación a tu correo corporativo.</p>
                        </div>`,
                confirmButtonText : 'Aceptar',
                confirmButtonColor: '#3085d6'
            }).then(() => window.location.href = 'index.php');
        } else {
            Swal.fire({ icon:'error', title:'Error al enviar',
                        html:`<p>${result.message}</p><small>Por favor, intenta nuevamente.</small>` });
        }
    } catch (err) {
        Swal.fire({ icon:'error', title:'Error de conexión',
                    text:'No se pudo conectar con el servidor.' });
    } finally {
        submitBtn.disabled  = false;
        submitBtn.innerHTML = textoOriginal;
    }
});

// Quitar clase is-invalid al escribir
document.querySelectorAll('input, textarea, select').forEach(el => {
    el.addEventListener('input', function () {
        if (this.value.trim()) this.classList.remove('is-invalid');
    });
});
</script>

<?php
 include 'includes/footer.php'; ?>
<script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
</body>
</html>

