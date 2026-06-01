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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                            <label class="auth-label text-center d-block">CÓDIGO DE VERIFICACIÓN</label>
                            <div class="code-input-container">
                                <input type="text" maxlength="1" class="code-input" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code">
                                <input type="text" maxlength="1" class="code-input" pattern="[0-9]" inputmode="numeric">
                                <input type="text" maxlength="1" class="code-input" pattern="[0-9]" inputmode="numeric">
                                <input type="text" maxlength="1" class="code-input" pattern="[0-9]" inputmode="numeric">
                                <input type="text" maxlength="1" class="code-input" pattern="[0-9]" inputmode="numeric">
                                <input type="text" maxlength="1" class="code-input" pattern="[0-9]" inputmode="numeric">
                            </div>
                            <div class="code-count"><span id="digitsCount">0</span>/6</div>
                            <input type="hidden" name="token" id="token" required>
                            <p class="form-text mt-2 text-start" style="font-size: 0.75rem;">Ingresa el código de 6 dígitos. Revisa también tu carpeta de spam.</p>
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
                    <!-- Slide 1 -->
                    <div class="carousel-slide active">
                        <img src="<?= BASE_URL ?>/assets/img/login_bg_main.png" alt="Industrial" class="carousel-img">
                        <div class="carousel-overlay">
                            <div class="carousel-quote">
                                <blockquote class="quote-text">
                                    "Seguridad de nivel corporativo en cada proceso."
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
        if (slides.length > 0) {
            const dots = document.querySelectorAll('.dot');
            let currentSlide = 0;
            let slideInterval;

            function showSlide(n) {
                slides.forEach(slide => slide.classList.remove('active'));
                if(dots) dots.forEach(dot => dot.classList.remove('active'));
                
                currentSlide = (n + slides.length) % slides.length;
                slides[currentSlide].classList.add('active');
                if(dots[currentSlide]) dots[currentSlide].classList.add('active');
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

            if(dots) {
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

        // Lógica de Inputs de 6 dígitos
        const codeInputs = document.querySelectorAll('.code-input');
        const hiddenToken = document.getElementById('token');
        const digitsCount = document.getElementById('digitsCount');

        function updateTokenAndCount() {
            let tokenValue = '';
            let count = 0;
            codeInputs.forEach(input => {
                tokenValue += input.value;
                if (input.value) {
                    count++;
                    input.classList.add('filled');
                } else {
                    input.classList.remove('filled');
                }
            });
            hiddenToken.value = tokenValue;
            digitsCount.textContent = count;
        }

        codeInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                if (e.target.value && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
                updateTokenAndCount();
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                    codeInputs[index - 1].value = '';
                    updateTokenAndCount();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                if (pasteData) {
                    codeInputs.forEach((input, i) => {
                        if (i < pasteData.length) {
                            input.value = pasteData[i];
                        } else {
                            input.value = '';
                        }
                    });
                    if (pasteData.length < 6) {
                        codeInputs[pasteData.length].focus();
                    } else {
                        codeInputs[5].focus();
                    }
                    updateTokenAndCount();
                }
            });
        });

        document.getElementById('verifyTokenForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            UI.inline.clear('.auth-alert-container');

            const token = hiddenToken.value;
            const email = document.querySelector('input[name="email"]').value;
            const submitBtn = document.getElementById('submitBtn');

            if (token.length !== 6) {
                UI.inline.show('.auth-alert-container', 'El código debe tener exactamente 6 dígitos.', 'warning');
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

        // Auto-enfocar el primer campo de token
        if (codeInputs.length > 0) {
            codeInputs[0].focus();
        }
    </script>
</body>

</html>