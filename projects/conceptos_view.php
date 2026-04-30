<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

$catalogo_id = (int)($_GET['catalogo_id'] ?? 0);
$obra_id     = (int)($_GET['obra_id']     ?? 0);

if ($catalogo_id <= 0) { header("Location: list_obras.php"); exit; }

// â”€â”€ CatÃ¡logo â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$stmt = $conn->prepare("SELECT * FROM catalogos WHERE id = ?");
$stmt->bind_param("i", $catalogo_id);
$stmt->execute();
$catalogo = $stmt->get_result()->fetch_assoc();
if (!$catalogo) { header("Location: list_obras.php"); exit; }

// â”€â”€ Obra â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$obra_info = null;
if ($obra_id > 0) {
    $so = $conn->prepare("SELECT * FROM obras WHERE id = ?");
    $so->bind_param("i", $obra_id);
    $so->execute();
    $obra_info = $so->get_result()->fetch_assoc();
}

// â”€â”€ Verificar tablas opcionales â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tiene_ordenes = $conn->query("SHOW TABLES LIKE 'orden_compra_items'")->num_rows > 0
              && $conn->query("SHOW TABLES LIKE 'ordenes_compra'")->num_rows > 0;

// â”€â”€ EstadÃ­sticas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Obtener Ã¡rbol de nodos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Obtener conceptos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Construir Ã¡rbol padre â†’ hijos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

/** Paleta visual por nivel â€” se cicla si hay mÃ¡s de 6 niveles */
function nivelEstilo(int $nivel): array {
    $paleta = [
        1 => ['bg' => '#1a3a5c', 'color' => '#ffffff', 'border' => '#1a3a5c', 'icon' => 'bi-folder-fill',      'fw' => 700, 'fs' => '1rem'],
        2 => ['bg' => '#e8f4fd', 'color' => '#155f7a', 'border' => '#17a2b8', 'icon' => 'bi-folder2-open',     'fw' => 600, 'fs' => '0.9rem'],
        3 => ['bg' => '#f0f9f0', 'color' => '#3d6b35', 'border' => '#5a9e50', 'icon' => 'bi-chevron-right',    'fw' => 600, 'fs' => '0.85rem'],
        4 => ['bg' => '#fff8e8', 'color' => '#7a5a00', 'border' => '#ffc107', 'icon' => 'bi-chevron-right',    'fw' => 500, 'fs' => '0.82rem'],
        5 => ['bg' => '#fdf0f8', 'color' => '#7a1a5c', 'border' => '#e91e8c', 'icon' => 'bi-chevron-right',    'fw' => 500, 'fs' => '0.80rem'],
        6 => ['bg' => '#f5f0ff', 'color' => '#4a1a7a', 'border' => '#9c27b0', 'icon' => 'bi-chevron-right',    'fw' => 500, 'fs' => '0.78rem'],
    ];
    $idx = (($nivel - 1) % count($paleta)) + 1;
    return $paleta[$idx];
}

/** Suma recursiva de items de un nodo */
function totalItemsNodo(array &$nodo): int {
    $t = array_sum(array_column($nodo['conceptos'], 'total_items'));
    foreach ($nodo['hijos'] as &$h) $t += totalItemsNodo($h);
    return $t;
}

/** Suma recursiva de monto de un nodo */
function totalMontoNodo(array &$nodo): float {
    $t = (float)array_sum(array_column($nodo['conceptos'], 'monto_total'));
    foreach ($nodo['hijos'] as &$h) $t += totalMontoNodo($h);
    return $t;
}

/**
 * Renderiza un nodo y todo su subÃ¡rbol de forma recursiva.
 * Funciona para cualquier profundidad sin cambiar cÃ³digo.
 */
