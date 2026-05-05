<!-- Archivo para cambiar contraseña temporal -->
<?php
require_once "config.php";

// Incluir el gestor de sesiones UNA sola vez
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();
?>

<!DOCTYPE html>
<html lang="es">
<head>
// Incluir el gestor de sesiones UNA sola vez
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cambio de Contraseña</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/change_pass.css"> 
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css">
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<style>
    .button-57 i {
  display: block;
  margin-right: 0.50em;
  transform-origin: center center;
  transition: transform 0.3s ease-in-out;
  transform: rotate(90deg) scale(1.1);
}
</style>
</head>
<body>


<div class="form-container">
    <!-- Logo -->
    <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
        <img
            src="<?= BASE_URL ?>/assets/img/proatam.png"
            alt="Logo PROATAM"
            width="200"
            height="auto"
            class="d-inline-block align-text-top"
        />
    </a>
    <h2>Cambio de Contraseña</h2>
    <p>Debes cambiar tu contraseña temporal para continuar.</p>

    <form id="changePassForm">
        <div class="form-group position-relative">
            <label for="new_password" class="form-label">Nueva Contraseña <span class="required">*</span></label>
            <div class="input-group">
                <input type="password" name="new_password" id="new_password" class="form-control" 
                       required minlength="6" maxlength="12"
                       placeholder="Entre 6 y 12 caracteres">
                <button type="button" class="btn btn-outline-secondary toggle-password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <small class="form-text text-muted">La contraseña debe tener entre 6 y 12 caracteres.</small>
        </div>

        <div class="form-group position-relative">
            <label for="confirm_password" class="form-label">Confirmar Contraseña <span class="required">*</span></label>
            <div class="input-group">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                       required minlength="6" maxlength="12"
                       placeholder="Repite la contraseña">
                <button type="button" class="btn btn-outline-secondary toggle-password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="button-57">
            <i class="bi bi-key"></i><span>Actualizar Contraseña</span>
        </button>
    </form>
</div>

<script>
// Mostrar/ocultar contraseña con cambio de ícono
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.parentElement.querySelector('input');
        const icon = btn.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});

// Validación en tiempo real
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const feedback = document.getElementById('password-feedback') || createPasswordFeedback();
    
    if (password.length > 0) {
        if (password.length < 6) {
            feedback.textContent = `Muy corta (mínimo 6 caracteres). Actual: ${password.length}`;
            feedback.className = 'form-text text-danger';
        } else if (password.length > 12) {
            feedback.textContent = `Muy larga (máximo 12 caracteres). Actual: ${password.length}`;
            feedback.className = 'form-text text-danger';
        } else {
            feedback.textContent = 'Longitud correcta ✓';
            feedback.className = 'form-text text-success';
        }
    } else {
        feedback.textContent = 'La contraseña debe tener entre 6 y 12 caracteres.';
        feedback.className = 'form-text text-muted';
    }
});

function createPasswordFeedback() {
    const feedback = document.createElement('div');
    feedback.id = 'password-feedback';
    feedback.className = 'form-text text-muted';
    feedback.textContent = 'La contraseña debe tener entre 6 y 12 caracteres.';
    document.querySelector('#new_password').closest('.form-group').appendChild(feedback);
    return feedback;
}

// Manejo del envío del formulario
document.getElementById('changePassForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const newPass = document.getElementById('new_password').value.trim();
    const confirmPass = document.getElementById('confirm_password').value.trim();

    if (newPass === '' || confirmPass === '') {
        UI.toast.warning('Debes completar ambos campos.');
        return;
    }

    // Validación de longitud (6-12 caracteres)
    if (newPass.length < 6) {
        UI.toast.error('La contraseña debe tener al menos 6 caracteres.');
        return;
    }

    if (newPass.length > 12) {
        UI.toast.error('La contraseña no puede tener más de 12 caracteres.');
        return;
    }

    // Validación de coincidencia
    if (newPass !== confirmPass) {
        UI.toast.error('Las contraseñas no coinciden.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('new_password', newPass);
        formData.append('confirm_password', confirmPass);

        const response = await fetch('update_password.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            UI.toast.success(result.message || 'Contraseña actualizada correctamente.', 2000);
            setTimeout(() => { window.location.href = 'index.php'; }, 2000);
        } else {
            UI.toast.error(result.message || 'Ocurrió un error, intenta nuevamente.');
        }

    } catch (error) {
        UI.toast.error('No se pudo conectar con el servidor.');
    }
});
</script>

<script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>

</body>
</html>
