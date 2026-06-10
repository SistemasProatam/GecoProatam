<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/cotizaciones.css">
</head>

<body>
    <?php include __DIR__ . "/../includes/navbar.php"; ?>

    <div class="hero-section">
        <div class="container hero-content">
            <div class="breadcrumb-custom">
                <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
                <span>/</span><a href="list_cotizaciones.php">Cotizaciones</a>
                <span>/</span><span>Reiniciar Folios</span>
            </div>
            <h1 class="hero-title">Gestión de Folios</h1>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="container">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h5 class="mb-4"><i class="bi bi-arrow-repeat me-2"></i>Resultados de la Operación</h5>
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

                <div class="alert alert-warning mt-5 border-0 shadow-sm rounded-3 p-4 d-flex gap-3 align-items-start">
                    <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                    <div>
                        <h6 class="fw-bold">Información Importante</h6>
                        <p class="mb-0 small text-muted">
                            Al reiniciar los folios, el sistema comenzará a numerar desde el <b>0001</b> para cada entidad.
                            Asegúrese de que este es el comportamiento deseado.
                        </p>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3 mt-5">
                    <a href="list_cotizaciones.php" class="btn btn-outline-secondary px-4">Ver Registros</a>
                    <a href="cotizacion.php" class="btn btn-primary px-4"><i class="bi bi-plus-lg me-1"></i> Nueva Cotización</a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../includes/footer.php"; ?>
    <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
</body>

</html>