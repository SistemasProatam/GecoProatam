<?php
require_once __DIR__ . "/conexion.php"; // Ajusta la ruta si es necesario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya estÃ¡ loggeado, redirigir al index
if (isset($_SESSION['user_id'])) {
    // Limpiar pestaÃ±as anteriores al hacer login
    if (isset($_SESSION['active_tabs'])) {
        $_SESSION['active_tabs'] = [];
    }
    header("Location: " . BASE_URL . "/index.php");
    exit();
}
$_SESSION['active_tabs'] = []; // Limpiar pestaÃ±as anteriores

if (isset($_GET['error'])) {
    $error_messages = [
        'session_expired' => 'Tu sesiÃ³n ha expirado. Por favor, inicia sesiÃ³n nuevamente.',
        'session_timeout' => 'Tu sesiÃ³n ha caducado por inactividad.'
    ];

    $error_message = $error_messages[$_GET['error']] ?? 'Error desconocido';

    echo "<script>document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'warning',
            title: 'SesiÃ³n Expirada',
            text: '$error_message'
        });
    });</script>";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de SesiÃ³n</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/login.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <h2>Inicio de SesiÃ³n</h2>

                    <form id="loginForm">
                        <div class="form-group">
                            <label for="email" class="form-label">Correo Corporativo</label>
                            <input type="email"
                                class="form-control custom-input"
                                id="email" name="correo_corporativo"
                                placeholder="usuario@proatam.com">
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">ContraseÃ±a</label>
                            <div class="input-group">
                                <input type="password" class="form-control custom-input" id="password" name="password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                                <button type="button" class="btn btn-outline-secondary toggle-password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="text-center mt-3 mb-3">
                            <a href="forgot_password.php" class="forgot_pass">Â¿Olvidaste tu contraseÃ±a?</a>
                        </div>

                        <button type="submit" class="button-57">
                            <i class="bi bi-key"></i><span>Iniciar SesiÃ³n</span>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Mostrar/ocultar contraseÃ±a
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
                    Swal.fire({
                        title: 'Â¡Bienvenido!',
                        html: `
            <div class="text-center">
                <small class="text-muted">Redirigiendo...</small>
            </div>
        `,
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                        background: '#f8f9fa',
                        willClose: () => {
                            window.location.href = result.redirect;
                        }
                    });

                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 2000);
                } else {
                    // Manejo de diferentes tipos de errores
                    if (result.message === 'solo_correo_proatam') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Correo no permitido',
                            html: `
                        <div class="text-center">
                            <p>Solo se permiten correos corporativos de Proatam 
                            <p class="mt-3">
                                Por favor, utiliza tu correo corporativo con dominio <strong>@proatam.com</strong>
                            </p>
                        </div>
                    `,
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#3f7555',
                            customClass: {
                                popup: 'border-warning'
                            }
                        });
                    } else if (result.message === 'correo_no_registrado') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Correo no registrado',
                            html: `
                        <div class="text-center">
                            <h5 class="fw-bold">Este correo no estÃ¡ registrado en el sistema</h5>
                            <p class="text-muted mt-3">
                                Verifica que el correo estÃ© correctamente escrito o contacta a Recursos Humanos.
                            </p>
                        </div>
                    `,
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#3f7555',
                            customClass: {
                                popup: 'border-danger'
                            }
                        });
                    } else {
                        // Mostrar otros errores normales
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message,
                            confirmButtonText: 'Intentar nuevamente'
                        });
                    }
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexiÃ³n',
                    text: 'No se pudo conectar con el servidor'
                });
            }
        });
    </script>

    <script>
        // Carrusel personalizado automÃ¡tico
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.carousel-slide');
            let currentSlide = 0;

            if (slides.length > 0) {
                // FunciÃ³n para cambiar slides
                function nextSlide() {
                    // Remover clase active del slide actual
                    slides[currentSlide].classList.remove('active');

                    // Avanzar al siguiente slide
                    currentSlide = (currentSlide + 1) % slides.length;

                    // Agregar clase active al nuevo slide
                    slides[currentSlide].classList.add('active');
                }

                // Iniciar el carrusel automÃ¡tico
                setInterval(nextSlide, 5000); // Cambiar cada 2 segundos
            }
        });
    </script>
</body>

</html>
