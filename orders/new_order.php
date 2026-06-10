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



<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Nueva Orden de Compra | GECO PROATAM</title>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="<?= BASE_URL ?>/orders/list_oc.php">Órdenes de Compra</a>
        <span class="separator">›</span>
        <span>Nueva Orden de Compra</span>
      </nav>
      <h1 class="orders-page-title">Nueva Orden de Compra</h1>
    </div>
    <button type="button" class="btn-geco-outline" onclick="history.back()">
      <i class="fa-solid fa-arrow-left"></i> Volver
    </button>
  </div>

  <form id="ordenCompraForm" method="POST" action="save_orden.php" enctype="multipart/form-data">

    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-circle-info"></i> Información General de la Orden</span>
      </div>
      <div class="oc-card-body">
        <p class="oc-card-intro">Complete los datos generales de la orden. Los campos marcados con <span class="required">*</span> son obligatorios.</p>

        <div class="orders-alert orders-alert--info mb-4">
          <i class="fa-solid fa-circle-info"></i>
          <div class="orders-alert__body">
            <p class="m-0">Este formulario debe ser completado por el personal autorizado para solicitar compras o requerir pagos.</p>
            <span class="mt-1 d-block"><strong>Importante:</strong> El envío no garantiza aprobación automática. Siga los procedimientos establecidos.</span>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Requisición Relacionada</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($folio_requisicion) ?>" readonly />
            <input type="hidden" name="requisicion_id" value="<?= $requisicion_id ?>">
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Número de Orden <span class="required">*</span></label>
            <input type="text" class="form-control" id="numeroOrden" name="numero_orden" readonly />
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Fecha de Solicitud <span class="required">*</span></label>
            <input type="datetime-local" class="form-control" id="fecha_solicitud" name="fecha_solicitud" readonly>
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Solicitante <span class="required">*</span></label>
            <input type="text" class="form-control" id="solicitante" name="solicitante" value="<?= htmlspecialchars($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']) ?>" readonly />
            <input type="hidden" name="solicitante_id" value="<?= $_SESSION['user_id'] ?>">
          </div>
          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Entidad <span class="required">*</span></label>
            <select class="form-select" id="entidad" name="entidad" required>
              <option value="">Seleccionar Entidad</option>
              <?php
              if ($result_entidades && $result_entidades->num_rows > 0) {
                while ($row = $result_entidades->fetch_assoc()) {
                  $selected = ($requisicion && $requisicion['entidad_id'] == $row['id']) ? "selected" : "";
                  echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>' . htmlspecialchars($row['nombre']) . '</option>';
                }
              }
              ?>
            </select>
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Proyecto <span class="required">*</span></label>
            <select class="form-select" id="proyecto" name="proyecto" required onchange="cargarObras()">
              <option value="">Seleccionar Proyecto</option>
              <?php
              if ($result_proyectos && $result_proyectos->num_rows > 0) {
                while ($row = $result_proyectos->fetch_assoc()) {
                  $selected = ($requisicion && $requisicion['proyecto_id'] == $row['id']) ? "selected" : "";
                  echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>' . htmlspecialchars($row['nombre_proyecto']) . ' - ' . htmlspecialchars($row['numero_contrato']) . '</option>';
                }
              } else {
                echo '<option value="">No hay proyectos disponibles</option>';
              }
              ?>
            </select>
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Obra</label>
            <select class="form-select" id="obra" name="obra" onchange="cargarCatalogos()">
              <option value="">-- Sin obra específica --</option>
            </select>
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Catálogo</label>
            <select class="form-select" id="catalogo" name="catalogo" onchange="cargarConceptosEnItems()">
              <option value="">-- Sin catálogo específico --</option>
            </select>
          </div>
          <div class="col-md-6 col-lg-6">
            <label class="oc-form-label">Categoría <span class="required">*</span></label>
            <select class="form-select" id="categoria" name="categoria" required onchange="handleCategoriaChange()">
              <option value="">Seleccionar Categoría</option>
              <?php
              if ($result_categorias && $result_categorias->num_rows > 0) {
                while ($row = $result_categorias->fetch_assoc()) {
                  $selected = ($requisicion && $requisicion['categoria_id'] == $row['id']) ? "selected" : "";
                  echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>' . htmlspecialchars($row['nombre']) . '</option>';
                }
              }
              ?>
            </select>
          </div>

          <div class="col-md-6 col-lg-6">
            <label class="oc-form-label">Proveedor <span class="required">*</span></label>
            <select class="form-select" id="proveedor" name="proveedor" required>
              <option value="">Seleccionar proveedor</option>
              <?php
              if ($result_proveedores && $result_proveedores->num_rows > 0) {
                while ($row = $result_proveedores->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['razon_social']) . '</option>';
                }
              }
              ?>
            </select>
            <small class="oc-form-hint"><i class="fa-solid fa-circle-info"></i> En caso de seleccionar un subcontrato, este valor se autocompletará.</small>
          </div>
        </div>

        <div id="subcontratoContainer" class="oc-form-subsection" style="display: none;">
          <div class="oc-form-subsection__title"><i class="fa-regular fa-file-lines"></i> Subcontrato Asignado</div>
          <div class="row g-3">
            <div class="col-12">
              <label class="oc-form-label">Seleccionar Subcontrato <span class="required">*</span></label>
              <select class="form-select" id="subcontrato" name="subcontrato">
                <option value="">-- Primero seleccione una obra --</option>
              </select>
            </div>
          </div>

          <div id="infoSubcontrato" style="display:none" class="mt-3">
            <label class="oc-form-label mb-2">Presupuesto de Subcontrato</label>
            <div class="oc-stat-grid">
              <div class="oc-stat-box">
                <div class="oc-stat-box__label">Total contrato</div>
                <p class="oc-stat-box__value text-success" id="subcontratoTotal">$0.00</p>
              </div>
              <div class="oc-stat-box">
                <div class="oc-stat-box__label">Pagado real</div>
                <p class="oc-stat-box__value text-primary" id="subcontratoUtilizado">$0.00</p>
              </div>
              <div class="oc-stat-box">
                <div class="oc-stat-box__label">Comprometido</div>
                <p class="oc-stat-box__value text-warning" id="subcontratoComprometido">$0.00</p>
              </div>
              <div class="oc-stat-box">
                <div class="oc-stat-box__label">Disponible</div>
                <p class="oc-stat-box__value text-info" id="subcontratoDisponible">$0.00</p>
              </div>
            </div>
          </div>
        </div>

        <div id="infoObra" class="oc-form-subsection" style="display:none">
          <div class="oc-form-subsection__title"><i class="fa-solid fa-wallet"></i> Presupuesto de Obra</div>
          <div class="oc-stat-grid">
            <div class="oc-stat-box">
              <div class="oc-stat-box__label">Costo directo obra</div>
              <p class="oc-stat-box__value" id="montoObra">$0.00</p>
            </div>
            <div class="oc-stat-box">
              <div class="oc-stat-box__label">Total contratos</div>
              <p class="oc-stat-box__value text-primary" id="contratosObra">$0.00</p>
            </div>
            <div class="oc-stat-box">
              <div class="oc-stat-box__label">Comprometido (OC)</div>
              <p class="oc-stat-box__value text-warning" id="comprometidoObra">$0.00</p>
            </div>
            <div class="oc-stat-box">
              <div class="oc-stat-box__label">Disponible obra</div>
              <p class="oc-stat-box__value text-success" id="disponibleObra">$0.00</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ─── CARD: PARTIDAS E ÍTEMS ──────────────────────────────── -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-list"></i> Partidas e Ítems</span>
        <button type="button" class="btn-geco-outline btn-geco-outline--sm" onclick="mostrarCatalogoProductos()">
          <i class="fa-solid fa-circle-plus"></i> Agregar Ítem
        </button>
      </div>
      <div class="oc-card-body oc-card-body--items">
        <div class="orders-table-wrap orders-table-wrap--items">
          <table class="orders-table orders-table--items" id="itemsTable">
            <thead>
              <tr>
                <th style="width: 5%">#</th>
                <th style="width: 30%">Descripción</th>
                <th style="width: 10%">Cantidad</th>
                <th style="width: 12%">Unidad</th>
                <th style="width: 15%">Concepto</th>
                <th style="width: 14%">Precio Unit.</th>
                <th style="width: 15%">Subtotal</th>
                <th style="width: 10%" class="text-center">Acción</th>
              </tr>
            </thead>
            <tbody>
              <!-- Las filas se agregarán dinámicamente -->
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <div id="alertPresupuesto" class="orders-alert orders-alert--warning" style="display: none;"></div>

    <div class="oc-form-layout">
      <div class="oc-form-layout-main">
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-regular fa-comments"></i> Detalle, Descripciones y Observaciones</span>
          </div>
          <div class="oc-card-body">
            <div class="mb-4">
              <label class="oc-form-label" for="descripcion">Descripción General</label>
              <p class="oc-form-hint--block">Describa de forma general y clara el bien o servicio que se requiere, indicando su uso o finalidad y cantidad aproximada.</p>
              <textarea class="form-control" id="descripcion" name="descripcion_general" rows="4" placeholder="Ingrese descripción general del bien o servicio..."><?= htmlspecialchars($descripcion_requisicion) ?></textarea>
            </div>
            <div>
              <label class="oc-form-label">Observaciones Adicionales</label>
              <p class="oc-form-hint--block">Anote detalles importantes: requisitos de empaque, condiciones de pago, contacto para entrega u observaciones a considerar.</p>
              <textarea class="form-control" id="observaciones" name="observaciones" rows="4" placeholder="Ingrese observaciones o comentarios adicionales..."><?= htmlspecialchars($observaciones_requisicion) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="oc-form-layout-side">
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-solid fa-paperclip"></i> Archivos Adjuntos</span>
          </div>
          <div class="oc-card-body">
            <div class="orders-alert orders-alert--info mb-3">
              <i class="fa-solid fa-circle-info"></i>
              <span>Cargue hasta 5 archivos de uno en uno. PDF, Word, Excel, imágenes. Máx. 10 MB por archivo.</span>
            </div>
            <div class="oc-files-input-group mb-3">
              <input type="file" class="form-control" id="singleFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
              <button class="btn-geco-secondary" type="button" onclick="agregarArchivo()">
                <i class="fa-solid fa-upload"></i> Subir
              </button>
            </div>
            <div id="archivosContainer" class="oc-files-dropzone">
              <h6 class="oc-files-dropzone__title">Archivos seleccionados: <span id="contadorArchivos" class="badge bg-secondary">0</span></h6>
              <ul id="fileList" class="list-group list-group-flush mt-2">
                <li id="emptyFilesState" class="list-group-item border-0 p-0">
                  <div class="text-center text-muted p-4">
                    <i class="fa-solid fa-inbox fs-4 d-block mb-1 opacity-50"></i>
                    <span class="small">Sin archivos adjuntos</span>
                  </div>
                </li>
              </ul>
            </div>

            <?php if (!empty($archivos_requisicion)): ?>
              <div class="mt-4 pt-3" style="border-top:1px solid var(--gray-100,#f3f4f6);">
                <h6 class="oc-form-label mb-2"><i class="fa-regular fa-folder-open"></i> Archivos de la Requisición</h6>
                <div class="orders-alert orders-alert--warning mb-3">
                  <i class="fa-solid fa-triangle-exclamation"></i>
                  <span>Los siguientes archivos provienen de la requisición. Puede eliminar los que no necesite.</span>
                </div>
                <div class="orders-table-wrap">
                  <table class="orders-table">
                    <thead>
                      <tr>
                        <th style="width:5%">#</th>
                        <th style="width:40%">Nombre</th>
                        <th style="width:15%">Tamaño</th>
                        <th style="width:20%">Tipo</th>
                        <th style="width:20%">Acción</th>
                      </tr>
                    </thead>
                    <tbody id="tablaArchivos">
                      <?php
                      $i = 1;
                      foreach ($archivos_requisicion as $archivo): ?>
                        <tr data-archivo-id="<?= $archivo['id'] ?>">
                          <td><?= $i++ ?></td>
                          <td>
                            <i class="fa-regular fa-file-lines text-primary me-1"></i>
                            <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                          </td>
                          <td class="cell-muted"><?= round($archivo['tamaño_archivo'] / 1024, 2) ?> KB</td>
                          <td class="cell-muted"><?= htmlspecialchars($archivo['tipo_mime']) ?></td>
                          <td>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="verArchivo(<?= $archivo['id'] ?>, '<?= htmlspecialchars($archivo['tipo_mime']) ?>')" title="Ver archivo">
                              <i class="fa-regular fa-eye"></i> Ver
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarArchivoTemporal(<?= $archivo['id'] ?>, this)">
                              <i class="fa-solid fa-trash-can"></i> Eliminar
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="oc-finance">
          <div class="oc-finance-title"><i class="fa-solid fa-calculator"></i> Resumen de Pago</div>
          <div class="oc-finance-row">
            <span>Subtotal</span>
            <span id="subtotalGeneral">$0.000</span>
          </div>
          <div class="oc-finance-row">
            <span class="d-flex align-items-center gap-2">
              IVA:
              <select class="form-select form-select-sm oc-finance-iva-select text-white bg-transparent border-0" style="color: white !important;" id="iva" name="iva" onchange="calcularIVA()">
                <option value="0" class="text-dark" selected>Sin IVA</option>
                <option value="8" class="text-dark">8%</option>
                <option value="16" class="text-dark">16%</option>
              </select>
            </span>
            <span id="ivaTotal">$0.000</span>
          </div>
          <div class="oc-finance-total">
            <span class="lbl">Total</span>
            <span class="amt" id="totalGeneral">$0.00</span>
          </div>
        </div>

        <div id="alertContainer" class="mb-3"></div>
        <p class="oc-form-submit-note"><i class="fa-solid fa-circle-info"></i> Esta orden será evaluada por el Subdirector General y Gerente de Recursos Humanos.</p>
        <div class="oc-form-submit-actions">
          <button type="submit" class="btn-geco-primary" id="btnEnviar">
            <i class="fa-solid fa-check"></i> Guardar Orden de Compra
          </button>
        </div>
      </div>
    </div>

  </form>
</div>


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
  // Inhabilitar Enter para guardar (solo clic en botón)
  document.getElementById('ordenCompraForm')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
      e.preventDefault();
    }
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

<?php include __DIR__ . "/../includes/footer.php"; ?>