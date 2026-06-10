<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Mostrar mensajes de estado
if (isset($_GET['msg'])) {
    $folio = htmlspecialchars($_GET['folio'] ?? '');

    echo '<div class="container mt-3">';

    switch ($_GET['msg']) {
        case 'estado_actualizado_con_email':
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <strong>¡Éxito!</strong> Estado actualizado correctamente y notificación enviada por correo para la requisición <strong>' . $folio . '</strong>.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            break;

        case 'estado_actualizado_sin_email':
            echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <strong>Atención:</strong> Estado actualizado para la requisición <strong>' . $folio . '</strong>, pero no se pudo enviar la notificación por correo.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            break;

        case 'estado_actualizado_error_email':
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <strong>Error:</strong> El estado se actualizó para <strong>' . $folio . '</strong>, pero ocurrió un error al enviar la notificación.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            break;
    }

    echo '</div>';
}

// Obtener datos de sesion
$departamento_id = $_SESSION['departamento_id'] ?? 0;
$departamento_sesion = $_SESSION['departamento'] ?? '';

// Departamentos autorizados para crear ordenes de compra
$departamentos_crear_oc = [
    'Director General',
    'Subdirector General',
    'Gerente de Operaciones',
    'Tecnico de Sistemas',
    'Procura'
];

$puede_crear_oc = in_array($departamento_sesion, $departamentos_crear_oc);

// Parametros de busqueda y paginacion
$busqueda = $_GET['q'] ?? '';
$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Filtros
$estado_filtro = $_GET['estado'] ?? '';
$entidad_filtro = $_GET['entidad'] ?? '';

// Construir WHERE dinámico
$where = [];
$params = [];
$types = '';

