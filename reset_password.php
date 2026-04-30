<!-- Archivo para cambiar contraseña con el token una vez que se solicita -->

<?php
require_once "config.php";

require_once config.php;
session_start();
// Si ya está loggeado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Verificar si hay un token válido de reset
$token = $_GET['token'] ?? '';
if (empty($token) || !isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $token) {
    header("Location: " . BASE_URL . "/forgot_password.php");
    exit();
}

// Verificar expiración
if (!isset($_SESSION['reset_token_expiry']) || time() > $_SESSION['reset_token_expiry']) {
    unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_token_expiry']);
    header("Location: forgot_password.php?error=expired");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/change_pass.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="page-wrapper">
    <div class="form-container">
         <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
        <img
            src="<?= BASE_URL ?>/assets/img/proatam.png"
            alt="Logo PROATAM"
            width="200"
            height="auto"
            class="d-inline-block align-text-top"
        />
    </a>
        <h2>Nueva Contraseña</h2>
        <p>Crea una nueva contraseña para tu cuenta.</p>

        <form id="resetPasswordForm">
            <input type="hidden" name="reset_token" value="<?php
require_once "config.php";

require_once config.php; echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="new_password" class="form-label">Nueva Contraseña <span class="required">*</span></label>
                <input type="text" name="new_password" id="new_password" class="form-control" required 
                       placeholder="Ingresa tu nueva contraseña" minlength="6" maxlength="12"
                       oninput="validatePasswordLength(this)">
                </button>
                <div class="form-text">La contraseña debe tener entre 6 y 12 caracteres.</div>
                <div id="passwordLengthFeedback" class="form-text text-danger" style="display: none;">
                    La contraseña debe tener entre 6 y 12 caracteres.
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirmar Contraseña <span class="required">*</span></label>
                <input type="text" name="confirm_password" id="confirm_password" class="form-control" required 
                       placeholder="Confirma tu nueva contraseña" minlength="6" maxlength="12">
                </button>
                <div id="passwordMatchFeedback" class="form-text text-danger" style="display: none;">
                    Las contraseñas no coinciden.
                </div>
            </div>

            <button type="submit" class="button-57" id="submitBtn">
                <i class="bi bi-key"></i><span>Cambiar Contraseña</span>
            </button>
        </form>

    </div>
</div>

    <script>
    function validatePasswordLength(input) {
        const feedback = document.getElementById('passwordLengthFeedback');
        const password = input.value;
        
        if (password.length > 12) {
            feedback.style.display = 'block';
            input.classList.add('is-invalid');
        } else {
            feedback.style.display = 'none';
            input.classList.remove('is-invalid');
        }
    }

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

    // Validar coincidencia de contraseñas en tiempo real
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;
        const feedback = document.getElementById('passwordMatchFeedback');
        
        if (confirmPassword && newPassword !== confirmPassword) {
            feedback.style.display = 'block';
            this.classList.add('is-invalid');
        } else {
            feedback.style.display = 'none';
            this.classList.remove('is-invalid');
        }
    });

    document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const resetToken = document.querySelector('input[name="reset_token"]').value;
        const submitBtn = document.getElementById('submitBtn');
        
        // Validar longitud de contraseña
        if (newPassword.length < 6 || newPassword.length > 12) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Longitud incorrecta', 
                text: 'La contraseña debe tener entre 6 y 12 caracteres.' 
            });
            return;
        }

        if (newPassword !== confirmPassword) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Contraseñas no coinciden', 
                text: 'Las contraseñas ingresadas no son iguales.' 
            });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i><span>Cambiando...</span>';

        try {
            const formData = new FormData();
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            formData.append('reset_token', resetToken);

            const response = await fetch('update_password_reset.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Contraseña Actualizada!',
                    text: result.message,
                    confirmButtonText: 'Ir al Login'
                }).then(() => {
                    window.location.href = 'login.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message
                });
            }

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor.'
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-key"></i><span>Cambiar Contraseña</span>';
        }
    });

    // Validar en tiempo real mientras se escribe
    document.getElementById('new_password').addEventListener('input', function() {
        const password = this.value;
        const feedback = document.getElementById('passwordLengthFeedback');
        
        if (password.length > 12) {
            feedback.style.display = 'block';
            this.classList.add('is-invalid');
        } else {
            feedback.style.display = 'none';
            this.classList.remove('is-invalid');
        }
    });
    </script>

    <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
    
</body>
</html>


