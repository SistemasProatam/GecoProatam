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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/auth.css?v=1.1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css?v=1.1">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/GECO_ISOLOGO.png" type="image/x-icon">
    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>

<body>

    <div class="auth-wrapper">
        <div class="row g-0 auth-container">
            <!-- Panel de formulario -->
            <div class="col-lg-5 col-xl-4 auth-panel">
                <div class="auth-form-wrapper">
                    <img src="<?= BASE_URL ?>/assets/img/GECO.png" alt="Logo PROATAM" class="auth-logo" />

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
                            <div class="position-relative">
                                <input type="password" name="new_password" id="new_password" class="auth-control" required
                                    placeholder="Mínimo 6 caracteres" minlength="6" maxlength="12">
                                <button type="button" class="toggle-password" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400); cursor: pointer;">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </div>

                            <div class="password-strength-container mt-2">
                                <div class="progress" style="height: 4px; border-radius: 2px;">
                                    <div class="progress-bar bg-danger" id="passwordStrengthBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span id="passwordStrengthText" style="font-size: 0.75rem; font-weight: 600; color: #dc3545;"></span>
                                </div>
                                <div class="mt-2" style="font-size: 0.7rem; color: var(--gray-400);">
                                    <div style="font-weight: 600; margin-bottom: 4px; color: var(--gray-600);">Requisito estricto:</div>
                                    <ul class="list-unstyled d-flex flex-wrap gap-2 req-list mb-2">
                                        <li id="reqLength"><i class="bi bi-circle-fill" style="font-size: 0.4rem; vertical-align: middle; margin-right: 2px;"></i>Entre 6 y 12 caracteres (límite máximo)</li>
                                    </ul>
                                    <div style="font-weight: 600; margin-bottom: 4px; color: var(--gray-600);">Recomendado para contraseña segura:</div>
                                    <ul class="list-unstyled d-flex flex-wrap gap-2 req-list">
                                        <li id="reqUpper"><i class="bi bi-circle-fill" style="font-size: 0.4rem; vertical-align: middle; margin-right: 2px;"></i>Mayúscula</li>
                                        <li id="reqNumber"><i class="bi bi-circle-fill" style="font-size: 0.4rem; vertical-align: middle; margin-right: 2px;"></i>Número</li>
                                        <li id="reqSpecial"><i class="bi bi-circle-fill" style="font-size: 0.4rem; vertical-align: middle; margin-right: 2px;"></i>Carácter especial</li>
                                    </ul>
                                </div>
                            </div>

                            <div id="passwordLengthFeedback" class="text-danger mt-1" style="display: none; font-size: 0.7rem;">
                                La contraseña no debe exceder los 12 caracteres.
                            </div>
                        </div>

                        <div class="auth-form-group">
                            <label for="confirm_password" class="auth-label">CONFIRMAR CONTRASEÑA</label>
                            <div class="position-relative">
                                <input type="password" name="confirm_password" id="confirm_password" class="auth-control" required
                                    placeholder="Repite la contraseña" minlength="6" maxlength="12">
                                <button type="button" class="toggle-password" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400); cursor: pointer;">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </div>
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
                    <!-- Slide 1 -->
                    <div class="carousel-slide active">
                        <img src="<?= BASE_URL ?>/assets/img/login_bg_main.png" alt="Industrial" class="carousel-img">
                        <div class="carousel-overlay">
                            <div class="carousel-quote">
                                <blockquote class="quote-text">
                                    "Tu nueva clave debe ser robusta y confidencial."
                                </blockquote>
                                <cite class="quote-author">PROATAM</cite>
                            </div>
                        </div>
                    </div>
                    <!-- Slide 2 -->
                    <div class="carousel-slide">
                        <img src="<?= BASE_URL ?>/assets/img/slider3.png" alt="Industrial 2" class="carousel-img">
                        <div class="carousel-overlay">
                            <div class="carousel-quote">
                                <blockquote class="quote-text">
                                    "La seguridad no es un privilegio, es un estándar."
                                </blockquote>
                                <cite class="quote-author">DIRECCIÓN GENERAL, PROATAM</cite>
                            </div>
                        </div>
                    </div>

                    <div class="carousel-dots">
                        <span class="dot active" data-slide="0"></span>
                        <span class="dot" data-slide="1"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>

    <script>
        // Carrusel Lógica
        const slides = document.querySelectorAll('.carousel-slide');
        if (slides.length > 0) {
            const dots = document.querySelectorAll('.dot');
            let currentSlide = 0;
            let slideInterval;

            function showSlide(n) {
                slides.forEach(slide => slide.classList.remove('active'));
                if (dots) dots.forEach(dot => dot.classList.remove('active'));

                currentSlide = (n + slides.length) % slides.length;
                slides[currentSlide].classList.add('active');
                if (dots[currentSlide]) dots[currentSlide].classList.add('active');
            }

            function nextSlide() {
                showSlide(currentSlide + 1);
            }

            function startInterval() {
                stopInterval();
                slideInterval = setInterval(nextSlide, 5000);
            }

            function stopInterval() {
                if (slideInterval) clearInterval(slideInterval);
            }

            if (dots) {
                dots.forEach(dot => {
                    dot.addEventListener('click', () => {
                        const index = parseInt(dot.getAttribute('data-slide'));
                        showSlide(index);
                        startInterval();
                    });
                });
            }

            startInterval();
        }

        // Mostrar/ocultar contraseña con cambio de ícono
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.parentElement.querySelector('input');
                const icon = btn.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                }
            });
        });

        // Validar coincidencia de contraseñas en tiempo real
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const feedback = document.getElementById('passwordMatchFeedback');

            if (confirmPassword === '') {
                feedback.style.display = 'none';
                this.classList.remove('is-invalid', 'is-valid');
            } else if (newPassword !== confirmPassword) {
                feedback.classList.remove('text-success');
                feedback.classList.add('text-danger');
                feedback.innerHTML = '<i class="bi bi-x-circle"></i> Las contraseñas no coinciden.';
                feedback.style.display = 'block';
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                feedback.classList.remove('text-danger');
                feedback.classList.add('text-success');
                feedback.innerHTML = '<i class="bi bi-check-circle"></i> Las contraseñas coinciden.';
                feedback.style.display = 'block';
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
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
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
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
            const pwd = this.value;
            const lengthValid = pwd.length >= 6 && pwd.length <= 12;
            const upperValid = /[A-Z]/.test(pwd);
            const numberValid = /[0-9]/.test(pwd);
            const specialValid = /[^A-Za-z0-9]/.test(pwd);

            // Update dots
            document.getElementById('reqLength').className = lengthValid ? 'valid' : '';
            document.getElementById('reqUpper').className = upperValid ? 'valid' : '';
            document.getElementById('reqNumber').className = numberValid ? 'valid' : '';
            document.getElementById('reqSpecial').className = specialValid ? 'valid' : '';

            // Calculate strength
            let strength = 0;
            if (lengthValid) strength += 25;
            if (upperValid) strength += 25;
            if (numberValid) strength += 25;
            if (specialValid) strength += 25;

            const bar = document.getElementById('passwordStrengthBar');
            const text = document.getElementById('passwordStrengthText');

            bar.style.width = strength + '%';
            if (pwd.length === 0) {
                bar.className = 'progress-bar bg-danger';
                bar.style.backgroundColor = '';
                bar.style.width = '0%';
                text.textContent = '';
                text.style.color = '#dc3545';
            } else if (strength <= 25) {
                bar.className = 'progress-bar bg-danger';
                bar.style.backgroundColor = '';
                text.textContent = 'Débil';
                text.style.color = '#dc3545';
            } else if (strength <= 50) {
                bar.className = 'progress-bar bg-warning';
                bar.style.backgroundColor = '';
                text.textContent = 'Regular';
                text.style.color = '#ffc107'; // Bootstrap warning color
            } else if (strength <= 75) {
                bar.className = 'progress-bar';
                bar.style.backgroundColor = '#fd7e14'; // Naranja
                text.textContent = 'Buena';
                text.style.color = '#fd7e14';
            } else {
                bar.className = 'progress-bar bg-success';
                bar.style.backgroundColor = '';
                text.textContent = 'Fuerte';
                text.style.color = '#198754';
            }

            const feedback = document.getElementById('passwordLengthFeedback');
            if (pwd.length > 12) {
                feedback.style.display = 'block';
                this.classList.add('is-invalid');
            } else {
                feedback.style.display = 'none';
                this.classList.remove('is-invalid');
            }

            // Check match if confirm is already typed
            const confirmPwd = document.getElementById('confirm_password').value;
            if (confirmPwd) {
                document.getElementById('confirm_password').dispatchEvent(new Event('input'));
            }
        });
    </script>

    <script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>

</body>

</html>