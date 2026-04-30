<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");
require_once __DIR__ . "/qr_generator.php";

// ─── CONSTANTES ─────────────────────────────────────────────────────────────
define('MAX_DOCUMENTO_MB', 10);           // 10 MB por documento normal
define('MAX_CATALOGO_MB', 1024);          // 1 GB para catálogo de refacciones
define('MAX_IMAGEN_MB', 10);              // 10 MB para imágenes

// ─── Helpers ───────────────────────────────────────────────────────────────

function sanitize($val) {
    return isset($val) ? trim($val) : null;
}

function nullIfEmpty($val) {
    $v = sanitize($val);
    return ($v === '' || $v === null) ? null : $v;
}

function subirArchivo($inputName, $carpetaDestino, $maxMB = MAX_DOCUMENTO_MB) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$inputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Error subiendo {$inputName}: código " . $file['error']);
        return null;
    }
    
    $maxBytes = $maxMB * 1024 * 1024;
    if ($maxMB > 0 && $file['size'] > $maxBytes) {
        $sizeMB = round($file['size'] / 1024 / 1024, 2);
        error_log("Archivo {$inputName} supera {$maxMB} MB. Tamaño: {$sizeMB} MB");
        $_SESSION['upload_errors'][] = "El archivo '" . $file['name'] . "' excede el límite de {$maxMB} MB";
        return null;
    }
    
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nombre   = uniqid('', true) . '.' . $ext;
    $dirBase  = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $carpetaDestino . '/';
    if (!is_dir($dirBase)) {
        mkdir($dirBase, 0775, true);
    }
    $destino = $dirBase . $nombre;
    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        error_log("No se pudo mover el archivo {$inputName} a {$destino}");
        return null;
    }
    return '/uploads/' . $carpetaDestino . '/' . $nombre;
}

function subirArchivoMultiple($inputName, $carpetaDestino, $maxMB = MAX_IMAGEN_MB) {
    $rutas = [];
    if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'][0])) {
        return $rutas;
    }
    $total = count($_FILES[$inputName]['name']);
    for ($i = 0; $i < $total; $i++) {
        if ($_FILES[$inputName]['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($maxMB > 0 && $_FILES[$inputName]['size'][$i] > $maxMB * 1024 * 1024) {
            error_log("Archivo múltiple supera {$maxMB} MB: " . $_FILES[$inputName]['name'][$i]);
            continue;
        }
        $ext     = strtolower(pathinfo($_FILES[$inputName]['name'][$i], PATHINFO_EXTENSION));
        $nombre  = uniqid('', true) . '.' . $ext;
        $dirBase = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $carpetaDestino . '/';
        if (!is_dir($dirBase)) {
            mkdir($dirBase, 0775, true);
        }
        $destino = $dirBase . $nombre;
        if (move_uploaded_file($_FILES[$inputName]['tmp_name'][$i], $destino)) {
            $rutas[] = '/uploads/' . $carpetaDestino . '/' . $nombre;
        }
    }
    return $rutas;
}

// ─── Generar código único ───────────────────────────────────────────────────

function generarCodigo($conn, $tipo_id) {
    $stmt = $conn->prepare("SELECT prefijo FROM activo_tipos WHERE id = ?");
    $stmt->bind_param("i", $tipo_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $prefijo = $row ? strtoupper($row['prefijo']) : 'ACT';

    $stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM activos WHERE tipo_id = ?");
    $stmt2->bind_param("i", $tipo_id);
    $stmt2->execute();
    $row2 = $stmt2->get_result()->fetch_assoc();
    $siguiente = ($row2['total'] ?? 0) + 1;

    return $prefijo . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
}

// ─── Recoger POST ───────────────────────────────────────────────────────────

$tipo_id         = (int)($_POST['tipo_id'] ?? 0);
$nombre          = sanitize($_POST['nombre'] ?? '');
$condicion       = nullIfEmpty($_POST['condicion']     ?? '');
$responsable_id  = nullIfEmpty($_POST['responsable_id'] ?? '');
$departamento_id = nullIfEmpty($_POST['departamento_id'] ?? '');
$fecha_adq       = nullIfEmpty($_POST['fecha_adquisicion'] ?? '');
$valor_factura   = nullIfEmpty($_POST['valor_factura'] ?? '');
$vida_util       = nullIfEmpty($_POST['vida_util']     ?? '');
$ubicacion       = nullIfEmpty($_POST['ubicacion']     ?? '');
$estatus         = sanitize($_POST['estatus']          ?? 'activo');
$notas           = nullIfEmpty($_POST['notas']         ?? '');

if (!$tipo_id || $nombre === '') {
    header("Location: new_activo.php?error=campos_requeridos");
    exit;
}

$codigo    = generarCodigo($conn, $tipo_id);
$qr_token  = QRGenerator::generarToken();

// ─── Subir imagen principal ─────────────────────────────────────────────────

$img_principal = subirArchivo('img_foto_principal', 'fotos', MAX_IMAGEN_MB);

// ─── Insertar activo principal ──────────────────────────────────────────────

$sql = "INSERT INTO activos
        (tipo_id, codigo, nombre, condicion, responsable_id, departamento_id,
         fecha_adquisicion, valor_factura, vida_util, ubicacion, estatus, notas,
         img_foto_principal, qr_token)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "issssssdisssss",
    $tipo_id, $codigo, $nombre, $condicion,
    $responsable_id, $departamento_id,
    $fecha_adq, $valor_factura, $vida_util,
    $ubicacion, $estatus, $notas, $img_principal, $qr_token
);

