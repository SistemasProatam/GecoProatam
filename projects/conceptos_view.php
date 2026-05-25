<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

$catalogo_id = (int)($_GET['catalogo_id'] ?? 0);
$obra_id     = (int)($_GET['obra_id']     ?? 0);

if ($catalogo_id <= 0) { header("Location: list_obras.php"); exit; }

// ── Catálogo ─────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM catalogos WHERE id = ?");
$stmt->bind_param("i", $catalogo_id);
$stmt->execute();
$catalogo = $stmt->get_result()->fetch_assoc();
if (!$catalogo) { header("Location: list_obras.php"); exit; }

// ── Obra ─────────────────────────────────────────────────────────
$obra_info = null;
if ($obra_id > 0) {
    $so = $conn->prepare("SELECT * FROM obras WHERE id = ?");
    $so->bind_param("i", $obra_id);
    $so->execute();
    $obra_info = $so->get_result()->fetch_assoc();
}

// ── Verificar tablas opcionales ───────────────────────────────────
$tiene_ordenes = $conn->query("SHOW TABLES LIKE 'orden_compra_items'")->num_rows > 0
              && $conn->query("SHOW TABLES LIKE 'ordenes_compra'")->num_rows > 0;

// ── Estadísticas ──────────────────────────────────────────────────
if ($tiene_ordenes) {
    $sq = "SELECT
               COUNT(c.id)                        AS total_conceptos,
               COUNT(DISTINCT n.nivel)            AS niveles_usados,
               COUNT(DISTINCT n.id)               AS total_nodos,
               COALESCE(SUM(
                   (SELECT COALESCE(SUM(oci.subtotal), 0)
                    FROM orden_compra_items oci
                    JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                    WHERE oci.concepto_id = c.id AND oc.estado = 'pagado')
               ), 0)                              AS monto_total_general
           FROM conceptos c
           LEFT JOIN concepto_nodos n ON c.nodo_id = n.id
           WHERE c.catalogo_id = ?";
} else {
    $sq = "SELECT COUNT(*) AS total_conceptos, 0 AS niveles_usados,
                  0 AS total_nodos, 0 AS monto_total_general
           FROM conceptos WHERE catalogo_id = ?";
}
$ss = $conn->prepare($sq);
$ss->bind_param("i", $catalogo_id);
$ss->execute();
$stats = $ss->get_result()->fetch_assoc();

// ── Obtener árbol de nodos ────────────────────────────────────────
$sn = $conn->prepare(
    "SELECT id, parent_id, clave, titulo, nivel, sort_path
     FROM concepto_nodos
     WHERE catalogo_id = ?
     ORDER BY sort_path ASC"
);
$sn->bind_param("i", $catalogo_id);
$sn->execute();
$res_nodos    = $sn->get_result();
$nodos_por_id = [];
while ($n = $res_nodos->fetch_assoc()) {
    $nodos_por_id[(int)$n['id']] = $n + ['hijos' => [], 'conceptos' => []];
}

