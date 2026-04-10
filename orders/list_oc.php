<?php
require_once __DIR__ . '/../config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

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
if($where){
    $stmtTotal->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $por_pagina);

// Opciones de entidades para el filtro
$entidadesRes = $conn->query("SELECT id, nombre FROM entidades WHERE activo=1 ORDER BY nombre ASC");
$entidadesOptions = "";
while($ent = $entidadesRes->fetch_assoc()){
    $selected = $entidad_filtro == $ent['id'] ? "selected" : "";
    $entidadesOptions .= "<option value='{$ent['id']}' $selected>".htmlspecialchars($ent['nombre'])."</option>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Órdenes de Compra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
    <style>
        .table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            background: white;
            margin-bottom: 1rem;
        }

        .table {
            min-width: 800px;
            margin: 0;
            white-space: nowrap;
        }

        .table-container .table thead {
            background: var(--light-bg);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-container .table th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            background: var(--light-bg);
        }

        .table-container .table td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table-container .table tbody tr {
            border-bottom: 1px solid #e9ecef;
        }

        .table-container .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Estilos personalizados para la scrollbar */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }

        /* Para Firefox */
        .table-container {
            scrollbar-width: thin;
            scrollbar-color: var(--secondary-color) #f1f1f1;
        }

        @media (max-width: 768px) {
            .table-container {
                font-size: 0.9rem;
            }
            
            .table-container .table th,
            .table-container .table td {
                padding: 0.75rem 0.5rem;
            }
            
            .btn-group button {
                padding: 0.4rem;
                font-size: 0.8rem;
            }
            
            .btn-group i {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "". BASE_URL ."/includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <span>Registro de Órdenes de Compra</span>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Registro de Órdenes de Compra</h1>
        </div>
      </div>
      
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">

        <div class="form-container">
            <div class="form-body">
                <!-- Buscador -->
                <form class="form-search d-flex justify-content-center w-100 mb-5" method="GET">
                    <input type="hidden" name="estado" value="<?= htmlspecialchars($estado_filtro) ?>">
                    <input type="hidden" name="entidad" value="<?= htmlspecialchars($entidad_filtro) ?>">
                    <input class="form-control w-100" type="search" name="q" 
                           placeholder="Buscar por folio, entidad o proveedor..." 
                           value="<?= htmlspecialchars($busqueda) ?>" />
                    <button class="btn btn-outline-success" type="submit"> 
                        <i class="bi bi-search"></i> 
                    </button>
                </form>

                <!-- Filtros -->
                <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
                    
                    <div style="flex: 0 0 auto; min-width: 150px;">
                        <select name="estado" class="form-select" style="max-width:250px;">
                            <option value="">-- Todos los estados --</option>
                            <option value="revisado" <?= $estado_filtro=='revisado'?'selected':'' ?>>Revisado</option>
                            <option value="pendiente" <?= $estado_filtro=='pendiente'?'selected':'' ?>>Pendiente</option>
                            <option value="aprobado" <?= $estado_filtro=='aprobado'?'selected':'' ?>>Aprobado</option>
                            <option value="rechazado" <?= $estado_filtro=='rechazado'?'selected':'' ?>>Rechazado</option>
                            <option value="devuelto" <?= $estado_filtro=='devuelto'?'selected':'' ?>>Devuelto para editar</option>
                            <option value="pagado" <?= $estado_filtro=='pagado'?'selected':'' ?>>Pagado</option>
                        </select>
                    </div>
                    
                    <div style="flex: 0 0 auto; min-width: 150px;">
                        <select name="entidad" class="form-select" style="max-width:250px;">
                            <option value="">-- Todas las entidades --</option>
                            <?= $entidadesOptions ?>
                        </select>
                    </div>

                    <div style="flex: 0 0 auto;">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                    </div>
                </form>

                <!-- Botón de agregar OC -->
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge-num"><?= $totalRegistros ?> órdenes de compra</span>
                    <button class="button-56" type="button" onclick="window.location.href='new_order.php'">
                        <i class="bi bi-plus-circle"></i> Agregar
                    </button>
                </div>

                <!-- Mensaje de éxito -->
                <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <strong>¡Éxito!</strong> Orden de compra 
                    <?php if(isset($_GET['folio'])): ?>
                        <strong><?= htmlspecialchars($_GET['folio']) ?></strong>
                    <?php endif; ?>
                    creada correctamente.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Lista de órdenes de compra con scroll horizontal -->
                <div class="table-container">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Folio OC</th>
                                <th>Entidad</th>
                                <th>Requisición</th>
                                <th>Estado</th>
                                <th>Fecha de Solicitud</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($result && $result->num_rows > 0): ?>
                                <?php while($oc = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($oc['folio']) ?></td>
                                        <td><?= htmlspecialchars($oc['entidad']) ?></td>
                                        <td>
                                            <?php if($oc['folio_requisicion']): ?>
                                                <span><?= htmlspecialchars($oc['folio_requisicion']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                switch($oc['estado']){
                                                    case 'pendiente': 
                                                        echo '<span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pendiente</span>'; 
                                                        break;
                                                    case 'revisado': 
                                                        echo '<span class="badge bg-light text-dark"><i class="bi bi-check2-circle"></i> Revisado</span>'; 
                                                        break;
                                                    case 'aprobado': 
                                                        echo '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Aprobado</span>'; 
                                                        break;
                                                    case 'rechazado': 
                                                        echo '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rechazado</span>'; 
                                                        break;
                                                    case 'pagado': 
                                                        echo '<span class="badge bg-primary"><i class="bi bi-currency-dollar"></i> Pagado</span>'; 
                                                        break;
                                                    case 'devuelto': 
                                                        echo '<span class="badge bg-warning"></i> Devuelto para editar</span>'; 
                                                        break;
                                                    default: 
                                                        echo '<span class="badge bg-secondary">'.htmlspecialchars($oc['estado']).'</span>';
                                                }
                                            ?>
                                        </td>  
                                        <td><?= date('d/m/Y H:i', strtotime($oc['fecha_solicitud'])) ?></td>
                                        <td class="descripcion" title="<?= htmlspecialchars($oc['descripcion']) ?>">
                                                    <?= htmlspecialchars($oc['descripcion']) ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" style="gap:5px;">
                                                <button class="btn-inf" onclick="window.location.href='see_oc.php?id=<?= $oc['id'] ?>'" title="Ver detalles"
                                                data-bs-toggle="tooltip" data-bs-placement="top">
                                                    <i class="bi bi-info-circle"></i>
                                                </button>

                                                <?php if ($oc['estado'] == 'devuelto'): ?>
                                                <!-- Botón de descargar PDF -->
                                                    <button class="btn-ed" onclick="descargarPDF(<?= $oc['id'] ?>)" title="Descargar PDF"
                                                    data-bs-toggle="tooltip" data-bs-placement="top">
                                                    <i class="bi bi-download"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($oc['estado'] == 'pagado'): ?>
                                                <!-- Botón de descargar PDF -->
                                                    <button class="btn-download" onclick="descargarPDF(<?= $oc['id'] ?>)" title="Descargar PDF"
                                                    data-bs-toggle="tooltip" data-bs-placement="top">
                                                    <i class="bi bi-download"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                        <p class="mt-2">No hay órdenes de compra registradas</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación estilo catálogo -->
                <?php if($totalPaginas > 1): ?>
                <nav aria-label="Paginación">
                    <ul class="pagination justify-content-center mt-3">
                        <?php for($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                            <a class="page-link" 
                               href="?q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado_filtro) ?>&entidad=<?= urlencode($entidad_filtro) ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
// Inicializar tooltips de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Función para descargar PDF
    function descargarPDF(ocId) {
        // Descargar directamente
        window.open(`download_pdf_oc.php?id=${ocId}`, '_blank');
    }
    </script>

    <?php include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>