if (!$stmt->execute()) {
    error_log("Error insertando activo: " . $stmt->error);
    header("Location: new_activo.php?error=db");
    exit;
}

$activo_id = $conn->insert_id;

// ─── Generar y guardar imagen QR ────────────────────────────────────────────

$qr_ruta = QRGenerator::generarYGuardar($qr_token);
if ($qr_ruta) {
    $stmt_qr = $conn->prepare("UPDATE activos SET qr_ruta_imagen = ? WHERE id = ?");
    $stmt_qr->bind_param("si", $qr_ruta, $activo_id);
    $stmt_qr->execute();
}

// ─── Detalles según tipo ────────────────────────────────────────────────────

$stmt_tipo = $conn->prepare("SELECT nombre FROM activo_tipos WHERE id = ?");
$stmt_tipo->bind_param("i", $tipo_id);
$stmt_tipo->execute();
$tipo_row   = $stmt_tipo->get_result()->fetch_assoc();
$tipo_lower = strtolower($tipo_row['nombre'] ?? '');

function normTipo($s) {
    return iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($s));
}
$tipo_norm = normTipo($tipo_lower);

// ── Vehículos ────────────────────────────────────────────────────────────────
if (str_contains($tipo_norm, 'vehiculo') || str_contains($tipo_norm, 'vehiculos')) {
    $sql_v = "INSERT INTO vehiculos_detalle
              (activo_id, marca, modelo, anio, color, placa, vin, numero_motor,
               entidad_federativa, numero_pedimento, origen, gravamen, nombre_propietario,
               nombre_aseguradora_mx, telefono_aseguradora_mx, fecha_venc_seguro_mx,
               nombre_aseguradora_usa, telefono_aseguradora_usa, fecha_venc_seguro_usa)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt_v = $conn->prepare($sql_v);

    $v_marca            = nullIfEmpty($_POST['v_marca']            ?? '');
    $v_modelo           = nullIfEmpty($_POST['v_modelo']           ?? '');
    $v_anio             = nullIfEmpty($_POST['v_anio']             ?? '');
    $v_color            = nullIfEmpty($_POST['v_color']            ?? '');
    $v_placa            = nullIfEmpty($_POST['v_placa']            ?? '');
    $v_vin              = nullIfEmpty($_POST['v_vin']              ?? '');
    $v_numero_motor     = nullIfEmpty($_POST['v_numero_motor']     ?? '');
    $v_entidad          = nullIfEmpty($_POST['v_entidad_federativa'] ?? '');
    $v_pedimento        = nullIfEmpty($_POST['v_numero_pedimento'] ?? '');
    $v_origen           = nullIfEmpty($_POST['v_origen']           ?? '');
    $v_gravamen         = nullIfEmpty($_POST['v_gravamen']         ?? '');
    $v_propietario      = nullIfEmpty($_POST['v_nombre_propietario'] ?? '');

    $v_aseg_mx_nombre   = nullIfEmpty($_POST['v_nombre_aseguradora_mx']      ?? '');
    $v_aseg_mx_tel      = nullIfEmpty($_POST['v_telefono_aseguradora_mx']    ?? '');
    $v_aseg_mx_fecha    = nullIfEmpty($_POST['v_fecha_vencimiento_seguro_mx'] ?? '');
    $v_aseg_usa_nombre  = nullIfEmpty($_POST['v_nombre_aseguradora_usa']     ?? '');
    $v_aseg_usa_tel     = nullIfEmpty($_POST['v_telefono_aseguradora_usa']   ?? '');
    $v_aseg_usa_fecha   = nullIfEmpty($_POST['v_fecha_vencimiento_seguro_usa'] ?? '');

    $stmt_v->bind_param(
        "issississssssssssss",
        $activo_id, $v_marca, $v_modelo, $v_anio, $v_color, $v_placa, $v_vin,
        $v_numero_motor, $v_entidad, $v_pedimento, $v_origen, $v_gravamen,
        $v_propietario, $v_aseg_mx_nombre, $v_aseg_mx_tel, $v_aseg_mx_fecha,
        $v_aseg_usa_nombre, $v_aseg_usa_tel, $v_aseg_usa_fecha
    );
    $stmt_v->execute();
}

