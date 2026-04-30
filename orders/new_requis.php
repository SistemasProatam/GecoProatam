<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
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

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Nueva RequisiciÃ³n</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/new_order.css" />
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
      <a href="<?= BASE_URL ?>/orders/list_requis.php">Registro de Requisiciones</a>
      <span>/</span>
      <span>Nueva RequisiciÃ³n</span>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Nueva RequisiciÃ³n</h1>
        </div>
      </div>
      
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">

  <div class="form-container">

    <div class="form-body">
      <form id="ordenCompraForm" method="POST" action="save_requis.php" enctype="multipart/form-data">

        <!-- InformaciÃ³n General -->
        <div>
          <p>
            Este formulario debe ser completado por el personal autorizado
            para solicitar la compra de bienes o servicios, o para requerir
            el pago de facturas y compromisos adquiridos por la
            organizaciÃ³n. <br />
            <b>Importante:</b> El envÃ­o de este formulario no garantiza la
            aprobaciÃ³n automÃ¡tica del pago o compra. AsegÃºrese de cumplir
            con los procedimientos y tiempos establecidos por la
            organizaciÃ³n. <br></p>
           <p>Los campos marcados con <span class="required">*</span> son obligatorios.</p>
        </div>

        <div class="section-title"><i class="bi bi-info-circle"></i> InformaciÃ³n General</div>

        <!-- Folio de RequisiciÃ³n  y fecha-->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Folio de RequisiciÃ³n</label>
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
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Fecha de Solicitud</label>
              <input type="datetime-local" class="form-control" id="fecha_solicitud" name="fecha_solicitud" readonly>
            </div>
          </div>
        </div>

        <!-- Entidad  y solicitante-->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Entidad <span class="required">*</span></label>
              <select class="form-select" id="entidad" name="entidad_id" required>
                <option value="">Seleccionar Entidad</option>
                <?php
if($result_entidades && $result_entidades->num_rows>0){ 
                  while($row=$result_entidades->fetch_assoc()){
                    echo '<option value="'.htmlspecialchars($row['id']).'">'.htmlspecialchars($row['nombre']).'</option>';
                  }
                } ?>
              </select>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Solicitante <span class="required">*</span></label>
              <input
                type="text"
                class="form-control"
                id="solicitante"
                name="solicitante"
                placeholder="Nombre de quien realiza la orden de compra"
                required
                value="<?php
