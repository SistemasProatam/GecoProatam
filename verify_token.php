<?php
require_once "config.php";

session_start();
// Si ya está loggeado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$email = $_GET['email'] ?? '';
if (empty($email)) {
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/auth.css?v=1.1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css?v=1.1">
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
    <style>
        .button-57 i {
            display: block;
            margin-right: 0.50em;
            transform-origin: center center;
            transition: transform 0.3s ease-in-out;
            transform: rotate(0deg) scale(1.1);
        }

        .form-text {
            font-size: 0.875em;
            color: #6c757d;
            text-align: center;
        }
    </style>
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
                        <div class="step-item active">
                            <div class="step-number">2</div>
                            <span class="step-label">Código</span>
                        </div>
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <span class="step-label">Nueva clave</span>
                        </div>
                    </div>

                    <h1 class="auth-title">Verificar Código</h1>
                    <p class="auth-subtitle">Ingresa el código de 6 dígitos enviado a:<br><strong><?= htmlspecialchars($email) ?></strong></p>

                    <div class="auth-alert-container"></div>

                    <form id="verifyTokenForm" class="auth-form">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                        <div class="auth-form-group">
                            <label for="token" class="auth-label">CÓDIGO DE VERIFICACIÓN</label>
                            <input type="text" name="token" id="token" class="auth-control" required
                                placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>

                        <button type="submit" class="auth-button" id="submitBtn">
                            <span>Verificar Código</span>
                            <i class="bi bi-shield-check"></i>
                        </button>
                    </form>

                    <a href="forgot_password.php" class="back-link" style="display: inline-flex; align-items: center; gap: 8px; margin-top: 3vh; font-size: 0.8rem; font-weight: 600; color: var(--gray-400); text-decoration: none;">
                        <i class="bi bi-arrow-left"></i>
                        <span>Regresar</span>
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
                                    "Seguridad de nivel corporativo en cada proceso."
                                </blockquote>
                                <cite class="quote-author">PROATAM</cite>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('verifyTokenForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            UI.inline.clear('.auth-alert-container');

            const token = document.getElementById('token').value;
            const email = document.querySelector('input[name="email"]').value;
            const submitBtn = document.getElementById('submitBtn');

            if (token.length !== 6) {
                UI.inline.show('.auth-alert-container', 'El código debe ser de 6 dígitos.', 'warning');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Verificando...</span><i class="bi bi-hourglass-split"></i>';

            try {
                const formData = new FormData();
                formData.append('email', email);
                formData.append('token', token);

                const response = await fetch('verify_reset_token.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    UI.toast.success(result.message, 3000);
                    setTimeout(() => {
                        window.location.href = `reset_password.php?token=${result.reset_token}&email=${encodeURIComponent(email)}`;
                    }, 2000);
                } else {
                    UI.inline.show('.auth-alert-container', result.message, 'error');
                }

            } catch (error) {
                UI.toast.error('No se pudo conectar con el servidor.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Verificar Código</span><i class="bi bi-shield-check"></i>';
            }
        });

        // Auto-enfocar el campo de token
        document.getElementById('token').focus();
    </script>
    <script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>
</body>

</html>