<?php
require_once __DIR__ . '/config.php';

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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/change_pass.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="row forgot-container shadow-lg">
        <!-- Panel de imagen -->
        <div class="col-lg-6 d-none d-lg-flex image-panel p-0">
            <div class="custom-carousel">
                <div class="carousel-track">
                    <div class="carousel-slide active">
                        <img src="<?= BASE_URL ?>/assets/img/slider4.png" alt="Imagen decorativa 1" class="img-fluid w-100 h-80 object-fit-cover">
                    </div>
                    <div class="carousel-slide">
                        <img src="<?= BASE_URL ?>/assets/img/slider5.png" alt="Imagen decorativa 2" class="img-fluid w-100 h-80 object-fit-cover">
                    </div>
                    <div class="carousel-slide">
                        <img src="<?= BASE_URL ?>/assets/img/slider3.png" alt="Imagen decorativa 3" class="img-fluid w-100 h-80 object-fit-cover">
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de formulario -->
        <div class="col-lg-6 col-md-12 forgot-panel d-flex align-items-center justify-content-center">
            <div class="w-100 px-2 px-lg-2">
                <div class="form-container">

                    <a class="navbar-brand">
                        <img src="<?= BASE_URL ?>/assets/img/proatam.png" alt="Logo PROATAM" />
                    </a>
                    <h2>Recuperar Contraseña</h2>
                    <p class="text-muted">Ingresa tu correo corporativo para recibir un código de verificación.</p>

                    <form id="forgotPasswordForm">
                        <div class="form-group">
                            <label for="email" class="form-label">Correo Corporativo</label>
                            <input type="email" name="email" id="email" class="form-control" required
                                placeholder="usuario@proatam.com">
                        </div>

                        <button type="submit" class="button-57" id="submitBtn">
                            <i class="bi bi-send"></i><span>Enviar Código</span>
                        </button>
                    </form>

                    <div class="text-center mt-3 mb-3">
                        <a href="login.php" class="forgot_pass">¿Recordaste tu contraseña? Inicia Sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value.trim();
            const submitBtn = document.getElementById('submitBtn');

            if (!email) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo vacío',
                    text: 'Ingresa tu correo electrónico.'
                });
                return;
            }

            // Validar formato de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Email inválido',
                    text: 'Ingresa un correo electrónico válido.'
                });
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i><span>Enviando...</span>';

            try {
                const formData = new FormData();
                formData.append('email', email);

                const response = await fetch('send_reset_token.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Código Enviado',
                        text: result.message,
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        // Redirigir a la página de verificación
                        window.location.href = `verify_token.php?email=${encodeURIComponent(email)}`;
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
                submitBtn.innerHTML = '<i class="bi bi-send"></i><span>Enviar Código</span>';
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
                setInterval(nextSlide, 5000); // Cambiar cada 5 segundos
            }
        });
    </script>

</body>

</html>