function renderNodo(array &$nodo): void {
    global $catalogo_id, $catalogo, $obra_id, $obra_info;

    $nivel  = (int)$nodo['nivel'];
    $est    = nivelEstilo($nivel);
    $indent = ($nivel - 1) * 18; // px de sangrÃ­a por nivel
    $t_items = totalItemsNodo($nodo);
    $t_monto = totalMontoNodo($nodo);
    ?>
    <div class="nodo-bloque" style="margin-left:<?= $indent ?>px;">

        <div class="nodo-header d-flex align-items-center justify-content-between px-3 py-2 mb-1"
             style="background:<?= $est['bg'] ?>;
                    border-left:4px solid <?= $est['border'] ?>;
                    border-radius:0 6px 6px 0;
                    color:<?= $est['color'] ?>;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi <?= $est['icon'] ?>"></i>
                <span style="font-size:<?= $est['fs'] ?>;font-weight:<?= $est['fw'] ?>;">
                    <?= htmlspecialchars($nodo['clave']) ?>
                    <?php
if (!empty($nodo['titulo']) && $nodo['titulo'] !== $nodo['clave']): ?>
                        <span style="font-weight:400;opacity:.85;"> &mdash; <?= htmlspecialchars($nodo['titulo']) ?></span>
                    <?php
endif; ?>
                </span>
            </div>
            <?php
if ($t_items > 0 || $t_monto > 0): ?>
                <div class="d-flex gap-2">
                    <?php
if ($t_items > 0): ?>
                        <span class="badge bg-info" style="font-size:.7rem;"><?= $t_items ?> items</span>
                    <?php
endif; ?>
                    <span class="badge bg-success" style="font-size:.7rem;">$<?= number_format($t_monto, 2) ?></span>
                </div>
            <?php
endif; ?>
        </div>

        <?php
foreach ($nodo['conceptos'] as $concepto): ?>
            <?php
renderConcepto($concepto, $indent + 18); ?>
        <?php
endforeach; ?>

        <?php
foreach ($nodo['hijos'] as &$hijo): ?>
            <?php
renderNodo($hijo); ?>
        <?php
endforeach; ?>

    </div>
    <?php
}

