<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

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

// ─── Tipo ────────────────────────────────────────────────────────────────────
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
$result_tipos         = $conn->query("SELECT id, nombre, prefijo FROM activo_tipos WHERE activo=1 ORDER BY nombre ASC");
$result_usuarios      = $conn->query("SELECT id, nombres, apellidos FROM usuarios WHERE activo=1 ORDER BY nombres ASC");
$result_departamentos = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");

// ─── Documentos e imágenes actuales ─────────────────────────────────────────
$stmt_docs = $conn->prepare("SELECT * FROM activos_documentos WHERE activo_id=? ORDER BY fecha_subida ASC");
$stmt_docs->bind_param("i", $id);
$stmt_docs->execute();
$documentos = $stmt_docs->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_imgs = $conn->prepare("SELECT * FROM activos_imagenes WHERE activo_id=? ORDER BY fecha_subida ASC");
$stmt_imgs->bind_param("i", $id);
$stmt_imgs->execute();
$imagenes = $stmt_imgs->get_result()->fetch_all(MYSQLI_ASSOC);

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
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/new_order.css" />
  <style>
    .section-detalle { display:none; animation:fadeIn .3s ease; }
    .section-detalle.visible { display:block; }
    @keyframes fadeIn {
      from { opacity:0; transform:translateY(-8px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .codigo-preview {
      background:#f0f4f8; border:1px dashed #adb5bd; border-radius:6px;
      padding:8px 14px; font-family:monospace; font-size:1rem; color:#495057; letter-spacing:1px;
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

    /* ══════════════════════════════════════════
       SISTEMA DE ARCHIVOS CON ESTADO VISUAL
    ══════════════════════════════════════════ */
    .file-drop-zone {
      border: 2px dashed #cbd5e1; border-radius: 10px;
      padding: 14px 16px; cursor: pointer; transition: all .2s;
      background: #f8fafc; position: relative;
    }
    .file-drop-zone:hover { border-color: #113456; background: #eef3f8; }
    .file-drop-zone input[type="file"] {
      position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .file-drop-label {
      display: flex; align-items: center; gap: 8px; pointer-events: none;
      font-size: .85rem; color: #64748b;
    }
    .file-drop-label i { font-size: 1.1rem; }

    /* Chips */
    .file-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .file-chip {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px 4px 8px; border-radius: 20px;
      font-size: .78rem; font-weight: 500; max-width: 240px; overflow: hidden;
      animation: chipIn .25s cubic-bezier(.34,1.56,.64,1) both;
    }
    @keyframes chipIn { from{opacity:0;transform:scale(.8);}to{opacity:1;transform:scale(1);} }
    .file-chip.ok    { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
    .file-chip.error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .file-chip .chip-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }
    .file-chip .chip-size { opacity:.75; white-space:nowrap; }
    .file-chip .chip-remove {
      background:none; border:none; padding:0; cursor:pointer;
      color:inherit; opacity:.7; font-size:.85rem; line-height:1; transition:opacity .15s;
    }
    .file-chip .chip-remove:hover { opacity:1; }

    /* Adjuntos */
    .adj-item {
      display:flex; align-items:center; gap:10px; padding:8px 12px;
      border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc;
      margin-bottom:6px; animation:chipIn .25s cubic-bezier(.34,1.56,.64,1) both;
    }
    .adj-item.ok    { border-color:#6ee7b7; background:#f0fdf4; }
    .adj-item.error { border-color:#fca5a5; background:#fff5f5; }
    .adj-icon { font-size:1.2rem; flex-shrink:0; }
    .adj-item.ok    .adj-icon { color:#059669; }
    .adj-item.error .adj-icon { color:#dc2626; }
    .adj-info { flex:1; min-width:0; }
    .adj-name { font-size:.85rem; font-weight:500; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .adj-meta { font-size:.75rem; color:#64748b; }
    .adj-item.error .adj-meta { color:#dc2626; font-weight:600; }

    /* ══════════════════════════════════════════
       TOASTS
    ══════════════════════════════════════════ */
    #toastContainer {
      position:fixed; top:76px; right:20px; z-index:9999;
      display:flex; flex-direction:column; gap:9px; pointer-events:none;
    }
    .toast-notif {
      pointer-events:all;
      display:flex; align-items:flex-start; gap:11px;
      min-width:300px; max-width:400px; padding:13px 14px;
      border-radius:12px; background:#ffffff;
      box-shadow:0 8px 24px rgba(0,0,0,.12),0 2px 6px rgba(0,0,0,.06);
      border-left:4px solid #113456; position:relative; overflow:hidden;
      animation:tIn .35s cubic-bezier(.34,1.56,.64,1) both;
    }
    .toast-notif.t-danger  { border-color:#dc2626; }
    .toast-notif.t-danger  .t-icon { color:#dc2626; background:#fee2e2; }
    .toast-notif.t-success { border-color:#16a34a; }
    .toast-notif.t-success .t-icon { color:#16a34a; background:#dcfce7; }
    .toast-notif.t-warning { border-color:#d97706; }
    .toast-notif.t-warning .t-icon { color:#d97706; background:#fef3c7; }
    .toast-notif.t-info    { border-color:#2563eb; }
    .toast-notif.t-info    .t-icon { color:#2563eb; background:#dbeafe; }
    .t-icon {
      flex-shrink:0; width:34px; height:34px; border-radius:8px;
      display:flex; align-items:center; justify-content:center; font-size:1rem;
    }
    .t-body { flex:1; min-width:0; }
    .t-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; margin-bottom:2px; }
    .t-msg   { font-size:.85rem; color:#1f2937; line-height:1.4; word-break:break-word; }
    .t-close { background:none; border:none; color:#9ca3af; cursor:pointer; padding:0; font-size:.9rem; flex-shrink:0; transition:color .15s; }
    .t-close:hover { color:#374151; }
    .t-progress { position:absolute; bottom:0; left:0; height:3px; animation:tProgress 5s linear forwards; }
    .t-danger  .t-progress { background:#dc2626; }
    .t-success .t-progress { background:#16a34a; }
    .t-warning .t-progress { background:#d97706; }
    .t-info    .t-progress { background:#2563eb; }
    @keyframes tIn  { from{opacity:0;transform:translateX(36px) scale(.95);}to{opacity:1;transform:translateX(0) scale(1);} }
    @keyframes tOut { from{opacity:1;transform:translateX(0) scale(1);max-height:120px;}to{opacity:0;transform:translateX(36px) scale(.95);max-height:0;} }
    @keyframes tProgress { from{width:100%;}to{width:0;} }

    /* ══════════════════════════════════════════
       MODAL CONFIRMACIÓN
    ══════════════════════════════════════════ */
    .confirm-overlay {
      position:fixed; inset:0; background:rgba(8,12,24,.5); backdrop-filter:blur(4px);
      z-index:10000; display:flex; align-items:center; justify-content:center;
      animation:ovIn .2s ease both;
    }
    @keyframes ovIn { from{opacity:0;}to{opacity:1;} }
    .confirm-modal {
      background:#fff; border-radius:16px; padding:30px 26px 22px;
      max-width:400px; width:90%;
      box-shadow:0 24px 60px rgba(0,0,0,.2);
      animation:mIn .35s cubic-bezier(.34,1.56,.64,1) both;
    }
    @keyframes mIn { from{opacity:0;transform:scale(.85) translateY(18px);}to{opacity:1;transform:scale(1) translateY(0);} }
    .m-icon { width:52px; height:52px; border-radius:13px; background:#dbeafe; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:#1d4ed8; margin:0 auto 16px; }
    .confirm-modal h5 { text-align:center; font-weight:700; font-size:1.05rem; color:#111827; margin-bottom:6px; }
    .confirm-modal p  { text-align:center; font-size:.86rem; color:#6b7280; margin-bottom:18px; }
    .m-stats { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:12px 16px; margin-bottom:20px; display:flex; gap:14px; justify-content:center; }
    .m-stat  { text-align:center; }
    .m-stat .val { font-size:1.35rem; font-weight:800; color:#113456; line-height:1; }
    .m-stat .lbl { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; margin-top:2px; }
    .m-sep { border-left:1px solid #e5e7eb; padding-left:14px; }
    .m-actions { display:flex; gap:9px; }
    .m-actions button { flex:1; padding:10px; border-radius:10px; font-weight:600; font-size:.88rem; border:none; cursor:pointer; transition:all .15s; }
    .btn-cancel { background:#f3f4f6; color:#374151; }
    .btn-cancel:hover { background:#e5e7eb; }
    .btn-ok { background:#113456; color:#fff; }
    .btn-ok:hover { background:#0d2740; transform:translateY(-1px); box-shadow:0 4px 12px rgba(17,52,86,.3); }
  </style>
</head>
<body>

<?php
include __DIR__ . "/../includes/navbar.php"; ?>

<!-- Toast container global -->
<div id="toastContainer"></div>

<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="<?= BASE_URL ?>/activos/list_activos.php">Registro de Activos</a>
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
                        $selected = ($row['id'] == $activo['tipo_id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($row['id']) . '" '
                           . 'data-prefijo="' . htmlspecialchars($row['prefijo']) . '" ' . $selected . '>'
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
          <?php
if (!empty($activo['img_foto_principal'])): ?>
            <div class="mb-2 img-preview-wrap">
              <img src="<?= htmlspecialchars($activo['img_foto_principal']) ?>" alt="Foto actual" />
              <div><small class="text-muted">Foto actual – suba una nueva para reemplazar</small></div>
              <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="eliminar_foto_principal" value="1" id="chkFotoPrincipal">
                <label class="form-check-label text-danger" for="chkFotoPrincipal">Eliminar foto actual</label>
              </div>
            </div>
          <?php
endif; ?>
          <div class="file-drop-zone">
            <input type="file" id="input_img_foto_principal" accept=".jpg,.jpeg,.png,.gif,.webp"
              onchange="handleFile(this,'img_foto_principal','imagen',false)" />
            <div class="file-drop-label">
              <i class="bi bi-cloud-arrow-up"></i>
              <span>Seleccionar imagen (máx. 10 MB)</span>
            </div>
          </div>
          <div class="file-chips" id="chips_img_foto_principal"></div>
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
              <input type="date" class="form-control" name="fecha_adquisicion" value="<?= v($activo,'fecha_adquisicion') ?>" />
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Valor Factura <span class="comentario">(MXN)</span></label>
              <input type="number" class="form-control" name="valor_factura" value="<?= v($activo,'valor_factura') ?>" step="0.01" min="0" />
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="form-label">Vida Útil <span class="comentario">(años)</span></label>
              <input type="number" class="form-control" name="vida_util" value="<?= v($activo,'vida_util') ?>" min="0" />
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
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Marca</label><input type="text" class="form-control" name="v_marca" value="<?= v($dv,'marca') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Modelo</label><input type="text" class="form-control" name="v_modelo" value="<?= v($dv,'modelo') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Año</label><input type="number" class="form-control" name="v_anio" value="<?= v($dv,'anio') ?>" min="1900" max="2099" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-3"><div class="form-group"><label class="form-label">Color</label><input type="text" class="form-control" name="v_color" value="<?= v($dv,'color') ?>" /></div></div>
            <div class="col-md-3"><div class="form-group"><label class="form-label">Placa</label><input type="text" class="form-control" name="v_placa" value="<?= v($dv,'placa') ?>" /></div></div>
            <div class="col-md-3"><div class="form-group"><label class="form-label">VIN / N° Serie</label><input type="text" class="form-control" name="v_vin" value="<?= v($dv,'vin') ?>" /></div></div>
            <div class="col-md-3"><div class="form-group"><label class="form-label">N° Motor</label><input type="text" class="form-control" name="v_numero_motor" value="<?= v($dv,'numero_motor') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Entidad Federativa</label><input type="text" class="form-control" name="v_entidad_federativa" value="<?= v($dv,'entidad_federativa') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">N° Pedimento</label><input type="text" class="form-control" name="v_numero_pedimento" value="<?= v($dv,'numero_pedimento') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Origen</label>
              <select class="form-select" name="v_origen">
                <option value="">Seleccionar</option>
                <option value="nacional"  <?= sel($dv,'origen','nacional')  ?>>Nacional</option>
                <option value="importado" <?= sel($dv,'origen','importado') ?>>Importado</option>
              </select>
            </div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Gravamen</label>
              <select class="form-select" name="v_gravamen">
                <option value="">Seleccionar</option>
                <option value="libre"    <?= sel($dv,'gravamen','libre')    ?>>Libre</option>
                <option value="limitado" <?= sel($dv,'gravamen','limitado') ?>>Limitado</option>
                <option value="gravado"  <?= sel($dv,'gravamen','gravado')  ?>>Con gravamen</option>
              </select>
            </div></div>
            <div class="col-md-8"><div class="form-group"><label class="form-label">Nombre del Propietario</label><input type="text" class="form-control" name="v_nombre_propietario" value="<?= v($dv,'nombre_propietario') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Aseguradora <span class="comentario">(MX)</span></label><input type="text" class="form-control" name="v_nombre_aseguradora_mx" value="<?= v($dv,'nombre_aseguradora_mx') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Teléfono Aseguradora <span class="comentario">(MX)</span></label><input type="text" class="form-control" name="v_telefono_aseguradora_mx" value="<?= v($dv,'telefono_aseguradora_mx') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Vto. Seguro <span class="comentario">(MX)</span></label><input type="date" class="form-control" name="v_fecha_vencimiento_seguro_mx" value="<?= v($dv,'fecha_venc_seguro_mx') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Aseguradora <span class="comentario">(USA)</span></label><input type="text" class="form-control" name="v_nombre_aseguradora_usa" value="<?= v($dv,'nombre_aseguradora_usa') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Teléfono Aseguradora <span class="comentario">(USA)</span></label><input type="text" class="form-control" name="v_telefono_aseguradora_usa" value="<?= v($dv,'telefono_aseguradora_usa') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Vto. Seguro <span class="comentario">(USA)</span></label><input type="date" class="form-control" name="v_fecha_vencimiento_seguro_usa" value="<?= v($dv,'fecha_venc_seguro_usa') ?>" /></div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- MAQUINARIA                                               -->
        <!-- ======================================================= -->
        <div id="seccion-maquinaria" class="section-detalle">
          <div class="section-title"><i class="bi bi-gear-wide-connected"></i> Detalles de Maquinaria</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Marca</label><input type="text" class="form-control" name="m_marca" value="<?= v($dm,'marca') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Modelo</label><input type="text" class="form-control" name="m_modelo" value="<?= v($dm,'modelo') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">N° Serie</label><input type="text" class="form-control" name="m_numero_serie" value="<?= v($dm,'numero_serie') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Km / Horómetro</label><input type="number" class="form-control" name="m_kilometraje" value="<?= v($dm,'kilometraje') ?>" min="0" /></div></div>
            <div class="col-md-8"><div class="form-group">
              <label class="form-label">Foto Motor</label>
              <?php
if (!empty($dm['foto_motor'])): ?>
                <div class="mb-1 img-preview-wrap">
                  <img src="<?= htmlspecialchars($dm['foto_motor']) ?>" alt="Motor actual" />
                  <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="eliminar_foto_motor" value="1" id="chkMotor">
                    <label class="form-check-label text-danger" for="chkMotor">Eliminar foto actual</label>
                  </div>
                </div>
              <?php
endif; ?>
              <div class="file-drop-zone">
                <input type="file" id="input_m_foto_motor" accept="image/*"
                  onchange="handleFile(this,'m_foto_motor','imagen',false)" />
                <div class="file-drop-label"><i class="bi bi-camera"></i><span>Imagen del motor (máx. 10 MB)</span></div>
              </div>
              <div class="file-chips" id="chips_m_foto_motor"></div>
            </div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- MOBILIARIO                                               -->
        <!-- ======================================================= -->
        <div id="seccion-mobiliario" class="section-detalle">
          <div class="section-title"><i class="bi bi-archive"></i> Detalles de Mobiliario</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Marca</label><input type="text" class="form-control" name="mob_marca" value="<?= v($dmb,'marca') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Modelo</label><input type="text" class="form-control" name="mob_modelo" value="<?= v($dmb,'modelo') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">N° de Items</label><input type="number" class="form-control" name="mob_numero_items" value="<?= v($dmb,'numero_items','1') ?>" min="1" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Medida Aprox.</label><input type="text" class="form-control" name="mob_medida_aprox" value="<?= v($dmb,'medida_aprox') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Edificio</label><input type="text" class="form-control" name="mob_edificio" value="<?= v($dmb,'edificio') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Área / Depto.</label><input type="text" class="form-control" name="mob_area_departamento" value="<?= v($dmb,'area_departamento') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group"><label class="form-label">Dirección</label><input type="text" class="form-control" name="mob_direccion" value="<?= v($dmb,'direccion') ?>" /></div></div>
            <div class="col-md-6"><div class="form-group"><label class="form-label">Descripción</label><textarea class="form-control" name="mob_descripcion" rows="2"><?= v($dmb,'descripcion') ?></textarea></div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- INMUEBLES                                                -->
        <!-- ======================================================= -->
        <div id="seccion-inmuebles" class="section-detalle">
          <div class="section-title"><i class="bi bi-building"></i> Detalles del Inmueble</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Tipo de Inmueble</label><input type="text" class="form-control" name="inm_tipo" value="<?= v($di,'tipo_inmueble') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Tipo de Posesión</label><input type="text" class="form-control" name="inm_tipo_posesion" value="<?= v($di,'tipo_posesion') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Uso</label><input type="text" class="form-control" name="inm_uso" value="<?= v($di,'uso') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group"><label class="form-label">Dirección</label><input type="text" class="form-control" name="inm_direccion" value="<?= v($di,'direccion') ?>" /></div></div>
            <div class="col-md-6"><div class="form-group"><label class="form-label">Coordenadas GPS</label><input type="text" class="form-control" name="inm_coordenadas" value="<?= v($di,'coordenadas') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-3"><div class="form-group"><label class="form-label">Sup. Terreno (mÂ²)</label><input type="number" class="form-control" name="inm_superficie_terreno" value="<?= v($di,'superficie_terreno') ?>" step="0.01" min="0" /></div></div>
            <div class="col-md-3"><div class="form-group"><label class="form-label">Sup. Construida (mÂ²)</label><input type="number" class="form-control" name="inm_superficie_construida" value="<?= v($di,'superficie_construida') ?>" step="0.01" min="0" /></div></div>
            <div class="col-md-2"><div class="form-group"><label class="form-label">Niveles</label><input type="number" class="form-control" name="inm_niveles" value="<?= v($di,'niveles') ?>" min="0" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Valor Terreno</label><input type="number" class="form-control" name="inm_valor_terreno" value="<?= v($di,'valor_terreno') ?>" step="0.01" min="0" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Folio RPP</label><input type="text" class="form-control" name="inm_folio_rpp" value="<?= v($di,'folio_rpp') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Predial</label><input type="text" class="form-control" name="inm_predial" value="<?= v($di,'predial') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Estatus Legal</label><input type="text" class="form-control" name="inm_estatus_legal" value="<?= v($di,'estatus_legal') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group"><label class="form-label">Resp. Administrativo</label><input type="text" class="form-control" name="inm_responsable_administrativo" value="<?= v($di,'responsable_administrativo') ?>" /></div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- HERRAMIENTAS                                             -->
        <!-- ======================================================= -->
        <div id="seccion-herramientas" class="section-detalle">
          <div class="section-title"><i class="bi bi-tools"></i> Detalles de Herramienta</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Marca</label><input type="text" class="form-control" name="h_marca" value="<?= v($dh,'marca') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Modelo</label><input type="text" class="form-control" name="h_modelo" value="<?= v($dh,'modelo') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">N° Serie</label><input type="text" class="form-control" name="h_numero_serie" value="<?= v($dh,'numero_serie') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group"><label class="form-label">Asignación</label><input type="text" class="form-control" name="h_asignacion" value="<?= v($dh,'asignacion') ?>" /></div></div>
            <div class="col-md-6"><div class="form-group"><label class="form-label">Ubicación Física</label><input type="text" class="form-control" name="h_ubicacion_fisica" value="<?= v($dh,'ubicacion_fisica') ?>" /></div></div>
          </div>
          <div class="form-group"><label class="form-label">Descripción</label><textarea class="form-control" name="h_descripcion" rows="2"><?= v($dh,'descripcion') ?></textarea></div>
        </div>

        <!-- ======================================================= -->
        <!-- TICs                                                     -->
        <!-- ======================================================= -->
        <div id="seccion-tics" class="section-detalle">
          <div class="section-title"><i class="bi bi-laptop"></i> Detalles de TICs</div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Marca</label><input type="text" class="form-control" name="t_marca" value="<?= v($dt,'marca') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Modelo</label><input type="text" class="form-control" name="t_modelo" value="<?= v($dt,'modelo') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">N° Serie</label><input type="text" class="form-control" name="t_numero_serie" value="<?= v($dt,'numero_serie') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Sistema Operativo</label><input type="text" class="form-control" name="t_sistema_operativo" value="<?= v($dt,'sistema_operativo') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Procesador</label><input type="text" class="form-control" name="t_procesador" value="<?= v($dt,'procesador') ?>" /></div></div>
            <div class="col-md-2"><div class="form-group"><label class="form-label">RAM</label><input type="text" class="form-control" name="t_ram" value="<?= v($dt,'ram') ?>" /></div></div>
            <div class="col-md-2"><div class="form-group"><label class="form-label">Almacenamiento</label><input type="text" class="form-control" name="t_almacenamiento" value="<?= v($dt,'almacenamiento') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-4"><div class="form-group"><label class="form-label">Office / Suite</label><input type="text" class="form-control" name="t_office" value="<?= v($dt,'office') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Correo Asignado</label><input type="email" class="form-control" name="t_correo" value="<?= v($dt,'correo') ?>" /></div></div>
            <div class="col-md-4"><div class="form-group"><label class="form-label">Ubicación Física</label><input type="text" class="form-control" name="t_ubicacion_fisica" value="<?= v($dt,'ubicacion_fisica') ?>" /></div></div>
          </div>
          <div class="row">
            <div class="col-md-6"><div class="form-group"><label class="form-label">Programas Instalados</label><textarea class="form-control" name="t_programas_instalados" rows="2"><?= v($dt,'programas_instalados') ?></textarea></div></div>
            <div class="col-md-6"><div class="form-group"><label class="form-label">Complementos / Accesorios</label><textarea class="form-control" name="t_complementos" rows="2"><?= v($dt,'complementos') ?></textarea></div></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- DOCUMENTOS ACTUALES                                      -->
        <!-- ======================================================= -->
        <?php
if (!empty($documentos)): ?>
        <div class="section-title"><i class="bi bi-folder2-open"></i> Documentos Actuales</div>
        <small class="text-muted d-block mb-3">Marque los documentos que desea eliminar.</small>
        <?php
foreach ($documentos as $doc): ?>
          <div class="doc-existente">
            <i class="bi bi-file-earmark"></i>
            <div class="doc-info">
              <strong><?= htmlspecialchars($doc['tipo_documento']) ?></strong><br>
              <?= htmlspecialchars($doc['nombre_original'] ?? basename($doc['ruta_archivo'])) ?>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="eliminar_doc[]" value="<?= (int)$doc['id'] ?>" id="doc_<?= $doc['id'] ?>">
              <label class="form-check-label text-danger" for="doc_<?= $doc['id'] ?>">Eliminar</label>
            </div>
            <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-eye"></i>
            </a>
          </div>
        <?php
endforeach; ?>
        <?php
endif; ?>

        <!-- ======================================================= -->
        <!-- NUEVOS DOCUMENTOS                                        -->
        <!-- ======================================================= -->
        <div class="section-title mt-3"><i class="bi bi-paperclip"></i> Documentos</div>
        <small class="text-muted d-block mb-4"><i class="bi bi-info-circle"></i> Máximo 10 MB por archivo. Catálogo de refacciones hasta 1 GB.</small>

        <div class="row">
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Factura / Comprobante de Compra</label>
            <div class="file-drop-zone"><input type="file" id="input_doc_factura" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_factura','normal',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF, Word o imagen (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_doc_factura"></div>
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Pedimento</label>
            <div class="file-drop-zone"><input type="file" id="input_doc_pedimento" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_pedimento','normal',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF o Word (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_doc_pedimento"></div>
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Póliza de Seguro <span class="comentario">(MX)</span></label>
            <div class="file-drop-zone"><input type="file" id="input_doc_poliza_seguro" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_poliza_seguro','normal',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF, Word o imagen (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_doc_poliza_seguro"></div>
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Póliza de Seguro <span class="comentario">(USA)</span></label>
            <div class="file-drop-zone"><input type="file" id="input_doc_poliza_seguro_usa" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_poliza_seguro_usa','normal',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF, Word o imagen (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_doc_poliza_seguro_usa"></div>
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Manual de Usuario / Operación</label>
            <div class="file-drop-zone"><input type="file" id="input_doc_manual" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_manual','normal',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF o Word (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_doc_manual"></div>
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Manual de Mantenimiento</label>
            <div class="file-drop-zone"><input type="file" id="input_doc_manual_mantenimiento" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_manual_mantenimiento','normal',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF o Word (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_doc_manual_mantenimiento"></div>
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Catálogo de Refacciones <span class="comentario">(máx. 1 GB)</span></label>
            <div class="file-drop-zone"><input type="file" id="input_doc_catalogo_refacciones" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_catalogo_refacciones','catalogo',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF o Word (máx. 1 GB)</span></div></div>
            <div class="file-chips" id="chips_doc_catalogo_refacciones"></div>
          </div>
          <div class="col-md-6 doc-item mb-3">
            <label class="form-label">Contrato / Escritura</label>
            <div class="file-drop-zone"><input type="file" id="input_doc_contrato" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_contrato','normal',false)" /><div class="file-drop-label"><i class="bi bi-file-earmark-arrow-up"></i><span>PDF o Word (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_doc_contrato"></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- IMÁGENES ACTUALES                                        -->
        <!-- ======================================================= -->
        <?php
if (!empty($imagenes)): ?>
        <div class="section-title"><i class="bi bi-card-image"></i> Imágenes Actuales</div>
        <div class="row mb-3">
          <?php
foreach ($imagenes as $img): ?>
            <div class="col-md-3 text-center mb-3">
              <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                   alt="<?= htmlspecialchars($img['tipo_imagen']) ?>"
                   class="img-thumbnail" style="max-height:100px;" />
              <div><small class="text-muted"><?= htmlspecialchars($img['tipo_imagen']) ?></small></div>
              <div class="form-check d-flex justify-content-center gap-1">
                <input class="form-check-input" type="checkbox" name="eliminar_img[]" value="<?= (int)$img['id'] ?>" id="img_<?= $img['id'] ?>">
                <label class="form-check-label text-danger" for="img_<?= $img['id'] ?>">Eliminar</label>
              </div>
            </div>
          <?php
endforeach; ?>
        </div>
        <?php
endif; ?>

        <!-- ======================================================= -->
        <!-- NUEVAS IMÁGENES                                          -->
        <!-- ======================================================= -->
        <div class="section-title"><i class="bi bi-card-image"></i> Agregar Imágenes</div>
        <div class="row">
          <div class="col-md-4 doc-item mb-3">
            <label class="form-label">Fotos Generales</label>
            <div class="file-drop-zone"><input type="file" id="input_img_foto_general" accept=".jpg,.jpeg,.png,.gif,.webp" multiple onchange="handleFile(this,'img_foto_general','imagen',true)" /><div class="file-drop-label"><i class="bi bi-images"></i><span>Varias imágenes (máx. 10 MB c/u)</span></div></div>
            <div class="file-chips" id="chips_img_foto_general"></div>
          </div>
          <div class="col-md-4 doc-item mb-3">
            <label class="form-label">Foto de Placa</label>
            <div class="file-drop-zone"><input type="file" id="input_img_foto_placa" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="handleFile(this,'img_foto_placa','imagen',false)" /><div class="file-drop-label"><i class="bi bi-camera"></i><span>JPG, PNG o WebP (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_img_foto_placa"></div>
          </div>
          <div class="col-md-4 doc-item mb-3">
            <label class="form-label">Foto de Número de Serie</label>
            <div class="file-drop-zone"><input type="file" id="input_img_foto_numero_serie" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="handleFile(this,'img_foto_numero_serie','imagen',false)" /><div class="file-drop-label"><i class="bi bi-camera"></i><span>JPG, PNG o WebP (máx. 10 MB)</span></div></div>
            <div class="file-chips" id="chips_img_foto_numero_serie"></div>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- EXPEDIENTE FISCAL                                        -->
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
          <div id="adjuntosListFiscal">
            <p class="text-muted text-center small"><i class="bi bi-inbox"></i> No hay archivos</p>
          </div>
        </div>

        <!-- ======================================================= -->
        <!-- DOCUMENTACIÓN EXTRA                                      -->
        <!-- ======================================================= -->
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
          <div id="adjuntosListExtra">
            <p class="text-muted text-center small"><i class="bi bi-inbox"></i> No hay archivos</p>
          </div>
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
// ═══════════════════════════════════════════════════════════════
// LÍMITES POR TIPO
// ═══════════════════════════════════════════════════════════════
const LIMITES_MB = { normal: 10, imagen: 10, catalogo: 1024, adjunto: 10 };

// ═══════════════════════════════════════════════════════════════
// STORE DE ARCHIVOS  { campo: [{ file, ok }] }
// ═══════════════════════════════════════════════════════════════
const fileStore = {};

// ═══════════════════════════════════════════════════════════════
// TOASTS
// ═══════════════════════════════════════════════════════════════
const TCFG = {
  danger:  { title: 'Error',       icon: 'bi-x-circle-fill' },
  success: { title: 'Listo',       icon: 'bi-check-circle-fill' },
  warning: { title: 'Aviso',       icon: 'bi-exclamation-triangle-fill' },
  info:    { title: 'Información', icon: 'bi-info-circle-fill' },
};

function mostrarAlerta(msg, tipo = 'danger') {
  const c = document.getElementById('toastContainer');
  const id = 'toast_' + Date.now() + '_' + Math.random().toString(36).slice(2,5);
  const cfg = TCFG[tipo] || TCFG.danger;
  const el = document.createElement('div');
  el.id = id;
  el.className = `toast-notif t-${tipo}`;
  el.innerHTML = `
    <div class="t-icon"><i class="bi ${cfg.icon}"></i></div>
    <div class="t-body">
      <div class="t-title">${cfg.title}</div>
      <div class="t-msg">${msg}</div>
    </div>
    <button class="t-close" onclick="cerrarAlerta('${id}')"><i class="bi bi-x-lg"></i></button>
    <div class="t-progress"></div>`;
  c.appendChild(el);
  const all = c.querySelectorAll('.toast-notif');
  if (all.length > 5) cerrarAlerta(all[0].id);
  setTimeout(() => cerrarAlerta(id), 5000);
}

function cerrarAlerta(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.animation = 'tOut .3s ease forwards';
  setTimeout(() => el?.remove(), 290);
}

// ═══════════════════════════════════════════════════════════════
// MODAL DE CONFIRMACIÓN
// ═══════════════════════════════════════════════════════════════
function mostrarConfirmacion({ archivos, totalMB }) {
  return new Promise(resolve => {
    const ov = document.createElement('div');
    ov.className = 'confirm-overlay';
    ov.innerHTML = `
      <div class="confirm-modal">
        <div class="m-icon"><i class="bi bi-cloud-arrow-up" style="font-size:1.4rem;"></i></div>
        <h5>¿Confirmar subida?</h5>
        <p>Se guardarán los cambios y se subirán los archivos adjuntos.</p>
        <div class="m-stats">
          <div class="m-stat"><div class="val">${archivos}</div><div class="lbl">Archivo${archivos !== 1 ? 's' : ''}</div></div>
          <div class="m-stat m-sep"><div class="val">${totalMB}</div><div class="lbl">MB total</div></div>
        </div>
        <div class="m-actions">
          <button class="btn-cancel" id="bCancel"><i class="bi bi-x-lg"></i> Cancelar</button>
          <button class="btn-ok"     id="bOk"><i class="bi bi-floppy"></i> Guardar</button>
        </div>
      </div>`;
    document.body.appendChild(ov);
    ov.querySelector('#bOk').onclick    = () => { ov.remove(); resolve(true);  };
    ov.querySelector('#bCancel').onclick = () => { ov.remove(); resolve(false); };
    ov.addEventListener('click', e => { if (e.target === ov) { ov.remove(); resolve(false); } });
  });
}

// ═══════════════════════════════════════════════════════════════
// MANEJO DE ARCHIVOS CON CHIPS
// ═══════════════════════════════════════════════════════════════
function handleFile(input, campo, tipo, multiple) {
  if (!fileStore[campo]) fileStore[campo] = [];
  const limiteMB = LIMITES_MB[tipo] || 10;
  const files = Array.from(input.files);

  if (!multiple) fileStore[campo] = [];

  files.forEach(file => {
    const sizeMB = file.size / 1024 / 1024;
    const ok = sizeMB <= limiteMB;
    fileStore[campo].push({ file, ok, tipo });
    if (ok) {
      mostrarAlerta(`"${file.name}" (${sizeMB.toFixed(2)} MB) listo para subir.`, 'success');
    } else {
      mostrarAlerta(`"${file.name}" pesa ${sizeMB.toFixed(2)} MB y supera el límite de ${limiteMB} MB. Retíralo antes de guardar.`, 'danger');
    }
  });

  renderChips(campo);
  input.value = '';
}

function renderChips(campo) {
  const container = document.getElementById('chips_' + campo);
  if (!container) return;
  const entries = fileStore[campo] || [];
  if (!entries.length) { container.innerHTML = ''; return; }

  container.innerHTML = entries.map((e, i) => {
    const sizeMB = (e.file.size / 1024 / 1024).toFixed(2);
    const cls  = e.ok ? 'ok'                  : 'error';
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
    fileStore[campo].forEach(e => { if (e.ok) { count++; totalBytes += e.file.size; } });
  }
  for (const tipo of ['fiscal', 'extra']) {
    if (pools[tipo]) pools[tipo].forEach(e => { if (e.ok) { count++; totalBytes += e.file.size; } });
  }
  return { count, totalMB: (totalBytes / 1024 / 1024).toFixed(2) };
}

// ═══════════════════════════════════════════════════════════════
// ADJUNTOS DINÁMICOS (fiscal / extra)
// ═══════════════════════════════════════════════════════════════
const pools = { fiscal: [], extra: [] };

function agregarAdjunto(tipo) {
  const inputId = tipo === 'fiscal' ? 'singleFileInputFiscal' : 'singleFileInputExtra';
  const input = document.getElementById(inputId);
  if (!input.files.length) { mostrarAlerta('Seleccione un archivo primero.', 'warning'); return; }
  const file = input.files[0];
  if (pools[tipo].length >= 10) { mostrarAlerta('Máximo 10 archivos por sección.', 'warning'); return; }

  const sizeMB = file.size / 1024 / 1024;
  const ok = sizeMB <= LIMITES_MB.adjunto;
  pools[tipo].push({ file, ok });

  if (ok) {
    mostrarAlerta(`"${file.name}" (${sizeMB.toFixed(2)} MB) agregado.`, 'success');
  } else {
    mostrarAlerta(`"${file.name}" pesa ${sizeMB.toFixed(2)} MB y supera 10 MB. Retíralo antes de guardar.`, 'danger');
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
  const listId  = tipo === 'fiscal' ? 'adjuntosListFiscal' : 'adjuntosListExtra';
  const countId = tipo === 'fiscal' ? 'contadorFiscal'     : 'contadorExtra';
  const lista   = document.getElementById(listId);
  document.getElementById(countId).textContent = pools[tipo].length;

  if (!pools[tipo].length) {
    lista.innerHTML = '<p class="text-muted text-center small"><i class="bi bi-inbox"></i> No hay archivos</p>';
    return;
  }

  lista.innerHTML = pools[tipo].map((e, i) => {
    const sizeMB = (e.file.size / 1024 / 1024).toFixed(2);
    const cls  = e.ok ? 'ok'                   : 'error';
    const icon = e.ok ? 'bi-file-earmark-check' : 'bi-file-earmark-x';
    const meta = e.ok ? `${sizeMB} MB`          : `${sizeMB} MB — excede el límite de 10 MB`;
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

// ═══════════════════════════════════════════════════════════════
// SECCIONES DINÁMICAS
// ═══════════════════════════════════════════════════════════════
const secciones = {
  'vehiculos': 'seccion-vehiculos', 'vehículos': 'seccion-vehiculos',
  'maquinaria': 'seccion-maquinaria', 'mobiliario': 'seccion-mobiliario',
  'inmuebles': 'seccion-inmuebles', 'herramientas': 'seccion-herramientas',
  'tics': 'seccion-tics', 'tic': 'seccion-tics',
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

// ═══════════════════════════════════════════════════════════════
// ENVÍO DEL FORMULARIO
// ═══════════════════════════════════════════════════════════════
document.getElementById('activoForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  // 1. Bloquear si hay archivos inválidos
  if (hayArchivosInvalidos()) {
    mostrarAlerta(
      'No se pudieron guardar los archivos. Hay archivos que superan el límite permitido. ' +
      'Retira los archivos marcados en rojo e inténtalo de nuevo.',
      'danger'
    );
    const primerError = document.querySelector('.file-chip.error, .adj-item.error');
    if (primerError) primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  // 2. Modal de confirmación si hay archivos
  const { count, totalMB } = contarArchivosValidos();
  if (count > 0) {
    const confirmar = await mostrarConfirmacion({ archivos: count, totalMB });
    if (!confirmar) return;
  }

  // 3. Deshabilitar botón
  const btn = document.getElementById('btnGuardar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando cambios...';

  // 4. Construir FormData
  const fd = new FormData(this);

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

  // 5. Enviar
  fetch(this.action, { method: 'POST', body: fd })
    .then(res => {
      if (res.redirected) { window.location.href = res.url; return; }
      return res.text().then(() => {
        if (res.status >= 400) {
          mostrarAlerta('Ocurrió un error al guardar. Revisa el log del servidor.', 'danger');
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-floppy"></i> Guardar Cambios';
        } else {
          window.location.href = res.url || 'list_activos.php';
        }
      });
    })
    .catch(err => {
      mostrarAlerta('Error de red: ' + err.message, 'danger');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-floppy"></i> Guardar Cambios';
    });
});
</script>

<?php
include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>



