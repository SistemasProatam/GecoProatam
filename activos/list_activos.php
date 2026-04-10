<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

// ===== Mensajes de sesión =====
$msg_success = $_SESSION['success'] ?? '';
$msg_error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// ===== Filtros =====
$busqueda = trim($_GET['q']      ?? '');
$tipo_id  = trim($_GET['tipo']   ?? '');
$estatus  = trim($_GET['estatus'] ?? '');

$pagina    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset    = ($pagina - 1) * $por_pagina;

// ===== Query Base =====
$sqlBase = "
FROM activos a
JOIN activo_tipos t ON a.tipo_id = t.id
WHERE 1=1
";

$params = [];
$types  = "";

if (!empty($busqueda)) {
    $sqlBase .= " AND (a.nombre LIKE ? OR a.codigo LIKE ?)";
    $like      = "%$busqueda%";
    $params[]  = $like;
    $params[]  = $like;
    $types    .= "ss";
}

if (!empty($tipo_id)) {
    $sqlBase  .= " AND a.tipo_id = ?";
    $params[]  = $tipo_id;
    $types    .= "i";
}

if (!empty($estatus)) {
    $sqlBase  .= " AND a.estatus = ?";
    $params[]  = $estatus;
    $types    .= "s";
}

// ===== Total =====
$stmtTotal = $conn->prepare("SELECT COUNT(*) as total $sqlBase");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPaginas   = max(1, ceil($totalRegistros / $por_pagina));

// ===== Datos =====
$sqlDatos = "
SELECT
    a.id,
    a.codigo,
    a.nombre,
    a.estatus,
    a.condicion,
    a.ubicacion,
    a.qr_token,
    t.nombre AS tipo
$sqlBase
ORDER BY a.fecha_creacion DESC
LIMIT ? OFFSET ?
";

$paramsPag  = $params;
$typesPag   = $types . "ii";
$paramsPag[] = $por_pagina;
$paramsPag[] = $offset;

$stmt = $conn->prepare($sqlDatos);
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();

// ===== Tipos para filtro =====
$tipos = $conn->query("SELECT id, nombre FROM activo_tipos WHERE activo = 1 ORDER BY nombre ASC");