function renderConcepto(array $c, int $indent_px): void {
    global $catalogo_id, $catalogo, $obra_id, $obra_info;
    ?>
    <div class="concepto-item card mb-2 border-0 shadow-sm"
         style="margin-left:<?= $indent_px ?>px;"
         data-concepto-id="<?= $c['id'] ?>"
         data-tiene-items="<?= $c['total_items'] > 0 ? 'si' : 'no' ?>">
        <div class="card-body py-2 px-3 <?= $c['total_items'] > 0 ? 'item-orden-pagada' : '' ?>">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <span class="badge bg-primary" style="font-size:.75rem;min-width:70px;text-align:center;">
                            <?= htmlspecialchars($c['codigo_concepto']) ?>
                        </span>
                        <?php
if ($c['numero_original']): ?>
                            <small class="text-muted">#<?= htmlspecialchars($c['numero_original']) ?></small>
                        <?php
endif; ?>
                    </div>
                    <div class="fw-semibold text-dark" style="font-size:.88rem;line-height:1.3;">
                        <?= htmlspecialchars($c['nombre_concepto']) ?>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mt-1" style="font-size:.78rem;color:#6c757d;">
                        <?php
if ($c['unidad_medida']): ?>
                            <span><i class="bi bi-rulers me-1"></i><?= htmlspecialchars($c['unidad_medida']) ?></span>
                        <?php
endif; ?>
                        <?php
if (!empty($c['cantidad'])): ?>
                            <span>Cant: <strong class="text-dark"><?= number_format($c['cantidad'], 3) ?></strong></span>
                        <?php
endif; ?>
                        <?php
if (!empty($c['precio_unitario'])): ?>
                            <span>P.U.: <strong class="text-dark">$<?= number_format($c['precio_unitario'], 2) ?></strong></span>
                        <?php
endif; ?>
                        <?php
if (!empty($c['importe'])): ?>
                            <span>Importe: <strong class="text-dark">$<?= number_format($c['importe'], 2) ?></strong></span>
                        <?php
endif; ?>
                        <?php
if (!empty($c['fecha_inicio']) || !empty($c['fecha_fin'])): ?>
                            <span><i class="bi bi-calendar-range me-1"></i>
                                <?= !empty($c['fecha_inicio']) ? date('d/m/Y', strtotime($c['fecha_inicio'])) : '&mdash;' ?>
                                &rarr;
                                <?= !empty($c['fecha_fin']) ? date('d/m/Y', strtotime($c['fecha_fin'])) : '&mdash;' ?>
                            </span>
                        <?php
endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-end align-items-center gap-3">
                        <div class="text-end">
                            <?php
if ($c['total_items'] > 0): ?>
                                <span class="badge bg-info mb-1"><?= $c['total_items'] ?> items</span><br>
                                <span class="fw-bold text-success" style="font-size:.9rem;">$<?= number_format($c['monto_total'], 2) ?></span>
                            <?php
else: ?>
                                <span class="badge bg-secondary mb-1">Sin items</span><br>
                                <span class="text-muted" style="font-size:.85rem;">$0.00</span>
                            <?php
endif; ?>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn-edit btn-sm"
                                    onclick="abrirModalEditar(<?= $c['id'] ?>)"
                                    title="Editar concepto">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-inf btn-sm"
                                    onclick="verDetalleConceptoView(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['codigo_concepto'])) ?>')"
                                    title="Ver detalle">
                                <i class="bi bi-info-circle"></i>
                            </button>
                            <button class="btn-del btn-sm"
                                    onclick="eliminarConceptoView(<?= $c['id'] ?>, <?= $catalogo_id ?>, '<?= htmlspecialchars(addslashes($catalogo['nombre_catalogo'])) ?>', <?= $obra_id ?: 'null' ?>, '<?= $obra_info ? htmlspecialchars(addslashes($obra_info['nombre_obra'])) : '' ?>')"
                                    title="Eliminar concepto">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conceptos - <?= htmlspecialchars($catalogo['nombre_catalogo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/details.css">
    <style>
        .item-orden-pagada { border-left: 4px solid #28a745; background: rgba(40,167,69,.05); }
        .nodo-header       { transition: box-shadow .15s; cursor: default; }
        .nodo-header:hover { box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .btn-edit { background:#0d6efd; color:#fff; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; }
        .btn-inf  { background:#6c757d; color:#fff; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; }
        .btn-del  { background:#c82333; color:#fff; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; }
        .btn-edit:hover { background:#0b5ed7; }
        .btn-inf:hover  { background:#5a6268; }
        .btn-del:hover  { background:#a71d2a; }
        /* Modal */
        #modalEditar .modal-header { background:#1a3a5c; color:#fff; }
        #modalEditar .modal-header .btn-close { filter:invert(1); }
        .sec-label { font-size:.73rem; font-weight:700; text-transform:uppercase;
                     letter-spacing:.06em; color:#6c757d;
                     border-bottom:1px solid #dee2e6; padding-bottom:3px; margin:16px 0 10px; }
    </style>
</head>
<body>
    <?php
include __DIR__ . "/../includes/navbar.php"; ?>

    <div class="hero-section">
        <div class="container hero-content">
            <div class="breadcrumb-custom">
                <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a> <span>/</span>
                <a href="list_project.php">Registro de Obras</a> <span>/</span>
                <?php
if ($obra_info): ?>
                    <a href="details_obra.php?id=<?= $obra_id ?>"><?= htmlspecialchars($obra_info['nombre_obra']) ?></a> <span>/</span>
                <?php
endif; ?>
                <span><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></span>
            </div>
            <h1 class="hero-title"><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></h1>
            <?php
if (!empty($catalogo['descripcion'])): ?>
                <p class="lead mb-0" style="color:#ddd;font-size:14px;"><?= htmlspecialchars($catalogo['descripcion']) ?></p>
            <?php
endif; ?>
            <?php
if ($obra_info): ?>
                <p class="mb-0" style="color:#ddd;font-size:13px;"><small>Obra: <?= htmlspecialchars($obra_info['nombre_obra']) ?></small></p>
            <?php
endif; ?>
            <div class="mt-3">
                <button class="btn btn-sm btn-outline-light" 
                        onclick="editarCatalogo(<?= $catalogo_id ?>, '<?= addslashes($catalogo['nombre_catalogo']) ?>', '<?= addslashes($catalogo['descripcion']) ?>')">
                    <i class="bi bi-pencil-square me-1"></i> Editar InformaciÃ³n del CatÃ¡logo
                </button>
            </div>
        </div>
    </div>

    <div class="content-wrapper">

        <!-- ESTADÃSTICAS -->
        <div class="budget-dashboard">
            <div class="dashboard-header">
                <div class="dashboard-title">
                    <div class="title-icon"><i class="bi bi-info-circle"></i></div>
                    <h3>InformaciÃ³n General</h3>
                </div>
            </div>
            <div class="budget-stats">
                <div class="budget-stat">
                    <div class="budget-stat-label">Total Conceptos</div>
                    <div class="budget-stat-value"><?= $stats['total_conceptos'] ?></div>
                </div>
                <div class="budget-stat">
                    <div class="budget-stat-label">Nodos en Ãrbol</div>
                    <div class="budget-stat-value"><?= $stats['total_nodos'] ?></div>
                </div>
                <div class="budget-stat">
                    <div class="budget-stat-label">Niveles Usados</div>
                    <div class="budget-stat-value"><?= $stats['niveles_usados'] ?></div>
                </div>
                <div class="budget-stat">
                    <div class="budget-stat-label">Monto Total</div>
                    <div class="budget-stat-value text-success">$<?= number_format($stats['monto_total_general'], 2) ?></div>
                </div>
            </div>
        </div>

        <!-- CONCEPTOS -->
        <div class="budget-dashboard">
            <div class="dashboard-header">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="dashboard-title mb-0">
                        <div class="title-icon"><i class="bi bi-list-ul"></i></div>
                        <h3>Conceptos</h3>
                    </div>
                    <select id="filtroItems" class="form-select form-select-sm" style="width:auto;">
                        <option value="todos">â€” Todos â€”</option>
                        <option value="conItems">Con items</option>
                        <option value="sinItems">Sin items</option>
                    </select>
                    <button class="btn btn-sm" style="background:#1a7a4a;color:#fff;border:none;"
                            onclick="toggleFilter(document.getElementById('filtroItems').value)">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
                <div class="d-flex gap-2 mt-2 mt-sm-0">
                    <button class="btn btn-success btn-sm" onclick="mostrarFormConcepto()">
                        <i class="bi bi-plus-circle"></i> Nuevo Concepto
                    </button>
                    <button class="btn btn-inf btn-sm" onclick="importarExcelConceptos()">
                        <i class="bi bi-upload"></i> Importar Excel
                    </button>
                </div>
            </div>

            <?php
if (!empty($raices) || !empty($conceptos_sin_nodo)): ?>

                <!-- ÃRBOL N NIVELES -->
                <?php
foreach ($raices as &$raiz): renderNodo($raiz); endforeach; ?>

                <!-- SIN CATEGORÃA -->
                <?php
if (!empty($conceptos_sin_nodo)): ?>
                    <div class="mt-4">
                        <div class="nodo-header d-flex align-items-center gap-2 px-3 py-2 mb-2"
                             style="background:#f8fafc;border-left:4px solid #adb5bd;border-radius:0 6px 6px 0;">
                            <i class="bi bi-question-circle text-muted"></i>
                            <span class="fw-semibold text-muted" style="font-size:.9rem;">Sin CategorÃ­a</span>
                            <span class="badge bg-secondary ms-auto"><?= count($conceptos_sin_nodo) ?></span>
                        </div>
                        <?php
foreach ($conceptos_sin_nodo as $c): renderConcepto($c, 18); endforeach; ?>
                    </div>
                <?php
endif; ?>

            <?php
else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">No hay conceptos registrados</h4>
                    <p class="text-muted">Crea tu primer concepto o importa desde Excel</p>
                    <div class="mt-3">
                        <button class="btn btn-success" onclick="mostrarFormConcepto()">
                            <i class="bi bi-plus-circle"></i> Crear Concepto
                        </button>
                        <button class="btn btn-inf ms-2" onclick="importarExcelConceptos()">
                            <i class="bi bi-upload"></i> Importar Excel
                        </button>
                    </div>
                </div>
            <?php
endif; ?>
        </div>

    </div><!-- /.content-wrapper -->

    <!-- ============================================================
         MODAL EDITAR CONCEPTO
    ============================================================= -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>
                        Editar Concepto &mdash;
                        <span id="editCodLabel" class="fw-light"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Spinner -->
                    <div id="editSpinner" class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2 text-muted small">Cargando datos...</p>
                    </div>

                    <div id="editFormBody" class="d-none">
                        <input type="hidden" id="editId">

                        <!-- IDENTIFICACIÃ“N -->
                        <div class="sec-label"><i class="bi bi-tag me-1"></i>IdentificaciÃ³n</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">CÃ³digo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editCodigo">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">NÃºm. Original</label>
                                <input type="text" class="form-control" id="editNumOrig">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editNombre">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold">DescripciÃ³n</label>
                            <textarea class="form-control" id="editDesc" rows="4"></textarea>
                        </div>

                        <!-- JERARQUÃA -->
                        <div class="sec-label mt-1"><i class="bi bi-diagram-3 me-1"></i>PosiciÃ³n en la JerarquÃ­a</div>
                        <div class="alert alert-light border mb-3" style="font-size:.82rem;">
                            <i class="bi bi-info-circle-fill text-primary me-1"></i>
                            Escribe la <strong>clave del nodo padre</strong> al que pertenece este concepto.<br>
                            Los segmentos se separan por <code>.</code> â€” cada segmento es un nivel.<br>
                            <strong>Ejemplos:</strong>
                            <code>CIMENTACION</code> &bull;
                            <code>PRELIMINARES</code> &bull;
                            <code>I.2</code> &bull;
                            <code>I.2.1</code> &bull;
                            <code>OBRAS.POZO.EQUIPAMIENTO</code><br>
                            Si el nodo no existe se crea automÃ¡ticamente.
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Clave del nodo</label>
                                <input type="text" class="form-control font-monospace"
                                       id="editNodoClave"
                                       placeholder="Ej: CIMENTACION  |  I.2  |  OBRAS.POZO.EQUIP">
                                <div class="form-text">Deja vacÃ­o para dejar el concepto sin categorÃ­a.</div>
                            </div>
                            <div class="col-md-4">
                                <div id="nivelPreview"
                                     class="p-2 rounded text-center"
                                     style="background:#f8fafc;border:1px dashed #ccc;font-size:.82rem;color:#555;min-height:38px;">
                                    Nivel: <strong id="nivelNum">&mdash;</strong>
                                </div>
                            </div>
                        </div>

                        <!-- MEDICIÃ“N Y COSTOS -->
                        <div class="sec-label mt-1"><i class="bi bi-currency-dollar me-1"></i>MediciÃ³n y Costos</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Unidad</label>
                                <input type="text" class="form-control" id="editUnidad" placeholder="pza, m, m2, kgâ€¦">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Cantidad</label>
                                <input type="number" class="form-control" id="editCantidad" step="0.001" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="editPU" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Importe</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="editImporte" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2"
                                onclick="calcularImporte()">
                            <i class="bi bi-calculator me-1"></i>Calcular importe (Cant Ã— P.U.)
                        </button>

                        <!-- PERIODO -->
                        <div class="sec-label mt-1"><i class="bi bi-calendar-range me-1"></i>Periodo</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha Inicio</label>
                                <input type="date" class="form-control" id="editFechaIni">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha Fin</label>
                                <input type="date" class="form-control" id="editFechaFin">
                            </div>
                        </div>
                    </div><!-- /#editFormBody -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardar" onclick="guardarEdicion()">
                        <i class="bi bi-floppy me-1"></i>Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FAB volver -->
    <div class="fab-container-backbtn">
        <a onclick="history.back()" class="fab-button-backbtn">
            <i class="bi bi-arrow-left"></i>
            <span class="fab-tooltip-backbtn">Volver</span>
        </a>
    </div>

    <input type="hidden" id="currentCatalogoId" value="<?= $catalogo_id ?>">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/scripts/catalogo-obras.js"></script>
    <script>
    const catalogoId     = <?= $catalogo_id ?>;
    const catalogoNombre = '<?= addslashes($catalogo['nombre_catalogo']) ?>';
    const obraId         = <?= $obra_id ?: 'null' ?>;
    const obraNombre     = '<?= $obra_info ? addslashes($obra_info['nombre_obra']) : '' ?>';
    const API            = 'catalogos_manager.php';

    // â”€â”€ Filtro â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function toggleFilter(tipo) {
        document.querySelectorAll('.concepto-item').forEach(el => {
            const ti = el.dataset.tieneItems === 'si';
            el.style.display =
                tipo === 'conItems' && !ti ? 'none' :
                tipo === 'sinItems' &&  ti ? 'none' : '';
        });
    }

    // â”€â”€ Wrappers funciones externas (catalogo-obras.js) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function mostrarFormConcepto() {
        typeof mostrarFormularioConcepto === 'function'
            ? mostrarFormularioConcepto(catalogoId, catalogoNombre, obraId, obraNombre)
            : Swal.fire('Error', 'FunciÃ³n no disponible. Recarga la pÃ¡gina.', 'error');
    }
    function importarExcelConceptos() {
        typeof mostrarImportarExcelConceptos === 'function'
            ? mostrarImportarExcelConceptos(catalogoId, catalogoNombre, obraId, obraNombre)
            : Swal.fire('Error', 'FunciÃ³n no disponible. Recarga la pÃ¡gina.', 'error');
    }
    function eliminarConceptoView(cid, catId, catNombre, oId, oNombre) {
        typeof eliminarConcepto === 'function' &&
            eliminarConcepto(cid, catId, catNombre, oId, oNombre);
    }
    function editarCatalogoView() {
        if (typeof editarCatalogo === 'function') {
            editarCatalogo(catalogoId, catalogoNombre, '<?= addslashes($catalogo['descripcion']) ?>');
        }
    }
    function verDetalleConceptoView(cid, codigo) {
        typeof verDetalleConcepto === 'function' &&
            verDetalleConcepto(cid, codigo, catalogoId, catalogoNombre, obraId, obraNombre);
    }

    // â”€â”€ Preview nivel mientras se escribe la clave â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('editNodoClave').addEventListener('input', function () {
            const val   = this.value.trim();
            const nivel = val === '' ? null : val.split('.').length;
            document.getElementById('nivelNum').textContent = nivel ? nivel : 'â€”';
        });
    });

    // â”€â”€ MODAL EDITAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function abrirModalEditar(conceptoId) {
        // Resetear estado
        document.getElementById('editSpinner').classList.remove('d-none');
        document.getElementById('editFormBody').classList.add('d-none');
        document.getElementById('editCodLabel').textContent = '';
        new bootstrap.Modal(document.getElementById('modalEditar')).show();

        const fd = new FormData();
        fd.append('action', 'obtener_detalle_concepto');
        fd.append('concepto_id', conceptoId);

        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                document.getElementById('editSpinner').classList.add('d-none');
                if (!data.success) {
                    Swal.fire('Error', data.error || 'No se pudo cargar el concepto', 'error');
                    return;
                }
                const c = data.concepto;
                document.getElementById('editId').value          = c.id;
                document.getElementById('editCodigo').value      = c.codigo_concepto  || '';
                document.getElementById('editNumOrig').value     = c.numero_original  || '';
                document.getElementById('editNombre').value      = c.nombre_concepto  || '';
                document.getElementById('editDesc').value        = c.descripcion      || '';
                document.getElementById('editNodoClave').value   = c.nodo_clave       || '';
                document.getElementById('editUnidad').value      = c.unidad_medida    || '';
                document.getElementById('editCantidad').value    = c.cantidad         || '';
                document.getElementById('editPU').value          = c.precio_unitario  || '';
                document.getElementById('editImporte').value     = c.importe          || '';
                document.getElementById('editFechaIni').value    = c.fecha_inicio     || '';
                document.getElementById('editFechaFin').value    = c.fecha_fin        || '';

                // Preview nivel
                const nk = c.nodo_clave || '';
                document.getElementById('nivelNum').textContent  = nk ? nk.split('.').length : 'â€”';
                document.getElementById('editCodLabel').textContent = c.codigo_concepto || '';
                document.getElementById('editFormBody').classList.remove('d-none');
            })
            .catch(err => {
                document.getElementById('editSpinner').classList.add('d-none');
                Swal.fire('Error', err.message, 'error');
            });
    }

    function calcularImporte() {
        const q  = parseFloat(document.getElementById('editCantidad').value) || 0;
        const pu = parseFloat(document.getElementById('editPU').value)        || 0;
        if (q > 0 && pu > 0) {
            document.getElementById('editImporte').value = (q * pu).toFixed(2);
        } else {
            Swal.fire('AtenciÃ³n', 'Ingresa Cantidad y Precio Unitario primero.', 'warning');
        }
    }

    function guardarEdicion() {
        const codigo = document.getElementById('editCodigo').value.trim();
        const nombre = document.getElementById('editNombre').value.trim();
        if (!codigo || !nombre) {
            Swal.fire('AtenciÃ³n', 'El CÃ³digo y el Nombre son obligatorios.', 'warning');
            return;
        }

        const btn = document.getElementById('btnGuardar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardandoâ€¦';

        const fd = new FormData();
        fd.append('action',          'actualizar_concepto');
        fd.append('concepto_id',     document.getElementById('editId').value);
        fd.append('codigo_concepto', codigo);
        fd.append('nombre_concepto', nombre);
        fd.append('descripcion',     document.getElementById('editDesc').value.trim());
        fd.append('nodo_clave',      document.getElementById('editNodoClave').value.trim());
        fd.append('unidad_medida',   document.getElementById('editUnidad').value.trim());
        fd.append('numero_original', document.getElementById('editNumOrig').value.trim());
        fd.append('cantidad',        document.getElementById('editCantidad').value);
        fd.append('precio_unitario', document.getElementById('editPU').value);
        fd.append('importe',         document.getElementById('editImporte').value);
        fd.append('fecha_inicio',    document.getElementById('editFechaIni').value);
        fd.append('fecha_fin',       document.getElementById('editFechaFin').value);

        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar Cambios';
                if (data.success) {
                    bootstrap.Modal.getInstance(
                        document.getElementById('modalEditar')
                    ).hide();
                    Swal.fire({
                        icon: 'success', title: 'Guardado',
                        text: data.message,
                        timer: 1800, showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'No se pudo actualizar', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar Cambios';
                Swal.fire('Error', err.message, 'error');
            });
    }
    </script>

    <?php
include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>



