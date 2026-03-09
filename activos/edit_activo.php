<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: list_activos.php");
    exit;
}

// ─── Activo principal ────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM activos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$activo = $stmt->get_result()->fetch_assoc();

if (!$activo) {
    header("Location: list_activos.php?error=no_encontrado");
    exit;
}

// ─── Tipo (para saber qué sección mostrar) ────────────────────────────────────
$stmt_tipo = $conn->prepare("SELECT nombre, prefijo FROM activo_tipos WHERE id = ?");
$stmt_tipo->bind_param("i", $activo['tipo_id']);
$stmt_tipo->execute();
$tipo_row  = $stmt_tipo->get_result()->fetch_assoc();
$tipo_norm = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($tipo_row['nombre'] ?? ''));

// ─── Detalles específicos ────────────────────────────────────────────────────
function fetchRow($conn, $sql, $id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?? [];
}

$dv  = str_contains($tipo_norm, 'vehiculo')   ? fetchRow($conn, "SELECT * FROM vehiculos_detalle    WHERE activo_id=?", $id) : [];
$dm  = str_contains($tipo_norm, 'maquinaria') ? fetchRow($conn, "SELECT * FROM maquinaria_detalle   WHERE activo_id=?", $id) : [];
$dmb = str_contains($tipo_norm, 'mobiliario') ? fetchRow($conn, "SELECT * FROM mobiliario_detalle   WHERE activo_id=?", $id) : [];
$di  = str_contains($tipo_norm, 'inmueble')   ? fetchRow($conn, "SELECT * FROM inmuebles_detalle    WHERE activo_id=?", $id) : [];
$dh  = str_contains($tipo_norm, 'herramienta')? fetchRow($conn, "SELECT * FROM herramientas_detalle WHERE activo_id=?", $id) : [];
$dt  = str_contains($tipo_norm, 'tic')        ? fetchRow($conn, "SELECT * FROM tics_detalle          WHERE activo_id=?", $id) : [];

// ─── Catálogos ───────────────────────────────────────────────────────────────
$result_tipos       = $conn->query("SELECT id, nombre, prefijo FROM activo_tipos WHERE activo=1 ORDER BY nombre ASC");
$result_usuarios    = $conn->query("SELECT id, nombres, apellidos FROM usuarios WHERE activo=1 ORDER BY nombres ASC");
$result_departamentos = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");

// ─── Documentos actuales ─────────────────────────────────────────────────────
$stmt_docs = $conn->prepare("SELECT * FROM activos_documentos WHERE activo_id=? ORDER BY fecha_subida ASC");
$stmt_docs->bind_param("i", $id);
$stmt_docs->execute();
$documentos = $stmt_docs->get_result()->fetch_all(MYSQLI_ASSOC);