// ── Maquinaria ────────────────────────────────────────────────────────────────
if (str_contains($tipo_norm, 'maquinaria')) {
    $foto_motor = subirArchivo('m_foto_motor', 'maquinaria', MAX_IMAGEN_MB);
    $sql_m = "INSERT INTO maquinaria_detalle
              (activo_id, marca, modelo, numero_serie, kilometraje, foto_motor)
              VALUES (?,?,?,?,?,?)";
    $stmt_m = $conn->prepare($sql_m);
    $m_marca  = nullIfEmpty($_POST['m_marca']        ?? '');
    $m_modelo = nullIfEmpty($_POST['m_modelo']       ?? '');
    $m_serie  = nullIfEmpty($_POST['m_numero_serie'] ?? '');
    $m_km     = nullIfEmpty($_POST['m_kilometraje']  ?? '');
    $stmt_m->bind_param("isssis", $activo_id, $m_marca, $m_modelo, $m_serie, $m_km, $foto_motor);
    $stmt_m->execute();
}

// ── Mobiliario ────────────────────────────────────────────────────────────────
if (str_contains($tipo_norm, 'mobiliario')) {
    $sql_mob = "INSERT INTO mobiliario_detalle
                (activo_id, marca, modelo, numero_items, medida_aprox,
                 edificio, area_departamento, direccion, descripcion)
                VALUES (?,?,?,?,?,?,?,?,?)";
    $stmt_mob = $conn->prepare($sql_mob);
    $mob_marca  = nullIfEmpty($_POST['mob_marca']             ?? '');
    $mob_modelo = nullIfEmpty($_POST['mob_modelo']            ?? '');
    $mob_items  = (int)nullIfEmpty($_POST['mob_numero_items'] ?? 1);
    $mob_medida = nullIfEmpty($_POST['mob_medida_aprox']      ?? '');
    $mob_edif   = nullIfEmpty($_POST['mob_edificio']          ?? '');
    $mob_area   = nullIfEmpty($_POST['mob_area_departamento'] ?? '');
    $mob_dir    = nullIfEmpty($_POST['mob_direccion']         ?? '');
    $mob_desc   = nullIfEmpty($_POST['mob_descripcion']       ?? '');
    $stmt_mob->bind_param("isssissss", $activo_id, $mob_marca, $mob_modelo, $mob_items,
        $mob_medida, $mob_edif, $mob_area, $mob_dir, $mob_desc);
    $stmt_mob->execute();
}

// ── Inmuebles ─────────────────────────────────────────────────────────────────
if (str_contains($tipo_norm, 'inmueble') || str_contains($tipo_norm, 'inmuebles')) {
    $sql_inm = "INSERT INTO inmuebles_detalle
                (activo_id, tipo_inmueble, tipo_posesion, uso, direccion, coordenadas,
                 superficie_terreno, superficie_construida, niveles, valor_terreno,
                 folio_rpp, predial, estatus_legal, responsable_administrativo)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt_inm = $conn->prepare($sql_inm);
    $inm_tipo   = nullIfEmpty($_POST['inm_tipo']                     ?? '');
    $inm_poses  = nullIfEmpty($_POST['inm_tipo_posesion']            ?? '');
    $inm_uso    = nullIfEmpty($_POST['inm_uso']                      ?? '');
    $inm_dir    = nullIfEmpty($_POST['inm_direccion']                ?? '');
    $inm_coord  = nullIfEmpty($_POST['inm_coordenadas']              ?? '');
    $inm_st     = nullIfEmpty($_POST['inm_superficie_terreno']       ?? '');
    $inm_sc     = nullIfEmpty($_POST['inm_superficie_construida']    ?? '');
    $inm_niv    = nullIfEmpty($_POST['inm_niveles']                  ?? '');
    $inm_valor  = nullIfEmpty($_POST['inm_valor_terreno']            ?? '');
    $inm_folio  = nullIfEmpty($_POST['inm_folio_rpp']                ?? '');
    $inm_pred   = nullIfEmpty($_POST['inm_predial']                  ?? '');
    $inm_est    = nullIfEmpty($_POST['inm_estatus_legal']            ?? '');
    $inm_resp   = nullIfEmpty($_POST['inm_responsable_administrativo'] ?? '');
    $stmt_inm->bind_param("isssssddiissss",
        $activo_id, $inm_tipo, $inm_poses, $inm_uso, $inm_dir, $inm_coord,
        $inm_st, $inm_sc, $inm_niv, $inm_valor,
        $inm_folio, $inm_pred, $inm_est, $inm_resp
    );
    $stmt_inm->execute();
}

