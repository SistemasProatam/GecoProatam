<!-- Archivo para cambiar contraseña con el token una vez que se solicita -->

<?php
require_once "config.php";

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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/auth.css?v=1.1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css?v=1.1">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>

<body>

    <div class="auth-wrapper">
        <div class="row g-0 auth-container">
            <!-- Panel de formulario -->
            <div class="col-lg-5 col-xl-4 auth-panel">
                <div class="auth-form-wrapper">
                    <img src="<?= BASE_URL ?>/assets/img/proatam.png" alt="Logo PROATAM" class="auth-logo" />
                    
                    <!-- Progress Steps -->
                    <div class="recovery-steps">
                        <div class="step-item completed">
                            <div class="step-number">1</div>
                            <span class="step-label">Correo</span>
                        </div>
                        <div class="step-item completed">
                            <div class="step-number">2</div>
                            <span class="step-label">Código</span>
                        </div>
                        <div class="step-item active">
                            <div class="step-number">3</div>
                            <span class="step-label">Nueva clave</span>
                        </div>
                    </div>

                    <h1 class="auth-title">Nueva Contraseña</h1>
                    <p class="auth-subtitle">Crea una nueva contraseña segura para tu cuenta corporativa.</p>

                    <div class="auth-alert-container"></div>

                    <form id="resetPasswordForm" class="auth-form">
                        <input type="hidden" name="reset_token" value="<?= htmlspecialchars($token) ?>">
                        
                        <div class="auth-form-group">
                            <label for="new_password" class="auth-label">NUEVA CONTRASEÑA</label>
                            <input type="password" name="new_password" id="new_password" class="auth-control" required
                                placeholder="Mínimo 6 caracteres" minlength="6" maxlength="12">
                            <div id="passwordLengthFeedback" class="text-danger mt-1" style="display: none; font-size: 0.7rem;">
                                La contraseña debe tener entre 6 y 12 caracteres.
                            </div>
                        </div>

                        <div class="auth-form-group">
                            <label for="confirm_password" class="auth-label">CONFIRMAR CONTRASEÑA</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="auth-control" required
                                placeholder="Repite tu nueva contraseña" minlength="6" maxlength="12">
                            <div id="passwordMatchFeedback" class="text-danger mt-1" style="display: none; font-size: 0.7rem;">
                                Las contraseñas no coinciden.
                            </div>
                        </div>

                        <button type="submit" class="auth-button" id="submitBtn">
                            <span>Cambiar Contraseña</span>
                            <i class="bi bi-key"></i>
                        </button>
                    </form>

                    <a href="login.php" class="back-link" style="display: inline-flex; align-items: center; gap: 8px; margin-top: 3vh; font-size: 0.8rem; font-weight: 600; color: var(--gray-400); text-decoration: none;">
                        <i class="bi bi-arrow-left"></i>
                        <span>Cancelar</span>
                    </a>
                </div>
            </div>

            <!-- Panel de imagen con Carrusel -->
            <div class="col-lg-7 col-xl-8 d-none d-lg-block auth-image-panel">
                <div class="auth-carousel">
                    <div class="carousel-slide active">
                        <img src="<?= BASE_URL ?>/assets/img/login_bg_premium.png" alt="Industrial" class="carousel-img">
                        <div class="carousel-overlay">
                            <div class="carousel-quote">
                                <blockquote class="quote-text">
                                    "Tu nueva clave debe ser robusta y confidencial."
                                </blockquote>
                                <cite class="quote-author">PROATAM</cite>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>

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
            UI.inline.clear('.auth-alert-container');

            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const resetToken = document.querySelector('input[name="reset_token"]').value;
            const submitBtn = document.getElementById('submitBtn');

            // Validar longitud de contraseña
            if (newPassword.length < 6 || newPassword.length > 12) {
                UI.inline.show('.auth-alert-container', 'La contraseña debe tener entre 6 y 12 caracteres.', 'warning');
                return;
            }

            if (newPassword !== confirmPassword) {
                UI.inline.show('.auth-alert-container', 'Las contraseñas ingresadas no son iguales.', 'warning');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Cambiando...</span><i class="bi bi-hourglass-split"></i>';

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
                    UI.toast.success(result.message, 3000);
                    setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                } else {
                    UI.inline.show('.auth-alert-container', result.message, 'error');
                }

            } catch (error) {
                UI.toast.error('No se pudo conectar con el servidor.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Cambiar Contraseña</span><i class="bi bi-key"></i>';
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

    <script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>

</body>

</html>