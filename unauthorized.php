<?php
require_once "config.php";

session_start();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso No Autorizado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="bi bi-shield-exclamation"></i> Acceso Denegado</h4>
                    </div>
                    <div class="card-body text-center">
                        <i class="bi bi-lock display-1 text-danger mb-3"></i>
                        <h3>No tiene permisos para acceder a esta página</h3>
                        <p class="text-muted">Contacte al administrador si cree que esto es un error.</p>
                        <div class="mt-4">
                            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary me-2">
                                <i class="bi bi-house"></i> Ir al Inicio
                            </a>
                            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-secondary">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>