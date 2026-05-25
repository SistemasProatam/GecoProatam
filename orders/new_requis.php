<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Obtener datos de entidad
$sql_entidades = "SELECT id, nombre FROM entidades ORDER BY nombre ASC";
$result_entidades = $conn->query($sql_entidades);

// Obtener datos de categoria
$sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre ASC";
$result_categorias = $conn->query($sql_categorias);

// Obtener datos de unidades
$sql_unidades = "SELECT id, nombre FROM unidades ORDER BY nombre ASC";
$result_unidades = $conn->query($sql_unidades);

// Obtener datos de Items
$sql_productos_servicios = "SELECT id, nombre, tipo FROM productos_servicios WHERE activo = 1 ORDER BY nombre ASC";
$result_productos_servicios = $conn->query($sql_productos_servicios);

// Obtener datos de usuarios
$sql_usuarios = "SELECT id, nombres, apellidos FROM usuarios ORDER BY nombres DESC";
$result_usuarios = $conn->query($sql_usuarios);

// Obtener datos de proyectos
$sql_proyectos = "SELECT id, nombre_proyecto, numero_contrato 
                  FROM proyectos 
                  WHERE fecha_fin >= CURDATE() 
                  ORDER BY nombre_proyecto ASC";
$result_proyectos = $conn->query($sql_proyectos);

// Preparar array de productos para JavaScript
$productos_array = [];
if ($result_productos_servicios && $result_productos_servicios->num_rows > 0) {
    while ($row = $result_productos_servicios->fetch_assoc()) {
        $productos_array[] = $row;
    }
}

// ============================
// Generar folio inicial para mostrar
// ============================
$sql_last = "SELECT folio FROM requisiciones ORDER BY id DESC LIMIT 1";
$res_last = $conn->query($sql_last);
if ($res_last && $res_last->num_rows > 0) {
    $last_folio = $res_last->fetch_assoc()['folio'];
    $parts = explode("-", $last_folio); // ["REQ", "0001"]
    $num = intval($parts[1]) + 1;
} else {
    $num = 1;
}
$folio = "REQ-" . str_pad($num, 4, "0", STR_PAD_LEFT);

?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">
<style>
   /* Overlay de carga pantalla completa */
#loadingOverlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