// ── Obtener conceptos ─────────────────────────────────────────────
if ($tiene_ordenes) {
    $sq_c = "SELECT c.*, n.sort_path AS nodo_sort_path,
                    (SELECT COUNT(*) FROM orden_compra_items oci
                      JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                      WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') AS total_items,
                    (SELECT COALESCE(SUM(oci.subtotal), 0) FROM orden_compra_items oci
                      JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                      WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') AS monto_total
             FROM conceptos c
             LEFT JOIN concepto_nodos n ON c.nodo_id = n.id
             WHERE c.catalogo_id = ?
             ORDER BY COALESCE(n.sort_path, '9999') ASC,
                      CAST(NULLIF(c.numero_original, '') AS UNSIGNED) ASC";
} else {
    $sq_c = "SELECT c.*, n.sort_path AS nodo_sort_path, 0 AS total_items, 0 AS monto_total
             FROM conceptos c
             LEFT JOIN concepto_nodos n ON c.nodo_id = n.id
             WHERE c.catalogo_id = ?
             ORDER BY COALESCE(n.sort_path, '9999') ASC,
                      CAST(NULLIF(c.numero_original, '') AS UNSIGNED) ASC";
}
$stmt_c = $conn->prepare($sq_c);
$stmt_c->bind_param("i", $catalogo_id);
$stmt_c->execute();
$res_c = $stmt_c->get_result();

$conceptos_sin_nodo = [];
while ($c = $res_c->fetch_assoc()) {
    $nid = $c['nodo_id'] ? (int)$c['nodo_id'] : null;
    if ($nid && isset($nodos_por_id[$nid])) {
        $nodos_por_id[$nid]['conceptos'][] = $c;
    } else {
        $conceptos_sin_nodo[] = $c;
    }
}

// ── Construir árbol padre → hijos ─────────────────────────────────
$raices = [];
foreach ($nodos_por_id as $id => &$nodo) {
    $pid = $nodo['parent_id'] ? (int)$nodo['parent_id'] : null;
    if ($pid && isset($nodos_por_id[$pid])) {
        $nodos_por_id[$pid]['hijos'][] = &$nodo;
    } else {
        $raices[] = &$nodo;
    }
}
unset($nodo);

// ================================================================
// HELPERS DE RENDER
// ================================================================

function nivelEstilo(int $nivel): array {
    $paleta = [
        1 => ['bg' => 'var(--s-700, #113557)', 'color' => '#ffffff', 'border' => 'var(--s-900, #020617)', 'icon' => 'bi-folder-fill',      'fw' => 700, 'fs' => '1rem'],
        2 => ['bg' => 'var(--s-50, #f1f5f9)', 'color' => 'var(--s-800, #0f172a)', 'border' => 'var(--s-300, #94a3b8)', 'icon' => 'bi-folder2-open',     'fw' => 600, 'fs' => '0.9rem'],
        3 => ['bg' => 'var(--p-50, #f0f7f2)', 'color' => 'var(--p-800, #233e30)', 'border' => 'var(--p-300, #8ecc9f)', 'icon' => 'bi-chevron-right',    'fw' => 600, 'fs' => '0.85rem'],
        4 => ['bg' => '#fffbeb', 'color' => '#78350f', 'border' => '#fde68a', 'icon' => 'bi-chevron-right',    'fw' => 500, 'fs' => '0.82rem'],
        5 => ['bg' => '#fdf2f8', 'color' => '#701a75', 'border' => '#fbcfe8', 'icon' => 'bi-chevron-right',    'fw' => 500, 'fs' => '0.80rem'],
        6 => ['bg' => '#faf5ff', 'color' => '#4a044e', 'border' => '#e9d5ff', 'icon' => 'bi-chevron-right',    'fw' => 500, 'fs' => '0.78rem'],
    ];
    $idx = (($nivel - 1) % count($paleta)) + 1;
    return $paleta[$idx];
}

function totalItemsNodo(array &$nodo): int {
    $t = array_sum(array_column($nodo['conceptos'], 'total_items'));
    foreach ($nodo['hijos'] as &$h) $t += totalItemsNodo($h);
    return $t;
}

function totalMontoNodo(array &$nodo): float {
    $t = (float)array_sum(array_column($nodo['conceptos'], 'monto_total'));
    foreach ($nodo['hijos'] as &$h) $t += totalMontoNodo($h);
    return $t;
}

function renderNodo(array &$nodo): void {
    global $catalogo_id, $catalogo, $obra_id, $obra_info;
    $nivel  = (int)$nodo['nivel'];
    $est    = nivelEstilo($nivel);
    $indent = ($nivel - 1) * 18;
    $t_items = totalItemsNodo($nodo);
    $t_monto = totalMontoNodo($nodo);
    ?>
    <div class="nodo-bloque" style="margin-left:<?= $indent ?>px;">
        <div class="nodo-header d-flex align-items-center justify-content-between px-3 py-2 mb-2"
            style="background:<?= $est['bg'] ?>; border-left:4px solid <?= $est['border'] ?>; color:<?= $est['color'] ?>;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi <?= $est['icon'] ?>"></i>
                <span style="font-size:<?= $est['fs'] ?>;font-weight:<?= $est['fw'] ?>;">
                    <?= htmlspecialchars($nodo['clave']) ?>
                    <?php if (!empty($nodo['titulo']) && $nodo['titulo'] !== $nodo['clave']): ?>
                        <span style="font-weight:400;opacity:.85;"> &mdash; <?= htmlspecialchars($nodo['titulo']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($t_items > 0 || $t_monto > 0): ?>
                <div class="d-flex gap-2">
                    <?php if ($t_items > 0): ?>
                        <span class="status-badge" style="color: var(--s-600); background: rgba(23, 162, 184, 0.08); border-color: rgba(23, 162, 184, 0.2); font-size:.7rem;"><?= $t_items ?> items</span>
                    <?php endif; ?>
                    <span class="status-badge" style="color: var(--p-700); background: rgba(64, 118, 86, 0.08); border-color: rgba(64, 118, 86, 0.2); font-size:.7rem;">$<?= number_format($t_monto, 2) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php foreach ($nodo['conceptos'] as $concepto): renderConcepto($concepto, $indent + 18); endforeach; ?>
        <?php foreach ($nodo['hijos'] as &$hijo): renderNodo($hijo); endforeach; ?>
    </div>
    <?php
}

function renderConcepto(array $c, int $indent_px): void {
    global $catalogo_id, $catalogo, $obra_id, $obra_info;
    ?>
    <div class="concepto-item card mb-2 border-0 shadow-sm" style="margin-left:<?= $indent_px ?>px;" data-concepto-id="<?= $c['id'] ?>" data-tiene-items="<?= $c['total_items'] > 0 ? 'si' : 'no' ?>">
        <div class="card-body py-2 px-3 <?= $c['total_items'] > 0 ? 'item-orden-pagada' : '' ?>">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <span class="status-badge" style="color: var(--p-700); background: rgba(64,118,86,0.06); border: 1px solid rgba(64,118,86,0.15); font-size:.75rem; font-weight:600;"><?= htmlspecialchars($c['codigo_concepto']) ?></span>
                        <?php if ($c['numero_original']): ?>
                            <small class="text-muted">#<?= htmlspecialchars($c['numero_original']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="fw-semibold text-dark" style="font-size:.88rem;line-height:1.3;"><?= htmlspecialchars($c['nombre_concepto']) ?></div>
                    <div class="d-flex flex-wrap gap-3 mt-1" style="font-size:.78rem;color:#6c757d;">
                        <?php if ($c['unidad_medida']): ?><span><i class="bi bi-rulers me-1"></i><?= htmlspecialchars($c['unidad_medida']) ?></span><?php endif; ?>
                        <?php if (!empty($c['cantidad'])): ?><span>Cant: <strong class="text-dark"><?= number_format($c['cantidad'], 3) ?></strong></span><?php endif; ?>
                        <?php if (!empty($c['precio_unitario'])): ?><span>P.U.: <strong class="text-dark">$<?= number_format($c['precio_unitario'], 2) ?></strong></span><?php endif; ?>
                        <?php if (!empty($c['importe'])): ?><span>Importe: <strong class="text-dark">$<?= number_format($c['importe'], 2) ?></strong></span><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-end align-items-center gap-3">
                        <div class="text-end">
                            <?php if ($c['total_items'] > 0): ?>
                                <span class="status-badge status-badge--aprobado mb-1" style="font-size:0.7rem;"><?= $c['total_items'] ?> items</span><br>
                                <span class="fw-bold text-success" style="font-size:.9rem;">$<?= number_format($c['monto_total'], 2) ?></span>
                            <?php else: ?>
                                <span class="status-badge status-badge--pendiente mb-1" style="font-size:0.7rem;">Sin items</span><br>
                                <span class="text-muted" style="font-size:.85rem;">$0.00</span>
                            <?php endif; ?>
                        </div>
                        <div class="actions-group">
                            <button class="btn-action btn-action--edit" onclick="abrirModalEditar(<?= $c['id'] ?>)" title="Editar"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn-action btn-action--view" onclick="verDetalleConceptoView(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['codigo_concepto'])) ?>')" title="Detalle"><i class="bi bi-info-circle"></i></button>
                            <button class="btn-action btn-action--delete" onclick="eliminarConceptoView(<?= $c['id'] ?>)" title="Eliminar"><i class="bi bi-trash3"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">

<style>
    .item-orden-pagada {
        border-left: 4px solid var(--p-500, #407656) !important;
        background: rgba(64, 118, 86, 0.02) !important;
    }
    
    .concepto-item {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1.5px solid var(--gray-100, #f3f4f6) !important;
        border-radius: var(--radius-md, 10px);
        background: #fff;
    }
    .concepto-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0,0,0,0.05)) !important;
        border-color: var(--p-200, #bce1c7) !important;
    }
    
    .nodo-header {
        border-radius: var(--radius-md, 10px) !important;
        box-shadow: var(--shadow-sm, 0 1px 2px 0 rgba(0,0,0,0.02));
        font-family: var(--font-heading, 'Outfit', sans-serif);
        transition: all 0.2s ease;
    }
    .nodo-header:hover {
        opacity: 0.95;
    }
    
    /* KPI Stats Grid */
    .budget-kpi-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }
    .kpi-card {
        background: #fff;
        border-radius: var(--radius-lg, 16px);
        padding: 1.5rem;
        border: 1.5px solid var(--gray-100, #f3f4f6);
        box-shadow: var(--shadow-sm, 0 1px 2px 0 rgba(0,0,0,0.03));
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        position: relative;
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md, 0 8px 20px -3px rgba(0,0,0,0.06));
        border-color: var(--card-hover-border) !important;
    }
    
    .kpi-label {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--gray-500, #6b7280);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .kpi-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--s-800, #0f172a);
        font-family: var(--font-heading, 'Outfit', sans-serif);
        line-height: 1.2;
    }
    .kpi-subtitle {
        font-size: 0.75rem;
        color: var(--gray-500, #6b7280);
    }
    
    .kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: var(--radius-md, 10px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        transition: all 0.2s ease;
    }

    /* Soft Pastel theme variations (no left borders, full color cards) */
    .kpi-card--conceptos {
        background: rgba(64, 118, 86, 0.04);
        border-color: rgba(64, 118, 86, 0.12);
        --card-hover-border: rgba(64, 118, 86, 0.3);
    }
    .kpi-card--conceptos .kpi-value, .kpi-card--conceptos .kpi-icon { color: var(--p-500, #407656) !important; }
    .kpi-card--conceptos .kpi-icon { background: rgba(64, 118, 86, 0.08); }

    .kpi-card--nodos {
        background: rgba(17, 53, 87, 0.04);
        border-color: rgba(17, 53, 87, 0.12);
        --card-hover-border: rgba(17, 53, 87, 0.3);
    }
    .kpi-card--nodos .kpi-value, .kpi-card--nodos .kpi-icon { color: var(--s-700, #113557) !important; }
    .kpi-card--nodos .kpi-icon { background: rgba(17, 53, 87, 0.08); }

    .kpi-card--niveles {
        background: rgba(245, 158, 11, 0.04);
        border-color: rgba(245, 158, 11, 0.12);
        --card-hover-border: rgba(245, 158, 11, 0.3);
    }
    .kpi-card--niveles .kpi-value, .kpi-card--niveles .kpi-icon { color: #d97706 !important; }
    .kpi-card--niveles .kpi-icon { background: rgba(245, 158, 11, 0.08); }

    .kpi-card--monto {
        background: rgba(34, 197, 94, 0.04);
        border-color: rgba(34, 197, 94, 0.12);
        --card-hover-border: rgba(34, 197, 94, 0.3);
    }
    .kpi-card--monto .kpi-value, .kpi-card--monto .kpi-icon { color: #16a34a !important; }
    .kpi-card--monto .kpi-icon { background: rgba(34, 197, 94, 0.08); }

    /* Custom Action Delete */
    .btn-action--delete:hover {
        border-color: rgba(239, 68, 68, 0.3) !important;
        color: #ef4444 !important;
        background: rgba(239, 68, 68, 0.04) !important;
    }
</style>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- Page Header -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <a href="list_project.php">Proyectos</a>
                <span class="separator">›</span>
                <a href="list_obras.php">Obras</a>
                <?php if ($obra_info): ?>
                    <span class="separator">›</span>
                    <a href="details_obra.php?id=<?= $obra_id ?>"><?= htmlspecialchars($obra_info['nombre_obra']) ?></a>
                <?php endif; ?>
                <span class="separator">›</span>
                <span><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></span>
            </nav>
            <h1 class="orders-page-title"><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></h1>
        </div>
        <div class="d-flex gap-2">
            <a href="details_obra.php?id=<?= $obra_id ?>" class="btn-geco-outline">
                <i class="bi bi-arrow-left"></i> Volver a la Obra
            </a>
            <button class="btn-geco-outline" onclick="editarCatalogoView()">
                <i class="bi bi-pencil-square"></i> Editar Catálogo
            </button>
        </div>
    </div>

    <!-- KPI Dashboard -->
    <div class="budget-kpi-container">
        <div class="kpi-card kpi-card--conceptos">
            <div class="d-flex justify-content-between align-items-start">
                <span class="kpi-label">Conceptos</span>
                <div class="kpi-icon kpi-icon--conceptos"><i class="bi bi-tag-fill"></i></div>
            </div>
            <span class="kpi-value"><?= $stats['total_conceptos'] ?></span>
            <span class="kpi-subtitle">Partidas registradas</span>
        </div>
        <div class="kpi-card kpi-card--nodos">
            <div class="d-flex justify-content-between align-items-start">
                <span class="kpi-label">Nodos / Capas</span>
                <div class="kpi-icon kpi-icon--nodos"><i class="bi bi-diagram-3-fill"></i></div>
            </div>
            <span class="kpi-value"><?= $stats['total_nodos'] ?></span>
            <span class="kpi-subtitle">Niveles en la estructura</span>
        </div>
        <div class="kpi-card kpi-card--niveles">
            <div class="d-flex justify-content-between align-items-start">
                <span class="kpi-label">Profundidad</span>
                <div class="kpi-icon kpi-icon--niveles"><i class="bi bi-layers-fill"></i></div>
            </div>
            <span class="kpi-value"><?= $stats['niveles_usados'] ?></span>
            <span class="kpi-subtitle">Nivel máximo alcanzado</span>
        </div>
        <div class="kpi-card kpi-card--monto">
            <div class="d-flex justify-content-between align-items-start">
                <span class="kpi-label">Monto Contratado</span>
                <div class="kpi-icon kpi-icon--monto"><i class="bi bi-cash-stack"></i></div>
            </div>
            <span class="kpi-value text-success">$<?= number_format($stats['monto_total_general'], 2) ?></span>
            <span class="kpi-subtitle">Acumulado en conceptos</span>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="orders-card">
        <!-- Filter Bar -->
        <div class="orders-filter-bar border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-ul me-2 text-primary"></i>Conceptos</h5>
                <select id="filtroItems" class="form-select form-select-sm" style="width:auto;" onchange="toggleFilter(this.value)">
                    <option value="todos">Todos los conceptos</option>
                    <option value="conItems">Con órdenes de compra</option>
                    <option value="sinItems">Sin órdenes de compra</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-geco-primary btn-sm" onclick="mostrarFormConcepto()"><i class="bi bi-plus-circle me-1"></i>Nuevo Concepto</button>
                <button class="btn-geco-secondary btn-sm" onclick="importarExcelConceptos()"><i class="bi bi-upload me-1"></i>Importar Excel</button>
            </div>
        </div>

        <!-- Tree Area -->
        <div class="p-4">
            <?php foreach ($raices as &$raiz): renderNodo($raiz); endforeach; ?>
            <?php if (!empty($conceptos_sin_nodo)): ?>
                <div class="mt-4">
                    <div class="nodo-header px-3 py-2 bg-light mb-2 rounded border-start border-4 border-secondary" style="border-radius: 8px !important;">
                        <strong class="text-secondary">Sin Categoría / Nodo Suelto</strong>
                    </div>
                    <?php foreach ($conceptos_sin_nodo as $c): renderConcepto($c, 18); endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (empty($raices) && empty($conceptos_sin_nodo)): ?>
                <div class="orders-empty-state">
                    <i class="bi bi-folder2-open" style="font-size: 3rem;"></i>
                    <p class="mt-2">No hay conceptos registrados en este catálogo.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="<?= BASE_URL ?>/assets/scripts/catalogo-obras.js"></script>
<script>
const catalogoId = <?= $catalogo_id ?>;
const catalogoNombre = '<?= addslashes($catalogo['nombre_catalogo']) ?>';
const obraId = <?= $obra_id ?: 'null' ?>;
const obraNombre = '<?= $obra_info ? addslashes($obra_info['nombre_obra']) : '' ?>';
const API = 'catalogos_manager.php';

function toggleFilter(tipo) {
    document.querySelectorAll('.concepto-item').forEach(el => {
        const ti = el.dataset.tieneItems === 'si';
        el.style.display = (tipo === 'conItems' && !ti) || (tipo === 'sinItems' && ti) ? 'none' : '';
    });
}

function mostrarFormConcepto() { mostrarFormularioConcepto(catalogoId, catalogoNombre, obraId, obraNombre); }
function importarExcelConceptos() { mostrarImportarExcelConceptos(catalogoId, catalogoNombre, obraId, obraNombre); }
function eliminarConceptoView(cid) { eliminarConcepto(cid); }
function editarCatalogoView() { editarCatalogo(catalogoId, catalogoNombre, '<?= addslashes($catalogo['descripcion']) ?>'); }
function verDetalleConceptoView(cid, codigo) { verDetalleConcepto(cid, codigo, catalogoId, catalogoNombre); }

function abrirModalEditar(id) {
    UI.loading("Cargando...");
    const fd = new FormData(); fd.append('action', 'obtener_detalle_concepto'); fd.append('concepto_id', id);
    fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
        UI.loading.hide();
        if (!data.success) { UI.toast.error(data.error); return; }
        const c = data.concepto;
        UI.modal({
            title: "Editar Concepto — " + c.codigo_concepto,
            size: "lg",
            html: `
                <div class="orders-page-container p-1">
                    <form id="formEditConc" class="m-0">
                        <input type="hidden" name="concepto_id" value="${c.id}">
                        
                        <div class="oc-form-subsection mt-0 pt-0 border-0">
                            <div class="oc-form-subsection__title">
                                <i class="bi bi-info-circle"></i> Identificación
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Código *</label>
                                    <input type="text" name="codigo_concepto" class="form-control" value="${c.codigo_concepto}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Núm.</label>
                                    <input type="text" name="numero_original" class="form-control" value="${c.numero_original || ''}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Nombre *</label>
                                    <input type="text" name="nombre_concepto" class="form-control" value="${c.nombre_concepto}" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label small fw-bold">Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="3" placeholder="Descripción detallada del concepto...">${c.descripcion || ''}</textarea>
                            </div>
                        </div>

                        <div class="oc-form-subsection">
                            <div class="oc-form-subsection__title">
                                <i class="bi bi-diagram-3"></i> Jerarquía
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Clave del nodo</label>
                                    <input type="text" name="nodo_clave" class="form-control font-monospace" value="${c.nodo_clave || ''}" placeholder="Ej: I.1.1">
                                    <small class="text-muted d-block mt-1" style="font-size: 11px;">Define la ubicación del concepto en el árbol de categorías.</small>
                                </div>
                            </div>
                        </div>

                        <div class="oc-form-subsection">
                            <div class="oc-form-subsection__title">
                                <i class="bi bi-cash-coin"></i> Costos
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Unidad</label>
                                    <input type="text" name="unidad_medida" class="form-control" value="${c.unidad_medida || ''}" placeholder="Ej: m, pza">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Cantidad</label>
                                    <input type="number" step="0.001" name="cantidad" class="form-control" value="${c.cantidad || ''}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Precio Unitario</label>
                                    <input type="number" step="0.01" name="precio_unitario" class="form-control" value="${c.precio_unitario || ''}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Importe</label>
                                    <input type="number" step="0.01" name="importe" class="form-control" value="${c.importe || ''}">
                                </div>
                            </div>
                        </div>

                        <div class="oc-form-subsection">
                            <div class="oc-form-subsection__title">
                                <i class="bi bi-calendar-event"></i> Periodo
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Inicio</label>
                                    <input type="date" name="fecha_inicio" class="form-control" value="${c.fecha_inicio || ''}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Fin</label>
                                    <input type="date" name="fecha_fin" class="form-control" value="${c.fecha_fin || ''}">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-secondary px-3" onclick="UI.modal.close()">Cancelar</button>
                            <button type="submit" class="btn-geco-primary"><i class="bi bi-floppy me-1"></i>Guardar Cambios</button>
                        </div>
                    </form>
                </div>`
        });
        document.getElementById('formEditConc').addEventListener('submit', function(e) {
            e.preventDefault();
            UI.loading("Guardando...");
            const fd2 = new FormData(this); fd2.append('action', 'actualizar_concepto');
            fetch(API, { method: 'POST', body: fd2 }).then(r => r.json()).then(res => {
                UI.loading.hide();
                if (res.success) { UI.modal.close(); UI.toast.success("Concepto actualizado correctamente"); setTimeout(() => location.reload(), 1500); }
                else UI.toast.error(res.error);
            }).catch(() => { UI.loading.hide(); UI.toast.error("Error de conexión"); });
        });
    });
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
