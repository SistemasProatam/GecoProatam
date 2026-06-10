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
    header("Location: " . BASE_URL . "/activos/qr_invalido.php?razon=departamento");
    exit;
}

require_once __DIR__ . "/../conexion.php";
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
function fetchRow($conn, $sql, $id)
{
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
function labelTipoDoc($tipo)
{
    $map = [
        'factura'             => 'Factura / Comprobante',
        'pedimento'           => 'Pedimento',
        'poliza_seguro_mx'    => 'Póliza de Seguro (MX)',
        'poliza_seguro_usa'   => 'Póliza de Seguro (USA)',
        'manual_usuario'      => 'Manual de Usuario',
        'manual_mantenimiento' => 'Manual de Mantenimiento',
        'catalogo_refacciones' => 'Catálogo de Refacciones',
        'contrato'            => 'Contrato / Escritura',
        'expediente_predial'   => 'Expediente / Predial',
        'extra'               => 'Documento Extra',
    ];
    return $map[$tipo] ?? ucfirst($tipo);
}

function iconoDoc($nombre)
{
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $map = [
        'pdf' => 'fa-regular fa-file-pdf',
        'doc' => 'fa-regular fa-file-word',
        'docx' => 'fa-regular fa-file-word',
        'xls' => 'fa-regular fa-file-excel',
        'xlsx' => 'fa-regular fa-file-excel',
        'txt' => 'fa-regular fa-file-lines',
        'jpg' => 'fa-regular fa-file-image',
        'jpeg' => 'fa-regular fa-file-image',
        'png' => 'fa-regular fa-file-image',
        'gif' => 'fa-regular fa-file-image',
        'webp' => 'fa-regular fa-file-image'
    ];
    return $map[$ext] ?? 'fa-regular fa-file';
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Detalles del Activo | GECO PROATAM</title>

<?php
include __DIR__ . "/../includes/navbar.php";

// Redefine visual-friendly field helper with GECO standards
function renderGecoCampo($label, $valor, $cols = 4)
{
    $v = $valor ?? '—';
    echo '<div class="col-md-' . $cols . ' mb-3">'
        . '<span style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-400, #9ca3af); letter-spacing: 0.05em; margin-bottom: 4px;">' . htmlspecialchars($label) . '</span>'
        . '<span style="display: block; font-size: 0.88rem; font-weight: 500; color: var(--s-800, #0f172a);">' . htmlspecialchars($v) . '</span>'
        . '</div>';
}

function renderCondicionBadge($condicion)
{
    $cond = strtolower($condicion ?? '');
    if ($cond === 'bueno') {
        return '<span class="status-badge status-badge--bueno">Bueno</span>';
    } elseif ($cond === 'regular') {
        return '<span class="status-badge status-badge--regular">Regular</span>';
    } elseif ($cond === 'malo') {
        return '<span class="status-badge status-badge--malo">Malo</span>';
    }
    return '<span class="status-badge">' . htmlspecialchars($condicion ?? '—') . '</span>';
}

function renderEstatusBadge($estatus)
{
    $est = strtolower($estatus ?? '');
    if ($est === 'activo') {
        return '<span class="status-badge status-badge--aprobado">Activo</span>';
    } elseif ($est === 'inactivo') {
        return '<span class="status-badge status-badge--rechazado">Inactivo</span>';
    }
    return '<span class="status-badge">' . htmlspecialchars($estatus ?? '—') . '</span>';
}
?>

<div class="orders-page-container">

    <!-- ===== Cabecera de Página ===== -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <a href="list_activos.php">Activos</a>
                <span class="separator">›</span>
                <span>Detalles del Activo</span>
            </nav>
            <div class="d-flex align-items-center gap-3 flex-wrap mt-1">
                <h1 class="orders-page-title m-0"><?= htmlspecialchars($activo['nombre']) ?></h1>
                <span class="status-badge" style="font-family: monospace; font-size: 0.88rem; font-weight: 600; padding: 4px 10px; border-style: dashed;">
                    <?= htmlspecialchars($activo['codigo']) ?>
                </span>
                <?= renderEstatusBadge($activo['estatus']) ?>
                <?= renderCondicionBadge($activo['condicion']) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="list_activos.php" class="btn-geco-outline">
                <i class="fa-solid fa-arrow-left"></i> Volver al Listado
            </a>
            <a href="edit_activo.php?id=<?= $id ?>" class="btn-geco-secondary">
                <i class="fa-solid fa-pen-to-square"></i> Editar
            </a>
        </div>
    </div>

    <!-- ===== Mensajes de registro ===== -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check"></i> Activo registrado exitosamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- ===== Columna Izquierda: Foto y QR (4 cols) ===== -->
        <div class="col-lg-4">

            <!-- Foto Principal Card -->
            <div class="oc-card mb-4">
                <div class="oc-card-header">
                    <span class="oc-card-header__title"><i class="fa-regular fa-image"></i> Foto Principal</span>
                </div>
                <div class="oc-card-body">
                    <div class="hero-img-wrap">
                        <?php if (!empty($activo['img_foto_principal'])): ?>
                            <img src="<?= htmlspecialchars($activo['img_foto_principal']) ?>"
                                alt="Foto principal"
                                data-bs-toggle="modal" data-bs-target="#modalImgPrincipal"
                                title="Clic para ampliar" />
                        <?php else: ?>
                            <div class="text-center py-5 text-muted" style="width: 100%;">
                                <i class="fa-regular fa-image" style="font-size: 3rem; opacity: 0.4; display: block; margin-bottom: 8px;"></i>
                                <span style="font-size: 0.88rem;">Sin foto principal</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Código QR Card -->
            <?php if (!empty($activo['qr_token'])):
                $qrRutaActual = array_key_exists('qr_ruta_imagen', $activo) ? $activo['qr_ruta_imagen'] : null;

                if (!$qrRutaActual || !file_exists(__DIR__ . '/..' . $qrRutaActual)) {
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
                <div class="oc-card mb-4">
                    <div class="oc-card-body">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($qrUrl) ?>"
                                alt="QR <?= htmlspecialchars($activo['codigo']) ?>"
                                style="width: 90px; height: 90px; border-radius: 8px; border: 1px solid var(--gray-200);"
                                title="Código QR del activo">
                            <div>
                                <div style="font-size: 0.88rem; font-weight: 700; color: var(--s-800); margin-bottom: 4px;">
                                    <i class="fa-solid fa-qrcode"></i> Código QR del Activo
                                </div>
                                <p style="font-size: 0.75rem; color: var(--gray-500); margin-bottom: 8px; line-height: 1.3;">
                                    Escanea este código para acceder rápidamente a la información móvil del activo.
                                </p>
                                <a href="print_qr.php?id=<?= $id ?>" target="_blank" class="btn-geco-outline" style="font-size: 0.75rem; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                    <i class="fa-solid fa-print"></i> Imprimir Etiqueta
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== Columna Derecha: Información y Detalles (8 cols) ===== -->
        <div class="col-lg-8">

            <!-- Información General Card -->
            <div class="oc-card mb-4">
                <div class="oc-card-header">
                    <span class="oc-card-header__title"><i class="fa-solid fa-circle-info"></i> Información General</span>
                </div>
                <div class="oc-card-body">
                    <div class="row g-3">
                        <?php
                        renderGecoCampo('Tipo de Activo',       $activo['tipo_nombre']);
                        renderGecoCampo('Nombre',               $activo['nombre']);
                        renderGecoCampo('Condición',            ucfirst($activo['condicion'] ?? ''));
                        renderGecoCampo('Responsable',          trim(($activo['resp_nombres'] ?? '') . ' ' . ($activo['resp_apellidos'] ?? '')) ?: null);
                        renderGecoCampo('Departamento',         $activo['depto_nombre']);
                        renderGecoCampo('Fecha de Adquisición', $activo['fecha_adquisicion'] ? date('d/m/Y', strtotime($activo['fecha_adquisicion'])) : null);
                        renderGecoCampo('Valor Factura (MXN)',  $activo['valor_factura'] ? '$' . number_format($activo['valor_factura'], 2) : null);
                        renderGecoCampo('Vida Útil',            $activo['vida_util'] ? $activo['vida_util'] . ' año(s)' : null);
                        renderGecoCampo('Ubicación',            $activo['ubicacion'], 4);
                        renderGecoCampo('Fecha de Registro',    $activo['fecha_creacion'] ? date('d/m/Y H:i', strtotime($activo['fecha_creacion'])) : null);
                        ?>
                    </div>
                    <?php if (!empty($activo['notas'])): ?>
                        <div class="mt-4 pt-3 border-top">
                            <span style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-400, #9ca3af); letter-spacing: 0.05em; margin-bottom: 6px;">Notas Generales</span>
                            <span style="display: block; font-size: 0.88rem; font-weight: 500; color: var(--s-800, #0f172a); line-height: 1.5; background: var(--gray-50); padding: 12px; border-radius: 8px; border: 1px solid var(--gray-100);"><?= nl2br(htmlspecialchars($activo['notas'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detalles Específicos por Tipo de Activo -->

            <!-- Vehículo -->
            <?php if ($detalle_vehiculo): ?>
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-truck"></i> Detalles del Vehículo</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="row g-3">
                            <?php
                            renderGecoCampo('Marca',             $detalle_vehiculo['marca']);
                            renderGecoCampo('Modelo',            $detalle_vehiculo['modelo']);
                            renderGecoCampo('Año',               $detalle_vehiculo['anio']);
                            renderGecoCampo('Color',             $detalle_vehiculo['color']);
                            renderGecoCampo('Placa',             $detalle_vehiculo['placa']);
                            renderGecoCampo('VIN / N° Serie',    $detalle_vehiculo['vin']);
                            renderGecoCampo('N° Motor',          $detalle_vehiculo['numero_motor']);
                            renderGecoCampo('Entidad Federativa', $detalle_vehiculo['entidad_federativa']);
                            renderGecoCampo('N° Pedimento',      $detalle_vehiculo['numero_pedimento']);
                            renderGecoCampo('Origen',            ucfirst($detalle_vehiculo['origen'] ?? ''));
                            renderGecoCampo('Gravamen',          ucfirst($detalle_vehiculo['gravamen'] ?? ''));
                            renderGecoCampo('Propietario',       $detalle_vehiculo['nombre_propietario'], 8);
                            ?>

                            <!-- Subsección Seguro MX -->
                            <div class="col-12 mt-4 mb-2">
                                <div class="oc-form-subsection">
                                    <div class="oc-form-subsection__title">
                                        <i class="fa-solid fa-shield-halved"></i> Seguro México
                                    </div>
                                </div>
                            </div>
                            <?php
                            renderGecoCampo('Aseguradora (MX)',   $detalle_vehiculo['nombre_aseguradora_mx']);
                            renderGecoCampo('Teléfono (MX)',      $detalle_vehiculo['telefono_aseguradora_mx']);
                            renderGecoCampo('Vto. Seguro (MX)',   $detalle_vehiculo['fecha_venc_seguro_mx'] ? date('d/m/Y', strtotime($detalle_vehiculo['fecha_venc_seguro_mx'])) : null);
                            ?>

                            <!-- Subsección Seguro USA -->
                            <div class="col-12 mt-4 mb-2">
                                <div class="oc-form-subsection">
                                    <div class="oc-form-subsection__title">
                                        <i class="fa-solid fa-shield-halved"></i> Seguro USA
                                    </div>
                                </div>
                            </div>
                            <?php
                            renderGecoCampo('Aseguradora (USA)',  $detalle_vehiculo['nombre_aseguradora_usa']);
                            renderGecoCampo('Teléfono (USA)',     $detalle_vehiculo['telefono_aseguradora_usa']);
                            renderGecoCampo('Vto. Seguro (USA)',  $detalle_vehiculo['fecha_venc_seguro_usa'] ? date('d/m/Y', strtotime($detalle_vehiculo['fecha_venc_seguro_usa'])) : null);
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Maquinaria -->
            <?php if ($detalle_maquinaria): ?>
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-gears"></i> Detalles de Maquinaria</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="row g-3">
                            <?php
                            renderGecoCampo('Marca',             $detalle_maquinaria['marca']);
                            renderGecoCampo('Modelo',            $detalle_maquinaria['modelo']);
                            renderGecoCampo('N° Serie',          $detalle_maquinaria['numero_serie']);
                            renderGecoCampo('Km / Horómetro',    $detalle_maquinaria['kilometraje'] ? number_format($detalle_maquinaria['kilometraje']) : null);
                            ?>
                        </div>
                        <?php if (!empty($detalle_maquinaria['foto_motor'])): ?>
                            <div class="mt-4 pt-3 border-top">
                                <span style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-400); letter-spacing: 0.05em; margin-bottom: 8px;">Foto Motor</span>
                                <img src="<?= htmlspecialchars($detalle_maquinaria['foto_motor']) ?>"
                                    alt="Foto motor" class="img-thumbnail" style="max-height: 220px; border-radius: 8px; border: 1px solid var(--gray-200);" />
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Mobiliario -->
            <?php if ($detalle_mobiliario): ?>
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-couch"></i> Detalles de Mobiliario</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="row g-3">
                            <?php
                            renderGecoCampo('Marca',            $detalle_mobiliario['marca']);
                            renderGecoCampo('Modelo',           $detalle_mobiliario['modelo']);
                            renderGecoCampo('N° de Items',      $detalle_mobiliario['numero_items']);
                            renderGecoCampo('Medida Aprox.',    $detalle_mobiliario['medida_aprox']);
                            renderGecoCampo('Edificio',         $detalle_mobiliario['edificio']);
                            renderGecoCampo('Área / Depto.',    $detalle_mobiliario['area_departamento']);
                            renderGecoCampo('Dirección',        $detalle_mobiliario['direccion'], 8);
                            ?>
                        </div>
                        <?php if (!empty($detalle_mobiliario['descripcion'])): ?>
                            <div class="mt-4 pt-3 border-top">
                                <span style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-400); letter-spacing: 0.05em; margin-bottom: 6px;">Descripción</span>
                                <span style="display: block; font-size: 0.88rem; font-weight: 500; color: var(--s-800); line-height: 1.5; background: var(--gray-50); padding: 12px; border-radius: 8px; border: 1px solid var(--gray-100);"><?= nl2br(htmlspecialchars($detalle_mobiliario['descripcion'])) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Inmueble -->
            <?php if ($detalle_inmueble): ?>
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-building"></i> Detalles del Inmueble</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="row g-3">
                            <?php
                            renderGecoCampo('Tipo de Inmueble',         $detalle_inmueble['tipo_inmueble']);
                            renderGecoCampo('Tipo de Posesión',         $detalle_inmueble['tipo_posesion']);
                            renderGecoCampo('Uso',                      $detalle_inmueble['uso']);
                            renderGecoCampo('Dirección',                $detalle_inmueble['direccion'], 6);
                            renderGecoCampo('Coordenadas GPS',          $detalle_inmueble['coordenadas'], 6);
                            renderGecoCampo('Sup. Terreno (m²)',        $detalle_inmueble['superficie_terreno'] ? number_format($detalle_inmueble['superficie_terreno'], 2) : null);
                            renderGecoCampo('Sup. Construida (m²)',     $detalle_inmueble['superficie_construida'] ? number_format($detalle_inmueble['superficie_construida'], 2) : null);
                            renderGecoCampo('Niveles',                  $detalle_inmueble['niveles']);
                            renderGecoCampo('Valor Terreno',            $detalle_inmueble['valor_terreno'] ? '$' . number_format($detalle_inmueble['valor_terreno'], 2) : null);
                            renderGecoCampo('Folio RPP',                $detalle_inmueble['folio_rpp']);
                            renderGecoCampo('Predial',                  $detalle_inmueble['predial']);
                            renderGecoCampo('Estatus Legal',            $detalle_inmueble['estatus_legal']);
                            renderGecoCampo('Resp. Administrativo',     $detalle_inmueble['responsable_administrativo'], 8);
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Herramienta -->
            <?php if ($detalle_herramienta): ?>
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-screwdriver-wrench"></i> Detalles de Herramienta</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="row g-3">
                            <?php
                            renderGecoCampo('Marca',         $detalle_herramienta['marca']);
                            renderGecoCampo('Modelo',        $detalle_herramienta['modelo']);
                            renderGecoCampo('N° Serie',      $detalle_herramienta['numero_serie']);
                            renderGecoCampo('Asignación',    $detalle_herramienta['asignacion']);
                            renderGecoCampo('Ubicación',     $detalle_herramienta['ubicacion_fisica']);
                            ?>
                        </div>
                        <?php if (!empty($detalle_herramienta['descripcion'])): ?>
                            <div class="mt-4 pt-3 border-top">
                                <span style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-400); letter-spacing: 0.05em; margin-bottom: 6px;">Descripción</span>
                                <span style="display: block; font-size: 0.88rem; font-weight: 500; color: var(--s-800); line-height: 1.5; background: var(--gray-50); padding: 12px; border-radius: 8px; border: 1px solid var(--gray-100);"><?= nl2br(htmlspecialchars($detalle_herramienta['descripcion'])) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TICs -->
            <?php if ($detalle_tic): ?>
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-laptop"></i> Detalles de TICs</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="row g-3">
                            <?php
                            renderGecoCampo('Marca',            $detalle_tic['marca']);
                            renderGecoCampo('Modelo',           $detalle_tic['modelo']);
                            renderGecoCampo('N° Serie',         $detalle_tic['numero_serie']);
                            renderGecoCampo('Sistema Operativo', $detalle_tic['sistema_operativo']);
                            renderGecoCampo('Procesador',       $detalle_tic['procesador']);
                            renderGecoCampo('RAM',              $detalle_tic['ram']);
                            renderGecoCampo('Almacenamiento',   $detalle_tic['almacenamiento']);
                            renderGecoCampo('Office / Suite',   $detalle_tic['office']);
                            renderGecoCampo('Correo Asignado',  $detalle_tic['correo']);
                            renderGecoCampo('Ubicación Física', $detalle_tic['ubicacion_fisica']);
                            ?>
                        </div>
                        <?php if (!empty($detalle_tic['programas_instalados'])): ?>
                            <div class="mt-4 pt-3 border-top">
                                <span style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-400); letter-spacing: 0.05em; margin-bottom: 6px;">Programas Instalados</span>
                                <span style="display: block; font-size: 0.88rem; font-weight: 500; color: var(--s-800); line-height: 1.5; background: var(--gray-50); padding: 12px; border-radius: 8px; border: 1px solid var(--gray-100);"><?= nl2br(htmlspecialchars($detalle_tic['programas_instalados'])) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($detalle_tic['complementos'])): ?>
                            <div class="mt-3 pt-3 border-top">
                                <span style="display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-400); letter-spacing: 0.05em; margin-bottom: 6px;">Complementos / Accesorios</span>
                                <span style="display: block; font-size: 0.88rem; font-weight: 500; color: var(--s-800); line-height: 1.5; background: var(--gray-50); padding: 12px; border-radius: 8px; border: 1px solid var(--gray-100);"><?= nl2br(htmlspecialchars($detalle_tic['complementos'])) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Documentos Adjuntos -->
            <div class="oc-card mb-4">
                <div class="oc-card-header">
                    <span class="oc-card-header__title"><i class="fa-solid fa-paperclip"></i> Documentos Adjuntos</span>
                </div>
                <div class="oc-card-body">
                    <?php if (!empty($documentos)): ?>
                        <div class="documents-grid">
                            <?php foreach ($documentos as $doc):
                                $ruta = htmlspecialchars($doc['ruta_archivo']);
                                $nombre = htmlspecialchars($doc['nombre_original'] ?? basename($doc['ruta_archivo']));
                                $tipoLabel = htmlspecialchars(labelTipoDoc($doc['tipo_documento']));
                                $iconClass = iconoDoc($doc['nombre_original'] ?? $doc['ruta_archivo']);
                            ?>
                                <div class="document-card">
                                    <div>
                                        <i class="<?= $iconClass ?> document-icon"></i>
                                        <h6><?= $tipoLabel ?></h6>
                                        <span style="font-size: 0.72rem; color: var(--gray-400); word-break: break-all; display: block;"><?= $nombre ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <a href="<?= $ruta ?>" target="_blank" class="btn btn-sm w-100" style="background: rgba(64,118,86,0.06); color: var(--p-700,#2a523a); border: 1px solid rgba(64,118,86,0.15); font-size: 0.78rem; font-weight: 600;">
                                            <i class="fa-regular fa-eye me-1"></i> Ver / Descargar
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="orders-empty-state">
                            <i class="fa-solid fa-circle-info"></i>
                            <p>No hay documentos asociados a este activo.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Galería de Imágenes -->
            <?php
            $imgs_galeria = array_filter($imagenes, fn($im) => $im['tipo_imagen'] !== 'foto_principal');
            $foto_placa   = array_filter($imagenes,  fn($im) => $im['tipo_imagen'] === 'foto_placa');
            $foto_serie   = array_filter($imagenes,  fn($im) => $im['tipo_imagen'] === 'foto_numero_serie');
            $fotos_gen    = array_filter($imagenes,  fn($im) => $im['tipo_imagen'] === 'foto_general');
            ?>
            <?php if (!empty($imgs_galeria)): ?>
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-regular fa-images"></i> Galería de Imágenes</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="gallery-grid">
                            <?php foreach ($fotos_gen as $img): ?>
                                <div class="gallery-card">
                                    <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                                        alt="Foto general"
                                        data-bs-toggle="modal" data-bs-target="#modalGaleria"
                                        onclick="abrirImagen(this)" />
                                    <small>Foto General</small>
                                </div>
                            <?php endforeach; ?>

                            <?php foreach ($foto_placa as $img): ?>
                                <div class="gallery-card">
                                    <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                                        alt="Foto placa"
                                        data-bs-toggle="modal" data-bs-target="#modalGaleria"
                                        onclick="abrirImagen(this)" />
                                    <small>Placa</small>
                                </div>
                            <?php endforeach; ?>

                            <?php foreach ($foto_serie as $img): ?>
                                <div class="gallery-card">
                                    <img src="<?= htmlspecialchars($img['ruta_archivo']) ?>"
                                        alt="N° Serie"
                                        data-bs-toggle="modal" data-bs-target="#modalGaleria"
                                        onclick="abrirImagen(this)" />
                                    <small>N° Serie</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Imagen Principal -->
<?php if (!empty($activo['img_foto_principal'])): ?>
    <div class="modal fade" id="modalImgPrincipal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img src="<?= htmlspecialchars($activo['img_foto_principal']) ?>"
                        alt="Foto principal" class="img-fluid" style="max-height:85vh; border-radius: 8px;" />
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Galería -->
<div class="modal fade" id="modalGaleria" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalGaleriaImg" src="" alt="Imagen" class="img-fluid" style="max-height:85vh; border-radius: 8px;" />
            </div>
        </div>
    </div>
</div>

<script>
    function abrirImagen(el) {
        document.getElementById('modalGaleriaImg').src = el.src;
    }
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>