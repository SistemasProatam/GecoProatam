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
        <div class="nodo-header d-flex align-items-center justify-content-between px-3 py-2 mb-1"
            style="background:<?= $est['bg'] ?>; border-left:4px solid <?= $est['border'] ?>; border-radius:0 6px 6px 0; color:<?= $est['color'] ?>;">
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
                        <span class="badge bg-info" style="font-size:.7rem;"><?= $t_items ?> items</span>
                    <?php endif; ?>
                    <span class="badge bg-success" style="font-size:.7rem;">$<?= number_format($t_monto, 2) ?></span>
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
                        <span class="badge bg-primary" style="font-size:.75rem;min-width:70px;text-align:center;"><?= htmlspecialchars($c['codigo_concepto']) ?></span>
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
                                <span class="badge bg-info mb-1"><?= $c['total_items'] ?> items</span><br>
                                <span class="fw-bold text-success" style="font-size:.9rem;">$<?= number_format($c['monto_total'], 2) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary mb-1">Sin items</span><br>
                                <span class="text-muted" style="font-size:.85rem;">$0.00</span>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="abrirModalEditar(<?= $c['id'] ?>)" title="Editar"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm btn-outline-info" onclick="verDetalleConceptoView(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['codigo_concepto'])) ?>')" title="Detalle"><i class="bi bi-info-circle"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminarConceptoView(<?= $c['id'] ?>)" title="Eliminar"><i class="bi bi-trash3"></i></button>
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
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/details.css">
    <style>
        .item-orden-pagada { border-left: 4px solid #28a745; background: rgba(40,167,69,.05); }
        .sec-label { font-size:.73rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6c757d; border-bottom:1px solid #dee2e6; padding-bottom:3px; margin:16px 0 10px; }
    </style>
</head>
<body>
    <?php include __DIR__ . "/../includes/navbar.php"; ?>

    <div class="hero-section">
        <div class="container hero-content">
            <div class="breadcrumb-custom">
                <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a> <span>/</span>
                <a href="list_project.php">Registro de Obras</a> <span>/</span>
                <?php if ($obra_info): ?><a href="details_obra.php?id=<?= $obra_id ?>"><?= htmlspecialchars($obra_info['nombre_obra']) ?></a> <span>/</span><?php endif; ?>
                <span><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></span>
            </div>
            <h1 class="hero-title"><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></h1>
            <div class="mt-3">
                <button class="btn btn-sm btn-outline-light" onclick="editarCatalogoView()"><i class="bi bi-pencil-square me-1"></i> Editar Catálogo</button>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="budget-dashboard mb-4">
            <div class="budget-stats">
                <div class="budget-stat"><div class="budget-stat-label">Total Conceptos</div><div class="budget-stat-value"><?= $stats['total_conceptos'] ?></div></div>
                <div class="budget-stat"><div class="budget-stat-label">Nodos</div><div class="budget-stat-value"><?= $stats['total_nodos'] ?></div></div>
                <div class="budget-stat"><div class="budget-stat-label">Niveles</div><div class="budget-stat-value"><?= $stats['niveles_usados'] ?></div></div>
                <div class="budget-stat"><div class="budget-stat-label">Monto Total</div><div class="budget-stat-value text-success">$<?= number_format($stats['monto_total_general'], 2) ?></div></div>
            </div>
        </div>

        <div class="budget-dashboard">
            <div class="dashboard-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Conceptos</h5>
                    <select id="filtroItems" class="form-select form-select-sm" style="width:auto;" onchange="toggleFilter(this.value)">
                        <option value="todos">Todos</option>
                        <option value="conItems">Con items</option>
                        <option value="sinItems">Sin items</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="mostrarFormConcepto()"><i class="bi bi-plus-circle me-1"></i>Nuevo</button>
                    <button class="btn btn-info btn-sm text-white" onclick="importarExcelConceptos()"><i class="bi bi-upload me-1"></i>Importar</button>
                </div>
            </div>

            <div class="mt-4">
                <?php foreach ($raices as &$raiz): renderNodo($raiz); endforeach; ?>
                <?php if (!empty($conceptos_sin_nodo)): ?>
                    <div class="mt-4"><div class="nodo-header px-3 py-2 bg-light mb-2"><strong>Sin Categoría</strong></div><?php foreach ($conceptos_sin_nodo as $c): renderConcepto($c, 18); endforeach; ?></div>
                <?php endif; ?>
                <?php if (empty($raices) && empty($conceptos_sin_nodo)): ?>
                    <div class="text-center py-5 text-muted"><i class="bi bi-inbox display-4 d-block mb-3"></i>No hay conceptos registrados.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="fab-container-backbtn">
        <a onclick="history.back()" class="fab-button-backbtn"><i class="bi bi-arrow-left"></i><span class="fab-tooltip-backbtn">Volver</span></a>
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
                title: "Editar Concepto - " + c.codigo_concepto,
                size: "lg",
                html: `
                    <form id="formEditConc">
                        <input type="hidden" name="concepto_id" value="${c.id}">
                        <div class="sec-label">Identificación</div>
                        <div class="row g-2">
                            <div class="col-4"><label class="form-label small">Código</label><input type="text" name="codigo_concepto" class="form-control" value="${c.codigo_concepto}" required></div>
                            <div class="col-2"><label class="form-label small">Núm.</label><input type="text" name="numero_original" class="form-control" value="${c.numero_original || ''}"></div>
                            <div class="col-6"><label class="form-label small">Nombre</label><input type="text" name="nombre_concepto" class="form-control" value="${c.nombre_concepto}" required></div>
                        </div>
                        <div class="mt-2"><label class="form-label small">Descripción</label><textarea name="descripcion" class="form-control" rows="2">${c.descripcion || ''}</textarea></div>
                        <div class="sec-label">Jerarquía</div>
                        <div class="row g-2"><div class="col-12"><label class="form-label small">Clave del nodo</label><input type="text" name="nodo_clave" class="form-control font-monospace" value="${c.nodo_clave || ''}" placeholder="Ej: I.1.1"></div></div>
                        <div class="sec-label">Costos</div>
                        <div class="row g-2">
                            <div class="col-3"><label class="form-label small">Unidad</label><input type="text" name="unidad_medida" class="form-control" value="${c.unidad_medida || ''}"></div>
                            <div class="col-3"><label class="form-label small">Cantidad</label><input type="number" step="0.001" name="cantidad" class="form-control" value="${c.cantidad || ''}"></div>
                            <div class="col-3"><label class="form-label small">P. Unitario</label><input type="number" step="0.01" name="precio_unitario" class="form-control" value="${c.precio_unitario || ''}"></div>
                            <div class="col-3"><label class="form-label small">Importe</label><input type="number" step="0.01" name="importe" class="form-control" value="${c.importe || ''}"></div>
                        </div>
                        <div class="sec-label">Periodo</div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small">Inicio</label><input type="date" name="fecha_inicio" class="form-control" value="${c.fecha_inicio || ''}"></div>
                            <div class="col-6"><label class="form-label small">Fin</label><input type="date" name="fecha_fin" class="form-control" value="${c.fecha_fin || ''}"></div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                    </form>`
            });
            document.getElementById('formEditConc').addEventListener('submit', function(e) {
                e.preventDefault();
                UI.loading("Guardando...");
                const fd2 = new FormData(this); fd2.append('action', 'actualizar_concepto');
                fetch(API, { method: 'POST', body: fd2 }).then(r => r.json()).then(res => {
                    UI.loading.hide();
                    if (res.success) { UI.modal.close(); UI.toast.success("Actualizado"); setTimeout(() => location.reload(), 1500); }
                    else UI.toast.error(res.error);
                }).catch(() => { UI.loading.hide(); UI.toast.error("Error de red"); });
            });
        });
    }
    </script>

    <?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
