<?php
// reset_folios.php — Reinicia los contadores de folio para cada entidad
// SOLO PARA SUPER_ADMIN
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
    $resultados[] = "✓ Folio para {$entidad} reiniciado a 1";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reiniciar Folios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/PROATAM/assets/styles/navbar.css">
</head>
<body>
<?php require_once __DIR__ . "/../includes/navbar.php"; ?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h4><i class="bi bi-arrow-repeat"></i> Reiniciar Folios por Entidad</h4>
        </div>
        <div class="card-body">
            <h5>Resultados:</h5>
            <ul>
                <?php foreach ($resultados as $res): ?>
                    <li><?= htmlspecialchars($res) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle"></i> 
                <strong>¿Cómo funciona?</strong><br>
                Cada entidad tiene un archivo de texto en la carpeta <code>cotizaciones/data/</code> que guarda el siguiente número de folio a usar.<br>
                Al reiniciar, ese número vuelve a <strong>1</strong>, por lo que la próxima cotización generará el folio <strong>-0001</strong>.
            </div>
            <a href="/PROATAM/cotizaciones/cotizacion.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear nueva cotización
            </a>
            <a href="/PROATAM/cotizaciones/list_cotizaciones.php" class="btn btn-secondary">
                <i class="bi bi-list"></i> Ver cotizaciones
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>