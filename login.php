<?php
require_once __DIR__ . "/conexion.php"; // Ajusta la ruta si es necesario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está loggeado, redirigir al index
if (isset($_SESSION['user_id'])) {
    // Limpiar pestañas anteriores al hacer login
    if (isset($_SESSION['active_tabs'])) {
        $_SESSION['active_tabs'] = [];
    }
    header("Location: " . BASE_URL . "/index.php");
    exit();
}
$_SESSION['active_tabs'] = []; // Limpiar pestañas anteriores

if (isset($_GET['error'])) {
    $error_messages = [
        'session_expired' => 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.',
        'session_timeout' => 'Tu sesión ha caducado por inactividad.'
    ];

    $error_message = $error_messages[$_GET['error']] ?? 'Error desconocido';

    echo "<script>document.addEventListener('DOMContentLoaded', function() {
        UI.toast.warning('$error_message');
    });</script>";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión | GECO PROATAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/tokens.css?v=1.2">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/auth.css?v=1.2">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css?v=1.1">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/GECO_ISOLOGO.png" type="image/x-icon">
    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>

<body>

    <div class="auth-wrapper">
        <div class="row g-0 auth-container">
            <!-- Panel de login -->
            <div class="col-lg-5 col-xl-4 auth-panel">
                <div class="auth-form-wrapper">
                    <img src="<?= BASE_URL ?>/assets/img/GECO.png" alt="Logo GECO" class="auth-logo" />
                    <h1 class="auth-title">Acceso al Sistema.</h1>
                    <p class="auth-subtitle">Gestión y Control Operativo PROATAM.</p>

                    <div id="loginAlertContainer"></div>

                    <form id="loginForm" class="auth-form">
                        <div class="auth-form-group">
                            <label for="email" class="auth-label">CORREO CORPORATIVO</label>
                            <input type="email" class="auth-control" id="email" name="correo_corporativo"
                                placeholder="nombre@proatam.com" required>
                        </div>

                        <div class="auth-form-group">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="auth-label mb-0">CONTRASEÑA</label>
                                <a href="forgot_password.php" class="forgot-link">¿OLVIDASTE TU CONTRASEÑA?</a>
                            </div>
                            <div class="position-relative">
                                <input type="password" class="auth-control" id="password" name="password" placeholder="••••••••" required>
                                <button type="button" class="toggle-password">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="auth-button">
                            <span>Iniciar Sesión</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="auth-footer">
                        <p>© 2026 PROATAM. Todos los derechos reservados.</p>
                    </div>
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
                                    "Infraestructura que transforma comunidades y construye legados."
                                </blockquote>
                                <cite class="quote-author">DIRECCIÓN GENERAL, PROATAM</cite>
                            </div>
                        </div>
                    </div>
                    <!-- Slide 2 -->
                    <div class="carousel-slide">
                        <img src="<?= BASE_URL ?>/assets/img/slider1.png" alt="Industrial 2" class="carousel-img">
                        <div class="carousel-overlay">
                            <div class="carousel-quote">
                                <blockquote class="quote-text">
                                    "La precisión en cada detalle define nuestra excelencia operativa."
                                </blockquote>
                                <cite class="quote-author">OPERACIONES, PROATAM</cite>
                            </div>
                        </div>
                    </div>
                    <!-- Slide 3 -->
                    <div class="carousel-slide">
                        <img src="<?= BASE_URL ?>/assets/img/slider2.png" alt="Industrial 3" class="carousel-img">
                        <div class="carousel-overlay">
                            <div class="carousel-quote">
                                <blockquote class="quote-text">
                                    "Innovación constante para los retos del mañana."
                                </blockquote>
                                <cite class="quote-author">INGENIERÍA, PROATAM</cite>
                            </div>
                        </div>
                    </div>

                    <div class="carousel-dots">
                        <span class="dot active" data-slide="0"></span>
                        <span class="dot" data-slide="1"></span>
                        <span class="dot" data-slide="2"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>

    <script>
        // Mostrar/ocultar contraseña
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.parentElement.querySelector('input');
                const icon = btn.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            UI.inline.clear('#loginAlertContainer');

            const formData = new FormData(this);

            try {
                const response = await fetch('login_process.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    UI.toast.success('¡Bienvenido! Redirigiendo...');
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1200);
                } else {
                    if (result.message === 'solo_correo_proatam') {
                        UI.inline.show('#loginAlertContainer', 'Solo se permiten correos corporativos <strong>@proatam.com</strong>', 'warning');
                    } else if (result.message === 'correo_no_registrado') {
                        UI.inline.show('#loginAlertContainer', 'Este correo no está registrado en el sistema. Contacta a RRHH.', 'error');
                    } else {
                        UI.inline.show('#loginAlertContainer', result.message, 'error');
                    }
                }
            } catch (error) {
                UI.toast.error('No se pudo conectar con el servidor');
            }
        });
    </script>

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

        // Iniciar
        startInterval();
    </script>
</body>

</html>