<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

// ─── CONTROL DE ACCESO POR DEPARTAMENTO ─────────────────────

$departamento_usuario = $_SESSION['departamento'] ?? '';

$departamentos_permitidos = [
    'Director General',
    'Subdirector General',
    'Coordinador de Control de Documentos y Facturación',
    'Gerente de Seguridad Salud y Medio Ambiente',
    'Tecnico de Sistemas'
];

if (!in_array($departamento_usuario, $departamentos_permitidos)) {
    header("Location: /activos/qr_invalido.php?razon=departamento");
    exit;
}

include(__DIR__ . "/../conexion.php");
require_once __DIR__ . "/qr_generator.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: list_activos.php");
    exit;
}

// ─── Activo principal ────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT a.*, at.nombre AS tipo_nombre, at.prefijo,
            u.nombres AS resp_nombres, u.apellidos AS resp_apellidos,
            d.nombre AS depto_nombre
     FROM activos a
     LEFT JOIN activo_tipos at ON at.id = a.tipo_id
     LEFT JOIN usuarios u ON u.id = a.responsable_id
     LEFT JOIN departamentos d ON d.id = a.departamento_id
     WHERE a.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$activo = $stmt->get_result()->fetch_assoc();

if (!$activo) {
    header("Location: list_activos.php?error=no_encontrado");
    exit;
}

$tipo_norm = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($activo['tipo_nombre'] ?? ''));

