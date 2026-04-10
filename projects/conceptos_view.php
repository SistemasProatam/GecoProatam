<?php
require_once __DIR__ . '/../config.php';

// conceptos_view.php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

// Obtener parámetros
$catalogo_id = $_GET['catalogo_id'] ?? 0;
$catalogo_nombre = $_GET['catalogo_nombre'] ?? '';
$obra_id = $_GET['obra_id'] ?? 0;
$obra_nombre = $_GET['obra_nombre'] ?? '';

if ($catalogo_id <= 0) {
    header("Location: list_obras.php");
    exit;
}

// Obtener información del catálogo
$sql_catalogo = "SELECT * FROM catalogos WHERE id = ?";
$stmt = $conn->prepare($sql_catalogo);
$stmt->bind_param("i", $catalogo_id);
$stmt->execute();
$catalogo = $stmt->get_result()->fetch_assoc();

if (!$catalogo) {
    header("Location: list_obras.php");
    exit;
}

// Obtener información de la obra si está disponible
$obra_info = null;
if ($obra_id > 0) {
    $sql_obra = "SELECT * FROM obras WHERE id = ?";
    $stmt_obra = $conn->prepare($sql_obra);
    $stmt_obra->bind_param("i", $obra_id);
    $stmt_obra->execute();
    $obra_info = $stmt_obra->get_result()->fetch_assoc();
}