// ===== Helper: URL conservando filtros =====
function urlFiltros(array $extras = []): string {
    $base = ['q' => $_GET['q'] ?? '', 'tipo' => $_GET['tipo'] ?? '', 'estatus' => $_GET['estatus'] ?? ''];
    $merged = array_merge($base, $extras);
    $merged = array_filter($merged, fn($v) => $v !== '');
    return '?' . http_build_query($merged);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Activos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">

    <style>
        /* Badge estatus */
        .badge-activo   { background:#d1fae5; color:#065f46; border-radius:20px; padding:3px 10px; font-size:.75rem; }
        .badge-inactivo { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 10px; font-size:.75rem; }
        /* Badge condición */
        .badge-bueno    { background:#dbeafe; color:#1e40af; border-radius:20px; padding:3px 10px; font-size:.75rem; }
        .badge-regular  { background:#fef9c3; color:#92400e; border-radius:20px; padding:3px 10px; font-size:.75rem; }
        .badge-malo     { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 10px; font-size:.75rem; }

    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . "". BASE_URL ."/includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
    <div class="container hero-content">
        <div class="breadcrumb-custom">
            <a href="index.php"><i class="bi bi-house-door"></i> Inicio</a>
            <span>/</span>
            <span>Registro de Activos</span>
        </div>
        <div class="row align-items-end">
            <div class="col-lg-8">
                <h1 class="hero-title">Registro de Activos</h1>
            </div>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="form-container">
        <div class="form-body">

            <!-- ===== Alertas de sesión ===== -->
            <?php if ($msg_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= $msg_success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($msg_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= $msg_error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- ===== Buscador ===== -->
            <form class="form-search d-flex justify-content-center w-100 mb-4" method="GET">
                <input type="hidden" name="tipo"    value="<?= htmlspecialchars($tipo_id) ?>">
                <input type="hidden" name="estatus" value="<?= htmlspecialchars($estatus) ?>">
                <input class="form-control w-100" type="search" name="q"
                       placeholder="Buscar por código o nombre..."
                       value="<?= htmlspecialchars($busqueda) ?>" />
                <button class="btn btn-outline-success" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <!-- ===== Filtros ===== -->
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-4">
                <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">

                <div style="flex:0 0 auto; min-width:160px;">
                    <select name="tipo" class="form-select">
                        <option value="">-- Tipo --</option>
                        <?php while ($t = $tipos->fetch_assoc()): ?>
                            <option value="<?= $t['id'] ?>" <?= $tipo_id == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="flex:0 0 auto; min-width:160px;">
                    <select name="estatus" class="form-select">
                        <option value="">-- Estatus --</option>
                        <option value="activo"   <?= $estatus == 'activo'   ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $estatus == 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>

                <?php if ($busqueda || $tipo_id || $estatus): ?>
                <a href="list_activos.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
                <?php endif; ?>
            </form>

            <!-- ===== Contador + Botón agregar ===== -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge-num"><?= $totalRegistros ?> activo<?= $totalRegistros != 1 ? 's' : '' ?></span>
                <button class="button-56" type="button" onclick="window.location.href='new_activo.php'">
                    <i class="bi bi-plus-circle"></i> Agregar
                </button>
            </div>

            <!-- ===== Lista ===== -->
            <?php if ($result && $result->num_rows > 0): ?>
            <ul class="list-group">
                <?php while ($row = $result->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center gap-2 py-3">

                    <div style="min-width:0; flex:1;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong><?= htmlspecialchars($row['codigo']) ?></strong>
                            <span class="badge-<?= $row['estatus'] ?>">
                                <?= ucfirst($row['estatus']) ?>
                            </span>
                        </div>
                        <div class="mt-1"><?= htmlspecialchars($row['nombre']) ?></div>
                        <small class="text-muted">
                            <i class="bi bi-tag"></i> <?= htmlspecialchars($row['tipo']) ?>
                            <?php if ($row['ubicacion']): ?>
                                &nbsp;|&nbsp;<i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['ubicacion']) ?>
                            <?php endif; ?> |
                            <span <?= $row['condicion'] ?>>
                                <?= ucfirst($row['condicion']) ?>
                            </span>
                        </small>
                    </div>  

                    <!-- Botones de accion -->
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button class="btn-inf" title="Ver detalle" style="color:#fff;"
                                onclick="window.location.href='details_activo.php?id=<?= $row['id'] ?>'"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Ver detalles">
                            <i class="bi bi-info-circle"></i>
                        </button>
                        <button class="btn-ed" title="Editar" style="color:#fff;"
                                onclick="window.location.href='edit_activo.php?id=<?= $row['id'] ?>'"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Editar activo">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>

                </li>
                <?php endwhile; ?>
            </ul>

            <!-- ===== Paginación ===== -->
            <?php if ($totalPaginas > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center flex-wrap">

                    <!-- Anterior -->
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= urlFiltros(['page' => $pagina - 1]) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    // Mostrar máximo 5 páginas alrededor de la actual
                    $inicio = max(1, $pagina - 2);
                    $fin    = min($totalPaginas, $pagina + 2);

                    if ($inicio > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= urlFiltros(['page' => 1]) ?>">1</a>
                        </li>
                        <?php if ($inicio > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $inicio; $p <= $fin; $p++): ?>
                        <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="<?= urlFiltros(['page' => $p]) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($fin < $totalPaginas): ?>
                        <?php if ($fin < $totalPaginas - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= urlFiltros(['page' => $totalPaginas]) ?>"><?= $totalPaginas ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- Siguiente -->
                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= urlFiltros(['page' => $pagina + 1]) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>

                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox" style="font-size:3rem;"></i>
                <p class="mt-2">No hay activos registrados<?= ($busqueda || $tipo_id || $estatus) ? ' con esos filtros' : '' ?></p>
                <?php if ($busqueda || $tipo_id || $estatus): ?>
                    <a href="list_activos.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Limpiar filtros
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /form-body -->
    </div><!-- /form-container -->
</div><!-- /content-wrapper -->

<script>
// Inicializar tooltips de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous">
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>