// ── Herramientas ──────────────────────────────────────────────────────────────
if (str_contains($tipo_norm, 'herramienta') || str_contains($tipo_norm, 'herramientas')) {
    $sql_h = "INSERT INTO herramientas_detalle
              (activo_id, marca, modelo, numero_serie, asignacion, ubicacion_fisica, descripcion)
              VALUES (?,?,?,?,?,?,?)";
    $stmt_h = $conn->prepare($sql_h);
    $h_marca  = nullIfEmpty($_POST['h_marca']          ?? '');
    $h_modelo = nullIfEmpty($_POST['h_modelo']         ?? '');
    $h_serie  = nullIfEmpty($_POST['h_numero_serie']   ?? '');
    $h_asig   = nullIfEmpty($_POST['h_asignacion']     ?? '');
    $h_ubic   = nullIfEmpty($_POST['h_ubicacion_fisica'] ?? '');
    $h_desc   = nullIfEmpty($_POST['h_descripcion']    ?? '');
    $stmt_h->bind_param("issssss", $activo_id, $h_marca, $h_modelo, $h_serie, $h_asig, $h_ubic, $h_desc);
    $stmt_h->execute();
}

// ── TICs ──────────────────────────────────────────────────────────────────────
if (str_contains($tipo_norm, 'tic')) {
    $sql_t = "INSERT INTO tics_detalle
              (activo_id, marca, modelo, numero_serie, sistema_operativo, procesador,
               ram, almacenamiento, office, correo, ubicacion_fisica,
               programas_instalados, complementos)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt_t = $conn->prepare($sql_t);
    $t_marca   = nullIfEmpty($_POST['t_marca']               ?? '');
    $t_modelo  = nullIfEmpty($_POST['t_modelo']              ?? '');
    $t_serie   = nullIfEmpty($_POST['t_numero_serie']        ?? '');
    $t_so      = nullIfEmpty($_POST['t_sistema_operativo']   ?? '');
    $t_proc    = nullIfEmpty($_POST['t_procesador']          ?? '');
    $t_ram     = nullIfEmpty($_POST['t_ram']                 ?? '');
    $t_storage = nullIfEmpty($_POST['t_almacenamiento']      ?? '');
    $t_office  = nullIfEmpty($_POST['t_office']              ?? '');
    $t_correo  = nullIfEmpty($_POST['t_correo']              ?? '');
    $t_ubic    = nullIfEmpty($_POST['t_ubicacion_fisica']    ?? '');
    $t_progs   = nullIfEmpty($_POST['t_programas_instalados'] ?? '');
    $t_comp    = nullIfEmpty($_POST['t_complementos']        ?? '');
    $stmt_t->bind_param("issssssssssss", $activo_id,
        $t_marca, $t_modelo, $t_serie, $t_so, $t_proc,
        $t_ram, $t_storage, $t_office, $t_correo,
        $t_ubic, $t_progs, $t_comp
    );
    $stmt_t->execute();
}

// ─── Documentos ─────────────────────────────────────────────────────────────

$docs = [
    'doc_factura'               => ['carpeta' => 'documentos', 'maxMB' => MAX_DOCUMENTO_MB, 'tipo' => 'factura'],
    'doc_pedimento'             => ['carpeta' => 'documentos', 'maxMB' => MAX_DOCUMENTO_MB, 'tipo' => 'pedimento'],
    'doc_poliza_seguro'         => ['carpeta' => 'documentos', 'maxMB' => MAX_DOCUMENTO_MB, 'tipo' => 'poliza_seguro_mx'],
    'doc_poliza_seguro_usa'     => ['carpeta' => 'documentos', 'maxMB' => MAX_DOCUMENTO_MB, 'tipo' => 'poliza_seguro_usa'],
    'doc_manual'                => ['carpeta' => 'documentos', 'maxMB' => MAX_DOCUMENTO_MB, 'tipo' => 'manual_usuario'],
    'doc_manual_mantenimiento'  => ['carpeta' => 'documentos', 'maxMB' => MAX_DOCUMENTO_MB, 'tipo' => 'manual_mantenimiento'],
    'doc_catalogo_refacciones'  => ['carpeta' => 'documentos', 'maxMB' => MAX_CATALOGO_MB, 'tipo' => 'catalogo_refacciones'],
    'doc_contrato'              => ['carpeta' => 'documentos', 'maxMB' => MAX_DOCUMENTO_MB, 'tipo' => 'contrato'],
];

