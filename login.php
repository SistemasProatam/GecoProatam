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
    });<\/script>";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/login.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css">
    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>

<body>

    <div class="row login-container shadow-lg">
        <!-- Panel de login -->
        <div class="col-lg-6 col-md-12 login-panel d-flex align-items-center justify-content-center">
            <div class="w-100 px-2 px-lg-2">
                <div class="form-container">
                    <a class="navbar-brand">
                        <img src="<?= BASE_URL ?>/assets/img/proatam.png" alt="Logo PROATAM" />
                    </a>
                    <h2>Inicio de Sesión</h2>

                    <form id="loginForm">
                        <div class="form-group">
                            <label for="email" class="form-label">Correo Corporativo</label>
                            <input type="email"
                                class="form-control custom-input"
                                id="email" name="correo_corporativo"
                                placeholder="usuario@proatam.com">
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control custom-input" id="password" name="password" placeholder="••••••••">
                                <button type="button" class="btn btn-outline-secondary toggle-password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="text-center mt-3 mb-3">
                            <a href="forgot_password.php" class="forgot_pass">¿Olvidaste tu contraseña?</a>
                        </div>

                        <button type="submit" class="button-57">
                            <i class="bi bi-key"></i><span>Iniciar Sesión</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Panel de imagen -->
        <div class="col-lg-6 d-none d-lg-flex image-panel p-0">
            <div class="custom-carousel">
                <div class="carousel-track">
                    <div class="carousel-slide active">
                        <source srcset="<?= BASE_URL ?>/assets/img/slider1.webp" type="image/webp">
                        <img src="<?= BASE_URL ?>/assets/img/slider1.png" alt="Imagen decorativa 1" class="img-fluid w-100 h-80 object-fit-cover">
                    </div>
                    <div class="carousel-slide">
                        <source srcset="<?= BASE_URL ?>/assets/img/slider2.webp" type="image/webp">
                        <img src="<?= BASE_URL ?>/assets/img/slider2.png" alt="Imagen decorativa 2" loading="lazy" class="img-fluid w-100 h-80 object-fit-cover">
                    </div>
                    <div class="carousel-slide">
                        <source srcset="<?= BASE_URL ?>/assets/img/slider3.webp" type="image/webp">
                        <img src="<?= BASE_URL ?>/assets/img/slider3.png" alt="Imagen decorativa 3" loading="lazy" class="img-fluid w-100 h-80 object-fit-cover">
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
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
        });

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

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
                        UI.modal({
                            title: 'Correo no permitido',
                            icon: 'warning',
                            html: `<p>Solo se permiten correos corporativos de Proatam.</p>
                                   <p>Usa tu correo con dominio <strong>@proatam.com</strong></p>`,
                        });
                    } else if (result.message === 'correo_no_registrado') {
                        UI.modal({
                            title: 'Correo no registrado',
                            icon: 'error',
                            html: `<p class="mb-1"><strong>Este correo no está registrado en el sistema.</strong></p>
                                   <p class="text-muted">Verifica que el correo esté correctamente escrito o contacta a Recursos Humanos.</p>`,
                        });
                    } else {
                        UI.toast.error(result.message);
                    }
                }
            } catch (error) {
                UI.toast.error('No se pudo conectar con el servidor');
            }
        });
    </script>

    <script>
        // Carrusel personalizado automático
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.carousel-slide');
            let currentSlide = 0;

            if (slides.length > 0) {
                // Función para cambiar slides
                function nextSlide() {
                    // Remover clase active del slide actual
                    slides[currentSlide].classList.remove('active');

                    // Avanzar al siguiente slide
                    currentSlide = (currentSlide + 1) % slides.length;

                    // Agregar clase active al nuevo slide
                    slides[currentSlide].classList.add('active');
                }

                // Iniciar el carrusel automático
                setInterval(nextSlide, 5000); // Cambiar cada 2 segundos
            }
        });
    </script>
</body>

</html>