// ─── Imágenes actuales ───────────────────────────────────────────────────────
$stmt_imgs = $conn->prepare("SELECT * FROM activos_imagenes WHERE activo_id=? ORDER BY fecha_subida ASC");
$stmt_imgs->bind_param("i", $id);
$stmt_imgs->execute();
$imagenes = $stmt_imgs->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper
function v($arr, $key, $default = '') {
    return htmlspecialchars($arr[$key] ?? $default);
}
function sel($arr, $key, $value) {
    return (isset($arr[$key]) && $arr[$key] == $value) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Editar Activo – <?= htmlspecialchars($activo['codigo']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous" />
    <link rel="icon" href="/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/assets/styles/new_order.css" />
  <style>
    .section-detalle { display:none; animation: fadeIn .3s ease; }
    .section-detalle.visible { display:block; }
    @keyframes fadeIn {
      from { opacity:0; transform:translateY(-8px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .codigo-preview {
      background:#f0f4f8; border:1px dashed #adb5bd; border-radius:6px;
      padding:8px 14px; font-family:monospace; font-size:1rem; color:#495057; letter-spacing:1px;
    }
    .img-preview-wrap {
      position:relative; display:inline-block;
    }
    .img-preview-wrap img {
      max-height:120px; border-radius:8px; border:1px solid #dee2e6;
    }
    .doc-existente {
      display:flex; align-items:center; gap:10px; padding:8px 12px;
      border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; margin-bottom:8px;
    }
    .doc-existente i { font-size:1.3rem; color:#113456; }
    .doc-existente .doc-info { flex:1; font-size:.85rem; }
  </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/navbar.php"; ?>

<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="/activos/list_activos.php">Registro de Activos</a>
      <span>/</span>
      <a href="details_activo.php?id=<?= $id ?>"><?= htmlspecialchars($activo['codigo']) ?></a>
      <span>/</span>
      <span>Editar</span>
    </div>
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Editar Activo</h1>
      </div>
    </div>
  </div>
</div>

<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">

      <form id="activoForm" method="POST" action="update_activo.php" enctype="multipart/form-data">
        <input type="hidden" name="activo_id" value="<?= $id ?>" />

        <p>Modifique los datos del activo. Los campos de archivo solo son necesarios si desea reemplazar un archivo existente.</p>

        <!-- ===== INFORMACIÓN GENERAL ===== -->
        <div class="section-title"><i class="bi bi-info-circle"></i> Información General</div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Tipo de Activo <span class="required">*</span></label>
              <select class="form-select" id="tipo_id" name="tipo_id" disabled>
                <option value="">Seleccionar Tipo</option>
                <?php
                if ($result_tipos && $result_tipos->num_rows > 0) {
                    while ($row = $result_tipos->fetch_assoc()) {
                        $sel = ($row['id'] == $activo['tipo_id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($row['id']) . '" '
                           . 'data-prefijo="' . htmlspecialchars($row['prefijo']) . '" ' . $sel . '>'
                           . htmlspecialchars($row['nombre']) . '</option>';
                    }
                }
                ?>
              </select>
              <input type="hidden" name="tipo_id" value="<?= htmlspecialchars($activo['tipo_id']) ?>" />
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Código de Activo</label>
              <div class="codigo-preview" id="codigoPreview">
                <i class="bi bi-upc-scan"></i> <?= htmlspecialchars($activo['codigo']) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Foto principal -->
        <div class="col-md-8 doc-item mb-3">
          <label class="form-label">Foto Principal</label>
          <?php if (!empty($activo['img_foto_principal'])): ?>
            <div class="mb-2 img-preview-wrap">
              <img src="<?= htmlspecialchars($activo['img_foto_principal']) ?>" alt="Foto actual" />
              <div><small class="text-muted">Foto actual – suba una nueva para reemplazar</small></div>
              <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="eliminar_foto_principal" value="1" id="chkFotoPrincipal">
                <label class="form-check-label text-danger" for="chkFotoPrincipal">Eliminar foto actual</label>
              </div>
            </div>
          <?php endif; ?>
          <input type="file" class="form-control" name="img_foto_principal" accept=".jpg,.jpeg,.png,.gif,.webp" />
          <small class="text-muted">JPG, PNG, GIF o WebP</small>
        </div>

        <div class="row">
          <div class="col-md-8">
            <div class="form-group">
              <label class="form-label">Nombre del Activo <span class="required">*</span></label>
              <input type="text" class="form-control" name="nombre" value="<?= v($activo,'nombre') ?>" required />
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Condición <span class="required">*</span></label>
              <select class="form-select" name="condicion" required>
                <option value="">Seleccionar</option>
                <option value="bueno"   <?= sel($activo,'condicion','bueno')   ?>>Bueno</option>
                <option value="regular" <?= sel($activo,'condicion','regular') ?>>Regular</option>
                <option value="malo"    <?= sel($activo,'condicion','malo')    ?>>Malo</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Responsable</label>
              <select class="form-select" name="responsable_id">
                <option value="">Sin responsable asignado</option>
                <?php
                if ($result_usuarios && $result_usuarios->num_rows > 0) {
                    while ($row = $result_usuarios->fetch_assoc()) {
                        $sel = ($row['id'] == $activo['responsable_id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $sel . '>'
                           . htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) . '</option>';
                    }
                }
                ?>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Departamento</label>
              <select class="form-select" name="departamento_id">
                <option value="">Sin departamento asignado</option>
                <?php
                if ($result_departamentos && $result_departamentos->num_rows > 0) {
                    while ($row = $result_departamentos->fetch_assoc()) {
                        $sel = ($row['id'] == $activo['departamento_id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $sel . '>'
                           . htmlspecialchars($row['nombre']) . '</option>';
                    }
                }
                ?>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Fecha de Adquisición</label>
              <input type="date" class="form-control" name="fecha_adquisicion"
                value="<?= v($activo,'fecha_adquisicion') ?>" />
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Valor Factura <span class="comentario">(MXN)</span></label>
              <input type="number" class="form-control" name="valor_factura"
                value="<?= v($activo,'valor_factura') ?>" step="0.01" min="0" />
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="form-label">Vida Útil <span class="comentario">(años)</span></label>
              <input type="number" class="form-control" name="vida_util"
                value="<?= v($activo,'vida_util') ?>" min="0" />
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-8">
            <div class="form-group">
              <label class="form-label">Ubicación</label>
              <input type="text" class="form-control" name="ubicacion" value="<?= v($activo,'ubicacion') ?>" />
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Estatus <span class="required">*</span></label>
              <select class="form-select" name="estatus" required>
                <option value="activo"   <?= sel($activo,'estatus','activo')   ?>>Activo</option>
                <option value="inactivo" <?= sel($activo,'estatus','inactivo') ?>>Inactivo</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Notas Generales</label>
          <textarea class="form-control" name="notas" rows="2"><?= v($activo,'notas') ?></textarea>
        </div>

        <!-- ======================================================= -->
        <!-- VEHÍCULOS                                                -->
        <!-- ======================================================= -->
        <div id="seccion-vehiculos" class="section-detalle">
          <div class="section-title"><i class="bi bi-truck"></i> Detalles del Vehículo</div>
          <small class="text-muted d-block mb-3">
            <i class="bi bi-info-circle"></i>
            Tipo de Gravamen: Libre = propiedad plena · Limitado = en proceso de pago · Con gravamen = restricción legal activa.
          </small>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Marca</label>
              <input type="text" class="form-control" name="v_marca" value="<?= v($dv,'marca') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" name="v_modelo" value="<?= v($dv,'modelo') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Año</label>
              <input type="number" class="form-control" name="v_anio" value="<?= v($dv,'anio') ?>" min="1900" max="2099" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-3"><div class="form-group">
              <label class="form-label">Color</label>
              <input type="text" class="form-control" name="v_color" value="<?= v($dv,'color') ?>" />
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label class="form-label">Placa</label>
              <input type="text" class="form-control" name="v_placa" value="<?= v($dv,'placa') ?>" />
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label class="form-label">VIN / N° Serie</label>
              <input type="text" class="form-control" name="v_vin" value="<?= v($dv,'vin') ?>" />
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label class="form-label">N° Motor</label>
              <input type="text" class="form-control" name="v_numero_motor" value="<?= v($dv,'numero_motor') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Entidad Federativa</label>
              <input type="text" class="form-control" name="v_entidad_federativa" value="<?= v($dv,'entidad_federativa') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">N° Pedimento</label>
              <input type="text" class="form-control" name="v_numero_pedimento" value="<?= v($dv,'numero_pedimento') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Origen</label>
              <select class="form-select" name="v_origen">
                <option value="">Seleccionar</option>
                <option value="nacional"  <?= sel($dv,'origen','nacional')  ?>>Nacional</option>
                <option value="importado" <?= sel($dv,'origen','importado') ?>>Importado</option>
              </select>
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Gravamen</label>
              <select class="form-select" name="v_gravamen">
                <option value="">Seleccionar</option>
                <option value="libre"    <?= sel($dv,'gravamen','libre')   ?>>Libre</option>
                <option value="limitado" <?= sel($dv,'gravamen','limitado') ?>>Limitado</option>
                <option value="gravado"  <?= sel($dv,'gravamen','gravado')  ?>>Con gravamen</option>
              </select>
            </div></div>
            <div class="col-md-8"><div class="form-group">
              <label class="form-label">Nombre del Propietario</label>
              <input type="text" class="form-control" name="v_nombre_propietario" value="<?= v($dv,'nombre_propietario') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Aseguradora <span class="comentario">(MX)</span></label>
              <input type="text" class="form-control" name="v_nombre_aseguradora_mx" value="<?= v($dv,'nombre_aseguradora_mx') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Teléfono Aseguradora <span class="comentario">(MX)</span></label>
              <input type="text" class="form-control" name="v_telefono_aseguradora_mx" value="<?= v($dv,'telefono_aseguradora_mx') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Vto. Seguro <span class="comentario">(MX)</span></label>
              <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_mx" value="<?= v($dv,'fecha_venc_seguro_mx') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Aseguradora <span class="comentario">(USA)</span></label>
              <input type="text" class="form-control" name="v_nombre_aseguradora_usa" value="<?= v($dv,'nombre_aseguradora_usa') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Teléfono Aseguradora <span class="comentario">(USA)</span></label>
              <input type="text" class="form-control" name="v_telefono_aseguradora_usa" value="<?= v($dv,'telefono_aseguradora_usa') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Vto. Seguro <span class="comentario">(USA)</span></label>
              <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_usa" value="<?= v($dv,'fecha_venc_seguro_usa') ?>" />
            </div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- MAQUINARIA                                               -->
        <!-- ======================================================= -->
        <div id="seccion-maquinaria" class="section-detalle">
          <div class="section-title"><i class="bi bi-gear-wide-connected"></i> Detalles de Maquinaria</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Marca</label>
              <input type="text" class="form-control" name="m_marca" value="<?= v($dm,'marca') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" name="m_modelo" value="<?= v($dm,'modelo') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">N° Serie</label>
              <input type="text" class="form-control" name="m_numero_serie" value="<?= v($dm,'numero_serie') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Km / Horómetro</label>
              <input type="number" class="form-control" name="m_kilometraje" value="<?= v($dm,'kilometraje') ?>" min="0" />
            </div></div>
            <div class="col-md-8"><div class="form-group">
              <label class="form-label">Foto Motor</label>
              <?php if (!empty($dm['foto_motor'])): ?>
                <div class="mb-1 img-preview-wrap">
                  <img src="<?= htmlspecialchars($dm['foto_motor']) ?>" alt="Motor actual" />
                  <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="eliminar_foto_motor" value="1" id="chkMotor">
                    <label class="form-check-label text-danger" for="chkMotor">Eliminar foto actual</label>
                  </div>
                </div>
              <?php endif; ?>
              <input type="file" class="form-control" name="m_foto_motor" accept="image/*" />
            </div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- MOBILIARIO                                               -->
        <!-- ======================================================= -->
        <div id="seccion-mobiliario" class="section-detalle">
          <div class="section-title"><i class="bi bi-archive"></i> Detalles de Mobiliario</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Marca</label>
              <input type="text" class="form-control" name="mob_marca" value="<?= v($dmb,'marca') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" name="mob_modelo" value="<?= v($dmb,'modelo') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">N° de Items</label>
              <input type="number" class="form-control" name="mob_numero_items" value="<?= v($dmb,'numero_items','1') ?>" min="1" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Medida Aprox.</label>
              <input type="text" class="form-control" name="mob_medida_aprox" value="<?= v($dmb,'medida_aprox') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Edificio</label>
              <input type="text" class="form-control" name="mob_edificio" value="<?= v($dmb,'edificio') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Área / Depto.</label>
              <input type="text" class="form-control" name="mob_area_departamento" value="<?= v($dmb,'area_departamento') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Dirección</label>
              <input type="text" class="form-control" name="mob_direccion" value="<?= v($dmb,'direccion') ?>" />
            </div></div>
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Descripción</label>
              <textarea class="form-control" name="mob_descripcion" rows="2"><?= v($dmb,'descripcion') ?></textarea>
            </div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- INMUEBLES                                                -->
        <!-- ======================================================= -->
        <div id="seccion-inmuebles" class="section-detalle">
          <div class="section-title"><i class="bi bi-building"></i> Detalles del Inmueble</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Tipo de Inmueble</label>
              <input type="text" class="form-control" name="inm_tipo" value="<?= v($di,'tipo_inmueble') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Tipo de Posesión</label>
              <input type="text" class="form-control" name="inm_tipo_posesion" value="<?= v($di,'tipo_posesion') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Uso</label>
              <input type="text" class="form-control" name="inm_uso" value="<?= v($di,'uso') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Dirección</label>
              <input type="text" class="form-control" name="inm_direccion" value="<?= v($di,'direccion') ?>" />
            </div></div>
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Coordenadas GPS</label>
              <input type="text" class="form-control" name="inm_coordenadas" value="<?= v($di,'coordenadas') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-3"><div class="form-group">
              <label class="form-label">Sup. Terreno (m²)</label>
              <input type="number" class="form-control" name="inm_superficie_terreno" value="<?= v($di,'superficie_terreno') ?>" step="0.01" min="0" />
            </div></div>
            <div class="col-md-3"><div class="form-group">
              <label class="form-label">Sup. Construida (m²)</label>
              <input type="number" class="form-control" name="inm_superficie_construida" value="<?= v($di,'superficie_construida') ?>" step="0.01" min="0" />
            </div></div>
            <div class="col-md-2"><div class="form-group">
              <label class="form-label">Niveles</label>
              <input type="number" class="form-control" name="inm_niveles" value="<?= v($di,'niveles') ?>" min="0" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Valor Terreno</label>
              <input type="number" class="form-control" name="inm_valor_terreno" value="<?= v($di,'valor_terreno') ?>" step="0.01" min="0" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Folio RPP</label>
              <input type="text" class="form-control" name="inm_folio_rpp" value="<?= v($di,'folio_rpp') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Predial</label>
              <input type="text" class="form-control" name="inm_predial" value="<?= v($di,'predial') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Estatus Legal</label>
              <input type="text" class="form-control" name="inm_estatus_legal" value="<?= v($di,'estatus_legal') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Resp. Administrativo</label>
              <input type="text" class="form-control" name="inm_responsable_administrativo" value="<?= v($di,'responsable_administrativo') ?>" />
            </div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- HERRAMIENTAS                                             -->
        <!-- ======================================================= -->
        <div id="seccion-herramientas" class="section-detalle">
          <div class="section-title"><i class="bi bi-tools"></i> Detalles de Herramienta</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Marca</label>
              <input type="text" class="form-control" name="h_marca" value="<?= v($dh,'marca') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" name="h_modelo" value="<?= v($dh,'modelo') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">N° Serie</label>
              <input type="text" class="form-control" name="h_numero_serie" value="<?= v($dh,'numero_serie') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Asignación</label>
              <input type="text" class="form-control" name="h_asignacion" value="<?= v($dh,'asignacion') ?>" />
            </div></div>
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Ubicación Física</label>
              <input type="text" class="form-control" name="h_ubicacion_fisica" value="<?= v($dh,'ubicacion_fisica') ?>" />
            </div></div>
          </div>
          <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="h_descripcion" rows="2"><?= v($dh,'descripcion') ?></textarea>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- TICs                                                     -->
        <!-- ======================================================= -->
        <div id="seccion-tics" class="section-detalle">
          <div class="section-title"><i class="bi bi-laptop"></i> Detalles de TICs</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Marca</label>
              <input type="text" class="form-control" name="t_marca" value="<?= v($dt,'marca') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" name="t_modelo" value="<?= v($dt,'modelo') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">N° Serie</label>
              <input type="text" class="form-control" name="t_numero_serie" value="<?= v($dt,'numero_serie') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Sistema Operativo</label>
              <input type="text" class="form-control" name="t_sistema_operativo" value="<?= v($dt,'sistema_operativo') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Procesador</label>
              <input type="text" class="form-control" name="t_procesador" value="<?= v($dt,'procesador') ?>" />
            </div></div>
            <div class="col-md-2"><div class="form-group">
              <label class="form-label">RAM</label>
              <input type="text" class="form-control" name="t_ram" value="<?= v($dt,'ram') ?>" />
            </div></div>
            <div class="col-md-2"><div class="form-group">
              <label class="form-label">Almacenamiento</label>
              <input type="text" class="form-control" name="t_almacenamiento" value="<?= v($dt,'almacenamiento') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Office / Suite</label>
              <input type="text" class="form-control" name="t_office" value="<?= v($dt,'office') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Correo Asignado</label>
              <input type="email" class="form-control" name="t_correo" value="<?= v($dt,'correo') ?>" />
            </div></div>
            <div class="col-md-4"><div class="form-group">
              <label class="form-label">Ubicación Física</label>
              <input type="text" class="form-control" name="t_ubicacion_fisica" value="<?= v($dt,'ubicacion_fisica') ?>" />
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Programas Instalados</label>
              <textarea class="form-control" name="t_programas_instalados" rows="2"><?= v($dt,'programas_instalados') ?></textarea>
            </div></div>
            <div class="col-md-6"><div class="form-group">
              <label class="form-label">Complementos / Accesorios</label>
              <textarea class="form-control" name="t_complementos" rows="2"><?= v($dt,'complementos') ?></textarea>
            </div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- DOCUMENTOS ACTUALES                                      -->
        <!-- ======================================================= -->
        <?php if (!empty($documentos)): ?>
        <div class="section-title"><i class="bi bi-folder2-open"></i> Documentos Actuales</div>
        <small class="text-muted d-block mb-3">Marque los documentos que desea eliminar. Si sube un archivo nuevo del mismo tipo, el anterior será reemplazado.</small>
        <?php foreach ($documentos as $doc): ?>
          <div class="doc-existente">
            <i class="bi bi-file-earmark"></i>
            <div class="doc-info">
              <strong><?= htmlspecialchars($doc['tipo_documento']) ?></strong><br>
              <?= htmlspecialchars($doc['nombre_original'] ?? basename($doc['ruta_archivo'])) ?>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="eliminar_doc[]"
                     value="<?= (int)$doc['id'] ?>" id="doc_<?= $doc['id'] ?>">
              <label class="form-check-label text-danger" for="doc_<?= $doc['id'] ?>">Eliminar</label>
            </div>
            <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-eye"></i>
            </a>
          </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- ======================================================= -->
        <!-- NUEVOS DOCUMENTOS                                        -->
        <!-- ======================================================= -->
        <div class="section-title mt-3"><i class="bi bi-paperclip"></i> Documentos</div>
        <small class="text-muted d-block mb-4"><i class="bi bi-info-circle"></i> Solo es necesario subir archivos que quiera agregar o reemplazar. Máximo 10 MB por archivo.</small>
        <div class="row">
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Factura / Comprobante de Compra</label>
            <input type="file" class="form-control" name="doc_factura" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Pedimento</label>
            <input type="file" class="form-control" name="doc_pedimento" accept=".pdf,.doc,.docx" />
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Póliza de Seguro <span class="comentario">(MX)</span></label>
            <input type="file" class="form-control" name="doc_poliza_seguro" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Póliza de Seguro <span class="comentario">(USA)</span></label>
            <input type="file" class="form-control" name="doc_poliza_seguro_usa" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Manual de Usuario / Operación</label>
            <input type="file" class="form-control" name="doc_manual" accept=".pdf,.doc,.docx" />
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Manual de Mantenimiento</label>
            <input type="file" class="form-control" name="doc_manual_mantenimiento" accept=".pdf,.doc,.docx" />
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Catálogo de Refacciones <span class="comentario">(máx. 1 GB)</span></label>
            <input type="file" class="form-control" name="doc_catalogo_refacciones" accept=".pdf,.doc,.docx" />
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Contrato / Escritura</label>
            <input type="file" class="form-control" name="doc_contrato" accept=".pdf,.doc,.docx" />
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- IMÁGENES ACTUALES                                        -->
        <!-- ======================================================= -->
        <?php if (!empty($imagenes)): ?>
        <div class="section-title"><i class="bi bi-card-image"></i> Imágenes Actuales</div>
        <div class="row mb-3">
          <?php foreach ($imagenes as $img): ?>
            <div class="col-md-3 text-center mb-3">
              <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                   alt="<?= htmlspecialchars($img['tipo_imagen']) ?>"
                   class="img-thumbnail" style="max-height:100px;" />
              <div><small class="text-muted"><?= htmlspecialchars($img['tipo_imagen']) ?></small></div>
              <div class="form-check d-flex justify-content-center gap-1">
                <input class="form-check-input" type="checkbox" name="eliminar_img[]"
                       value="<?= (int)$img['id'] ?>" id="img_<?= $img['id'] ?>">
                <label class="form-check-label text-danger" for="img_<?= $img['id'] ?>">Eliminar</label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ======================================================= -->
        <!-- NUEVAS IMÁGENES                                          -->
        <!-- ======================================================= -->
        <div class="section-title"><i class="bi bi-card-image"></i> Agregar Imágenes</div>
        <div class="row">
          <div class="col-md-4 doc-item mb-3">
            <label class="form-label">Fotos Generales</label>
            <input type="file" class="form-control" name="img_foto_general[]" accept=".jpg,.jpeg,.png,.gif,.webp" multiple />
            <small class="text-muted">Puede seleccionar varias imágenes</small>
          </div>
          <div class="col-md-4 doc-item mb-3">
            <label class="form-label">Foto de Placa</label>
            <input type="file" class="form-control" name="img_foto_placa" accept=".jpg,.jpeg,.png,.gif,.webp" />
          </div>
          <div class="col-md-4 doc-item mb-3">
            <label class="form-label">Foto de Número de Serie</label>
            <input type="file" class="form-control" name="img_foto_numero_serie" accept=".jpg,.jpeg,.png,.gif,.webp" />
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- EXPEDIENTE / DOC EXTRA                                   -->
        <!-- ======================================================= -->
        <div class="section-title"><i class="bi bi-paperclip"></i> Expediente de Control Fiscal y Tenencia / Predial</div>
        <div class="form-group">
          <small class="text-muted d-block mb-3"><i class="bi bi-info-circle"></i> Máx. 10 archivos, 10 MB c/u.</small>
          <div class="input-group">
            <input type="file" class="form-control" id="singleFileInputFiscal"
                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
            <button class="btn btn-primary" type="button" onclick="agregarAdjunto('fiscal')" style="background:#113456;transform:none;">
              <i class="bi bi-plus-circle"></i> Agregar
            </button>
          </div>
        </div>
        <div id="adjuntosContainerFiscal" class="mt-2 mb-3">
          <h6 class="mb-2">Archivos: <span id="contadorFiscal">0</span></h6>
          <ul id="adjuntosListFiscal" class="list-group">
            <li class="list-group-item text-center text-muted"><i class="bi bi-inbox"></i> No hay archivos</li>
          </ul>
        </div>

        <div class="section-title"><i class="bi bi-paperclip"></i> Documentación Extra</div>
        <div class="form-group">
          <small class="text-muted d-block mb-3"><i class="bi bi-info-circle"></i> Máx. 10 archivos, 10 MB c/u.</small>
          <div class="input-group">
            <input type="file" class="form-control" id="singleFileInputExtra"
                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
            <button class="btn btn-primary" type="button" onclick="agregarAdjunto('extra')" style="background:#113456;transform:none;">
              <i class="bi bi-plus-circle"></i> Agregar
            </button>
          </div>
        </div>
        <div id="adjuntosContainerExtra" class="mt-2 mb-3">
          <h6 class="mb-2">Archivos: <span id="contadorExtra">0</span></h6>
          <ul id="adjuntosListExtra" class="list-group">
            <li class="list-group-item text-center text-muted"><i class="bi bi-inbox"></i> No hay archivos</li>
          </ul>
        </div>

        <!-- ======================================================= -->
        <!-- GUARDAR                                                  -->
        <!-- ======================================================= -->
        <div class="form-actions mt-3">
          <div class="send-otxt">Verifique que toda la información sea correcta antes de guardar.</div>
          <div class="container overflow-hidden text-center">
            <div class="row gx-5">
              <div class="col">
                <div class="p-3">
                  <button type="submit" class="button-57" id="btnGuardar">
                    <i class="bi bi-floppy"></i> Guardar Cambios
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>

<div class="fab-container-backbtn">
  <a onclick="history.back()" class="fab-button-backbtn gray">
    <i class="bi bi-arrow-left"></i>
    <span class="fab-tooltip-backbtn">Volver</span>
  </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
  crossorigin="anonymous"></script>

<script>
// ── Secciones dinámicas ──────────────────────────────────────────────────────
const secciones = {
  'vehiculos':'seccion-vehiculos','vehículos':'seccion-vehiculos',
  'maquinaria':'seccion-maquinaria','mobiliario':'seccion-mobiliario',
  'inmuebles':'seccion-inmuebles','herramientas':'seccion-herramientas',
  'tics':'seccion-tics','tic':'seccion-tics',
};

function normalizarTexto(str) {
  return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
}

function mostrarSeccionDetalle() {
  document.querySelectorAll('.section-detalle').forEach(el => el.classList.remove('visible'));
  const select = document.getElementById('tipo_id');
  const option = select.options[select.selectedIndex];
  if (!option || !option.value) return;
  const nombreTipo = normalizarTexto(option.text);
  const prefijo    = option.getAttribute('data-prefijo') || '';
  document.getElementById('codigoPreview').innerHTML =
    '<i class="bi bi-upc-scan"></i> <?= htmlspecialchars($activo['codigo']) ?>';
  for (const [clave, idSeccion] of Object.entries(secciones)) {
    if (nombreTipo.includes(normalizarTexto(clave))) {
      const sec = document.getElementById(idSeccion);
      if (sec) sec.classList.add('visible');
      break;
    }
  }
}

// Mostrar sección al cargar
window.addEventListener('DOMContentLoaded', mostrarSeccionDetalle);

// ── Adjuntos dinámicos ───────────────────────────────────────────────────────
const pools = { fiscal: [], extra: [] };

function agregarAdjunto(tipo) {
  const inputId = tipo === 'fiscal' ? 'singleFileInputFiscal' : 'singleFileInputExtra';
  const input   = document.getElementById(inputId);
  if (!input.files.length) { alert('Seleccione un archivo primero.'); return; }
  const file = input.files[0];
  if (pools[tipo].length >= 10) { alert('Máximo 10 archivos.'); return; }
  if (file.size > 10 * 1024 * 1024) { alert('El archivo supera 10 MB.'); return; }
  pools[tipo].push(file);
  renderLista(tipo);
  input.value = '';
}

function eliminarAdjunto(tipo, index) {
  pools[tipo].splice(index, 1);
  renderLista(tipo);
}

function renderLista(tipo) {
  const listId  = tipo === 'fiscal' ? 'adjuntosListFiscal'   : 'adjuntosListExtra';
  const countId = tipo === 'fiscal' ? 'contadorFiscal'        : 'contadorExtra';
  const lista   = document.getElementById(listId);
  document.getElementById(countId).textContent = pools[tipo].length;
  if (!pools[tipo].length) {
    lista.innerHTML = '<li class="list-group-item text-center text-muted"><i class="bi bi-inbox"></i> No hay archivos</li>';
    return;
  }
  lista.innerHTML = pools[tipo].map((f, i) =>
    `<li class="list-group-item d-flex justify-content-between align-items-center">
      <span><i class="bi bi-file-earmark"></i> ${f.name}
        <small class="text-muted ms-2">(${(f.size/1024).toFixed(1)} KB)</small></span>
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunto('${tipo}',${i})">
        <i class="bi bi-trash"></i>
      </button>
    </li>`
  ).join('');
}

// Inyectar archivos al enviar — usa FormData+fetch para enviar los pools correctamente
document.getElementById('activoForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const btn = document.getElementById('btnGuardar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

  const fd = new FormData(this);

  // Agregar archivos de ambos pools
  pools.fiscal.forEach(f => {
  fd.append('documentos[]', f, f.name);
  fd.append('documentos_tipo[]', 'expediente_predial');
});
pools.extra.forEach(f => {
  fd.append('documentos[]', f, f.name);
  fd.append('documentos_tipo[]', 'extra');
});

  fetch(this.action, { method: 'POST', body: fd })
    .then(res => {
      if (res.redirected) { window.location.href = res.url; return; }
      return res.text().then(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-floppy"></i> Guardar Cambios';
        if (res.status >= 400) alert('Ocurrió un error al guardar. Revisa el log del servidor.');
        else window.location.href = res.url || 'list_activos.php';
      });
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-floppy"></i> Guardar Cambios';
      alert('Error de red: ' + err.message);
    });
});
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>