$stmt_doc = $conn->prepare(
    "INSERT INTO activos_documentos (activo_id, tipo_documento, ruta_archivo, nombre_original, fecha_subida)
     VALUES (?, ?, ?, ?, NOW())"
);

foreach ($docs as $inputName => $cfg) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) continue;
    $ruta = subirArchivo($inputName, $cfg['carpeta'], $cfg['maxMB']);
    if ($ruta) {
        $nombre_original = $_FILES[$inputName]['name'];
        $stmt_doc->bind_param("isss", $activo_id, $cfg['tipo'], $ruta, $nombre_original);
        $stmt_doc->execute();
    }
}

// ─── Imágenes ────────────────────────────────────────────────────────────────

$stmt_img = $conn->prepare(
    "INSERT INTO activos_imagenes (activo_id, tipo_imagen, ruta_archivo, fecha_subida)
     VALUES (?, ?, ?, NOW())"
);

$fotos_generales = subirArchivoMultiple('img_foto_general', 'fotos', MAX_IMAGEN_MB);
foreach ($fotos_generales as $ruta) {
    $tipo = 'foto_general';
    $stmt_img->bind_param("iss", $activo_id, $tipo, $ruta);
    $stmt_img->execute();
}

$foto_placa = subirArchivo('img_foto_placa', 'fotos', MAX_IMAGEN_MB);
if ($foto_placa) {
    $tipo = 'foto_placa';
    $stmt_img->bind_param("iss", $activo_id, $tipo, $foto_placa);
    $stmt_img->execute();
}

$foto_serie = subirArchivo('img_foto_numero_serie', 'fotos', MAX_IMAGEN_MB);
if ($foto_serie) {
    $tipo = 'foto_numero_serie';
    $stmt_img->bind_param("iss", $activo_id, $tipo, $foto_serie);
    $stmt_img->execute();
}

// ─── Documentos extra (fiscal + extra) ──────────────────────────────────────

if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
    $total = count($_FILES['documentos']['name']);
    $tipos_post = $_POST['documentos_tipo'] ?? [];
    $tipos_validos = ['expediente_predial', 'extra', 'factura', 'pedimento',
                  'poliza_seguro_mx', 'poliza_seguro_usa', 'manual_usuario',
                  'manual_mantenimiento', 'catalogo_refacciones', 'contrato'];
    $stmt_docx = $conn->prepare(
        "INSERT INTO activos_documentos (activo_id, tipo_documento, ruta_archivo, nombre_original, fecha_subida)
         VALUES (?, ?, ?, ?, NOW())"
    );
    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['documentos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        
        if ($_FILES['documentos']['size'][$i] > MAX_DOCUMENTO_MB * 1024 * 1024) {
            error_log("Documento extra excede " . MAX_DOCUMENTO_MB . " MB: " . $_FILES['documentos']['name'][$i]);
            continue;
        }
        
        $ext     = strtolower(pathinfo($_FILES['documentos']['name'][$i], PATHINFO_EXTENSION));
        $nom     = uniqid('', true) . '.' . $ext;
        $dir     = $_SERVER['DOCUMENT_ROOT'] . '/uploads/documentos/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $dest = $dir . $nom;
        if (move_uploaded_file($_FILES['documentos']['tmp_name'][$i], $dest)) {
            $ruta_doc = '/uploads/documentos/' . $nom;
            $nom_orig = $_FILES['documentos']['name'][$i];
            $tipo_doc = isset($tipos_post[$i]) && in_array($tipos_post[$i], $tipos_validos)
            ? $tipos_post[$i] : 'extra';
            $stmt_docx->bind_param("isss", $activo_id, $tipo_doc, $ruta_doc, $nom_orig);
            $stmt_docx->execute();
        }
    }
}

// ─── Redirigir ───────────────────────────────────────────────────────────────

header("Location: details_activo.php?id={$activo_id}&success=1");
exit;