if ($busqueda !== '') {
    $where[] = "(r.id LIKE ? OR e.nombre LIKE ? OR r.folio LIKE ?)";
    $like = "%$busqueda%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}
if ($estado_filtro !== '') {
    $where[] = "r.estado = ?";
    $params[] = $estado_filtro;
    $types .= "s";
}
if ($entidad_filtro !== '') {
    $where[] = "r.entidad_id = ?";
    $params[] = $entidad_filtro;
    $types .= "i";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Consulta principal
$sql = "SELECT r.id, r.folio, r.estado, r.fecha_solicitud, r.descripcion, e.nombre AS entidad
        FROM requisiciones r
        JOIN entidades e ON r.entidad_id = e.id
        $where_sql
        ORDER BY r.id DESC
        LIMIT ? OFFSET ?";

$params[] = $por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Total registros
$count_sql = "SELECT COUNT(*) AS total FROM requisiciones r
              JOIN entidades e ON r.entidad_id = e.id
              $where_sql";
$stmtTotal = $conn->prepare($count_sql);
if ($where) {
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    $stmtTotal->bind_param($count_types, ...$count_params);
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $por_pagina);

// Opciones de entidades para el filtro
$entidadesRes = $conn->query("SELECT id, nombre FROM entidades WHERE activo=1 ORDER BY nombre ASC");
$entidadesOptions = "";
while ($ent = $entidadesRes->fetch_assoc()) {
    $selected = $entidad_filtro == $ent['id'] ? "selected" : "";
    $entidadesOptions .= "<option value='{$ent['id']}' $selected>" . htmlspecialchars($ent['nombre']) . "</option>";
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Requisiciones | GECO Proatam</title>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <span>Requisiciones</span>
            </nav>
            <h1 class="orders-page-title">Requisiciones</h1>
        </div>
        <button class="btn-geco-primary" type="button" onclick="window.location.href='new_requis.php'">
            <i class="fa-solid fa-plus"></i> Agregar
        </button>
    </div>

    <!-- ─── FILTERS + SEARCH ─────────────────────────────────────── -->
    <div class="orders-card mb-4">

        <!-- Hidden search form (preserves AJAX sync) -->
        <form id="search-form" method="GET" style="display:none;">
            <input type="hidden" name="estado" id="hiddenEstadoInput" value="<?= htmlspecialchars($estado_filtro) ?>">
            <input type="hidden" name="entidad" value="<?= htmlspecialchars($entidad_filtro) ?>">
            <input type="search" name="q" value="<?= htmlspecialchars($busqueda) ?>">
        </form>

        <!-- Filter form (Single Row) -->
        <form id="filter-form" method="GET">
            <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">

            <?php
            $tab_labels = [
                ''          => 'Todas',
                'pendiente' => 'Pendientes',
                'aprobado'  => 'Aprobadas',
                'rechazado' => 'Rechazadas'
            ];
            ?>
            <!-- Hidden state select synchronized with tabs -->
            <select name="estado" id="estadoSelect" style="display:none;">
                <?php foreach ($tab_labels as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $estado_filtro === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="orders-filter-bar">
                <!-- Left: Tabs -->
                <div class="orders-filter-tabs" id="estadoTabs">
                    <?php foreach ($tab_labels as $val => $label): ?>
                        <button type="button"
                            class="tab-btn <?= $estado_filtro === $val ? 'active' : '' ?>"
                            data-estado="<?= $val ?>"><?= $label ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Middle: Dropdown selectors -->
                <div class="orders-filter-selects">
                    <select name="entidad" class="form-select">
                        <option value="">Todas las entidades</option>
                        <?= $entidadesOptions ?>
                    </select>
                </div>

                <!-- Right: Search Input -->
                <div class="orders-filter-search">
                    <div class="search-input-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="visibleSearchInput"
                            placeholder="Buscar por folio o entidad..."
                            value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ─── TABLE ────────────────────────────────────────────────── -->
    <div class="orders-card orders-ajax-fade" id="table-container-wrapper">
        <div class="orders-table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Entidad</th>
                        <th>Estado</th>
                        <th>Fecha de Solicitud</th>
                        <th>Descripción</th>
                        <th style="width: 120px; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="cell-folio"><?= htmlspecialchars($row['folio']) ?></td>
                                <td><strong><?= htmlspecialchars($row['entidad']) ?></strong></td>
                                <td>
                                    <?php
                                    $badge_map = [
                                        'pendiente' => ['status-badge--pendiente', 'fa-regular fa-clock', 'Pendiente'],
                                        'espera'    => ['status-badge--pendiente', 'fa-regular fa-clock', 'En Espera'],
                                        'aprobado'  => ['status-badge--aprobado', 'fa-solid fa-circle-check', 'Aprobado'],
                                        'aprobada'  => ['status-badge--aprobado', 'fa-solid fa-circle-check', 'Aprobada'],
                                        'rechazado' => ['status-badge--rechazado', 'fa-solid fa-circle-xmark', 'Rechazado'],
                                        'rechazada' => ['status-badge--rechazado', 'fa-solid fa-circle-xmark', 'Rechazada']
                                    ];
                                    $b = $badge_map[$row['estado']] ?? ['status-badge--pendiente', 'fa-regular fa-circle', ucfirst($row['estado'])];
                                    echo '<span class="status-badge ' . $b[0] . '"><i class="' . $b[1] . '"></i> ' . $b[2] . '</span>';
                                    ?>
                                </td>
                                <td class="cell-date"><?= date('d/m/Y H:i', strtotime($row['fecha_solicitud'])) ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['descripcion']) ?>">
                                    <?= htmlspecialchars($row['descripcion']) ?>
                                </td>
                                <td>
                                    <div class="actions-group">
                                        <!-- SOLO mostrar boton de orden de compra si está APROBADO/APROBADA y el departamento tiene permiso -->
                                        <?php if (($row['estado'] === 'aprobado' || $row['estado'] === 'aprobada') && $puede_crear_oc): ?>
                                            <a href="new_order.php?requisicion_id=<?= $row['id'] ?>" class="btn-action btn-action--edit" style="background-color: #e8f5e9; color: #2e7d32; border-color: #c8e6c9;">
                                                <i class="fa-solid fa-file-circle-plus"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="see_requis.php?id=<?= $row['id'] ?>" class="btn-action btn-action--view">
                                            <i class="fa-regular fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="orders-empty-state">
                                    <i class="fa-solid fa-inbox" style="font-size: 2.5rem; color: var(--gray-400);"></i>
                                    <p>No hay requisiciones registradas</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="orders-pagination-bar mt-3">
            <!-- Left Info -->
            <div class="orders-pagination-left">
                <span class="orders-pagination-info">
                    <?php
                    $inicio_registro = $totalRegistros > 0 ? $offset + 1 : 0;
                    $fin_registro = min($offset + $por_pagina, $totalRegistros);
                    ?>
                    Mostrando <strong><?= $inicio_registro ?>-<?= $fin_registro ?></strong> de <strong><?= $totalRegistros ?></strong> resultados
                </span>
            </div>

            <!-- Controls -->
            <div class="orders-pagination-controls">
                <?php if ($totalPaginas > 1): ?>
                    <nav class="orders-pagination-nav" aria-label="Paginación">
                        <!-- Ir al primero -->
                        <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&page=1"
                            aria-label="Primera página">
                            &laquo;
                        </a>

                        <!-- Anterior -->
                        <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&page=<?= max(1, $pagina - 1) ?>"
                            aria-label="Página anterior">
                            &lsaquo;
                        </a>

                        <!-- Números de página -->
                        <?php
                        $rango_inicio = max(1, $pagina - 2);
                        $rango_fin = min($totalPaginas, $pagina + 2);
                        for ($i = $rango_inicio; $i <= $rango_fin; $i++):
                        ?>
                            <a class="page-btn page-link <?= $i == $pagina ? 'active' : '' ?>"
                                href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Siguiente -->
                        <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&page=<?= min($totalPaginas, $pagina + 1) ?>"
                            aria-label="Página siguiente">
                            &rsaquo;
                        </a>

                        <!-- Ir al último -->
                        <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&page=<?= $totalPaginas ?>"
                            aria-label="Última página">
                            &raquo;
                        </a>
                    </nav>

                    <!-- Divider -->
                    <div class="orders-pagination-divider"></div>

                    <!-- Go to page -->
                    <div class="orders-pagination-goto">
                        <span>Ir a</span>
                        <input type="number"
                            class="goto-page-input"
                            min="1"
                            max="<?= $totalPaginas ?>"
                            value="<?= $pagina ?>"
                            aria-label="Ir a la página">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div> <!-- /table-container-wrapper -->
</div>

<script>
    // Función para actualizar la lista vía AJAX
    function initAJAX() {
        const searchForm = document.getElementById('search-form');
        const filterForm = document.getElementById('filter-form');
        const container = document.getElementById('table-container-wrapper');
        const visibleSearch = document.getElementById('visibleSearchInput');
        const hiddenSearchInput = searchForm ? searchForm.querySelector('input[name="q"]') : null;

        if (!searchForm || !filterForm || !container) return;

        // Sync visible search → hidden form
        if (visibleSearch && hiddenSearchInput) {
            visibleSearch.addEventListener('input', function() {
                hiddenSearchInput.value = this.value;
                const filterQ = filterForm.querySelector('input[name="q"]');
                if (filterQ) filterQ.value = this.value;
            });

            visibleSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filterForm.requestSubmit();
                }
            });
        }

        // Tab click → update hidden select and submit
        document.querySelectorAll('#estadoTabs .tab-btn').forEach(function(tab) {
            tab.addEventListener('click', function() {
                const estadoSelect = document.getElementById('estadoSelect');
                if (estadoSelect) {
                    estadoSelect.value = this.dataset.estado;
                }
                const hiddenEstadoInput = document.getElementById('hiddenEstadoInput');
                if (hiddenEstadoInput) {
                    hiddenEstadoInput.value = this.dataset.estado;
                }
                document.querySelectorAll('#estadoTabs .tab-btn').forEach(function(t) {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                filterForm.requestSubmit();
            });
        });

        // Sync tab active state after AJAX
        function syncTabState() {
            const estadoSelect = document.getElementById('estadoSelect');
            if (estadoSelect) {
                var val = estadoSelect.value;
                document.querySelectorAll('#estadoTabs .tab-btn').forEach(function(t) {
                    t.classList.toggle('active', t.dataset.estado === val);
                });
            }
        }

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
                    if (newFilter) {
                        syncForm(filterForm, newFilter);
                        syncTabState();
                    }

                    // Sync visible search input
                    if (visibleSearch && hiddenSearchInput) {
                        visibleSearch.value = hiddenSearchInput.value;
                    }

                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';

                    if (pushState) window.history.pushState({}, '', url);
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

        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('goto-page-input') && e.key === 'Enter') {
                e.preventDefault();
                let page = parseInt(e.target.value);
                let maxPage = parseInt(e.target.getAttribute('max'));
                if (isNaN(page) || page < 1) {
                    page = 1;
                } else if (page > maxPage) {
                    page = maxPage;
                }

                const params = new URLSearchParams(new FormData(filterForm));
                const searchData = new FormData(searchForm);
                params.set('q', searchData.get('q') || "");
                params.set('page', page);
                updateList('?' + params.toString());
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
<?php include __DIR__ . "/../includes/footer.php"; ?>