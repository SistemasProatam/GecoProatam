<?php
require_once "config.php";
session_start();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/auth.css?v=1.1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css?v=1.1">
</head>

<body>
    <div class="auth-wrapper">
        <div class="row g-0 auth-container">
            <!-- Panel de Mensaje -->
            <div class="col-lg-5 col-xl-4 auth-panel">
                <div class="auth-form-wrapper" style="text-align: center;">
                    <img src="<?= BASE_URL ?>/assets/img/proatam.png" alt="Logo PROATAM" class="auth-logo" style="margin: 0 auto 30px auto;" />
                    
                    <div style="background-color: rgba(220, 53, 69, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
                        <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: #dc3545;"></i>
                    </div>

                    <h1 class="auth-title" style="text-align: center;">Acceso Restringido</h1>
                    <p class="auth-subtitle" style="text-align: center; margin-bottom: 30px;">
                        No tienes los permisos necesarios para acceder a este módulo o realizar esta acción.
                    </p>

                    <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 40px;">
                        Si crees que esto es un error, por favor contacta al administrador del sistema.
                    </p>

                    <div class="d-flex flex-column gap-3">
                        <a href="<?= BASE_URL ?>/index.php" class="auth-button" style="text-decoration: none;">
                            <span>Regresar al Inicio</span>
                            <i class="bi bi-house-door"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/logout.php" class="auth-button" style="background: transparent; color: var(--p-500); border: 2px solid var(--p-500); text-decoration: none;">
                            <span>Cerrar Sesión</span>
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
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
                                    "Los protocolos de seguridad protegen nuestra integridad operativa."
                                </blockquote>
                                <cite class="quote-author">SISTEMAS, PROATAM</cite>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>