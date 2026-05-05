<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Requisición relacionada 
$requisicion_id = $_GET['requisicion_id'] ?? '';
$requisicion = null;
$archivos_requisicion = [];

// Inicializar variables para evitar warnings
$folio_requisicion = '';
$descripcion_requisicion = '';
$observaciones_requisicion = '';

if (!empty($requisicion_id)) {
  $sql_requis = "SELECT r.*, e.nombre AS entidad_nombre, c.nombre AS categoria_nombre
                   FROM requisiciones r
                   LEFT JOIN entidades e ON r.entidad_id = e.id
                   LEFT JOIN categorias c ON r.categoria_id = c.id
                   WHERE r.id = ?";
  $stmt = $conn->prepare($sql_requis);
  $stmt->bind_param("i", $requisicion_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $requisicion = $result->fetch_assoc();

  if ($requisicion) {
    $folio_requisicion = $requisicion['folio'] ?? '';
    $descripcion_requisicion = $requisicion['descripcion'] ?? '';
    $observaciones_requisicion = $requisicion['observaciones'] ?? '';

    // Obtener archivos de la requisición
    $sql_archivos = "SELECT id, nombre_archivo, ruta_archivo, tamaño_archivo, tipo_mime 
                         FROM requisicion_archivos 
                         WHERE requisicion_id = ?";
    $stmt_archivos = $conn->prepare($sql_archivos);
    $stmt_archivos->bind_param("i", $requisicion_id);
    $stmt_archivos->execute();
    $result_archivos = $stmt_archivos->get_result();
    while ($archivo = $result_archivos->fetch_assoc()) {
      $archivos_requisicion[] = $archivo;
    }
  }
}

// Obtener datos de entidad
$sql_entidades = "SELECT id, nombre FROM entidades ORDER BY nombre ASC";
$result_entidades = $conn->query($sql_entidades);

// Obtener datos de categoria
$sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre ASC";
$result_categorias = $conn->query($sql_categorias);

// Obtener datos de unidades
$sql_unidades = "SELECT id, nombre FROM unidades ORDER BY nombre ASC";
$result_unidades = $conn->query($sql_unidades);

// Obtener datos de proveedor
$sql_proveedores = "SELECT id, razon_social FROM proveedores ORDER BY razon_social DESC";
$result_proveedores = $conn->query($sql_proveedores);

// Obtener proyectos activos
$sql_proyectos = "SELECT id, nombre_proyecto, numero_contrato, monto_designado, costo_directo 
                  FROM proyectos 
                  WHERE fecha_fin >= CURDATE() 
                  ORDER BY nombre_proyecto ASC";
$result_proyectos = $conn->query($sql_proyectos);

// Obtener productos/servicios registrados
$sql_productos = "SELECT id, nombre, descripcion, tipo, proveedor_id 
                  FROM productos_servicios 
                  WHERE activo = 1 
                  ORDER BY nombre ASC";
$result_productos = $conn->query($sql_productos);

// Preparar array de productos para JavaScript
$productos_array = [];
if ($result_productos && $result_productos->num_rows > 0) {
  while ($producto = $result_productos->fetch_assoc()) {
    $productos_array[] = $producto;
  }
  // Reset pointer para usar nuevamente en el modal
  $result_productos->data_seek(0);
}

// Obtener items de la requisición si existe
$requisicion_items = [];
if (!empty($requisicion_id)) {
  $sql_items = "SELECT ri.*, ri.concepto_id, ps.nombre as producto_nombre, ps.tipo as producto_tipo, u.nombre as unidad_nombre 
                  FROM requisicion_items ri 
                  LEFT JOIN productos_servicios ps ON ri.producto_id = ps.id 
                  LEFT JOIN unidades u ON ri.unidad_id = u.id 
                  WHERE ri.requisicion_id = ?";
  $stmt_items = $conn->prepare($sql_items);
  $stmt_items->bind_param("i", $requisicion_id);
  $stmt_items->execute();
  $result_items = $stmt_items->get_result();

  while ($item = $result_items->fetch_assoc()) {
    $requisicion_items[] = $item;
  }

  // DEBUG: Registrar en consola qué se está obteniendo
  error_log("DEBUG - Items de requisición para ID $requisicion_id: " . count($requisicion_items));
  foreach ($requisicion_items as $index => $item) {
    error_log("DEBUG - Item $index: " .
      "ID: {$item['id']}, " .
      "Producto: {$item['producto_nombre']}, " .
      "Concepto ID: " . ($item['concepto_id'] ?? 'NULL') . ", " .
      "Unidad: {$item['unidad_nombre']}");
  }
}

// Preparar opciones de unidades para JavaScript
$unidad_options = '';
if ($result_unidades && $result_unidades->num_rows > 0) {
  while ($row = $result_unidades->fetch_assoc()) {
    $unidad_options .= '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nueva Orden de Compra</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous" />
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/new_order.css" />
  <style>
    .presupuesto-info {
      background: #f8f9fa;
      border-radius: 5px;
      padding: 10px;
      margin-top: 5px;
      font-size: 0.85em;
    }

    .presupuesto-alert {
      padding: 8px 12px;
      border-radius: 4px;
      margin-top: 5px;
    }

    .progress {
      height: 6px;
      margin-top: 5px;
    }

    .btn-sm {
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
    }

    .producto-autocomplete {
      position: relative;
    }

    .autocomplete-list {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-top: none;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
    }

    .autocomplete-item {
      padding: 8px 12px;
      cursor: pointer;
      border-bottom: 1px solid #eee;
    }

    .autocomplete-item:hover {
      background: #f8f9fa;
    }

    .autocomplete-item:last-child {
      border-bottom: none;
    }
  </style>
</head>

<body>

  <?php
  include __DIR__ . "/../includes/navbar.php"; ?>

  <!-- HERO SECTION -->
  <div class="hero-section">
    <div class="container hero-content">
      <div class="breadcrumb-custom">
        <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
        <span>/</span>
        <a href="<?= BASE_URL ?>/orders/list_oc.php">Registro de Órdenes de Compra</a>
        <span>/</span>
        <span>Nueva Orden de Compra</span>
      </div>

      <div class="row align-items-end">
        <div class="col-lg-8">
          <h1 class="hero-title">Nueva Orden de Compra</h1>
        </div>
      </div>

    </div>
  </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content-wrapper">

    <div class="form-container">

      <!-- Form Body -->
      <div class="form-body">

        <form id="ordenCompraForm" method="POST" action="save_orden.php" enctype="multipart/form-data">
          <!-- Información General -->
          <div>
            <p>
              Este formulario debe ser completado por el personal autorizado
              para solicitar la compra de bienes o servicios, o para requerir
              el pago de facturas y compromisos adquiridos por la
              organización. <br />
              <b>Importante:</b> El envío de este formulario no garantiza la
              aprobación automática del pago o compra. Asegúrese de cumplir
              con los procedimientos y tiempos establecidos por la
              organización.
            </p>
          </div>
          <div class="section-title">
            <i class="bi bi-info-circle"></i>
            Información General
          </div>

          <!-- Requisición Relacionada -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Requisición Relacionada</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($folio_requisicion) ?>" readonly />
                <input type="hidden" name="requisicion_id" value="<?= $requisicion_id ?>">
              </div>
            </div>

            <!-- Num de orden -->
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Número de Orden <span class="required">*</span></label>
                <input
                  type="text"
                  class="form-control"
                  id="numeroOrden"
                  name="numero_orden"
                  readonly />
              </div>
            </div>
          </div>

          <!-- Fecha -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">
                  Fecha de Solicitud <span class="required">*</span>
                </label>
                <input type="datetime-local"
                  class="form-control"
                  id="fecha_solicitud"
                  name="fecha_solicitud"
                  readonly>
              </div>
            </div>

            <!-- Solicitante -->
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">
                  Solicitante <span class="required">*</span>
                </label>
                <input
                  type="text"
                  class="form-control"
                  id="solicitante"
                  name="solicitante"
                  placeholder="Nombre de quien realiza la orden de compra"
                  required
                  value="<?php
                          echo htmlspecialchars($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']); ?>"
                  readonly />
                <input type="hidden" name="solicitante_id" value="<?= $_SESSION['user_id'] ?>">
              </div>
            </div>
          </div>

          <!-- Entidad / Proyecto -->
          <div class="row">

            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">
                  Entidad <span class="required">*</span>
                </label>

                <select class="form-select" id="entidad" name="entidad" required>
                  <option value="">Seleccionar Entidad</option>

                  <?php
                  if ($result_entidades && $result_entidades->num_rows > 0) {
                    while ($row = $result_entidades->fetch_assoc()) {

                      $selected = ($requisicion && $requisicion['entidad_id'] == $row['id']) ? "selected" : "";

                      echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>'
                        . htmlspecialchars($row['nombre']) .
                        '</option>';
                    }
                  }
                  ?>
                </select>

              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">
                  Proyecto <span class="required">*</span>
                </label>

                <select class="form-select"
                  id="proyecto"
                  name="proyecto"
                  required
                  onchange="cargarObras()">

                  <option value="">Seleccionar Proyecto</option>

                  <?php
                  if ($result_proyectos && $result_proyectos->num_rows > 0) {

                    while ($row = $result_proyectos->fetch_assoc()) {

                      $selected = ($requisicion && $requisicion['proyecto_id'] == $row['id']) ? "selected" : "";

                      echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>'
                        . htmlspecialchars($row['nombre_proyecto']) . ' - '
                        . htmlspecialchars($row['numero_contrato']) .
                        '</option>';
                    }
                  } else {

                    echo '<option value="">No hay proyectos disponibles</option>';
                  }
                  ?>
                </select>

              </div>
            </div>

          </div>

          <!-- Obra / Catálogo -->
          <div class="row">

            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Obra</label>

                <select class="form-select"
                  id="obra"
                  name="obra"
                  onchange="cargarCatalogos()">

                  <option value="">-- Sin obra específica --</option>
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Catálogo</label>

                <select class="form-select"
                  id="catalogo"
                  name="catalogo"
                  onchange="cargarConceptosEnItems()">

                  <option value="">-- Sin catálogo específico --</option>
                </select>
              </div>
            </div>

          </div>

          <!-- Categoría / Proveedor -->
          <div class="row">

            <div class="col-md-6">
              <div class="form-group">

                <label class="form-label">
                  Categoría <span class="required">*</span>
                </label>
                <select class="form-select"
                  id="categoria"
                  name="categoria"
                  required
                  onchange="handleCategoriaChange()">
                  <option value="">Seleccionar Categoría</option>
                  <?php
                  if ($result_categorias && $result_categorias->num_rows > 0) {
                    while ($row = $result_categorias->fetch_assoc()) {
                      $selected = ($requisicion && $requisicion['categoria_id'] == $row['id']) ? "selected" : "";
                      echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>'
                        . htmlspecialchars($row['nombre']) .
                        '</option>';
                    }
                  }
                  ?>
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">
                Proveedor <span class="required">*</span>
              </label>
              <select class="form-select" id="proveedor" name="proveedor" required>
                <option value="">Seleccionar proveedor</option>
                <?php
                if ($result_proveedores && $result_proveedores->num_rows > 0) {
                  while ($row = $result_proveedores->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($row['id']) . '">'
                      . htmlspecialchars($row['razon_social']) .
                      '</option>';
                  }
                }
                ?>
              </select>
              <small class="text-muted d-block mt-1">
                <i class="bi bi-info-circle"></i>
                En caso de seleccionar un subcontrato, este valor se seleccionará automáticamente.
              </small>
            </div>
          </div>

          <!-- Contenedor de Subcontrato (para mostrar/ocultar todo el bloque) -->
          <div id="subcontratoContainer" style="display: none;">
            <!-- Selector de Subcontrato -->
            <div class="row mt-3">
              <div class="col-md-12">
                <div class="form-group">
                  <label class="form-label">
                    Seleccionar Subcontrato <span class="required">*</span>
                  </label>
                  <select class="form-select" id="subcontrato" name="subcontrato">
                    <option value="">-- Primero seleccione una obra --</option>
                  </select>
                  <small class="text-muted d-block mt-1">
                    <i class="bi bi-info-circle"></i>
                    Seleccione el subcontrato que se utilizará para esta orden de compra.
                  </small>
                </div>
              </div>
            </div>

            <!-- Panel informativo del subcontrato -->
            <div id="infoSubcontrato" style="display:none">
              <div class="row g-3 mt-1">
                <label class="form-label">
                  Presupuesto de Subcontrato
                </label>
                <div class="col-6 col-md-3">
                  <div class="budget-stat p-3 bg-light rounded shadow-sm">
                    <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Total contrato</div>
                    <div class="budget-stat-value h5 fw-bold text-success mb-0" id="subcontratoTotal">$0.00</div>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="budget-stat p-3 bg-light rounded shadow-sm">
                    <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Pagado real</div>
                    <div class="budget-stat-value h5 fw-bold text-primary mb-0" id="subcontratoUtilizado">$0.00</div>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="budget-stat p-3 bg-light rounded shadow-sm">
                    <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Comprometido</div>
                    <div class="budget-stat-value h5 fw-bold text-warning mb-0" id="subcontratoComprometido">$0.00</div>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="budget-stat p-3 bg-light rounded shadow-sm">
                    <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Disponible</div>
                    <div class="budget-stat-value h5 fw-bold text-info mb-0" id="subcontratoDisponible">$0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Panel obra -->
          <div id="infoObra" style="display:none">
            <div class="row g-3 mt-1">
              <label class="form-label">
                Presupuesto de Obra
              </label>
              <div class="col-6 col-md-3">
                <div class="budget-stat p-3 bg-light rounded shadow-sm">
                  <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Costo directo obra</div>
                  <div class="budget-stat-value h5 fw-bold text-dark mb-0" id="montoObra">$0.00</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="budget-stat p-3 bg-light rounded shadow-sm">
                  <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Total contratos (subcontratos)</div>
                  <div class="budget-stat-value h5 fw-bold text-primary mb-0" id="contratosObra">$0.00</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="budget-stat p-3 bg-light rounded shadow-sm">
                  <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Comprometido (OC activas)</div>
                  <div class="budget-stat-value h5 fw-bold text-warning mb-0" id="comprometidoObra">$0.00</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="budget-stat p-3 bg-light rounded shadow-sm">
                  <div class="budget-stat-label text-muted small text-uppercase fw-semibold">Disponible obra</div>
                  <div class="budget-stat-value h5 fw-bold text-success mb-0" id="disponibleObra">$0.00</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Items de la Orden -->
          <div class="section-title">
            <i class="bi bi-list-ul"></i>
            Items de la Orden
          </div>

          <div class="items-table">
            <table class="table" id="itemsTable">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 30%">Descripción</th>
                  <th style="width: 10%">Cantidad</th>
                  <th style="width: 12%">Unidad</th>
                  <th style="width: 15%">Concepto</th>
                  <th style="width: 14%">Precio Unit.</th>
                  <th style="width: 15%">Subtotal</th>
                  <th style="width: 10%">Acción</th>
                </tr>
              </thead>
              <tbody>
                <!-- Las filas se agregarán dinámicamente desde el catálogo -->
              </tbody>
            </table>
          </div>

          <div class="text-end mt-3">
            <button type="button" class="button-56" onclick="mostrarCatalogoProductos()">
              <i class="bi bi-plus-circle"></i> Agregar Item
            </button>
          </div>

          <!-- Totales -->
          <div class="total-section">
            <div class="total-row">
              <span>Subtotal:</span>
              <span id="subtotalGeneral">$0.00</span>
            </div>
            <div class="total-row">
              <span>
                <select class="form-select" id="iva" name="iva" style="margin-right: 20px" onchange="calcularIVA()">
                  <option value="0" selected>Sin IVA</option>
                  <option value="8">8%</option>
                  <option value="16">16%</option>
                </select>
              </span>
              <span id="ivaTotal">$0.00</span>
            </div>
            <div class="total-row final">
              <span>TOTAL:</span>
              <span id="totalGeneral">$0.00</span>
            </div>
          </div>

          <!-- Información del Presupuesto -->
          <div id="alertPresupuesto" class="presupuesto-alert" style="display: none;"></div>

          <!-- Descripción -->
          <div class="section-title">
            <i class="bi bi-chat-text"></i>
            Descripción
          </div>

          <div class="form-group">
            <label class="form-label">Describa de forma general y clara el bien o servicio que se
              requiere, indicando su uso o finalidad, la cantidad aproximada y
              cualquier detalle relevante que facilite su identificación o
              cotización.</label>
            <textarea
              class="form-control"
              id="descripcion"
              name="descripcion_general"
              rows="3"
              placeholder="Ingrese descripción general del bien o servicio..."><?= htmlspecialchars($descripcion_requisicion) ?></textarea>
          </div>

          <!-- Observaciones -->
          <div class="section-title">
            <i class="bi bi-chat-text"></i>
            Observaciones
          </div>

          <div class="form-group">
            <label class="form-label">Utilice este espacio para anotar detalles importantes, como
              requisitos de empaque, condiciones de pago acordadas, contacto
              para entrega o cualquier otra observación que deba tenerse en
              cuenta para procesar esta orden.</label>
            <textarea
              class="form-control"
              id="observaciones"
              name="observaciones"
              rows="3"
              placeholder="Ingrese observaciones o comentarios adicionales..."><?= htmlspecialchars($observaciones_requisicion) ?></textarea>
          </div>

          <!-- Archivos - Agregar de uno en uno -->
          <div class="section-title"><i class="bi bi-paperclip"></i> Adjuntar Archivos</div>

          <div class="form-group">
            <small class="text-muted d-block mt-2 mb-4">
              <i class="bi bi-info-circle"></i>
              Cargue hasta 5 archivos seleccionándolos de uno en uno.
              Formatos permitidos: PDF, Word, Excel, imágenes. Tamaño máximo por archivo: 10 MB.
            </small>

            <!-- Input visible para seleccionar archivos de uno en uno -->
            <div class="input-group">
              <input
                type="file"
                class="form-control"
                id="singleFileInput"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
              <button class="btn btn-primary" type="button" onclick="agregarArchivo()" style="background: #113456; transform: none;">
                <i class="bi bi-plus-circle"></i> Agregar
              </button>
            </div>

          </div>

          <!-- Lista de archivos acumulados -->
          <div id="archivosContainer" class="mt-3">
            <h6 class="mb-2">Archivos seleccionados: <span id="contadorArchivos">0</span></h6>
            <ul id="fileList" class="list-group">
              <!-- Mensaje inicial que siempre se muestra -->
              <li class="list-group-item text-center text-muted">
                <i class="bi bi-inbox"></i> No hay archivos agregados
              </li>
            </ul>
          </div>

          <!-- Archivos heredados de la requisición (si existe) -->
          <?php
          if (!empty($archivos_requisicion)): ?>
            <div class="mt-4">
              <div class="section-title"><i class="bi bi-files"></i> Archivos de la Requisición</div>

              <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Los siguientes archivos provienen de la requisición.
                Puede eliminar los que no necesite para esta orden de compra.
              </div>

              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 40%">Nombre</th>
                    <th style="width: 15%">Tamaño</th>
                    <th style="width: 20%">Tipo</th>
                    <th style="width: 20%">Acción</th>
                  </tr>
                </thead>
                <tbody id="tablaArchivos">
                  <?php
                  $i = 1;
                  foreach ($archivos_requisicion as $archivo): ?>
                    <tr data-archivo-id="<?= $archivo['id'] ?>">
                      <td><?= $i++ ?></td>
                      <td>
                        <i class="bi bi-file-earmark-text"></i>
                        <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                      </td>
                      <td><?= round($archivo['tamaño_archivo'] / 1024, 2) ?> KB</td>
                      <td><?= htmlspecialchars($archivo['tipo_mime']) ?></td>
                      <td>
                        <button type="button"
                          class="btn btn-sm btn-outline-info"
                          onclick="verArchivo(<?= $archivo['id'] ?>, '<?= htmlspecialchars($archivo['tipo_mime']) ?>')"
                          title="Ver archivo">
                          <i class="bi bi-eye"></i> Ver
                        </button>
                        <button type="button" class="btn btn-sm btn-danger"
                          onclick="eliminarArchivoTemporal(<?= $archivo['id'] ?>, this)">
                          <i class="bi bi-trash"></i> Eliminar
                        </button>
                      </td>
                    </tr>
                  <?php
                  endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php
          endif; ?>

          <!-- Guardar -->
          <div class="form-actions mt-3">

            <!-- Contenedor de alertas -->
            <div id="alertContainer" class="mt-2"></div>

            <div class="send-otxt">Esta orden de compra será evaluada y pagada por el Subdirector General y Gerente de Recursos Humanos.</div>
            <div class="container overflow-hidden text-center">
              <div class="row gx-5">
                <div class="col">
                  <div class="p-3">
                    <button type="submit" class="button-57" id="btnEnviar"><i class="bi bi-floppy"></i> Guardar</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>



  <!-- Boton de regreso -->
  <div class="fab-container-backbtn">
    <a onclick="history.back()" class="fab-button-backbtn gray">
      <i class="bi bi-arrow-left"></i>
      <span class="fab-tooltip-backbtn">Volver</span>
    </a>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>

  <!-- JavaScript Externo -->
  <script src="<?= BASE_URL ?>/assets/scripts/new_order.js"></script>

  <!-- Configuración e Inicialización -->
  <script>
    // Configuración desde PHP
    const config = {
      productosCatalogo: <?= json_encode($productos_array) ?>,
      unidadOptions: '<?= $unidad_options ?>',
      requisicionItems: <?= json_encode($requisicion_items) ?>,
      requisicionId: <?= !empty($requisicion_id) ? $requisicion_id : 'null' ?>,
      entidadId: <?= !empty($requisicion['entidad_id']) ? $requisicion['entidad_id'] : 'null' ?>,
      requisicion: <?= !empty($requisicion) ? json_encode([
                      'proyecto_id' => $requisicion['proyecto_id'] ?? null,
                      'obra_id' => $requisicion['obra_id'] ?? null,
                      'catalogo_id' => $requisicion['catalogo_id'] ?? null
                    ]) : 'null' ?>
    };

    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
      initNewOrder(config);
    });
  </script>

  <script>
    function verArchivo(archivoId, tipoMime) {
      const tiposVisualizables = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

      if (tiposVisualizables.includes(tipoMime)) {
        window.open('/orders/view_archivo.php?id=' + archivoId, '_blank');
      } else {
        UI.toast.info('Este tipo de archivo no se puede visualizar en el navegador. Se descargará automáticamente.');
        window.open('/orders/download_archivo.php?id=' + archivoId, '_blank');
      }
    }
  </script>

  <?php
  include __DIR__ . "/../includes/footer.php"; ?>

</body>

</html>