echo htmlspecialchars($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']); ?>"
                readonly
                />
                <input type="hidden" name="solicitante_id" value="<?= $_SESSION['user_id'] ?>">
            </div>
          </div>
        </div>

        <!-- Categoria-->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">CategorÃ­a <span class="required">*</span></label>
              <select class="form-select" id="categoria" name="categoria_id" required>
                <option value="">Seleccionar CategorÃ­a</option>
                <?php
if($result_categorias && $result_categorias->num_rows>0){
                  while($row=$result_categorias->fetch_assoc()){
                    echo '<option value="'.htmlspecialchars($row['id']).'">'.htmlspecialchars($row['nombre']).'</option>';
                  }
                } ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Proyecto, Obra, CatÃ¡logo -->
        <div class="section-title"><i class="bi bi-diagram-3"></i> UbicaciÃ³n del Presupuesto</div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Proyecto</label>
              <select class="form-select" id="proyecto" name="proyecto_id" required>
                <option value="">Seleccionar Proyecto</option>
                <?php
if($result_proyectos && $result_proyectos->num_rows>0){ 
                  while($row=$result_proyectos->fetch_assoc()){
                    echo '<option value="'.htmlspecialchars($row['id']).'">'.htmlspecialchars($row['nombre_proyecto']) . ' - ' . htmlspecialchars($row['numero_contrato']) . '</option>';
                  }
                } ?>
              </select>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Obra</label>
              <select class="form-select" id="obra" name="obra_id" disabled required>
                <option value="">Primero seleccione un proyecto</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">CatÃ¡logo</label>
              <select class="form-select" id="catalogo" name="catalogo_id" disabled required>
                <option value="">Primero seleccione una obra</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Items dinÃ¡micos -->
        <div class="section-title"><i class="bi bi-list-ul"></i> Items de la Orden</div>

        <div class="items-table">
          <table class="table" id="itemsTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Tipo</th>
                <th>Producto/Servicio</th>
                <th>Cantidad</th>
                <th>Unidad</th>
                <th>Concepto</th>
                <th>AcciÃ³n</th>
              </tr>
            </thead>
            <tbody>
             <!-- Inicialmente vacÃ­o -->
            </tbody>
          </table>
        </div>

        <div class="text-end mt-3">
              <button type="button" class="button-56" onclick="mostrarCatalogoProductos()">
                <i class="bi bi-plus-circle"></i> Agregar Item
              </button>
            </div>

        <!-- Extra, DescripciÃ³n y Observaciones -->
      <div class="section-title"><i class="bi bi-plus-circle"></i>Producto/servicio no listado</div>
        <div class="form-group">
              <label class="form-label"
                ><b>Â¿No encuentra un producto o servicio?</b> <br>
                  Proporcione: Nombre, tipo (producto/servicio) y detalles adicionales para que este pueda ser aÃ±adido a la lista.</label
              >
              <textarea
                class="form-control"
                id="extra"
                name="extra"
                rows="3"
                placeholder="Ingrese producto o servicio no listado..."
              ></textarea>
            </div>

        <div class="section-title"><i class="bi bi-file-text"></i> DescripciÃ³n</div>
        <div class="form-group">
              <label class="form-label"
                >Describa de forma general y clara el bien o servicio que se
                requiere, indicando su uso o finalidad, la cantidad aproximada y
                cualquier detalle relevante que facilite su identificaciÃ³n o
                cotizaciÃ³n.</label
              >
              <textarea
                class="form-control"
                id="descripcion"
                name="descripcion"
                rows="3"
                placeholder="Ingrese una descripciÃ³n adicional..."
              ></textarea>
            </div>

        <div class="section-title"><i class="bi bi-chat-text"></i> Observaciones</div>
        <div class="form-group">
              <label class="form-label"
                >Utilice este espacio para anotar detalles importantes, como
                requisitos de empaque, condiciones de pago acordadas, contacto
                para entrega o cualquier otra observaciÃ³n que deba tenerse en
                cuenta para procesar esta orden.</label
              >
              <textarea
                class="form-control"
                id="observaciones"
                name="observaciones"
                rows="3"
                placeholder="Ingrese observaciones o comentarios adicionales..."
              ></textarea>
            </div>

        <!-- Archivos - Agregar de uno en uno -->
<div class="section-title"><i class="bi bi-paperclip"></i> Adjuntar Archivos</div>

<div class="form-group">
  <small class="text-muted d-block mt-2 mb-4">
    <i class="bi bi-info-circle"></i>
    Cargue hasta 5 archivos seleccionÃ¡ndolos de uno en uno. 
    Formatos permitidos: PDF, Word, Excel, imÃ¡genes. TamaÃ±o mÃ¡ximo por archivo: 10 MB.  
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

<!-- Contenedor de alertas -->
<div id="alertContainer" class="mt-2"></div>

<!-- Lista de archivos acumulados -->
<div id="archivosContainer" class="mt-3">
  <h6 class="mb-2">Archivos seleccionados: <span id="contadorArchivos">0</span></h6>
  <ul id="fileList" class="list-group"></ul>
</div>

        <!-- Guardar -->
        <div class="form-actions mt-3">
          <div class="send-otxt">Esta requisiciÃ³n serÃ¡ evaluada por el Supervisor de Proyectos.
          </div>
          <button type="submit" class="button-57"><i class="bi bi-floppy"></i> Guardar</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Modal para catÃ¡logo de productos -->
<div class="modal fade" id="modalCatalogo" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">CatÃ¡logo de Productos y Servicios</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="buscarCatalogo" placeholder="Buscar producto o servicio...">
        </div>
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>AcciÃ³n</th>
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

<!-- Boton de regreso -->
<div class="fab-container-backbtn">  
  <a onclick="history.back()" class="fab-button-backbtn gray">
    <i class="bi bi-arrow-left"></i>
    <span class="fab-tooltip-backbtn">Volver</span>
  </a>
</div>

<div id="loadingOverlay">
    <div class="loading-box">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-3">Procesandoâ€¦ por favor espere</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<!-- Datos para JavaScript -->
<script>
// Datos de productos y unidades para el catÃ¡logo
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
// Mostrar overlay Ãºnicamente al enviar el formulario de nueva requisiciÃ³n
document.getElementById('ordenCompraForm')?.addEventListener('submit', function(e) {
  const overlay = document.getElementById('loadingOverlay');
  if (overlay) overlay.style.display = 'flex';
  // Deshabilitar botones submit para evitar envÃ­os mÃºltiples
  this.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = true);
});
</script>

<!-- Script principal -->
<script src="<?= BASE_URL ?>/assets/scripts/new_requis.js"></script>

<?php
include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>



