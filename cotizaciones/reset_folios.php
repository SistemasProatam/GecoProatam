<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

$es_super_admin = ($_SESSION['departamento'] ?? '') === 'SUPER_ADMIN';
if (!$es_super_admin) {
    die("Acceso denegado. Solo SUPER_ADMIN puede reiniciar folios.");
}

$dir = __DIR__ . '/data';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$entidades = ['PROATAM', 'INGETAM', 'LUBYCOMP', 'DAVID GOMEZ'];
$resultados = [];

foreach ($entidades as $entidad) {
    $file = "$dir/folio_{$entidad}.txt";
    file_put_contents($file, '1');
    $resultados[] = "Folio para {$entidad} reiniciado a 1";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reiniciar Folios - PROATAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/navbar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/cotizaciones.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php require_once __DIR__ . "/../includes/navbar.php"; ?>

    <!-- HERO SECTION -->
    <div class="hero-section">
        <div class="container hero-content">
            <div class="breadcrumb-custom">
                <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
                <span>/</span>
                <a href="<?= BASE_URL ?>/cotizaciones/list_cotizaciones.php">Cotizaciones</a>
                <span>/</span><span>Reiniciar Folios</span>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="hero-title">Gestión de Folios</h1>
                    <p class="mt-2 text-white-50">Administración técnica de contadores de venta</p>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content-wrapper">
        <div class="form-container">
            <div class="form-body">
                <div class="section-title">
                    <i class="bi bi-arrow-repeat"></i> Resultados de la Operación
                </div>
                
                <div class="row g-3">
                    <?php foreach ($resultados as $res): ?>
                        <div class="col-md-6">
                            <div class="p-3 bg-light border rounded-3 d-flex align-items-center gap-3">
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                <span class="fw-bold"><?= htmlspecialchars($res) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-warning mt-5 border-0 shadow-sm rounded-3 p-4">
                    <div class="d-flex gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                        <div>
                            <h5 class="fw-bold">Información Importante</h5>
                            <p class="mb-0">
                                Al reiniciar los folios, el sistema comenzará a numerar desde el <strong>0001</strong> para cada entidad. 
                                Asegúrese de que este es el comportamiento deseado antes de continuar con la operación.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3 mt-5">
                    <a href="<?= BASE_URL ?>/cotizaciones/list_cotizaciones.php" class="button-cancel">
                        <i class="bi bi-list-ul"></i> Ver Registros
                    </a>
                    <a href="<?= BASE_URL ?>/cotizaciones/cotizacion.php" class="button-57">
                        <i class="bi bi-plus-lg"></i> Nueva Cotización
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../includes/footer.php"; ?>
    <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
</body>
</html>