// Obtener conceptos del catálogo ordenados jerárquicamente
// NOTA: contabilizamos y sumamos items directamente desde `orden_compra_items`
// sólo cuando la orden asociada tiene estado 'pagado'. No hacemos transferencias.
$sql_conceptos = "SELECT c.*, 
                                 (SELECT COUNT(*) FROM orden_compra_items oci JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') as total_items,
                                 (SELECT COALESCE(SUM(oci.subtotal), 0) 
                                    FROM orden_compra_items oci JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                                    WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') as monto_total
                                 FROM conceptos c 
                                 WHERE c.catalogo_id = ? 
                                 ORDER BY c.id ASC";

$conceptos = null;
$stmt_conceptos = $conn->prepare($sql_conceptos);

if ($stmt_conceptos) {
    $stmt_conceptos->bind_param("i", $catalogo_id);
    
    if ($stmt_conceptos->execute()) {
        $conceptos = $stmt_conceptos->get_result();
        
        if (!$conceptos) {
            error_log("Error al obtener resultados: " . $stmt_conceptos->error);
            $conceptos = null;
        }
    } else {
        error_log("Error al ejecutar consulta: " . $stmt_conceptos->error);
    }
} else {
    error_log("Error al preparar consulta: " . $conn->error);
}

// Verificar si existe la tabla `orden_compra_items` y `ordenes_compra`
$tabla_ordenes_items_existe = false;
$tabla_ordenes_compra_existe = false;

$sql_check_tabla = "SHOW TABLES LIKE 'orden_compra_items'";
$result_check = $conn->query($sql_check_tabla);
if ($result_check && $result_check->num_rows > 0) {
    $tabla_ordenes_items_existe = true;
}

$sql_check_tabla2 = "SHOW TABLES LIKE 'ordenes_compra'";
$result_check2 = $conn->query($sql_check_tabla2);
if ($result_check2 && $result_check2->num_rows > 0) {
    $tabla_ordenes_compra_existe = true;
}

// Obtener estadísticas - Versión adaptada según tablas existentes
if ($tabla_ordenes_compra_existe && $tabla_ordenes_items_existe) {
    // Versión con JOIN a ordenes_compra: sumar subtotales desde orden_compra_items
    $sql_stats = "SELECT 
                  COUNT(*) as total_conceptos,
                  COUNT(DISTINCT categoria) as total_categorias,
                  COUNT(DISTINCT subcategoria) as total_subcategorias,
                  COALESCE(SUM(
                      (SELECT COALESCE(SUM(oci.subtotal), 0) 
                       FROM orden_compra_items oci JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                       WHERE oci.concepto_id = c.id AND oc.estado = 'pagado')
                  ), 0) as monto_total_general,
                  (SELECT COUNT(DISTINCT oc.id) 
                   FROM ordenes_compra oc 
                   WHERE oc.estado = 'pagado' 
                   AND (oc.catalogo_id = ? OR EXISTS (SELECT 1 FROM orden_compra_items oci JOIN conceptos cc ON oci.concepto_id = cc.id WHERE oci.orden_compra_id = oc.id AND cc.catalogo_id = ?))) as ordenes_pagadas
                  FROM conceptos c 
                  WHERE c.catalogo_id = ?";
} else {
    // Versión simplificada sin JOIN a ordenes_compra: sumar desde orden_compra_items si existe
    $sql_stats = "SELECT 
                  COUNT(*) as total_conceptos,
                  COUNT(DISTINCT categoria) as total_categorias,
                  COUNT(DISTINCT subcategoria) as total_subcategorias,
                  COALESCE(SUM(
                      (SELECT COALESCE(SUM(oci.subtotal), 0) 
                       FROM orden_compra_items oci JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                       WHERE oci.concepto_id = c.id AND oc.estado = 'pagado')
                  ), 0) as monto_total_general,
                  0 as ordenes_pagadas
                  FROM conceptos c 
                  WHERE c.catalogo_id = ?";
}

$stmt_stats = $conn->prepare($sql_stats);
if ($tabla_ordenes_compra_existe && $tabla_ordenes_items_existe) {
    // Tres placeholders: EXISTS(...) usa un ? y la cláusula WHERE c.catalogo_id = ? también
    $stmt_stats->bind_param("iii", $catalogo_id, $catalogo_id, $catalogo_id);
} else {
    $stmt_stats->bind_param("i", $catalogo_id);
}
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();


// No se realiza transferencia automática a una tabla separada.
// Ahora los items asignados a conceptos se leen directamente desde
// `orden_compra_items` cuando la orden asociada tiene `estado = 'pagado'`.

// Organizar conceptos en estructura jerárquica
$estructura_jerarquica = [];
$conceptos_sin_categoria = [];

// Verificar que $conceptos no sea null antes de usarlo
if ($conceptos) {
    while ($concepto = $conceptos->fetch_assoc()) {
        $categoria = $concepto['categoria'] ?: 'Sin Categoría';
        $subcategoria = $concepto['subcategoria'] ?: 'General';
        
        if ($categoria === 'Sin Categoría') {
            $conceptos_sin_categoria[] = $concepto;
        } else {
            if (!isset($estructura_jerarquica[$categoria])) {
                $estructura_jerarquica[$categoria] = [];
            }
            if (!isset($estructura_jerarquica[$categoria][$subcategoria])) {
                $estructura_jerarquica[$categoria][$subcategoria] = [];
            }
            $estructura_jerarquica[$categoria][$subcategoria][] = $concepto;
        }
    }
} else {
    echo "<div class='alert alert-warning'>No se pudieron cargar los conceptos</div>";
}
?>

<?php

// ============================================================
// HELPER: detectar si un codigo_concepto es categoría/subcategoría
// ============================================================
function getTipoConcepto($codigo) {
    $c = trim($codigo);
    // Nivel 1: I, II, III, IV... (solo romanos)
    if (preg_match('/^[IVXLCDM]+$/i', $c)) return 'nivel1';
    // Nivel 2 romano: I.1, II.3, etc.
    if (preg_match('/^[IVXLCDM]+\.\d+$/i', $c)) return 'nivel2';
    // Nivel 2 numérico: 1.2, 11.1, etc.
    if (preg_match('/^\d+\.\d+$/', $c)) return 'nivel2';
    // Nivel 3 numérico: 1.2.1, 11.2.3, etc.
    if (preg_match('/^\d+\.\d+\.\d+$/', $c)) return 'nivel3';
    // Nivel 3 romano+número+número: II.3.2, I.2.1, etc.
    if (preg_match('/^[IVXLCDM]+\.\d+\.\d+$/i', $c)) return 'nivel3';
    return 'concepto';
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
        .item-orden-pagada {
            border-left: 4px solid #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }
        .badge-pagado {
            background-color: #28a745;
        }
        .btn-ordenes-pagadas {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        .btn-ordenes-pagadas:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }

        .concepto-badge {
            min-width: 80px;
            text-align: center;
        }
        .action-buttons .btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .btn-inf {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        .btn-del {
            background-color: #c82333;
            color: white;
            border: none;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . "/../includes/navbar.php"; ?>

    <!-- HERO SECTION -->
    <div class="hero-section">
        <div class="container hero-content">
            <div class="breadcrumb-custom">
                <a href="index.php"><i class="bi bi-house-door"></i> Inicio</a>
                <span>/</span>
                <a href="list_project.php"> Registro de Obras</a>
                <span>/</span>
                <a href="details_obra.php?id=<?= $obra_id ?>"><?= htmlspecialchars($obra_info['nombre_obra']) ?></a>
                <span>/</span>
                <span>Catalogo - <?= htmlspecialchars($catalogo['nombre_catalogo']) ?></span>
            </div>
            
            <div class="row align-items-end">
                <div class="col-lg-8">
                    <h1 class="hero-title"><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></h1>
                    <div style="color: #ddd; font-size: 14px; margin-top: -5px;">
                        <?php if ($catalogo['descripcion']): ?>
                            <p class="lead mb-0"><?= htmlspecialchars($catalogo['descripcion']) ?></p>
                        <?php endif; ?>
                        <?php if ($obra_info): ?>
                            <p class="mb-0"><small>Obra: <?= htmlspecialchars($obra_info['nombre_obra']) ?></small></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content-wrapper">
        <!-- ESTADÍSTICAS -->
        <div class="budget-dashboard">
            <div class="dashboard-header">
                <div class="dashboard-title">
                    <div class="title-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <h3>Información General</h3>
                </div>

            </div>

            <div class="budget-stats">
                <div class="budget-stat">
                    <div class="budget-stat-label">Total Conceptos</div>
                    <div class="budget-stat-value"><?= $stats['total_conceptos'] ?></div>
                </div>
                <div class="budget-stat">
                    <div class="budget-stat-label">Categorías</div>
                    <div class="budget-stat-value"><?= $stats['total_categorias'] ?></div>
                </div>
                <div class="budget-stat">
                    <div class="budget-stat-label">Subcategorías</div>
                    <div class="budget-stat-value"><?= $stats['total_subcategorias'] ?></div>
                </div>
                <div class="budget-stat">
                    <div class="budget-stat-label">Monto Total</div>
                    <div class="budget-stat-value text-success">$<?= number_format($stats['monto_total_general'], 2) ?></div>
                    <small class="text-muted">Monto asignado a conceptos</small>
                </div>
            </div>
        </div>

        <!-- BOTONES DE ACCIÓN -->
        <div class="budget-dashboard">
            <div class="dashboard-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="dashboard-title mb-0">
                        <div class="title-icon">
                            <i class="bi bi-list-ul"></i>
                        </div>
                        <h3>Conceptos</h3>
                    </div>
                    <select id="filtroItems" class="form-select form-select-sm" style="width:auto;">
                        <option value="todos">-- Todos los conceptos --</option>
                        <option value="conItems">Con items</option>
                        <option value="sinItems">Sin items</option>
                    </select>
                    <button class="btn btn-sm" style="background-color:#1a7a4a;color:white;border:none;" onclick="toggleFilter(document.getElementById('filtroItems').value)">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
                <div>
                    <button class="btn btn-success btn-sm" onclick="mostrarFormConcepto()">
                        <i class="bi bi-plus-circle"></i> Nuevo Concepto
                    </button>
                    <button class="btn btn-inf btn-sm" onclick="importarExcelConceptos()">
                        <i class="bi bi-upload"></i> Importar Excel
                    </button>
                </div>
            </div>

            <!-- ESTRUCTURA JERÁRQUICA DE CONCEPTOS -->
            <?php if (!empty($estructura_jerarquica) || !empty($conceptos_sin_categoria)): ?>
                
                <!-- CONCEPTOS ORGANIZADOS POR CATEGORÍA Y SUBCATEGORÍA -->
                <?php foreach ($estructura_jerarquica as $categoria => $subcategorias): ?>
                    <?php
                    // Calcular total de items para esta categoría
                    $total_items_categoria = 0;
                    $total_monto_categoria = 0;
                    foreach ($subcategorias as $subcategoria => $conceptos_sub) {
                        foreach ($conceptos_sub as $concepto) {
                            $total_items_categoria += $concepto['total_items'];
                            $total_monto_categoria += $concepto['monto_total'];
                        }
                    }
                    ?>
                    
                    <div class="categoria-section mb-4 concepto-container" 
                         data-total-items="<?= $total_items_categoria ?>">
                        <div class="categoria-header p-3 rounded" style="background: #f8fafc;border: 1px solid var(--border);">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-folder-fill text-warning me-2 fs-5"></i>
                                    <div>
                                        <h5 class="mb-1 text-dark"><?= htmlspecialchars($categoria) ?></h5>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge bg-info"><?= $total_items_categoria ?> items</span>
                                    <span class="badge bg-success ms-2">$<?= number_format($total_monto_categoria, 2) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php foreach ($subcategorias as $subcategoria => $conceptos_sub): ?>
                            <?php
                            // Calcular total para esta subcategoría
                            $total_items_sub = array_sum(array_column($conceptos_sub, 'total_items'));
                            $total_monto_sub = array_sum(array_column($conceptos_sub, 'monto_total'));
                            ?>
                            
                            <div class="subcategoria-section ms-4 mt-3">
                                <?php if (count($subcategorias) > 1 || $subcategoria !== 'General'): ?>
                                    <div class="subcategoria-header p-2 rounded mb-2" style="background: #f8fafc;border: 1px solid var(--border);">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-folder2 text-info me-2"></i>
                                                <div>
                                                    <h6 class="mb-0 text-dark"><?= htmlspecialchars($subcategoria) ?></h6>
                                                </div>
                                            </div>
                                            <div>
                                                <small class="text-muted"><?= count($conceptos_sub) ?> conceptos</small>
                                                <span class="badge bg-info ms-2"><?= $total_items_sub ?> items</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="conceptos-container">
                                    <?php foreach ($conceptos_sub as $concepto): ?>
                                        <?php $tipoCodigo = getTipoConcepto($concepto['codigo_concepto']); ?>
                                        
                                        <?php if ($tipoCodigo === 'nivel2'): ?>
                                            <!-- TÍTULO NIVEL 2 -->
                                            <div class="subtitulo-nivel2 d-flex align-items-center px-2 py-2 mt-3 mb-1"
                                                 style="background:linear-gradient(90deg,#e8f4fd 0%,transparent 100%);border-left:3px solid #17a2b8;border-radius:0 4px 4px 0;">
                                                <i class="bi bi-folder2-open text-info me-2"></i>
                                                <span class="fw-semibold text-secondary" style="font-size:0.85rem;">
                                                    <?= htmlspecialchars($concepto['codigo_concepto']) ?>
                                                    <?php if ($concepto['nombre_concepto']): ?>
                                                        &nbsp;—&nbsp;<?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>

                                        <?php elseif ($tipoCodigo === 'nivel3'): ?>
                                            <!-- TÍTULO NIVEL 3 -->
                                            <div class="subtitulo-nivel3 d-flex align-items-center px-2 py-1 mt-2 mb-1 ms-3"
                                                 style="background:linear-gradient(90deg,#f0f9f0 0%,transparent 100%);border-left:2px solid #6c757d;border-radius:0 4px 4px 0;">
                                                <i class="bi bi-file-earmark-text text-secondary me-2" style="font-size:0.75rem;"></i>
                                                <span class="text-muted" style="font-size:0.8rem;">
                                                    <?= htmlspecialchars($concepto['codigo_concepto']) ?>
                                                    <?php if ($concepto['nombre_concepto']): ?>
                                                        &nbsp;—&nbsp;<?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>

                                        <?php else: ?>
                                            <!-- CONCEPTO NORMAL -->
                                            <div class="concepto-item card mb-2 border-0 shadow-sm" 
                                                 data-concepto-id="<?= $concepto['id'] ?>"
                                                 data-tiene-items="<?= $concepto['total_items'] > 0 ? 'si' : 'no' ?>">
                                                <div class="card-body <?= $concepto['total_items'] > 0 ? 'item-orden-pagada' : '' ?>">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-8">
                                                            <div class="d-flex align-items-start mb-2">
                                                                <div class="concepto-badge bg-primary text-white rounded px-2 py-1 me-3">
                                                                    <small class="fw-bold"><?= htmlspecialchars($concepto['codigo_concepto']) ?></small>
                                                                </div>
                                                                <?php if ($concepto['numero_original']): ?>
                                                                    <small class="text-muted align-self-center">
                                                                        #<?= htmlspecialchars($concepto['numero_original']) ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <h6 class="concepto-nombre mb-2 text-dark">
                                                                <?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                                            </h6>
                                                            <div class="concepto-meta d-flex align-items-center flex-wrap gap-1">
                                                                <?php endif; ?>
                                                            </div>
                                                            <h6 class="concepto-nombre mb-2 text-dark">
                                                                <?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                                            </h6>
                                                            <div class="concepto-meta d-flex align-items-center flex-wrap gap-1">
                                                                <?php if ($concepto['unidad_medida']): ?>
                                                                    <span class="badge bg-light text-dark me-2">
                                                                        <i class="bi bi-rulers me-1"></i>
                                                                        <?= htmlspecialchars($concepto['unidad_medida']) ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="concepto-extra mt-2 d-flex flex-wrap gap-2">
                                                                <?php if (!empty($concepto['cantidad'])): ?>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-sort-numeric-up me-1"></i>
                                                                        Cantidad: <strong class="text-dark"><?= number_format($concepto['cantidad'], 3) ?></strong>
                                                                    </small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($concepto['precio_unitario'])): ?>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-tag me-1"></i>
                                                                        P.U.: <strong class="text-dark">$<?= number_format($concepto['precio_unitario'], 2) ?></strong>
                                                                    </small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($concepto['importe'])): ?>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-currency-dollar me-1"></i>
                                                                        Importe: <strong class="text-dark">$<?= number_format($concepto['importe'], 2) ?></strong>
                                                                    </small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($concepto['fecha_inicio']) || !empty($concepto['fecha_fin'])): ?>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-calendar-range me-1"></i>
                                                                        <?= !empty($concepto['fecha_inicio']) ? date('d/m/Y', strtotime($concepto['fecha_inicio'])) : '—' ?>
                                                                        →
                                                                        <?= !empty($concepto['fecha_fin']) ? date('d/m/Y', strtotime($concepto['fecha_fin'])) : '—' ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div class="concepto-stats text-end">
                                                                    <div class="mb-2">
                                                                        <?php if ($concepto['total_items'] > 0): ?>
                                                                            <span class="badge bg-info">
                                                                                <i class="bi bi-box me-1"></i>
                                                                                <?= $concepto['total_items'] ?> items
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-secondary">
                                                                                <i class="bi bi-box me-1"></i>
                                                                                Sin items
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div>
                                                                        <span class="fs-6 fw-bold <?= $concepto['total_items'] > 0 ? 'text-success' : 'text-muted' ?>">
                                                                            $<?= number_format($concepto['monto_total'], 2) ?>
                                                                        </span>
                                                                        <?php if ($concepto['total_items'] > 0): ?>
                                                                            <div><small class="text-muted">Monto total</small></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="action-buttons ms-3">
                                                                    <div class="btn-group">
                                                                        <button class="btn-inf btn-sm" 
                                                                                onclick="verDetalleConceptoView(<?= $concepto['id'] ?>, '<?= htmlspecialchars(addslashes($concepto['codigo_concepto'])) ?>')"
                                                                                data-bs-toggle="tooltip" data-bs-placement="top" title="Ver Detalles">
                                                                            <i class="bi bi-info-circle"></i>
                                                                        </button>
                                                                        <button class="btn-del btn-sm" 
                                                                                onclick="eliminarConceptoView(<?= $concepto['id'] ?>, <?= $catalogo_id ?>, '<?= htmlspecialchars(addslashes($catalogo['nombre_catalogo'])) ?>', <?= $obra_id ?: 'null' ?>, '<?= $obra_info ? htmlspecialchars(addslashes($obra_info['nombre_obra'])) : '' ?>')"
                                                                                data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar Concepto">
                                                                            <i class="bi bi-trash3"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <!-- CONCEPTOS SIN CATEGORÍA -->
                <?php if (!empty($conceptos_sin_categoria)): ?>
                    <div class="sin-categoria-section mt-5">
                        <div class="categoria-header p-3 rounded" style="background: #f8fafc;border: 1px solid var(--border);">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-question-circle text-secondary me-2 fs-5"></i>
                                    <div>
                                        <h5 class="mb-1 text-dark">Conceptos Sin Categoría</h5>
                                        <small class="text-muted"><?= count($conceptos_sin_categoria) ?> concepto(s)</small>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $total_items_sin_cat = array_sum(array_column($conceptos_sin_categoria, 'total_items'));
                                    $total_monto_sin_cat = array_sum(array_column($conceptos_sin_categoria, 'monto_total'));
                                    ?>
                                    <span class="badge bg-info"><?= $total_items_sin_cat ?> items</span>
                                    <span class="badge bg-success ms-2">$<?= number_format($total_monto_sin_cat, 2) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="conceptos-container mt-3">
                            <?php foreach ($conceptos_sin_categoria as $concepto): ?>
                                <?php $tipoCodigo = getTipoConcepto($concepto['codigo_concepto']); ?>

                                <?php if ($tipoCodigo === 'nivel2'): ?>
                                    <!-- TÍTULO NIVEL 2 -->
                                    <div class="subtitulo-nivel2 d-flex align-items-center px-2 py-2 mt-3 mb-1"
                                         style="background:linear-gradient(90deg,#e8f4fd 0%,transparent 100%);border-left:3px solid #17a2b8;border-radius:0 4px 4px 0;">
                                        <i class="bi bi-folder2-open text-info me-2"></i>
                                        <span class="fw-semibold text-secondary" style="font-size:0.85rem;">
                                            <?= htmlspecialchars($concepto['codigo_concepto']) ?>
                                            <?php if ($concepto['nombre_concepto']): ?>
                                                &nbsp;—&nbsp;<?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                <?php elseif ($tipoCodigo === 'nivel3'): ?>
                                    <!-- TÍTULO NIVEL 3 -->
                                    <div class="subtitulo-nivel3 d-flex align-items-center px-2 py-1 mt-2 mb-1 ms-3"
                                         style="background:linear-gradient(90deg,#f0f9f0 0%,transparent 100%);border-left:2px solid #6c757d;border-radius:0 4px 4px 0;">
                                        <i class="bi bi-file-earmark-text text-secondary me-2" style="font-size:0.75rem;"></i>
                                        <span class="text-muted" style="font-size:0.8rem;">
                                            <?= htmlspecialchars($concepto['codigo_concepto']) ?>
                                            <?php if ($concepto['nombre_concepto']): ?>
                                                &nbsp;—&nbsp;<?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                <?php else: ?>
                                    <!-- CONCEPTO NORMAL -->
                                    <div class="concepto-item card mb-2 border-0 shadow-sm" 
                                         data-concepto-id="<?= $concepto['id'] ?>"
                                         data-tiene-items="<?= $concepto['total_items'] > 0 ? 'si' : 'no' ?>">
                                        <div class="card-body <?= $concepto['total_items'] > 0 ? 'item-orden-pagada' : '' ?>">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="d-flex align-items-start mb-2">
                                                        <div class="concepto-badge bg-secondary text-white rounded px-2 py-1 me-3">
                                                            <small class="fw-bold"><?= htmlspecialchars($concepto['codigo_concepto']) ?></small>
                                                        </div>
                                                        <?php if ($concepto['numero_original']): ?>
                                                            <small class="text-muted align-self-center">
                                                                #<?= htmlspecialchars($concepto['numero_original']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <h6 class="concepto-nombre mb-1 text-dark">
                                                        <?= htmlspecialchars($concepto['nombre_concepto']) ?>
                                                    </h6>
                                                    <?php if ($concepto['descripcion']): ?>
                                                        <p class="concepto-descripcion text-muted mb-2 small">
                                                            <?= htmlspecialchars($concepto['descripcion']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <div class="concepto-meta d-flex align-items-center flex-wrap gap-1">
                                                        <?php if ($concepto['unidad_medida']): ?>
                                                            <span class="badge bg-light text-dark me-2">
                                                                <i class="bi bi-rulers me-1"></i>
                                                                <?= htmlspecialchars($concepto['unidad_medida']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($concepto['total_items'] > 0): ?>
                                                            <span class="badge bg-success">
                                                                <i class="bi bi-box me-1"></i>
                                                                <?= $concepto['total_items'] ?> items asignados
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($tabla_ordenes_compra_existe): ?>
                                                            <?php
                                                            $sql_ordenes = "SELECT COUNT(*) as total_ordenes FROM ordenes_compra WHERE concepto_id = ? AND estado = 'pagado'";
                                                            $stmt_ordenes = $conn->prepare($sql_ordenes);
                                                            $stmt_ordenes->bind_param("i", $concepto['id']);
                                                            $stmt_ordenes->execute();
                                                            $result_ordenes = $stmt_ordenes->get_result();
                                                            $ordenes_data = $result_ordenes->fetch_assoc();
                                                            $total_ordenes_pagadas = $ordenes_data['total_ordenes'] ?? 0;
                                                            ?>
                                                            <?php if ($total_ordenes_pagadas > 0): ?>
                                                                <span class="badge badge-pagado status-badge">
                                                                    <i class="bi bi-cash-coin me-1"></i>
                                                                    <?= $total_ordenes_pagadas ?> OC pagadas
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="concepto-extra mt-2 d-flex flex-wrap gap-2">
                                                        <?php if (!empty($concepto['precio_unitario'])): ?>
                                                            <small class="text-muted">
                                                                <i class="bi bi-tag me-1"></i>
                                                                P.U.: <strong class="text-dark">$<?= number_format($concepto['precio_unitario'], 2) ?></strong>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if (!empty($concepto['importe'])): ?>
                                                            <small class="text-muted">
                                                                <i class="bi bi-currency-dollar me-1"></i>
                                                                Importe: <strong class="text-dark">$<?= number_format($concepto['importe'], 2) ?></strong>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if (!empty($concepto['fecha_inicio']) || !empty($concepto['fecha_fin'])): ?>
                                                            <small class="text-muted">
                                                                <i class="bi bi-calendar-range me-1"></i>
                                                                <?= !empty($concepto['fecha_inicio']) ? date('d/m/Y', strtotime($concepto['fecha_inicio'])) : '—' ?>
                                                                →
                                                                <?= !empty($concepto['fecha_fin']) ? date('d/m/Y', strtotime($concepto['fecha_fin'])) : '—' ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="concepto-stats text-end">
                                                            <div class="mb-2">
                                                                <?php if ($concepto['total_items'] > 0): ?>
                                                                    <span class="badge bg-info">
                                                                        <i class="bi bi-box me-1"></i>
                                                                        <?= $concepto['total_items'] ?> items
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">
                                                                        <i class="bi bi-box me-1"></i>
                                                                        Sin items
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <span class="fs-6 fw-bold <?= $concepto['total_items'] > 0 ? 'text-success' : 'text-muted' ?>">
                                                                    $<?= number_format($concepto['monto_total'], 2) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="action-buttons ms-3">
                                                            <div class="btn-group">
                                                                <button class="btn-inf btn-sm" 
                                                                        onclick="verDetalleConceptoView(<?= $concepto['id'] ?>, '<?= htmlspecialchars(addslashes($concepto['codigo_concepto'])) ?>')"
                                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Ver Detalles">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                <button class="btn-del btn-sm" 
                                                                        onclick="eliminarConceptoView(<?= $concepto['id'] ?>, <?= $catalogo_id ?>, '<?= htmlspecialchars(addslashes($catalogo['nombre_catalogo'])) ?>', <?= $obra_id ?: 'null' ?>, '<?= $obra_info ? htmlspecialchars(addslashes($obra_info['nombre_obra'])) : '' ?>')"
                                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar Concepto">
                                                                    <i class="bi bi-trash3"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">No hay conceptos registrados</h4>
                    <p class="text-muted">Comienza creando tu primer concepto o importando desde Excel</p>
                    <div class="mt-3">
                        <button class="btn btn-success" onclick="mostrarFormConcepto()">
                            <i class="bi bi-plus-circle"></i> Crear Primer Concepto
                        </button>
                        <button class="btn btn-inf" onclick="importarExcelConceptos()">
                            <i class="bi bi-upload"></i> Importar desde Excel
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL PARA AGREGAR ITEMS DESDE ÓRDENES PAGADAS -->
    <div class="modal fade" id="modalOrdenesPagadas" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-cash-coin me-2"></i>
                        <span id="modalTitulo">Órdenes de Compra Pagadas</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Mostrando solo órdenes con estado: <span class="text-success">PAGADO</span></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="searchOrden" class="form-label">Buscar órdenes:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchOrden" placeholder="Buscar por folio, proveedor o descripción...">
                            <button class="btn btn-outline-primary" type="button" onclick="buscarOrdenesPagadas()">
                                <i class="bi bi-search"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('searchOrden').value = ''; buscarOrdenesPagadas();">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="loadingOrdenes" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando órdenes pagadas...</span>
                        </div>
                        <p class="mt-2">Cargando órdenes pagadas...</p>
                    </div>
                    
                    <div id="listaOrdenesPagadas" class="d-none">
                        <!-- Las órdenes se cargarán aquí -->
                    </div>
                    
                    <div id="noOrdenesDisponibles" class="text-center py-4 d-none">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <h5 class="mt-3 text-muted">No hay órdenes pagadas disponibles</h5>
                        <p class="text-muted">No se encontraron órdenes de compra con estado "pagado" para este catálogo.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- FLOATING ACTION BUTTONS -->
    <div class="fab-container-backbtn">  
        <a onclick="history.back()" class="fab-button-backbtn">
            <i class="bi bi-arrow-left"></i>
            <span class="fab-tooltip-backbtn">Volver</span>
        </a>
    </div>

    <!-- HIDDEN INPUTS -->
    <input type="hidden" id="currentConceptoId" value="">
    <input type="hidden" id="currentCatalogoId" value="<?= $catalogo_id ?>">

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/scripts/catalogo-obras.js"></script>

    <script>
        // Variables globales para esta vista
        const catalogoId = <?= $catalogo_id ?>;
        const catalogoNombre = '<?= addslashes($catalogo['nombre_catalogo']) ?>';
        const obraId = <?= $obra_id ?: 'null' ?>;
        const obraNombre = '<?= $obra_info ? addslashes($obra_info['nombre_obra']) : '' ?>';
        const tablaOrdenesExiste = <?= $tabla_ordenes_compra_existe && $tabla_ordenes_items_existe ? 'true' : 'false' ?>;
        let currentConceptoId = 0;
        let modalSoloVer = false;

        // Función para filtrar conceptos
        function toggleFilter(tipo) {
            // Mostrar/ocultar conceptos según filtro
            const conceptos = document.querySelectorAll('.concepto-item');
            
            conceptos.forEach(concepto => {
                const tieneItems = concepto.getAttribute('data-tiene-items') === 'si';
                
                switch(tipo) {
                    case 'todos':
                        concepto.style.display = 'block';
                        break;
                    case 'conItems':
                        concepto.style.display = tieneItems ? 'block' : 'none';
                        break;
                    case 'sinItems':
                        concepto.style.display = !tieneItems ? 'block' : 'none';
                        break;
                }
            });
            
            // También mostrar/ocultar categorías vacías
            const categorias = document.querySelectorAll('.categoria-section');
            categorias.forEach(categoria => {
                const itemsVisibles = categoria.querySelectorAll('.concepto-item[style*="display: block"], .concepto-item:not([style*="display: none"])');
                const subcategorias = categoria.querySelectorAll('.subcategoria-section');
                
                let tieneConceptosVisibles = false;
                subcategorias.forEach(subcat => {
                    const itemsSubcat = subcat.querySelectorAll('.concepto-item[style*="display: block"], .concepto-item:not([style*="display: none"])');
                    subcat.style.display = itemsSubcat.length > 0 ? 'block' : 'none';
                    if (itemsSubcat.length > 0) tieneConceptosVisibles = true;
                });
                
                categoria.style.display = tieneConceptosVisibles ? 'block' : 'none';
            });
        }

        // Función para abrir modal de órdenes pagadas
        function agregarItemsDesdeOrdenesPagadas(conceptoId = 0, soloVer = false) {
            if (!tablaOrdenesExiste) {
                Swal.fire({
                    icon: 'info',
                    title: 'Módulo no disponible',
                    text: 'La tabla orden_compra_items no está configurada en el sistema.',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            currentConceptoId = conceptoId;
            document.getElementById('currentConceptoId').value = conceptoId;
            modalSoloVer = !!soloVer;
            
            // Actualizar título del modal
            const titulo = soloVer ? 'Órdenes de Compra Pagadas' : 'Agregar Items desde Órdenes Pagadas';
            document.getElementById('modalTitulo').textContent = titulo;
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalOrdenesPagadas'));
            modal.show();
            
            // Cargar órdenes pagadas
            cargarOrdenesPagadas();
        }

        // Función para cargar órdenes pagadas
        function cargarOrdenesPagadas(busqueda = '') {
            document.getElementById('loadingOrdenes').classList.remove('d-none');
            document.getElementById('listaOrdenesPagadas').classList.add('d-none');
            document.getElementById('noOrdenesDisponibles').classList.add('d-none');
            
            fetch(`/api/get_ordenes_pagadas.php?catalogo_id=${catalogoId}&busqueda=${encodeURIComponent(busqueda)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingOrdenes').classList.add('d-none');
                    
                    if (data.success && data.ordenes && data.ordenes.length > 0) {
                        mostrarOrdenesPagadas(data.ordenes);
                        document.getElementById('listaOrdenesPagadas').classList.remove('d-none');
                    } else {
                        document.getElementById('noOrdenesDisponibles').classList.remove('d-none');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loadingOrdenes').classList.add('d-none');
                    document.getElementById('listaOrdenesPagadas').innerHTML = 
                        '<div class="alert alert-danger">Error al cargar las órdenes pagadas: ' + error.message + '</div>';
                    document.getElementById('listaOrdenesPagadas').classList.remove('d-none');
                });
        }

        // Función para mostrar órdenes pagadas
        function mostrarOrdenesPagadas(ordenes) {
            let html = '';
            
            if (ordenes.length === 0) {
                html = '<div class="alert alert-info">No se encontraron órdenes pagadas para este catálogo</div>';
            } else {
                html += `<p class="text-muted mb-3">Mostrando ${ordenes.length} orden(es) pagada(s)</p>`;
                
                ordenes.forEach(orden => {
                    html += `
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <div>
                                <strong><i class="bi bi-receipt me-2"></i>${orden.folio}</strong>
                                <span class="ms-3"><i class="bi bi-building me-1"></i>${orden.proveedor_nombre || 'Sin proveedor'}</span>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    ${orden.fecha_pago_formatted || 'Sin fecha'}
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Descripción:</small>
                                    <p class="mb-2">${orden.descripcion || 'Sin descripción'}</p>
                                </div>
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Subtotal:</small>
                                            <p class="mb-0 fw-bold">$${parseFloat(orden.subtotal || 0).toFixed(2)}</p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Total:</small>
                                            <p class="mb-0 fw-bold text-success">$${parseFloat(orden.total || 0).toFixed(2)}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${orden.items && orden.items.length > 0 ? `
                            <div class="mt-3">
                                <h6 class="border-bottom pb-2"><i class="bi bi-cart me-2"></i>Items de la orden</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Descripción</th>
                                                <th class="text-center">Cantidad</th>
                                                <th class="text-center">Unidad</th>
                                                <th class="text-end">Precio Unitario</th>
                                                <th class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${orden.items.map(item => `
                                                <tr>
                                                    <td>${item.descripcion || ''}</td>
                                                    <td class="text-center">${item.cantidad || 0}</td>
                                                    <td class="text-center">${item.unidad_medida || 'N/A'}</td>
                                                    <td class="text-end">$${parseFloat(item.precio_unitario || 0).toFixed(2)}</td>
                                                    <td class="text-end fw-bold">$${parseFloat(item.subtotal || 0).toFixed(2)}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            ` : '<div class="alert alert-warning mt-3">Esta orden no tiene items registrados</div>'}
                        </div>
                    </div>`;
                });
            }
            
            document.getElementById('listaOrdenesPagadas').innerHTML = html;
        }

        // Función para buscar órdenes
        function buscarOrdenesPagadas() {
            const busqueda = document.getElementById('searchOrden').value;
            cargarOrdenesPagadas(busqueda);
        }

        // Función para verificar que las funciones estén cargadas
        function verificarFunciones() {
            console.log('Verificando funciones...');
            console.log('mostrarImportarExcelConceptos:', typeof mostrarImportarExcelConceptos);
            console.log('mostrarFormularioConcepto:', typeof mostrarFormularioConcepto);
            
            if (typeof mostrarImportarExcelConceptos === 'undefined') {
                console.error('Función mostrarImportarExcelConceptos no está disponible');
                return false;
            }
            if (typeof mostrarFormularioConcepto === 'undefined') {
                console.error('Función mostrarFormularioConcepto no está disponible');
                return false;
            }
            return true;
        }

        // Funciones específicas para esta vista
        function mostrarFormConcepto() {
            if (typeof mostrarFormularioConcepto === 'function') {
                mostrarFormularioConcepto(catalogoId, catalogoNombre, obraId, obraNombre);
            } else {
                Swal.fire('Error', 'Las funciones no están disponibles. Recarga la página.', 'error');
            }
        }

        function importarExcelConceptos() {
            if (typeof mostrarImportarExcelConceptos === 'function') {
                mostrarImportarExcelConceptos(catalogoId, catalogoNombre, obraId, obraNombre);
            } else {
                Swal.fire('Error', 'Las funciones no están disponibles. Recarga la página.', 'error');
                }
        }

        function eliminarConceptoView(conceptoId) {
            if (typeof eliminarConcepto === 'function') {
                eliminarConcepto(conceptoId, catalogoId, catalogoNombre, obraId, obraNombre);
            } else {
                console.error('Función eliminarConcepto no disponible');
            }
        }

        function verDetalleConceptoView(conceptoId, codigoClave) {
            if (typeof verDetalleConcepto === 'function') {
                verDetalleConcepto(conceptoId, codigoClave, catalogoId, catalogoNombre, obraId, obraNombre);
            } else {
                console.error('Función verDetalleConcepto no disponible');
            }
        }

        function verItemsConceptoView(conceptoId, conceptoNombre) {
            if (typeof verItemsConcepto === 'function') {
                verItemsConcepto(conceptoId, conceptoNombre, catalogoId, catalogoNombre);
            } else {
                console.error('Función verItemsConcepto no disponible');
            }
        }

        // Event listener para búsqueda con Enter
        document.getElementById('searchOrden')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                buscarOrdenesPagadas();
            }
        });

        // Cargar órdenes cuando se abre el modal
        document.getElementById('modalOrdenesPagadas')?.addEventListener('shown.bs.modal', function() {
            cargarOrdenesPagadas();
        });

        // Inicializar tooltips de Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Verificar funciones al cargar la página
            setTimeout(verificarFunciones, 1000);
        });
    </script>

    <?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>