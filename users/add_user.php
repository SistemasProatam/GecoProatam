<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

// Obtener departamentos para el select
$departamentos = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
$departamentosOptions = "";
while ($dep = $departamentos->fetch_assoc()) {
    $departamentosOptions .= "<option value='{$dep['id']}'>{$dep['nombre']}</option>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Agregar Nuevo Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/new_order.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">

    <style>
        .form-body {
            padding-top: 0;
        }

        #loadingOverlay {
            display: none !important;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . "/../includes/navbar.php"; ?>

    <!-- HERO SECTION -->
    <div class="hero-section">
        <div class="container hero-content">
            <div class="breadcrumb-custom">
                <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
                <span>/</span>
                <a href="list_users.php"> Registro de Usuarios</a>
                <span>/</span>
                <span>Agregar Usuario</span>
            </div>

            <div class="row align-items-end">
                <div class="col-lg-8">
                    <h1 class="hero-title">Agregar Nuevo Usuario</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content-wrapper">
        <div class="form-container">
            <div class="form-body">
                <form id="formAgregarUsuario" method="POST" action="insert_user.php" enctype="multipart/form-data">

                    <!-- Información Básica -->
                    <div class="section-title">
                        <h4><i class="bi bi-person"></i> Información Básica</h4>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombres <span class="required">*</span></label>
                                <input type="text" name="nombres" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Apellidos <span class="required">*</span></label>
                                <input type="text" name="apellidos" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Correo Corporativo <span class="required">*</span></label>
                                <input type="email" name="correo_corporativo" class="form-control" required>
                                <small class="text-muted">Debe terminar en @proatam.com</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Correo Personal</label>
                                <input type="email" name="correo_personal" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Número de Celular Particular</label>
                                <input type="text" name="telefono_personal" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Departamento <span class="required">*</span></label>
                                <select name="departamento_id" class="form-select" required>
                                    <option value="">-- Seleccionar Departamento --</option>
                                    <?= $departamentosOptions ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Fecha de Ingreso <span class="required">*</span></label>
                                    <input type="date" name="fecha_ingreso" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Funciones y Actividades -->
                    <div class="mb-3">
                        <label class="form-label">Lista de Funciones y Actividades a Cargo</label>
                        <textarea name="funciones_actividades" class="form-control" rows="4"
                            placeholder="Describa las funciones y actividades que tendrá a cargo el usuario..."></textarea>
                    </div>

                    <!-- Contacto de Emergencia -->
                    <div class="section-title">
                        <h4><i class="bi bi-telephone"></i> Contacto de Emergencia</h4>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nombre Completo</label>
                                <input type="text" name="contacto_emergencia_nombre" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Parentesco</label>
                                <input type="text" name="contacto_emergencia_parentesco" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Número de Celular</label>
                                <input type="text" name="contacto_emergencia_telefono" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Documentos del Expediente -->
                    <div class="section-title">
                        <h4><i class="bi bi-folder"></i> Documentos del Expediente</h4>
                    </div>

                    <div class="document-section">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Curriculum Vitae</label>
                                    <input type="file" name="curriculum_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Identificación Oficial</label>
                                    <input type="file" name="identificacion_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Acta de Nacimiento</label>
                                    <input type="file" name="acta_nacimiento_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">CURP</label>
                                    <input type="file" name="curp_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Constancia de Situación Fiscal</label>
                                    <input type="file" name="situacion_fiscal_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Número de Seguro Social</label>
                                    <input type="file" name="nss_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Comprobante de Domicilio</label>
                                    <input type="file" name="comprobante_domicilio_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Foto</label>
                                    <input type="file" name="foto_jpg" class="form-control" accept=".jpg,.jpeg">
                                    <small class="text-muted">Formato JPG con fondo blanco</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Último Comprobante de Estudios</label>
                                    <input type="file" name="comprobante_estudios_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Credencial Corporativa</label>
                                    <input type="file" name="credencial_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>

                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Acuerdo de Confidencialidad</label>
                                    <input type="file" name="acuerdo_confidencialidad_pdf" class="form-control" accept=".pdf">
                                    <small class="text-muted">Formato PDF</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contratos</label>
                                <div id="contratos-container">
                                    <div class="contrato-item mb-2">
                                        <div class="input-group">
                                            <input type="file" name="contratos[]" class="form-control" accept=".pdf">
                                            <select name="tipos_contrato[]" class="form-select">
                                                <option value="">-- Tipo --</option>
                                                <option value="Indeterminado">Tiempo Indeterminado</option>
                                                <option value="Determinado">Tiempo Determinado</option>
                                                <option value="Prueba">Periodo de Prueba</option>
                                                <option value="Obra">Obra Determinada</option>
                                                <option value="Otro">Otro</option>
                                            </select>
                                            <button type="button" class="btn btn-danger btn-remove-contrato" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary mt-2" id="agregar-contrato">
                                    <i class="bi bi-plus"></i> Agregar otro contrato
                                </button>
                                <small class="text-muted">Puedes subir múltiples contratos</small>
                            </div>
                        </div>
                    </div>

                    <!-- Guardar -->
                    <div class="form-actions mt-3">

                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-between mt-4">
                            <button type="submit" class="button-57">
                                <i class="bi bi-floppy"></i> Guardar Usuario
                            </button>
                        </div>

                    </div>

                </form>
            </div>
        </div>
    </div>

    <div id="loadingOverlay">
        <div class="loading-box">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-3">Procesando… por favor espere</div>
        </div>
    </div>

    <?php include __DIR__ . "/../includes/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Manejar el envío del formulario - Versión consolidada
        document.getElementById('formAgregarUsuario').addEventListener('submit', function(e) {
            e.preventDefault();

            // Mostrar overlay y deshabilitar botones
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.style.display = 'flex';
            this.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = true);

            const formData = new FormData(this);

            fetch('insert_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            html: data.message,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.location.href = 'list_users.php';
                        });
                    } else {
                        // Ocultar overlay en caso de error
                        if (overlay) overlay.style.display = 'none';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message,
                            confirmButtonText: 'Aceptar'
                        });
                        // Habilitar botones nuevamente
                        this.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = false);
                    }
                })
                .catch(error => {
                    // Ocultar overlay en caso de error
                    if (overlay) overlay.style.display = 'none';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo conectar con el servidor',
                        confirmButtonText: 'Aceptar'
                    });
                    // Habilitar botones nuevamente
                    this.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = false);
                });
        });
    </script>
    <script>
        // Manejar contratos múltiples
        document.getElementById('agregar-contrato').addEventListener('click', function() {
            const container = document.getElementById('contratos-container');
            const newItem = document.createElement('div');
            newItem.className = 'contrato-item mb-2';
            newItem.innerHTML = `
            <div class="input-group">
                <input type="file" name="contratos[]" class="form-control" accept=".pdf">
                <select name="tipos_contrato[]" class="form-select">
                    <option value="">-- Tipo --</option>
                    <option value="Indeterminado">Tiempo Indeterminado</option>
                    <option value="Determinado">Tiempo Determinado</option>
                    <option value="Prueba">Periodo de Prueba</option>
                    <option value="Obra">Obra Determinada</option>
                    <option value="Otro">Otro</option>
                </select>
                <button type="button" class="btn btn-danger btn-remove-contrato">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
            container.appendChild(newItem);

            // Habilitar botón de eliminar en todos menos el primero
            const removeBtns = document.querySelectorAll('.btn-remove-contrato');
            removeBtns[0].disabled = removeBtns.length <= 1;
        });

        // Delegar evento para eliminar contratos
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-contrato')) {
                const item = e.target.closest('.contrato-item');
                item.remove();

                // Si solo queda uno, deshabilitar su botón de eliminar
                const removeBtns = document.querySelectorAll('.btn-remove-contrato');
                if (removeBtns.length === 1) {
                    removeBtns[0].disabled = true;
                }
            }
        });
    </script>
</body>

</html>