// ─── Detalles específicos ────────────────────────────────────────────────────
function fetchRow($conn, $sql, $id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$detalle_vehiculo    = null;
$detalle_maquinaria  = null;
$detalle_mobiliario  = null;
$detalle_inmueble    = null;
$detalle_herramienta = null;
$detalle_tic         = null;

if (str_contains($tipo_norm, 'vehiculo')) {
    $detalle_vehiculo = fetchRow($conn, "SELECT * FROM vehiculos_detalle WHERE activo_id = ?", $id);
}
if (str_contains($tipo_norm, 'maquinaria')) {
    $detalle_maquinaria = fetchRow($conn, "SELECT * FROM maquinaria_detalle WHERE activo_id = ?", $id);
}
if (str_contains($tipo_norm, 'mobiliario')) {
    $detalle_mobiliario = fetchRow($conn, "SELECT * FROM mobiliario_detalle WHERE activo_id = ?", $id);
}
if (str_contains($tipo_norm, 'inmueble')) {
    $detalle_inmueble = fetchRow($conn, "SELECT * FROM inmuebles_detalle WHERE activo_id = ?", $id);
}
if (str_contains($tipo_norm, 'herramienta')) {
    $detalle_herramienta = fetchRow($conn, "SELECT * FROM herramientas_detalle WHERE activo_id = ?", $id);
}
if (str_contains($tipo_norm, 'tic')) {
    $detalle_tic = fetchRow($conn, "SELECT * FROM tics_detalle WHERE activo_id = ?", $id);
}

// ─── Documentos ─────────────────────────────────────────────────────────────
$stmt_docs = $conn->prepare("SELECT * FROM activos_documentos WHERE activo_id = ? ORDER BY fecha_subida ASC");
$stmt_docs->bind_param("i", $id);
$stmt_docs->execute();
$documentos = $stmt_docs->get_result()->fetch_all(MYSQLI_ASSOC);

// ─── Imágenes ────────────────────────────────────────────────────────────────
$stmt_imgs = $conn->prepare("SELECT * FROM activos_imagenes WHERE activo_id = ? ORDER BY fecha_subida ASC");
$stmt_imgs->bind_param("i", $id);
$stmt_imgs->execute();
$imagenes = $stmt_imgs->get_result()->fetch_all(MYSQLI_ASSOC);

// ─── Helpers ────────────────────────────────────────────────────────────────
function campo($label, $valor, $cols = 4) {
    $v = $valor ?? '—';
    echo '<div class="col-md-' . $cols . ' detail-field">'
       . '<span class="detail-label">' . htmlspecialchars($label) . '</span>'
       . '<span class="detail-value">' . htmlspecialchars($v) . '</span>'
       . '</div>';
}

function iconoDoc($nombre) {
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $map = ['pdf'=>'bi-file-pdf','doc'=>'bi-file-word','docx'=>'bi-file-word',
            'xls'=>'bi-file-excel','xlsx'=>'bi-file-excel','txt'=>'bi-file-text',
            'jpg'=>'bi-file-image','jpeg'=>'bi-file-image','png'=>'bi-file-image',
            'gif'=>'bi-file-image','webp'=>'bi-file-image'];
    return $map[$ext] ?? 'bi-file-earmark';
}

function labelTipoDoc($tipo) {
    $map = [
        'factura'             => 'Factura / Comprobante',
        'pedimento'           => 'Pedimento',
        'poliza_seguro_mx'    => 'Póliza de Seguro (MX)',
        'poliza_seguro_usa'   => 'Póliza de Seguro (USA)',
        'manual_usuario'      => 'Manual de Usuario',
        'manual_mantenimiento'=> 'Manual de Mantenimiento',
        'catalogo_refacciones'=> 'Catálogo de Refacciones',
        'contrato'            => 'Contrato / Escritura',
        'expediente_predial'   => 'Expediente / Predial',
        'extra'               => 'Documento Extra',
    ];
    return $map[$tipo] ?? ucfirst($tipo);
}

$condicion_badges = [
    'bueno'   => 'success',
    'regular' => 'warning',
    'malo'    => 'danger',
];
$estatus_badges = [
    'activo'  => 'success',
    'inactivo'=> 'secondary',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Activo – <?= htmlspecialchars($activo['codigo']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous" />
    <link rel="icon" href="/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/assets/styles/new_order.css" />
  <link rel="stylesheet" href="/assets/styles/details_mobile.css" />
  <style>
    /* ── Hero image ── */
    .hero-img-wrap {
      width: 100%;
      max-height: 380px;
      overflow: hidden;
      border-radius: 14px;
      margin-bottom: 28px;
      box-shadow: 0 6px 30px rgba(0,0,0,.18);
      background: #e9ecef;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .hero-img-wrap img {
      width: 100%;
      height: 380px;
      object-fit: cover;
      border-radius: 14px;
      transition: transform .35s ease;
    }
    .hero-img-wrap img:hover { transform: scale(1.02); }
    .hero-img-placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 200px;
      color: #adb5bd;
    }
    .hero-img-placeholder i { font-size: 4rem; }

    /* ── Secciones ── */
    .detail-section {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 22px 24px;
      margin-bottom: 22px;
    }
    .detail-section .section-title {
      font-size: 1rem;
      font-weight: 700;
      color: #113456;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 10px;
      margin-bottom: 16px;
    }
    .detail-field {
      margin-bottom: 14px;
    }
    .detail-label {
      display: block;
      font-size: .72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6c757d;
      margin-bottom: 2px;
    }
    .detail-value {
      display: block;
      font-size: .95rem;
      color: #212529;
    }

    /* ── Galería ── */
    .gallery-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 10px;
    }
    .gallery-item img {
      width: 100%;
      height: 120px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #dee2e6;
      cursor: pointer;
      transition: transform .2s, box-shadow .2s;
    }
    .gallery-item img:hover { transform: scale(1.04); box-shadow: 0 4px 14px rgba(0,0,0,.18); }
    .gallery-item small { font-size: .72rem; color: #6c757d; display: block; text-align: center; margin-top: 4px; }

    /* ── Docs ── */
    .doc-item-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      margin-bottom: 8px;
      background: #f8fafc;
    }
    .doc-item-row i { font-size: 0.85rem; }
    .doc-item-row .doc-info { flex: 1; }
    .doc-item-row .doc-label { font-size: .75rem; color: #6c757d; }
    .doc-item-row .doc-name  { font-size: .9rem;  color: #212529; font-weight: 500; }

    /* ── Código badge ── */
    .codigo-badge {
      font-family: monospace;
      font-size: 1.1rem;
      background: #f0f4f8;
      border: 1px dashed #adb5bd;
      border-radius: 6px;
      padding: 4px 12px;
      color: #113456;
      letter-spacing: 1px;
    }

    /* ── Acciones ── */
    .action-bar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 22px;
    }
  </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/navbar.php"; ?>

<!-- HERO -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="/activos/list_activos.php">Registro de Activos</a>
      <span>/</span>
      <span><?= htmlspecialchars($activo['codigo']) ?></span>
    </div>
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title"><?= htmlspecialchars($activo['nombre']) ?></h1>
        <div class="d-flex align-items-center gap-3 mt-2 flex-wrap">
          <span class="codigo-badge"><i class="bi bi-upc-scan"></i> <?= htmlspecialchars($activo['codigo']) ?></span>
          <span class="badge bg-<?= $estatus_badges[$activo['estatus']] ?? 'secondary' ?> fs-6">
            <?= ucfirst(htmlspecialchars($activo['estatus'] ?? '')) ?>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CONTENIDO -->
<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle-fill"></i> Activo registrado exitosamente.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!empty($activo['qr_token'])): ?>
      <!-- ══ QR DEL ACTIVO ═════════════════════════════════════════════════ -->
      <div class="detail-section">
  <div class="row align-items-center g-4">

    <!-- QR -->
    <div class="col-auto">
      <?php
        $qrRutaActual = array_key_exists('qr_ruta_imagen', $activo)
            ? $activo['qr_ruta_imagen']
            : null;

        if (!$qrRutaActual || !file_exists($_SERVER['DOCUMENT_ROOT'] . $qrRutaActual)) {
            $nuevaRuta = QRGenerator::generarYGuardar($activo['qr_token']);

            if ($nuevaRuta) {
                try {
                    $stmt_qrupd = $conn->prepare("UPDATE activos SET qr_ruta_imagen=? WHERE id=?");
                    $stmt_qrupd->bind_param("si", $nuevaRuta, $id);
                    $stmt_qrupd->execute();
                } catch (Throwable $e) {
                    error_log("QR update skipped: " . $e->getMessage());
                }

                $qrRutaActual = $nuevaRuta;
            }
        }

        $qrUrl = QRGenerator::getQRUrl($activo['qr_token'], $qrRutaActual, 200);
      ?>

      <img src="<?= htmlspecialchars($qrUrl) ?>"
           alt="QR <?= htmlspecialchars($activo['codigo']) ?>"
           class="qr-img">
    </div>

    <!-- TEXTO Y OPCIONES -->
    <div class="col">

      <div class="section-title mb-2" style="border:none;padding:0;">
        <i class="bi bi-qr-code"></i> Código QR del Activo
      </div>

      <p class="text-muted mb-3" style="font-size:.88rem;">
        Escanea este código para acceder rápidamente a la información del activo
        desde cualquier dispositivo móvil.
      </p>

      <div class="d-flex flex-wrap gap-2">

        <a href="print_qr.php?id=<?= $id ?>"
           target="_blank"
           class="btn btn-sm btn-outline-dark">
          <i class="bi bi-download"></i> Descargar etiqueta
        </a>

      </div>
    </div>
  </div>
</div>
      <?php endif; ?>

      <!-- ══ IMAGEN PRINCIPAL ══════════════════════════════════════════════ -->
      <div class="hero-img-wrap">
        <?php if (!empty($activo['img_foto_principal'])): ?>
          <img src="<?= htmlspecialchars($activo['img_foto_principal']) ?>"
               alt="Foto principal de <?= htmlspecialchars($activo['nombre']) ?>"
               data-bs-toggle="modal" data-bs-target="#modalImgPrincipal"
               title="Clic para ampliar" />
        <?php else: ?>
          <div class="hero-img-placeholder">
            <i class="bi bi-image"></i>
            <p class="mt-2 mb-0">Sin foto principal</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- ══ INFORMACIÓN GENERAL ═══════════════════════════════════════════ -->
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-info-circle"></i> Información General</div>
        <div class="row">
          <?php campo('Tipo de Activo',       $activo['tipo_nombre']);     ?>
          <?php campo('Nombre',               $activo['nombre']);          ?>
          <?php campo('Condición',            ucfirst($activo['condicion'] ?? ''));  ?>
          <?php campo('Responsable',          trim(($activo['resp_nombres'] ?? '') . ' ' . ($activo['resp_apellidos'] ?? '')) ?: null); ?>
          <?php campo('Departamento',         $activo['depto_nombre']);    ?>
          <?php campo('Fecha de Adquisición', $activo['fecha_adquisicion'] ? date('d/m/Y', strtotime($activo['fecha_adquisicion'])) : null); ?>
          <?php campo('Valor Factura (MXN)',  $activo['valor_factura'] ? '$' . number_format($activo['valor_factura'], 2) : null); ?>
          <?php campo('Vida Útil',            $activo['vida_util'] ? $activo['vida_util'] . ' año(s)' : null); ?>
          <?php campo('Ubicación',            $activo['ubicacion'],  4); ?>
          <?php campo('Fecha de Registro',    $activo['fecha_creacion'] ? date('d/m/Y H:i', strtotime($activo['fecha_creacion'])) : null); ?>
        </div>
        <?php if (!empty($activo['notas'])): ?>
          <div class="mt-2">
            <span class="detail-label">Notas Generales</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($activo['notas'])) ?></span>
          </div>
        <?php endif; ?>
      </div>

      <!-- ══ DETALLES VEHÍCULO ══════════════════════════════════════════════ -->
      <?php if ($detalle_vehiculo): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-truck"></i> Detalles del Vehículo</div>
        <div class="row">
          <?php campo('Marca',             $detalle_vehiculo['marca']);           ?>
          <?php campo('Modelo',            $detalle_vehiculo['modelo']);          ?>
          <?php campo('Año',               $detalle_vehiculo['anio']);            ?>
          <?php campo('Color',             $detalle_vehiculo['color']);           ?>
          <?php campo('Placa',             $detalle_vehiculo['placa']);           ?>
          <?php campo('VIN / N° Serie',    $detalle_vehiculo['vin']);             ?>
          <?php campo('N° Motor',          $detalle_vehiculo['numero_motor']);    ?>
          <?php campo('Entidad Federativa',$detalle_vehiculo['entidad_federativa']); ?>
          <?php campo('N° Pedimento',      $detalle_vehiculo['numero_pedimento']); ?>
          <?php campo('Origen',            ucfirst($detalle_vehiculo['origen'] ?? '')); ?>
          <?php campo('Gravamen',          ucfirst($detalle_vehiculo['gravamen'] ?? '')); ?>
          <?php campo('Propietario',       $detalle_vehiculo['nombre_propietario'], 8); ?>
        </div>

        <div class="row mt-3">
          <div class="col-12"><strong class="text-muted" style="font-size:.8rem;">SEGURO MÉXICO</strong></div>
          <?php campo('Aseguradora (MX)',   $detalle_vehiculo['nombre_aseguradora_mx']);    ?>
          <?php campo('Teléfono (MX)',      $detalle_vehiculo['telefono_aseguradora_mx']);  ?>
          <?php campo('Vto. Seguro (MX)',   $detalle_vehiculo['fecha_venc_seguro_mx'] ? date('d/m/Y', strtotime($detalle_vehiculo['fecha_venc_seguro_mx'])) : null); ?>
        </div>
        <div class="row mt-2">
          <div class="col-12"><strong class="text-muted" style="font-size:.8rem;">SEGURO USA</strong></div>
          <?php campo('Aseguradora (USA)',  $detalle_vehiculo['nombre_aseguradora_usa']);   ?>
          <?php campo('Teléfono (USA)',     $detalle_vehiculo['telefono_aseguradora_usa']); ?>
          <?php campo('Vto. Seguro (USA)',  $detalle_vehiculo['fecha_venc_seguro_usa'] ? date('d/m/Y', strtotime($detalle_vehiculo['fecha_venc_seguro_usa'])) : null); ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ══ DETALLES MAQUINARIA ════════════════════════════════════════════ -->
      <?php if ($detalle_maquinaria): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-gear-wide-connected"></i> Detalles de Maquinaria</div>
        <div class="row">
          <?php campo('Marca',             $detalle_maquinaria['marca']);        ?>
          <?php campo('Modelo',            $detalle_maquinaria['modelo']);       ?>
          <?php campo('N° Serie',          $detalle_maquinaria['numero_serie']); ?>
          <?php campo('Km / Horómetro',    $detalle_maquinaria['kilometraje'] ? number_format($detalle_maquinaria['kilometraje']) : null); ?>
        </div>
        <?php if (!empty($detalle_maquinaria['foto_motor'])): ?>
          <div class="mt-3">
            <span class="detail-label">Foto Motor</span>
            <img src="<?= htmlspecialchars($detalle_maquinaria['foto_motor']) ?>"
                 alt="Foto motor" class="img-thumbnail mt-1" style="max-height:200px;" />
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- ══ DETALLES MOBILIARIO ════════════════════════════════════════════ -->
      <?php if ($detalle_mobiliario): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-archive"></i> Detalles de Mobiliario</div>
        <div class="row">
          <?php campo('Marca',            $detalle_mobiliario['marca']);             ?>
          <?php campo('Modelo',           $detalle_mobiliario['modelo']);            ?>
          <?php campo('N° de Items',      $detalle_mobiliario['numero_items']);      ?>
          <?php campo('Medida Aprox.',    $detalle_mobiliario['medida_aprox']);      ?>
          <?php campo('Edificio',         $detalle_mobiliario['edificio']);          ?>
          <?php campo('Área / Depto.',    $detalle_mobiliario['area_departamento']); ?>
          <?php campo('Dirección',        $detalle_mobiliario['direccion'], 8);      ?>
        </div>
        <?php if (!empty($detalle_mobiliario['descripcion'])): ?>
          <div class="mt-2">
            <span class="detail-label">Descripción</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($detalle_mobiliario['descripcion'])) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- ══ DETALLES INMUEBLE ══════════════════════════════════════════════ -->
      <?php if ($detalle_inmueble): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-building"></i> Detalles del Inmueble</div>
        <div class="row">
          <?php campo('Tipo de Inmueble',         $detalle_inmueble['tipo_inmueble']);             ?>
          <?php campo('Tipo de Posesión',         $detalle_inmueble['tipo_posesion']);             ?>
          <?php campo('Uso',                      $detalle_inmueble['uso']);                       ?>
          <?php campo('Dirección',                $detalle_inmueble['direccion'], 6);              ?>
          <?php campo('Coordenadas GPS',          $detalle_inmueble['coordenadas'], 6);            ?>
          <?php campo('Sup. Terreno (m²)',        $detalle_inmueble['superficie_terreno'] ? number_format($detalle_inmueble['superficie_terreno'], 2) : null); ?>
          <?php campo('Sup. Construida (m²)',     $detalle_inmueble['superficie_construida'] ? number_format($detalle_inmueble['superficie_construida'], 2) : null); ?>
          <?php campo('Niveles',                  $detalle_inmueble['niveles']);                   ?>
          <?php campo('Valor Terreno',            $detalle_inmueble['valor_terreno'] ? '$' . number_format($detalle_inmueble['valor_terreno'], 2) : null); ?>
          <?php campo('Folio RPP',                $detalle_inmueble['folio_rpp']);                 ?>
          <?php campo('Predial',                  $detalle_inmueble['predial']);                   ?>
          <?php campo('Estatus Legal',            $detalle_inmueble['estatus_legal']);             ?>
          <?php campo('Resp. Administrativo',     $detalle_inmueble['responsable_administrativo'], 8); ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ══ DETALLES HERRAMIENTA ═══════════════════════════════════════════ -->
      <?php if ($detalle_herramienta): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-tools"></i> Detalles de Herramienta</div>
        <div class="row">
          <?php campo('Marca',         $detalle_herramienta['marca']);            ?>
          <?php campo('Modelo',        $detalle_herramienta['modelo']);           ?>
          <?php campo('N° Serie',      $detalle_herramienta['numero_serie']);     ?>
          <?php campo('Asignación',    $detalle_herramienta['asignacion']);       ?>
          <?php campo('Ubicación',     $detalle_herramienta['ubicacion_fisica']); ?>
        </div>
        <?php if (!empty($detalle_herramienta['descripcion'])): ?>
          <div class="mt-2">
            <span class="detail-label">Descripción</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($detalle_herramienta['descripcion'])) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- ══ DETALLES TICs ══════════════════════════════════════════════════ -->
      <?php if ($detalle_tic): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-laptop"></i> Detalles de TICs</div>
        <div class="row">
          <?php campo('Marca',            $detalle_tic['marca']);              ?>
          <?php campo('Modelo',           $detalle_tic['modelo']);             ?>
          <?php campo('N° Serie',         $detalle_tic['numero_serie']);       ?>
          <?php campo('Sistema Operativo',$detalle_tic['sistema_operativo']);  ?>
          <?php campo('Procesador',       $detalle_tic['procesador']);         ?>
          <?php campo('RAM',              $detalle_tic['ram']);                ?>
          <?php campo('Almacenamiento',   $detalle_tic['almacenamiento']);     ?>
          <?php campo('Office / Suite',   $detalle_tic['office']);             ?>
          <?php campo('Correo Asignado',  $detalle_tic['correo']);             ?>
          <?php campo('Ubicación Física', $detalle_tic['ubicacion_fisica']);   ?>
        </div>
        <?php if (!empty($detalle_tic['programas_instalados'])): ?>
          <div class="mt-2">
            <span class="detail-label">Programas Instalados</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($detalle_tic['programas_instalados'])) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($detalle_tic['complementos'])): ?>
          <div class="mt-2">
            <span class="detail-label">Complementos / Accesorios</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($detalle_tic['complementos'])) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- ══ DOCUMENTOS ════════════════════════════════════════════════════ -->
      <?php if (!empty($documentos)): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-paperclip"></i> Documentos</div>
        <?php foreach ($documentos as $doc): ?>
          <div class="doc-item-row">
            <i class="bi <?= iconoDoc($doc['nombre_original'] ?? $doc['ruta_archivo']) ?>"></i>
            <div class="doc-info">
              <div class="doc-label"><?= htmlspecialchars(labelTipoDoc($doc['tipo_documento'])) ?></div>
              <div class="doc-name"><?= htmlspecialchars($doc['nombre_original'] ?? basename($doc['ruta_archivo'])) ?></div>
            </div>
            <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank"
               class="btn btn-sm btn-outline-dark" title="Abrir documento">
              <i class="bi bi-eye"></i>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- ══ GALERÍA DE IMÁGENES ════════════════════════════════════════════ -->
      <?php
      $imgs_galeria = array_filter($imagenes, fn($im) => $im['tipo_imagen'] !== 'foto_principal');
      $foto_placa   = array_filter($imagenes,  fn($im) => $im['tipo_imagen'] === 'foto_placa');
      $foto_serie   = array_filter($imagenes,  fn($im) => $im['tipo_imagen'] === 'foto_numero_serie');
      $fotos_gen    = array_filter($imagenes,  fn($im) => $im['tipo_imagen'] === 'foto_general');
      ?>
      <?php if (!empty($imgs_galeria)): ?>
      <div class="detail-section">
        <div class="section-title"><i class="bi bi-card-image"></i> Imágenes</div>
        <div class="gallery-grid">
          <?php foreach ($fotos_gen as $img): ?>
            <div class="gallery-item">
              <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                   alt="Foto general"
                   data-bs-toggle="modal" data-bs-target="#modalGaleria"
                   data-src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                   onclick="abrirImagen(this)" />
              <small>Foto general</small>
            </div>
          <?php endforeach; ?>
          <?php foreach ($foto_placa as $img): ?>
            <div class="gallery-item">
              <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                   alt="Foto placa"
                   data-bs-toggle="modal" data-bs-target="#modalGaleria"
                   onclick="abrirImagen(this)" />
              <small>Placa</small>
            </div>
          <?php endforeach; ?>
          <?php foreach ($foto_serie as $img): ?>
            <div class="gallery-item">
              <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                   alt="N° Serie"
                   data-bs-toggle="modal" data-bs-target="#modalGaleria"
                   onclick="abrirImagen(this)" />
              <small>N° Serie</small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /form-body -->
  </div><!-- /form-container -->
</div><!-- /content-wrapper -->

<!-- Modal imagen principal -->
<?php if (!empty($activo['img_foto_principal'])): ?>
<div class="modal fade" id="modalImgPrincipal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark border-0">
      <div class="modal-header border-0">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-0">
        <img src="<?= htmlspecialchars($activo['img_foto_principal']) ?>"
             alt="Foto principal" class="img-fluid" style="max-height:85vh;" />
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal galería -->
<div class="modal fade" id="modalGaleria" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark border-0">
      <div class="modal-header border-0">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-0">
        <img id="modalGaleriaImg" src="" alt="Imagen" class="img-fluid" style="max-height:85vh;" />
      </div>
    </div>
  </div>
</div>

<!-- Botón volver -->
<div class="fab-container-backbtn">
  <a onclick="history.back()" class="fab-button-backbtn gray">
    <i class="bi bi-arrow-left"></i>
    <span class="fab-tooltip-backbtn">Volver</span>
  </a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
  crossorigin="anonymous"></script>
<script>
  function abrirImagen(el) {
    document.getElementById('modalGaleriaImg').src = el.src;
  }
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>