/* Contenedor del spinner */
.loading-box {
    background: #ffffff;
    padding: 25px 40px;
    border-radius: 12px;
    box-shadow: 0 0 15px rgba(0,0,0,0.3);
    text-align: center;
    font-size: 17px;
    font-weight: bold;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
}
</style>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <a href="<?= BASE_URL ?>/orders/list_requis.php">Registro de Requisiciones</a>
                <span class="separator">›</span>
                <span>Nueva Requisición</span>
            </nav>
            <h1 class="orders-page-title">Nueva Requisición</h1>
        </div>
        <a href="list_requis.php" class="btn-geco-outline">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <!-- Formulario principal -->
    <form id="ordenCompraForm" method="POST" action="save_requis.php" enctype="multipart/form-data">

        <!-- ─── CARD: INFORMACIÓN GENERAL ───────────────────────────── -->
        <div class="oc-card">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="bi bi-info-circle"></i> Información General de la Requisición</span>
            </div>
            <div class="oc-card-body">
                <p class="oc-card-intro">Complete los datos generales de la requisición. Los campos marcados con <span class="required">*</span> son obligatorios.</p>

                <div class="orders-alert orders-alert--info mb-4">
                    <i class="bi bi-info-circle"></i>
                    <div class="orders-alert__body">
                        <p class="m-0">Este formulario debe ser completado por el personal autorizado para solicitar la compra de bienes o servicios, o para requerir el pago de facturas y compromisos adquiridos por la organización.</p>
                        <span class="mt-1 d-block"><strong>Importante:</strong> El envío de este formulario no garantiza la aprobación automática del pago o compra. Asegúrese de cumplir con los procedimientos y tiempos establecidos por la organización.</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <label class="oc-form-label" for="Folio">Folio de Requisición</label>
                        <input
                          type="text"
                          class="form-control"
                          id="Folio"
                          name="folio"
                          placeholder="Identificador de requisiciones"
                          required
                          value="<?= htmlspecialchars($folio) ?>"
                          readonly
                        />
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <label class="oc-form-label" for="fecha_solicitud">Fecha de Solicitud</label>
                        <input type="datetime-local" class="form-control" id="fecha_solicitud" name="fecha_solicitud" readonly>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <label class="oc-form-label" for="solicitante">Solicitante <span class="required">*</span></label>
                        <input
                          type="text"
                          class="form-control"
                          id="solicitante"
                          name="solicitante"
                          placeholder="Nombre de quien realiza la requisición"
                          required
                          value="<?= htmlspecialchars($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']); ?>"
                          readonly
                        />
                        <input type="hidden" name="solicitante_id" value="<?= $_SESSION['user_id'] ?>">
                    </div>

                    <div class="col-md-6 col-lg-6">
                        <label class="oc-form-label" for="entidad">Entidad <span class="required">*</span></label>
                        <select class="form-select" id="entidad" name="entidad_id" required>
                            <option value="">Seleccionar Entidad</option>
                            <?php
                            if ($result_entidades && $result_entidades->num_rows > 0) { 
                                while ($row = $result_entidades->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-6 col-lg-6">
                        <label class="oc-form-label" for="categoria">Categoría <span class="required">*</span></label>
                        <select class="form-select" id="categoria" name="categoria_id" required>
                            <option value="">Seleccionar Categoría</option>
                            <?php
                            if ($result_categorias && $result_categorias->num_rows > 0) {
                                while ($row = $result_categorias->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                            } ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── CARD: UBICACIÓN DEL PRESUPUESTO ──────────────────────── -->
        <div class="oc-card">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="bi bi-diagram-3"></i> Ubicación del Presupuesto</span>
            </div>
            <div class="oc-card-body">
                <p class="oc-card-intro">Especifique el proyecto, obra y catálogo correspondiente para la afectación presupuestal.</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="oc-form-label" for="proyecto">Proyecto <span class="required">*</span></label>
                        <select class="form-select" id="proyecto" name="proyecto_id" required>
                            <option value="">Seleccionar Proyecto</option>
                            <?php
                            if ($result_proyectos && $result_proyectos->num_rows > 0) { 
                                while ($row = $result_proyectos->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nombre_proyecto']) . ' - ' . htmlspecialchars($row['numero_contrato']) . '</option>';
                                }
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="oc-form-label" for="obra">Obra <span class="required">*</span></label>
                        <select class="form-select" id="obra" name="obra_id" disabled required>
                            <option value="">Primero seleccione un proyecto</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="oc-form-label" for="catalogo">Catálogo <span class="required">*</span></label>
                        <select class="form-select" id="catalogo" name="catalogo_id" disabled required>
                            <option value="">Primero seleccione una obra</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── CARD: ITEMS DE LA REQUISICIÓN ────────────────────────── -->
        <div class="oc-card">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="bi bi-list-ul"></i> Items de la Requisición</span>
                <button type="button" class="btn-geco-outline btn-geco-outline--sm" onclick="mostrarCatalogoProductos()">
                    <i class="bi bi-plus-circle"></i> Agregar Item
                </button>
            </div>
            <div class="oc-card-body oc-card-body--items">
                <div class="orders-table-wrap orders-table-wrap--items">
                    <table class="orders-table orders-table--items" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 15%">Tipo <span class="required">*</span></th>
                                <th style="width: 35%">Producto/Servicio <span class="required">*</span></th>
                                <th style="width: 12%">Cantidad <span class="required">*</span></th>
                                <th style="width: 15%">Unidad <span class="required">*</span></th>
                                <th style="width: 13%">Concepto</th>
                                <th style="width: 5%" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Las filas se agregarán dinámicamente desde JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ─── SECCIÓN: DETALLES Y ADJUNTOS (DOS COLUMNAS) ────────── -->
        <div class="oc-form-layout">
            <div class="oc-form-layout-main">
                <div class="oc-card">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="bi bi-chat-text"></i> Detalle, Descripciones y Observaciones</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="mb-4">
                            <label class="oc-form-label" for="extra">¿No encuentra un producto o servicio?</label>
                            <p class="oc-form-hint--block">Proporcione: Nombre, tipo (producto/servicio) y detalles adicionales para que este pueda ser añadido a la lista.</p>
                            <textarea
                              class="form-control"
                              id="extra"
                              name="extra"
                              rows="3"
                              placeholder="Ingrese producto o servicio no listado..."
                            ></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="oc-form-label" for="descripcion">Descripción General</label>
                            <p class="oc-form-hint--block">Describa de forma general y clara el bien o servicio que se requiere, indicando su uso o finalidad y cantidad aproximada.</p>
                            <textarea
                              class="form-control"
                              id="descripcion"
                              name="descripcion"
                              rows="3"
                              placeholder="Ingrese una descripción general..."
                            ></textarea>
                        </div>

                        <div>
                            <label class="oc-form-label" for="observaciones">Observaciones Adicionales</label>
                            <p class="oc-form-hint--block">Utilice este espacio para anotar detalles importantes: condiciones de entrega, contacto, especificaciones técnicas o cualquier observación relevante.</p>
                            <textarea
                              class="form-control"
                              id="observaciones"
                              name="observaciones"
                              rows="3"
                              placeholder="Ingrese observaciones o comentarios adicionales..."
                            ></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="oc-form-layout-side">
                <div class="oc-card">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="bi bi-paperclip"></i> Archivos Adjuntos</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="orders-alert orders-alert--info mb-3">
                            <i class="bi bi-info-circle"></i>
                            <span>Cargue hasta 5 archivos de uno en uno. PDF, Word, Excel, imágenes. Máx. 10 MB por archivo.</span>
                        </div>
                        
                        <div class="oc-files-input-group mb-3">
                            <input 
                              type="file" 
                              class="form-control" 
                              id="singleFileInput"
                              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
                            <button class="btn-geco-secondary" type="button" onclick="agregarArchivo()">
                              <i class="bi bi-upload"></i> Subir
                            </button>
                        </div>

                        <!-- Lista de archivos acumulados -->
                        <div id="archivosContainer" class="oc-files-dropzone">
                            <h6 class="oc-files-dropzone__title">Archivos seleccionados: <span id="contadorArchivos" class="badge bg-secondary">0</span></h6>
                            <ul id="fileList" class="list-group list-group-flush mt-2">
                                <li class="list-group-item text-center text-muted" style="background:transparent;border:none;">
                                    <i class="bi bi-inbox fs-4 d-block mb-1"></i> No hay archivos agregados
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- ─── SUBMIT ACTIONS ──────────────────────────────────────── -->
                <p class="oc-form-submit-note"><i class="bi bi-info-circle"></i> Esta requisición será evaluada por el Supervisor de Proyectos.</p>
                <div class="oc-form-submit-actions" style="align-items: center;">
                    <button type="submit" class="btn-geco-primary" id="btnEnviar" style="max-width: 320px; width: 100%;">
                        <i class="bi bi-floppy"></i> Guardar Requisición
                    </button>
                </div>
            </div>
        </div>

    </form>

    <!-- Modal para catálogo de productos (Legacy/Soporte) -->
    <div class="modal fade" id="modalCatalogo" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Catálogo de Productos y Servicios</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <input type="text" class="form-control" id="buscarCatalogo" placeholder="Buscar producto o servicio...">
            </div>
            <div class="orders-table-wrap">
              <table class="orders-table">
                <thead>
                  <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Acción</th>
                  </tr>
                </thead>
                <tbody id="tbodyCatalogo">
                  <?php
                  if ($result_productos_servicios && $result_productos_servicios->num_rows > 0): ?>
                    <?php
                    // Reset pointer para usar nuevamente
                    $result_productos_servicios->data_seek(0);
                    while ($producto = $result_productos_servicios->fetch_assoc()): ?>
                      <tr>
                        <td><?= htmlspecialchars($producto['nombre']) ?></td>
                        <td>
                          <span class="badge bg-<?= $producto['tipo'] == 'producto' ? 'primary' : 'success' ?>">
                            <?= ucfirst($producto['tipo']) ?>
                          </span>
                        </td>
                        <td>
                          <button type="button" class="btn btn-sm btn-primary" 
                                   onclick="seleccionarProducto(<?= $producto['id'] ?>, '<?= htmlspecialchars(addslashes($producto['nombre'])) ?>')">
                            <i class="bi bi-plus"></i> Seleccionar
                          </button>
                        </td>
                      </tr>
                    <?php
                    endwhile; ?>
                  <?php
                  else: ?>
                    <tr>
                      <td colspan="3" class="text-center text-muted">
                        No hay productos o servicios registrados
                      </td>
                    </tr>
                  <?php
                  endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
</div> <!-- Termina .orders-page-container -->

<div id="loadingOverlay">
    <div class="loading-box">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-3">Procesando… por favor espere</div>
    </div>
</div>

<!-- Datos para JavaScript -->
<script>
// Datos de productos y unidades para el catálogo
const productosServiciosData = <?= json_encode($productos_array) ?>;
const unidadesData = <?php
$unidades_array = [];
    if ($result_unidades && $result_unidades->num_rows > 0) {
        while ($row = $result_unidades->fetch_assoc()) {
            $unidades_array[] = [
                'id' => $row['id'],
                'unidad' => $row['nombre']
            ];
        }
    }
    echo json_encode($unidades_array);
?>;
</script>

<script>
// Mostrar overlay únicamente al enviar el formulario de nueva requisición
document.getElementById('ordenCompraForm')?.addEventListener('submit', function(e) {
  const overlay = document.getElementById('loadingOverlay');
  if (overlay) overlay.style.display = 'flex';
  // Deshabilitar botones submit para evitar envíos múltiples
  this.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = true);
});
</script>

<!-- Script principal -->
<script src="<?= BASE_URL ?>/assets/scripts/new_requis.js"></script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
