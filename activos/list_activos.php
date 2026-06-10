<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

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
function urlFiltros(array $extras = []): string
{
    $base = ['q' => $_GET['q'] ?? '', 'tipo' => $_GET['tipo'] ?? '', 'estatus' => $_GET['estatus'] ?? ''];
    $merged = array_merge($base, $extras);
    $merged = array_filter($merged, fn($v) => $v !== '');
    return '?' . http_build_query($merged);
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Activos | GECO PROATAM</title>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- ===== Cabecera de Página ===== -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <span>Activos</span>
            </nav>
            <h1 class="orders-page-title">Activos</h1>
        </div>
        <a href="new_activo.php" class="btn-geco-primary">
            <i class="fa-solid fa-plus"></i> Agregar
        </a>
    </div>

    <!-- ===== Alertas de sesión ===== -->
    <?php if ($msg_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check"></i> <?= $msg_success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $msg_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ===== CARD 1: FILTROS ===== -->
    <div class="orders-card mb-3">
        <!-- ===== Filtros y Buscador Combinados ===== -->
        <div class="orders-filter-bar">
            <!-- Buscador (Izquierda) -->
            <form id="search-form" method="GET" class="orders-filter-search" style="margin: 0; padding: 0;">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_id) ?>">
                <input type="hidden" name="estatus" value="<?= htmlspecialchars($estatus) ?>">
                <div class="search-input-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="q" placeholder="Buscar por código o nombre..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </form>

            <!-- Selectores y Filtros (Derecha) -->
            <form id="filter-form" method="GET" class="orders-filter-selects" style="margin: 0; padding: 0; display: flex; align-items: center; gap: 0.75rem;">
                <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">

                <select name="tipo" class="form-select" style="width: auto; min-width: 150px;">
                    <option value="">-- Tipo --</option>
                    <?php
                    $tipos->data_seek(0);
                    while ($t = $tipos->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>" <?= $tipo_id == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nombre']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="estatus" class="form-select" style="width: auto; min-width: 140px;">
                    <option value="">-- Estatus --</option>
                    <option value="activo" <?= $estatus == 'activo'   ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $estatus == 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>

                <?php if ($busqueda || $tipo_id || $estatus): ?>
                    <a href="list_activos.php" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-1.5" style="font-size:0.8rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                        <i class="fa-solid fa-circle-xmark"></i> Limpiar
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ===== CARD 2: TABLA ===== -->
    <div class="orders-card">
        <div id="table-container-wrapper">
            <!-- ===== Lista / Tabla ===== -->
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="orders-table-wrap">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre del Activo</th>
                                <th>Tipo de Activo</th>
                                <th>Ubicación</th>
                                <th>Condición</th>
                                <th>Estatus</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong class="cell-folio"><?= htmlspecialchars($row['codigo']) ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--s-800);"><?= htmlspecialchars($row['nombre']) ?></div>
                                    </td>
                                    <td><span class="cell-muted"><?= htmlspecialchars($row['tipo']) ?></span></td>
                                    <td>
                                        <span class="cell-muted">
                                            <?php if ($row['ubicacion']): ?>
                                                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($row['ubicacion']) ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['condicion']): ?>
                                            <span class="status-badge status-badge--<?= htmlspecialchars($row['condicion']) ?>">
                                                <?= ucfirst($row['condicion']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="cell-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-badge--<?= $row['estatus'] === 'activo' ? 'aprobado' : 'rechazado' ?>">
                                            <?= ucfirst($row['estatus']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a href="details_activo.php?id=<?= $row['id'] ?>" class="btn-action btn-action--view" title="Ver detalle"
                                                data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                            <a href="edit_activo.php?id=<?= $row['id'] ?>" class="btn-action btn-action--edit" title="Editar activo"
                                                data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== Barra de Paginación y Conteo ===== -->
                <div class="orders-pagination-bar">
                    <div class="orders-pagination-left">
                        <span class="orders-pagination-info">
                            <?php
                            $ini = $totalRegistros > 0 ? $offset + 1 : 0;
                            $fin = min($offset + $por_pagina, $totalRegistros);
                            ?>
                            Mostrando <strong><?= $ini ?>–<?= $fin ?></strong> de <strong><?= $totalRegistros ?></strong> activos
                        </span>
                    </div>

                    <?php if ($totalPaginas > 1): ?>
                        <div class="orders-pagination-controls">
                            <nav class="orders-pagination-nav">
                                <!-- Anterior -->
                                <a class="page-btn <?= $pagina <= 1 ? 'disabled' : '' ?>" href="<?= urlFiltros(['page' => $pagina - 1]) ?>">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>

                                <?php
                                $inicio = max(1, $pagina - 2);
                                $fin    = min($totalPaginas, $pagina + 2);

                                if ($inicio > 1): ?>
                                    <a class="page-btn" href="<?= urlFiltros(['page' => 1]) ?>">1</a>
                                    <?php if ($inicio > 2): ?>
                                        <span class="page-btn disabled">…</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($p = $inicio; $p <= $fin; $p++): ?>
                                    <a class="page-btn <?= $p === $pagina ? 'active' : '' ?>" href="<?= urlFiltros(['page' => $p]) ?>"><?= $p ?></a>
                                <?php endfor; ?>

                                <?php if ($fin < $totalPaginas): ?>
                                    <?php if ($fin < $totalPaginas - 1): ?>
                                        <span class="page-btn disabled">…</span>
                                    <?php endif; ?>
                                    <a class="page-btn" href="<?= urlFiltros(['page' => $totalPaginas]) ?>"><?= $totalPaginas ?></a>
                                <?php endif; ?>

                                <!-- Siguiente -->
                                <a class="page-btn <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>" href="<?= urlFiltros(['page' => $pagina + 1]) ?>">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ===== Estado Vacío ===== -->
                <div class="orders-empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <p>No se encontraron activos registrados</p>
                    <?php if ($busqueda || $tipo_id || $estatus): ?>
                        <a href="list_activos.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4 mt-2" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-circle-xmark"></i> Limpiar filtros
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div> <!-- /table-container-wrapper -->

    </div> <!-- /orders-card -->

</div> <!-- /orders-page-container -->

<?php include __DIR__ . "/../includes/footer.php"; ?>

<script>
    // Función para actualizar la lista vía AJAX
    function initAJAX() {
        const searchForm = document.getElementById('search-form');
        const filterForm = document.getElementById('filter-form');
        const container = document.getElementById('table-container-wrapper');

        if (!searchForm || !filterForm || !container) return;

        function updateList(url, pushState = true) {
            container.style.opacity = '0.5';
            container.style.pointerEvents = 'none';

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('table-container-wrapper');

                    if (newContent) {
                        container.innerHTML = newContent.innerHTML;
                    }

                    const newSearch = doc.getElementById('search-form');
                    const newFilter = doc.getElementById('filter-form');
                    if (newSearch) syncForm(searchForm, newSearch);
                    if (newFilter) syncForm(filterForm, newFilter);

                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';

                    if (pushState) window.history.pushState({}, '', url);

                    // Reinicializar tooltips
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function(tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                })
                .catch(err => {
                    console.error('Error:', err);
                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';
                });
        }

        function syncForm(current, source) {
            source.querySelectorAll('input, select').forEach(input => {
                const target = current.querySelector(`[name="${input.name}"]`);
                if (target) target.value = input.value;
            });
        }

        document.addEventListener('click', function(e) {
            const pageLink = e.target.closest('.page-link');
            if (pageLink) {
                e.preventDefault();
                updateList(pageLink.href);
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });

        [searchForm, filterForm].forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams(new FormData(filterForm));
                const searchData = new FormData(searchForm);
                params.set('q', searchData.get('q') || "");

                params.set('page', '1');
                updateList('?' + params.toString());
            });
        });

        filterForm.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', () => filterForm.requestSubmit());
        });
    }

    document.addEventListener('DOMContentLoaded', initAJAX);
</script>