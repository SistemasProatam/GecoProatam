<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list_activos.php"); exit; }

$stmt = $conn->prepare("SELECT * FROM activos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$activo = $stmt->get_result()->fetch_assoc();
if (!$activo) { header("Location: list_activos.php?error=no_encontrado"); exit; }

$stmt_tipo = $conn->prepare("SELECT nombre, prefijo FROM activo_tipos WHERE id = ?");
$stmt_tipo->bind_param("i", $activo['tipo_id']);
$stmt_tipo->execute();
$tipo_row  = $stmt_tipo->get_result()->fetch_assoc();
$tipo_norm = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($tipo_row['nombre'] ?? ''));

if (!function_exists('fetchRow')) {
    function fetchRow($conn, $sql, $id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?? [];
    }
}

$dv  = str_contains($tipo_norm, 'vehiculo')   ? fetchRow($conn, "SELECT * FROM vehiculos_detalle    WHERE activo_id=?", $id) : [];
$dm  = str_contains($tipo_norm, 'maquinaria') ? fetchRow($conn, "SELECT * FROM maquinaria_detalle   WHERE activo_id=?", $id) : [];
$dmb = str_contains($tipo_norm, 'mobiliario') ? fetchRow($conn, "SELECT * FROM mobiliario_detalle   WHERE activo_id=?", $id) : [];
$di  = str_contains($tipo_norm, 'inmueble')   ? fetchRow($conn, "SELECT * FROM inmuebles_detalle    WHERE activo_id=?", $id) : [];
$dh  = str_contains($tipo_norm, 'herramienta')? fetchRow($conn, "SELECT * FROM herramientas_detalle WHERE activo_id=?", $id) : [];
$dt  = str_contains($tipo_norm, 'tic')        ? fetchRow($conn, "SELECT * FROM tics_detalle          WHERE activo_id=?", $id) : [];

$result_tipos         = $conn->query("SELECT id, nombre, prefijo FROM activo_tipos WHERE activo=1 ORDER BY nombre ASC");
$result_usuarios      = $conn->query("SELECT id, nombres, apellidos, departamento_id FROM usuarios WHERE activo=1 ORDER BY nombres ASC");
$result_departamentos = $conn->query("SELECT id, nombre FROM departamentos WHERE activo=1 ORDER BY nombre ASC");

$stmt_docs = $conn->prepare("SELECT * FROM activos_documentos WHERE activo_id=? ORDER BY fecha_subida ASC");
$stmt_docs->bind_param("i", $id);
$stmt_docs->execute();
$documentos = $stmt_docs->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_imgs = $conn->prepare("SELECT * FROM activos_imagenes WHERE activo_id=? ORDER BY fecha_subida ASC");
$stmt_imgs->bind_param("i", $id);
$stmt_imgs->execute();
$imagenes = $stmt_imgs->get_result()->fetch_all(MYSQLI_ASSOC);

if (!function_exists('v')) {
    function v($arr, $key, $default = '') { return htmlspecialchars($arr[$key] ?? $default); }
}
if (!function_exists('sel')) {
    function sel($arr, $key, $value) { return (isset($arr[$key]) && $arr[$key] == $value) ? 'selected' : ''; }
}

// Helpers to identify specific documents or images
if (!function_exists('findDoc')) {
    function findDoc($documentos, $tipo) {
        foreach ($documentos as $d) {
            if ($d['tipo_documento'] === $tipo) return $d;
        }
        return null;
    }
}
if (!function_exists('findImg')) {
    function findImg($imagenes, $tipo) {
        foreach ($imagenes as $img) {
            if ($img['tipo_imagen'] === $tipo) return $img;
        }
        return null;
    }
}
?>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">

