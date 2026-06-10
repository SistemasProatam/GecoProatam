<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Obtener datos de sesión
$departamento_id = $_SESSION['departamento_id'] ?? 0;

// Parámetros de búsqueda y paginación
$busqueda = $_GET['q'] ?? '';
$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10; // Mostrar 10 elementos por página
$offset = ($pagina - 1) * $por_pagina;

// Filtros
$estado_filtro = $_GET['estado'] ?? '';
$entidad_filtro = $_GET['entidad'] ?? '';
$obra_filtro = $_GET['obra'] ?? '';

// Construir WHERE dinámico
$where = [];
$params = [];
$types = '';

if ($busqueda !== '') {
    $where[] = "(o.id LIKE ? OR e.nombre LIKE ? OR o.folio LIKE ? OR p.nombre LIKE ?)";
    $like = "%$busqueda%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}
if ($estado_filtro !== '') {
    $where[] = "o.estado = ?";
    $params[] = $estado_filtro;
    $types .= "s";
}
if ($entidad_filtro !== '') {
    $where[] = "o.entidad_id = ?";
    $params[] = $entidad_filtro;
    $types .= "i";
}
if ($obra_filtro !== '') {
    $where[] = "o.obra_id = ?";
    $params[] = $obra_filtro;
    $types .= "i";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Consulta principal - CORREGIDA para incluir proveedor y requisición
$sql = "SELECT o.id, o.folio, o.estado, o.fecha_solicitud, o.descripcion, o.total,
               e.nombre AS entidad,
               p.nombre AS proveedor,
               r.folio AS folio_requisicion
        FROM ordenes_compra o
        JOIN entidades e ON o.entidad_id = e.id
        JOIN proveedores p ON o.proveedor_id = p.id
        LEFT JOIN requisiciones r ON o.requisicion_id = r.id
        $where_sql
        ORDER BY o.id DESC
        LIMIT ? OFFSET ?";

$params[] = $por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Total registros
$count_sql = "SELECT COUNT(*) AS total FROM ordenes_compra o
              JOIN entidades e ON o.entidad_id = e.id
              JOIN proveedores p ON o.proveedor_id = p.id
              LEFT JOIN requisiciones r ON o.requisicion_id = r.id
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

// Opciones de estados para el filtro
$estados_posibles = [
    'pendiente' => 'Pendiente',
    'revisado' => 'Revisado',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado',
    'pagado' => 'Pagado',
    'devuelto' => 'Devuelto para editar'
];
$estadoValues = "";
foreach ($estados_posibles as $key => $label) {
    $selected = $estado_filtro === $key ? "selected" : "";
    $estadoValues .= "<option value='{$key}' $selected>{$label}</option>";
}

// Tab labels para filtros de estado
$tab_labels = [
    '' => 'Todos',
    'pendiente' => 'Pendientes',
    'revisado' => 'Revisados',
    'aprobado' => 'Aprobados',
    'rechazado' => 'Rechazados',
    'devuelto' => 'Devueltos',
    'pagado' => 'Pagados'
];

// Opciones de obras para el filtro
$obrasRes = $conn->query("SELECT id, nombre_obra FROM obras ORDER BY nombre_obra ASC");
$obrasOptions = "";
if ($obrasRes) {
    while ($obra = $obrasRes->fetch_assoc()) {
        $selected = $obra_filtro == $obra['id'] ? "selected" : "";
        $obrasOptions .= "<option value='{$obra['id']}' $selected>" . htmlspecialchars($obra['nombre_obra']) . "</option>";
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Órdenes de Compra | GECO Proatam</title>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <span>Órdenes de Compra</span>
            </nav>
            <h1 class="orders-page-title">Órdenes de Compra</h1>
        </div>
        <a href="new_order.php" class="btn-geco-primary">
            <i class="fa-solid fa-plus"></i> Agregar
        </a>
    </div>

    <!-- ─── FILTERS + SEARCH ─────────────────────────────────────── -->
    <div class="orders-card mb-4">

        <!-- Hidden search form (preserves AJAX sync) -->
        <form id="search-form" method="GET" style="display:none;">
            <input type="hidden" name="estado" value="<?= htmlspecialchars($estado_filtro) ?>">
            <input type="hidden" name="entidad" value="<?= htmlspecialchars($entidad_filtro) ?>">
            <input type="hidden" name="obra" value="<?= htmlspecialchars($obra_filtro) ?>">
            <input type="search" name="q" value="<?= htmlspecialchars($busqueda) ?>">
        </form>

        <!-- Filter form (Single Row) -->
        <form id="filter-form" method="GET">
            <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">

            <!-- Estado select (hidden, synced by tabs) -->
            <select name="estado" id="estadoSelect" style="display:none;">
                <option value="">-- Todos los estados --</option>
                <?= $estadoValues ?>
            </select>

            <!-- Filter bar: tabs + dropdowns + search -->
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
                    <select name="obra" class="form-select">
                        <option value="">Todas las obras</option>
                        <?= $obrasOptions ?>
                    </select>
                </div>

                <!-- Right: Search Input -->
                <div class="orders-filter-search">
                    <div class="search-input-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="visibleSearchInput"
                            placeholder="Buscar folio, proveedor..."
                            value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ─── TABLE ────────────────────────────────────────────────── -->
    <div class="orders-card orders-ajax-fade" id="table-container-wrapper">

        <!-- Success alert -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="orders-alert orders-alert--success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check"></i>
                <span>
                    <strong>¡Éxito!</strong> Orden de compra
                    <?php if (isset($_GET['folio'])): ?>
                        <strong><?= htmlspecialchars($_GET['folio']) ?></strong>
                    <?php endif; ?>
                    creada correctamente.
                </span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="orders-table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Folio OC</th>
                        <th>Entidad</th>
                        <th>Proveedor</th>
                        <th>Requisición</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($oc = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="cell-folio"><?= htmlspecialchars($oc['folio']) ?></td>
                                <td><?= htmlspecialchars($oc['entidad']) ?></td>
                                <td><?= htmlspecialchars($oc['proveedor']) ?></td>
                                <td>
                                    <?php if ($oc['folio_requisicion']): ?>
                                        <span><?= htmlspecialchars($oc['folio_requisicion']) ?></span>
                                    <?php else: ?>
                                        <span class="cell-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge_map = [
                                        'pendiente'  => ['status-badge--pendiente', 'fa-regular fa-clock',              'Pendiente'],
                                        'revisado'   => ['status-badge--revisado',  'fa-regular fa-circle-check',      'Revisado'],
                                        'aprobado'   => ['status-badge--aprobado',  'fa-solid fa-circle-check',       'Aprobado'],
                                        'rechazado'  => ['status-badge--rechazado', 'fa-solid fa-circle-xmark',       'Rechazado'],
                                        'pagado'     => ['status-badge--pagado',    'fa-solid fa-dollar-sign',    'Pagado'],
                                        'devuelto'   => ['status-badge--devuelto',  'fa-solid fa-rotate-left', 'Devuelto'],
                                    ];
                                    $b = $badge_map[$oc['estado']] ?? ['status-badge--revisado', 'fa-regular fa-circle', ucfirst($oc['estado'])];
                                    echo '<span class="status-badge ' . $b[0] . '"><i class="' . $b[1] . '"></i> ' . $b[2] . '</span>';
                                    ?>
                                </td>
                                <td class="cell-date"><?= date('d/m/Y H:i', strtotime($oc['fecha_solicitud'])) ?></td>
                                <td class="cell-description" title="<?= htmlspecialchars($oc['descripcion']) ?>">
                                    <?= htmlspecialchars($oc['descripcion']) ?>
                                </td>
                                <td>
                                    <div class="actions-group">
                                        <?php if ($oc['estado'] == 'devuelto'): ?>
                                            <button class="btn-action btn-action--download"
                                                onclick="descargarPDF(<?= $oc['id'] ?>)"
                                                title="Descargar PDF">
                                                <i class="fa-solid fa-download"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($oc['estado'] == 'pagado'): ?>
                                            <button class="btn-action btn-action--download"
                                                onclick="descargarPDF(<?= $oc['id'] ?>)"
                                                title="Descargar PDF">
                                                <i class="fa-solid fa-download"></i>
                                            </button>
                                        <?php endif; ?>

                                        <button class="btn-action btn-action--edit"
                                            onclick="window.location.href='edit_oc.php?id=<?= $oc['id'] ?>'">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <button class="btn-action btn-action--view"
                                            onclick="window.location.href='see_oc.php?id=<?= $oc['id'] ?>'">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="orders-empty-state">
                                    <i class="fa-solid fa-inbox"></i>
                                    <p>No hay órdenes de compra registradas</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="orders-pagination-bar">
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

            <!-- Center Nav and Right Controls -->
            <div class="orders-pagination-controls">
                <?php if ($totalPaginas > 1): ?>
                    <nav class="orders-pagination-nav" aria-label="Paginación">
                        <?php
                        $max_paginas_mostrar = 10;
                        if ($totalPaginas <= $max_paginas_mostrar) {
                            $rango_inicio = 1;
                            $rango_fin = $totalPaginas;
                        } else {
                            $rango_inicio = $pagina - 5;
                            $rango_fin = $pagina + 4;
                            if ($rango_inicio < 1) {
                                $rango_fin = $max_paginas_mostrar;
                                $rango_inicio = 1;
                            } elseif ($rango_fin > $totalPaginas) {
                                $rango_inicio = $totalPaginas - $max_paginas_mostrar + 1;
                                $rango_fin = $totalPaginas;
                            }
                        }
                        ?>

                        <!-- Ir al primero -->
                        <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&obra=<?= urlencode($obra_filtro) ?>&page=1"
                            aria-label="Primera página">
                            &laquo;
                        </a>

                        <!-- Anterior -->
                        <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&obra=<?= urlencode($obra_filtro) ?>&page=<?= max(1, $pagina - 1) ?>"
                            aria-label="Página anterior">
                            &lsaquo;
                        </a>

                        <!-- Números de página -->
                        <?php for ($i = $rango_inicio; $i <= $rango_fin; $i++): ?>
                            <a class="page-btn page-link <?= $i == $pagina ? 'active' : '' ?>"
                                href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&obra=<?= urlencode($obra_filtro) ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Siguiente -->
                        <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&obra=<?= urlencode($obra_filtro) ?>&page=<?= min($totalPaginas, $pagina + 1) ?>"
                            aria-label="Página siguiente">
                            &rsaquo;
                        </a>

                        <!-- Ir al último -->
                        <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
                            href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&obra=<?= urlencode($obra_filtro) ?>&page=<?= $totalPaginas ?>"
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
    </div>

    <!-- ─── SCRIPTS ──────────────────────────────────────────────── -->
    <script>
        // Inicializar tooltips de Bootstrap
        function initTooltips() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    container: 'body'
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initTooltips();

            const searchForm = document.getElementById('search-form');
            const filterForm = document.getElementById('filter-form');
            const container = document.getElementById('table-container-wrapper');
            const visibleSearch = document.getElementById('visibleSearchInput');
            const hiddenSearchInput = searchForm.querySelector('input[name="q"]');

            // Sync visible search → hidden form
            visibleSearch.addEventListener('input', function() {
                hiddenSearchInput.value = this.value;
                filterForm.querySelector('input[name="q"]').value = this.value;
            });

            // Submit on Enter in visible search
            visibleSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filterForm.requestSubmit();
                }
            });

            // Tab click → update hidden select and submit
            document.querySelectorAll('#estadoTabs .tab-btn').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    document.getElementById('estadoSelect').value = this.dataset.estado;
                    document.querySelectorAll('#estadoTabs .tab-btn').forEach(function(t) {
                        t.classList.remove('active');
                    });
                    this.classList.add('active');
                    filterForm.requestSubmit();
                });
            });

            // Sync tab active state after AJAX
            function syncTabState() {
                var val = document.getElementById('estadoSelect').value;
                document.querySelectorAll('#estadoTabs .tab-btn').forEach(function(t) {
                    t.classList.toggle('active', t.dataset.estado === val);
                });
            }

            // Función para actualizar la lista vía AJAX
            function updateList(url, pushState) {
                if (pushState === undefined) pushState = true;
                container.style.opacity = '0.5';
                container.style.pointerEvents = 'none';

                fetch(url)
                    .then(function(response) {
                        return response.text();
                    })
                    .then(function(html) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, 'text/html');

                        // Actualizar contenido de la tabla y paginación
                        var newContent = doc.getElementById('table-container-wrapper');
                        if (newContent) {
                            container.innerHTML = newContent.innerHTML;
                        }

                        // Sincronizar los valores de los formularios
                        var newSearch = doc.getElementById('search-form');
                        var newFilter = doc.getElementById('filter-form');

                        if (newSearch) syncForm(searchForm, newSearch);
                        if (newFilter) syncForm(filterForm, newFilter);

                        // Sync visible search input
                        visibleSearch.value = hiddenSearchInput.value;

                        // Sync tabs
                        syncTabState();

                        container.style.opacity = '1';
                        container.style.pointerEvents = 'auto';

                        if (pushState) {
                            window.history.pushState({}, '', url);
                        }

                        initTooltips();
                    })
                    .catch(function(err) {
                        console.error('Error al actualizar la lista:', err);
                        container.style.opacity = '1';
                        container.style.pointerEvents = 'auto';
                    });
            }

            // Sincroniza los valores entre formularios para mantener consistencia
            function syncForm(current, source) {
                source.querySelectorAll('input, select').forEach(function(input) {
                    var target = current.querySelector('[name="' + input.name + '"]');
                    if (target) {
                        if (target.type === 'hidden' || target.tagName === 'SELECT' || target.type === 'search') {
                            target.value = input.value;
                        }
                    }
                });
            }

            // Delegación de eventos para clics en la paginación
            document.addEventListener('click', function(e) {
                var pageLink = e.target.closest('.page-link');
                if (pageLink) {
                    e.preventDefault();
                    updateList(pageLink.href);
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });

            // Navegación con input "Ir a" (Go To Page)
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('goto-page-input')) {
                    handleGoToPage(e.target);
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.target.classList.contains('goto-page-input') && e.key === 'Enter') {
                    e.preventDefault();
                    handleGoToPage(e.target);
                }
            });

            function handleGoToPage(input) {
                var page = parseInt(input.value);
                var maxPage = parseInt(input.getAttribute('max'));
                if (isNaN(page) || page < 1) {
                    page = 1;
                } else if (page > maxPage) {
                    page = maxPage;
                }

                var filterForm = document.getElementById('filter-form');
                var searchForm = document.getElementById('search-form');
                if (filterForm && searchForm) {
                    var params = new URLSearchParams(new FormData(filterForm));
                    var searchData = new FormData(searchForm);
                    params.set('q', searchData.get('q') || "");
                    params.set('page', page);
                    updateList('?' + params.toString());
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            }

            // Manejo unificado de envíos de formulario
            [searchForm, filterForm].forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    var params = new URLSearchParams(new FormData(filterForm));
                    var searchData = new FormData(searchForm);
                    params.set('q', searchData.get('q') || "");

                    params.set('page', '1'); // Resetear a página 1 al filtrar/buscar
                    updateList('?' + params.toString());
                });
            });

            // Auto-envío al cambiar filtros de selección
            filterForm.querySelectorAll('select').forEach(function(select) {
                select.addEventListener('change', function() {
                    filterForm.requestSubmit();
                });
            });
        });
    </script>

    <script>
        // Función para descargar PDF
        function descargarPDF(ocId) {
            window.open('download_pdf_oc.php?id=' + ocId, '_blank');
        }
    </script>

</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>