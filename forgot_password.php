<?php
require_once "config.php";

session_start();
// Si ya está loggeado, redirigir al index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña</title>
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
                    <img src="<?= BASE_URL ?>/assets/img/proatam.png" alt="Logo PROATAM" class="auth-logo" />

                    <!-- Progress Steps -->
                    <div class="recovery-steps">
                        <div class="step-item active">
                            <div class="step-number">1</div>
                            <span class="step-label">Correo</span>
                        </div>
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <span class="step-label">Código</span>
                        </div>
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <span class="step-label">Nueva clave</span>
                        </div>
                    </div>

                    <h1 class="auth-title">Recuperar Clave</h1>
                    <p class="auth-subtitle">Ingresa tu correo corporativo para recibir un código de verificación.</p>

                    <div class="auth-alert-container"></div>

                    <form id="forgotPasswordForm" class="auth-form">
                        <div class="auth-form-group">
                            <label for="email" class="auth-label">CORREO CORPORATIVO</label>
                            <input type="email" name="email" id="email" class="auth-control" required
                                placeholder="nombre@proatam.com">
                        </div>

                        <button type="submit" class="auth-button" id="submitBtn">
                            <span>Enviar Código</span>
                            <i class="bi bi-send"></i>
                        </button>
                    </form>

                    <a href="login.php" class="back-link" style="display: inline-flex; align-items: center; gap: 8px; margin-top: 3vh; font-size: 0.8rem; font-weight: 600; color: var(--gray-400); text-decoration: none;">
                        <i class="bi bi-arrow-left"></i>
                        <span>Regresar al Login</span>
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
                                    "La seguridad de tu cuenta es nuestra prioridad técnica."
                                </blockquote>
                                <cite class="quote-author">SISTEMAS, PROATAM</cite>
                            </div>
                        </div>
                    </div>
                    <!-- Slide 2 -->
                    <div class="carousel-slide">
                        <img src="<?= BASE_URL ?>/assets/img/slider3.png" alt="Industrial 2" class="carousel-img">
                        <div class="carousel-overlay">
                            <div class="carousel-quote">
                                <blockquote class="quote-text">
                                    "Tecnología de vanguardia para la industria nacional."
                                </blockquote>
                                <cite class="quote-author">PRODUCCIÓN, PROATAM</cite>
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
        const dots = document.querySelectorAll('.dot');
        let currentSlide = 0;
        let slideInterval;

        function showSlide(n) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));

            currentSlide = (n + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.add('active');
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

        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                const index = parseInt(dot.getAttribute('data-slide'));
                showSlide(index);
                startInterval();
            });
        });

        startInterval();

        document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            UI.inline.clear('.auth-alert-container');

            const email = document.getElementById('email').value;
            const submitBtn = document.getElementById('submitBtn');

            if (!email) {
                UI.inline.show('.auth-alert-container', 'Por favor, ingresa tu correo.', 'warning');
                return;
            }

            // Validar formato de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                UI.toast.error('Ingresa un correo electrónico válido.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Enviando...</span><i class="bi bi-hourglass-split"></i>';

            try {
                const formData = new FormData();
                formData.append('email', email);

                const response = await fetch('send_reset_token.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    UI.toast.success(result.message, 10000);
                    // MODO PRUEBA: Mostramos alert para poder copiar el código
                    alert("Copia tu código antes de continuar: " + result.message);
                    setTimeout(() => {
                        window.location.href = `verify_token.php?email=${encodeURIComponent(email)}`;
                    }, 3000);
                } else {
                    UI.inline.show('.auth-alert-container', result.message, 'error');
                }

            } catch (error) {
                UI.toast.error('No se pudo conectar con el servidor.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Enviar Código</span><i class="bi bi-arrow-right"></i>';
            }
        });
    </script>

</body>

</html>