<style>
  /* GECO design improvements for Form sections and dynamic sections */
  .section-title {
    font-size: 0.88rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.06em !important;
    color: var(--s-700, #113557) !important;
    border-bottom: 2px solid var(--p-500, #407656) !important;
    padding-bottom: 0.35rem !important;
    margin-top: 2rem !important;
    margin-bottom: 1.25rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
  }
  .section-title i {
    font-size: 1.1rem !important;
    color: var(--p-500, #407656) !important;
  }
  
  .section-detalle {
    display: none;
    animation: fadeIn .3s ease;
  }

  .section-detalle.visible {
    display: block;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(-8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .codigo-preview {
    background: var(--gray-50, #f9fafb);
    border: 1px dashed var(--gray-300, #d1d5db);
    border-radius: 8px;
    padding: 8px 14px;
    font-family: var(--font-sans, system-ui, -apple-system, sans-serif);
    font-size: 0.9rem;
    color: var(--gray-600, #4b5563);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-height: 38px;
  }

  /* File Drop Zone with GECO style */
  .file-drop-zone {
    border: 2px dashed var(--gray-200, #e5e7eb);
    border-radius: 10px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all .2s;
    background: var(--gray-50, #f9fafb);
    position: relative;
  }

  .file-drop-zone:hover {
    border-color: var(--p-500, #407656);
    background: rgba(64, 118, 86, 0.04);
  }

  .file-drop-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
  }

  .file-drop-label {
    display: flex;
    align-items: center;
    gap: 8px;
    pointer-events: none;
    font-size: .82rem;
    color: var(--gray-500, #6b7280);
  }

  .file-drop-label i {
    font-size: 1.1rem;
    color: var(--p-500, #407656);
  }

  /* Chips de archivos */
  .file-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
  }

  .file-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px 4px 8px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 500;
    max-width: 240px;
    overflow: hidden;
    animation: chipIn .25s cubic-bezier(.34, 1.56, .64, 1) both;
  }

  @keyframes chipIn {
    from {
      opacity: 0;
      transform: scale(.8);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  .file-chip.ok {
    background: rgba(34, 197, 94, 0.08);
    color: #15803d;
    border: 1px solid rgba(34, 197, 94, 0.25);
  }

  .file-chip.error {
    background: rgba(239, 68, 68, 0.08);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, 0.25);
  }

  .file-chip .chip-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
  }

  .file-chip .chip-size {
    opacity: .75;
    white-space: nowrap;
  }

  .file-chip .chip-remove {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: inherit;
    opacity: .7;
    font-size: .85rem;
    line-height: 1;
    transition: opacity .15s;
  }

  .file-chip .chip-remove:hover {
    opacity: 1;
  }

  /* Adjuntos dinámicos */
  .adj-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid var(--gray-200, #e5e7eb);
    background: var(--gray-50, #f9fafb);
    margin-bottom: 6px;
    animation: chipIn .25s cubic-bezier(.34, 1.56, .64, 1) both;
  }

  .adj-item.ok {
    border-color: rgba(34, 197, 94, 0.25);
    background: rgba(34, 197, 94, 0.03);
  }

  .adj-item.error {
    border-color: rgba(239, 68, 68, 0.25);
    background: rgba(239, 68, 68, 0.03);
  }

  .adj-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
  }

  .adj-item.ok .adj-icon {
    color: #15803d;
  }

  .adj-item.error .adj-icon {
    color: #b91c1c;
  }

  .adj-info {
    flex: 1;
    min-width: 0;
  }

  .adj-name {
    font-size: .82rem;
    font-weight: 500;
    color: var(--s-900, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .adj-meta {
    font-size: .75rem;
    color: var(--gray-500, #6b7280);
  }

  .adj-item.error .adj-meta {
    color: #b91c1c;
    font-weight: 600;
  }
</style>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="<?= BASE_URL ?>/activos/list_activos.php">Registro de Activos</a>
        <span class="separator">›</span>
        <span>Editar Activo</span>
      </nav>
      <h1 class="orders-page-title">Editar Activo: <?= htmlspecialchars($activo['codigo']) ?></h1>
    </div>
    <a href="details_activo.php?id=<?= $id ?>" class="btn-geco-outline">
      ← Volver al Detalle
    </a>
  </div>

  <!-- ===== Banners de Error / Carga ===== -->
  <?php if (isset($_SESSION['upload_errors']) && !empty($_SESSION['upload_errors'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
      <h6 class="alert-heading fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Hubo problemas al subir algunos archivos:</h6>
      <ul class="mb-0 ps-3">
        <?php foreach ($_SESSION['upload_errors'] as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['upload_errors']); ?>
  <?php endif; ?>

  <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
      <i class="bi bi-exclamation-octagon-fill"></i> 
      <?php 
        $err = $_GET['error'];
        if ($err === 'campos_requeridos') echo 'Por favor, complete todos los campos obligatorios.';
        elseif ($err === 'db') echo 'Error al guardar los cambios en la base de datos.';
        elseif ($err === 'no_encontrado') echo 'El activo especificado no existe.';
        else echo 'Ocurrió un error inesperado al procesar la solicitud.';
      ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <form id="activoForm" method="POST" action="update_activo.php" enctype="multipart/form-data">
    <input type="hidden" name="activo_id" value="<?= $id ?>" />

    <!-- ===== CARD 1: INFORMACIÓN GENERAL ===== -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-info-circle"></i> Información General del Activo</span>
      </div>
      <div class="oc-card-body">
        <p class="oc-card-intro">Modifique los datos principales del activo. El tipo de activo no puede ser cambiado una vez creado para asegurar la integridad de la base de datos.</p>

        <div class="row g-3">
          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Tipo de Activo</label>
            <select class="form-select" id="tipo_id" name="tipo_id_disabled" disabled>
              <?php
              if ($result_tipos && $result_tipos->num_rows > 0) {
                while ($row = $result_tipos->fetch_assoc()) {
                  $selected = $row['id'] == $activo['tipo_id'] ? 'selected' : '';
                  echo '<option value="' . htmlspecialchars($row['id']) . '" '
                    . $selected . ' data-prefijo="' . htmlspecialchars($row['prefijo']) . '">'
                    . htmlspecialchars($row['nombre']) . '</option>';
                }
              }
              ?>
            </select>
            <input type="hidden" name="tipo_id" value="<?= $activo['tipo_id'] ?>" />
          </div>
          
          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Código de Activo</label>
            <div class="codigo-preview">
              <i class="bi bi-upc-scan" style="color: var(--p-500, #407656);"></i> <?= htmlspecialchars($activo['codigo']) ?>
            </div>
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Nombre del Activo <span class="required">*</span></label>
            <input type="text" class="form-control" name="nombre" value="<?= v($activo, 'nombre') ?>" required />
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Condición <span class="required">*</span></label>
            <select class="form-select" name="condicion" required>
              <option value="bueno" <?= sel($activo, 'condicion', 'bueno') ?>>Bueno</option>
              <option value="regular" <?= sel($activo, 'condicion', 'regular') ?>>Regular</option>
              <option value="malo" <?= sel($activo, 'condicion', 'malo') ?>>Malo</option>
            </select>
          </div>

          <div class="col-md-6 col-lg-6">
            <label class="oc-form-label">Responsable</label>
            <select class="form-select" name="responsable_id" id="responsable">
              <option value="">Sin responsable asignado</option>
              <?php
              if ($result_usuarios && $result_usuarios->num_rows > 0) {
                $result_usuarios->data_seek(0);
                while ($row = $result_usuarios->fetch_assoc()) {
                  $selected = $row['id'] == $activo['responsable_id'] ? 'selected' : '';
                  echo '<option value="' . htmlspecialchars($row['id']) . '"'
                    . ' data-departamento="' . htmlspecialchars($row['departamento_id']) . '"' . $selected . '>'
                    . htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) . '</option>';
                }
              }
              ?>
            </select>
          </div>

          <div class="col-md-6 col-lg-6">
            <label class="oc-form-label">Departamento</label>
            <select class="form-select" name="departamento_id" id="departamento">
              <option value="">Sin departamento asignado</option>
              <?php
              if ($result_departamentos && $result_departamentos->num_rows > 0) {
                $result_departamentos->data_seek(0);
                while ($row = $result_departamentos->fetch_assoc()) {
                  $selected = $row['id'] == $activo['departamento_id'] ? 'selected' : '';
                  echo '<option value="' . htmlspecialchars($row['id']) . '"' . $selected . '>'
                    . htmlspecialchars($row['nombre']) . '</option>';
                }
              }
              ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- VEHÍCULOS                                                     -->
    <!-- ============================================================ -->
    <div id="seccion-vehiculos" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-truck"></i> Detalles del Vehículo</span>
      </div>
      <div class="oc-card-body">
        <div class="orders-alert orders-alert--info mb-4">
          <i class="bi bi-info-circle"></i>
          <span><strong>Gravamen:</strong> -Libre: Propiedad plena. -Limitado: Propiedad compartida/proceso de pago. -Con gravamen: Restricción legal activa.</span>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="v_marca" value="<?= v($dv, 'marca') ?>" placeholder="Ford, Toyota, Nissan..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="v_modelo" value="<?= v($dv, 'modelo') ?>" placeholder="F-150, Hilux..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Año</label>
            <input type="number" class="form-control" name="v_anio" value="<?= v($dv, 'anio') ?>" placeholder="2024" min="1900" max="2099" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Color</label>
            <input type="text" class="form-control" name="v_color" value="<?= v($dv, 'color') ?>" placeholder="Blanco, Rojo..." />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Placa</label>
            <input type="text" class="form-control" name="v_placa" value="<?= v($dv, 'placa') ?>" placeholder="ABC-123-D" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">VIN / Número de Serie</label>
            <input type="text" class="form-control" name="v_vin" value="<?= v($dv, 'vin') ?>" placeholder="17 caracteres..." />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Número de Motor</label>
            <input type="text" class="form-control" name="v_numero_motor" value="<?= v($dv, 'numero_motor') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Entidad Federativa</label>
            <input type="text" class="form-control" name="v_entidad_federativa" value="<?= v($dv, 'entidad_federativa') ?>" placeholder="Tamaulipas, Nuevo León..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Pedimento</label>
            <input type="text" class="form-control" name="v_numero_pedimento" value="<?= v($dv, 'numero_pedimento') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Origen</label>
            <select class="form-select" name="v_origen">
              <option value="">Seleccionar</option>
              <option value="nacional" <?= sel($dv, 'origen', 'nacional') ?>>Nacional</option>
              <option value="importado" <?= sel($dv, 'origen', 'importado') ?>>Importado</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Gravamen</label>
            <select class="form-select" name="v_gravamen">
              <option value="">Seleccionar</option>
              <option value="libre" <?= sel($dv, 'gravamen', 'libre') ?>>Libre</option>
              <option value="limitado" <?= sel($dv, 'gravamen', 'limitado') ?>>Limitado</option>
              <option value="gravado" <?= sel($dv, 'gravamen', 'gravado') ?>>Con gravamen</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="oc-form-label">Nombre del Propietario</label>
            <input type="text" class="form-control" name="v_nombre_propietario" value="<?= v($dv, 'nombre_propietario') ?>" />
          </div>

          <!-- Seguro México -->
          <div class="col-12 mt-4 mb-2">
            <div class="oc-form-subsection">
              <div class="oc-form-subsection__title">
                <i class="bi bi-shield-check"></i> Seguro México
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Aseguradora <span class="comentario">(México)</span></label>
            <input type="text" class="form-control" name="v_nombre_aseguradora_mx" value="<?= v($dv, 'nombre_aseguradora_mx') ?>" placeholder="Qualitas, Inbursa..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Teléfono Aseguradora <span class="comentario">(México)</span></label>
            <input type="text" class="form-control" name="v_telefono_aseguradora_mx" value="<?= v($dv, 'telefono_aseguradora_mx') ?>" placeholder="800-XXX-XXXX" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Vto. Seguro <span class="comentario">(México)</span></label>
            <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_mx" value="<?= v($dv, 'fecha_venc_seguro_mx') ?>" />
          </div>

          <!-- Seguro USA -->
          <div class="col-12 mt-4 mb-2">
            <div class="oc-form-subsection">
              <div class="oc-form-subsection__title">
                <i class="bi bi-shield-check"></i> Seguro USA
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Aseguradora <span class="comentario">(USA)</span></label>
            <input type="text" class="form-control" name="v_nombre_aseguradora_usa" value="<?= v($dv, 'nombre_aseguradora_usa') ?>" placeholder="GEICO, State Farm..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Teléfono Aseguradora <span class="comentario">(USA)</span></label>
            <input type="text" class="form-control" name="v_telefono_aseguradora_usa" value="<?= v($dv, 'telefono_aseguradora_usa') ?>" placeholder="800-XXX-XXXX" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Vto. Seguro <span class="comentario">(USA)</span></label>
            <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_usa" value="<?= v($dv, 'fecha_venc_seguro_usa') ?>" />
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- MAQUINARIA                                                    -->
    <!-- ============================================================ -->
    <div id="seccion-maquinaria" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-gear-wide-connected"></i> Detalles de Maquinaria</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="m_marca" value="<?= v($dm, 'marca') ?>" placeholder="Caterpillar, Komatsu..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="m_modelo" value="<?= v($dm, 'modelo') ?>" placeholder="D6T, PC200..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Serie</label>
            <input type="text" class="form-control" name="m_numero_serie" value="<?= v($dm, 'numero_serie') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Kilometraje / Horómetro</label>
            <input type="number" class="form-control" name="m_kilometraje" value="<?= v($dm, 'kilometraje') ?>" placeholder="0" min="0" />
          </div>
          <div class="col-md-8">
            <label class="oc-form-label">Foto Motor</label>
            <?php if (!empty($dm['foto_motor'])): ?>
              <div class="mb-2 d-flex align-items-center gap-3 p-2 rounded border bg-light">
                <img src="<?= htmlspecialchars($dm['foto_motor']) ?>" class="rounded shadow-sm" style="height: 40px; object-fit: cover;">
                <div class="form-check mb-0">
                  <input class="form-check-input" type="checkbox" name="eliminar_foto_motor" value="1" id="del_foto_motor">
                  <label class="form-check-label text-danger fw-semibold small" for="del_foto_motor" style="cursor: pointer;">
                    Eliminar foto actual
                  </label>
                </div>
              </div>
            <?php endif; ?>
            <div class="file-drop-zone">
              <input type="file" name="m_foto_motor" accept="image/*" />
              <div class="file-drop-label">
                <i class="bi bi-camera"></i>
                <span>Seleccionar nueva imagen del motor (máx. 10 MB)</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- MOBILIARIO                                                    -->
    <!-- ============================================================ -->
    <div id="seccion-mobiliario" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-archive"></i> Detalles de Mobiliario</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="mob_marca" value="<?= v($dmb, 'marca') ?>" placeholder="Ikea, Steelcase..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="mob_modelo" value="<?= v($dmb, 'modelo') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Items</label>
            <input type="number" class="form-control" name="mob_numero_items" value="<?= v($dmb, 'numero_items', '1') ?>" placeholder="1" min="1" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Medida Aproximada</label>
            <input type="text" class="form-control" name="mob_medida_aprox" value="<?= v($dmb, 'medida_aprox') ?>" placeholder="1.80 x 0.80 m" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Edificio</label>
            <input type="text" class="form-control" name="mob_edificio" value="<?= v($dmb, 'edificio') ?>" placeholder="Edificio A, Torre Norte..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Área / Departamento</label>
            <input type="text" class="form-control" name="mob_area_departamento" value="<?= v($dmb, 'area_departamento') ?>" placeholder="Recursos Humanos, Gerencia..." />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Dirección</label>
            <input type="text" class="form-control" name="mob_direccion" value="<?= v($dmb, 'direccion') ?>" placeholder="Calle, número, colonia..." />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Descripción</label>
            <textarea class="form-control" name="mob_descripcion" rows="2"><?= v($dmb, 'descripcion') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- INMUEBLES                                                     -->
    <!-- ============================================================ -->
    <div id="seccion-inmuebles" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-building"></i> Detalles del Inmueble</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Tipo de Inmueble</label>
            <input type="text" class="form-control" name="inm_tipo" value="<?= v($di, 'tipo_inmueble') ?>" placeholder="Oficina, Bodega, Terreno..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Tipo de Posesión</label>
            <input type="text" class="form-control" name="inm_tipo_posesion" value="<?= v($di, 'tipo_posesion') ?>" placeholder="Propio, Arrendado, Comodato..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Uso</label>
            <input type="text" class="form-control" name="inm_uso" value="<?= v($di, 'uso') ?>" placeholder="Habitacional, Comercial, Industrial..." />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Dirección</label>
            <input type="text" class="form-control" name="inm_direccion" value="<?= v($di, 'direccion') ?>" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Coordenadas GPS</label>
            <input type="text" class="form-control" name="inm_coordenadas" value="<?= v($di, 'coordenadas') ?>" placeholder="29.0729° N, 110.9559° W" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Superficie Terreno (m²)</label>
            <input type="number" class="form-control" name="inm_superficie_terreno" value="<?= v($di, 'superficie_terreno') ?>" placeholder="0.00" step="0.01" min="0" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Superficie Construida (m²)</label>
            <input type="number" class="form-control" name="inm_superficie_construida" value="<?= v($di, 'superficie_construida') ?>" placeholder="0.00" step="0.01" min="0" />
          </div>
          <div class="col-md-2">
            <label class="oc-form-label">Niveles</label>
            <input type="number" class="form-control" name="inm_niveles" value="<?= v($di, 'niveles') ?>" placeholder="1" min="0" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Valor Terreno</label>
            <input type="number" class="form-control" name="inm_valor_terreno" value="<?= v($di, 'valor_terreno') ?>" placeholder="0.00" step="0.01" min="0" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Folio RPP</label>
            <input type="text" class="form-control" name="inm_folio_rpp" value="<?= v($di, 'folio_rpp') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Predial</label>
            <input type="text" class="form-control" name="inm_predial" value="<?= v($di, 'predial') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Estatus Legal</label>
            <input type="text" class="form-control" name="inm_estatus_legal" value="<?= v($di, 'estatus_legal') ?>" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Responsable Administrativo</label>
            <input type="text" class="form-control" name="inm_responsable_administrativo" value="<?= v($di, 'responsable_administrativo') ?>" />
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- HERRAMIENTAS                                                  -->
    <!-- ============================================================ -->
    <div id="seccion-herramientas" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-tools"></i> Detalles de Herramienta</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="h_marca" value="<?= v($dh, 'marca') ?>" placeholder="Dewalt, Bosch, Stanley..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="h_modelo" value="<?= v($dh, 'modelo') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Serie</label>
            <input type="text" class="form-control" name="h_numero_serie" value="<?= v($dh, 'numero_serie') ?>" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Asignación</label>
            <input type="text" class="form-control" name="h_asignacion" value="<?= v($dh, 'asignacion') ?>" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Ubicación</label>
            <input type="text" class="form-control" name="h_ubicacion_fisica" value="<?= v($dh, 'ubicacion_fisica') ?>" />
          </div>
          <div class="col-12 mt-3">
            <label class="oc-form-label">Descripción</label>
            <textarea class="form-control" name="h_descripcion" rows="2"><?= v($dh, 'descripcion') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- TICs                                                          -->
    <!-- ============================================================ -->
    <div id="seccion-tics" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="bi bi-laptop"></i> Detalles de TICs</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="t_marca" value="<?= v($dt, 'marca') ?>" placeholder="Dell, HP, Apple..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="t_modelo" value="<?= v($dt, 'modelo') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Serie</label>
            <input type="text" class="form-control" name="t_numero_serie" value="<?= v($dt, 'numero_serie') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Sistema Operativo</label>
            <input type="text" class="form-control" name="t_sistema_operativo" value="<?= v($dt, 'sistema_operativo') ?>" placeholder="Windows 11, macOS 14..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Procesador</label>
            <input type="text" class="form-control" name="t_procesador" value="<?= v($dt, 'procesador') ?>" />
          </div>
          <div class="col-md-2">
            <label class="oc-form-label">RAM</label>
            <input type="text" class="form-control" name="t_ram" value="<?= v($dt, 'ram') ?>" placeholder="16 GB" />
          </div>
          <div class="col-md-2">
            <label class="oc-form-label">Almacenamiento</label>
            <input type="text" class="form-control" name="t_almacenamiento" value="<?= v($dt, 'almacenamiento') ?>" placeholder="512 GB SSD" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Office / Suite</label>
            <input type="text" class="form-control" name="t_office" value="<?= v($dt, 'office') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Correo Asignado</label>
            <input type="email" class="form-control" name="t_correo" value="<?= v($dt, 'correo') ?>" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Ubicación Física</label>
            <input type="text" class="form-control" name="t_ubicacion_fisica" value="<?= v($dt, 'ubicacion_fisica') ?>" />
          </div>
          <div class="col-md-6 mt-3">
            <label class="oc-form-label">Programas Instalados</label>
            <textarea class="form-control" name="t_programas_instalados" rows="2"><?= v($dt, 'programas_instalados') ?></textarea>
          </div>
          <div class="col-md-6 mt-3">
            <label class="oc-form-label">Complementos / Accesorios</label>
            <textarea class="form-control" name="t_complementos" rows="2"><?= v($dt, 'complementos') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- DOUBLE COLUMN FORM LAYOUT -->
    <div class="oc-form-layout">
      <div class="oc-form-layout-main">

        <!-- CARD: IMÁGENES -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="bi bi-card-image"></i> Imágenes del Activo</span>
          </div>
          <div class="oc-card-body">
            <div class="row g-3">
              <!-- Foto Principal -->
              <div class="col-12 mb-3">
                <label class="oc-form-label">Foto Principal</label>
                <?php if ($activo['img_foto_principal']): ?>
                  <div class="mb-3 d-flex align-items-center gap-3 p-3 rounded border bg-light">
                    <img src="<?= htmlspecialchars($activo['img_foto_principal']) ?>" class="rounded shadow-sm" style="height: 60px; width: 60px; object-fit: cover;">
                    <div class="form-check mb-0">
                      <input class="form-check-input" type="checkbox" name="eliminar_foto_principal" value="1" id="del_foto_p">
                      <label class="form-check-label text-danger fw-semibold" for="del_foto_p" style="font-size: 0.85rem; cursor: pointer;">
                        Eliminar foto principal actual
                      </label>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="file-drop-zone" id="zone_img_foto_principal">
                  <input type="file" id="input_img_foto_principal"
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                    onchange="handleFile(this,'img_foto_principal','imagen',false)" />
                  <div class="file-drop-label">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Haz clic para seleccionar o arrastra una nueva foto principal (máx. 10 MB)</span>
                  </div>
                </div>
                <div class="file-chips" id="chips_img_foto_principal"></div>
              </div>

              <!-- Fotos Generales -->
              <div class="col-md-4">
                <label class="oc-form-label">Fotos Generales</label>
                <?php 
                $f_gen = array_filter($imagenes, fn($im) => $im['tipo_imagen'] === 'foto_general');
                if (!empty($f_gen)): 
                ?>
                  <div class="row g-2 mb-2">
                    <?php foreach ($f_gen as $img): ?>
                      <div class="col-6">
                        <div class="border rounded p-1 text-center bg-white">
                          <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>" class="rounded" style="width: 100%; height: 50px; object-fit: cover;">
                          <div class="form-check mt-1 text-start" style="padding-left: 1.5em; margin-bottom: 0;">
                            <input class="form-check-input" type="checkbox" name="eliminar_img[]" value="<?= $img['id'] ?>" id="del_img_<?= $img['id'] ?>">
                            <label class="form-check-label text-danger fw-semibold" for="del_img_<?= $img['id'] ?>" style="font-size: 0.65rem; cursor: pointer;">
                              Eliminar
                            </label>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="file-drop-zone">
                  <input type="file" id="input_img_foto_general" accept=".jpg,.jpeg,.png,.gif,.webp" multiple onchange="handleFile(this,'img_foto_general','imagen',true)" />
                  <div class="file-drop-label"><i class="bi bi-images"></i><span>Agregar fotos</span></div>
                </div>
                <div class="file-chips" id="chips_img_foto_general"></div>
              </div>

              <!-- Foto Placa -->
              <div class="col-md-4">
                <label class="oc-form-label">Foto de Placa</label>
                <?php $f_placa = findImg($imagenes, 'foto_placa'); if ($f_placa): ?>
                  <div class="mb-2 p-2 rounded border bg-light text-center">
                    <img src="<?= htmlspecialchars($f_placa['ruta_archivo']) ?>" class="rounded mb-1" style="max-height: 50px; object-fit: cover;">
                    <div class="form-check d-flex justify-content-center mb-0">
                      <input class="form-check-input" type="checkbox" name="eliminar_img[]" value="<?= $f_placa['id'] ?>" id="del_img_<?= $f_placa['id'] ?>">
                      <label class="form-check-label text-danger fw-semibold small ms-1" for="del_img_<?= $f_placa['id'] ?>" style="font-size: 0.72rem; cursor: pointer;">
                        Eliminar actual
                      </label>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="file-drop-zone">
                  <input type="file" id="input_img_foto_placa" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="handleFile(this,'img_foto_placa','imagen',false)" />
                  <div class="file-drop-label"><i class="bi bi-camera"></i><span>Foto placa</span></div>
                </div>
                <div class="file-chips" id="chips_img_foto_placa"></div>
              </div>

              <!-- Foto Serie -->
              <div class="col-md-4">
                <label class="oc-form-label">Foto de Número de Serie</label>
                <?php $f_serie = findImg($imagenes, 'foto_numero_serie'); if ($f_serie): ?>
                  <div class="mb-2 p-2 rounded border bg-light text-center">
                    <img src="<?= htmlspecialchars($f_serie['ruta_archivo']) ?>" class="rounded mb-1" style="max-height: 50px; object-fit: cover;">
                    <div class="form-check d-flex justify-content-center mb-0">
                      <input class="form-check-input" type="checkbox" name="eliminar_img[]" value="<?= $f_serie['id'] ?>" id="del_img_<?= $f_serie['id'] ?>">
                      <label class="form-check-label text-danger fw-semibold small ms-1" for="del_img_<?= $f_serie['id'] ?>" style="font-size: 0.72rem; cursor: pointer;">
                        Eliminar actual
                      </label>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="file-drop-zone">
                  <input type="file" id="input_img_foto_numero_serie" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="handleFile(this,'img_foto_numero_serie','imagen',false)" />
                  <div class="file-drop-label"><i class="bi bi-camera"></i><span>Foto serie</span></div>
                </div>
                <div class="file-chips" id="chips_img_foto_numero_serie"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD: EXPEDIENTE DIGITAL -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="bi bi-file-earmark-pdf"></i> Expediente Digital (Documentos)</span>
          </div>
          <div class="oc-card-body">
            <div class="orders-alert orders-alert--info mb-3">
              <i class="bi bi-info-circle"></i>
              <span>Cargue documentos PDF, Word o imagen. Máx. 10 MB por archivo (Catálogo hasta 1 GB).</span>
            </div>

            <!-- Factura -->
            <div class="mb-3">
              <label class="oc-form-label">Factura / Comprobante de Compra</label>
              <?php $f = findDoc($documentos, 'factura'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_factura" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_factura','normal',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nueva Factura</span></div>
              </div>
              <div class="file-chips" id="chips_doc_factura"></div>
            </div>

            <!-- Pedimento -->
            <div class="mb-3">
              <label class="oc-form-label">Pedimento de Importación</label>
              <?php $f = findDoc($documentos, 'pedimento'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_pedimento" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_pedimento','normal',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nuevo Pedimento</span></div>
              </div>
              <div class="file-chips" id="chips_doc_pedimento"></div>
            </div>

            <!-- Seguro MX -->
            <div class="mb-3">
              <label class="oc-form-label">Póliza de Seguro <span class="comentario">(México)</span></label>
              <?php $f = findDoc($documentos, 'poliza_seguro_mx'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_poliza_seguro" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_poliza_seguro','normal',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nueva Póliza Seguro MX</span></div>
              </div>
              <div class="file-chips" id="chips_doc_poliza_seguro"></div>
            </div>

            <!-- Seguro USA -->
            <div class="mb-3">
              <label class="oc-form-label">Póliza de Seguro <span class="comentario">(USA)</span></label>
              <?php $f = findDoc($documentos, 'poliza_seguro_usa'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_poliza_seguro_usa" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_poliza_seguro_usa','normal',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nueva Póliza Seguro USA</span></div>
              </div>
              <div class="file-chips" id="chips_doc_poliza_seguro_usa"></div>
            </div>

            <!-- Manual Usuario -->
            <div class="mb-3">
              <label class="oc-form-label">Manual de Usuario / Operación</label>
              <?php $f = findDoc($documentos, 'manual_usuario'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_manual" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_manual','normal',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nuevo Manual</span></div>
              </div>
              <div class="file-chips" id="chips_doc_manual"></div>
            </div>

            <!-- Manual Mantenimiento -->
            <div class="mb-3">
              <label class="oc-form-label">Manual de Mantenimiento</label>
              <?php $f = findDoc($documentos, 'manual_mantenimiento'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_manual_mantenimiento" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_manual_mantenimiento','normal',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nuevo Manual de Mantenimiento</span></div>
              </div>
              <div class="file-chips" id="chips_doc_manual_mantenimiento"></div>
            </div>

            <!-- Catálogo Refacciones -->
            <div class="mb-3">
              <label class="oc-form-label">Catálogo de Refacciones <span class="comentario">(máx. 1 GB)</span></label>
              <?php $f = findDoc($documentos, 'catalogo_refacciones'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_catalogo_refacciones" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_catalogo_refacciones','catalogo',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nuevo Catálogo</span></div>
              </div>
              <div class="file-chips" id="chips_doc_catalogo_refacciones"></div>
            </div>

            <!-- Contrato/Escritura -->
            <div class="mb-3">
              <label class="oc-form-label">Contrato / Escritura</label>
              <?php $f = findDoc($documentos, 'contrato'); if ($f): ?>
                <div class="mb-2 d-flex align-items-center gap-2 p-2 rounded border bg-light">
                  <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                  <a href="<?= htmlspecialchars($f['ruta_archivo']) ?>" target="_blank" class="text-decoration-none small text-dark fw-semibold text-truncate" style="max-width: 250px;">
                    <?= htmlspecialchars($f['nombre_original'] ?? basename($f['ruta_archivo'])) ?>
                  </a>
                  <div class="form-check ms-auto mb-0">
                    <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $f['id'] ?>" id="del_doc_<?= $f['id'] ?>">
                    <label class="form-check-label text-danger fw-semibold small" for="del_doc_<?= $f['id'] ?>" style="cursor:pointer;">
                      Eliminar actual
                    </label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="file-drop-zone"><input type="file" id="input_doc_contrato" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_contrato','normal',false)" />
                <div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>Seleccionar nuevo Contrato</span></div>
              </div>
              <div class="file-chips" id="chips_doc_contrato"></div>
            </div>
          </div>
        </div>

        <!-- CARD: EXPEDIENTE FISCAL Y EXTRA -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="bi bi-folder-plus"></i> Carpetas de Control Dinámico</span>
          </div>
          <div class="oc-card-body">
            <!-- Fiscal -->
            <div class="mb-4">
              <label class="oc-form-label"><i class="bi bi-hash"></i> Control Fiscal / Tenencias / Predial</label>
              <?php 
              $docs_fiscal = array_filter($documentos, fn($d) => $d['tipo_documento'] === 'expediente_predial');
              if (!empty($docs_fiscal)): 
              ?>
                <div class="mb-2 border rounded p-2 bg-light">
                  <div class="row g-2">
                    <?php foreach ($docs_fiscal as $d): ?>
                      <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2 p-1 border rounded bg-white">
                          <i class="bi bi-file-earmark-text text-secondary"></i>
                          <a href="<?= htmlspecialchars($d['ruta_archivo']) ?>" target="_blank" class="text-truncate small text-dark fw-semibold" style="max-width: 150px;" title="<?= htmlspecialchars($d['nombre_original'] ?? basename($d['ruta_archivo'])) ?>">
                            <?= htmlspecialchars($d['nombre_original'] ?? basename($d['ruta_archivo'])) ?>
                          </a>
                          <div class="form-check ms-auto mb-0">
                            <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $d['id'] ?>" id="del_doc_<?= $d['id'] ?>">
                            <label class="form-check-label text-danger small fw-semibold" for="del_doc_<?= $d['id'] ?>" style="font-size:0.75rem; cursor:pointer;">Eliminar</label>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
              <small class="text-muted d-block mb-2">Subir nuevos archivos. Máx. 10 archivos, 10 MB c/u.</small>
              <div class="input-group">
                <input type="file" class="form-control" id="singleFileInputFiscal" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                <button class="btn-geco-secondary" type="button" onclick="agregarAdjunto('fiscal')">
                  <i class="bi bi-plus-circle"></i> Agregar
                </button>
              </div>
              <div id="adjuntosContainerFiscal" class="mt-2">
                <h6 class="mb-2">Archivos agregados: <span id="contadorFiscal" class="badge bg-secondary">0</span></h6>
                <div id="adjuntosListFiscal">
                  <p class="text-muted text-center small mb-0"><i class="bi bi-inbox"></i> No hay archivos</p>
                </div>
              </div>
            </div>

            <!-- Extra -->
            <div>
              <label class="oc-form-label"><i class="bi bi-plus-square"></i> Documentación Extra / Adicional</label>
              <?php 
              $docs_extra = array_filter($documentos, fn($d) => $d['tipo_documento'] === 'extra');
              if (!empty($docs_extra)): 
              ?>
                <div class="mb-2 border rounded p-2 bg-light">
                  <div class="row g-2">
                    <?php foreach ($docs_extra as $d): ?>
                      <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2 p-1 border rounded bg-white">
                          <i class="bi bi-file-earmark-text text-secondary"></i>
                          <a href="<?= htmlspecialchars($d['ruta_archivo']) ?>" target="_blank" class="text-truncate small text-dark fw-semibold" style="max-width: 150px;" title="<?= htmlspecialchars($d['nombre_original'] ?? basename($d['ruta_archivo'])) ?>">
                            <?= htmlspecialchars($d['nombre_original'] ?? basename($d['ruta_archivo'])) ?>
                          </a>
                          <div class="form-check ms-auto mb-0">
                            <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= $d['id'] ?>" id="del_doc_<?= $d['id'] ?>">
                            <label class="form-check-label text-danger small fw-semibold" for="del_doc_<?= $d['id'] ?>" style="font-size:0.75rem; cursor:pointer;">Eliminar</label>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
              <small class="text-muted d-block mb-2">Subir nuevos archivos. Máx. 10 archivos, 10 MB c/u.</small>
              <div class="input-group">
                <input type="file" class="form-control" id="singleFileInputExtra" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                <button class="btn-geco-secondary" type="button" onclick="agregarAdjunto('extra')">
                  <i class="bi bi-plus-circle"></i> Agregar
                </button>
              </div>
              <div id="adjuntosContainerExtra" class="mt-2">
                <h6 class="mb-2">Archivos agregados: <span id="contadorExtra" class="badge bg-secondary">0</span></h6>
                <div id="adjuntosListExtra">
                  <p class="text-muted text-center small mb-0"><i class="bi bi-inbox"></i> No hay archivos</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="oc-form-layout-side">

        <!-- CARD: INVERSIÓN Y UBICACIÓN -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="bi bi-wallet2"></i> Inversión y Ubicación</span>
          </div>
          <div class="oc-card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="oc-form-label">Fecha de Adquisición</label>
                <input type="date" class="form-control" name="fecha_adquisicion" value="<?= v($activo, 'fecha_adquisicion') ?>" />
              </div>
              <div class="col-12">
                <label class="oc-form-label">Valor Factura <span class="comentario">(MXN)</span></label>
                <input type="number" class="form-control" name="valor_factura" value="<?= v($activo, 'valor_factura') ?>" placeholder="0.00" step="0.01" min="0" />
              </div>
              <div class="col-12">
                <label class="oc-form-label">Vida Útil <span class="comentario">(años)</span></label>
                <input type="number" class="form-control" name="vida_util" value="<?= v($activo, 'vida_util') ?>" placeholder="0" min="0" />
              </div>
              <div class="col-12">
                <label class="oc-form-label">Ubicación Física</label>
                <input type="text" class="form-control" name="ubicacion" value="<?= v($activo, 'ubicacion') ?>" placeholder="Ej. Oficina Ribereña, Almaguer..." />
              </div>
            </div>
          </div>
        </div>

        <!-- CARD: ESTATUS Y NOTAS -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="bi bi-activity"></i> Estatus y Notas</span>
          </div>
          <div class="oc-card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="oc-form-label">Estatus <span class="required">*</span></label>
                <select class="form-select" name="estatus" required>
                  <option value="activo" <?= sel($activo, 'estatus', 'activo') ?>>Activo</option>
                  <option value="inactivo" <?= sel($activo, 'estatus', 'inactivo') ?>>Inactivo</option>
                </select>
              </div>
              <div class="col-12">
                <label class="oc-form-label">Notas Generales</label>
                <textarea class="form-control" name="notas" rows="4" placeholder="Observaciones generales..."><?= v($activo, 'notas') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ACCIONES DE ENVÍO -->
        <div class="oc-card">
          <div class="oc-card-body">
            <p class="oc-form-submit-note mb-3"><i class="bi bi-info-circle"></i> Verifique los datos técnicos del activo físico antes de confirmar los cambios.</p>
            <div class="oc-form-submit-actions">
              <button type="submit" class="btn-geco-primary w-100" id="btnGuardar">
                <i class="bi bi-floppy"></i> Guardar Cambios
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
  crossorigin="anonymous"></script>

<script>
  // Limits
  const LIMITES_MB = {
    normal: 10,
    imagen: 10,
    catalogo: 1024,
    adjunto: 10
  };

  // File Store
  const fileStore = {};

  // Dynamic sections showing/hiding
  const secciones = {
    'vehiculo': 'seccion-vehiculos',
    'maquinaria': 'seccion-maquinaria',
    'mobiliario': 'seccion-mobiliario',
    'inmueble': 'seccion-inmuebles',
    'herramienta': 'seccion-herramientas',
    'tic': 'seccion-tics'
  };

  function normalizarTexto(str) {
    return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  function mostrarSeccionDetalle() {
    document.querySelectorAll('.section-detalle').forEach(el => el.classList.remove('visible'));
    const select = document.getElementById('tipo_id');
    const option = select.options[select.selectedIndex];
    if (!option || !option.value) return;
    const nombreTipo = normalizarTexto(option.text);
    for (const [clave, idSec] of Object.entries(secciones)) {
      if (nombreTipo.includes(normalizarTexto(clave))) {
        const sec = document.getElementById(idSec);
        if (sec) sec.classList.add('visible');
        break;
      }
    }
  }

  document.addEventListener('DOMContentLoaded', mostrarSeccionDetalle);

  // File Helpers
  function mostrarAlerta(msg, tipo = 'danger') {
    if (tipo === 'success') UI.toast.success(msg);
    else if (tipo === 'warning') UI.toast.warning(msg);
    else if (tipo === 'info') UI.toast.info(msg);
    else UI.toast.error(msg);
  }

  function handleFile(input, campo, tipo, multiple) {
    if (!fileStore[campo]) fileStore[campo] = [];
    const limiteMB = LIMITES_MB[tipo] || 10;
    const files = Array.from(input.files);

    if (!multiple) {
      fileStore[campo] = [];
    }

    files.forEach(file => {
      const sizeMB = file.size / 1024 / 1024;
      const ok = sizeMB <= limiteMB;
      fileStore[campo].push({ file, ok, tipo });
      if (ok) {
        mostrarAlerta(`"${file.name}" (${sizeMB.toFixed(2)} MB) listo para subir.`, 'success');
      } else {
        mostrarAlerta(`"${file.name}" supera el límite de ${limiteMB} MB. Retíralo antes de guardar.`, 'danger');
      }
    });

    renderChips(campo);
    input.value = '';
  }

  function renderChips(campo) {
    const container = document.getElementById('chips_' + campo);
    if (!container) return;
    const entries = fileStore[campo] || [];
    if (!entries.length) {
      container.innerHTML = '';
      return;
    }

    container.innerHTML = entries.map((e, i) => {
      const sizeMB = (e.file.size / 1024 / 1024).toFixed(2);
      const cls = e.ok ? 'ok' : 'error';
      const icon = e.ok ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
      return `
        <div class="file-chip ${cls}">
          <i class="bi ${icon}" style="font-size:.85rem;flex-shrink:0;"></i>
          <span class="chip-name" title="${e.file.name}">${e.file.name}</span>
          <span class="chip-size">${sizeMB} MB</span>
          <button type="button" class="chip-remove" onclick="removeChip('${campo}',${i})" title="Quitar archivo">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>`;
    }).join('');
  }

  function removeChip(campo, index) {
    if (!fileStore[campo]) return;
    const removed = fileStore[campo].splice(index, 1)[0];
    mostrarAlerta(`"${removed.file.name}" eliminado de la lista.`, 'info');
    renderChips(campo);
  }

  function hayArchivosInvalidos() {
    for (const campo in fileStore) {
      if (fileStore[campo].some(e => !e.ok)) return true;
    }
    for (const tipo of ['fiscal', 'extra']) {
      if (pools[tipo] && pools[tipo].some(e => !e.ok)) return true;
    }
    return false;
  }

  function contarArchivosValidos() {
    let count = 0, totalBytes = 0;
    for (const campo in fileStore) {
      fileStore[campo].forEach(e => {
        if (e.ok) { count++; totalBytes += e.file.size; }
      });
    }
    for (const tipo of ['fiscal', 'extra']) {
      if (pools[tipo]) pools[tipo].forEach(e => {
        if (e.ok) { count++; totalBytes += e.file.size; }
      });
    }
    return { count, totalMB: (totalBytes / 1024 / 1024).toFixed(2) };
  }

  // Dynamic attachments
  const pools = { fiscal: [], extra: [] };

  function agregarAdjunto(tipo) {
    const inputId = tipo === 'fiscal' ? 'singleFileInputFiscal' : 'singleFileInputExtra';
    const input = document.getElementById(inputId);
    if (!input.files.length) {
      mostrarAlerta('Seleccione un archivo primero.', 'warning');
      return;
    }
    const file = input.files[0];

    if (pools[tipo].length >= 10) {
      mostrarAlerta('Máximo 10 archivos por sección.', 'warning');
      return;
    }

    const sizeMB = file.size / 1024 / 1024;
    const ok = sizeMB <= LIMITES_MB.adjunto;
    pools[tipo].push({ file, ok });

    if (ok) {
      mostrarAlerta(`"${file.name}" (${sizeMB.toFixed(2)} MB) agregado.`, 'success');
    } else {
      mostrarAlerta(`"${file.name}" supera los 10 MB. Retíralo antes de guardar.`, 'danger');
    }

    renderListaAdj(tipo);
    input.value = '';
  }

  function eliminarAdjunto(tipo, index) {
    const removed = pools[tipo].splice(index, 1)[0];
    mostrarAlerta(`"${removed.file.name}" eliminado.`, 'info');
    renderListaAdj(tipo);
  }

  function renderListaAdj(tipo) {
    const listId = tipo === 'fiscal' ? 'adjuntosListFiscal' : 'adjuntosListExtra';
    const countId = tipo === 'fiscal' ? 'contadorFiscal' : 'contadorExtra';
    const lista = document.getElementById(listId);
    document.getElementById(countId).textContent = pools[tipo].length;

    if (!pools[tipo].length) {
      lista.innerHTML = '<p class="text-muted text-center small"><i class="bi bi-inbox"></i> No hay nuevos archivos</p>';
      return;
    }

    lista.innerHTML = pools[tipo].map((e, i) => {
      const sizeMB = (e.file.size / 1024 / 1024).toFixed(2);
      const cls = e.ok ? 'ok' : 'error';
      const icon = e.ok ? 'bi-file-earmark-check' : 'bi-file-earmark-x';
      const meta = e.ok ? `${sizeMB} MB` : `${sizeMB} MB — excede 10 MB`;
      return `
        <div class="adj-item ${cls}">
          <i class="bi ${icon} adj-icon"></i>
          <div class="adj-info">
            <div class="adj-name">${e.file.name}</div>
            <div class="adj-meta">${meta}</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunto('${tipo}',${i})">
            <i class="bi bi-trash"></i>
          </button>
        </div>`;
    }).join('');
  }

  // Form Submit Handler
  document.getElementById('activoForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    if (hayArchivosInvalidos()) {
      mostrarAlerta(
        'No se puede guardar. Hay archivos que superan el límite permitido. ' +
        'Retira los archivos marcados en rojo e inténtalo de nuevo.',
        'danger'
      );
      const primerError = document.querySelector('.file-chip.error, .adj-item.error');
      if (primerError) primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const { count, totalMB } = contarArchivosValidos();
    if (count > 0) {
      const confirmar = await UI.confirm({
        title: '¿Confirmar cambios?',
        message: `Se actualizarán los datos del activo y se subirán <b>${count}</b> nuevo(s) archivo(s) (${totalMB} MB total).`,
        confirmText: 'Guardar Cambios',
        icon: 'question'
      });
      if (!confirmar) return;
    } else {
      const confirmar = await UI.confirm({
        title: '¿Confirmar cambios?',
        message: '¿Estás seguro de que deseas actualizar los datos de este activo?',
        confirmText: 'Guardar Cambios',
        icon: 'question'
      });
      if (!confirmar) return;
    }

    UI.loading('Guardando cambios...');

    const fd = new FormData(this);

    // Add new files from store
    for (const [campo, entries] of Object.entries(fileStore)) {
      entries.forEach(e => {
        if (!e.ok) return;
        if (campo === 'img_foto_general') {
          fd.append('img_foto_general[]', e.file, e.file.name);
        } else {
          fd.append(campo, e.file, e.file.name);
        }
      });
    }

    // Add new dynamic attachments
    pools.fiscal.forEach(e => {
      if (!e.ok) return;
      fd.append('documentos[]', e.file, e.file.name);
      fd.append('documentos_tipo[]', 'expediente_predial');
    });
    pools.extra.forEach(e => {
      if (!e.ok) return;
      fd.append('documentos[]', e.file, e.file.name);
      fd.append('documentos_tipo[]', 'extra');
    });

    fetch(this.action, {
      method: 'POST',
      body: fd
    })
    .then(res => {
      if (res.redirected) {
        window.location.href = res.url;
        return;
      }
      UI.loading.hide();
      if (res.status >= 400) {
        UI.toast.error("Ocurrió un error al guardar los cambios");
      } else {
        UI.toast.success("Activo actualizado con éxito");
        setTimeout(() => {
          window.location.href = 'details_activo.php?id=<?= $id ?>&success=updated';
        }, 1200);
      }
    })
    .catch(err => {
      UI.loading.hide();
      console.error(err);
      UI.toast.error("Error de conexión al servidor");
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // SINCRONIZAR DEPARTAMENTO AL SELECCIONAR RESPONSABLE
  // ═══════════════════════════════════════════════════════════════
  document.getElementById('responsable').addEventListener('change', function() {
    const deptId = this.options[this.selectedIndex].getAttribute('data-departamento');
    const sel = document.getElementById('departamento');
    if (sel) sel.value = deptId || '';
  });

  // ═══════════════════════════════════════════════════════════════
  // MICRO-INTERACCIONES: DRAG OVER EN ZONAS DE CARGA
  // ═══════════════════════════════════════════════════════════════
  document.querySelectorAll('.file-drop-zone').forEach(zone => {
    const input = zone.querySelector('input[type="file"]');
    if (!input) return;
    input.addEventListener('dragenter', () => {
      zone.style.borderColor = 'var(--p-500, #407656)';
      zone.style.background = 'rgba(64, 118, 86, 0.08)';
    });
    input.addEventListener('dragover', () => {
      zone.style.borderColor = 'var(--p-500, #407656)';
      zone.style.background = 'rgba(64, 118, 86, 0.08)';
    });
    input.addEventListener('dragleave', () => {
      zone.style.borderColor = '';
      zone.style.background = '';
    });
    input.addEventListener('drop', () => {
      zone.style.borderColor = '';
      zone.style.background = '';
    });
  });
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
